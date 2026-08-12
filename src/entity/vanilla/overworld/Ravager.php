<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Monster;

class Ravager extends Monster {
    public static function getNetworkTypeId(): string {
        return "minecraft:ravager";
    }
}
