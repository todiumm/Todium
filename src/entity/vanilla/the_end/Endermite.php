<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\the_end;

use pocketmine\entity\vanilla\Monster;

class Endermite extends Monster {
    public static function getNetworkTypeId(): string {
        return "minecraft:endermite";
    }
}
