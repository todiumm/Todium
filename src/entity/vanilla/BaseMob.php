<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\Location;

abstract class BaseMob extends Living
{
    protected int $tickOffset = 0;
    protected ?Vector3 $targetPosition = null;
    protected ?\pocketmine\entity\Living $targetEntity = null;

    public function __construct(Location $location, ?CompoundTag $nbt = null)
    {
        $this->tickOffset = mt_rand(0, 20);
        parent::__construct($location, $nbt);
        if ($this->isFlying()) {
            $this->gravity = 0.0;
        }
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        $this->setMaxHealth($this->getDefaultMaxHealth());
        parent::initEntity($nbt);

        if ($nbt->getTag("MaxHealth") !== null) {
            $this->setMaxHealth($nbt->getInt("MaxHealth"));
        }

        $healthTag = $nbt->getTag("Health");
        if ($healthTag !== null) {
            $this->setHealth(min($healthTag->getValue(), $this->getMaxHealth()));
        } else {
            $this->setHealth($this->getMaxHealth());
        }

        $fenceLeashTag = $nbt->getCompoundTag("FenceLeash");
        if ($fenceLeashTag !== null) {
            $x = $fenceLeashTag->getInt("X");
            $y = $fenceLeashTag->getInt("Y");
            $z = $fenceLeashTag->getInt("Z");
            $worldName = $fenceLeashTag->getString("World");
            \pocketmine\vanilla\listener\LeashListener::registerFenceLeash($this, new Vector3($x, $y, $z), $worldName);
        }
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setInt("MaxHealth", $this->getMaxHealth());

        if (isset(\pocketmine\vanilla\listener\LeashListener::$fenceLeashedEntities[$this->getId()])) {
            $fenceData = \pocketmine\vanilla\listener\LeashListener::$fenceLeashedEntities[$this->getId()];
            $leashNBT = CompoundTag::create()
                ->setInt("X", (int)$fenceData['pos']->x)
                ->setInt("Y", (int)$fenceData['pos']->y)
                ->setInt("Z", (int)$fenceData['pos']->z)
                ->setString("World", $fenceData['world']);
            $nbt->setTag("FenceLeash", $leashNBT);
        }
        return $nbt;
    }

    public function getDefaultMaxHealth(): int
    {
        $className = static::class;
        $parts = explode('\\', $className);
        $name = strtolower(array_pop($parts));

        $healthMap = [
            'cow' => 10,
            'pig' => 10,
            'sheep' => 8,
            'chicken' => 4,
            'wolf' => 20,
            'cat' => 10,
            'ocelot' => 10,
            'horse' => 20,
            'donkey' => 20,
            'mule' => 20,
            'llama' => 20,
            'traderllama' => 20,
            'fox' => 10,
            'panda' => 20,
            'turtle' => 30,
            'dolphin' => 10,
            'squid' => 10,
            'glowsquid' => 10,
            'bat' => 6,
            'villager' => 20,
            'wanderingtrader' => 20,
            'irongolem' => 100,
            'snowgolem' => 4,
            'warden' => 500,
            'axolotl' => 14,
            'goat' => 20,
            'frog' => 10,
            'tadpole' => 6,
            'camel' => 32,
            'sniffer' => 14,
            'allay' => 20,
            'bee' => 10,
            'cod' => 3,
            'salmon' => 3,
            'pufferfish' => 3,
            'tropicalfish' => 3,
            'skeleton' => 20,
            'zombie' => 20,
            'zombievillager' => 20,
            'husk' => 20,
            'drowned' => 20,
            'stray' => 20,
            'creeper' => 20,
            'spider' => 16,
            'cavespider' => 12,
            'slime' => 16,
            'silverfish' => 8,
            'witch' => 26,
            'phantom' => 20,
            'vindicator' => 24,
            'evoker' => 24,
            'pillager' => 24,
            'ravager' => 100,
            'vex' => 14,
            'guardian' => 30,
            'elderguardian' => 80,
            'zombifiedpiglin' => 20,
            'piglin' => 16,
            'piglinbrute' => 50,
            'hoglin' => 40,
            'zoglin' => 40,
            'ghast' => 10,
            'blaze' => 20,
            'magmacube' => 16,
            'witherskeleton' => 20,
            'strider' => 20,
            'enderman' => 40,
            'endermite' => 8,
            'shulker' => 30,
            'enderdragon' => 200,
        ];

        return $healthMap[$name] ?? 20;
    }

