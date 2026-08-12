<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\nether;

use pocketmine\entity\vanilla\Monster;

class Zoglin extends Monster {
    public static function getNetworkTypeId(): string {
        return "minecraft:zoglin";
    }
}
