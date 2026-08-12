<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Monster;

class Spider extends Monster {
    public static function getNetworkTypeId(): string {
        return "minecraft:spider";
    }

    protected function initEntity(\pocketmine\nbt\tag\CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->getNetworkProperties()->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::WALLCLIMBING, false);
    }

    protected function syncNetworkData(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection $properties): void {
        parent::syncNetworkData($properties);
        $properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::WALLCLIMBING, $this->isCollidedHorizontally);
    }

    protected function moveTowardsTarget(): void {
        parent::moveTowardsTarget();
        if ($this->isCollidedHorizontally) {
            $this->motion->y = 0.2;
        }
    }
}
