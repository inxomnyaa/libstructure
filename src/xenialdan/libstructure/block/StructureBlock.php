<?php

declare(strict_types=1);

namespace xenialdan\libstructure\block;

use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\types\StructureEditorData;
use pocketmine\player\Player;
use xenialdan\libstructure\tile\StructureBlockTile as TileStructureBlock;

class StructureBlock extends Block
{
	private int $mode = StructureEditorData::TYPE_SAVE;//TODO validate if correct

	public function __construct(BlockIdentifier $idInfo, string $name, ?BlockBreakInfo $breakInfo = null)
	{
		parent::__construct($idInfo, $name, $breakInfo ?? BlockBreakInfo::indestructible());
	}

	/*protected function writeStateToMeta(): int
	{
		return $this->mode;
	}

	public function readStateFromData(int $id, int $stateMeta): void
	{
		$this->mode = $stateMeta;
	}*/

	public function getStateBitmask() : int{
		return 0b101;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null): bool
	{
		if ($player instanceof Player) {
			$structureBlock = $this->position->getWorld()->getTile($this->position);
			if ($structureBlock instanceof TileStructureBlock and $player->isCreative(true)) {
				$player->setCurrentWindow($structureBlock->getInventory());
				//TODO remove once PMMP allows injecting to InventoryManager::createContainerOpen
				$id = $player->getNetworkSession()->getInvManager()->getCurrentWindowId();
				$pk = ContainerOpenPacket::blockInv($id, WindowTypes::STRUCTURE_EDITOR, BlockPosition::fromVector3($this->position->asVector3()));
				$player->getNetworkSession()->sendDataPacket($pk);
			}
		}

		return true;
	}
}
