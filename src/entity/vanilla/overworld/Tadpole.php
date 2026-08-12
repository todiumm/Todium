<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;

class Tadpole extends Animal {
    public static function getNetworkTypeId(): string {
        return "minecraft:tadpole";
    }

    public function isAquatic(): bool {
        return true;
    }
}
