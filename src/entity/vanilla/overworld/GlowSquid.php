<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;

class GlowSquid extends Animal {
    public static function getNetworkTypeId(): string {
        return "minecraft:glow_squid";
    }

    public function isAquatic(): bool {
        return true;
    }
}
