# Todium PM3 Plugin Compatibility

Todium ships an opt-in compatibility layer that lets PocketMine-MP 3.x plugins run
alongside PM5 plugins. This document explains how it works, what works, what doesn't,
and how to debug plugins that fail to load.

## Enabling / disabling

The compat layer is **enabled by default**. To disable it, add this to `pocketmine.yml`
under the `settings:` section:

```yaml
settings:
  todium.pm3-compat: false
```

When disabled, plugins declaring `api: [3.x.x]` in their `plugin.yml` are rejected at
load time with the standard "incompatible API" error. This is useful for production
servers that want to enforce strict PM5-only plugin compatibility.

## How it works

Todium adds two pieces:

1. **API version acceptance** — `pocketmine\plugin\ApiVersion::isCompatible()` is
   extended to accept plugins that declare `api: [3.x.x]`. The 3.x line is treated as
   compatible with Todium's PM5-based API surface when the compat layer is enabled.

2. **Class alias layer** — `src/pm3-aliases.php` is loaded once (lazily, when the first
   PM3 plugin is detected) and installs `class_alias()` mappings that restore the old
   PM3 class names:

   ```php
   class_alias(\pocketmine\player\Player::class, \pocketmine\Player::class);
   class_alias(\pocketmine\world\World::class,  \pocketmine\Level::class);
   class_alias(\pocketmine\item\Item::class,    \pocketmine\Item::class);
   // ... ~80 more aliases
   ```

   This lets PM3 plugins' `use pocketmine\Player;` statements resolve to the modern
   `pocketmine\player\Player` class without source modification.

When the alias layer is loaded, Todium logs:

```
[INFO] Loaded Todium PM3 compatibility aliases for plugin "MyOldPlugin" (declares API 3.x).
```

## Supported aliases

The alias layer covers the most commonly used PM3 classes:

| Category       | Examples |
|----------------|----------|
| Player / entities | `pocketmine\Player`, `pocketmine\Entity`, `pocketmine\Location`, `pocketmine\Arrow`, `pocketmine\Snowball`, `pocketmine\Egg`, `pocketmine\EnderPearl`, `pocketmine\ItemEntity`, `pocketmine\FallingBlock`, `pocketmine\PrimedTNT` |
| Items / blocks | `pocketmine\Item`, `pocketmine\ItemFactory`, `pocketmine\ItemIds`, `pocketmine\Block`, `pocketmine\BlockFactory`, `pocketmine\BlockIds`, `pocketmine\Enchantment` |
| Commands | `pocketmine\Command`, `pocketmine\CommandSender`, `pocketmine\ConsoleCommandSender`, `pocketmine\PluginCommand` |
| Events | `pocketmine\event\block\BlockBreakEvent`, `BlockPlaceEvent`, `pocketmine\event\entity\EntityDamageEvent`, `EntityDamageByEntityEvent`, `EntityDeathEvent`, `pocketmine\event\player\PlayerChatEvent`, `PlayerDeathEvent`, `PlayerJoinEvent`, `PlayerQuitEvent`, `PlayerLoginEvent`, `PlayerMoveEvent`, `PlayerRespawnEvent`, `PlayerInteractEvent`, `PlayerItemHeldEvent`, `PlayerDropItemEvent`, `PlayerItemConsumeEvent`, `pocketmine\event\server\DataPacketReceiveEvent`, `DataPacketSendEvent` |
| Inventories | `pocketmine\inventory\Inventory`, `BaseInventory`, `CraftingGrid`, `PlayerInventory`, `ArmorInventory`, `PlayerCursorInventory` |
| Level → World | `pocketmine\Level` → `pocketmine\world\World`, `pocketmine\level\Level` → `pocketmine\world\World`, `pocketmine\level\Position` → `pocketmine\world\Position`, `pocketmine\Position` → `pocketmine\world\Position` |
| NBT | `pocketmine\nbt\tag\CompoundTag`, `ListTag`, `StringTag`, `IntTag`, `FloatTag`, `ByteTag`, `ShortTag`, `LongTag`, `DoubleTag`, `ByteArrayTag`, `IntArrayTag` |
| Network | `pocketmine\network\mcpe\protocol\DataPacket` |
| Permission | `pocketmine\permission\Permission`, `PermissibleBase` |
| Plugin / scheduler | `pocketmine\plugin\Plugin`, `PluginBase`, `PluginManager`, `pocketmine\scheduler\TaskHandler`, `TaskScheduler` (note: `PluginTask` was removed in PM5 — use `ClosureTask` instead) |
| Tile → BlockEntity | `pocketmine\tile\*` was renamed to `pocketmine\block\tile\*` in PM5. The alias layer does NOT map these (the PM5 classes have different internals). Use `pocketmine\block\tile\Tile`, `Sign`, `Chest`, `Furnace`, etc. directly. |
| Utils | `pocketmine\utils\Config`, `TextFormat`, `UUID` (shim), `Utils` |
| Language | `pocketmine\lang\BaseLang` → `pocketmine\lang\Language` |

