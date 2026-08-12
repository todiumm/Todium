<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\BaseMob;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class IronGolem extends BaseMob {

    private int $angerTicks = 0;
    private ?Player $angryTarget = null;
    private int $attackDelay = 0;

    public static function getNetworkTypeId(): string {
        return "minecraft:iron_golem";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(2.7, 1.4);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->setMaxHealth(100);
        $this->setHealth(100);
    }

    public function getMovementSpeed(): float {
        return $this->targetEntity !== null ? 0.32 : 0.22;
    }

    public function attack(EntityDamageEvent $source): void {
        parent::attack($source);
        if (!$source->isCancelled()) {
            if ($source instanceof EntityDamageByEntityEvent) {
                $damager = $source->getDamager();
                if ($damager instanceof Player && !$damager->isCreative()) {
                    $this->angryTarget = $damager;
                    $this->angerTicks = 600;
                }
            }
        }
    }

    protected function calculateAI(): void {
        if ($this->attackDelay > 0) {
            $this->attackDelay -= 10;
        }

        if ($this->angerTicks > 0) {
            $this->angerTicks -= 10;
            if ($this->angryTarget !== null && (!$this->angryTarget->isOnline() || $this->angryTarget->getWorld() !== $this->getWorld() || $this->angryTarget->isCreative())) {
                $this->angryTarget = null;
                $this->angerTicks = 0;
            }
        } else {
            $this->angryTarget = null;
        }

        $target = null;
        if ($this->angryTarget !== null) {
            $target = $this->angryTarget;
        } else {

            $nearest = null;
            $minDist = 1024;
            foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(32, 16, 32)) as $entity) {
                if ($entity instanceof \pocketmine\entity\vanilla\Monster && $entity->isAlive()) {
                    $dist = $this->location->distanceSquared($entity->getLocation());
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $nearest = $entity;
                    }
                }
            }
            if ($nearest !== null) {
                $target = $nearest;
            }
        }

        $this->targetEntity = $target;

        if ($target !== null) {
            $this->targetPosition = clone $target->getLocation();

            $dist = $this->location->distanceSquared($target->getLocation());
            if ($dist < 4.0 && $this->attackDelay <= 0) {

                $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 15.0);
                $target->attack($ev);

                $targetMotion = $target->getMotion();
                $target->setMotion(new Vector3($targetMotion->x, 0.65, $targetMotion->z));

                $pk = new \pocketmine\network\mcpe\protocol\AnimatePacket();
                $pk->action = \pocketmine\network\mcpe\protocol\AnimatePacket::ACTION_SWING_ARM;
                $pk->actorRuntimeId = $this->getId();
                $this->getWorld()->broadcastPacketToViewers($this->getLocation(), $pk);

                $this->attackDelay = 20;
            }
            return;
        }

        if ($this->targetPosition === null || mt_rand(1, 100) <= 15) {
            if (mt_rand(1, 100) <= 50) {
                $this->targetPosition = clone $this->location->add(mt_rand(-6, 6), 0, mt_rand(-6, 6));
            } else {
                $this->targetPosition = null;
            }
        }
    }

    public function getXpDropAmount(): int {
        return 0;
    }
}
