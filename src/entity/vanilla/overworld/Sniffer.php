<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;

class Sniffer extends Animal {
    public static function getNetworkTypeId(): string {
        return "minecraft:sniffer";
    }
}
