<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\structure;

use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\populator\Populator;

/**
 * Bastion Remnant structure generator.
 *
 * Generates large structures made of Blackstone in the Nether.
 * Bastions are the primary source of Piglin Brutes and gold loot.
 *
 * Spawns less frequently than Nether Fortresses (~every 600-900 blocks).
 */
class BastionRemnant implements Populator{

	private const BASTION_SPACING = 40;
	private const BASTION_CHANCE = 30; // 30% of eligible regions get a bastion

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$regionX = intdiv($chunkX, self::BASTION_SPACING);
		$regionZ = intdiv($chunkZ, self::BASTION_SPACING);

		$hash = $this->regionHash($regionX, $regionZ);
		if($hash % 100 > self::BASTION_CHANCE){
			return;
		}

		$offsetX = ($hash >> 1) % 10;
		$offsetZ = ($hash >> 5) % 10;

		$bastionChunkX = $regionX * self::BASTION_SPACING + $offsetX;
		$bastionChunkZ = $regionZ * self::BASTION_SPACING + $offsetZ;

		if($chunkX !== $bastionChunkX || $chunkZ !== $bastionChunkZ){
			return;
		}

		$this->generateBastion($world, $chunkX, $chunkZ);
	}

	private function generateBastion(ChunkManager $world, int $centerChunkX, int $centerChunkZ) : void{
		$blackstone = VanillaBlocks::AIR(); // fallback if blackstone doesn't exist
		$polishedBlackstone = VanillaBlocks::AIR();
		$chiseledPolishedBlackstone = VanillaBlocks::AIR();
		$goldBlock = VanillaBlocks::GOLD();
		$air = VanillaBlocks::AIR();
		$lava = VanillaBlocks::LAVA();

		// Try to use blackstone variants — if not available, use nether bricks
		try{
			$blackstone = VanillaBlocks::BLACKSTONE();
			$polishedBlackstone = VanillaBlocks::POLISHED_BLACKSTONE();
		}catch(\Error $e){
			$blackstone = VanillaBlocks::NETHER_BRICKS();
			$polishedBlackstone = VanillaBlocks::NETHER_BRICKS();
		}

		$baseY = 50;
		$centerX = $centerChunkX * 16 + 8;
		$centerZ = $centerChunkZ * 16 + 8;

		// Generate a 16x16 platform at baseY
		for($x = 0; $x < 16; $x++){
			for($z = 0; $z < 16; $z++){
				$world->setBlockAt($centerX + $x, $baseY, $centerZ + $z, $polishedBlackstone);
			}
		}

		// Generate 4 corner pillars (4 blocks wide, 8 blocks tall)
		$pillarPositions = [
			[$centerX + 1, $centerZ + 1],
			[$centerX + 12, $centerZ + 1],
			[$centerX + 1, $centerZ + 12],
			[$centerX + 12, $centerZ + 12],
		];

		foreach($pillarPositions as [$px, $pz]){
			for($dx = 0; $dx < 3; $dx++){
				for($dz = 0; $dz < 3; $dz++){
					for($y = $baseY + 1; $y <= $baseY + 8; $y++){
						$world->setBlockAt($px + $dx, $y, $pz + $dz, $blackstone);
					}
				}
			}
		}

		// Connect pillars with walls
		// North and south walls
		for($x = 4; $x <= 11; $x++){
			for($y = $baseY + 1; $y <= $baseY + 6; $y++){
				$world->setBlockAt($centerX + $x, $y, $centerZ + 1, $blackstone);
				$world->setBlockAt($centerX + $x, $y, $centerZ + 14, $blackstone);
			}
		}
		// East and west walls
		for($z = 4; $z <= 11; $z++){
			for($y = $baseY + 1; $y <= $baseY + 6; $y++){
				$world->setBlockAt($centerX + 1, $y, $centerZ + $z, $blackstone);
				$world->setBlockAt($centerX + 14, $y, $centerZ + $z, $blackstone);
			}
		}

		// Hollow interior
		for($x = 5; $x <= 10; $x++){
			for($z = 5; $z <= 10; $z++){
				for($y = $baseY + 1; $y <= $baseY + 5; $y++){
					$world->setBlockAt($centerX + $x, $y, $centerZ + $z, $air);
				}
			}
		}

		// Place gold blocks as loot (center of the structure)
		$world->setBlockAt($centerX + 7, $baseY + 1, $centerZ + 7, $goldBlock);
		$world->setBlockAt($centerX + 8, $baseY + 1, $centerZ + 7, $goldBlock);
		$world->setBlockAt($centerX + 7, $baseY + 1, $centerZ + 8, $goldBlock);
		$world->setBlockAt($centerX + 8, $baseY + 1, $centerZ + 8, $goldBlock);

		// Add an entrance on the south side
		for($y = $baseY + 1; $y <= $baseY + 3; $y++){
			$world->setBlockAt($centerX + 7, $y, $centerZ + 14, $air);
			$world->setBlockAt($centerX + 8, $y, $centerZ + 14, $air);
		}

		// Add lava moat around the bastion (1 block gap)
		for($x = 0; $x < 16; $x++){
			$world->setBlockAt($centerX + $x, $baseY - 1, $centerZ, $lava);
			$world->setBlockAt($centerX + $x, $baseY - 1, $centerZ + 15, $lava);
		}
		for($z = 0; $z < 16; $z++){
			$world->setBlockAt($centerX, $baseY - 1, $centerZ + $z, $lava);
			$world->setBlockAt($centerX + 15, $baseY - 1, $centerZ + $z, $lava);
		}
	}

	private function regionHash(int $regionX, int $regionZ) : int{
		$hash = ($regionX * 341873128) ^ ($regionZ * 132897987);
		return $hash & 0x7FFFFFFF;
	}
}
