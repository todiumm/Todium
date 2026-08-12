<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\the_end;

use pocketmine\entity\vanilla\Monster;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

class Enderman extends Monster {

    private int $heldBlockStateId = 0;

    public static function getNetworkTypeId(): string {
        return "minecraft:enderman";
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->setMaxHealth(40);
        $this->setHealth(40);

        $this->heldBlockStateId = $nbt->getInt("HeldBlockStateId", 0);
        if ($this->heldBlockStateId !== 0) {
            $networkId = \pocketmine\network\mcpe\convert\TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($this->heldBlockStateId);
            $this->getNetworkProperties()->setShort(EntityMetadataProperties::ENDERMAN_HELD_ITEM_ID, $networkId);
        }
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setInt("HeldBlockStateId", $this->heldBlockStateId);
        return $nbt;
    }

    public function onUpdate(int $currentTick): bool {
        $hasUpdate = parent::onUpdate($currentTick);
        if (!$this->isAlive() || $this->isClosed()) {
            return $hasUpdate;
        }

        $block = $this->getWorld()->getBlock($this->location);
        if ($block instanceof \pocketmine\block\Water) {
            $ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_CONTACT, 1.0);
            $this->attack($ev);
            $this->teleportRandomly();
            $hasUpdate = true;
        }

        $teleportInterval = $this->targetPosition !== null ? mt_rand(80, 160) : mt_rand(200, 400);
        if ($currentTick % $teleportInterval === 0) {
            $this->teleportRandomly();
            $hasUpdate = true;
        }

        if ($this->targetPosition === null) {
            if ($this->heldBlockStateId === 0) {
                if (mt_rand(1, 100) <= 2 && $currentTick % 100 === 0) {
                    $this->tryStealBlock();
                }
            } else {
                if (mt_rand(1, 100) <= 2 && $currentTick % 100 === 0) {
                    $this->tryPlaceBlock();
                }
            }
        }

