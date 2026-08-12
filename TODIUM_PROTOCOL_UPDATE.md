# Todium — Minecraft Protocol Update Guide

This document describes how Todium maintainers bump support for a new Minecraft: Bedrock
Edition release. It mirrors the upstream PMMP documentation at
<https://doc.pmmp.io/en/rtfd/developers/internals-docs/updating-minecraft-protocol.html>
and adds Todium-specific steps.

The first such bump — Minecraft 1.26.30 → 1.26.33 — is what bootstrapped the Todium project.
Future bumps should follow the same recipe.

---

## 0. Before you start

You will need:

- A working PHP 8.1+ environment with all Todium extensions (see `composer.json`).
- A Minecraft: Bedrock Edition client running the target version (e.g. 1.26.33) on a vanilla
  BDS server, or the official BDS dedicated server binary for that version.
- A clone of the `todium/bedrock-protocol`, `todium/bedrock-data`,
  `todium/bedrock-block-upgrade-schema`, and `todium/bedrock-item-upgrade-schema` repositories.
- `Wireshark` with the **Minecraft Bedrock** dissector, or `tools/generate-bedrock-data-from-packets.php`
  fed from a packet capture of a vanilla client ↔ vanilla BDS session.

## 1. Capture vanilla packets

1. Start the vanilla BDS for the target version.
2. Connect the matching Bedrock client.
3. Capture the full login → spawn → play sequence. Make sure to:
   - Join the world and move around (chunks, movement, inventory).
   - Place and break a few blocks (block changes, inventory transactions).
   - Open the chat and run a command (text packets, command data).

## 2. Bump `bedrock-protocol`

In `todium/bedrock-protocol`:

### 2.1 `src/ProtocolInfo.php`

```php
public const CURRENT_PROTOCOL = <new protocol number>; // from the LoginPacket packet header
public const MINECRAFT_VERSION = "1.26.33";            // exact patch version
public const MINECRAFT_VERSION_NETWORK = "1.26.33";    // short form used in ResourcePackStackPacket
```

The new protocol number comes from the `protocol` field of the `LoginPacket` sent by the
client. Compare it to the previous number — if it didn't change, Mojang shipped a
protocol-compatible patch and you can skip most of the steps below.

### 2.2 Packet diffs

Run `tools/generate-bedrock-data-from-packets.php` against the capture. The diff between
the old and new generated data tells you which packets changed. Common changes:

- New packet IDs (`PacketPool::registerPacket()`).
- Renamed or new fields on existing packets (use the captured packet body to figure out the
  new layout — read each field in order and match it against the previous schema).
- New packet header flags or new `protocol_id` values for game data (block states, items,
  biomes, etc.).

Apply each change to the corresponding packet class in `src/` and the serializer/deserializer
in `src/serializer/`.

### 2.3 Type registry

If the capture shows new enum values (e.g. a new `PlayerAction`), add them to
`src/types/`. Use the same numeric IDs the client uses.

### 2.4 Tests

Run `composer test` in `bedrock-protocol`. Existing tests should still pass; add new test
cases for any packets you changed.

## 3. Bump `bedrock-data`

In `todium/bedrock-data`:

1. Copy `leveldata/version_metadata.json` from the vanilla BDS world folder.
2. Copy `canonical_block_states.nbt`, `block_state_meta_map.json`, and
   `required_item_list.json` from the vanilla BDS `data/` folder.
3. Run `tools/generate-block-palette-spec.php` and `tools/generate-item-upgrade-schema.php`
   from the Todium tree, pointing at the new data files.
4. If new blockstates were introduced, generate a new blockstate upgrade schema with
   `tools/blockstate-upgrade-schema-utils.php`. Commit the resulting JSON to
   `bedrock-block-upgrade-schema`.
5. If new items were introduced, do the same for `bedrock-item-upgrade-schema`.

Bump the `bedrock-data` package version in its own `composer.json` to
`<new minor>.0.0+bedrock-<target version>` (e.g. `6.8.0+bedrock-1.26.33`).

## 4. Bump Todium itself

Back in the Todium tree:

