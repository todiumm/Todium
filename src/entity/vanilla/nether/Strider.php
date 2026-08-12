<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\nether;

use pocketmine\entity\vanilla\Animal;

class Strider extends Animal {
    public static function getNetworkTypeId(): string {
        return "minecraft:strider";
    }
}
