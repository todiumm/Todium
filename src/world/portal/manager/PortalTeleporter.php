<?php

declare(strict_types=1);

namespace pocketmine\world\portal\manager;

use pocketmine\world\World;
use pocketmine\world\Position;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\NetherPortal;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\world\particle\PortalParticle;
use pocketmine\math\Axis;
use pocketmine\world\portal\PortalModule;

class PortalTeleporter {

    private PortalModule $module;
    private array $portalTicks = [];
    private array $cooldowns = [];
    private array $generatingPortals = [];

    public function __construct(PortalModule $module) {
        $this->module = $module;
    }

    public function isGenerating(string $uuid): bool {
        return isset($this->generatingPortals[$uuid]);
    }

    public function hasCooldown(string $uuid): bool {
        if (isset($this->cooldowns[$uuid])) {
            if (time() < $this->cooldowns[$uuid]) {
                unset($this->portalTicks[$uuid]);
                return true;
            } else {
                unset($this->cooldowns[$uuid]);
            }
        }
        return false;
    }

    public function cleanupPlayer(string $uuid): void {
        unset($this->portalTicks[$uuid]);
        unset($this->cooldowns[$uuid]);
        unset($this->generatingPortals[$uuid]);
    }

    public function tickPortals(): void {
        $delay = (int)$this->module->getConfig()->get("teleport_delay", 3);
        $cooldownTime = (int)$this->module->getConfig()->get("cooldown_time", 5);

        foreach ($this->module->getServer()->getOnlinePlayers() as $player) {
            $uuid = $player->getUniqueId()->toString();

            if ($this->isGenerating($uuid) || $this->hasCooldown($uuid)) {
                continue;
            }

            $world = $player->getWorld();
            $pos = $player->getPosition();

            $blockFeet = $world->getBlock($pos);
            $blockHead = $world->getBlock($pos->add(0, 1, 0));

            $inPortal = ($blockFeet instanceof NetherPortal || $blockHead instanceof NetherPortal);

            if ($inPortal) {
                if (!isset($this->portalTicks[$uuid])) {
                    $this->portalTicks[$uuid] = $delay * 2;

                    $player->getNetworkSession()->sendDataPacket(PlaySoundPacket::create(
                        "portal.portal",
                        $pos->x, $pos->y, $pos->z,
                        0.35, 1.0,
                        0
                    ));
                } else {
                    $this->portalTicks[$uuid]--;

                    for ($j = 0; $j < 8; $j++) {
                        $world->addParticle(
                            $pos->add(
                                mt_rand(-6, 6) / 10,
                                mt_rand(0, 20) / 10,
                                mt_rand(-6, 6) / 10
                            ),
                            new PortalParticle()
                        );
                    }

                    if ($this->portalTicks[$uuid] <= 0) {
                        unset($this->portalTicks[$uuid]);
                        $this->cooldowns[$uuid] = time() + $cooldownTime;
                        $this->teleportPlayer($player);
                    }
                }
            } else {
                if (isset($this->portalTicks[$uuid])) {
                    unset($this->portalTicks[$uuid]);
                }
            }
        }
    }