        return $hasUpdate;
    }

    public function attack(EntityDamageEvent $source): void {
        if ($source->getCause() === EntityDamageEvent::CAUSE_PROJECTILE) {
            $source->cancel();
            $this->teleportRandomly();
            return;
        }

        parent::attack($source);

        if ($this->isAlive() && mt_rand(1, 100) <= 50) {
            $this->teleportRandomly();
        }
    }

    public function teleportRandomly(): bool {
        return $this->teleportNear($this->getLocation(), 15, 8);
    }

    public function teleportNear(Vector3 $target, int $horizontalRange = 5, int $verticalRange = 3): bool {
        $world = $this->getWorld();

        for ($i = 0; $i < 16; $i++) {
            $randX = floor($target->x) + mt_rand(-$horizontalRange, $horizontalRange) + 0.5;
            $randZ = floor($target->z) + mt_rand(-$horizontalRange, $horizontalRange) + 0.5;
            $randY = floor($target->y) + mt_rand(-$verticalRange, $verticalRange);

            $pos = new Vector3($randX, $randY, $randZ);
            $block = $world->getBlock($pos);
            $blockBelow = $world->getBlock($pos->subtract(0, 1, 0));
            $blockAbove = $world->getBlock($pos->add(0, 1, 0));
            $blockAbove2 = $world->getBlock($pos->add(0, 2, 0));

            if ($blockBelow->isSolid() && !$block->isSolid() && !$blockAbove->isSolid() && !$blockAbove2->isSolid() && !($block instanceof \pocketmine\block\Water)) {
                $world->addSound($this->location, new \pocketmine\world\sound\EndermanTeleportSound());
                $world->addParticle($this->location, new \pocketmine\world\particle\EndermanTeleportParticle());

                $this->teleport($pos);

                $world->addSound($pos, new \pocketmine\world\sound\EndermanTeleportSound());
                $world->addParticle($pos, new \pocketmine\world\particle\EndermanTeleportParticle());

                $this->targetPosition = null;
                return true;
            }
        }
        return false;
    }

    protected function calculateAI(): void {
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

        if ($nearest !== null) {
            $this->targetEntity = $nearest;
            $playerPos = $nearest->getLocation();
            $blockAbove = $this->getWorld()->getBlock($playerPos->add(0, 2, 0));

            if ($blockAbove->isSolid()) {
                $this->targetPosition = clone $nearest->getLocation();
            } else {
                $this->targetPosition = clone $nearest->getLocation();

                if ($minDist < 3.0 && $this->attackDelay <= 0) {
                    $ev = new EntityDamageByEntityEvent($this, $nearest, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getAttackDamage());
                    $nearest->attack($ev);
                    $this->attackDelay = 20;
                } elseif ($minDist > 25.0 && mt_rand(1, 100) <= 20) {
                    $this->teleportNear($playerPos, 3, 2);
                }
            }
        } else {
            $this->targetEntity = null;
            if ($this->targetPosition === null || mt_rand(1, 100) <= 10) {
                $randX = mt_rand(-12, 12);
                $randZ = mt_rand(-12, 12);
                $randY = mt_rand(-2, 2);
                $newPos = $this->location->add($randX, $randY, $randZ);

                $chunkX = (int)floor($newPos->x) >> 4;
                $chunkZ = (int)floor($newPos->z) >> 4;
                if ($this->getWorld()->isChunkGenerated($chunkX, $chunkZ)) {
                    $highestBlockY = $this->getWorld()->getHighestBlockAt((int)floor($newPos->x), (int)floor($newPos->z));
                    if ($highestBlockY !== null) {
                        $targetY = max($highestBlockY + 1.0, min($highestBlockY + 8, $newPos->y));
                        $newPos->y = $targetY;
                    }
                    if (!$this->getWorld()->getBlock($newPos)->isSolid()) {
                        $this->targetPosition = $newPos;
                    }
                }
            }
        }
    }

    private function tryStealBlock(): void {
        $world = $this->getWorld();
        $pos = $this->location->floor();

        for ($x = -1; $x <= 1; $x++) {
            for ($y = -1; $y <= 2; $y++) {
                for ($z = -1; $z <= 1; $z++) {
                    $blockPos = $pos->add($x, $y, $z);
                    $block = $world->getBlock($blockPos);
                    $name = strtolower($block->getName());

                    $isStealable = false;
                    foreach (["grass", "dirt", "sand", "gravel", "clay", "pumpkin", "melon", "tnt", "flower", "dandelion", "poppy", "orchid", "allium", "tulip", "daisy", "rose"] as $term) {
                        if (str_contains($name, $term)) {
                            $isStealable = true;
                            break;
                        }
                    }

                    if ($isStealable) {
                        $this->heldBlockStateId = $block->getStateId();
                        $world->setBlock($blockPos, \pocketmine\block\VanillaBlocks::AIR());

                        $networkId = \pocketmine\network\mcpe\convert\TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($this->heldBlockStateId);
                        $this->getNetworkProperties()->setShort(EntityMetadataProperties::ENDERMAN_HELD_ITEM_ID, $networkId);
                        return;
                    }
                }
            }
        }
    }

    private function tryPlaceBlock(): void {
        if ($this->heldBlockStateId === 0) return;

        $world = $this->getWorld();
        $pos = $this->location->floor();

        for ($x = -1; $x <= 1; $x++) {
            for ($z = -1; $z <= 1; $z++) {
                for ($y = -1; $y <= 1; $y++) {
                    $blockPos = $pos->add($x, $y, $z);
                    $block = $world->getBlock($blockPos);
                    $blockAbove = $world->getBlock($blockPos->add(0, 1, 0));

                    if ($block->isSolid() && $blockAbove->getTypeId() === \pocketmine\block\BlockTypeIds::AIR) {
                        $placePos = $blockPos->add(0, 1, 0);
                        $heldBlock = \pocketmine\block\RuntimeBlockStateRegistry::getInstance()->fromStateId($this->heldBlockStateId);
                        $world->setBlock($placePos, $heldBlock);

                        $this->heldBlockStateId = 0;
                        $this->getNetworkProperties()->setShort(EntityMetadataProperties::ENDERMAN_HELD_ITEM_ID, 0);
                        return;
                    }
                }
            }
        }
    }

    public function getDrops(): array {
        $drops = parent::getDrops();
        if ($this->heldBlockStateId !== 0) {
            $block = \pocketmine\block\RuntimeBlockStateRegistry::getInstance()->fromStateId($this->heldBlockStateId);
            $drops[] = $block->asItem();
        }
        return $drops;
    }
}