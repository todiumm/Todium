<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;

class Llama extends Animal {
    public static function getNetworkTypeId(): string {
        return "minecraft:llama";
    }

    public function isBreedingItem(\pocketmine\item\Item $item): bool {
        $hay = \pocketmine\item\StringToItemParser::getInstance()->parse("hay_block");
        return $hay !== null && $item->equals($hay, false, false);
    }
}
