<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\behaviorpacks;

use Ahc\Json\Comment as CommentedJsonDecoder;
use pocketmine\behaviorpacks\json\Manifest;
use pocketmine\behaviorpacks\json\ManifestModuleEntry;
use pocketmine\utils\Utils;
use Ramsey\Uuid\Uuid;
use function assert;
use function fclose;
use function feof;
use function file_exists;
use function filesize;
use function fopen;
use function fread;
use function fseek;
use function gettype;
use function hash_file;
use function implode;
use function in_array;
use function preg_match;
use function strlen;

/**
 * Behavior pack backed by a `.mcpack` / `.zip` archive on disk.
 *
 * The archive must contain a `manifest.json` at its root (or nested one level deep, which
 * is the layout produced by Mojang's `mcpack` exporter). The manifest is parsed at
 * construction time so that any malformed pack is rejected before it can be advertised to
 * a client.
 */
class ZippedBehaviorPack implements BehaviorPack{
	protected string $path;
	protected Manifest $manifest;
	protected ?string $sha256 = null;
	protected bool $hasScripts;

	/** @var resource */
	protected $fileResource;

	/**
	 * @param string $zipPath Path to the behavior pack zip
	 * @throws BehaviorPackException
	 */
	public function __construct(string $zipPath){
		$this->path = $zipPath;

		if(!file_exists($zipPath)){
			throw new BehaviorPackException("File not found");
		}
		$size = filesize($zipPath);
		if($size === false){
			throw new BehaviorPackException("Unable to determine size of file");
		}
		if($size === 0){
			throw new BehaviorPackException("Empty file, probably corrupted");
		}

		$archive = new \ZipArchive();
		if(($openResult = $archive->open($zipPath)) !== true){
			throw new BehaviorPackException("Encountered ZipArchive error code $openResult while trying to open $zipPath");
		}

		if(($manifestData = $archive->getFromName("manifest.json")) === false){
			//Mojang's .mcpack exporter sometimes nests the manifest under a top-level folder
			//(e.g. `my_pack/manifest.json`). Look for the shallowest such path.
			$manifestPath = null;
			$manifestIdx = null;
			for($i = 0; $i < $archive->numFiles; ++$i){
				$name = Utils::assumeNotFalse($archive->getNameIndex($i), "This index should be valid");
				if(
					($manifestPath === null || strlen($name) < strlen($manifestPath)) &&
					preg_match('#.*/manifest.json$#', $name) === 1
				){
					$manifestPath = $name;
					$manifestIdx = $i;
				}
			}
			if($manifestIdx !== null){
				$manifestData = $archive->getFromIndex($manifestIdx);
				assert($manifestData !== false);
			}elseif($archive->locateName("pack_manifest.json") !== false){
				throw new BehaviorPackException("Unsupported old pack format");
			}else{
				throw new BehaviorPackException("manifest.json not found in the archive root");
			}
		}

		$archive->close();

		//maybe comments in the json, use stripped decoder (thanks mojang)
		try{
			$manifest = (new CommentedJsonDecoder())->decode($manifestData);
		}catch(\RuntimeException $e){
			throw new BehaviorPackException("Failed to parse manifest.json: " . $e->getMessage(), 0, $e);
		}
		if(!($manifest instanceof \stdClass)){
			throw new BehaviorPackException("manifest.json should contain a JSON object, not " . gettype($manifest));
		}

		$mapper = new \JsonMapper();
		$mapper->bExceptionOnMissingData = true;
		$mapper->bStrictObjectTypeChecking = true;

		try{
			/** @var Manifest $manifest */
			$manifest = $mapper->map($manifest, new Manifest());
		}catch(\JsonMapper_Exception $e){
			throw new BehaviorPackException("Invalid manifest.json contents: " . $e->getMessage(), 0, $e);
		}
		if(!Uuid::isValid($manifest->header->uuid)){
			throw new BehaviorPackException("Behavior pack has an invalid UUID");
		}
		if(count($manifest->modules) === 0){
			throw new BehaviorPackException("Behavior pack manifest must declare at least one module");
		}

		$this->manifest = $manifest;
		$this->hasScripts = $this->detectScripts($manifest->modules);

		$this->fileResource = fopen($zipPath, "rb");
	}

	/**
	 * Returns true if any module in the manifest is declared as a script module.
	 *
	 * Mojang currently uses `type: "script"` for both JavaScript and TypeScript behavior
	 * modules. We treat any such module as evidence that the pack enables scripts.
	 */
	private function detectScripts(array $modules) : bool{
		foreach($modules as $module){
			if($module instanceof ManifestModuleEntry && in_array($module->type, ["script", "client_script"], true)){
				return true;
			}
		}
		//Also detect the explicit "scripts" capability that some packs declare instead of
		//(or in addition to) a script module.
		if($this->manifest->capabilities !== null){
			foreach($this->manifest->capabilities as $capability){
				if($capability === "script" || $capability === "javascript"){
					return true;
				}
			}
		}
		return false;
	}

	public function __destruct(){
		fclose($this->fileResource);
	}

	public function getPath() : string{
		return $this->path;
	}

	/**
	 * Returns the parsed manifest. Plugins can use this to inspect module types,
	 * dependencies, and capabilities before deciding whether to forward the pack to a
	 * joining player.
	 */
	public function getManifest() : Manifest{
		return $this->manifest;
	}

	public function getPackName() : string{
		return $this->manifest->header->name;
	}

	public function getPackVersion() : string{
		return implode(".", $this->manifest->header->version);
	}

	public function getPackId() : string{
		return $this->manifest->header->uuid;
	}

	public function getPackSize() : int{
		return filesize($this->path);
	}

	public function getSha256(bool $cached = true) : string{
		if($this->sha256 === null || !$cached){
			$this->sha256 = hash_file("sha256", $this->path, true);
		}
		return $this->sha256;
	}

	public function hasScripts() : bool{
		return $this->hasScripts;
	}

	public function getPackChunk(int $start, int $length) : string{
		if($length < 1){
			throw new \InvalidArgumentException("Pack length must be positive");
		}
		fseek($this->fileResource, $start);
		if(feof($this->fileResource)){
			throw new \InvalidArgumentException("Requested a behavior pack chunk with invalid start offset");
		}
		return Utils::assumeNotFalse(fread($this->fileResource, $length), "Already checked that we're not at EOF");
	}
}
