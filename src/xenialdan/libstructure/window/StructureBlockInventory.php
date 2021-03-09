<?php

declare(strict_types=1);

namespace xenialdan\libstructure\window;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\world\Position;

class StructureBlockInventory extends BlockInventory
{
	public function __construct(Position $position)
	{
		parent::__construct($position, 0);
	}
}