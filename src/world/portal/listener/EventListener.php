<?php

declare(strict_types=1);

namespace pocketmine\world\portal\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\world\Position;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\NetherPortal;
use pocketmine\item\FlintSteel;
use pocketmine\scheduler\ClosureTask;
use pocketmine\math\Axis;
use pocketmine\world\portal\PortalModule;

class EventListener implements Listener {

    private PortalModule $module;

    public function __construct(PortalModule $module) {
        $this->module = $module;
    }

    /**
     * @EventHandler
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void {
        if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            $item = $event->getItem();
            if ($item instanceof FlintSteel) {
                $block = $event->getBlock();
                $face = $event->getFace();
                $targetPos = $block->getSide($face)->getPosition();

                $this->module->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($targetPos): void {
                    $this->tryCreatePortal($targetPos);
                }), 1);
            }
        }
    }

    /**
     * @EventHandler
     */
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $world = $event->getPlayer()->getWorld();
        $pm = $this->module->getPortalManager();
        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $newBlock]) {
            $oldBlock = $world->getBlockAt($x, $y, $z);
            if ($oldBlock instanceof NetherPortal) {
                $key = $world->getFolderName() . ":" . $x . ":" . $y . ":" . $z;
                $portalId = $pm->getPortalIdByBlock($key);
                if ($portalId !== null) {
                    $pm->deletePortal($portalId);
                } else {
                    $this->destroyPortal(new Position($x, $y, $z, $world));
                }
            }
        }
    }

    /**
     * @EventHandler
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $block = $event->getBlock();
        $pos = $block->getPosition();
        $world = $pos->getWorld();
        $worldName = $world->getFolderName();
        
        $obsidianId = VanillaBlocks::OBSIDIAN()->getTypeId();
        $pm = $this->module->getPortalManager();
        
        if ($block->getTypeId() === $obsidianId) {
            $sides = [
                $pos->add(1, 0, 0),
                $pos->add(-1, 0, 0),
                $pos->add(0, 1, 0),
                $pos->add(0, -1, 0),
                $pos->add(0, 0, 1),
                $pos->add(0, 0, -1)
            ];
            foreach ($sides as $side) {
                $key = $worldName . ":" . $side->x . ":" . $side->y . ":" . $side->z;
                $portalId = $pm->getPortalIdByBlock($key);
                if ($portalId !== null) {
                    $pm->deletePortal($portalId);
                } elseif ($world->getBlock($side) instanceof NetherPortal) {
                    $this->destroyPortal(new Position($side->x, $side->y, $side->z, $world));
                }
            }
        } elseif ($block instanceof NetherPortal) {
            $key = $worldName . ":" . $pos->x . ":" . $pos->y . ":" . $pos->z;
            $portalId = $pm->getPortalIdByBlock($key);
            if ($portalId !== null) {
                $pm->deletePortal($portalId);
            } else {
                $this->destroyPortal($pos);
            }
        }
    }

    /**
     * @EventHandler
     */
    public function onEntityExplode(EntityExplodeEvent $event): void {
        $world = $event->getEntity()->getWorld();
        $worldName = $world->getFolderName();
        $obsidianId = VanillaBlocks::OBSIDIAN()->getTypeId();
        $pm = $this->module->getPortalManager();
        
        foreach ($event->getBlockList() as $block) {
            $pos = $block->getPosition();
            if ($block->getTypeId() === $obsidianId) {
                $sides = [
                    $pos->add(1, 0, 0),
                    $pos->add(-1, 0, 0),
                    $pos->add(0, 1, 0),
                    $pos->add(0, -1, 0),
                    $pos->add(0, 0, 1),
                    $pos->add(0, 0, -1)
                ];
                foreach ($sides as $side) {
                    $key = $worldName . ":" . $side->x . ":" . $side->y . ":" . $side->z;
                    $portalId = $pm->getPortalIdByBlock($key);
                    if ($portalId !== null) {
                        $pm->deletePortal($portalId);
                    } elseif ($world->getBlock($side) instanceof NetherPortal) {
                        $this->destroyPortal(new Position($side->x, $side->y, $side->z, $world));
                    }
                }
            } elseif ($block instanceof NetherPortal) {
                $key = $worldName . ":" . $pos->x . ":" . $pos->y . ":" . $pos->z;
                $portalId = $pm->getPortalIdByBlock($key);
                if ($portalId !== null) {
                    $pm->deletePortal($portalId);
                } else {
                    $this->destroyPortal($pos);
                }
            }
        }
    }

    /**
     * @EventHandler
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $uuid = $event->getPlayer()->getUniqueId()->toString();
        $this->module->getPortalTeleporter()->cleanupPlayer($uuid);
    }

    public function tryCreatePortal(Position $pos): bool {
        if ($this->checkAndCreatePortalAxis($pos, Axis::X)) {
            return true;
        }
        if ($this->checkAndCreatePortalAxis($pos, Axis::Z)) {
            return true;
        }
        return false;
    }

    private function checkAndCreatePortalAxis(Position $pos, int $axis): bool {
        $world = $pos->getWorld();
        $startX = $pos->getFloorX();
        $startY = $pos->getFloorY();
        $startZ = $pos->getFloorZ();
        
        $obsidianId = VanillaBlocks::OBSIDIAN()->getTypeId();
        $airId = VanillaBlocks::AIR()->getTypeId();
        $fireId = VanillaBlocks::FIRE()->getTypeId();
        $portalId = VanillaBlocks::NETHER_PORTAL()->getTypeId();
        
        $isX = ($axis === Axis::X);
        
        $minCoord = null;
        $maxCoord = null;
        
        for ($offset = 0; $offset <= 22; $offset++) {
            $cx = $isX ? $startX - $offset : $startX;
            $cz = !$isX ? $startZ - $offset : $startZ;
            $block = $world->getBlockAt($cx, $startY, $cz);
            
            if ($block->getTypeId() === $obsidianId) {
                $minCoord = $isX ? $cx : $cz;
                break;
            }
            
            $bid = $block->getTypeId();
            if ($bid !== $airId && $bid !== $fireId && $bid !== $portalId) {
                return false;
            }
        }
        
        for ($offset = 0; $offset <= 22; $offset++) {
            $cx = $isX ? $startX + $offset : $startX;
            $cz = !$isX ? $startZ + $offset : $startZ;
            $block = $world->getBlockAt($cx, $startY, $cz);
            
            if ($block->getTypeId() === $obsidianId) {
                $maxCoord = $isX ? $cx : $cz;
                break;
            }
            
            $bid = $block->getTypeId();
            if ($bid !== $airId && $bid !== $fireId && $bid !== $portalId) {
                return false;
            }
        }
        
        if ($minCoord === null || $maxCoord === null) {
            return false;
        }
        
        $width = $maxCoord - $minCoord - 1;
        if ($width < 2 || $width > 21) {
            return false;
        }
        
        $bottomY = null;
        $topY = null;
        $firstInnerCoord = $minCoord + 1;
        
        for ($offset = 0; $offset <= 22; $offset++) {
            $cy = $startY - $offset;
            $cx = $isX ? $firstInnerCoord : $startX;
            $cz = !$isX ? $firstInnerCoord : $startZ;
            $block = $world->getBlockAt($cx, $cy, $cz);
            
            if ($block->getTypeId() === $obsidianId) {
                $bottomY = $cy;
                break;
            }
            
            $bid = $block->getTypeId();
            if ($bid !== $airId && $bid !== $fireId && $bid !== $portalId) {
                return false;
            }
        }
        
        for ($offset = 0; $offset <= 22; $offset++) {
            $cy = $startY + $offset;
            $cx = $isX ? $firstInnerCoord : $startX;
            $cz = !$isX ? $firstInnerCoord : $startZ;
            $block = $world->getBlockAt($cx, $cy, $cz);
            
            if ($block->getTypeId() === $obsidianId) {
                $topY = $cy;
                break;
            }
            
            $bid = $block->getTypeId();
            if ($bid !== $airId && $bid !== $fireId && $bid !== $portalId) {
                return false;
            }
        }
        
        if ($bottomY === null || $topY === null) {
            return false;
        }
        
        $height = $topY - $bottomY - 1;
        if ($height < 3 || $height > 21) {
            return false;
        }
        
        for ($coord = $minCoord; $coord <= $maxCoord; $coord++) {
            $isSide = ($coord === $minCoord || $coord === $maxCoord);
            
            for ($y = $bottomY; $y <= $topY; $y++) {
                $isBottomOrTop = ($y === $bottomY || $y === $topY);
                
                $cx = $isX ? $coord : $startX;
                $cz = !$isX ? $coord : $startZ;
                $block = $world->getBlockAt($cx, $y, $cz);
                
                if ($isSide || $isBottomOrTop) {
                    $isCorner = ($isSide && $isBottomOrTop);
                    if (!$isCorner && $block->getTypeId() !== $obsidianId) {
                        return false;
                    }
                } else {
                    $bid = $block->getTypeId();
                    if ($bid !== $airId && $bid !== $fireId && $bid !== $portalId) {
                        return false;
                    }
                }
            }
        }
        
        $portalBlock = VanillaBlocks::NETHER_PORTAL()->setAxis($isX ? Axis::X : Axis::Z);
        
        for ($coord = $minCoord + 1; $coord < $maxCoord; $coord++) {
            for ($y = $bottomY + 1; $y < $topY; $y++) {
                $cx = $isX ? $coord : $startX;
                $cz = !$isX ? $coord : $startZ;
                $world->setBlockAt($cx, $y, $cz, $portalBlock);
            }
        }
        
        return true;
    }

    public function destroyPortal(Position $pos): void {
        $world = $pos->getWorld();
        $queue = [$pos];
        $air = VanillaBlocks::AIR();
        $visited = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $hash = $current->x . "," . $current->y . "," . $current->z;
            if (isset($visited[$hash])) {
                continue;
            }
            $visited[$hash] = true;

            $block = $world->getBlock($current);
            if ($block instanceof NetherPortal) {
                $world->setBlock($current, $air);

                $queue[] = Position::fromObject($current->add(1, 0, 0), $world);
                $queue[] = Position::fromObject($current->add(-1, 0, 0), $world);
                $queue[] = Position::fromObject($current->add(0, 1, 0), $world);
                $queue[] = Position::fromObject($current->add(0, -1, 0), $world);
                $queue[] = Position::fromObject($current->add(0, 0, 1), $world);
                $queue[] = Position::fromObject($current->add(0, 0, -1), $world);
            }
        }
    }
}
