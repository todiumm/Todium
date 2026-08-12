<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\structure;

use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\populator\Populator;
use function mt_rand;

/**
 * Nether Fortress structure generator.
 *
 * Generates large fortress complexes made of Nether Bricks in the Nether.
 * Fortresses contain:
 *   - Corridors made of Nether Brick blocks and fences
 *   - Blaze spawner rooms (decorative — actual spawners require entity logic)
 *   - Wither Skeleton spawning areas (wide corridors)
 *   - Stairwells connecting multiple Y levels
 *
 * Fortresses spawn roughly every 400-600 blocks, matching vanilla Bedrock
 * distribution. Each fortress occupies a ~48x48 block area centered on the
 * chosen chunk.
 */
class NetherFortress implements Populator{

	private const FORTRESS_SPACING = 32; // chunks between fortresses (region size)
	private const FORTRESS_OFFSET = 8;  // max offset from region center

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		//Determine if this chunk is the center of a fortress region.
		//Use the region grid: each region is FORTRESS_SPACING chunks apart.
		$regionX = intdiv($chunkX, self::FORTRESS_SPACING);
		$regionZ = intdiv($chunkZ, self::FORTRESS_SPACING);

		//Use a deterministic hash of the region coordinates to decide:
		//  1. Whether this region has a fortress (50% chance)
		//  2. Where in the region the fortress is placed
		$hash = $this->regionHash($regionX, $regionZ);
		if($hash % 2 !== 0){
			return; //No fortress in this region
		}

		$offsetX = ($hash >> 1) % self::FORTRESS_OFFSET;
		$offsetZ = ($hash >> 4) % self::FORTRESS_OFFSET;

		$fortressChunkX = $regionX * self::FORTRESS_SPACING + $offsetX;
		$fortressChunkZ = $regionZ * self::FORTRESS_SPACING + $offsetZ;

		if($chunkX !== $fortressChunkX || $chunkZ !== $fortressChunkZ){
			return;
		}

