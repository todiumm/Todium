<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\nether;

use pocketmine\entity\vanilla\Monster;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;

class Piglin extends Monster {
    private int $barterTicks = 0;
    private ?ItemEntity $targetGoldEntity = null;
    private ?Item $heldItem = null;

    protected function sendSpawnPacket(\pocketmine\player\Player $player): void {
        parent::sendSpawnPacket($player);
        try {
            $pk = new \pocketmine\network\mcpe\protocol\MobEquipmentPacket();
            $pk->actorRuntimeId = $this->getId();
            $pk->item = \pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper::legacy(\pocketmine\network\mcpe\convert\TypeConverter::getInstance()->coreItemStackToNet($this->heldItem ?? \pocketmine\item\VanillaItems::GOLDEN_SWORD()));
            $pk->inventorySlot = 0;
            $pk->hotbarSlot = 0;
            $pk->windowId = 0;
            $player->getNetworkSession()->sendDataPacket($pk);
        } catch (\Throwable $e) {}
    }

    private function equipItem(Item $item): void {
        $this->heldItem = $item;
        try {
            $pk = new \pocketmine\network\mcpe\protocol\MobEquipmentPacket();
            $pk->actorRuntimeId = $this->getId();
            $pk->item = \pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper::legacy(\pocketmine\network\mcpe\convert\TypeConverter::getInstance()->coreItemStackToNet($item));
            $pk->inventorySlot = 0;
            $pk->hotbarSlot = 0;
            $pk->windowId = 0;
            $this->getWorld()->broadcastPacketToViewers($this->getLocation(), $pk);
        } catch (\Throwable $e) {}
    }

    public static function getNetworkTypeId(): string {
        return "minecraft:piglin";
    }

    public function onUpdate(int $currentTick): bool {
        $hasUpdate = parent::onUpdate($currentTick);
        if ($this->barterTicks > 0) {
            $this->barterTicks--;
            if ($this->barterTicks <= 0) {
                $this->barter();
                $this->equipItem(\pocketmine\item\VanillaItems::GOLDEN_SWORD());
            }
            $hasUpdate = true;
        }
        return $hasUpdate;
    }

    private function isWearingGoldArmor(Player $player): bool {
        $armorInventory = $player->getArmorInventory();
        foreach ($armorInventory->getContents() as $item) {
            $typeId = $item->getTypeId();
            if (
                $typeId === \pocketmine\item\VanillaItems::GOLDEN_HELMET()->getTypeId() ||
                $typeId === \pocketmine\item\VanillaItems::GOLDEN_CHESTPLATE()->getTypeId() ||
                $typeId === \pocketmine\item\VanillaItems::GOLDEN_LEGGINGS()->getTypeId() ||
                $typeId === \pocketmine\item\VanillaItems::GOLDEN_BOOTS()->getTypeId()
            ) {
                return true;
            }
        }
        return false;
    }

    protected function calculateAI(): void {
        if ($this->barterTicks > 0) {
            $this->targetPosition = null;
            return;
        }

        if ($this->attackDelay > 0) {
            $this->attackDelay -= 10;
        }

        if ($this->targetGoldEntity !== null && !$this->targetGoldEntity->isClosed() && $this->targetGoldEntity->getWorld() === $this->getWorld()) {
            $dist = $this->location->distanceSquared($this->targetGoldEntity->getLocation());
            if ($dist < 1.8) {

                $item = $this->targetGoldEntity->getItem();
                if ($item->getCount() > 1) {
                    $this->targetGoldEntity->setStackSize($item->getCount() - 1);
                } else {
                    $this->targetGoldEntity->flagForDespawn();
                }

                $this->targetGoldEntity = null;
                $this->targetPosition = null;
                $this->equipItem(\pocketmine\item\VanillaItems::GOLD_INGOT());
                $this->barterTicks = 40;
                return;
            } else {
                $this->targetPosition = clone $this->targetGoldEntity->getLocation();
                return;
            }
        } else {
            $this->targetGoldEntity = null;

            $nearestGold = null;
            $nearestGoldDist = 100.0;
            foreach ($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(10, 5, 10), $this) as $entity) {
                if ($entity instanceof ItemEntity) {
                    $item = $entity->getItem();
                    if ($item->getTypeId() === \pocketmine\item\VanillaItems::GOLD_INGOT()->getTypeId()) {
                        $dist = $this->location->distanceSquared($entity->getLocation());
                        if ($dist < $nearestGoldDist) {
                            $nearestGoldDist = $dist;
                            $nearestGold = $entity;
                        }
                    }
                }
            }
            if ($nearestGold !== null) {
                $this->targetGoldEntity = $nearestGold;
                $this->targetPosition = clone $nearestGold->getLocation();
                return;
            }
        }

