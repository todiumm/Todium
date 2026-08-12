<?php

declare(strict_types=1);

namespace pocketmine\vanilla;

use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\entity\vanilla\projectile\BlazeFireball;
use pocketmine\entity\vanilla\projectile\GhastFireball;
use pocketmine\entity\vanilla\projectile\WitchPotion;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\SpawnEgg;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\vanilla\item\FishBucket;
use pocketmine\vanilla\item\Lead;
use pocketmine\vanilla\listener\EventListener;
use pocketmine\vanilla\listener\LeashListener;
use pocketmine\vanilla\listener\RidingListener;
use pocketmine\vanilla\listener\TradeListener;
use pocketmine\vanilla\loot\LootManager;
use pocketmine\vanilla\spawner\SpawnerTask;
use pocketmine\world\World;
use Symfony\Component\Filesystem\Path;
use function class_exists;

/**
 * Todium's integrated Vanilla Mobs system.
 *
 * Replaces the AZVanillaMobs plugin — provides 70+ vanilla mobs with custom AI,
 * natural spawning, loot tables, trading, leashing, and riding, all built into
 * the Todium server core (no plugin required).
 *
 * Initialized by {@see Server} during bootstrap when `todium.vanilla-mobs: true`
 * in pocketmine.yml (default).
 */
final class VanillaMobsModule{
        use SingletonTrait;

        /** @var array<string, list<class-string>> */
        public array $spawnerLists = [
                'nether' => [],
                'the_end' => [],
                'overworld_hostile' => [],
                'overworld_passive' => []
        ];

        private Server $server;
        private Config $config;
        private string $dataFolder;
        private TaskScheduler $scheduler;
        private bool $enabled = false;

        public function __construct(){
                // Singleton — actual initialization happens in initialize()
        }

