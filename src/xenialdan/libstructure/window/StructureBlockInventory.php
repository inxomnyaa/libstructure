<?php

declare(strict_types=1);

namespace xenialdan\libstructure\window;

use pocketmine\inventory\CustomInventory;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;

class StructureBlockInventory extends CustomInventory {

	/** @var Position */
	protected $holder;

	public function __construct(Position $pos){
		parent::__construct($pos->asPosition());
	}

	public function getNetworkType() : int{
		return WindowTypes::STRUCTURE_EDITOR;
	}

	public function getName() : string{
		return "Structure Block";
	}

	public function getDefaultSize() : int{
		return 0;
	}

	/**
	 * This override is here for documentation and code completion purposes only.
	 * @return Position
	 */
	public function getHolder(){
		return $this->holder;
	}

    /**
     * @param Player|Player[] $target
     */
    public function sendContents($target) : void{
    }
}