### The UUID shim

PocketMine-MP 5.x removed `pocketmine\utils\UUID` in favor of the upstream `ramsey/uuid`
library. Todium ships a shim class at `src/utils/UUID.php` that restores the PM3
method surface (`fromString`, `fromBinary`, `fromRandom`, `toString`, `toBinary`,
`getLeastSignificantBits`, `getMostSignificantBits`, `equals`) and delegates to
`ramsey/uuid` internally.

## What works

In practice, most PM3 plugins that do "boring" things — register event listeners,
schedule tasks, send messages, run commands, manipulate inventories — load and run on
Todium without source modification after the alias layer kicks in.

Known-good categories:

- Event listener plugins (`PlayerJoinEvent`, `PlayerChatEvent`, `BlockBreakEvent`, etc.)
- Command plugins (`/mycommand`)
- Task scheduler plugins (`$this->getScheduler()->scheduleDelayedTask(...)`)
- Config-driven plugins (`new Config($file, Config::YAML)`)
- Inventory manipulation (via the `BaseInventory` alias)
- NBT read/write (via the alias layer)

## What doesn't work

The alias layer restores class **names** but cannot restore PM3 **method signatures**.
The following are known to fail and require source modification:

1. **Renamed methods.** Many PM3 methods were renamed or restructured in PM4/PM5.
   For example, `Player::sendMessage(string)` still works, but
   `Player::kick(string $reason, bool $isLogged = false)` was replaced with
   `Player::kick(string $reason, Translatable|string|null $quitMessage = null)`. Plugins
   that call `kick()` with the old signature will get argument count errors.

2. **PM3-only events.** Some PM3 events were removed or renamed in PM4:
   - `pocketmine\event\player\PlayerBedEnterEvent` (still exists, but with different fields)
   - `pocketmine\event\level\LevelLoadEvent` → renamed to `WorldLoadEvent`
   - `pocketmine\event\level\LevelSaveEvent` → renamed to `WorldSaveEvent`

3. **`pocketmine\level\Level` API differences.** The `Level` alias points to
   `pocketmine\world\World`, but `Level` had methods that `World` doesn't (and vice
   versa). For example:
   - `Level::getBlockEntityAt(int $x, int $y, int $z)` → `World::getTileAt(...)` (PM3 name preserved via alias)
   - `Level::getEntities()` works, but the return type changed (now `Entity[]` instead of a `Level`-specific collection)
   - `Level::useBreakOn(...)` signature changed significantly

4. **Metadata API.** PM3 had `pocketmine\metadata\MetadataValue` and the `Metadatable`
   interface. PM5 removed them entirely. Plugins using the metadata API will get
   "class not found" errors.

