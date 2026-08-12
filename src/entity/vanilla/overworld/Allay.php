<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;

class Allay extends Animal {
    public static function getNetworkTypeId(): string {
        return "minecraft:allay";
    }

    public function isFlying(): bool {
        return true;
    }
}