1. Update `composer.json` `require` to point at the new fork versions:
   ```json
   "pocketmine/bedrock-data": "~6.8.0+bedrock-1.26.33",
   "pocketmine/bedrock-item-upgrade-schema": "~1.18.0+bedrock-1.26.33",
   "pocketmine/bedrock-protocol": "~58.1.0+bedrock-1.26.33"
   ```
   (These become the only constraint once we drop the legacy `1.26.30` fallback.)
2. Run `composer update pocketmine/bedrock-*` to pull the new versions.
3. Run `composer update-codegen` to regenerate `generated/data/bedrock/*` from the new
   data files.
4. Update `src/data/bedrock/WorldDataVersions.php`:
   - Bump `LAST_OPENED_IN` patch component to match the new target.
   - Bump `NETWORK` if Mojang bumped the world NetworkVersion (you can tell by diffing
     the new `leveldata/version_metadata.json` against the old one).
   - Bump `BLOCK_STATES` if any new blockstate upgrade schema was needed.
5. Update `src/network/mcpe/TodiumProtocolInfo.php`:
   - `TODIUM_TARGET_BEDROCK_PATCH` (and major/minor if needed).
6. Smoke-test the server against a real client running the target version:
   - Player can log in.
   - Player can move, break/place blocks, open inventories, send chat.
   - Resource packs and behavior packs download and apply correctly.

## 5. Update the `composer.lock` and tag a release

1. `composer update --no-dev --lock` to refresh the lockfile.
2. Commit `composer.json`, `composer.lock`, `generated/`, and the
   `src/data/bedrock/WorldDataVersions.php` / `src/network/mcpe/TodiumProtocolInfo.php`
   bumps.
3. Tag the release as `1.<next>.0+bedrock-<target>` (e.g. `1.1.0+bedrock-1.26.34`).
4. Run `composer make-server` to produce `Todium.phar` and attach it to the GitHub release.

## 6. Common pitfalls

- **Forgetting the `todium/bedrock-protocol` fork.** If you forget to switch the composer
  constraint, the upstream `pocketmine/bedrock-protocol` package will still be installed and
  `TodiumProtocolInfo::verifyCompatibility()` will print a non-fatal warning on boot. The
  server will still start (the protocol number is identical between 1.26.30 and 1.26.33) but
  the log output will show `v26.30` instead of `v26.33`.
- **Missing blockstate upgrade schema.** Old worlds saved on previous versions will load
  with broken blocks if the upgrade schema isn't shipped alongside the protocol bump.
- **Client-side cache mismatches.** Bedrock caches the protocol blob locally; if a
  protocol bump also bumps the cache version, clients will need to clear their cache before
  they can connect.
- **Behavior pack UUID collisions.** When a behavior pack is also shipped as a resource pack
  by the same name, ensure their UUIDs differ. The Todium `BehaviorPackManager` validates
  this on load.

---

## Reference: 1.26.30 → 1.26.33 bump notes

This was the inaugural Todium bump. Differences from a "normal" protocol bump:

- **No protocol number change.** Mojang shipped 1.26.31 / 1.26.32 / 1.26.33 as
  protocol-compatible patches. `ProtocolInfo::CURRENT_PROTOCOL` remains **1001** (the same
  value upstream PMMP used for 1.26.30), but `MINECRAFT_VERSION` and
  `MINECRAFT_VERSION_NETWORK` advance to "1.26.33".
- **No new blockstates.** `WorldDataVersions::BLOCK_STATES` and the blockstate upgrade
  schema are unchanged.
- **No new world NetworkVersion.** `WorldDataVersions::NETWORK` remains 924.
- **`LAST_OPENED_IN` bumped to `1.26.33`** so Todium-advertised worlds reflect the new
  target.
- **`TodiumProtocolInfo::verifyCompatibility()` checks `MINECRAFT_VERSION_NETWORK`** instead
  of `CURRENT_PROTOCOL`, because the protocol number alone can't distinguish 1.26.30 from
  1.26.33.
- **Behavior pack support added** as the headline feature of the Todium 1.0 release; see
  `src/behaviorpacks/` and `src/network/mcpe/handler/ResourcePacksPacketHandler.php` for
  the implementation.
