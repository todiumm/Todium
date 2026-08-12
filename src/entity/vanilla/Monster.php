<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla;

use pocketmine\player\Player;
use pocketmine\math\Vector3;

use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\item\VanillaItems;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

abstract class Monster extends BaseMob {

    protected int $attackDelay = 0;

    public function __construct(Location $location, ?CompoundTag $nbt = null) {
        parent::__construct($location, $nbt);

        if (mt_rand(1, 100) <= 15 && $this->getArmorInventory() !== null) {
            $armors = [
                VanillaItems::LEATHER_CAP(), VanillaItems::IRON_HELMET(), VanillaItems::GOLDEN_HELMET(),
                VanillaItems::LEATHER_TUNIC(), VanillaItems::IRON_CHESTPLATE(), VanillaItems::GOLDEN_CHESTPLATE(),
                VanillaItems::LEATHER_PANTS(), VanillaItems::IRON_LEGGINGS(), VanillaItems::GOLDEN_LEGGINGS(),
                VanillaItems::LEATHER_BOOTS(), VanillaItems::IRON_BOOTS(), VanillaItems::GOLDEN_BOOTS()
            ];

            if (mt_rand(1, 10) <= 3) $this->getArmorInventory()->setHelmet($armors[mt_rand(0, 2)]);
            if (mt_rand(1, 10) <= 3) $this->getArmorInventory()->setChestplate($armors[mt_rand(3, 5)]);
            if (mt_rand(1, 10) <= 3) $this->getArmorInventory()->setLeggings($armors[mt_rand(6, 8)]);
            if (mt_rand(1, 10) <= 3) $this->getArmorInventory()->setBoots($armors[mt_rand(9, 11)]);
        }
    }

    protected function calculateAI(): void {

        if ($this->isSwimming()) {
            if ($this->targetPosition === null || mt_rand(1, 100) <= 15) {
                $foundWater = [];
                for ($x = -6; $x <= 6; $x++) {
                    for ($y = -3; $y <= 3; $y++) {
                        for ($z = -6; $z <= 6; $z++) {
                            $pos = $this->location->add($x, $y, $z);
                            if ($this->getWorld()->getBlock($pos) instanceof \pocketmine\block\Water) {
                                $foundWater[] = $pos;
                            }
                        }
                    }
                }
                if (!empty($foundWater)) {
                    $this->targetPosition = $foundWater[array_rand($foundWater)];
                }
            }
            return;
        }

        if ($this->isAquatic() && !$this->isSwimming()) {
            if ($this->targetPosition === null || mt_rand(1, 100) <= 20) {
                $foundWater = null;
                for ($x = -5; $x <= 5; $x++) {
                    for ($y = -2; $y <= 2; $y++) {
                        for ($z = -5; $z <= 5; $z++) {
                            $pos = $this->location->add($x, $y, $z);
                            if ($this->getWorld()->getBlock($pos) instanceof \pocketmine\block\Water) {
                                $foundWater = $pos;
                                break 3;
                            }
                        }
                    }
                }
                if ($foundWater !== null) {
                    $this->targetPosition = $foundWater;
                } else {
                    $this->targetPosition = clone $this->location->add(mt_rand(-2, 2), 0, mt_rand(-2, 2));
                }
            }
            return;
        }

        if ($this->targetPosition !== null && mt_rand(1, 100) <= 10) {

            if ($this->location->distanceSquared($this->targetPosition) > 400) {
                $this->targetPosition = null;
            }
        }
        if ($this->attackDelay > 0) {
            $this->attackDelay -= 10;
        }

        $nearest = null;
        $minDist = 1600;

        foreach ($this->getWorld()->getPlayers() as $player) {
            if ($player->isCreative() || $player->isSpectator()) continue;

            $dist = $this->location->distanceSquared($player->getLocation());
            if ($dist < $minDist) {
                $minDist = $dist;
                $nearest = $player;
            }
        }

        foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(20, 10, 20)) as $entity) {
            if (($entity instanceof \pocketmine\entity\vanilla\overworld\IronGolem || $entity instanceof \pocketmine\entity\vanilla\overworld\SnowGolem) && $entity->isAlive() && !$entity->isClosed()) {
                $dist = $this->location->distanceSquared($entity->getLocation());
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $nearest = $entity;
                }
            }
        }

        if ($nearest !== null) {
            $this->targetEntity = $nearest;
            $this->targetPosition = clone $nearest->getLocation();

            if ($minDist < 2.5 && $this->attackDelay <= 0) {
                $ev = new EntityDamageByEntityEvent($this, $nearest, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getAttackDamage());
                $nearest->attack($ev);

                if ($nearest instanceof Player) {
                    if ($this instanceof \pocketmine\entity\vanilla\overworld\CaveSpider) {
                        $nearest->getEffects()->add(new \pocketmine\entity\effect\EffectInstance(
                            \pocketmine\entity\effect\VanillaEffects::POISON(),
                            100, 
                            0
                        ));
                    } elseif ($this instanceof \pocketmine\entity\vanilla\nether\WitherSkeleton) {
                        $nearest->getEffects()->add(new \pocketmine\entity\effect\EffectInstance(
                            \pocketmine\entity\effect\VanillaEffects::WITHER(),
                            200, 
                            0
                        ));
                    } elseif ($this instanceof \pocketmine\entity\vanilla\overworld\Husk) {
                        $nearest->getEffects()->add(new \pocketmine\entity\effect\EffectInstance(
                            \pocketmine\entity\effect\VanillaEffects::HUNGER(),
                            140, 
                            0
                        ));
                    }
                }

                $pk = new \pocketmine\network\mcpe\protocol\AnimatePacket();
                $pk->action = \pocketmine\network\mcpe\protocol\AnimatePacket::ACTION_SWING_ARM;
                $pk->actorRuntimeId = $this->getId();
                $this->getWorld()->broadcastPacketToViewers($this->getLocation(), $pk);

                $this->attackDelay = 20;
            }
        } else {
            $this->targetEntity = null;

            if ($this->targetPosition === null || mt_rand(1, 100) <= 10) {
                if ($this->isFlying() || $this->isSwimming()) {
                    $randX = mt_rand(-8, 8);
                    $randZ = mt_rand(-8, 8);
                    $randY = mt_rand(-3, 3);
                    $newPos = $this->location->add($randX, $randY, $randZ);
                    if ($this->isSwimming()) {
                        if ($this->getWorld()->getBlock($newPos) instanceof \pocketmine\block\Water) {
                            $this->targetPosition = $newPos;
                        }
                    } else {
                        $chunkX = (int)floor($newPos->x) >> 4;
                        $chunkZ = (int)floor($newPos->z) >> 4;
                        if ($this->getWorld()->isChunkGenerated($chunkX, $chunkZ)) {
                            $highestBlockY = $this->getWorld()->getHighestBlockAt((int)floor($newPos->x), (int)floor($newPos->z));
                            if ($highestBlockY !== null) {
                                $targetY = max($highestBlockY + 1.5, min($highestBlockY + 8, $newPos->y));
                                $newPos->y = $targetY;
                            }
                            if (!$this->getWorld()->getBlock($newPos)->isSolid()) {
                                $this->targetPosition = $newPos;
                            }
                        }
                    }
                } else {
                    $this->targetPosition = clone $this->location->add(mt_rand(-6, 6), 0, mt_rand(-6, 6));
                }
            }
        }
    }

    public function getAttackDamage(): float {
        return parent::getAttackDamage();
    }

    public function getXpDropAmount(): int {
        return 5;
    }
}
