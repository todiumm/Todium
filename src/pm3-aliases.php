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

/**
 * Todium PM3 compatibility layer — class aliases.
 *
 * PocketMine-MP 4.0 introduced a major namespace refactor. Many classes that lived in
 * flat namespaces under PM3 (`pocketmine\Player`, `pocketmine\Item`, etc.) were moved
 * into sub-namespaces (`pocketmine\player\Player`, `pocketmine\item\Item`).
 *
 * As a result, PM3 plugins that `use pocketmine\Player;` fail to load on PM5/Todium
 * because the class `pocketmine\Player` no longer exists. This file restores those old
 * class names as aliases of their PM5 equivalents, so existing PM3 plugins can run
 * without modification.
 *
 * Todium loads this file once during bootstrap (when at least one PM3-compatible plugin
 * is detected). Plugins that declare `api: [3.x.x]` in their plugin.yml trigger the
 * alias layer to be loaded before their main class is instantiated.
 *
 * What this layer does NOT do:
 *   - It does NOT translate PM3 method signatures. Many PM3 methods took different
 *     parameters than their PM5 equivalents. Plugins that call those methods directly
 *     will still fail.
 *   - It does NOT restore PM3 events that were renamed or removed in PM4/PM5.
 *   - It does NOT restore removed classes like `ItemFactory`, `BlockFactory`, `ItemIds`,
 *     `BlockIds`, `PluginTask`, etc. Plugins that depend on those need source patches.
 *
 * See `PM3_COMPATIBILITY.md` for the full list of supported aliases and known limitations.
 */

//Only register aliases for classes that actually exist in the PM5/Todium codebase.
//Trying to alias to a non-existent class triggers a PHP warning and leaves the alias
//unregistered, which is worse than not aliasing at all.

