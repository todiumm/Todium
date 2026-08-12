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

use pocketmine\utils\Config;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function copy;
use function count;
use function file_exists;
use function gettype;
use function is_array;
use function is_dir;
use function is_float;
use function is_int;
use function is_string;
use function mkdir;
use function rtrim;
use function strlen;
use function strtolower;
use const DIRECTORY_SEPARATOR;

/**
 * Manages the server-side behavior pack stack.
 *
 * This is the behavior pack analogue of {@see \pocketmine\resourcepacks\ResourcePackManager}.
 * It loads packs from `behavior_packs/` next to the server data path, validates their
 * manifests, and exposes the stack to the network layer so that joining clients can be
 * told to download and apply them.
 *
 * The list of packs to load is configured via `behavior_packs/behavior_packs.yml`:
 *
 * ```yaml
 * force_behavior_packs: false
 * behavior_stack:
 *   - my_addon.mcpack
 *   - vanilla_behaviors.zip
 * ```
 *
 * Packs are applied by the client from bottom to top, with later entries overriding
 * earlier ones (same precedence rule as resource packs).
 */
class BehaviorPackManager{
        private string $path;
        private bool $serverForceBehaviorPacks = false;

        /**
         * @var BehaviorPack[]
         * @phpstan-var list<BehaviorPack>
         */
        private array $behaviorPacks = [];

        /**
         * @var BehaviorPack[]
         * @phpstan-var array<string, BehaviorPack>
         */
        private array $uuidList = [];

        /**
         * @var string[]
         * @phpstan-var array<string, string>
         */
        private array $encryptionKeys = [];

        /**
         * @param string $path Path to behavior-packs directory.
         */
        public function __construct(string $path, \Logger $logger){
                $this->path = $path;

                if(!file_exists($this->path)){
                        $logger->debug("Behavior packs path $path does not exist, creating directory");
                        mkdir($this->path);
                }elseif(!is_dir($this->path)){
                        throw new \InvalidArgumentException("Behavior packs path $path exists and is not a directory");
                }

                $behaviorPacksYml = Path::join($this->path, "behavior_packs.yml");
                if(!file_exists($behaviorPacksYml)){
                        copy(Path::join(\pocketmine\RESOURCE_PATH, "behavior_packs.yml"), $behaviorPacksYml);
                }

                $behaviorPacksConfig = new Config($behaviorPacksYml, Config::YAML, []);

                $this->serverForceBehaviorPacks = (bool) $behaviorPacksConfig->get("force_behavior_packs", false);

                $logger->info("Loading behavior packs...");

                $behaviorStack = $behaviorPacksConfig->get("behavior_stack", []);
                if(!is_array($behaviorStack)){
                        throw new \InvalidArgumentException("\"behavior_stack\" key should contain a list of pack names");
                }

                foreach(Utils::promoteKeys($behaviorStack) as $pos => $pack){
                        if(!is_string($pack) && !is_int($pack) && !is_float($pack)){
                                $logger->critical("Found invalid entry in behavior pack list at offset $pos of type " . gettype($pack));
                                continue;
                        }
                        $pack = (string) $pack;
                        try{
                                $newPack = $this->loadPackFromPath(Path::join($this->path, $pack));

                                $index = strtolower($newPack->getPackId());
                                if(!Uuid::isValid($index)){
                                        throw new BehaviorPackException("Invalid UUID ($index)");
                                }
                                $this->uuidList[$index] = $newPack;
                                $this->behaviorPacks[] = $newPack;

                                $keyPath = Path::join($this->path, $pack . ".key");
                                if(file_exists($keyPath)){
                                        try{
                                                $key = Filesystem::fileGetContents($keyPath);
                                        }catch(\RuntimeException $e){
                                                throw new BehaviorPackException("Could not read encryption key file: " . $e->getMessage(), 0, $e);
                                        }
                                        $key = rtrim($key, "\r\n");
                                        if(strlen($key) !== 32){
                                                throw new BehaviorPackException("Invalid encryption key length, must be exactly 32 bytes");
                                        }
                                        $this->encryptionKeys[$index] = $key;
                                }
                        }catch(BehaviorPackException $e){
                                $logger->critical("Could not load behavior pack \"$pack\": " . $e->getMessage());
                        }
                }

                $logger->debug("Successfully loaded " . count($this->behaviorPacks) . " behavior packs");
        }

