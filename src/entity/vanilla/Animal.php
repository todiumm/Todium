<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla;

use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\world\particle\HeartParticle;
use pocketmine\entity\EntitySizeInfo;

abstract class Animal extends BaseMob
{
    public int $inLoveTicks = 0;
    public int $age = 0;
    public int $panicTicks = 0;

    public function __construct(\pocketmine\entity\Location $location, ?\pocketmine\nbt\tag\CompoundTag $nbt = null)
    {
        parent::__construct($location, $nbt);
        if ($nbt !== null) {
            $this->inLoveTicks = $nbt->getInt("InLove", 0);
            $this->age = $nbt->getInt("Age", 0);
        } else {
            $this->inLoveTicks = 0;
            $this->age = 0;
        }
        $this->panicTicks = 0;
    }

    protected function getBreedingItem(): Item
    {
        return VanillaItems::WHEAT();
    }

    public function isBreedingItem(Item $item): bool
    {
        return $item->getTypeId() === $this->getBreedingItem()->getTypeId();
    }

    public function onUpdate(int $currentTick): bool
    {
        if ($this->inLoveTicks > 0) $this->inLoveTicks--;
        if ($this->panicTicks > 0) $this->panicTicks--;
        if ($this->age < 0) {
            $this->age++;
            if ($this->age >= 0) {
                $this->setScale(1.0);
            }
        } elseif ($this->age > 0) {
            $this->age--;
        }
        return parent::onUpdate($currentTick);
    }

    protected function getAIUpdateInterval(): int {
        $tps = \pocketmine\Server::getInstance()->getTicksPerSecond();
        
        if ($tps < 12) {
            return 30;
        } elseif ($tps < 15) {
            return 20;
        }
        
        return 10;
    }

    public function attack(\pocketmine\event\entity\EntityDamageEvent $source): void
    {
        parent::attack($source);
        if (!$source->isCancelled()) {
            $this->panicTicks = 100;
            if ($this->isFlying()) {
                $this->targetPosition = clone $this->location->add(mt_rand(-10, 10), mt_rand(-2, 4), mt_rand(-10, 10));
            } else {
                $this->targetPosition = clone $this->location->add(mt_rand(-8, 8), 0, mt_rand(-8, 8));
            }
        }
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool
    {
        $item = $player->getInventory()->getItemInHand();

        if ($this->age === 0 && $this->inLoveTicks <= 0 && $this->isBreedingItem($item)) {
            $this->inLoveTicks = 600;
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $this->getWorld()->addParticle($this->getLocation()->add(0, $this->getEyeHeight() + 0.5, 0), new HeartParticle(2));
            return true;
        }

        if ($this->age < 0 && $this->isBreedingItem($item)) {

            $this->age += (int)abs($this->age * 0.1);
            if ($this->age >= 0) {
                $this->age = 0;
                $this->setScale(1.0);
            }

            $item->pop();
            $player->getInventory()->setItemInHand($item);

            $this->getWorld()->addParticle($this->getLocation()->add(0, $this->getEyeHeight() + 0.5, 0), new HeartParticle(2));
            return true;
        }

        return parent::onInteract($player, $clickPos);
    }

    protected function calculateAI(): void
    {

        if ($this->isSwimming()) {
            if ($this->targetPosition === null || mt_rand(1, 100) <= 15) {
                $foundWater = [];
                for ($x = -6; $x <= 6; $x++) {
                    for ($y = -3; $y <= 3; $y++) {
                        for ($z = -6; $z <= 6; $z++) {
                            $pos = $this->location->add($x, $y, $z);
                            if ($this->getWorld()->getBlock($pos) instanceof \pocketmine\block\Water) {
                                $foundWater[] = $pos;
                            }
                        }
                    }
                }
                if (!empty($foundWater)) {
                    $this->targetPosition = $foundWater[array_rand($foundWater)];
                }
            }
            return;
        }

        if ($this->isAquatic() && !$this->isSwimming()) {
            if ($this->targetPosition === null || mt_rand(1, 100) <= 20) {
                $foundWater = null;
                for ($x = -5; $x <= 5; $x++) {
                    for ($y = -2; $y <= 2; $y++) {
                        for ($z = -5; $z <= 5; $z++) {
                            $pos = $this->location->add($x, $y, $z);
                            if ($this->getWorld()->getBlock($pos) instanceof \pocketmine\block\Water) {
                                $foundWater = $pos;
                                break 3;
                            }
                        }
                    }
                }
                if ($foundWater !== null) {
                    $this->targetPosition = $foundWater;
                } else {
                    $this->targetPosition = clone $this->location->add(mt_rand(-2, 2), 0, mt_rand(-2, 2));
                }
            }
            return;
        }

        if ($this->panicTicks > 0 || $this->isOnFire()) {
            if (mt_rand(1, 10) <= 3 || $this->targetPosition === null) {
                if ($this->isFlying()) {
                    $this->targetPosition = clone $this->location->add(mt_rand(-12, 12), mt_rand(-3, 5), mt_rand(-12, 12));
                } else {
                    $this->targetPosition = clone $this->location->add(mt_rand(-10, 10), 0, mt_rand(-10, 10));
                }
            }
            return;
        }

        if ($this->inLoveTicks > 0 && $this->age === 0) {

            foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(8, 4, 8)) as $entity) {
                if ($entity !== $this && $entity instanceof self && get_class($entity) === static::class) {

                    if ($entity->inLoveTicks > 0 && $entity->age === 0) {
                        $this->targetPosition = $entity->getPosition();

                        if ($this->location->distanceSquared($entity->getLocation()) < 4) {
                            $this->inLoveTicks = 0;
                            $entity->inLoveTicks = 0;

                            $this->age = 6000;
                            $entity->age = 6000;

                            $class = static::class;

                            $baby = new $class(\pocketmine\entity\Location::fromObject($this->getPosition(), $this->getWorld()));
                            $baby->age = -24000;
                            $baby->setScale(0.5);
                            $baby->spawnToAll();

                            $this->getWorld()->addParticle($this->getLocation()->add(0, 1, 0), new HeartParticle(2));
                        }
                        return;
                    }
                }
            }
        }

