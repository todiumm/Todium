<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Monster;

use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;

class Skeleton extends Monster {
    public function __construct(Location $location, ?CompoundTag $nbt = null) {
        parent::__construct($location, $nbt);
    }

    protected function sendSpawnPacket(\pocketmine\player\Player $player): void {
        parent::sendSpawnPacket($player);
        try {
            $pk = new \pocketmine\network\mcpe\protocol\MobEquipmentPacket();
            $pk->actorRuntimeId = $this->getId();
            $pk->item = \pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper::legacy(\pocketmine\network\mcpe\convert\TypeConverter::getInstance()->coreItemStackToNet(\pocketmine\item\VanillaItems::BOW()));
            $pk->inventorySlot = 0;
            $pk->hotbarSlot = 0;
            $pk->windowId = 0;
            $player->getNetworkSession()->sendDataPacket($pk);
        } catch (\Throwable $e) {}
    }

    public static function getNetworkTypeId(): string {
        return "minecraft:skeleton";
    }

    public function isUndead(): bool {
        return true;
    }

    private int $bowChargeTicks = 0;

    protected function calculateAI(): void {
        if ($this->attackDelay > 0) {
            $this->attackDelay -= 10;
        }

        $nearest = null;
        $minDist = 1600;

        foreach ($this->getWorld()->getPlayers() as $player) {
            if ($player->isCreative() || $player->isSpectator()) continue;

            $dist = $this->location->distanceSquared($player->getLocation());
            if ($dist < $minDist) {
                $minDist = $dist;
                $nearest = $player;
            }
        }

        foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(20, 10, 20)) as $entity) {
            if (($entity instanceof \pocketmine\entity\vanilla\overworld\IronGolem || $entity instanceof \pocketmine\entity\vanilla\overworld\SnowGolem) && $entity->isAlive() && !$entity->isClosed()) {
                $dist = $this->location->distanceSquared($entity->getLocation());
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $nearest = $entity;
                }
            }
        }

        if ($nearest !== null) {
            $this->targetEntity = $nearest;
            if ($minDist > 64) {
                $this->targetPosition = clone $nearest->getLocation();
            } else {
                $this->targetPosition = null;
                $this->lookAt($nearest->getLocation());
            }

            if ($minDist < 200 && $this->attackDelay <= 0) {
                if ($this->bowChargeTicks === 0) {
                    $this->bowChargeTicks = 20;
                    $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::ACTION, true);
                    $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::FACING_TARGET_TO_RANGE_ATTACK, true);
                }
            }
        } else {
            $this->targetEntity = null;

            if ($this->bowChargeTicks > 0) {
                $this->bowChargeTicks = 0;
                $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::ACTION, false);
                $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::FACING_TARGET_TO_RANGE_ATTACK, false);
            }
            if ($this->targetPosition === null || mt_rand(1, 100) <= 10) {
                $this->targetPosition = clone $this->location->add(mt_rand(-6, 6), 0, mt_rand(-6, 6));
            }
        }
    }
    protected function syncNetworkData(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection $properties): void {
        parent::syncNetworkData($properties);
        $properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::ACTION, $this->bowChargeTicks > 0);
        $properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::FACING_TARGET_TO_RANGE_ATTACK, $this->bowChargeTicks > 0);
    }

    public function onUpdate(int $currentTick): bool {
        $hasUpdate = parent::onUpdate($currentTick);

        if ($this->bowChargeTicks > 0) {
            $this->bowChargeTicks--;
            if ($this->bowChargeTicks <= 0) {
                $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::ACTION, false);
                $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::FACING_TARGET_TO_RANGE_ATTACK, false);

                $nearest = null;
                $minDist = 1600;
                foreach ($this->getWorld()->getPlayers() as $player) {
                    if ($player->isCreative() || $player->isSpectator()) continue;
                    $dist = $this->location->distanceSquared($player->getLocation());
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $nearest = $player;
                    }
                }

                foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(20, 10, 20)) as $entity) {
                    if (($entity instanceof \pocketmine\entity\vanilla\overworld\IronGolem || $entity instanceof \pocketmine\entity\vanilla\overworld\SnowGolem) && $entity->isAlive() && !$entity->isClosed()) {
                        $dist = $this->location->distanceSquared($entity->getLocation());
                        if ($dist < $minDist) {
                            $minDist = $dist;
                            $nearest = $entity;
                        }
                    }
                }

                if ($nearest !== null && $minDist < 200) {
                    $this->shootArrow($nearest);
                }
                $this->attackDelay = 40;
            }
            $hasUpdate = true;
        }

        return $hasUpdate;
    }

    private function shootArrow(\pocketmine\entity\Living $target): void {
        $diff = $target->getLocation()->subtractVector($this->getLocation());
        $pitch = -atan2($diff->y, sqrt($diff->x * $diff->x + $diff->z * $diff->z));
        $yaw = atan2($diff->z, $diff->x) - M_PI_2;

        $location = \pocketmine\entity\Location::fromObject($this->getLocation()->add(0, $this->getEyeHeight(), 0), $this->getWorld(), rad2deg($yaw), rad2deg($pitch));
        $arrow = new \pocketmine\entity\projectile\Arrow($location, $this, false);
        $arrow->setMotion($diff->normalize()->multiply(1.5));

        $ev = new \pocketmine\event\entity\ProjectileLaunchEvent($arrow);
        $ev->call();
        if (!$ev->isCancelled()) {
            $arrow->spawnToAll();
            $this->getWorld()->addSound($this->getLocation(), new \pocketmine\world\sound\BowShootSound());

            $pk = new \pocketmine\network\mcpe\protocol\AnimatePacket();
            $pk->action = \pocketmine\network\mcpe\protocol\AnimatePacket::ACTION_SWING_ARM;
            $pk->actorRuntimeId = $this->getId();
            $this->getWorld()->broadcastPacketToViewers($this->getLocation(), $pk);
        } else {
            $arrow->close();
        }
    }
}
