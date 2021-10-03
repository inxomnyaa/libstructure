<?php

declare(strict_types=1);

namespace xenialdan\libstructure\tile;

use pocketmine\block\tile\Nameable;
use pocketmine\block\tile\NameableTrait;
use pocketmine\block\tile\Spawnable;
use pocketmine\inventory\InventoryHolder;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\StructureEditorData;
use pocketmine\network\mcpe\protocol\types\StructureSettings;
use pocketmine\world\World;
use xenialdan\libstructure\window\StructureBlockInventory;
use xenialdan\MagicWE2\session\data\Asset;

class StructureBlockTile extends Spawnable implements Nameable, InventoryHolder
{
	use NameableTrait {
		addAdditionalSpawnData as addNameSpawnData;
	}

	/** @var StructureBlockInventory */
	protected StructureBlockInventory $inventory;
	private bool $hideStructureBlock = true;
	private bool $showPlayers = false;
	private bool $showEntities = false;
	private bool $showBlocks = true;
	private bool $showBoundingBox = true;
	protected int $mode = StructureEditorData::TYPE_SAVE;
	private Vector3 $fromV3;
	private Vector3 $toV3;
	private ?string $title = null;

	public function __construct(World $world, Vector3 $pos)
	{
		var_dump("constructing tile");
		parent::__construct($world, $pos);
		$this->fromV3 = $this->toV3 = $this->position->asVector3();
		$this->inventory = new StructureBlockInventory($this->position);
		var_dump("constructing tile done");
	}

	public function readSaveData(CompoundTag $nbt): void
	{
		//todo read structure block data
		$this->loadName($nbt);
		var_dump(__METHOD__);
	}

	protected function writeSaveData(CompoundTag $nbt): void
	{
		//~todo~ write structure block data
		$this->addStructureBlockData($nbt);
		$nbt->setInt(StructureBlockTags::TAG_DATA, $this->mode);
		$this->saveName($nbt);
		var_dump(__METHOD__);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt): void
	{
		$this->addStructureBlockData($nbt);
		$this->addNameSpawnData($nbt);
		var_dump($nbt->toString());
	}

	/**
	 * @return StructureBlockInventory
	 */
	public function getInventory(): StructureBlockInventory
	{
		return $this->inventory;
	}

	public function getDefaultName(): string
	{
		return "Structure Block";
	}

	/**
	 * @param Vector3 $fromV3
	 * @return StructureBlockTile
	 */
	public function setFromV3(Vector3 $fromV3): self
	{
		$this->fromV3 = $fromV3;
		return $this;
	}

	/**
	 * @param Vector3 $toV3
	 * @return StructureBlockTile
	 */
	public function setToV3(Vector3 $toV3): self
	{
		$this->toV3 = $toV3;
		return $this;
	}

	/**
	 * @param string $title
	 * @return StructureBlockTile
	 */
	public function setTitle(string $title): self
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * @param bool $hideStructureBlock
	 * @return StructureBlockTile
	 */
	public function setHideStructureBlock(bool $hideStructureBlock): self
	{
		$this->hideStructureBlock = $hideStructureBlock;
		return $this;
	}

	/**
	 * @param bool $showPlayers
	 * @return StructureBlockTile
	 */
	public function setShowPlayers(bool $showPlayers): self
	{
		$this->showPlayers = $showPlayers;
		return $this;
	}

	/**
	 * @param bool $showEntities
	 * @return StructureBlockTile
	 */
	public function setShowEntities(bool $showEntities): self
	{
		$this->showEntities = $showEntities;
		return $this;
	}

	/**
	 * @param bool $showBlocks
	 * @return StructureBlockTile
	 */
	public function setShowBlocks(bool $showBlocks): self
	{
		$this->showBlocks = $showBlocks;
		return $this;
	}

	/**
	 * @param bool $showBoundingBox
	 * @return StructureBlockTile
	 */
	public function setShowBoundingBox(bool $showBoundingBox): self
	{
		$this->showBoundingBox = $showBoundingBox;
		return $this;
	}

