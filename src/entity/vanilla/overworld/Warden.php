<?php
declare(strict_types=1);

namespace pocketmine\entity\vanilla\overworld;

use pocketmine\entity\vanilla\Monster;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\world\particle\DustParticle;
use pocketmine\color\Color;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;

class Warden extends Monster {

    private int $sonicBoomCooldown = 0;
    private int $sonicBoomChargeTicks = 0;
    private ?Living $sonicBoomTarget = null;
    private int $darknessInterval = 0;
    private int $emergeTicks = 0;

    public static function getNetworkTypeId(): string {
        return "minecraft:warden";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(2.9, 0.9);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->setMaxHealth(500);
        $this->setHealth(500);
        if ($nbt->getTag("Emerged") !== null) {
            $this->emergeTicks = 0;
        } else {
            $this->emergeTicks = 80;
            $soundPacket = PlaySoundPacket::create(
                "mob.warden.emerge", 
                $this->location->x, $this->location->y, $this->location->z, 
                1.0, 1.0, null
            );
            foreach ($this->getWorld()->getPlayers() as $p) {
                $p->getNetworkSession()->sendDataPacket($soundPacket);
            }
        }
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setByte("Emerged", 1);
        return $nbt;
    }

    public function getMovementSpeed(): float {
        return $this->targetEntity !== null ? 0.30 : 0.20;
    }

    public function attack(EntityDamageEvent $source): void {
        if ($this->emergeTicks > 0) {
            $source->cancel();
            return;
        }
        parent::attack($source);
    }

    public function onUpdate(int $currentTick): bool {
        if ($this->emergeTicks > 0) {
            $this->emergeTicks--;
            $this->motion->x = 0.0;
            $this->motion->y = 0.0;
            $this->motion->z = 0.0;

            if ($this->emergeTicks % 4 === 0) {
                $pos = $this->location;
                $colors = [new Color(110, 80, 50), new Color(50, 60, 65), new Color(0, 100, 100)];
                for ($i = 0; $i < 12; $i++) {
                    $color = $colors[array_rand($colors)];
                    $offset = $pos->add(mt_rand(-10, 10)/10, 0.05, mt_rand(-10, 10)/10);
                    $this->getWorld()->addParticle($offset, new DustParticle($color, (float)(mt_rand(10, 20)/10)));
                }
                
                $digSound = PlaySoundPacket::create(
                    "block.suspicious_sand.break",
                    $this->location->x, $this->location->y, $this->location->z,
                    0.8, (float)(mt_rand(6, 10)/10), null
                );
                foreach ($this->getWorld()->getPlayers() as $p) {
                    $p->getNetworkSession()->sendDataPacket($digSound);
                }
            }

            if ($this->emergeTicks === 0) {
                $roarSound = PlaySoundPacket::create(
                    "mob.warden.roar",
                    $this->location->x, $this->location->y, $this->location->z,
                    1.5, 1.0, null
                );
                foreach ($this->getWorld()->getPlayers() as $p) {
                    $p->getNetworkSession()->sendDataPacket($roarSound);
                }

                $pos = $this->location->add(0, 1.0, 0);
                $cyan = new Color(0, 255, 255);
                $gray = new Color(100, 100, 100);
                for ($angle = 0; $angle < 360; $angle += 15) {
                    $rad = deg2rad($angle);
                    $direction = new Vector3(cos($rad), 0, sin($rad));
                    for ($dist = 0.5; $dist <= 3.0; $dist += 0.5) {
                        $offset = $pos->add($direction->x * $dist, mt_rand(-5, 5)/10, $direction->z * $dist);
                        $this->getWorld()->addParticle($offset, new DustParticle(mt_rand(0, 1) ? $cyan : $gray, 1.8));
                    }
                }

                $effect = method_exists(VanillaEffects::class, "DARKNESS") ? VanillaEffects::DARKNESS() : VanillaEffects::BLINDNESS();
                foreach ($this->getWorld()->getPlayers() as $player) {
                    if ($player->isAlive() && !$player->isCreative() && !$player->isSpectator()) {
                        if ($this->location->distanceSquared($player->getLocation()) <= 400) {
                            $player->getEffects()->add(new EffectInstance($effect, 200, 0, false));
                        }
                    }
                }
            }
            return true;
        }

        $hasUpdate = parent::onUpdate($currentTick);
        if (!$this->isAlive() || $this->isClosed()) {
            return $hasUpdate;
        }

        // Apply periodic darkness to players nearby
        $this->darknessInterval++;
        if ($this->darknessInterval >= 100) {
            $this->darknessInterval = 0;
            $effect = method_exists(VanillaEffects::class, "DARKNESS") ? VanillaEffects::DARKNESS() : VanillaEffects::BLINDNESS();
            foreach ($this->getWorld()->getPlayers() as $player) {
                if ($player->isAlive() && !$player->isCreative() && !$player->isSpectator()) {
                    if ($this->location->distanceSquared($player->getLocation()) <= 400) {
                        $player->getEffects()->add(new EffectInstance($effect, 160, 0, false));
                    }
                }
            }
        }

        // Handle sonic boom charging state
        if ($this->sonicBoomChargeTicks > 0) {
            $this->sonicBoomChargeTicks--;
            
            if ($this->sonicBoomTarget !== null && $this->sonicBoomTarget->isAlive() && !$this->sonicBoomTarget->isClosed()) {
                $this->lookAt($this->sonicBoomTarget->getLocation());
                
                $pos = $this->location->add(0, 1.6, 0);
                $color = new Color(0, 255, 255); // Cyan
                for ($i = 0; $i < 4; $i++) {
                    $offset = $pos->add(mt_rand(-5, 5)/10, mt_rand(-5, 5)/10, mt_rand(-5, 5)/10);
                    $this->getWorld()->addParticle($offset, new DustParticle($color, 1.0));
                }
            }

            if ($this->sonicBoomChargeTicks === 0) {
                $this->fireSonicBoom();
            }
            $hasUpdate = true;
        }

        if ($this->sonicBoomCooldown > 0) {
            $this->sonicBoomCooldown--;
        }

        return $hasUpdate;
    }