        private function loadPackFromPath(string $packPath) : BehaviorPack{
                if(!file_exists($packPath)){
                        throw new BehaviorPackException("File or directory not found");
                }
                if(is_dir($packPath)){
                        throw new BehaviorPackException("Directory behavior packs are unsupported");
                }

                //Detect the type of behavior pack.
                $info = new \SplFileInfo($packPath);
                switch($info->getExtension()){
                        case "zip":
                        case "mcpack":
                                return new ZippedBehaviorPack($packPath);
                }

                throw new BehaviorPackException("Format not recognized");
        }

        /**
         * Returns the directory which behavior packs are loaded from.
         */
        public function getPath() : string{
                return $this->path . DIRECTORY_SEPARATOR;
        }

        /**
         * Returns whether players must accept behavior packs in order to join.
         */
        public function behaviorPacksRequired() : bool{
                return $this->serverForceBehaviorPacks;
        }

        /**
         * Sets whether players must accept behavior packs in order to join.
         */
        public function setBehaviorPacksRequired(bool $value) : void{
                $this->serverForceBehaviorPacks = $value;
        }

        /**
         * Returns an array of behavior packs in use, sorted in order of priority.
         * @return BehaviorPack[]
         * @phpstan-return list<BehaviorPack>
         */
        public function getBehaviorStack() : array{
                return $this->behaviorPacks;
        }

        /**
         * Sets the behavior packs to use. Packs earliest in the list will appear at the top of
         * the stack (maximum priority), and later ones will appear below (lower priority), in
         * the same manner as the Bedrock behavior packs screen in-game.
         *
         * @param BehaviorPack[] $behaviorStack
         * @phpstan-param list<BehaviorPack> $behaviorStack
         */
        public function setBehaviorStack(array $behaviorStack) : void{
                $uuidList = [];
                $behaviorPacks = [];
                foreach($behaviorStack as $pack){
                        $uuid = strtolower($pack->getPackId());
                        if(!Uuid::isValid($uuid)){
                                throw new \InvalidArgumentException("Invalid behavior pack UUID ($uuid)");
                        }
                        if(isset($uuidList[$uuid])){
                                throw new \InvalidArgumentException("Cannot load two behavior packs with the same UUID ($uuid)");
                        }
                        $uuidList[$uuid] = $pack;
                        $behaviorPacks[] = $pack;
                }
                $this->behaviorPacks = $behaviorPacks;
                $this->uuidList = $uuidList;
        }

        /**
         * Returns the behavior pack matching the specified UUID string, or null if the ID was
         * not recognized.
         */
        public function getPackById(string $id) : ?BehaviorPack{
                return $this->uuidList[strtolower($id)] ?? null;
        }

        /**
         * Returns an array of pack IDs for packs currently in use.
         * @return string[]
         */
        public function getPackIdList() : array{
                return array_keys($this->uuidList);
        }

        /**
         * Returns the key with which the pack was encrypted, or null if the pack has no key.
         */
        public function getPackEncryptionKey(string $id) : ?string{
                return $this->encryptionKeys[strtolower($id)] ?? null;
        }

        /**
         * Sets the encryption key to use for decrypting the specified behavior pack. The pack
         * will **NOT** be decrypted by Todium; the key is simply passed to the client to allow
         * it to decrypt the pack after downloading it.
         */
        public function setPackEncryptionKey(string $id, ?string $key) : void{
                $id = strtolower($id);
                if($key === null){
                        //allow deprovisioning keys for behavior packs that have been removed
                        unset($this->encryptionKeys[$id]);
                }elseif(isset($this->uuidList[$id])){
                        if(strlen($key) !== 32){
                                throw new \InvalidArgumentException("Encryption key must be exactly 32 bytes long");
                        }
                        $this->encryptionKeys[$id] = $key;
                }else{
                        throw new \InvalidArgumentException("Unknown pack ID $id");
                }
        }

        /**
         * Returns true if any pack in the stack declares the `script` module type or the
         * `scripts` capability. The network layer uses this to set the `hasScripts` flag in
         * `ResourcePacksInfoPacket`, which the client uses to decide whether to run scripts
         * at all.
         */
        public function hasScriptedPacks() : bool{
                foreach($this->behaviorPacks as $pack){
                        if($pack->hasScripts()){
                                return true;
                        }
                }
                return false;
        }
}
