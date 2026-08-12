<?php

declare(strict_types=1);

namespace pocketmine\world\portal;

use pocketmine\Server;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\generator\hell\Nether;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\portal\EnhancedNether;
use pocketmine\world\portal\listener\EventListener;
use pocketmine\world\portal\manager\PortalManager;
use pocketmine\world\portal\manager\PortalTeleporter;
use Symfony\Component\Filesystem\Path;

/**
 * Todium's integrated Nether Portal system.
 *
 * Replaces the AZNetherPortal plugin — provides functional Nether portals with
 * SQLite persistence, automatic Nether world generation, cooldown, teleport
 * delay, and random teleport destination. Built into the Todium server core.
 *
 * Initialized by {@see Server} during bootstrap when `todium.nether-portals: true`
 * in pocketmine.yml (default).
 */
final class PortalModule{
        use SingletonTrait;

        private Server $server;
        private Config $config;
        private string $dataFolder;
        private TaskScheduler $scheduler;
        private PortalManager $portalManager;
        private PortalTeleporter $portalTeleporter;
        private bool $enabled = false;

        public function __construct(){
                // Singleton — actual initialization happens in initialize()
        }

        /**
         * Initializes the portal system.
         *
         * @param string $dataFolder Where to store config and data
         */
        public function initialize(Server $server, string $dataFolder) : void{
                if($this->enabled){
                        return;
                }
                $this->server = $server;
                $this->dataFolder = $dataFolder;
                $this->scheduler = new TaskScheduler("TodiumNetherPortal");

                if(!file_exists($dataFolder)){
                        @mkdir($dataFolder, 0777, true);
                }

                // Load config
                $configPath = Path::join($dataFolder, "config.yml");
                if(!file_exists($configPath)){
                        $defaultConfig = Path::join(\pocketmine\RESOURCE_PATH, "nether_portal_config.yml");
                        if(file_exists($defaultConfig)){
                                copy($defaultConfig, $configPath);
                        }
                }
                $this->config = new Config($configPath, Config::YAML);

                // Ensure Nether world exists
                $netherName = $this->config->get("nether_world", "nether");
                $wm = $server->getWorldManager();

                if(!$wm->isWorldLoaded($netherName)){
                        if($wm->isWorldGenerated($netherName)){
                                $wm->loadWorld($netherName);
                                $server->getLogger()->info("Loaded Nether world: " . $netherName);
                        }else{
                                //Use the vanilla Nether generator (the EnhancedNether structures are
                                //applied as populators at runtime — the world itself uses vanilla terrain)
                                $wm->generateWorld($netherName, WorldCreationOptions::create()->setGeneratorClass(Nether::class));
                                $server->getLogger()->info("Generated and loaded new Nether world: " . $netherName);
                        }
                }

                // Initialize managers
                $this->portalManager = new PortalManager($this);
                $this->portalTeleporter = new PortalTeleporter($this);

                // Register events directly via HandlerList (bypasses PluginManager)
                $this->registerListener(new EventListener($this));

                // Start teleport tick task
                $this->scheduler->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function() : void{
                        $this->portalTeleporter->tickPortals();
                }), 10);

                $this->enabled = true;
                $server->getLogger()->info("Nether Portal system enabled");
        }

        /**
         * Registers event listeners by scanning public methods for Event parameters.
         * Mirrors PM5's PluginManager::registerEvents() — does NOT require @EventHandler.
         */
        private function registerListener(object $listener) : void{
                $reflection = new \ReflectionClass($listener);
                foreach($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method){
                        if($method->isStatic()){
                                continue;
                        }
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
                        $docComment = $method->getDocComment();
                        if($docComment !== false && str_contains($docComment, "@notHandler")){
                                continue;
                        }
                        $closure = $method->getClosure($listener);
                        $registeredListener = new \pocketmine\event\RegisteredListener(
                                $closure,
                                \pocketmine\event\EventPriority::NORMAL,
                                $this->getDummyPlugin($this->server, $this->dataFolder),
                                false,
                                new \pocketmine\timings\TimingsHandler("TodiumNetherPortal: " . $method->getName())
                        );
                        \pocketmine\event\HandlerListManager::global()->getListFor($eventClass)->register($registeredListener);
                }
        }

        /**
         * Creates a minimal Plugin wrapper for registering events.
         */
        private function getDummyPlugin(Server $server, string $dataFolder) : \pocketmine\plugin\Plugin{
                static $dummy = null;
                if($dummy === null){
                        $desc = new \pocketmine\plugin\PluginDescription([
                                "name" => "TodiumNetherPortal",
                                "version" => "1.0.0",
                                "main" => "TodiumNetherPortal",
                                "api" => ["5.0.0"],
                        ]);
                        $dummy = new class(
                                new \pocketmine\plugin\ScriptPluginLoader(),
                                $server,
                                $desc,
                                $dataFolder,
                                $dataFolder,
                                new \pocketmine\plugin\DiskResourceProvider($dataFolder)
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

        public function getPortalManager() : PortalManager{
                return $this->portalManager;
        }

        public function getPortalTeleporter() : PortalTeleporter{
                return $this->portalTeleporter;
        }

        public function isEnabled() : bool{
                return $this->enabled;
        }
}
