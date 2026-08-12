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

namespace pocketmine\network\mcpe;

use pocketmine\network\mcpe\protocol\ProtocolInfo;

/**
 * Todium-specific protocol metadata.
 *
 * The authoritative {@see ProtocolInfo} constants live in the upstream `pocketmine/bedrock-protocol`
 * library. After the upstream PMMP project announced end-of-support at Minecraft 1.26.30, Todium
 * maintains a fork of that library (published as `todium/bedrock-protocol`) which advances
 * {@see ProtocolInfo::MINECRAFT_VERSION} and {@see ProtocolInfo::MINECRAFT_VERSION_NETWORK} for each
 * new Bedrock release.
 *
 * This class exposes the **Todium-side** view of the same metadata. It serves three purposes:
 *
 * 1. It lets plugins read the protocol version Todium was built against without depending on the
 *    upstream library's namespace.
 * 2. It documents the protocol update process for Todium maintainers (see `TODIUM_PROTOCOL_UPDATE.md`).
 * 3. It verifies at bootstrap that the installed `todium/bedrock-protocol` (or the upstream
 *    `pocketmine/bedrock-protocol` fallback) is at least at the display version Todium expects,
 *    surfacing a non-fatal warning if it isn't.
 *
 * Constants here MUST stay in sync with the values declared in
 * `vendor/todium/bedrock-protocol/src/ProtocolInfo.php` after each protocol bump.
 */
final class TodiumProtocolInfo{
	/**
	 * Bedrock protocol version that Todium currently targets. This reflects
	 * {@see ProtocolInfo::CURRENT_PROTOCOL} from the `todium/bedrock-protocol` library.
	 *
	 * As of Todium 1.0.0, this targets Minecraft: Bedrock Edition 1.26.33.
	 *
	 * Minecraft 1.26.33 is a **protocol-compatible** patch release: Mojang shipped 1.26.31,
	 * 1.26.32, and 1.26.33 without bumping the protocol number. `CURRENT_PROTOCOL` stays at
	 * 1001 — the same value upstream PMMP used for 1.26.30. Only the display version strings
	 * advance to `1.26.33`.
	 */
	public const CURRENT_PROTOCOL = ProtocolInfo::CURRENT_PROTOCOL;

	/**
	 * Human-readable Minecraft version Todium currently targets. Mirrors
	 * {@see ProtocolInfo::MINECRAFT_VERSION}.
	 */
	public const MINECRAFT_VERSION = ProtocolInfo::MINECRAFT_VERSION;

	/**
	 * Short network version string used in `ResourcePackStackPacket` (e.g. "1.26.33").
	 * Mirrors {@see ProtocolInfo::MINECRAFT_VERSION_NETWORK}.
	 */
	public const MINECRAFT_VERSION_NETWORK = ProtocolInfo::MINECRAFT_VERSION_NETWORK;

	/**
	 * Bedrock major.minor.patch revision Todium targets. Bumped whenever a new Minecraft: Bedrock
	 * release is integrated. This is informational only and used by the crash dumper.
	 */
	public const TODIUM_TARGET_BEDROCK_MAJOR = 1;
	public const TODIUM_TARGET_BEDROCK_MINOR = 26;
	public const TODIUM_TARGET_BEDROCK_PATCH = 33;

	/**
	 * The `MINECRAFT_VERSION_NETWORK` value Todium expects from the installed bedrock-protocol
	 * library. Used by {@see verifyCompatibility()} to detect installations that are still on the
	 * upstream 1.26.30 strings.
	 *
	 * This is compared as a string, not a protocol number, because protocol-compatible patches
	 * (like 1.26.31 / 1.26.32 / 1.26.33) do not bump `CURRENT_PROTOCOL` — only the display
	 * version advances.
	 */
	public const EXPECTED_MINECRAFT_VERSION_NETWORK = "1.26.33";

	/**
	 * Last upstream PocketMine-MP protocol version before Todium forked.
	 *
	 * Minecraft 1.26.30 was the last release upstream PMMP supported (bedrock-protocol 58.0.x).
	 * The protocol number was 1001. Todium 1.0.0 also targets protocol 1001 because 1.26.33 is
	 * protocol-compatible with 1.26.30 — the difference is only in the display version strings.
	 */
	public const LAST_UPSTREAM_PMMP_PROTOCOL = 1001;

	/**
	 * Last upstream PocketMine-MP `MINECRAFT_VERSION_NETWORK` string. Used by
	 * {@see verifyCompatibility()} to detect installations still on the upstream library.
	 */
	public const LAST_UPSTREAM_PMMP_VERSION_NETWORK = "1.26.30";

	private function __construct(){
		//NOOP
	}

	/**
	 * Called during server bootstrap to verify that the installed `todium/bedrock-protocol`
	 * (or the upstream `pocketmine/bedrock-protocol` fallback) is at least at the display
	 * version Todium expects.
	 *
	 * Because Minecraft 1.26.33 is a protocol-compatible patch release, the check is based on
	 * `MINECRAFT_VERSION_NETWORK` (string comparison) rather than `CURRENT_PROTOCOL` (which is
	 * identical between 1.26.30 and 1.26.33).
	 *
	 * @return string[] list of human-readable mismatch warnings; empty if everything is consistent
	 */
	public static function verifyCompatibility() : array{
		$warnings = [];

		//Display version check: the installed library must report at least the Todium target
		//version. If it doesn't, the user has installed the upstream `pocketmine/bedrock-protocol`
		//(frozen at 1.26.30) instead of the `todium/bedrock-protocol` fork.
		if(ProtocolInfo::MINECRAFT_VERSION_NETWORK !== self::EXPECTED_MINECRAFT_VERSION_NETWORK){
			$warnings[] = "Installed bedrock-protocol reports Minecraft " . ProtocolInfo::MINECRAFT_VERSION_NETWORK .
				" (protocol " . ProtocolInfo::CURRENT_PROTOCOL . "), but Todium " .
				self::TODIUM_TARGET_BEDROCK_MAJOR . "." . self::TODIUM_TARGET_BEDROCK_MINOR . "." . self::TODIUM_TARGET_BEDROCK_PATCH .
				" expects " . self::EXPECTED_MINECRAFT_VERSION_NETWORK . ". " .
				"Did you install the upstream `pocketmine/bedrock-protocol` instead of the " .
				"`todium/bedrock-protocol` fork? The server will still accept clients on protocol " .
				ProtocolInfo::CURRENT_PROTOCOL . " (1.26.30 through 1.26.33 are protocol-compatible), " .
				"but the log output will show the wrong display version. " .
				"See TODIUM_PROTOCOL_UPDATE.md for instructions.";
		}

		return $warnings;
	}
}
