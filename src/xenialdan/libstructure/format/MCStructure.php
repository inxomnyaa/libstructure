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
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\math\Vector3;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\Position;
use pocketmine\world\World;
use UnexpectedValueException;
use xenialdan\libstructure\exception\StructureFileException;
use xenialdan\libstructure\exception\StructureFormatException;
use xenialdan\MagicWE2\helper\BlockStatesEntry;
use xenialdan\MagicWE2\helper\BlockStatesParser;

class MCStructure
{
	//https://github.com/df-mc/structure/blob/651c5d323dbfb24991dafdb72129e2a8a478a81b/structure.go#L66-L91
	public const TAG_STRUCTURE_WORLD_ORIGIN = 'structure_world_origin';
	public const TAG_FORMAT_VERSION = 'format_version';
	public const TAG_SIZE = 'size';
	public const TAG_STRUCTURE = 'structure';
	public const TAG_BLOCK_INDICES = 'block_indices';
	public const TAG_ENTITIES = 'entities';
	public const TAG_PALETTE = 'palette';
	public const TAG_PALETTE_DEFAULT = 'default';
	public const TAG_PALETTE_BLOCK_PALETTE = 'block_palette';
	public const TAG_PALETTE_BLOCK_POSITION_DATA = 'block_position_data';
	public const TAG_PALETTE_BLOCK_ENTITY_DATA = 'block_entity_data';
	public const EXTENSION_MCSTRUCTURE = '.mcstructure';
	public const LAYER_BLOCKS = 0;
	public const LAYER_LIQUIDS = 1;
	/** @var Vector3 */
	private $structure_world_origin;
	/** @var int */
	private $format_version;
	/** @var Vector3 */
	private $size;
	/** @var PalettedBlockArray[] */
	private $blockLayers = [];
	/** @var array|CompoundTag[] */
	private $entities = [];
	/** @var array|CompoundTag[] */
	private $blockEntities = [];

	/**
	 * Parses a *.mcstructure file
	 * @param string $path path to the .mcstructure file
	 * @see MCStructure
	 */
	public function parse(string $path): void
	{
		$pathext = pathinfo($path, PATHINFO_EXTENSION);
		if ('.' . strtolower($pathext) !== self::EXTENSION_MCSTRUCTURE) throw new InvalidArgumentException("File extension $pathext for file $path is not " . self::EXTENSION_MCSTRUCTURE);
		$path = Filesystem::cleanPath(realpath($path));
		$fread = file_get_contents($path);
		if ($fread === false) throw new StructureFileException("Could not read file $path");
		$namedTag = (new LittleEndianNBTSerializer())->read($fread)->mustGetCompoundTag();
		#Server::getInstance()->getLogger()->debug($namedTag->toString(2));
		//version
		$version = $namedTag->getInt(self::TAG_FORMAT_VERSION);
		if ($version === null) throw new StructureFormatException(self::TAG_FORMAT_VERSION . " must be present and valid integer");
		$this->format_version = $version;
		//structure origin
		$structureWorldOrigin = self::parseVec3($namedTag, self::TAG_STRUCTURE_WORLD_ORIGIN, true);//TODO check if optional (makes it V3{0,0,0})
		$this->structure_world_origin = $structureWorldOrigin;
		//size
		$size = self::parseVec3($namedTag, self::TAG_SIZE, false);
		$this->size = $size;
		$this->parseStructure($namedTag->getCompoundTag(self::TAG_STRUCTURE));
	}

	/**
	 * @param CompoundTag $nbt
	 * @param string $tagName
	 * @param bool $optional
	 * @return Vector3
	 * @throws UnexpectedValueException
	 * @throws UnexpectedTagTypeException
	 */
	private static function parseVec3(CompoundTag $nbt, string $tagName, bool $optional): Vector3
	{
		$pos = $nbt->getListTag($tagName);
		if ($pos === null and $optional) {
			return new Vector3(0, 0, 0);
		}
		if (!($pos instanceof ListTag) or $pos->getTagType() !== NBT::TAG_Int) {
			throw new UnexpectedValueException("'$tagName' should be a List<Int>");
		}
		/** @var IntTag[] $values */
		$values = $pos->getValue();
		if (count($values) !== 3) {
			throw new UnexpectedValueException("Expected exactly 3 entries in '$tagName' tag");
		}
		return new Vector3($values[0]->getValue(), $values[1]->getValue(), $values[2]->getValue());
	}

