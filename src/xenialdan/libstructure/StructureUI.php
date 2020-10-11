<?php

declare(strict_types=1);

namespace xenialdan\libstructure;

use muqsit\invmenu\inventories\SingleBlockInventory;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\utils\HolderData;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;
use xenialdan\libstructure\tile\StructureBlockTags;

class StructureUI extends SingleBlockInventory
{
    const INVENTORY_HEIGHT = -1;

    private $hideStructureBlock = true;
    private $showPlayers = false;
    private $showEntities = false;
    private $showBlocks = true;
    private $showBoundingBox = true;
    protected $mode = 5;
    private $fromV3;
    private $toV3;

    /**
     * StructureUI constructor.
     * @param InvMenu $menu
     * @param Vector3 $fromV3
     * @param Vector3 $toV3
     * @param string $title
     */
    public function __construct(InvMenu $menu, Vector3 $fromV3, Vector3 $toV3, string $title = "")
    {
        $menu->readonly();
        $this->fromV3 = $fromV3;
        $this->toV3 = $toV3;
        parent::__construct($menu, [], 0, $title);
    }

    /**
     * @param Vector3 $fromV3
     * @return StructureUI
     */
    public function setFromV3(Vector3 $fromV3): StructureUI
    {
        $this->fromV3 = $fromV3;
        return $this;
    }

    /**
     * @param Vector3 $toV3
     * @return StructureUI
     */
    public function setToV3(Vector3 $toV3): StructureUI
    {
        $this->toV3 = $toV3;
        return $this;
    }

    /**
     * @param string $title
     * @return StructureUI
     */
    public function setTitle(string $title): StructureUI
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @param bool $hideStructureBlock
     * @return StructureUI
     */
    public function setHideStructureBlock(bool $hideStructureBlock): StructureUI
    {
        $this->hideStructureBlock = $hideStructureBlock;
        return $this;
    }

    /**
     * @param bool $showPlayers
     * @return StructureUI
     */
    public function setShowPlayers(bool $showPlayers): StructureUI
    {
        $this->showPlayers = $showPlayers;
        return $this;
    }

    /**
     * @param bool $showEntities
     * @return StructureUI
     */
    public function setShowEntities(bool $showEntities): StructureUI
    {
        $this->showEntities = $showEntities;
        return $this;
    }

    /**
     * @param bool $showBlocks
     * @return StructureUI
     */
    public function setShowBlocks(bool $showBlocks): StructureUI
    {
        $this->showBlocks = $showBlocks;
        return $this;
    }

    /**
     * @param bool $showBoundingBox
     * @return StructureUI
     */
    public function setShowBoundingBox(bool $showBoundingBox): StructureUI
    {
        $this->showBoundingBox = $showBoundingBox;
        return $this;
    }

    private function calculateOffset(Vector3 $holderV3): Vector3
    {
        return $holderV3->subtract(self::getMinV3($this->fromV3, $this->toV3))->multiply(-1)->floor();
    }

    private function calculateSize(): Vector3
    {
        return $this->fromV3->subtract($this->toV3)->abs()->add(1, 1, 1);
    }

    /**
     * @param Vector3 $v1
     * @param Vector3 $v2
     * @return Vector3
     */
    public static function getMinV3(Vector3 $v1, Vector3 $v2): Vector3
    {
        return (new Vector3(min($v1->x, $v2->x), min($v1->y, $v2->y), min($v1->z, $v2->z)))->floor();
    }

    /**
     * @param Vector3 $v1
     * @param Vector3 $v2
     * @return Vector3
     */
    public static function getMaxV3(Vector3 $v1, Vector3 $v2): Vector3
    {
        return (new Vector3(max($v1->x, $v2->x), max($v1->y, $v2->y), max($v1->z, $v2->z)))->floor();
    }

    /* InvMenu */

    protected function sendFakeBlockData(Player $player, HolderData $data): void
    {
        $block = $this->getBlock()->setComponents($data->position->x, $data->position->y, $data->position->z);
        $player->getLevel()->sendBlocks([$player], [$block]);

        $tag = new CompoundTag();
        if ($data->custom_name !== null) {
            $tag->setString("CustomName", $data->custom_name);
        }
        $offset = $this->calculateOffset($block->asVector3());
        $size = $this->calculateSize();
        var_dump("offset", $offset, "size", $size, "blockV3", $block->asVector3());
        $tag->setInt("data", (int)$this->mode);
        $tag->setString("dataField", "");
        $tag->setByte("ignoreEntities", $this->showEntities ? 0 : 1);
        $tag->setByte("includePlayers", $this->showPlayers ? 1 : 0);
        $tag->setFloat("integrity", 100.0);
        $tag->setByte("isMovable", 1);
        $tag->setByte("isPowered", 0);
        $tag->setByte("mirror", 0);
        $tag->setByte("removeBlocks", $this->showBlocks ? 0 : 1);
        $tag->setByte("rotation", 0);
        $tag->setLong("seed", 0);
        $tag->setByte("showBoundingBox", $this->showBoundingBox ? 1 : 0);
        $tag->setString("structureName", $data->custom_name ?? $this->title ?? $this->getName());
        $tag->setInt("x", (int)$block->x);
        $tag->setInt("xStructureOffset", (int)$offset->x);
        $tag->setInt("xStructureSize", (int)$size->x);
        $tag->setInt("y", (int)$block->y);
        $tag->setInt("yStructureOffset", (int)$offset->y);
        $tag->setInt("yStructureSize", (int)$size->y);
        $tag->setInt("z", (int)$block->z);
        $tag->setInt("zStructureOffset", (int)$offset->z);
        $tag->setInt("zStructureSize", (int)$size->z);
        var_dump($tag->toString());

        $this->sendTile($player, $block, $tag);

        $this->onFakeBlockDataSend($player);
    }

    public function onFakeBlockDataSendSuccess(Player $player): void
    {
        var_dump($this);
        #parent::onFakeBlockDataSendSuccess($player);
    }

    public function getTileId(): string
    {
        return StructureBlockTags::TAG_ID;
    }

    public function getName(): string
    {
        return "Structure Block";
    }

    public function getDefaultSize(): int
    {
        return 0;
    }

    /**
     * Returns the Minecraft PE inventory type used to show the inventory window to clients.
     * @return int
     */
    public function getNetworkType(): int
    {
        return WindowTypes::STRUCTURE_EDITOR;
    }

    public function getBlock(): Block
    {
        return Block::get(Block::STRUCTURE_BLOCK, $this->mode);
    }
}