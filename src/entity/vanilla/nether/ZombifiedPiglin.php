<?php

declare(strict_types=1);

namespace pocketmine\entity\vanilla\nether;

use pocketmine\entity\vanilla\Monster;

class ZombifiedPiglin extends Monster {
    public static function getNetworkTypeId(): string {
        return "minecraft:zombie_pigman";
    }

    public function attack(\pocketmine\event\entity\EntityDamageEvent $source): void {
        parent::attack($source);
        if ($source instanceof \pocketmine\event\entity\EntityDamageByEntityEvent) {
            $damager = $source->getDamager();
            if ($damager instanceof \pocketmine\player\Player) {
                $this->targetPosition = clone $damager->getLocation();
                foreach ($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(16, 16, 16), $this) as $entity) {
                    if ($entity instanceof self) {
                        $entity->targetPosition = clone $damager->getLocation();
                    }
                }
            }
        }
    }
}
