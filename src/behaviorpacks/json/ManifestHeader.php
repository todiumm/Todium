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
 * Model for JsonMapper to represent behavior pack manifest.json header block.
 *
 * The header is identical in shape to a resource pack header: it carries the pack's UUID,
 * human-readable name, version (as a 3-tuple), and the minimum engine version that the
 * pack requires. The `min_engine_version` is critical for behavior packs because it tells
 * the client which script API surface to expose to the pack's scripts.
 */
final class ManifestHeader{

	public string $description;

	/** @required */
	public string $name;

	/** @required */
	public string $uuid;

	/**
	 * @var int[]
	 * @phpstan-var array{int, int, int}
	 * @required
	 */
	public array $version;

	/**
	 * @var int[]
	 * @phpstan-var array{int, int, int}
	 */
	public array $min_engine_version;
}
