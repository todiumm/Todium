<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Animal;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

class Bee extends Animal {

    private int $angerTicks = 0;
    private ?Player $angerTarget = null;
    private bool $isAngry = false;

    public static function getNetworkTypeId(): string {
        return "minecraft:bee";
    }

    public function isFlying(): bool {
        return true;
    }

    public function isBreedingItem(\pocketmine\item\Item $item): bool {
        $name = strtolower($item->getName());
        return str_contains($name, "flower") ||
               str_contains($name, "tulip") ||
               str_contains($name, "orchid") ||
               str_contains($name, "allium") ||
               str_contains($name, "poppy") ||
               str_contains($name, "dandelion") ||
               str_contains($name, "daisy") ||
               str_contains($name, "rose") ||
               str_contains($name, "lilac") ||
               str_contains($name, "peony");
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->angerTicks = $nbt->getInt("AngerTicks", 0);
        $this->isAngry = $this->angerTicks > 0;
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setInt("AngerTicks", $this->angerTicks);
        return $nbt;
    }

    public function setAngry(bool $angry): void {
        $this->isAngry = $angry;
        $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::ANGRY, $angry);
        if (!$angry) {
            $this->angerTicks = 0;
            $this->angerTarget = null;
        }
    }

    protected function syncNetworkData(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection $properties): void {
        parent::syncNetworkData($properties);
        $properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->isAngry);
    }

    public function isAngry(): bool {
        return $this->isAngry;
    }

    public function anger(?Player $player): void {
        $this->setAngry(true);
        $this->angerTicks = 600;
        $this->angerTarget = $player;
    }

    public function onUpdate(int $currentTick): bool {
        $hasUpdate = parent::onUpdate($currentTick);
        if (!$this->isAlive() || $this->isClosed()) {
            return $hasUpdate;
        }

        if ($this->isAngry()) {
            if ($this->angerTicks > 0) {
                $this->angerTicks--;
                if ($this->angerTarget !== null && (!$this->angerTarget->isOnline() || $this->angerTarget->getWorld() !== $this->getWorld() || $this->angerTarget->isCreative())) {
                    $this->setAngry(false);
                }
            } else {
                $this->setAngry(false);
            }
            $hasUpdate = true;
        }

        return $hasUpdate;
    }

    protected function calculateAI(): void {
        if ($this->isAngry() && $this->angerTarget !== null) {
            $player = $this->angerTarget;
            $this->targetPosition = clone $player->getLocation();

            $dist = $this->location->distanceSquared($player->getLocation());
            if ($dist < 2.0) {

                $ev = new \pocketmine\event\entity\EntityDamageByEntityEvent($this, $player, \pocketmine\event\entity\EntityDamageEvent::CAUSE_ENTITY_ATTACK, 2.0);
                $player->attack($ev);

                $player->getEffects()->add(new \pocketmine\entity\effect\EffectInstance(
                    \pocketmine\entity\effect\VanillaEffects::POISON(),
                    120,
                    0
                ));

                $this->setAngry(false);
            }
            return;
        }

        parent::calculateAI();
    }
}