$aliases = [
        // === Player / entities ===
        "pocketmine\\Player" => "pocketmine\\player\\Player",

        // === Items / blocks (only the classes that survived PM4/PM5) ===
        "pocketmine\\Item" => "pocketmine\\item\\Item",
        "pocketmine\\item\\Item" => "pocketmine\\item\\Item", //already exists; class_alias will no-op
        "pocketmine\\Enchantment" => "pocketmine\\item\\enchantment\\Enchantment",
        "pocketmine\\enchantment\\Enchantment" => "pocketmine\\item\\enchantment\\Enchantment",
        "pocketmine\\enchantment\\EnchantmentInstance" => "pocketmine\\item\\enchantment\\EnchantmentInstance",

        // === Block (block namespace was preserved across PM3 → PM5) ===
        "pocketmine\\Block" => "pocketmine\\block\\Block",

        // === Commands ===
        "pocketmine\\Command" => "pocketmine\\command\\Command",
        "pocketmine\\CommandSender" => "pocketmine\\command\\CommandSender",

        // === Entity namespace ===
        "pocketmine\\Entity" => "pocketmine\\entity\\Entity",
        "pocketmine\\Location" => "pocketmine\\entity\\Location",

        // === Events (most event class names were preserved across PM3 → PM5) ===
        "pocketmine\\event\\block\\BlockBreakEvent" => "pocketmine\\event\\block\\BlockBreakEvent",
        "pocketmine\\event\\block\\BlockPlaceEvent" => "pocketmine\\event\\block\\BlockPlaceEvent",
        "pocketmine\\event\\entity\\EntityDamageEvent" => "pocketmine\\event\\entity\\EntityDamageEvent",
        "pocketmine\\event\\entity\\EntityDamageByEntityEvent" => "pocketmine\\event\\entity\\EntityDamageByEntityEvent",
        "pocketmine\\event\\entity\\EntityDeathEvent" => "pocketmine\\event\\entity\\EntityDeathEvent",
        "pocketmine\\event\\player\\PlayerChatEvent" => "pocketmine\\event\\player\\PlayerChatEvent",
        "pocketmine\\event\\player\\PlayerDeathEvent" => "pocketmine\\event\\player\\PlayerDeathEvent",
        "pocketmine\\event\\player\\PlayerJoinEvent" => "pocketmine\\event\\player\\PlayerJoinEvent",
        "pocketmine\\event\\player\\PlayerQuitEvent" => "pocketmine\\event\\player\\PlayerQuitEvent",
        "pocketmine\\event\\player\\PlayerLoginEvent" => "pocketmine\\event\\player\\PlayerLoginEvent",
        "pocketmine\\event\\player\\PlayerMoveEvent" => "pocketmine\\event\\player\\PlayerMoveEvent",
        "pocketmine\\event\\player\\PlayerRespawnEvent" => "pocketmine\\event\\player\\PlayerRespawnEvent",
        "pocketmine\\event\\player\\PlayerInteractEvent" => "pocketmine\\event\\player\\PlayerInteractEvent",
        "pocketmine\\event\\player\\PlayerItemHeldEvent" => "pocketmine\\event\\player\\PlayerItemHeldEvent",
        "pocketmine\\event\\player\\PlayerDropItemEvent" => "pocketmine\\event\\player\\PlayerDropItemEvent",
        "pocketmine\\event\\player\\PlayerItemConsumeEvent" => "pocketmine\\event\\player\\PlayerItemConsumeEvent",
        "pocketmine\\event\\server\\DataPacketReceiveEvent" => "pocketmine\\event\\server\\DataPacketReceiveEvent",
        "pocketmine\\event\\server\\DataPacketSendEvent" => "pocketmine\\event\\server\\DataPacketSendEvent",

        // === Math ===
        "pocketmine\\math\\Vector3" => "pocketmine\\math\\Vector3",

        // === NBT ===
        "pocketmine\\nbt\\tag\\CompoundTag" => "pocketmine\\nbt\\tag\\CompoundTag",
        "pocketmine\\nbt\\tag\\ListTag" => "pocketmine\\nbt\\tag\\ListTag",
        "pocketmine\\nbt\\tag\\StringTag" => "pocketmine\\nbt\\tag\\StringTag",
        "pocketmine\\nbt\\tag\\IntTag" => "pocketmine\\nbt\\tag\\IntTag",
        "pocketmine\\nbt\\tag\\FloatTag" => "pocketmine\\nbt\\tag\\FloatTag",
        "pocketmine\\nbt\\tag\\ByteTag" => "pocketmine\\nbt\\tag\\ByteTag",
        "pocketmine\\nbt\\tag\\ShortTag" => "pocketmine\\nbt\\tag\\ShortTag",
        "pocketmine\\nbt\\tag\\LongTag" => "pocketmine\\nbt\\tag\\LongTag",
        "pocketmine\\nbt\\tag\\DoubleTag" => "pocketmine\\nbt\\tag\\DoubleTag",
        "pocketmine\\nbt\\tag\\ByteArrayTag" => "pocketmine\\nbt\\tag\\ByteArrayTag",
        "pocketmine\\nbt\\tag\\IntArrayTag" => "pocketmine\\nbt\\tag\\IntArrayTag",

        // === Network ===
        "pocketmine\\network\\mcpe\\protocol\\DataPacket" => "pocketmine\\network\\mcpe\\protocol\\DataPacket",

        // === Permission ===
        "pocketmine\\permission\\Permission" => "pocketmine\\permission\\Permission",
        "pocketmine\\permission\\Permissible" => "pocketmine\\permission\\Permissible",
        "pocketmine\\permission\\PermissibleBase" => "pocketmine\\permission\\PermissibleBase",
        "pocketmine\\permission\\PermissionManager" => "pocketmine\\permission\\PermissionManager",
        "pocketmine\\permission\\PermissionAttachment" => "pocketmine\\permission\\PermissionAttachment",
        "pocketmine\\permission\\PermissionAttachmentInfo" => "pocketmine\\permission\\PermissionAttachmentInfo",

        // === Plugin / scheduler (PluginBase/PluginManager/TaskScheduler kept their names) ===
        "pocketmine\\plugin\\Plugin" => "pocketmine\\plugin\\Plugin",
        "pocketmine\\plugin\\PluginBase" => "pocketmine\\plugin\\PluginBase",
        "pocketmine\\plugin\\PluginManager" => "pocketmine\\plugin\\PluginManager",
        "pocketmine\\scheduler\\TaskHandler" => "pocketmine\\scheduler\\TaskHandler",
        "pocketmine\\scheduler\\TaskScheduler" => "pocketmine\\scheduler\\TaskScheduler",

        // === Utils ===
        "pocketmine\\utils\\Config" => "pocketmine\\utils\\Config",
        "pocketmine\\utils\\TextFormat" => "pocketmine\\utils\\TextFormat",
        "pocketmine\\utils\\Utils" => "pocketmine\\utils\\Utils",

        // === Level → World (the biggest PM3 → PM4 rename) ===
        "pocketmine\\Level" => "pocketmine\\world\\World",
        "pocketmine\\level\\Level" => "pocketmine\\world\\World",
        "pocketmine\\level\\Position" => "pocketmine\\world\\Position",
        "pocketmine\\Position" => "pocketmine\\world\\Position",

        // === Language ===
        "pocketmine\\lang\\BaseLang" => "pocketmine\\lang\\Language",
];

foreach($aliases as $alias => $target){
        //Skip if the alias is the same as the target (no-op) — happens for several events
        //and NBT tags whose names were preserved across PM3 → PM5.
        if($alias === $target){
                continue;
        }
        //Skip if the alias is already registered (e.g. by a previous call or by PM5 itself).
        if(class_exists($alias, false) || interface_exists($alias, false)){
                continue;
        }
        //Skip if the target class doesn't exist (PM5 removed it). This avoids PHP warnings.
        if(!class_exists($target, true) && !interface_exists($target, true)){
                continue;
        }
        //Use @ to suppress the "already in use" warning if the alias somehow got registered
        //between our check and the class_alias call (race condition with the autoloader).
        @class_alias($target, $alias);
}

// === UUID shim ===
//PM5 removed `pocketmine\utils\UUID` in favor of `ramsey/uuid`. We provide a shim class
//that restores the PM3 method surface. This is loaded from a separate file because
//class_alias can't alias to a class that's defined later in the same file reliably.
if(!class_exists("pocketmine\\utils\\UUID")){
        require_once __DIR__ . "/utils/UUID.php";
}
