<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Monster;

class CaveSpider extends Spider {
    public static function getNetworkTypeId(): string {
        return "minecraft:cave_spider";
    }
}
