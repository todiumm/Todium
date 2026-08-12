<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\structure;

use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\populator\Populator;
use function mt_rand;

/**
 * Ruined Portal structure generator.
 *
 * Generates small ruined portal structures in the Nether — broken obsidian
 * frames with crying obsidian, sometimes partially submerged in lava.
 *
 * These are the most common Nether structure (~every 100-200 blocks) and
 * serve as navigation landmarks.
 */
class RuinedPortal implements Populator{

	private const PORTAL_SPACING = 12;
	private const PORTAL_CHANCE = 25; // 25% of eligible regions

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$regionX = intdiv($chunkX, self::PORTAL_SPACING);
		$regionZ = intdiv($chunkZ, self::PORTAL_SPACING);

		$hash = $this->regionHash($regionX, $regionZ);
		if($hash % 100 > self::PORTAL_CHANCE){
			return;
		}

		$offsetX = ($hash >> 1) % self::PORTAL_SPACING;
		$offsetZ = ($hash >> 5) % self::PORTAL_SPACING;

		$portalChunkX = $regionX * self::PORTAL_SPACING + $offsetX;
		$portalChunkZ = $regionZ * self::PORTAL_SPACING + $offsetZ;

		if($chunkX !== $portalChunkX || $chunkZ !== $portalChunkZ){
			return;
		}

		$this->generateRuinedPortal($world, $chunkX, $chunkZ);
	}

	private function generateRuinedPortal(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$obsidian = VanillaBlocks::OBSIDIAN();
		$cryingObsidian = VanillaBlocks::AIR();
		$netherrack = VanillaBlocks::NETHERRACK();
		$lava = VanillaBlocks::LAVA();
		$air = VanillaBlocks::AIR();

		// Try crying obsidian — may not exist in all versions
		try{
			$cryingObsidian = VanillaBlocks::CRYING_OBSIDIAN();
		}catch(\Error $e){
			$cryingObsidian = $obsidian; // fallback
		}

		$baseX = $chunkX * 16 + mt_rand(4, 10);
		$baseZ = $chunkZ * 16 + mt_rand(4, 10);
		$baseY = mt_rand(40, 70);

		// Generate a ruined portal frame (4 wide x 5 tall, with some blocks missing)
		// The frame is "broken" — random blocks are removed or replaced with crying obsidian
		$frame = [
			// Bottom row
			[0, 0, 0], [1, 0, 0], [2, 0, 0], [3, 0, 0],
			// Left column
			[0, 1, 0], [0, 2, 0], [0, 3, 0],
			// Right column
			[3, 1, 0], [3, 2, 0], [3, 3, 0],
			// Top row
			[0, 4, 0], [1, 4, 0], [2, 4, 0], [3, 4, 0],
		];

		foreach($frame as [$dx, $dy, $dz]){
			$x = $baseX + $dx;
			$y = $baseY + $dy;
			$z = $baseZ + $dz;

			// 20% chance to skip the block (ruined look)
			if(mt_rand(1, 5) === 1){
				continue;
			}

			// 15% chance to use crying obsidian instead
			if(mt_rand(1, 7) <= 2){
				$world->setBlockAt($x, $y, $z, $cryingObsidian);
			}else{
				$world->setBlockAt($x, $y, $z, $obsidian);
			}
		}

		// Sometimes add lava in the portal interior (if the frame is mostly intact)
		if(mt_rand(1, 2) === 1){
			for($dy = 1; $dy <= 3; $dy++){
				$world->setBlockAt($baseX + 1, $baseY + $dy, $baseZ, $lava);
				$world->setBlockAt($baseX + 2, $baseY + $dy, $baseZ, $lava);
			}
		}

		// Add netherrack rubble around the portal
		for($dx = -2; $dx <= 5; $dx++){
			for($dz = -2; $dz <= 2; $dz++){
				if(mt_rand(1, 4) === 1){
					$world->setBlockAt($baseX + $dx, $baseY - 1, $baseZ + $dz, $netherrack);
				}
			}
		}
	}

	private function regionHash(int $regionX, int $regionZ) : int{
		$hash = ($regionX * 947183) ^ ($regionZ * 617283);
		return $hash & 0x7FFFFFFF;
	}
}