        $nearestDist = 100;
        $targetPlayer = null;

        foreach ($this->getWorld()->getPlayers() as $player) {
            $dist = $this->location->distanceSquared($player->getLocation());
            if ($dist < $nearestDist && $this->isBreedingItem($player->getInventory()->getItemInHand())) {
                $nearestDist = $dist;
                $targetPlayer = $player;
            }
        }

        if ($targetPlayer !== null) {
            $this->targetPosition = clone $targetPlayer->getLocation();
            return;
        }

        if ($this->targetPosition === null || mt_rand(1, 100) <= 20) {
            if (mt_rand(1, 100) <= 40) {
                if ($this->isFlying() || $this->isSwimming()) {
                    $randX = mt_rand(-8, 8);
                    $randZ = mt_rand(-8, 8);
                    $randY = mt_rand(-3, 3);
                    $newPos = $this->location->add($randX, $randY, $randZ);
                    if ($this->isSwimming()) {
                        if ($this->getWorld()->getBlock($newPos) instanceof \pocketmine\block\Water) {
                            $this->targetPosition = $newPos;
                        }
                    } else {
                        $chunkX = (int)floor($newPos->x) >> 4;
                        $chunkZ = (int)floor($newPos->z) >> 4;
                        if ($this->getWorld()->isChunkGenerated($chunkX, $chunkZ)) {
                            $highestBlockY = $this->getWorld()->getHighestBlockAt((int)floor($newPos->x), (int)floor($newPos->z));
                            if ($highestBlockY !== null) {
                                $targetY = max($highestBlockY + 1.5, min($highestBlockY + 8, $newPos->y));
                                $newPos->y = $targetY;
                            }
                            if (!$this->getWorld()->getBlock($newPos)->isSolid()) {
                                $this->targetPosition = $newPos;
                            }
                        }
                    }
                } else {
                    $this->targetPosition = clone $this->location->add(mt_rand(-6, 6), 0, mt_rand(-6, 6));
                }
            } else {
                $this->targetPosition = null;
            }
        }
    }

    public function getXpDropAmount(): int
    {
        return $this->age < 0 ? 0 : mt_rand(1, 3);
    }
}
