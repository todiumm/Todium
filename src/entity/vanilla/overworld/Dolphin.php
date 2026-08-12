<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;

class Dolphin extends Animal {
    public static function getNetworkTypeId(): string {
        return "minecraft:dolphin";
    }

    public function isAquatic(): bool {
        return true;
    }
}