    public function getAttackDamage(): float
    {
        $className = static::class;
        $parts = explode('\\', $className);
        $name = strtolower(array_pop($parts));

        $damageMap = [
            'zombie' => 3.0,
            'zombievillager' => 3.0,
            'husk' => 3.0,
            'drowned' => 3.0,
            'skeleton' => 2.0,
            'stray' => 2.0,
            'creeper' => 3.0,
            'spider' => 2.0,
            'cavespider' => 2.0,
            'silverfish' => 1.0,
            'witch' => 2.0,
            'phantom' => 6.0,
            'vindicator' => 8.0,
            'evoker' => 6.0,
            'pillager' => 4.0,
            'ravager' => 12.0,
            'vex' => 3.0,
            'guardian' => 6.0,
            'elderguardian' => 8.0,
            'zombifiedpiglin' => 5.0,
            'piglin' => 5.0,
            'piglinbrute' => 12.0,
            'hoglin' => 7.0,
            'zoglin' => 7.0,
            'ghast' => 6.0,
            'blaze' => 6.0,
            'magmacube' => 4.0,
            'witherskeleton' => 8.0,
            'strider' => 2.0,
            'enderman' => 7.0,
            'endermite' => 2.0,
            'shulker' => 4.0,
            'enderdragon' => 10.0,
            'irongolem' => 15.0,
            'wolf' => 4.0,
            'warden' => 30.0,
        ];

        return $damageMap[$name] ?? 3.0;
    }

    public function isFlying(): bool
    {
        return false;
    }
    public function isAquatic(): bool
    {
        return false;
    }

    public function isInsideOfWater(): bool
    {
        $block = $this->getWorld()->getBlock($this->location);
        if ($block instanceof \pocketmine\block\Water) return true;
        $eyeBlock = $this->getWorld()->getBlock($this->location->add(0, $this->getEyeHeight(), 0));
        return $eyeBlock instanceof \pocketmine\block\Water;
    }

    public function canBreathe(): bool
    {
        if ($this->isAquatic()) {
            return $this->isInsideOfWater() || $this->isUnderwater();
        }
        return parent::canBreathe();
    }

    public function isSwimming(): bool
    {
        return $this->isAquatic() && $this->isInsideOfWater();
    }