        $nearest = null;
        $minDist = 1600;

        foreach ($this->getWorld()->getPlayers() as $player) {
            if ($player->isCreative() || $player->isSpectator()) continue;
            if ($this->isWearingGoldArmor($player)) continue;

            $dist = $this->location->distanceSquared($player->getLocation());
            if ($dist < $minDist) {
                $minDist = $dist;
                $nearest = $player;
            }
        }

        if ($nearest !== null) {
            $this->targetEntity = $nearest;
            $this->targetPosition = clone $nearest->getLocation();

            if ($minDist < 2.5 && $this->attackDelay <= 0) {
                $ev = new \pocketmine\event\entity\EntityDamageByEntityEvent($this, $nearest, \pocketmine\event\entity\EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getAttackDamage());
                $nearest->attack($ev);
                $this->attackDelay = 20;
            }
        } else {
            $this->targetEntity = null;

            if ($this->targetPosition === null || mt_rand(1, 100) <= 10) {
                $this->targetPosition = clone $this->location->add(mt_rand(-6, 6), 0, mt_rand(-6, 6));
            }
        }
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool {
        if ($this->barterTicks > 0) return false;

        $item = $player->getInventory()->getItemInHand();
        if ($item->getTypeId() === \pocketmine\item\VanillaItems::GOLD_INGOT()->getTypeId()) {
            $item->pop();
            $player->getInventory()->setItemInHand($item);

            $pk = new \pocketmine\network\mcpe\protocol\AnimatePacket();
            $pk->action = \pocketmine\network\mcpe\protocol\AnimatePacket::ACTION_SWING_ARM;
            $pk->actorRuntimeId = $this->getId();
            $this->getWorld()->broadcastPacketToViewers($this->getLocation(), $pk);

            $this->equipItem(\pocketmine\item\VanillaItems::GOLD_INGOT());
            $this->barterTicks = 40;
            return true;
        }
        return parent::onInteract($player, $clickPos);
    }

    private function barter(): void {
        $lootTable = [
            fn() => \pocketmine\item\VanillaItems::ENDER_PEARL()->setCount(mt_rand(2, 4)),
            fn() => \pocketmine\item\VanillaItems::FIRE_CHARGE()->setCount(mt_rand(1, 5)),
            fn() => \pocketmine\block\VanillaBlocks::OBSIDIAN()->asItem()->setCount(mt_rand(1, 1)),
            fn() => \pocketmine\item\VanillaItems::LEATHER()->setCount(mt_rand(2, 5)),
            fn() => \pocketmine\item\VanillaItems::IRON_INGOT()->setCount(mt_rand(2, 5)),
            fn() => \pocketmine\item\VanillaItems::GLOWSTONE_DUST()->setCount(mt_rand(2, 5)),
            fn() => \pocketmine\item\VanillaItems::STRING()->setCount(mt_rand(4, 12)),
            fn() => \pocketmine\item\VanillaItems::NETHER_QUARTZ()->setCount(mt_rand(4, 12)),
            fn() => \pocketmine\block\VanillaBlocks::GRAVEL()->asItem()->setCount(mt_rand(8, 16)),
        ];

        $randomLoot = $lootTable[array_rand($lootTable)]();
        $this->getWorld()->dropItem($this->getLocation(), $randomLoot);
        $this->getWorld()->addSound($this->getLocation(), new \pocketmine\world\sound\PopSound());
    }
}
