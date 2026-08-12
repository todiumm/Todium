<?php

declare(strict_types=1);

namespace pocketmine\vanilla\inventory;

use pocketmine\inventory\SimpleInventory;

class TradeInventory extends SimpleInventory {

    public function __construct() {
        parent::__construct(5); 
    }
}
