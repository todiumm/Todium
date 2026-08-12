<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;

class Cod extends Animal {

    public static function getNetworkTypeId(): string {
        return "minecraft:cod";
    }

    public function isAquatic(): bool {
        return true;
    }

    public function isBreedingItem(Item $item): bool {
        return false;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(0.35, 0.6);
    }
}