    public function getInitialGravity(): float
    {
        return $this->isFlying() ? 0.0 : parent::getInitialGravity();
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.8, 0.6);
    }

    public function isUndead(): bool
    {
        return false;
    }

    public function getName(): string
    {
        $path = explode('\\', static::class);
        return array_pop($path);
    }

    public function isSafePosition(Vector3 $pos): bool {
        if ($this->isFlying() || $this->isSwimming()) {
            return !$this->getWorld()->getBlock($pos)->isSolid();
        }
        
        $checkPos = $pos->add(0, 0.5, 0);
        $blockAt = $this->getWorld()->getBlock($checkPos);
        if ($blockAt->isSolid()) {
            return false;
        }

        for ($i = 1; $i <= 3; $i++) {
            if ($this->getWorld()->getBlock($checkPos->subtract(0, $i, 0))->isSolid()) {
                return true;
            }
        }
        return false;
    }

    private bool $hasPlayerNearby = true;

    public function onUpdate(int $currentTick): bool
    {
        if ($this->isSwimming()) {
            $this->gravity = 0.0;
        } else {
            $this->gravity = $this->isFlying() ? 0.0 : $this->getInitialGravity();
        }

        $hasUpdate = parent::onUpdate($currentTick);

        if (!$this->isAlive() || $this->isClosed()) {
            return $hasUpdate;
        }

        if (($currentTick + $this->tickOffset) % $this->getAIUpdateInterval() === 0) {
            $this->hasPlayerNearby = false;
            foreach ($this->getWorld()->getPlayers() as $player) {
                if ($this->location->distanceSquared($player->getLocation()) <= 2304) {
                    $this->hasPlayerNearby = true;
                    break;
                }
            }

            $isLeashed = \pocketmine\vanilla\listener\LeashListener::getLeashHolder($this) !== null;

            if ($this->hasPlayerNearby && !$isLeashed) {
                $this->calculateAI();
                if ($this->targetPosition !== null && !$this->isSafePosition($this->targetPosition)) {
                    $this->targetPosition = null;
                }
            } elseif (!$isLeashed) {
                $this->targetPosition = null;
                $this->motion->x = 0;
                $this->motion->z = 0;
            }
            $hasUpdate = true;

            if ($this->hasPlayerNearby && $this->isUndead() && $this->getWorld()->getTimeOfDay() < \pocketmine\world\World::TIME_NIGHT) {
                if (!\pocketmine\vanilla\listener\EventListener::isWorldRaining($this->getWorld())) {
                    $x = $this->location->getFloorX();
                    $z = $this->location->getFloorZ();
                    if ($this->getWorld()->isChunkGenerated($x >> 4, $z >> 4) && $this->getWorld()->getHighestBlockAt($x, $z) <= $this->location->getFloorY()) {
                        $helmet = $this->getArmorInventory() !== null ? $this->getArmorInventory()->getHelmet() : null;
                        if ($helmet === null || $helmet->isNull()) {
                            $this->setOnFire(8);
                        }
                    }
                }
            }
        }

        if ($this->hasPlayerNearby) {
            if ($this->targetEntity !== null && $this->targetEntity->isAlive() && !$this->targetEntity->isClosed()) {
                if ($this->targetPosition !== null) {
                    if (!$this->isFlying() && !$this->isSwimming()) {
                        $this->targetPosition = clone $this->targetEntity->getLocation();
                    }
                }
            }

            if ($this->targetPosition !== null) {
                $this->moveTowardsTarget();
                $hasUpdate = true;
            }
        }

        $holderPos = \pocketmine\vanilla\listener\LeashListener::getLeashHolder($this);
        if ($holderPos !== null) {
            $this->targetPosition = null;
            $dist = $this->location->distance($holderPos);
            if ($dist > 5.0) {
                $dir = $holderPos->subtractVector($this->location);
                $motionY = $this->motion->y;
                if ($dir->y > 0) {
                    $motionY = $dir->y / max($dist, 1.0) * 0.4;
                }
                $dir->y = 0;
                if ($dir->lengthSquared() > 0) {
                    $dir = $dir->normalize();
                }
                $speed = min(0.45, $dist * 0.06);
                $this->setMotion(new Vector3($dir->x * $speed, $motionY, $dir->z * $speed));
                if ($this->isCollidedHorizontally) {
                    $this->motion->y = max($this->motion->y, 0.42);
                }
                $yaw = rad2deg(atan2($dir->z, $dir->x)) - 90;
                $this->setRotation($yaw, 0);
                $hasUpdate = true;
            } elseif ($dist > 2.0) {
                $dir = $holderPos->subtractVector($this->location);
                $dir->y = 0;
                if ($dir->lengthSquared() > 0) {
                    $dir = $dir->normalize();
                }
                $speed = $this->getMovementSpeed();
                $this->setMotion(new Vector3($dir->x * $speed, $this->motion->y, $dir->z * $speed));
                if ($this->isCollidedHorizontally && $this->onGround) {
                    $this->motion->y = $this->getJumpVelocity();
                }
                $yaw = rad2deg(atan2($dir->z, $dir->x)) - 90;
                $this->setRotation($yaw, 0);
                $hasUpdate = true;
            }
        }

        return $hasUpdate;
    }

    protected function calculateAI(): void {}

    protected function getAIUpdateInterval(): int
    {
        return 10;
    }

    protected function moveTowardsTarget(): void
    {
        if ($this->targetPosition === null) return;

        $x = $this->targetPosition->x - $this->location->x;
        $y = $this->targetPosition->y - $this->location->y;
        $z = $this->targetPosition->z - $this->location->z;

        if ($this->isFlying() || $this->isSwimming()) {
            if ($this->isCollidedHorizontally) {
                $this->targetPosition = null;
                $this->motion->x = 0;
                $this->motion->z = 0;
                return;
            }
            $distanceSq = $x * $x + $y * $y + $z * $z;
            if ($distanceSq < 1.5) {
                $this->targetPosition = null;
                $this->motion->x = 0;
                $this->motion->y = 0;
                $this->motion->z = 0;
                return;
            }

            $speed = $this->getMovementSpeed();
            $distance = sqrt($distanceSq);
            $this->motion->x = ($x / $distance) * $speed;
            $this->motion->y = ($y / $distance) * $speed;
            $this->motion->z = ($z / $distance) * $speed;

            $yaw = rad2deg(atan2($z, $x)) - 90;
            $pitch = -rad2deg(atan2($y, sqrt($x * $x + $z * $z)));
            $this->setRotation($yaw, $pitch);
            return;
        }

        $distanceSq = $x * $x + $z * $z;
        if ($distanceSq < 1.0 || $distanceSq > 2500) {
            $this->targetPosition = null;
            $this->motion->x = 0;
            $this->motion->z = 0;
            return;
        }

        $angle = atan2($z, $x);
        $speed = $this->getMovementSpeed();

        $this->motion->x = cos($angle) * $speed;
        $this->motion->z = sin($angle) * $speed;

        $this->setRotation(rad2deg($angle) - 90, 0);

        if ($this->isCollidedHorizontally && $this->onGround) {
            $direction = $this->getDirectionVector();
            $horizontalDir = new Vector3($direction->x, 0, $direction->z);
            if ($horizontalDir->lengthSquared() > 0) {
                $horizontalDir = $horizontalDir->normalize();
            }
            $width = $this->size !== null ? $this->size->getWidth() : 0.6;
            $offsetDist = ($width / 2.0) + 0.35;
            $frontPos = new Vector3($this->location->x + $horizontalDir->x * $offsetDist, $this->location->y + 0.5, $this->location->z + $horizontalDir->z * $offsetDist);
            $frontBlock = $this->getWorld()->getBlock($frontPos);
            $frontBlockUpper = $this->getWorld()->getBlock($frontPos->add(0, 1, 0));
            if ($frontBlock->isSolid() || $frontBlockUpper->isSolid()) {
                $this->motion->y = $this->getJumpVelocity();
                $this->motion->x *= 1.5; 
                $this->motion->z *= 1.5;
            }
        }
    }

    public function getJumpVelocity(): float
    {
        return 0.52;
    }

    public function getMovementSpeed(): float {
        return 0.23;
    }

    public function getXpDropAmount(): int
    {
        return 0;
    }

    protected function syncNetworkData(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection $properties) : void
    {
        parent::syncNetworkData($properties);

        $holderId = -1;
        $entityId = $this->getId();
        if (isset(\pocketmine\vanilla\listener\LeashListener::$leashedEntities[$entityId])) {
            $holderId = \pocketmine\vanilla\listener\LeashListener::$leashedEntities[$entityId];
        } elseif (isset(\pocketmine\vanilla\listener\LeashListener::$fenceLeashedEntities[$entityId])) {
            $holderId = \pocketmine\vanilla\listener\LeashListener::$fenceLeashedEntities[$entityId]['knotId'];
        } elseif (isset(\pocketmine\vanilla\listener\LeashListener::$entityLeashedEntities[$entityId])) {
            $holderId = \pocketmine\vanilla\listener\LeashListener::$entityLeashedEntities[$entityId];
        }

        if ($holderId !== -1) {
            $properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::LEASHED, true);
            $properties->setLong(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties::LEAD_HOLDER_EID, $holderId);
        } else {
            $properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::LEASHED, false);
            $properties->setLong(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties::LEAD_HOLDER_EID, -1);
        }
    }
}