5. **Scheduler differences.** `pocketmine\scheduler\PluginTask` was removed in PM5 in
   favor of closures + `ClosureTask`. The alias layer maps `PluginTask` to the modern
   `Task` class, but PM3 plugins that extend `PluginTask` and override `onRun(int $tick)`
   will work — the signature is preserved.

6. **Network packet IDs.** PM3 packet IDs and PM5 packet IDs are completely different.
   Plugins that build packets by hand (`new TextPacket()`, set `->type = ...`) will
   likely produce malformed packets.

7. **`pocketmine\Player` method signatures.** The `Player` alias points to
   `pocketmine\player\Player`, but many methods changed signatures. Common breakages:
   - `Player::sendTip(string)` → still works
   - `Player::getInventory()` → returns `PlayerInventory` (same)
   - `Player::teleport(Vector3 $pos, int $yaw = 0, int $pitch = 0)` → signature now `teleport(Position $target, ?float $yaw = null, ?float $pitch = null)`. Old code passing `Vector3` will get a type error.

8. **Item / block ID system.** PM3 used numeric IDs everywhere (`Item::get(1, 0, 1)` for
   one stone). PM5 deprecated numeric IDs in favor of `ItemTypeIds` / `BlockTypeIds`.
   The PM3 `Item::get()` static method is not aliased because the PM5 equivalent has a
   completely different signature. Use `StringToItemParser` or `VanillaItems::*` instead.

## Debugging

If a PM3 plugin fails to load, check the server log for these messages:

### "Class pocketmine\X not found"

The plugin uses a class that's not in the alias layer. The alias list is in
`src/pm3-aliases.php`. Add the missing class alias and rebuild the phar.

### "Class pocketmine\player\Player has no method X"

The plugin calls a PM3 method that was renamed or removed. You'll need to patch the
plugin source code.

### "Argument #1 ($x) must be of type Y, Z given"

Method signature mismatch. The plugin passes a value of the wrong type to a PM5 method
that has stricter type hints than its PM3 equivalent. You'll need to patch the plugin.

### "Todium PM3 compatibility layer is disabled"

You set `todium.pm3-compat: false` in `pocketmine.yml`. Either set it back to `true` or
upgrade the plugin to declare `api: [5.0.0]`.

## Porting strategy

If you maintain a PM3 plugin and want to make it work on Todium without modification:

1. Make sure your `plugin.yml` declares both API lines:
   ```yaml
   api: [3.0.0, 5.0.0]
   ```
2. Avoid the categories in "What doesn't work" above.
3. Use `StringToItemParser` instead of numeric item IDs.
4. Use closures for scheduled tasks (`$scheduler->scheduleTask(new ClosureTask(function(): void { ... }))`).
5. Test on Todium with `todium.pm3-compat: true`. Watch the log for "Class not found"
   errors and add aliases as needed.

## Migration path

For long-term portability, the recommended approach is to migrate PM3 plugins to the PM5
API surface natively. The PM3 compat layer is meant as a stopgap so server admins can
keep using legacy plugins while they (or the plugin authors) port them.

To port a plugin:

1. Update `plugin.yml` to declare `api: [5.0.0]` only.
2. Replace `use pocketmine\Player;` with `use pocketmine\player\Player;` (and similar).
3. Replace `pocketmine\Level` with `pocketmine\world\World`.
4. Replace `pocketmine\utils\UUID` with `Ramsey\Uuid\Uuid`.
5. Replace `pocketmine\tile\*` with `pocketmine\block\tile\*`.
6. Update method signatures per the PM5 changelogs (see `changelogs/4.0.md`, `5.0.md`).
7. Test on Todium with `todium.pm3-compat: false` to ensure no PM3 aliases are needed.

## Reporting missing aliases

If you encounter a PM3 plugin that fails with "Class pocketmine\X not found" and the
class has a clear PM5 equivalent, please open an issue at
<https://github.com/todium/Todium/issues> with the class name and a link to the plugin.
We'll add the alias in the next Todium release.
