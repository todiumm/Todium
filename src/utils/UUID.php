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

namespace pocketmine\utils;

use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Shim for the PM3 `pocketmine\utils\UUID` class.
 *
 * PocketMine-MP 5.x removed its `UUID` class in favor of the upstream `ramsey/uuid`
 * library. Many PM3 plugins use `pocketmine\utils\UUID` directly. This shim restores the
 * class with a method surface that mirrors the PM3 API, delegating to `ramsey/uuid`
 * internally.
 *
 * The most common PM3 usages (`UUID::fromString`, `UUID::toBinary`, `UUID::toString`,
 * `UUID::getLeastSignificantBits` / `getMostSignificantBits`) are supported. Less common
 * methods throw a runtime error so plugin authors get a clear failure message instead of
 * silent incorrect behavior.
 */
class UUID{

	private UuidInterface $uuid;

	private function __construct(UuidInterface $uuid){
		$this->uuid = $uuid;
	}

	public static function fromString(string $uuid) : self{
		return new self(RamseyUuid::fromString($uuid));
	}

	public static function fromBinary(string $bytes) : self{
		return new self(RamseyUuid::fromBytes($bytes));
	}

	public static function fromRandom() : self{
		return new self(RamseyUuid::uuid4());
	}

	public function toString() : string{
		return $this->uuid->toString();
	}

	public function __toString() : string{
		return $this->uuid->toString();
	}

	public function toBinary() : string{
		return $this->uuid->getBytes();
	}

	public function getLeastSignificantBits() : int{
		$hex = bin2hex(substr($this->uuid->getBytes(), 8, 8));
		$value = 0;
		for($i = 0; $i < 8; ++$i){
			$value = ($value << 8) | hexdec(substr($hex, $i * 2, 2));
		}
		//Force signed 64-bit interpretation to match Java's UUID.getLeastSignificantBits()
		return $value > PHP_INT_MAX ? $value - 0x1_0000_0000_0000_0000 : $value;
	}

	public function getMostSignificantBits() : int{
		$hex = bin2hex(substr($this->uuid->getBytes(), 0, 8));
		$value = 0;
		for($i = 0; $i < 8; ++$i){
			$value = ($value << 8) | hexdec(substr($hex, $i * 2, 2));
		}
		return $value > PHP_INT_MAX ? $value - 0x1_0000_0000_0000_0000 : $value;
	}

	public function equals(UUID $other) : bool{
		return $this->uuid->equals($other->uuid);
	}
}
