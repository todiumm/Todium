<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

class Frog extends Animal {

    private int $variant = 0;

    public static function getNetworkTypeId(): string {
        return "minecraft:frog";
    }

    public function isBreedingItem(Item $item): bool {
        return $item->getTypeId() === VanillaItems::SLIMEBALL()->getTypeId();
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        if ($nbt->getTag("Variant") !== null) {
            $this->variant = $nbt->getInt("Variant");
        } else {
            $this->variant = mt_rand(0, 2);
        }

        $this->getNetworkProperties()->setInt(EntityMetadataProperties::VARIANT, $this->variant);
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setInt("Variant", $this->variant);
        return $nbt;
    }

    public function getVariant(): int {
        return $this->variant;
    }

    public function setVariant(int $variant): void {
        $this->variant = $variant;
        $this->getNetworkProperties()->setInt(EntityMetadataProperties::VARIANT, $variant);
    }
}
