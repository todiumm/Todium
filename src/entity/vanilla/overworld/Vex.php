<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Monster;

class Vex extends Monster {
    public static function getNetworkTypeId(): string {
        return "minecraft:vex";
    }

    public function isFlying(): bool {
        return true;
    }

    public function move(float $dx, float $dy, float $dz): void {
        $this->location->x += $dx;
        $this->location->y += $dy;
        $this->location->z += $dz;
        $this->recalculateBoundingBox();
    }
}
