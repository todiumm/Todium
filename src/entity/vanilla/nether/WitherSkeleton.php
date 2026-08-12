<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\nether;

use pocketmine\entity\vanilla\Monster;

class WitherSkeleton extends Monster {
    protected function sendSpawnPacket(\pocketmine\player\Player $player): void {
        parent::sendSpawnPacket($player);
        try {
            $pk = new \pocketmine\network\mcpe\protocol\MobEquipmentPacket();
            $pk->actorRuntimeId = $this->getId();
            $pk->item = \pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper::legacy(\pocketmine\network\mcpe\convert\TypeConverter::getInstance()->coreItemStackToNet(\pocketmine\item\VanillaItems::IRON_SWORD()));
            $pk->inventorySlot = 0;
            $pk->hotbarSlot = 0;
            $pk->windowId = 0;
            $player->getNetworkSession()->sendDataPacket($pk);
        } catch (\Throwable $e) {}
    }

    public static function getNetworkTypeId(): string {
        return "minecraft:wither_skeleton";
    }
}
