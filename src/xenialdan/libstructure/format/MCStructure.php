<?php

namespace xenialdan\libstructure\format;

use Generator;
use InvalidArgumentException;
use OutOfBoundsException;
use OutOfRangeException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\math\Vector3;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\Position;
use pocketmine\world\World;
use UnexpectedValueException;
use xenialdan\libblockstate\BlockState;
use xenialdan\libblockstate\BlockStatesParser;
use xenialdan\libstructure\exception\StructureFileException;
use xenialdan\libstructure\exception\StructureFormatException;
use xenialdan\libstructure\format\filter\MCStructureFilter;
use function array_key_exists;
use function count;

class MCStructure{
	//https://github.com/df-mc/structure/blob/651c5d323dbfb24991dafdb72129e2a8a478a81b/structure.go#L66-L91
	private const VERSION = 1;
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

	public int $formatVersion;
	public BlockPosition $size;
	public BlockPosition $origin;
	public MCStructureData $structure;

	private string $paletteName;
	/** @phpstan-var array<string, list<int>|CompoundTag<string, CompoundTag> */
	private array $palette;//pointer
	/** @var PalettedBlockArray[] */
	private array $layers = [];
	private int $activeLayer = 0;
	/** @phpstan-var array<int, CompoundTag> blockHash => data */
	public array $blockEntities = [];

	public function __construct(MCStructureData $structure, BlockPosition $size, BlockPosition $origin, string $paletteName = self::TAG_PALETTE_DEFAULT){
		$this->formatVersion = self::VERSION;
		$this->structure = &$structure;
		$this->size = $size;
		$this->origin = $origin;
		$this->paletteName = $paletteName;
		$this->palette = [];
	}

	public function check() : bool{
		if($this->formatVersion !== self::VERSION) throw new StructureFileException("Unsupported format version: " . $this->formatVersion);
		if(count($this->structure->blockIndices) === 0) throw new StructureFileException("Structure has no blocks in it");
		if(count($this->structure->palettes) === 0) throw new StructureFileException("Structure has no palettes in it");
		$size = $this->size->getX() * $this->size->getY() * $this->size->getZ();
		foreach($this->structure->blockIndices as $layer => $indices){
			if(count($indices) !== $size) throw new StructureFileException("Structure is " . $this->size->getX() . "x" . $this->size->getY() . "x" . $this->size->getZ() . " but has " . count($indices) . " blocks in layer " . $layer);
		}
		$paletteLen = -1;
		foreach($this->structure->palettes as $name => $palette){
			if($paletteLen === -1){
				$paletteLen = count($palette[MCStructure::TAG_PALETTE_BLOCK_PALETTE]);
				continue;
			}
			if(count($palette[MCStructure::TAG_PALETTE_BLOCK_PALETTE]) !== $paletteLen) throw new StructureFileException("Structure palette " . $name . " has " . count($palette[MCStructure::TAG_PALETTE_BLOCK_PALETTE]) . " entries but previous palettes have " . $paletteLen);
		}
		return true;
	}


	public function usePalette(string $name) : void{
		//FIXME add write mode
		$this->paletteName = $name;
		if(!array_key_exists($name, $this->structure->palettes)){
			$this->structure->palettes[$name] = [
				self::TAG_PALETTE_BLOCK_PALETTE => [],
				self::TAG_PALETTE_BLOCK_POSITION_DATA => new CompoundTag()
			];
		}
		$this->palette = &$this->structure->palettes[$name];
	}

	public function lookup(BlockState $properties) : int{
		foreach($this->palette[MCStructure::TAG_PALETTE_BLOCK_PALETTE] as $index => $entry){
			/** @var BlockStatesParser $blockStatesParser */
			$blockStatesParser = BlockStatesParser::getInstance();
			$blockState = $blockStatesParser->getFromCompound($entry);
			if($blockState instanceof BlockState && $blockState->equals($properties)){
				return $index;
			}
		}
		return -1;
	}