    protected function calculateAI(): void {
        if ($this->emergeTicks > 0 || $this->sonicBoomChargeTicks > 0) {
            $this->targetPosition = null;
            $this->motion->x = 0;
            $this->motion->y = 0;
            $this->motion->z = 0;
            return;
        }

        parent::calculateAI();

        $target = $this->targetEntity;
        if ($target !== null && $target->isAlive() && !$target->isClosed()) {
            $dist = $this->location->distance($target->getLocation());

            if ($dist <= 20.0 && $this->sonicBoomCooldown <= 0 && $this->sonicBoomChargeTicks <= 0) {
                $this->startSonicBoomCharge($target);
            }
        }
    }

    private function startSonicBoomCharge(Living $target): void {
        $this->sonicBoomChargeTicks = 30;
        $this->sonicBoomCooldown = 60;
        $this->sonicBoomTarget = $target;

        $soundPacket = PlaySoundPacket::create(
            "mob.warden.sonic_charge", 
            $this->location->x, $this->location->y, $this->location->z, 
            1.0, 1.0, null
        );
        foreach ($this->getWorld()->getPlayers() as $p) {
            $p->getNetworkSession()->sendDataPacket($soundPacket);
        }
    }

    private function fireSonicBoom(): void {
        $target = $this->sonicBoomTarget;
        if ($target === null || !$target->isAlive() || $target->isClosed() || $target->getWorld() !== $this->getWorld()) {
            $this->sonicBoomTarget = null;
            return;
        }

        $dist = $this->location->distance($target->getLocation());
        if ($dist > 22.0) {
            $this->sonicBoomTarget = null;
            return;
        }

        $startPos = $this->location->add(0, 1.6, 0);
        $endPos = $target->getLocation()->add(0, $target->getEyeHeight() / 2, 0);
        $diff = $endPos->subtractVector($startPos);
        $steps = (int)($dist * 2);
        if ($steps > 0) {
            $delta = $diff->multiply(1 / $steps);
            $currentPos = clone $startPos;
            $color = new Color(0, 191, 255);
            for ($i = 0; $i < $steps; $i++) {
                $currentPos = $currentPos->addVector($delta);
                for ($j = 0; $j < 3; $j++) {
                    $offset = $currentPos->add(mt_rand(-2, 2)/10, mt_rand(-2, 2)/10, mt_rand(-2, 2)/10);
                    $this->getWorld()->addParticle($offset, new DustParticle($color, 1.5));
                }
            }
        }

        $soundPacket = PlaySoundPacket::create(
            "mob.warden.sonic_boom", 
            $this->location->x, $this->location->y, $this->location->z, 
            1.5, 1.0, null
        );
        foreach ($this->getWorld()->getPlayers() as $p) {
            $p->getNetworkSession()->sendDataPacket($soundPacket);
        }

        $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_MAGIC, 15.0);
        $target->attack($ev);

        $direction = $diff->normalize();
        $target->setMotion($target->getMotion()->add($direction->x * 1.5, 0.4, $direction->z * 1.5));

        $this->sonicBoomTarget = null;
    }
}