        /**
         * Initializes the vanilla mobs system.
         *
         * @param string $dataFolder Where to store config and data (typically plugin_data/Todium/)
         */
        public function initialize(Server $server, string $dataFolder) : void{
                if($this->enabled){
                        return;
                }
                $this->server = $server;
                $this->dataFolder = $dataFolder;
                $this->scheduler = new TaskScheduler("TodiumVanillaMobs");

                // Load config
                if(!file_exists($dataFolder)){
                        @mkdir($dataFolder, 0777, true);
                }
                $configPath = Path::join($dataFolder, "config.yml");
                if(!file_exists($configPath)){
                        $defaultConfig = Path::join(\pocketmine\RESOURCE_PATH, "vanilla_mobs_config.yml");
                        if(file_exists($defaultConfig)){
                                copy($defaultConfig, $configPath);
                        }
                }
                $this->config = new Config($configPath, Config::YAML);

                // Register listeners directly via HandlerList (bypasses PluginManager
                // which requires an enabled Plugin). We use RegisteredListener directly.
                $this->registerListener(new LootManager());
                $this->registerListener(new EventListener($this));
                $this->registerListener(new TradeListener($this));
                $this->registerListener(new LeashListener($this));
                $this->registerListener(new RidingListener($this));

                // Register commands
                // First register the permissions (PM5 requires permissions to exist
                //before Command::setPermission() can reference them)
                $permManager = \pocketmine\permission\PermissionManager::getInstance();
                $permManager->addPermission(new \pocketmine\permission\Permission("azvanillamobs.command.summon", "Allows summoning of vanilla mobs", \pocketmine\permission\PermissionParser::DEFAULT_OP));
                $permManager->addPermission(new \pocketmine\permission\Permission("azvanillamobs.command.kill", "Allows killing of vanilla mobs", \pocketmine\permission\PermissionParser::DEFAULT_OP));

                $map = $server->getCommandMap();
                $cmd = $map->getCommand("summon");
                if($cmd !== null){
                        $map->unregister($cmd);
                }
                $map->register("VanillaMobs", new \pocketmine\vanilla\command\SummonCommand($this));
                $map->register("VanillaMobs", new \pocketmine\vanilla\command\KillCommand($this));

                // Register entities
                $this->registerEntities();

                // Start spawner task
                $this->scheduler->scheduleRepeatingTask(new SpawnerTask($this), 20);

                // Start leash tick task
                $this->scheduler->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function() : void{
                        LeashListener::tickLeashes();
                }), 2);

                $this->enabled = true;
                $server->getLogger()->info("Vanilla Mobs system enabled (70+ mobs registered)");
        }

        /**
         * Registers event listeners for a Listener object by scanning its public
         * methods for ones that take exactly one Event subclass parameter.
         * Bypasses PluginManager (which requires an enabled Plugin) by registering
         * directly with HandlerList.
         *
         * This mirrors PM5's PluginManager::registerEvents() behavior — it does NOT
         * require @EventHandler annotations. Any public non-static method with exactly
         * one parameter that is an Event subclass is automatically registered.
         */
        private function registerListener(object $listener) : void{
                $reflection = new \ReflectionClass($listener);
                foreach($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method){
                        if($method->isStatic()){
                                continue;
                        }
                        //Skip methods from the base Listener interface or Object
                        if($method->getDeclaringClass()->getName() === \pocketmine\event\Listener::class){
                                continue;
                        }
                        $params = $method->getParameters();
                        if(count($params) !== 1){
                                continue;
                        }
                        $paramType = $params[0]->getType();
                        if($paramType === null){
                                continue;
                        }
                        $eventClass = $paramType instanceof \ReflectionNamedType ? $paramType->getName() : null;
                        if($eventClass === null || !class_exists($eventClass)){
                                continue;
                        }
                        if(!is_subclass_of($eventClass, \pocketmine\event\Event::class)){
                                continue;
                        }

                        //Check for @notHandler annotation (skip if present)
                        $docComment = $method->getDocComment();
                        if($docComment !== false && str_contains($docComment, "@notHandler")){
                                continue;
                        }

                        //Register the handler
                        $closure = $method->getClosure($listener);
                        $registeredListener = new \pocketmine\event\RegisteredListener(
                                $closure,
                                \pocketmine\event\EventPriority::NORMAL,
                                $this->getDummyPlugin(),
                                false,
                                new \pocketmine\timings\TimingsHandler("TodiumVanillaMobs: " . $method->getName())
                        );
                        \pocketmine\event\HandlerListManager::global()->getListFor($eventClass)->register($registeredListener);
                }
        }

        /**
         * Creates a minimal Plugin wrapper for registering events without a real plugin.
         */
        private function getDummyPlugin() : \pocketmine\plugin\Plugin{
                static $dummy = null;
                if($dummy === null){
                        // Create a PluginDescription for the dummy plugin
                        $desc = new \pocketmine\plugin\PluginDescription([
                                "name" => "TodiumVanillaMobs",
                                "version" => "1.0.0",
                                "main" => "TodiumVanillaMobs",
                                "api" => ["5.0.0"],
                        ]);
                        $dummy = new class(
                                new \pocketmine\plugin\ScriptPluginLoader(),
                                $this->server,
                                $desc,
                                $this->dataFolder,
                                $this->dataFolder,
                                new \pocketmine\plugin\DiskResourceProvider($this->dataFolder)
                        ) extends \pocketmine\plugin\PluginBase{};
                }
                return $dummy;
        }

        public function getServer() : Server{
                return $this->server;
        }

        public function getConfig() : Config{
                return $this->config;
        }

        public function getDataFolder() : string{
                return $this->dataFolder;
        }

        public function getScheduler() : TaskScheduler{
                return $this->scheduler;
        }

        public function getLogger() : \pocketmine\utils\MainLogger{
                return $this->server->getLogger();
        }

        /**
         * Registers all vanilla mob entities with EntityFactory and creates spawn eggs.
         */
        private function registerEntities() : void{
                // Register projectiles
                EntityFactory::getInstance()->register(GhastFireball::class, function(World $world, CompoundTag $nbt) : \pocketmine\entity\Entity{
                        return new GhastFireball(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
                }, ['GhastFireball', 'minecraft:fireball']);

                EntityFactory::getInstance()->register(BlazeFireball::class, function(World $world, CompoundTag $nbt) : \pocketmine\entity\Entity{
                        return new BlazeFireball(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
                }, ['BlazeFireball', 'minecraft:small_fireball']);

                EntityFactory::getInstance()->register(WitchPotion::class, function(World $world, CompoundTag $nbt) : \pocketmine\entity\Entity{
                        return new WitchPotion(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
                }, ['WitchPotion', 'minecraft:splash_potion']);

                // Mob registration helper
                $register = function(string $class, string $name, string $id, string $category) : void{
                        EntityFactory::getInstance()->register($class, function(World $world, CompoundTag $nbt) use($class) : \pocketmine\entity\Entity{
                                return new $class(EntityDataHelper::parseLocation($nbt, $world), $nbt);
                        }, [$name, $id]);
                        $this->spawnerLists[$category][] = $class;

                        // Create spawn egg
                        $this->createSpawnEgg($class, $name, $id);
                };

                // Overworld hostile
                $register(\pocketmine\entity\vanilla\overworld\Skeleton::class, 'Skeleton', 'minecraft:skeleton', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Zombie::class, 'Zombie', 'minecraft:zombie', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Creeper::class, 'Creeper', 'minecraft:creeper', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Spider::class, 'Spider', 'minecraft:spider', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\CaveSpider::class, 'CaveSpider', 'minecraft:cave_spider', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Slime::class, 'Slime', 'minecraft:slime', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Silverfish::class, 'Silverfish', 'minecraft:silverfish', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Witch::class, 'Witch', 'minecraft:witch', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\ZombieVillager::class, 'ZombieVillager', 'minecraft:zombie_villager', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Drowned::class, 'Drowned', 'minecraft:drowned', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Husk::class, 'Husk', 'minecraft:husk', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Stray::class, 'Stray', 'minecraft:stray', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Phantom::class, 'Phantom', 'minecraft:phantom', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Vindicator::class, 'Vindicator', 'minecraft:vindicator', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Evoker::class, 'Evoker', 'minecraft:evoker', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Pillager::class, 'Pillager', 'minecraft:pillager', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Ravager::class, 'Ravager', 'minecraft:ravager', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Vex::class, 'Vex', 'minecraft:vex', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Guardian::class, 'Guardian', 'minecraft:guardian', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\ElderGuardian::class, 'ElderGuardian', 'minecraft:elder_guardian', 'overworld_hostile');
                $register(\pocketmine\entity\vanilla\overworld\Warden::class, 'Warden', 'minecraft:warden', 'overworld_hostile');

                // Overworld passive
                $register(\pocketmine\entity\vanilla\overworld\Cow::class, 'Cow', 'minecraft:cow', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Pig::class, 'Pig', 'minecraft:pig', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Sheep::class, 'Sheep', 'minecraft:sheep', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Chicken::class, 'Chicken', 'minecraft:chicken', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Wolf::class, 'Wolf', 'minecraft:wolf', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Ocelot::class, 'Ocelot', 'minecraft:ocelot', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Cat::class, 'Cat', 'minecraft:cat', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Horse::class, 'Horse', 'minecraft:horse', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Donkey::class, 'Donkey', 'minecraft:donkey', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Mule::class, 'Mule', 'minecraft:mule', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Llama::class, 'Llama', 'minecraft:llama', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\TraderLlama::class, 'TraderLlama', 'minecraft:trader_llama', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Fox::class, 'Fox', 'minecraft:fox', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Panda::class, 'Panda', 'minecraft:panda', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Turtle::class, 'Turtle', 'minecraft:turtle', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Dolphin::class, 'Dolphin', 'minecraft:dolphin', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Squid::class, 'Squid', 'minecraft:squid', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\GlowSquid::class, 'GlowSquid', 'minecraft:glow_squid', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Bat::class, 'Bat', 'minecraft:bat', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Villager::class, 'Villager', 'minecraft:villager_v2', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\WanderingTrader::class, 'WanderingTrader', 'minecraft:wandering_trader', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\IronGolem::class, 'IronGolem', 'minecraft:iron_golem', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\SnowGolem::class, 'SnowGolem', 'minecraft:snow_golem', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Axolotl::class, 'Axolotl', 'minecraft:axolotl', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Goat::class, 'Goat', 'minecraft:goat', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Frog::class, 'Frog', 'minecraft:frog', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Tadpole::class, 'Tadpole', 'minecraft:tadpole', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Cod::class, 'Cod', 'minecraft:cod', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Salmon::class, 'Salmon', 'minecraft:salmon', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Pufferfish::class, 'Pufferfish', 'minecraft:pufferfish', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\TropicalFish::class, 'TropicalFish', 'minecraft:tropicalfish', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Camel::class, 'Camel', 'minecraft:camel', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Sniffer::class, 'Sniffer', 'minecraft:sniffer', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Allay::class, 'Allay', 'minecraft:allay', 'overworld_passive');
                $register(\pocketmine\entity\vanilla\overworld\Bee::class, 'Bee', 'minecraft:bee', 'overworld_passive');

                // Nether
                $register(\pocketmine\entity\vanilla\nether\ZombifiedPiglin::class, 'ZombifiedPiglin', 'minecraft:zombie_pigman', 'nether');
                $register(\pocketmine\entity\vanilla\nether\Piglin::class, 'Piglin', 'minecraft:piglin', 'nether');
                $register(\pocketmine\entity\vanilla\nether\PiglinBrute::class, 'PiglinBrute', 'minecraft:piglin_brute', 'nether');
                $register(\pocketmine\entity\vanilla\nether\Hoglin::class, 'Hoglin', 'minecraft:hoglin', 'nether');
                $register(\pocketmine\entity\vanilla\nether\Zoglin::class, 'Zoglin', 'minecraft:zoglin', 'nether');
                $register(\pocketmine\entity\vanilla\nether\Ghast::class, 'Ghast', 'minecraft:ghast', 'nether');
                $register(\pocketmine\entity\vanilla\nether\Blaze::class, 'Blaze', 'minecraft:blaze', 'nether');
                $register(\pocketmine\entity\vanilla\nether\MagmaCube::class, 'MagmaCube', 'minecraft:magma_cube', 'nether');
                $register(\pocketmine\entity\vanilla\nether\WitherSkeleton::class, 'WitherSkeleton', 'minecraft:wither_skeleton', 'nether');
                $register(\pocketmine\entity\vanilla\nether\Strider::class, 'Strider', 'minecraft:strider', 'nether');

                // The End
                $register(\pocketmine\entity\vanilla\the_end\Enderman::class, 'Enderman', 'minecraft:enderman', 'the_end');
                $this->spawnerLists['overworld_hostile'][] = \pocketmine\entity\vanilla\the_end\Enderman::class;
                $register(\pocketmine\entity\vanilla\the_end\Endermite::class, 'Endermite', 'minecraft:endermite', 'the_end');
                $register(\pocketmine\entity\vanilla\the_end\Shulker::class, 'Shulker', 'minecraft:shulker', 'the_end');
                EntityFactory::getInstance()->register(\pocketmine\entity\vanilla\the_end\EnderDragon::class, function(World $world, CompoundTag $nbt) : \pocketmine\entity\Entity{
                        return new \pocketmine\entity\vanilla\the_end\EnderDragon(EntityDataHelper::parseLocation($nbt, $world), $nbt);
                }, ['EnderDragon', 'minecraft:ender_dragon']);

                // Register custom items (saddle, lead, fish buckets)
                $this->registerCustomItems();
        }

        private function createSpawnEgg(string $class, string $name, string $id) : void{
                $eggMapping = [
                        'minecraft:skeleton' => 'skeleton_spawn_egg',
                        'minecraft:zombie' => 'zombie_spawn_egg',
                        'minecraft:creeper' => 'creeper_spawn_egg',
                        'minecraft:spider' => 'spider_spawn_egg',
                        'minecraft:cave_spider' => 'cave_spider_spawn_egg',
                        'minecraft:slime' => 'slime_spawn_egg',
                        'minecraft:silverfish' => 'silverfish_spawn_egg',
                        'minecraft:witch' => 'witch_spawn_egg',
                        'minecraft:zombie_villager' => 'zombie_villager_spawn_egg',
                        'minecraft:drowned' => 'drowned_spawn_egg',
                        'minecraft:husk' => 'husk_spawn_egg',
                        'minecraft:stray' => 'stray_spawn_egg',
                        'minecraft:phantom' => 'phantom_spawn_egg',
                        'minecraft:vindicator' => 'vindicator_spawn_egg',
                        'minecraft:evoker' => 'evoker_spawn_egg',
                        'minecraft:pillager' => 'pillager_spawn_egg',
                        'minecraft:ravager' => 'ravager_spawn_egg',
                        'minecraft:vex' => 'vex_spawn_egg',
                        'minecraft:guardian' => 'guardian_spawn_egg',
                        'minecraft:elder_guardian' => 'elder_guardian_spawn_egg',
                        'minecraft:cow' => 'cow_spawn_egg',
                        'minecraft:pig' => 'pig_spawn_egg',
                        'minecraft:sheep' => 'sheep_spawn_egg',
                        'minecraft:chicken' => 'chicken_spawn_egg',
                        'minecraft:wolf' => 'wolf_spawn_egg',
                        'minecraft:ocelot' => 'ocelot_spawn_egg',
                        'minecraft:cat' => 'cat_spawn_egg',
                        'minecraft:horse' => 'horse_spawn_egg',
                        'minecraft:donkey' => 'donkey_spawn_egg',
                        'minecraft:mule' => 'mule_spawn_egg',
                        'minecraft:llama' => 'llama_spawn_egg',
                        'minecraft:trader_llama' => 'trader_llama_spawn_egg',
                        'minecraft:fox' => 'fox_spawn_egg',
                        'minecraft:panda' => 'panda_spawn_egg',
                        'minecraft:turtle' => 'turtle_spawn_egg',
                        'minecraft:dolphin' => 'dolphin_spawn_egg',
                        'minecraft:squid' => 'squid_spawn_egg',
                        'minecraft:glow_squid' => 'glow_squid_spawn_egg',
                        'minecraft:bat' => 'bat_spawn_egg',
                        'minecraft:villager_v2' => 'villager_spawn_egg',
                        'minecraft:wandering_trader' => 'wandering_trader_spawn_egg',
                        'minecraft:axolotl' => 'axolotl_spawn_egg',
                        'minecraft:goat' => 'goat_spawn_egg',
                        'minecraft:frog' => 'frog_spawn_egg',
                        'minecraft:tadpole' => 'tadpole_spawn_egg',
                        'minecraft:cod' => 'cod_spawn_egg',
                        'minecraft:salmon' => 'salmon_spawn_egg',
                        'minecraft:pufferfish' => 'pufferfish_spawn_egg',
                        'minecraft:tropicalfish' => 'tropical_fish_spawn_egg',
                        'minecraft:camel' => 'camel_spawn_egg',
                        'minecraft:sniffer' => 'sniffer_spawn_egg',
                        'minecraft:allay' => 'allay_spawn_egg',
                        'minecraft:bee' => 'bee_spawn_egg',
                        'minecraft:zombie_pigman' => 'zombie_pigman_spawn_egg',
                        'minecraft:piglin' => 'piglin_spawn_egg',
                        'minecraft:piglin_brute' => 'piglin_brute_spawn_egg',
                        'minecraft:hoglin' => 'hoglin_spawn_egg',
                        'minecraft:zoglin' => 'zoglin_spawn_egg',
                        'minecraft:ghast' => 'ghast_spawn_egg',
                        'minecraft:blaze' => 'blaze_spawn_egg',
                        'minecraft:magma_cube' => 'magma_cube_spawn_egg',
                        'minecraft:wither_skeleton' => 'wither_skeleton_spawn_egg',
                        'minecraft:strider' => 'strider_spawn_egg',
                        'minecraft:enderman' => 'enderman_spawn_egg',
                        'minecraft:endermite' => 'endermite_spawn_egg',
                        'minecraft:shulker' => 'shulker_spawn_egg',
                        'minecraft:warden' => 'warden_spawn_egg',
                ];

                $parseId = $eggMapping[$id] ?? null;
                if($parseId === null){
                        return;
                }

                try{
                        $oldEgg = StringToItemParser::getInstance()->parse($parseId);
                        if($oldEgg !== null){
                                CreativeInventory::getInstance()->remove($oldEgg);
                        }
                }catch(\Exception $e){}

                $typeId = ItemTypeIds::newId();
                $eggItem = new class(new ItemIdentifier($typeId), "§r§e" . $name . " Spawn Egg") extends SpawnEgg{
                        public string $entityClass;
                        protected function createEntity(World $world, \pocketmine\math\Vector3 $pos, float $yaw, float $pitch) : \pocketmine\entity\Entity{
                                $c = $this->entityClass;
                                return new $c(Location::fromObject($pos, $world, $yaw, $pitch));
                        }
                };
                $eggItem->entityClass = $class;

                try{
                        \pocketmine\world\format\io\GlobalItemDataHandlers::getSerializer()->map($eggItem, fn() => new \pocketmine\data\bedrock\item\SavedItemData("minecraft:" . $parseId));
                        \pocketmine\world\format\io\GlobalItemDataHandlers::getDeserializer()->map("minecraft:" . $parseId, fn() => clone $eggItem);
                        CreativeInventory::getInstance()->add($eggItem, \pocketmine\inventory\CreativeCategory::NATURE);
                        StringToItemParser::getInstance()->override($parseId, fn() => clone $eggItem);
                }catch(\Exception $e){}
        }

        private function registerCustomItems() : void{
                // Saddle
                try{
                        $saddleItem = new Item(new ItemIdentifier(ItemTypeIds::newId()), "Saddle");
                        \pocketmine\world\format\io\GlobalItemDataHandlers::getSerializer()->map($saddleItem, fn() => new \pocketmine\data\bedrock\item\SavedItemData("minecraft:saddle"));
                        \pocketmine\world\format\io\GlobalItemDataHandlers::getDeserializer()->map("minecraft:saddle", fn() => clone $saddleItem);
                        CreativeInventory::getInstance()->add($saddleItem, \pocketmine\inventory\CreativeCategory::EQUIPMENT);
                        StringToItemParser::getInstance()->override("saddle", fn() => clone $saddleItem);
                }catch(\Exception $e){}

                // Lead
                try{
                        $leadItem = new Lead(new ItemIdentifier(ItemTypeIds::newId()), "Lead");
                        \pocketmine\world\format\io\GlobalItemDataHandlers::getSerializer()->map($leadItem, fn() => new \pocketmine\data\bedrock\item\SavedItemData("minecraft:lead"));
                        \pocketmine\world\format\io\GlobalItemDataHandlers::getDeserializer()->map("minecraft:lead", fn() => clone $leadItem);
                        CreativeInventory::getInstance()->add($leadItem);
                        StringToItemParser::getInstance()->override("lead", fn() => clone $leadItem);
                }catch(\Exception $e){}

                // Fish buckets
                $registerBucket = function(string $class, string $name, string $id, string $parseId) : void{
                        try{
                                $bucketItem = new FishBucket(new ItemIdentifier(ItemTypeIds::newId()), $name, $class);
                                \pocketmine\world\format\io\GlobalItemDataHandlers::getSerializer()->map($bucketItem, fn() => new \pocketmine\data\bedrock\item\SavedItemData("minecraft:" . $id));
                                \pocketmine\world\format\io\GlobalItemDataHandlers::getDeserializer()->map("minecraft:" . $id, fn() => clone $bucketItem);
                                CreativeInventory::getInstance()->add($bucketItem, \pocketmine\inventory\CreativeCategory::NATURE);
                                StringToItemParser::getInstance()->override($parseId, fn() => clone $bucketItem);
                        }catch(\Exception $e){}
                };
                $registerBucket(\pocketmine\entity\vanilla\overworld\Cod::class, "Cod Bucket", "cod_bucket", "cod_bucket");
                $registerBucket(\pocketmine\entity\vanilla\overworld\Salmon::class, "Salmon Bucket", "salmon_bucket", "salmon_bucket");
                $registerBucket(\pocketmine\entity\vanilla\overworld\Pufferfish::class, "Pufferfish Bucket", "pufferfish_bucket", "pufferfish_bucket");
                $registerBucket(\pocketmine\entity\vanilla\overworld\TropicalFish::class, "Tropical Fish Bucket", "tropical_fish_bucket", "tropical_fish_bucket");
                $registerBucket(\pocketmine\entity\vanilla\overworld\Axolotl::class, "Axolotl Bucket", "axolotl_bucket", "axolotl_bucket");
                $registerBucket(\pocketmine\entity\vanilla\overworld\Tadpole::class, "Tadpole Bucket", "tadpole_bucket", "tadpole_bucket");
        }

        public function isEnabled() : bool{
                return $this->enabled;
        }
}
