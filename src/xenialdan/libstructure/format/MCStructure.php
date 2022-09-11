<?php

namespace xenialdan\libstructure\format;

use Closure;
use Generator;
use InvalidArgumentException;
use OutOfBoundsException;
use OutOfRangeException;
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
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\Position;
use pocketmine\world\World;
use UnexpectedValueException;
use xenialdan\libblockstate\BlockState;
use xenialdan\libstructure\exception\StructureFileException;
use xenialdan\libstructure\exception\StructureFormatException;
use xenialdan\libstructure\format\filter\MCStructureFilter;
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
	private array $layers;
	private int $activeLayer = 0;

	public array $blockEntities;

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

	public function set(int $x, int $y, int $z, BlockState $blockState, ?CompoundTag $nbt = null){
		$ptr = $this->lookup($blockState);
		if($ptr === -1){
			$ptr = count($this->palette[self::TAG_PALETTE_BLOCK_PALETTE]);
			$this->palette[$ptr] = [
				self::TAG_PALETTE_BLOCK_PALETTE => $blockState,
				self::TAG_PALETTE_BLOCK_POSITION_DATA => $nbt
			];
		}
		$l = $this->size->getZ();
		$h = $this->size->getY();
		$offset = ($x * $l * $h) + ($y * $l) + $z;
		$this->structure->blockIndices[0][$offset] = $ptr;
	}

	//usePalette function
	public function usePalette(string $name) : void{
		//FIXME add write mode
		if(isset($this->structure->palettes[$name])){//array_key_exists?
			$this->paletteName = $name;
			$this->palette = &$this->structure->palettes[$name];
		}
	}

	public function lookup(BlockState $properties) : int{
		foreach($this->palette[MCStructure::TAG_PALETTE_BLOCK_PALETTE] as $index => $entry){
			$blockState = $entry[self::TAG_PALETTE_BLOCK_PALETTE];
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

		$structure = new self();

		$structure->formatVersion = $namedTag->getInt(self::TAG_FORMAT_VERSION);
		$structure->origin = self::parseBlockPosition($namedTag, self::TAG_STRUCTURE_WORLD_ORIGIN, true);
		$structure->size = self::parseBlockPosition($namedTag, self::TAG_SIZE, false);
		$structure->structure = MCStructureData::fromNBT($namedTag->getCompoundTag(self::TAG_STRUCTURE));

		$structure->check();

		return $structure;

		#$this->parseStructure($namedTag->getCompoundTag(self::TAG_STRUCTURE));
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

//	/**
//	 * @param CompoundTag|null              $paletteCompound
//	 * @param ListTag<ListTag<IntTag>>|null $blockIndicesList
//	 *
//	 * @throws InvalidArgumentException|OutOfRangeException|UnexpectedTagTypeException
//	 */
//	private function parseBlockLayers(?CompoundTag $paletteCompound, ?ListTag $blockIndicesList) : void{
//		/*if($paletteCompound->count() > 1){
//
//		}*/
//		$paletteDefaultTag = $paletteCompound->getCompoundTag(self::TAG_PALETTE_DEFAULT);
//		$paletteBlocks = new PalettedBlockArray(0xff_ff_ff_ff);
//		$paletteLiquids = new PalettedBlockArray(0xff_ff_ff_ff);
//		$blockEntities = [];
//		/** @var BlockState[] $paletteArray */
//		$paletteArray = [];
//		/** @var CompoundTag $blockCompoundTag */
//		foreach($paletteDefaultTag->getListTag(self::TAG_PALETTE_BLOCK_PALETTE) as $paletteIndex => $blockCompoundTag){
//			$blockState = BlockStatesParser::getInstance()->getFromCompound($blockCompoundTag);
//			if($blockState instanceof BlockState) $paletteArray[$paletteIndex] = $blockState;
//			else print TextFormat::RED . $blockCompoundTag . " is not BlockStatesEntry";
//		}
//		/** @var CompoundTag $blockPositionData */
//		$blockPositionData = $paletteDefaultTag->getCompoundTag(self::TAG_PALETTE_BLOCK_POSITION_DATA);
//		//positions
//		$l = $this->size->getZ();
//		$h = $this->size->getY();
//		foreach(range(0, $this->size->getZ() - 1) as $z){
//			foreach(range(0, $this->size->getY() - 1) as $y){
//				foreach(range(0, $this->size->getX() - 1) as $x){
////					foreach ($blockIndicesList as $layerIndex => $layer) {
////						$layer = reset($layer);//only default
////						/** @var ListTag $layer */
////						foreach ($layer as $i => $paletteId) {
////							/** @var IntTag $paletteId */
////
////						}
////					}
//					$offset = (int) (($x * $l * $h) + ($y * $l) + $z);
//
//					//block layer
//					/** @var ListTag<IntTag> $tag */
//					$tag = $blockIndicesList->get(0);
//					$blockLayer = $tag->getAllValues();
//					if(($i = $blockLayer[$offset] ?? -1) !== -1){
//						if(($statesEntry = $paletteArray[$i] ?? null) !== null){
//							try{
//								$block = $statesEntry->getBlock();
//								/** @noinspection PhpInternalEntityUsedInspection */
//								$paletteBlocks->set($x, $y, $z, $block->getFullId());
//							}catch(Exception $e){
//								GlobalLogger::get()->logException($e);
//							}
//						}
//					}
//					//liquid layer
//					/** @var ListTag<IntTag> $tag */
//					$tag = $blockIndicesList->get(1);
//					$liquidLayer = $tag->getAllValues();
//					if(($i = $liquidLayer[$offset] ?? -1) !== -1){
//						if(($statesEntry = $paletteArray[$i] ?? null) !== null){
//							try{
//								$block = $statesEntry->getBlock();
//								/** @noinspection PhpInternalEntityUsedInspection */
//								$paletteLiquids->set($x, $y, $z, $block->getFullId());
//							}catch(Exception $e){
//								GlobalLogger::get()->logException($e);
//							}
//						}
//					}
//					//nbt
//					if($blockPositionData->getTag((string) $offset) !== null){
//						/** @var CompoundTag<CompoundTag> $tag1 */
//						$tag1 = $blockPositionData->getCompoundTag((string) $offset);
//						$blockEntities[World::blockHash($x, $y, $z)] = $tag1->getCompoundTag(self::TAG_PALETTE_BLOCK_ENTITY_DATA);
//					}
//				}
//			}
//		}
//
//		$this->blockLayers = [$paletteBlocks, $paletteLiquids];
//		$this->blockEntities = $blockEntities;
//	}

	public function getPalettedBlockArray(?int $layer = null) : PalettedBlockArray{
		$this->activeLayer = $layer ?? $this->activeLayer;
		return $this->layers[$this->activeLayer];
	}

	/**
	 * @param PalettedBlockArray|null $palettedBlockArray can be filtered or modified using {@link MCStructureFilter} methods. If null is passed, the current layer will be used.
	 *
	 * @return Generator
	 */
	public function blocks(?PalettedBlockArray $palettedBlockArray = null) : Generator{
		$palettedBlockArray ??= $this->getPalettedBlockArray();
		for($x = 0; $x < $this->size->getX(); $x++){
			for($y = 0; $y < $this->size->getY(); $y++){
				for($z = 0; $z < $this->size->getZ(); $z++){
					$fullId = $palettedBlockArray->get($x, $y, $z);
					$block = BlockFactory::getInstance()->fromFullBlock($fullId);
					[$block->getPosition()->x, $block->getPosition()->y, $block->getPosition()->z] = [$x, $y, $z];
					yield $block;
				}
			}
		}
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

	/**
	 * Reads a value of an object, regardless of access modifiers
	 *
	 * @param object $object
	 * @param string $property
	 *
	 * @return mixed
	 */
	public static function &readAnyValue(object $object, string $property) : mixed{
		$invoke = Closure::bind(function & () use ($property){
			return $this->$property;
		}, $object, $object)->__invoke();
		/** @noinspection PhpUnnecessaryLocalVariableInspection */
		$value = &$invoke;

		return $value;
	}

	public function getSize() : BlockPosition{
		return $this->size;
	}

	public function getStructureWorldOrigin() : BlockPosition{
		return $this->origin;
	}

	/**
	 * @return CompoundTag[]
	 */
	public function getBlockEntitiesRaw() : array{
		return $this->blockEntities;
	}

	/**
	 * @return CompoundTag[]
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