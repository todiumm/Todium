<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;

class Turtle extends Animal {
    public static function getNetworkTypeId(): string {
        return "minecraft:turtle";
    }

    public function isAquatic(): bool {
        return true;
    }

    public function isBreedingItem(\pocketmine\item\Item $item): bool {
        $seagrass = \pocketmine\item\StringToItemParser::getInstance()->parse("seagrass");
        return $seagrass !== null && $item->equals($seagrass, false, false);
    }
}