    public function teleportPlayer(Player $player): void {
        $netherName = $this->module->getConfig()->get("nether_world", "nether");
        $fallbackName = $this->module->getConfig()->get("fallback_world", "lobby");

        $currentWorld = $player->getWorld();
        $currentWorldName = $currentWorld->getFolderName();
        $pos = $player->getPosition();
        $uuid = $player->getUniqueId()->toString();

        $pm = $this->module->getPortalManager();
        $keyFeet = $currentWorldName . ":" . $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();
        $keyHead = $currentWorldName . ":" . $pos->getFloorX() . ":" . ($pos->getFloorY() + 1) . ":" . $pos->getFloorZ();
        $portalId = $pm->getPortalIdByBlock($keyFeet) ?? $pm->getPortalIdByBlock($keyHead) ?? null;

        if ($portalId !== null) {
            $portal = $pm->getPortalById($portalId);
            if ($portal !== null) {
                $destWorld = $this->module->getServer()->getWorldManager()->getWorldByName($portal["dest_world"]);

                if ($destWorld !== null) {
                    $cx = ((int)$portal["dest_x"]) >> 4;
                    $cz = ((int)$portal["dest_z"]) >> 4;
                    $destWorld->loadChunk($cx, $cz);

                    $destLocation = new Location($portal["dest_x"], $portal["dest_y"], $portal["dest_z"], $destWorld, 0.0, 0.0);
                    $player->teleport($destLocation);
                    return;
                }
            }
        }

        if ($currentWorldName === $netherName) {
            $lobbyWorld = $this->module->getServer()->getWorldManager()->getWorldByName($fallbackName);
            if ($lobbyWorld === null) {
                $lobbyWorld = $this->module->getServer()->getWorldManager()->getDefaultWorld();
            }
            if ($lobbyWorld !== null) {
                $player->teleport($lobbyWorld->getSpawnLocation());
            }
        } else {
            $netherWorld = $this->module->getServer()->getWorldManager()->getWorldByName($netherName);
            if ($netherWorld === null) {
                return;
            }

            $minX = (int)$this->module->getConfig()->get("rtp_min_x", -1000);
            $maxX = (int)$this->module->getConfig()->get("rtp_max_x", 1000);
            $minZ = (int)$this->module->getConfig()->get("rtp_min_z", -1000);
            $maxZ = (int)$this->module->getConfig()->get("rtp_max_z", 1000);

            $rx = mt_rand($minX, $maxX);
            $rz = mt_rand($minZ, $maxZ);

            $this->generatingPortals[$uuid] = true;

            $cx = $rx >> 4;
            $cz = $rz >> 4;

            $remaining = 9;
            $onComplete = function() use ($player, $netherWorld, $rx, $rz, $pos, $currentWorldName, $netherName, &$remaining): void {
                $remaining--;
                if ($remaining === 0) {
                    $this->onChunksGenerated($player, $netherWorld, $rx, $rz, $pos, $currentWorldName, $netherName);
                }
            };

            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dz = -1; $dz <= 1; $dz++) {
                    $netherWorld->orderChunkPopulation($cx + $dx, $cz + $dz, null)->onCompletion($onComplete, $onComplete);
                }
            }
        }
    }

    private function onChunksGenerated(Player $player, World $netherWorld, int $rx, int $rz, Position $pos, string $currentWorldName, string $netherName): void {
        $uuid = $player->getUniqueId()->toString();
        unset($this->generatingPortals[$uuid]);

        if (!$player->isOnline()) {
            return;
        }

        $netherLoc = null;
        $airId = VanillaBlocks::AIR()->getTypeId();

        for ($dx = -8; $dx <= 8; $dx += 2) {
            for ($dz = -8; $dz <= 8; $dz += 2) {
                $tx = $rx + $dx;
                $tz = $rz + $dz;

                for ($y = 100; $y >= 35; $y--) {
                    $b = $netherWorld->getBlockAt($tx, $y, $tz);
                    $bUp1 = $netherWorld->getBlockAt($tx, $y + 1, $tz);
                    $bUp2 = $netherWorld->getBlockAt($tx, $y + 2, $tz);
                    $bGround = $netherWorld->getBlockAt($tx, $y - 1, $tz);

                    if ($b->getTypeId() === $airId &&
                        $bUp1->getTypeId() === $airId &&
                        $bUp2->getTypeId() === $airId &&
                        $bGround->isSolid() &&
                        !($bGround instanceof \pocketmine\block\Liquid) &&
                        !($bGround instanceof \pocketmine\block\Fire)
                    ) {
                        $netherLoc = new Location($tx + 0.5, $y, $tz + 0.5, $netherWorld, 0.0, 0.0);
                        break 3;
                    }
                }
            }
        }

        if ($netherLoc === null) {
            $netherLoc = new Location($rx + 0.5, 64, $rz + 0.5, $netherWorld, 0.0, 0.0);
        }

        $this->buildNetherPortal($netherLoc);

        $overworldBlocks = $this->detectPortalBlocks($pos);
        if (empty($overworldBlocks)) {
            $overworldBlocks[] = [
                "world" => $currentWorldName,
                "x" => $pos->getFloorX(),
                "y" => $pos->getFloorY(),
                "z" => $pos->getFloorZ()
            ];
        }

        $netherBlocks = $this->detectPortalBlocks($netherLoc);
        if (empty($netherBlocks)) {
            $netherBlocks[] = [
                "world" => $netherName,
                "x" => $netherLoc->getFloorX(),
                "y" => $netherLoc->getFloorY(),
                "z" => $netherLoc->getFloorZ()
            ];
        }

        $owId = "ow_" . $overworldBlocks[0]["x"] . "_" . $overworldBlocks[0]["y"] . "_" . $overworldBlocks[0]["z"];
        $nId = "n_" . $netherBlocks[0]["x"] . "_" . $netherBlocks[0]["y"] . "_" . $netherBlocks[0]["z"];

        $owData = [
            "id" => $owId,
            "world" => $currentWorldName,
            "x" => $overworldBlocks[0]["x"],
            "y" => $overworldBlocks[0]["y"],
            "z" => $overworldBlocks[0]["z"],
            "dest_world" => $netherName,
            "dest_x" => $netherLoc->x,
            "dest_y" => $netherLoc->y,
            "dest_z" => $netherLoc->z,
            "axis" => Axis::X,
            "blocks" => $overworldBlocks
        ];
        $this->module->getPortalManager()->savePortal($owData);

        $nData = [
            "id" => $nId,
            "world" => $netherName,
            "x" => $netherBlocks[0]["x"],
            "y" => $netherBlocks[0]["y"],
            "z" => $netherBlocks[0]["z"],
            "dest_world" => $currentWorldName,
            "dest_x" => $overworldBlocks[0]["x"] + 0.5,
            "dest_y" => (float)$overworldBlocks[0]["y"],
            "dest_z" => $overworldBlocks[0]["z"] + 0.5,
            "axis" => Axis::X,
            "blocks" => $netherBlocks
        ];
        $this->module->getPortalManager()->savePortal($nData);

        $player->teleport($netherLoc);
    }

    public function detectPortalBlocks(Position $pos): array {
        $world = $pos->getWorld();
        $startPos = null;

        $bx = $pos->getFloorX();
        $by = $pos->getFloorY();
        $bz = $pos->getFloorZ();

        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 2; $dy++) {
                for ($dz = -1; $dz <= 1; $dz++) {
                    $block = $world->getBlockAt($bx + $dx, $by + $dy, $bz + $dz);
                    if ($block instanceof NetherPortal) {
                        $startPos = new Position($bx + $dx, $by + $dy, $bz + $dz, $world);
                        break 3;
                    }
                }
            }
        }

        if ($startPos === null) {
            return [];
        }

        $queue = [$startPos];
        $visited = [];
        $blocks = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $cx = $current->getFloorX();
            $cy = $current->getFloorY();
            $cz = $current->getFloorZ();
            $hash = $cx . "," . $cy . "," . $cz;
            if (isset($visited[$hash])) {
                continue;
            }
            $visited[$hash] = true;

            $block = $world->getBlockAt($cx, $cy, $cz);
            if ($block instanceof NetherPortal) {
                $blocks[] = [
                    "world" => $world->getFolderName(),
                    "x" => $cx,
                    "y" => $cy,
                    "z" => $cz
                ];

                $queue[] = new Position($cx + 1, $cy, $cz, $world);
                $queue[] = new Position($cx - 1, $cy, $cz, $world);
                $queue[] = new Position($cx, $cy + 1, $cz, $world);
                $queue[] = new Position($cx, $cy - 1, $cz, $world);
                $queue[] = new Position($cx, $cy, $cz + 1, $world);
                $queue[] = new Position($cx, $cy, $cz - 1, $world);
            }
        }
        return $blocks;
    }

    private function buildNetherPortal(Location $location): void {
        $world = $location->getWorld();
        $x = $location->getFloorX();
        $y = $location->getFloorY();
        $z = $location->getFloorZ();

        $obsidian = VanillaBlocks::OBSIDIAN();
        $portal = VanillaBlocks::NETHER_PORTAL();

        for ($dx = -2; $dx <= 3; $dx++) {
            for ($dz = -2; $dz <= 2; $dz++) {
                $world->setBlockAt($x + $dx, $y - 1, $z + $dz, $obsidian);
            }
        }

        for ($dy = -1; $dy <= 3; $dy++) {
            for ($dx = -1; $dx <= 2; $dx++) {
                $isEdge = ($dx === -1 || $dx === 2 || $dy === -1 || $dy === 3);
                $bx = $x + $dx;
                $by = $y + $dy;
                $bz = $z;

                if ($isEdge) {
                    $world->setBlockAt($bx, $by, $bz, $obsidian);
                } else {
                    $world->setBlockAt($bx, $by, $bz, $portal);
                }
            }
        }
    }
}
