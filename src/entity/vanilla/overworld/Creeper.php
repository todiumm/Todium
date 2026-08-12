<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\world\Explosion;

class Creeper extends \pocketmine\entity\vanilla\Monster {
    private int $fuse = 0;

    public static function getNetworkTypeId(): string {
        return "minecraft:creeper";
    }

    protected function initEntity(\pocketmine\nbt\tag\CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::IGNITED, false);
    }

    protected function syncNetworkData(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection $properties): void {
        parent::syncNetworkData($properties);
        $properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::IGNITED, $this->fuse > 0);
    }

    public function onUpdate(int $currentTick): bool {
        $hasUpdate = parent::onUpdate($currentTick);
        if (!$this->isAlive() || $this->isClosed()) {
            return $hasUpdate;
        }

        $flee = false;
        foreach ($this->getWorld()->getEntities() as $entity) {
            if ($entity instanceof \pocketmine\entity\vanilla\overworld\Cat || $entity instanceof \pocketmine\entity\vanilla\overworld\Ocelot) {
                if ($this->location->distanceSquared($entity->getLocation()) < 36.0) {
                    $flee = true;
                    break;
                }
            }
        }

        if ($flee) {
            if ($this->fuse > 0) {
                $this->fuse--;
                if ($this->fuse === 0) {
                    $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::IGNITED, false);
                }
            }
            return $hasUpdate;
        }

        $targetPlayer = null;
        $nearestDist = 16.0;
        foreach ($this->getWorld()->getPlayers() as $player) {
            if ($player->isAlive() && !$player->isCreative() && !$player->isSpectator()) {
                $dist = $this->location->distanceSquared($player->getLocation());
                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $targetPlayer = $player;
                }
            }
        }

        if ($targetPlayer !== null) {
            if ($this->fuse === 0) {
                $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::IGNITED, true);
            }
            $this->fuse++;
            $this->getWorld()->addParticle($this->getLocation()->add(0, 1, 0), new \pocketmine\world\particle\LavaParticle());

            if ($this->fuse >= 30) {
                $explosion = new Explosion($this->getPosition(), 3, $this);
                $explosion->explodeA();
                $explosion->explodeB();
                $this->kill();
            }
        } else {
            if ($this->fuse > 0) {
                $this->fuse--;
                if ($this->fuse === 0) {
                    $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::IGNITED, false);
                }
            }
        }

        return $hasUpdate;
    }

    protected function calculateAI(): void {
        $fleeFrom = null;
        $minFleeDist = 36.0;

        foreach ($this->getWorld()->getEntities() as $entity) {
            if ($entity instanceof \pocketmine\entity\vanilla\overworld\Cat || $entity instanceof \pocketmine\entity\vanilla\overworld\Ocelot) {
                $dist = $this->location->distanceSquared($entity->getLocation());
                if ($dist < $minFleeDist) {
                    $minFleeDist = $dist;
                    $fleeFrom = $entity;
                }
            }
        }

        if ($fleeFrom !== null) {
            $catPos = $fleeFrom->getLocation();
            $dir = $this->location->subtract($catPos->x, $catPos->y, $catPos->z);
            if ($dir->lengthSquared() > 0) {
                $dir = $dir->normalize();
            } else {
                $dir = new \pocketmine\math\Vector3(1, 0, 0);
            }

            $this->targetPosition = $this->location->addVector($dir->multiply(8.0));
            return;
        }

        parent::calculateAI();
    }
}
