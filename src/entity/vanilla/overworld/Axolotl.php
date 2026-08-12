<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

class Axolotl extends Animal {

    private int $variant = 0;

    public static function getNetworkTypeId(): string {
        return "minecraft:axolotl";
    }

    public function isAquatic(): bool {
        return true;
    }

    public function isBreedingItem(Item $item): bool {
        return $item->getTypeId() === VanillaItems::CLOWNFISH()->getTypeId();
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        if ($nbt->getTag("Variant") !== null) {
            $this->variant = $nbt->getInt("Variant");
        } else {

            $r = mt_rand(1, 1000);
            if ($r <= 5) {
                $this->variant = 4;
            } elseif ($r <= 250) {
                $this->variant = 0;
            } elseif ($r <= 500) {
                $this->variant = 1;
            } elseif ($r <= 750) {
                $this->variant = 2;
            } else {
                $this->variant = 3;
            }
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
