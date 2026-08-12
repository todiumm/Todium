<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;

class Bat extends Animal {
    public static function getNetworkTypeId(): string {
        return "minecraft:bat";
    }

    public function isFlying(): bool {
        return true;
    }
}