	private function calculateOffset(Vector3 $holderV3): Vector3
	{
		return $holderV3->subtractVector(Vector3::minComponents($this->fromV3, $this->toV3))->multiply(-1)->floor();
	}

	private function calculateSize(): Vector3
	{
		return $this->fromV3->subtractVector($this->toV3)->abs()->add(1, 1, 1);
	}

	protected function addStructureBlockData(CompoundTag $nbt): void
	{
		$pos = $this->getPosition();
		$offset = $this->calculateOffset($pos->asVector3());
		$size = $this->calculateSize();
		var_dump("offset", $offset, "size", $size, "blockV3", $pos->asVector3());
		$nbt->setInt("data", $this->mode);
		$nbt->setString("dataField", "");
		$nbt->setByte("ignoreEntities", $this->showEntities ? 0 : 1);
		$nbt->setByte("includePlayers", $this->showPlayers ? 1 : 0);
		$nbt->setFloat("integrity", 100.0);
		$nbt->setByte("isMovable", 1);
		$nbt->setByte("isPowered", 0);
		$nbt->setByte("mirror", 0);
		$nbt->setByte("removeBlocks", $this->showBlocks ? 0 : 1);
		$nbt->setByte("rotation", 0);
		$nbt->setLong("seed", 0);
		$nbt->setByte("showBoundingBox", $this->showBoundingBox ? 1 : 0);
		$nbt->setString("structureName", $this->title ?? $this->getName());
		$nbt->setInt("x", (int)$pos->x);
		$nbt->setInt("xStructureOffset", (int)$offset->x);
		$nbt->setInt("xStructureSize", (int)$size->x);
		$nbt->setInt("y", (int)$pos->y);
		$nbt->setInt("yStructureOffset", (int)$offset->y+1);//TODO remove +1 hack
		$nbt->setInt("yStructureSize", (int)$size->y);
		$nbt->setInt("z", (int)$pos->z);
		$nbt->setInt("zStructureOffset", (int)$offset->z);
		$nbt->setInt("zStructureSize", (int)$size->z);
		var_dump($nbt->toString());
	}

	/**
	 * @return bool
	 */
	public function isShowPlayers(): bool
	{
		return $this->showPlayers;
	}

	/**
	 * @return bool
	 */
	public function isShowEntities(): bool
	{
		return $this->showEntities;
	}

	/**
	 * @return bool
	 */
	public function isShowBlocks(): bool
	{
		return $this->showBlocks;
	}

	/**
	 * @return bool
	 */
	public function isShowBoundingBox(): bool
	{
		return $this->showBoundingBox;
	}

	/**
	 * @return int
	 */
	public function getMode(): int
	{
		return $this->mode;
	}

	public function getStructureEditorData(Asset $asset): StructureEditorData
	{
		$data = new StructureEditorData();
		$data->structureName = $asset->displayname;
		$data->structureDataField = "";
		$data->includePlayers = $this->isShowPlayers();
		$data->showBoundingBox = $this->isShowBoundingBox();
		$data->structureBlockType = $this->getMode();
		$data->structureSettings = $this->getStructureSettings($asset);
		$data->structureRedstoneSaveMove = 0;
		return $data;
	}

	private function getStructureSettings(Asset $asset): StructureSettings
	{
		$settings = new StructureSettings();
		$settings->paletteName = "default";
		$settings->ignoreEntities = !$this->isShowEntities();
		$settings->ignoreBlocks = !$this->isShowBlocks();
		$settings->structureSizeX = $asset->getSize()->getFloorX();
		$settings->structureSizeY = $asset->getSize()->getFloorY();
		$settings->structureSizeZ = $asset->getSize()->getFloorZ();
		$settings->structureOffsetX = 0;
		$settings->structureOffsetY = 0;
		$settings->structureOffsetZ = 0;//TODO position
		$settings->lastTouchedByPlayerID = -1;
		$settings->rotation = 0;
		$settings->mirror = false;
		$settings->integrityValue = 1.0;
		$settings->integritySeed = 0;
		$settings->pivot = $asset->getOrigin();
		return $settings;
	}
}