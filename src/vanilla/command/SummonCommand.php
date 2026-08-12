<?php

declare(strict_types=1);

namespace pocketmine\vanilla\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;


use pocketmine\vanilla\VanillaMobsModule;

class SummonCommand extends Command  {

    private VanillaMobsModule $module;

    public function __construct(VanillaMobsModule $module) {
        
        parent::__construct("summon", "Summon an AZVanillaMob", "/summon <mob> [amount]", ["azsummon"]);
        $this->setPermission("azvanillamobs.command.summon");
        $this->module = $module;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) return false;
        if (!$sender instanceof Player) {
            $sender->sendMessage("Please use this command in-game.");
            return false;
        }

        if (count($args) < 1) {
            $sender->sendMessage("Usage: /summon <mob> [amount]");
            return false;
        }

        $mobName = strtolower($args[0]);
        $amount = isset($args[1]) ? max(1, (int)$args[1]) : 1;

        $targetClass = null;
        foreach ($this->module->spawnerLists as $list) {
            foreach ($list as $class) {
                $path = explode('\\', $class);
                $name = strtolower(array_pop($path));
                if ($name === $mobName || "minecraft:" . $name === $mobName) {
                    $targetClass = $class;
                    break 2;
                }
            }
        }

        if ($targetClass === null) {
            $sender->sendMessage("§cMob '$mobName' not found!");
            return false;
        }

        $aquaticClasses = [
            \pocketmine\entity\vanilla\overworld\Axolotl::class,
            \pocketmine\entity\vanilla\overworld\Dolphin::class,
            \pocketmine\entity\vanilla\overworld\GlowSquid::class,
            \pocketmine\entity\vanilla\overworld\Squid::class,
            \pocketmine\entity\vanilla\overworld\Tadpole::class,
            \pocketmine\entity\vanilla\overworld\Turtle::class,
            \pocketmine\entity\vanilla\overworld\Guardian::class,
            \pocketmine\entity\vanilla\overworld\ElderGuardian::class,
            \pocketmine\entity\vanilla\overworld\Cod::class,
            \pocketmine\entity\vanilla\overworld\Salmon::class,
            \pocketmine\entity\vanilla\overworld\Pufferfish::class,
            \pocketmine\entity\vanilla\overworld\TropicalFish::class,
        ];
        $isAquatic = in_array($targetClass, $aquaticClasses, true);

        $flyingClasses = [
            \pocketmine\entity\vanilla\overworld\Vex::class,
            \pocketmine\entity\vanilla\overworld\Allay::class,
            \pocketmine\entity\vanilla\overworld\Bat::class,
            \pocketmine\entity\vanilla\overworld\Bee::class,
            \pocketmine\entity\vanilla\overworld\Phantom::class,
            \pocketmine\entity\vanilla\nether\Ghast::class,
            \pocketmine\entity\vanilla\nether\Blaze::class,
            \pocketmine\entity\vanilla\the_end\EnderDragon::class,
        ];
        $isFlying = in_array($targetClass, $flyingClasses, true);

        for ($i = 0; $i < $amount; $i++) {
            $spawnPos = clone $sender->getPosition();

            $isSenderInWater = $sender->getWorld()->getBlock($sender->getPosition()) instanceof \pocketmine\block\Water || $sender->getWorld()->getBlock($sender->getPosition()->add(0, 1, 0)) instanceof \pocketmine\block\Water;
            if ($isAquatic && !$isSenderInWater) {

                $foundWater = null;
                for ($x = -8; $x <= 8; $x++) {
                    for ($y = -4; $y <= 4; $y++) {
                        for ($z = -8; $z <= 8; $z++) {
                            $pos = $sender->getPosition()->add($x, $y, $z);
                            if ($sender->getWorld()->getBlock($pos) instanceof \pocketmine\block\Water) {
                                $foundWater = $pos;
                                break 3;
                            }
                        }
                    }
                }
                if ($foundWater !== null) {
                    $spawnPos = $foundWater->add(0.5, 0.5, 0.5);
                }
            } elseif ($isFlying) {
                $spawnPos->y += 2.0;
            }

            $entity = new $targetClass(Location::fromObject($spawnPos, $sender->getWorld(), mt_rand(0, 360), 0));
            $entity->spawnToAll();
        }

        $sender->sendMessage("§aSummoned $amount x $mobName.");
        return true;
    }
}
