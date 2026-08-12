<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\object\ItemEntity;
use pocketmine\world\particle\HeartParticle;

class Panda extends Animal {

    private bool $isTamed = false;
    private int $sitEatingTicks = 0;
    private bool $isSitting = false;

    public static function getNetworkTypeId(): string {
        return "minecraft:panda";
    }

    public function isBreedingItem(\pocketmine\item\Item $item): bool {
        $bamboo = \pocketmine\item\StringToItemParser::getInstance()->parse("bamboo");
        return $bamboo !== null && $item->equals($bamboo, false, false);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->isTamed = $nbt->getByte("IsTamed", 0) === 1;
        $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::TAMED, $this->isTamed);
        $this->getNetworkProperties()->setFloat(EntityMetadataProperties::SITTING_AMOUNT, 0.0);
        $this->getNetworkProperties()->setFloat(EntityMetadataProperties::SITTING_AMOUNT_PREVIOUS, 0.0);
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setByte("IsTamed", $this->isTamed ? 1 : 0);
        return $nbt;
    }

    public function isTamed(): bool {
        return $this->isTamed;
    }

    public function setTamed(bool $tamed): void {
        $this->isTamed = $tamed;
        $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::TAMED, $tamed);
    }

    public function isSitting(): bool {
        return $this->isSitting;
    }

    protected function syncNetworkData(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection $properties): void {
        parent::syncNetworkData($properties);
        $properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->isTamed);
        $properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->isSitting);
        $properties->setGenericFlag(EntityMetadataFlags::EATING, $this->isSitting);
        $properties->setFloat(EntityMetadataProperties::SITTING_AMOUNT, $this->isSitting ? 1.0 : 0.0);
        $properties->setFloat(EntityMetadataProperties::SITTING_AMOUNT_PREVIOUS, $this->isSitting ? 1.0 : 0.0);
    }

    public function setSitting(bool $sitting): void {
        $this->isSitting = $sitting;
        $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SITTING, $sitting);
        $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::EATING, $sitting);
        $this->getNetworkProperties()->setFloat(EntityMetadataProperties::SITTING_AMOUNT, $sitting ? 1.0 : 0.0);
        $this->getNetworkProperties()->setFloat(EntityMetadataProperties::SITTING_AMOUNT_PREVIOUS, $sitting ? 1.0 : 0.0);
    }

    public function startEating(): void {
        $this->sitEatingTicks = 100;
        $this->setSitting(true);
        $this->targetPosition = null;
        $this->motion->x = 0.0;
        $this->motion->z = 0.0;

        $bambooItem = \pocketmine\item\StringToItemParser::getInstance()->parse("bamboo") ?? \pocketmine\item\VanillaItems::AIR();
        $netItem = \pocketmine\network\mcpe\convert\TypeConverter::getInstance()->coreItemStackToNet($bambooItem);
        $wrapper = \pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper::legacy($netItem);
        $pk = \pocketmine\network\mcpe\protocol\MobEquipmentPacket::create(
            $this->getId(),
            $wrapper,
            0,
            0,
            \pocketmine\network\mcpe\protocol\types\inventory\ContainerIds::INVENTORY
        );
        foreach ($this->getViewers() as $viewer) {
            $viewer->getNetworkSession()->sendDataPacket($pk);
        }
    }

    public function onUpdate(int $currentTick): bool {
        if ($this->sitEatingTicks > 0) {
            $this->sitEatingTicks--;
            $this->targetPosition = null;
            $this->motion->x = 0.0;
            $this->motion->z = 0.0;

            if ($this->sitEatingTicks % 15 === 0) {
                $this->getWorld()->addSound($this->getLocation(), new \pocketmine\world\sound\ItemUseOnBlockSound(\pocketmine\block\VanillaBlocks::OAK_WOOD()));
            }

            if ($this->sitEatingTicks % 20 === 0) {
                $bambooItem = \pocketmine\item\StringToItemParser::getInstance()->parse("bamboo") ?? \pocketmine\item\VanillaItems::AIR();
                $netItem = \pocketmine\network\mcpe\convert\TypeConverter::getInstance()->coreItemStackToNet($bambooItem);
                $wrapper = \pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper::legacy($netItem);
                $pk = \pocketmine\network\mcpe\protocol\MobEquipmentPacket::create(
                    $this->getId(),
                    $wrapper,
                    0,
                    0,
                    \pocketmine\network\mcpe\protocol\types\inventory\ContainerIds::INVENTORY
                );
                foreach ($this->getViewers() as $viewer) {
                    $viewer->getNetworkSession()->sendDataPacket($pk);
                }
            }

            if ($this->sitEatingTicks === 0) {
                $this->setSitting(false);

                $airItem = \pocketmine\item\VanillaItems::AIR();
                $netItem = \pocketmine\network\mcpe\convert\TypeConverter::getInstance()->coreItemStackToNet($airItem);
                $wrapper = \pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper::legacy($netItem);
                $pk = \pocketmine\network\mcpe\protocol\MobEquipmentPacket::create(
                    $this->getId(),
                    $wrapper,
                    0,
                    0,
                    \pocketmine\network\mcpe\protocol\types\inventory\ContainerIds::INVENTORY
                );
                foreach ($this->getViewers() as $viewer) {
                    $viewer->getNetworkSession()->sendDataPacket($pk);
                }
            }
        }

        return parent::onUpdate($currentTick);
    }

    protected function calculateAI(): void {
        if ($this->sitEatingTicks > 0) {
            $this->targetPosition = null;
            return;
        }

        $bambooEntity = null;
        $closestDist = 999.0;

        foreach ($this->getWorld()->getEntities() as $entity) {
            if ($entity instanceof ItemEntity) {
                $item = $entity->getItem();
                $bambooItem = \pocketmine\item\StringToItemParser::getInstance()->parse("bamboo");
                if ($bambooItem !== null && $item->equals($bambooItem, false, false)) {
                    $dist = $this->getLocation()->distanceSquared($entity->getLocation());
                    if ($dist < 144 && $dist < $closestDist) {
                        $closestDist = $dist;
                        $bambooEntity = $entity;
                    }
                }
            }
        }

        if ($bambooEntity !== null) {

            $this->targetPosition = $bambooEntity->getLocation();

            if ($closestDist <= 2.5) {
                $bambooEntity->close();
                $this->setTamed(true);
                $this->getWorld()->addParticle($this->getLocation()->add(0, 1.0, 0), new HeartParticle(3));
                $this->startEating();
            }
            return;
        }

        parent::calculateAI();
    }
}
