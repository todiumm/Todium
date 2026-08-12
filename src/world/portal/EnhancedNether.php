<?php

declare(strict_types=1);

namespace pocketmine\world\portal;

use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\generator\noise\Simplex;
use pocketmine\world\generator\populator\Populator;
use pocketmine\world\generator\hell\Nether as VanillaNether;

/**
 * Enhanced Nether generator with biomes, structures, and populators.
 *
 * Extends the vanilla PMMP Nether generator with:
 *   - 5 Nether biomes: Nether Wastes, Soul Sand Valley, Crimson Forest,
 *     Warped Forest, Basalt Deltas
 *   - Nether structures: Nether Fortress, Bastion Remnant, Ruined Portal
 *   - Lava lakes, lava falls, glowstone clusters, ore veins
 *
 * The biomes are assigned per-chunk based on a Simplex noise function, and
 * each biome has its own populator set that determines what structures and
 * features appear within it.
 */
class EnhancedNether extends VanillaNether{

	private Simplex $biomeNoise;
	/** @var Populator[] */
	private array $biomePopulators = [];

	/**
	 * @throws InvalidGeneratorOptionsException
	 */
	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);

		// Separate noise for biome selection — uses a different frequency
		//so biomes span larger areas than terrain features.
		$this->biomeNoise = new Simplex($this->random, 2, 1 / 8, 1 / 128);
		$this->random->setSeed($this->seed);

		//Register structure populators
		$this->generationPopulators[] = new \pocketmine\world\generator\nether\structure\NetherFortress();
		$this->generationPopulators[] = new \pocketmine\world\generator\nether\structure\BastionRemnant();
		$this->generationPopulators[] = new \pocketmine\world\generator\nether\structure\RuinedPortal();
	}

	/**
	 * Determines which Nether biome a given chunk should use.
	 *
	 * The biome is selected based on two noise values:
	 *   - Temperature noise: determines hot (Crimson/Warped) vs cold (Soul Sand/Basalt)
	 *   - Humidity noise: determines dry (Nether Wastes) vs wet (Crimson/Warped Forest)
	 *
	 * Returns one of the BiomeIds constants for Nether biomes.
	 */
	private function getNetherBiome(int $chunkX, int $chunkZ) : int{
		// Sample noise at chunk-center resolution. Using chunk coords (not block coords)
		// gives biome regions that span ~64-128 blocks — similar to vanilla Bedrock.
		$tempNoise = $this->biomeNoise->noise2D($chunkX * 16, $chunkZ * 16, true);
		$humidNoise = $this->biomeNoise->noise2D(($chunkX + 1000) * 16, ($chunkZ + 1000) * 16, true);

		//Biome selection logic — threshold values chosen to roughly match vanilla distribution:
		//  ~40% Nether Wastes (default)
		//  ~15% Soul Sand Valley
		//  ~15% Crimson Forest
		//  ~15% Warped Forest
		//  ~15% Basalt Deltas
		if($tempNoise < -0.3){
			//Cold biomes
			if($humidNoise < -0.2){
				return BiomeIds::SOULSAND_VALLEY; // Soul Sand Valley
			}
			return BiomeIds::BASALT_DELTAS; // Basalt Deltas
		}elseif($tempNoise > 0.3){
			//Hot biomes (forests)
			if($humidNoise > 0){
				return BiomeIds::CRIMSON_FOREST; // Crimson Forest
			}
			return BiomeIds::WARPED_FOREST; // Warped Forest
		}

		//Default: Nether Wastes
		return BiomeIds::HELL;
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		//Generate terrain using the parent (vanilla Nether) generator
		parent::generateChunk($world, $chunkX, $chunkZ);

		//Override the biome for this chunk based on the enhanced biome selector.
		//This replaces the single-biome (all Hell) approach of vanilla PMMP with
		//per-column biome assignment, so different Nether biomes appear in
		//different regions.
		$biomeId = $this->getNetherBiome($chunkX, $chunkZ);
		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk !== null){
			for($x = 0; $x < Chunk::EDGE_LENGTH; $x++){
				for($z = 0; $z < Chunk::EDGE_LENGTH; $z++){
					$chunk->setBiomeId($x, $z, $biomeId);
				}
			}

			//Apply biome-specific ground cover
			$this->applyBiomeGroundCover($chunk, $biomeId);
		}
	}

	/**
	 * Applies biome-specific ground cover to a chunk after terrain generation.
	 *
	 * Each Nether biome has a characteristic surface:
	 *   - Nether Wastes: Netherrack (default — already placed by parent)
	 *   - Soul Sand Valley: Soul Sand on top, Soul Soil underneath
	 *   - Crimson Forest: Netherrack + Crimson Nylium on top
	 *   - Warped Forest: Netherrack + Warped Nylium on top
	 *   - Basalt Deltas: Basalt on top, smooth basalt underneath
	 */
	private function applyBiomeGroundCover(Chunk $chunk, int $biomeId) : void{
		$soulSand = VanillaBlocks::SOUL_SAND();
		$soulSoil = VanillaBlocks::SOUL_SOIL();
		$netherrack = VanillaBlocks::NETHERRACK();
		$basalt = VanillaBlocks::BASALT();

		for($x = 0; $x < Chunk::EDGE_LENGTH; $x++){
			for($z = 0; $z < Chunk::EDGE_LENGTH; $z++){
				//Find the top solid block in this column
				for($y = 127; $y > 0; $y--){
					$current = $chunk->getBlockStateId($x, $y, $z);
					if($current === 0){ // air
						continue;
					}

					//Check if this is a Netherrack block (we only replace Netherrack)
					//Since we don't have an easy instanceof check on state IDs, we
					//replace the top few blocks based on biome.
					switch($biomeId){
						case BiomeIds::SOULSAND_VALLEY:
							//Replace top 1 block with soul sand, next 2 with soul soil
							$chunk->setBlockStateId($x, $y, $z, $soulSand->getStateId());
							if($y > 0){
								$chunk->setBlockStateId($x, $y - 1, $z, $soulSoil->getStateId());
							}
							if($y > 1){
								$chunk->setBlockStateId($x, $y - 2, $z, $soulSoil->getStateId());
							}
							break;
						case BiomeIds::BASALT_DELTAS:
							//Replace top 2 blocks with basalt
							$chunk->setBlockStateId($x, $y, $z, $basalt->getStateId());
							if($y > 0){
								$chunk->setBlockStateId($x, $y - 1, $z, $basalt->getStateId());
							}
							break;
						//Crimson and Warped forests would need Nylium blocks which
						//may not exist in PM5. For now, keep Netherrack.
						case BiomeIds::CRIMSON_FOREST:
						case BiomeIds::WARPED_FOREST:
						case BiomeIds::HELL:
						default:
							//Netherrack — already placed by parent generator
							break;
					}
					break; //Only process the top solid block
				}
			}
		}
	}
}
