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

/**
 * Represents a Minecraft: Bedrock Edition behavior pack (also called an "addon").
 *
 * Behavior packs are zip/mcpack archives that contain a `manifest.json` describing the
 * pack and one or more modules: scripts, block behavior JSON, entity behavior JSON, loot
 * tables, etc. When a client connects, the server advertises the behavior packs it wants
 * the client to load via `ResourcePacksInfoPacket` (with `hasAddons = true`). The client
 * downloads them using the same chunked transfer protocol as resource packs, then applies
 * them locally.
 *
 * The Todium server itself does **not** interpret the behavior pack contents. It only:
 *
 * 1. Validates the manifest at load time (so that a malformed pack doesn't disconnect
 *    every joining client).
 * 2. Serves the raw zip bytes to clients on request.
 * 3. Reports the pack's UUID, version, size, and SHA256 hash so the client can verify
 *    the download.
 *
 * Plugins that want to inspect behavior pack contents (e.g. to register custom items
 * based on the pack's JSON) can read the manifest via {@see ZippedBehaviorPack::getManifest()}.
 */
interface BehaviorPack{

	/**
	 * Returns the human-readable name of the behavior pack, as declared in manifest.json.
	 */
	public function getPackName() : string;

	/**
	 * Returns the pack's UUID as a human-readable string. This is the canonical identifier
	 * the client uses to track which packs it has already downloaded.
	 */
	public function getPackId() : string;

	/**
	 * Returns the size of the pack on disk in bytes.
	 */
	public function getPackSize() : int;

	/**
	 * Returns a version number for the pack in the format major.minor.patch.
	 */
	public function getPackVersion() : string;

	/**
	 * Returns the raw SHA256 sum of the compressed behavior pack archive. The client uses
	 * this to validate the download.
	 *
	 * @return string byte-array, 32 bytes long
	 */
	public function getSha256() : string;

	/**
	 * Returns a chunk of the behavior pack archive as a byte-array for sending to clients.
	 *
	 * Behavior packs must **always** be in zip archive format for sending.
	 *
	 * @param int $start  Offset to start reading the chunk from
	 * @param int $length Maximum length of data to return
	 *
	 * @phpstan-param positive-int $length
	 *
	 * @return string byte-array
	 * @throws \InvalidArgumentException if the chunk does not exist
	 */
	public function getPackChunk(int $start, int $length) : string;

	/**
	 * Returns whether the pack declares the `scripts` capability in its manifest.
	 *
	 * Packs with scripts require the `hasScripts` flag in `ResourcePacksInfoPacket` to be
	 * set to `true`, and the client will only run the scripts if the player has explicitly
	 * enabled "Beta APIs" / "Show experimental gameplay" depending on the script API level.
	 */
	public function hasScripts() : bool;
}
