<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\nbt\tag\CompoundTag;

class WanderingTrader extends Villager {
    public bool $spawnLlamas = false;

    public static function getNetworkTypeId(): string {
        return "minecraft:wandering_trader";
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        if ($nbt->getTag("LlamasSpawned") === null) {
            $nbt->setInt("LlamasSpawned", 1);
            $this->spawnLlamas = true;
        }
    }
}