	/**
	 * Parses a *.mcstructure file
	 *
	 * @param string $path path to the .mcstructure file
	 *
	 * @throws InvalidArgumentException|StructureFileException|StructureFormatException|UnexpectedTagTypeException|UnexpectedValueException|NbtDataException|OutOfRangeException
	 * @see MCStructure
	 */
	public static function read(string $path) : self{
		$pathext = pathinfo($path, PATHINFO_EXTENSION);
		if('.' . strtolower($pathext) !== self::EXTENSION_MCSTRUCTURE) throw new InvalidArgumentException("File extension $pathext for file $path is not " . self::EXTENSION_MCSTRUCTURE);
		$path = Filesystem::cleanPath(realpath($path));
		$fread = file_get_contents($path);
		if($fread === false) throw new StructureFileException("Could not read file $path");
		$namedTag = (new LittleEndianNBTSerializer())->read($fread)->mustGetCompoundTag();

		$structure = new self(
			MCStructureData::fromNBT($namedTag->getCompoundTag(self::TAG_STRUCTURE)),
			self::parseBlockPosition($namedTag, self::TAG_SIZE, false),
			self::parseBlockPosition($namedTag, self::TAG_STRUCTURE_WORLD_ORIGIN, true)
		);
		$structure->formatVersion = $namedTag->getInt(self::TAG_FORMAT_VERSION);

		$structure->check();

		return $structure;
	}

	//write file
	public function write(string $path, ?MCStructureData $data = null) : void{
		$this->structure ??= $data;
		$pathext = pathinfo($path, PATHINFO_EXTENSION);
		if('.' . strtolower($pathext) !== self::EXTENSION_MCSTRUCTURE) throw new InvalidArgumentException("File extension $pathext for file $path is not " . self::EXTENSION_MCSTRUCTURE);
		$path = Filesystem::cleanPath($path);
		$namedTag = new TreeRoot((new CompoundTag())
			->setInt(self::TAG_FORMAT_VERSION, self::VERSION)
			->setTag(self::TAG_SIZE, new ListTag([
				new IntTag($this->size->getX()),
				new IntTag($this->size->getY()),
				new IntTag($this->size->getZ())
			], NBT::TAG_Int))
			->setTag(self::TAG_STRUCTURE, $this->structure->toNBT())
			->setTag(self::TAG_STRUCTURE_WORLD_ORIGIN, new ListTag([
				new IntTag($this->origin->getX()),
				new IntTag($this->origin->getY()),
				new IntTag($this->origin->getZ())
			], NBT::TAG_Int)));
		$serialized = (new LittleEndianNBTSerializer())->write($namedTag);
		file_put_contents($path, $serialized);
	}

	//parse method
	public function parse() : self{
		$this->structure->parse($this);
		return $this;
	}

	/**
	 * @param CompoundTag $nbt
	 * @param string      $tagName
	 * @param bool        $optional
	 *
	 * @return BlockPosition
	 * @throws UnexpectedTagTypeException
	 * @throws UnexpectedValueException
	 */
	private static function parseBlockPosition(CompoundTag $nbt, string $tagName, bool $optional) : BlockPosition{
		$pos = $nbt->getListTag($tagName);
		if($pos === null and $optional){
			return new BlockPosition(0, 0, 0);
		}
		if(!($pos instanceof ListTag) or $pos->getTagType() !== NBT::TAG_Int){
			throw new UnexpectedValueException("'$tagName' should be a List<Int>");
		}
		/** @var IntTag[] $values */
		$values = $pos->getValue();
		if(count($values) !== 3){
			throw new UnexpectedValueException("Expected exactly 3 entries in '$tagName' tag");
		}
		return new BlockPosition($values[0]->getValue(), $values[1]->getValue(), $values[2]->getValue());
	}

	public function getPaletteName() : string{
		return $this->paletteName;
	}

	/**
	 * @param PalettedBlockArray[] $layers
	 */
	public function setLayers(array $layers) : void{
		$this->layers = $layers;
	}

	/**
	 * @return PalettedBlockArray[]
	 */
	public function getLayers() : array{
		return $this->layers;
	}

	/**
	 * @throws OutOfBoundsException
	 */
	public function setActiveLayer(int $layer) : self{
		if($layer > count($this->layers) || $layer < 0) throw new OutOfBoundsException('Layers must be in range 0...' . count($this->layers));
		$this->activeLayer = $layer;
		return $this;
	}

	public function getPalettedBlockArray(?int $layer = null) : PalettedBlockArray{
		$this->activeLayer = $layer ?? $this->activeLayer;
		return $this->layers[$this->activeLayer];
	}

