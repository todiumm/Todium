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

namespace pocketmine\event\player;

use pocketmine\event\Event;
use pocketmine\player\PlayerInfo;
use pocketmine\behaviorpacks\BehaviorPack;
use function array_unshift;

/**
 * Called after a player authenticates and is being offered behavior packs to download.
 *
 * This event is fired separately from {@see PlayerResourcePackOfferEvent} because behavior
 * packs and resource packs have distinct precedence rules and may be configured
 * independently. The two events fire in sequence (resource packs first, then behavior
 * packs) just before the combined stack is sent to the client via
 * `ResourcePacksInfoPacket`.
 *
 * Plugins can use this event to:
 *   - Add per-player behavior packs (e.g. a "tutorial" pack only for new players).
 *   - Remove behavior packs that the player has already accepted (e.g. via a custom
 *     handshake packet processed earlier).
 *   - Force the player to download all server-supplied behavior packs before joining.
 */
class PlayerBehaviorPackOfferEvent extends Event{
	/**
	 * @param BehaviorPack[] $behaviorPacks
	 * @param string[]       $encryptionKeys pack UUID => key, leave unset for any packs that are not encrypted
	 *
	 * @phpstan-param list<BehaviorPack>    $behaviorPacks
	 * @phpstan-param array<string, string> $encryptionKeys
	 */
	public function __construct(
		private readonly PlayerInfo $playerInfo,
		private array $behaviorPacks,
		private array $encryptionKeys,
		private bool $mustAccept
	){}

	public function getPlayerInfo() : PlayerInfo{
		return $this->playerInfo;
	}

	/**
	 * Adds a behavior pack to the top of the stack.
	 * The behaviors in this pack will be applied over the top of any existing packs.
	 */
	public function addBehaviorPack(BehaviorPack $entry, ?string $encryptionKey = null) : void{
		array_unshift($this->behaviorPacks, $entry);
		if($encryptionKey !== null){
			$this->encryptionKeys[$entry->getPackId()] = $encryptionKey;
		}
	}

	/**
	 * Sets the behavior packs to offer. Packs are applied from the highest key to the
	 * lowest, with each pack overwriting any behaviors from the previous pack. This means
	 * that the pack at index 0 gets the final say on which behaviors are used.
	 *
	 * @param BehaviorPack[] $behaviorPacks
	 * @param string[]       $encryptionKeys pack UUID => key, leave unset for any packs that are not encrypted
	 *
	 * @phpstan-param list<BehaviorPack>    $behaviorPacks
	 * @phpstan-param array<string, string> $encryptionKeys
	 */
	public function setBehaviorPacks(array $behaviorPacks, array $encryptionKeys) : void{
		$this->behaviorPacks = $behaviorPacks;
		$this->encryptionKeys = $encryptionKeys;
	}

	/**
	 * @return BehaviorPack[]
	 * @phpstan-return list<BehaviorPack>
	 */
	public function getBehaviorPacks() : array{
		return $this->behaviorPacks;
	}

	/**
	 * @return string[]
	 * @phpstan-return array<string, string>
	 */
	public function getEncryptionKeys() : array{
		return $this->encryptionKeys;
	}

	public function setMustAccept(bool $mustAccept) : void{
		$this->mustAccept = $mustAccept;
	}

	public function mustAccept() : bool{
		return $this->mustAccept;
	}
}
