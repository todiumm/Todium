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

namespace pocketmine\behaviorpacks\json;

/**
 * Model for JsonMapper representing one entry in the `modules` array of a behavior pack
 * manifest.json.
 *
 * Modules describe what the pack contains. The `type` field is one of:
 *   - `data`         — JSON behavior data (loot tables, block behaviors, etc.)
 *   - `script`       — JavaScript/TypeScript game scripts (requires the `scripts` capability)
 *   - `client_data`  — client-side data override (rare in server-side behavior packs)
 *   - `interface`    — UI elements (rare in server-side behavior packs)
 *   - `resources`    — embedded resource data (also rare)
 *
 * Todium only inspects the `type` to determine whether the pack enables scripts. The
 * actual module contents are interpreted by the client.
 */
final class ManifestModuleEntry{

	public string $description;

	/** @required */
	public string $type;

	/** @required */
	public string $uuid;

	/**
	 * @var int[]
	 * @phpstan-var array{int, int, int}
	 * @required
	 */
	public array $version;
}