	/**
	 * @param PalettedBlockArray|null $palettedBlockArray can be filtered or modified using {@link MCStructureFilter} methods. If null is passed, the current layer will be used.
	 *
	 * @return Generator
	 * @phpstan-return Generator<int, Block>
	 */
	public function blocks(?PalettedBlockArray $palettedBlockArray = null) : Generator{//TODO offset
		for($x = 0; $x < $this->size->getX(); $x++){
			for($y = 0; $y < $this->size->getY(); $y++){
				for($z = 0; $z < $this->size->getZ(); $z++){
					yield $this->get($x, $y, $z, $palettedBlockArray);
				}
			}
		}
	}

	/**
	 * Get block at position.
	 *
	 * @param PalettedBlockArray|null $palettedBlockArray can be filtered or modified using {@link MCStructureFilter} methods. If null is passed, the current layer will be used.
	 */
	public function get(int $x, int $y, int $z, ?PalettedBlockArray $palettedBlockArray = null) : ?Block{//TODO offset
		$palettedBlockArray ??= $this->getPalettedBlockArray();
		$fullId = $palettedBlockArray->get($x, $y, $z);
		if($fullId === 0xff_ff_ff_ff) return null;
		$block = BlockFactory::getInstance()->fromFullBlock($fullId);
		[$block->getPosition()->x, $block->getPosition()->y, $block->getPosition()->z] = [$x, $y, $z];
		return $block;
	}

	public function set(int $x, int $y, int $z, ?BlockState $blockState, ?CompoundTag $nbt = null){
		if($blockState === null){
			$this->getPalettedBlockArray()->set($x, $y, $z, 0xff_ff_ff_ff);
			return;
		}
		$this->getPalettedBlockArray()->set($x, $y, $z, $blockState->getFullId());
//		$ptr = $this->lookup($blockState);
//		if($ptr === -1){
//			$ptr = count($this->palette[self::TAG_PALETTE_BLOCK_PALETTE]);
//			$this->palette[$ptr] = [
//				self::TAG_PALETTE_BLOCK_PALETTE => $blockState,
//				self::TAG_PALETTE_BLOCK_POSITION_DATA => $nbt
//			];
//		}
//		$l = $this->size->getZ();
//		$h = $this->size->getY();
//		$offset = ($x * $l * $h) + ($y * $l) + $z;
//		$this->structure->blockIndices[0][$offset] = $ptr;
	}

	/**
	 * @throws UnexpectedTagTypeException|InvalidArgumentException|AssumptionFailedError|SavedDataLoadingException
	 */
	public function translateBlockEntity(Position $position, Vector3 $origin) : ?Tile{
		$hash = World::blockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
		$data = $this->blockEntities[$hash] ?? null;
		if($data === null) return null;
		$instance = TileFactory::getInstance();
		$data->setInt(Tile::TAG_X, $origin->getFloorX());//why do i have to manually change that before creation.. it won't work after creation
		$data->setInt(Tile::TAG_Y, $origin->getFloorY());
		$data->setInt(Tile::TAG_Z, $origin->getFloorZ());

		//hack to fix container items loading
		if(($inventoryTag = $data->getTag(Container::TAG_ITEMS)) instanceof ListTag){
			/** @var CompoundTag $itemNBT */
			foreach($inventoryTag as $itemNBT){
				$itemNBT->setString("id", $itemNBT->getString("Name", "minecraft:air"));
				$itemNBT->removeTag("Name");
				if(($tag = $itemNBT->getCompoundTag("tag")) !== null){
					if($tag->getTag("Damage") !== null) $tag->removeTag("Damage");
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

		/** @noinspection PhpInternalEntityUsedInspection */
		$tile = $instance->createFromData($position->getWorld(), $data);
		if($tile === null) return null;//Might return null if the tile is not registered
		return $tile;
	}

	public function getSize() : BlockPosition{
		return $this->size;
	}

	public function getStructureWorldOrigin() : BlockPosition{
		return $this->origin;
	}

	public function getBlockEntitiesRaw() : array{
		return $this->blockEntities;
	}

	/**
	 * @return CompoundTag[]
	 * @phpstan-return list<CompoundTag>
	 */
	public function getEntitiesRaw() : array{
		return $this->structure->entities;
	}

	public function getBlockLayersCount() : int{
		return count($this->layers);
	}

	public function getFormatVersion() : int{
		return $this->formatVersion;
	}

}