	private function parseStructure(CompoundTag $structure): void
	{
		$blockIndicesList = $structure->getListTag('block_indices');//list<list<int>>
		$entitiesList = $structure->getListTag('entities');
		#var_dump($entitiesList->toString(2));
		$paletteCompound = $structure->getCompoundTag('palette');
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
		$paletteDefaultTag = $paletteCompound->getCompoundTag(self::TAG_PALETTE_DEFAULT);
		$paletteBlocks = new PalettedBlockArray(BlockLegacyIds::AIR << 4);
		$paletteLiquids = new PalettedBlockArray(BlockLegacyIds::AIR << 4);
		$blockEntities = [];
		/** @var BlockStatesEntry[] $paletteArray */
		$paletteArray = [];
		/** @var CompoundTag $blockCompoundTag */
		foreach ($paletteDefaultTag->getListTag(self::TAG_PALETTE_BLOCK_PALETTE) as $paletteIndex => $blockCompoundTag) {
			$blockState = BlockStatesParser::getInstance()::getStateByCompound($blockCompoundTag);
			if ($blockState instanceof BlockStatesEntry) $paletteArray[$paletteIndex] = $blockState;
			else print TextFormat::RED . $blockCompoundTag . " is not BlockStatesEntry";
		}
		/** @var CompoundTag $blockPositionData */
		$blockPositionData = $paletteDefaultTag->getCompoundTag(self::TAG_PALETTE_BLOCK_POSITION_DATA);
		//positions
		var_dump($this->size);
		$l = $this->size->getZ();
		$h = $this->size->getY();
		foreach (range(0, $this->size->getZ() - 1) as $z) {
			foreach (range(0, $this->size->getY() - 1) as $y) {
				foreach (range(0, $this->size->getX() - 1) as $x) {
//					foreach ($blockIndicesList as $layerIndex => $layer) {
//						$layer = reset($layer);//only default
//						/** @var ListTag $layer */
//						foreach ($layer as $i => $paletteId) {
//							/** @var IntTag $paletteId */
//
//						}
//					}
					$offset = (int)(($x * $l * $h) + ($y * $l) + $z);
					var_dump($offset);

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
					//nbt
					if ($blockPositionData->hasTag((string)$offset)) {
						/** @var CompoundTag<CompoundTag> $tag1 */
						$tag1 = $blockPositionData->getCompoundTag((string)$offset);
						$blockEntities[World::blockHash($x, $y, $z)] = $tag1->getCompoundTag(self::TAG_PALETTE_BLOCK_ENTITY_DATA);
					}
				}
			}
		}

		$this->blockLayers = [$paletteBlocks, $paletteLiquids];
		$this->blockEntities = $blockEntities;
	}

	/**
	 * @param int $layer Zero = block layer, One = liquid layer
	 * @return Generator|Block[]
	 * @throws OutOfBoundsException
	 */
	public function blocks(int $layer = 0): Generator
	{
		if ($layer > count($this->blockLayers) || $layer < 0) throw new OutOfBoundsException('Layers must be in range 0...' . count($this->blockLayers));
		for ($x = 0; $x < $this->size->getX(); $x++) {
			for ($y = 0; $y < $this->size->getY(); $y++) {
				for ($z = 0; $z < $this->size->getZ(); $z++) {
					$fullState = $this->blockLayers[$layer]->get($x, $y, $z);
					$block = BlockFactory::getInstance()->fromFullBlock($fullState);
					[$block->getPos()->x, $block->getPos()->y, $block->getPos()->z] = [$x, $y, $z];
					yield $block;
				}
			}
		}
	}

	public function translateBlockEntity(Position $position, Vector3 $origin): ?Tile
	{
		$hash = World::blockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
		$data = $this->blockEntities[$hash] ?? null;
		if ($data === null) return null;
		/** @var TileFactory $instance */
		$instance = TileFactory::getInstance();
		$data->setInt(Tile::TAG_X, $origin->getFloorX());//why do i have to manually change that before creation.. it won't work after creation
		$data->setInt(Tile::TAG_Y, $origin->getFloorY());
		$data->setInt(Tile::TAG_Z, $origin->getFloorZ());

		//hack to fix container items loading
		if (($inventoryTag = $data->getTag(Container::TAG_ITEMS)) instanceof ListTag) {
			/** @var CompoundTag $itemNBT */
			foreach ($inventoryTag as $itemNBT) {
				$itemNBT->setString("id", $itemNBT->getString("Name", "minecraft:air"));
				$itemNBT->removeTag("Name");
				if ($itemNBT->hasTag("tag", CompoundTag::class)) {
					/** @var CompoundTag $tag */
					$tag = $itemNBT->getTag("tag", CompoundTag::class);
					if ($tag->hasTag("Damage")) $tag->removeTag("Damage");
				}
			}
		}

//		$knownTiles = self::readAnyValue(TileFactory::getInstance(), "knownTiles");
//		$tileId = $data->getString(Tile::TAG_ID, "");
//
//		switch ($knownTiles[$tileId] ?? null) {
//			case Chest::class:
//			{
//				if (($inventoryTag = $data->getTag(Container::TAG_ITEMS)) instanceof ListTag) {
//					/** @var CompoundTag $itemNBT */
//					foreach ($inventoryTag as $itemNBT) {
//						$itemNBT->setString("id", $itemNBT->getString("Name", "minecraft:air"));
//						$itemNBT->removeTag("Name");
//					}
//				}
//			}
//		}

		$tile = $instance->createFromData($position->getWorld(), $data);
		if ($tile === null) return null;
		return $tile;
	}

	/**
	 * Reads a value of an object, regardless of access modifiers
	 * @param object $object
	 * @param string $property
	 * @return mixed
	 */
	public static function &readAnyValue(object $object, string $property)
	{
		$invoke = \Closure::bind(function & () use ($property) {
			return $this->$property;
		}, $object, $object)->__invoke();
		/** @noinspection PhpUnnecessaryLocalVariableInspection */
		$value = &$invoke;

		return $value;
	}

}