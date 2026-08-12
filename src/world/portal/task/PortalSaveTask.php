<?php

declare(strict_types=1);

namespace pocketmine\world\portal\task;

use pocketmine\scheduler\AsyncTask;

class PortalSaveTask extends AsyncTask {

    private string $serializedData;

    public function __construct(
        private string $dbPath,
        private string $action,
        array $data
    ) {
        $this->serializedData = serialize($data);
    }

    public function onRun(): void {
        $data = unserialize($this->serializedData);
        $db = new \SQLite3($this->dbPath);
        $db->busyTimeout(5000);

        switch ($this->action) {
            case "save_portal":
                $stmt = $db->prepare("INSERT OR REPLACE INTO portals (id, world, x, y, z, dest_world, dest_x, dest_y, dest_z, axis) VALUES (:id, :world, :x, :y, :z, :dest_world, :dest_x, :dest_y, :dest_z, :axis)");
                $stmt->bindValue(":id", $data["id"], SQLITE3_TEXT);
                $stmt->bindValue(":world", $data["world"], SQLITE3_TEXT);
                $stmt->bindValue(":x", $data["x"], SQLITE3_INTEGER);
                $stmt->bindValue(":y", $data["y"], SQLITE3_INTEGER);
                $stmt->bindValue(":z", $data["z"], SQLITE3_INTEGER);
                $stmt->bindValue(":dest_world", $data["dest_world"], SQLITE3_TEXT);
                $stmt->bindValue(":dest_x", $data["dest_x"], SQLITE3_FLOAT);
                $stmt->bindValue(":dest_y", $data["dest_y"], SQLITE3_FLOAT);
                $stmt->bindValue(":dest_z", $data["dest_z"], SQLITE3_FLOAT);
                $stmt->bindValue(":axis", $data["axis"], SQLITE3_INTEGER);
                $stmt->execute();

                $db->exec("BEGIN TRANSACTION");
                $stmtBlock = $db->prepare("INSERT OR REPLACE INTO portal_blocks (portal_id, world, x, y, z) VALUES (:portal_id, :world, :x, :y, :z)");
                foreach ($data["blocks"] as $block) {
                    $stmtBlock->bindValue(":portal_id", $data["id"], SQLITE3_TEXT);
                    $stmtBlock->bindValue(":world", $block["world"], SQLITE3_TEXT);
                    $stmtBlock->bindValue(":x", $block["x"], SQLITE3_INTEGER);
                    $stmtBlock->bindValue(":y", $block["y"], SQLITE3_INTEGER);
                    $stmtBlock->bindValue(":z", $block["z"], SQLITE3_INTEGER);
                    $stmtBlock->execute();
                }
                $db->exec("COMMIT");
                break;

            case "delete_portal":
                $stmt = $db->prepare("DELETE FROM portals WHERE id = :id");
                $stmt->bindValue(":id", $data["id"], SQLITE3_TEXT);
                $stmt->execute();

                $stmtBlocks = $db->prepare("DELETE FROM portal_blocks WHERE portal_id = :portal_id");
                $stmtBlocks->bindValue(":portal_id", $data["id"], SQLITE3_TEXT);
                $stmtBlocks->execute();
                break;
        }

        $db->close();
    }
}
