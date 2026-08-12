<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;

class Squid extends Animal {
    public static function getNetworkTypeId(): string {
        return "minecraft:squid";
    }

    public function isAquatic(): bool {
        return true;
    }
}
