<?php

declare(strict_types=1);

namespace xenialdan\libstructure\window;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\block\inventory\BlockInventoryTrait;
use pocketmine\inventory\SimpleInventory;
use pocketmine\world\Position;

class StructureBlockInventory extends SimpleInventory implements BlockInventory
{
	use BlockInventoryTrait;
	public function __construct(Position $holder)
	{
		$this->holder = $holder;
		parent::__construct(0);
	}
}