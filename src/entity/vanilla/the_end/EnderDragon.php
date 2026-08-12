<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\the_end;

use pocketmine\entity\vanilla\Monster;

class EnderDragon extends Monster {
    public static function getNetworkTypeId(): string {
        return "minecraft:ender_dragon";
    }

    public function isFlying(): bool {
        return true;
    }

    public function getXpDropAmount(): int {
        return 500;
    }
}
