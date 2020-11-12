<?php

namespace xenialdan\libstructure\format;

use Exception;
use Generator;
use InvalidArgumentException;
use OutOfBoundsException;
use OutOfRangeException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\PalettedBlockArray;
use xenialdan\MagicWE2\API;
use xenialdan\MagicWE2\helper\BlockStatesEntry;
use xenialdan\MagicWE2\helper\BlockStatesParser;

class MCStructure
{
	/** @var Vector3 */
	private $structure_world_origin;
	/** @var int */
	private $format_version;
	/** @var Vector3 */
	private $size;
	/** @var PalettedBlockArray[] */
	private $blockLayers;
	/** @var array */
	private $entities;

	/**
	 * MCStructure constructor.
	 * @param int $format_version
	 * @param Vector3 $structure_world_origin
	 * @param Vector3 $size
	 * @param CompoundTag $structure
	 */
	public function __construct(int $format_version, Vector3 $structure_world_origin, Vector3 $size, CompoundTag $structure)
	{
		$this->structure_world_origin = $structure_world_origin;
		$this->format_version = $format_version;
		$this->size = $size;
		$this->parseStructure($structure);
	}

	private function parseStructure(CompoundTag $structure): void
	{
		$blockIndicesList = $structure->getListTag('block_indices');//list<list<int>>
		#var_dump($blockIndicesList->toString(2));
		$entitiesList = $structure->getListTag('entities');
		#var_dump($entitiesList->toString(2));
		$paletteCompound = $structure->getCompoundTag('palette');
		#var_dump($paletteCompound->toString(2));
		#$this->parseEntities($entitiesList);//TODO
		$this->parseBlockLayers($paletteCompound, $blockIndicesList);
	}

	/**
	 * @param CompoundTag|null $paletteCompound
	 * @param ListTag<ListTag<IntTag>>|null $blockIndicesList
	 * @throws InvalidArgumentException
	 * @throws OutOfRangeException
	 */
	private function parseBlockLayers(?CompoundTag $paletteCompound, ?ListTag $blockIndicesList): void
	{
		/*if($paletteCompound->count() > 1){

		}*/
		$paletteDefaultTag = $paletteCompound->getCompoundTag(MCStructureFile::TAG_PALETTE_DEFAULT);
		$paletteBlocks = new PalettedBlockArray(BlockLegacyIds::AIR << 4);
		$paletteLiquids = new PalettedBlockArray(BlockLegacyIds::AIR << 4);
		/** @var BlockStatesEntry[] $paletteArray */
		$paletteArray = [];
		/** @var CompoundTag $blockCompoundTag */
		foreach ($paletteDefaultTag->getListTag(MCStructureFile::TAG_PALETTE_BLOCK_PALETTE) as $paletteIndex => $blockCompoundTag) {
			$blockState = BlockStatesParser::getInstance()::getStateByCompound($blockCompoundTag);
			if ($blockState instanceof BlockStatesEntry) $paletteArray[$paletteIndex] = $blockState;
			else print TextFormat::RED . $blockCompoundTag . " is not BlockStatesEntry";
		}
		//positions
		$l = $this->size->getZ();
		$h = $this->size->getY();
		foreach (range(0, $this->size->getZ()) as $z) {
			foreach (range(0, $this->size->getY()) as $y) {
				foreach (range(0, $this->size->getX()) as $x) {
//					foreach ($blockIndicesList as $layerIndex => $layer) {
//						$layer = reset($layer);//only default
//						/** @var ListTag $layer */
//						foreach ($layer as $i => $paletteId) {
//							/** @var IntTag $paletteId */
//
//						}
//					}
					$offset = (int)(($x * $l * $h) + ($y * $l) + $z);

					//block layer
					/** @var ListTag<IntTag> $tag */
					$tag = $blockIndicesList->get(0);
					$blockLayer = $tag->getAllValues();
					if (($i = $blockLayer[$offset] ?? -1) !== -1) {
						if (($statesEntry = $paletteArray[$i] ?? null) !== null) {
							try {
								$block = $statesEntry->toBlock();
								#API::setComponents($block, $x, $y, $z);//todo test
								//todo block_entity_data (tile nbt)
								$paletteBlocks->set($x, $y, $z, $block->getFullId());
							} catch (Exception $e) {
								Server::getInstance()->getLogger()->logException($e);
							}
						}
					}
					//liquid layer
					$tag = $blockIndicesList->get(1);
					$liquidLayer = $tag->getAllValues();
					if (($i = $liquidLayer[$offset] ?? -1) !== -1) {
						if (($statesEntry = $paletteArray[$i] ?? null) !== null) {
							try {
								$block = $statesEntry->toBlock();
								#API::setComponents($block, $x, $y, $z);//todo test
								$paletteLiquids->set($x, $y, $z, $block->getFullId());
							} catch (Exception $e) {
								Server::getInstance()->getLogger()->logException($e);
							}
						}
					}
				}
			}
		}
		//debug TODO remove
		foreach ($paletteBlocks->getPalette() as $fullId) Server::getInstance()->getLogger()->debug(BlockStatesParser::printStates(BlockStatesParser::getStateByBlock(BlockFactory::getInstance()->fromFullBlock($fullId)), false));
		foreach ($paletteLiquids->getPalette() as $fullId) Server::getInstance()->getLogger()->debug(BlockStatesParser::printStates(BlockStatesParser::getStateByBlock(BlockFactory::getInstance()->fromFullBlock($fullId)), false));
		#foreach ($paletteLiquids->getPalette() as $fullId) print BlockFactory::getInstance()->fromFullBlock($fullId)->getName());

		var_dump($paletteBlocks->getBitsPerBlock());
		$i1 = $paletteLiquids->get(3, 0, 3);
		var_dump($i1, BlockFactory::getInstance()->fromFullBlock($i1));

		$this->blockLayers = [$paletteBlocks, $paletteLiquids];
	}

	/**
	 * @param int $layer Zero = block layer, One = liquid layer
	 * @return Generator|Block[]
	 * @throws OutOfBoundsException
	 */
	public function iterateBlocks(int $layer = 0): Generator
	{
		if ($layer > count($this->blockLayers) || $layer < 0) throw new OutOfBoundsException('Layers must be in range 0...' . count($this->blockLayers));
		for ($x = 0; $x <= $this->size->getX(); $x++) {
			for ($y = 0; $y <= $this->size->getY(); $y++) {
				for ($z = 0; $z <= $this->size->getZ(); $z++) {
					$fullState = $this->blockLayers[$layer]->get($x, $y, $z);
					yield API::setComponents(BlockFactory::getInstance()->fromFullBlock($fullState), $x, $y, $z);
				}
			}
		}
	}

}