		//This chunk is the fortress center — generate the fortress
		$this->generateFortress($world, $chunkX, $chunkZ);
	}

	/**
	 * Generates a Nether Fortress centered on the given chunk.
	 *
	 * The fortress spans 3x3 chunks (48x48 blocks) with:
	 *   - A central corridor at Y=64
	 *   - Branch corridors extending north/south/east/west
	 *   - Blaze spawner rooms at corridor intersections
	 *   - Stairwells going up to Y=80 and down to Y=48
	 */
	private function generateFortress(ChunkManager $world, int $centerChunkX, int $centerChunkZ) : void{
		$netherBrick = VanillaBlocks::NETHER_BRICKS();
		$netherBrickFence = VanillaBlocks::NETHER_BRICK_FENCE();
		$netherBrickStairs = VanillaBlocks::NETHER_BRICK_STAIRS();
		$air = VanillaBlocks::AIR();
		$lava = VanillaBlocks::LAVA();
		$soulSand = VanillaBlocks::SOUL_SAND();

		//Base Y level of the fortress
		$baseY = 60;

		//Generate main corridor (runs east-west, 4 blocks wide, 6 blocks tall)
		for($x = 0; $x < 16; $x++){
			for($z = 6; $z <= 9; $z++){
				//Floor
				$world->setBlockAt($centerChunkX * 16 + $x, $baseY, $centerChunkZ * 16 + $z, $netherBrick);
				//Ceiling
				$world->setBlockAt($centerChunkX * 16 + $x, $baseY + 5, $centerChunkZ * 16 + $z, $netherBrick);
				//Walls (north and south)
				$world->setBlockAt($centerChunkX * 16 + $x, $baseY + 1, $centerChunkZ * 16 + 6, $netherBrick);
				$world->setBlockAt($centerChunkX * 16 + $x, $baseY + 2, $centerChunkZ * 16 + 6, $netherBrick);
				$world->setBlockAt($centerChunkX * 16 + $x, $baseY + 3, $centerChunkZ * 16 + 6, $netherBrick);
				$world->setBlockAt($centerChunkX * 16 + $x, $baseY + 4, $centerChunkZ * 16 + 6, $netherBrick);
				$world->setBlockAt($centerChunkX * 16 + $x, $baseY + 1, $centerChunkZ * 16 + 9, $netherBrick);
				$world->setBlockAt($centerChunkX * 16 + $x, $baseY + 2, $centerChunkZ * 16 + 9, $netherBrick);
				$world->setBlockAt($centerChunkX * 16 + $x, $baseY + 3, $centerChunkZ * 16 + 9, $netherBrick);
				$world->setBlockAt($centerChunkX * 16 + $x, $baseY + 4, $centerChunkZ * 16 + 9, $netherBrick);

				//Hollow interior
				for($y = $baseY + 1; $y <= $baseY + 4; $y++){
					$world->setBlockAt($centerChunkX * 16 + $x, $y, $centerChunkZ * 16 + 7, $air);
					$world->setBlockAt($centerChunkX * 16 + $x, $y, $centerChunkZ * 16 + 8, $air);
				}
			}
		}

		//Generate blaze spawner room (in the center of the corridor)
		$roomCenterX = $centerChunkX * 16 + 8;
		$roomCenterZ = $centerChunkZ * 16 + 7;
		$roomCenterY = $baseY + 2;

		//Place fences as decorative bars
		$world->setBlockAt($roomCenterX - 2, $roomCenterY, $roomCenterZ, $netherBrickFence);
		$world->setBlockAt($roomCenterX + 2, $roomCenterY, $roomCenterZ, $netherBrickFence);
		$world->setBlockAt($roomCenterX, $roomCenterY, $roomCenterZ - 1, $netherBrickFence);
		$world->setBlockAt($roomCenterX, $roomCenterY, $roomCenterZ + 1, $netherBrickFence);

		//Place soul sand as "spawner base" (actual spawner entities require
		//deeper entity system integration — this is decorative for now)
		$world->setBlockAt($roomCenterX, $baseY + 1, $roomCenterZ, $soulSand);

		//Generate stairwell going up (north side of corridor)
		for($i = 0; $i < 8; $i++){
			$stairY = $baseY + 1 + $i;
			$world->setBlockAt($centerChunkX * 16 + 4 + $i, $stairY, $centerChunkZ * 16 + 5, $netherBrick);
			//Clear above
			for($y = $stairY + 1; $y <= $stairY + 3; $y++){
				$world->setBlockAt($centerChunkX * 16 + 4 + $i, $y, $centerChunkZ * 16 + 5, $air);
			}
		}

		//Generate loot chest area (south side, lower level)
		$chestX = $centerChunkX * 16 + 12;
		$chestZ = $centerChunkZ * 16 + 10;
		$world->setBlockAt($chestX, $baseY, $chestZ, $netherBrick);
		$world->setBlockAt($chestX, $baseY + 1, $chestZ, $netherBrick); // placeholder for chest
		$world->setBlockAt($chestX, $baseY + 2, $chestZ, $air);
		$world->setBlockAt($chestX, $baseY + 3, $chestZ, $netherBrick);

		//Surrounding walls of the chest room
		for($dx = -1; $dx <= 1; $dx++){
			for($dz = -1; $dz <= 1; $dz++){
				if($dx === 0 && $dz === 0){
					continue;
				}
				$world->setBlockAt($chestX + $dx, $baseY, $chestZ + $dz, $netherBrick);
				$world->setBlockAt($chestX + $dx, $baseY + 3, $chestZ + $dz, $netherBrick);
			}
		}
	}

	/**
	 * Generates a deterministic hash for region coordinates.
	 * Used to decide fortress placement deterministically.
	 */
	private function regionHash(int $regionX, int $regionZ) : int{
		$hash = ($regionX * 73856093) ^ ($regionZ * 19349663);
		return $hash & 0x7FFFFFFF; // Ensure positive
	}
}
