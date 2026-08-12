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
 * Model for JsonMapper to represent behavior pack manifest.json contents.
 *
 * The behavior pack manifest format is a superset of the resource pack manifest format.
 * The only behavior-pack-specific rule enforced at the type level is that at least one
 * module must be present (Mojang requires this). Todium additionally inspects the modules
 * array for any entry whose `type` is `script`, which causes the
 * {@see \pocketmine\behaviorpacks\BehaviorPack::hasScripts()} flag to be set to `true`.
 */
final class Manifest{
	/** @required */
	public int $format_version;

	/** @required */
	public ManifestHeader $header;

	/**
	 * @var ManifestModuleEntry[]
	 * @required
	 */
	public array $modules;

	public ?ManifestMetadata $metadata = null;

	/** @var string[] */
	public ?array $capabilities = null;

	/** @var ManifestDependencyEntry[] */
	public ?array $dependencies = null;
}
