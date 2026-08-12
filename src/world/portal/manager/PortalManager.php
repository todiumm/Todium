<?php

declare(strict_types=1);

namespace pocketmine\world\portal\manager;

use pocketmine\block\VanillaBlocks;
use pocketmine\world\portal\PortalModule;
use pocketmine\world\portal\task\PortalSaveTask;

class PortalManager {

    private PortalModule $module;
    private array $portals = [];
    private array $portalBlocks = [];

    public function __construct(PortalModule $module) {
        $this->module = $module;
        $this->initDatabase();
    }

    private function initDatabase(): void {
        @mkdir($this->module->getDataFolder());
        $dbPath = $this->module->getDataFolder() . "portals.db";
        $db = new \SQLite3($dbPath);
        $db->busyTimeout(5000);

        $db->exec("CREATE TABLE IF NOT EXISTS portals (
            id TEXT PRIMARY KEY,
            world TEXT,
            x INT,
            y INT,
            z INT,
            dest_world TEXT,
            dest_x DOUBLE,
            dest_y DOUBLE,
            dest_z DOUBLE,
            axis INT
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS portal_blocks (
            portal_id TEXT,
            world TEXT,
            x INT,
            y INT,
            z INT,
            PRIMARY KEY (world, x, y, z)
        )");

        $res = $db->query("SELECT * FROM portals");
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $this->portals[$row["id"]] = [
                "id" => $row["id"],
                "world" => $row["world"],
                "x" => $row["x"],
                "y" => $row["y"],
                "z" => $row["z"],
                "dest_world" => $row["dest_world"],
                "dest_x" => $row["dest_x"],
                "dest_y" => $row["dest_y"],
                "dest_z" => $row["dest_z"],
                "axis" => $row["axis"],
                "blocks" => []
            ];
        }

        $resBlocks = $db->query("SELECT * FROM portal_blocks");
        while ($row = $resBlocks->fetchArray(SQLITE3_ASSOC)) {
            $key = $row["world"] . ":" . $row["x"] . ":" . $row["y"] . ":" . $row["z"];
            $this->portalBlocks[$key] = $row["portal_id"];
            if (isset($this->portals[$row["portal_id"]])) {
                $this->portals[$row["portal_id"]]["blocks"][] = [
                    "world" => $row["world"],
                    "x" => $row["x"],
                    "y" => $row["y"],
                    "z" => $row["z"]
                ];
            }
        }
        $db->close();
        $this->module->getLogger()->info("Loaded " . count($this->portals) . " portal links from database.");
    }

    public function getPortals(): array {
        return $this->portals;
    }

    public function getPortalBlocks(): array {
        return $this->portalBlocks;
    }

    public function getPortalById(string $id): ?array {
        return $this->portals[$id] ?? null;
    }

    public function getPortalIdByBlock(string $key): ?string {
        return $this->portalBlocks[$key] ?? null;
    }

    public function savePortal(array $data): void {
        $this->portals[$data["id"]] = $data;
        foreach ($data["blocks"] as $block) {
            $key = $block["world"] . ":" . $block["x"] . ":" . $block["y"] . ":" . $block["z"];
            $this->portalBlocks[$key] = $data["id"];
        }

        $dbPath = $this->module->getDataFolder() . "portals.db";
        $this->module->getServer()->getAsyncPool()->submitTask(new PortalSaveTask($dbPath, "save_portal", $data));
    }

    public function deletePortal(string $portalId): void {
        if (!isset($this->portals[$portalId])) {
            return;
        }

        $data = $this->portals[$portalId];

        unset($this->portals[$portalId]);
        foreach ($data["blocks"] as $block) {
            $key = $block["world"] . ":" . $block["x"] . ":" . $block["y"] . ":" . $block["z"];
            unset($this->portalBlocks[$key]);
        }

        $world = $this->module->getServer()->getWorldManager()->getWorldByName($data["world"]);
        if ($world !== null) {
            $air = VanillaBlocks::AIR();
            foreach ($data["blocks"] as $block) {
                $world->setBlockAt($block["x"], $block["y"], $block["z"], $air);
            }
        }

        $dbPath = $this->module->getDataFolder() . "portals.db";
        $this->module->getServer()->getAsyncPool()->submitTask(new PortalSaveTask($dbPath, "delete_portal", ["id" => $portalId]));

        foreach ($this->portals as $pid => $pdata) {
            foreach ($data["blocks"] as $b) {
                if ($pdata["dest_world"] === $b["world"] &&
                    abs($pdata["dest_x"] - ($b["x"] + 0.5)) < 1.0 &&
                    abs($pdata["dest_y"] - $b["y"]) < 1.0 &&
                    abs($pdata["dest_z"] - ($b["z"] + 0.5)) < 1.0
                ) {
                    $this->deletePortal($pid);
                    break;
                }
            }
        }
    }
}
