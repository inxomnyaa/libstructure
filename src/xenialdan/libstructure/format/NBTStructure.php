<?php

declare(strict_types=1);

namespace xenialdan\libstructure\format;

use Generator;
use InvalidArgumentException;
use OutOfRangeException;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use xenialdan\libstructure\exception\StructureFileException;
use xenialdan\MagicWE2\exception\InvalidBlockStateException;
use xenialdan\MagicWE2\helper\BlockStatesParser;
use xenialdan\MagicWE2\Loader;
use function file_get_contents;
use function zlib_decode;

class NBTStructure
{
	/** @var int */
	private $version;
	/** @var string */
	private $author;
	/** @var Vector3 */
	private $size;
	/** @var ListTag<CompoundTag> */
	private $palettes;
	/** @var ListTag<CompoundTag> */
	private $blocks;
	/** @var ListTag<CompoundTag> */
	private $entities;

	/**
	 * save saves a schematic to disk.
	 *
	 * @param string $file the Schematic output file name
	 */
	public function save(string $file): void//TODO
	{
//		$nbt = new TreeRoot(
//			CompoundTag::create()
//				->setByteArray("Blocks", $this->blocks)
//				->setByteArray("Data", $this->data)
//				->setShort("Length", $this->length)
//				->setShort("Width", $this->width)
//				->setShort("Height", $this->height)
//				->setString("Materials", self::MATERIALS_POCKET)
//		);
//		//NOTE: Save after encoding with zlib_encode for backward compatibility.
//		file_put_contents($file, zlib_encode((new BigEndianNbtSerializer())->write($nbt), ZLIB_ENCODING_GZIP));
	}

	/**
	 * parse parses a schematic from the file passed.
	 *
	 * @param string $file
	 * @throws OutOfRangeException
	 * @throws StructureFileException
	 * @throws NbtDataException
	 */
	public function parse(string $file): void
	{
		$nbt = (new BigEndianNbtSerializer())->read(zlib_decode(file_get_contents($file)));
		$nbt = $nbt->getTag();
		/** @var CompoundTag $nbt */

		//https://minecraft.gamepedia.com/Data_version
		$this->version = $nbt->getInt("DataVersion", 100);//todo figure out fallback
		if($this->version !== 100) throw new StructureFileException("File contains DataVersion, indicating Java structure. Java structures are not supported");
		$this->author = $nbt->getString("author", "");

		/** @var ListTag<IntTag> $size */
		$size = $nbt->getListTag("size");
		$this->size = new Vector3($size->get(0)->getValue(), $size->get(1)->getValue(), $size->get(2)->getValue());

		$this->palettes = $nbt->getListTag("palettes") ?? new ListTag([$nbt->getListTag("palette")], NBT::TAG_List);
		$this->blocks = $nbt->getListTag("blocks") ?? new ListTag([], NBT::TAG_List);
		$this->entities = $nbt->getListTag("entities") ?? new ListTag([$nbt->getListTag("entities")], NBT::TAG_List);
	}

	/**
	 * @param ListTag $paletteList
	 * @return Block[]
	 * @throws InvalidArgumentException
	 * @throws \pocketmine\block\utils\InvalidBlockStateException
	 */
	private function paletteToBlocks(ListTag $paletteList): array
	{
		/** @var Block[] $blocks */
		$blocks = [];
		/** @var CompoundTag $blockCompound */
		foreach ($paletteList/*->getValue()*/ as $blockCompound) {
			$id = $blockCompound->getString('Name');
			$states = [];
			/** @var CompoundTag<StringTag> $properties */
			$properties = $blockCompound->getCompoundTag('Properties');
			if ($properties instanceof CompoundTag)
				//Java/legacy hack
				/*if($properties->hasTag('dataID')){
					$legacyDataId = $properties->getInt('dataID');
					//Block::getStateFromLegacyData
				} else{
					if($properties->hasTag('half')){
						$legacyHalf = $properties->getString('half');
						//LegacyStructureTemplate::_mapToProperty(&v99, v19, v65);
					}
					if($properties->hasTag('waterlogged')){
						$legacyWaterlogged = $properties->getString('waterlogged');
						//LegacyStructureTemplate::_mapPropertyToExtraBlock(&v97, v20);
					}
					//while properties -> v65 = (Block *)LegacyStructureTemplate::_mapToProperty(v21, v22, v65); (v5 = Block)
				}*///TODO java fixes

				/**
				 * @var string $name
				 * @var StringTag $value
				 */
				foreach ($properties->getValue() as $name => $value) {
					$valueString = (string)$value->getValue();
					$states[] = $name . '=' . $valueString;
				}
			try {
				$fromString = BlockStatesParser::fromString($id . '[' . implode(',', $states) . ']');
			} catch (InvalidBlockStateException $e) {
				Loader::getInstance()->getLogger()->logException($e);
			}
			$blocks[] = reset($fromString);
		}
		return $blocks;
	}

	/**
	 * returns a generator of blocks found in the schematic opened.
	 * @param int $palette
	 * @return Generator
	 * @throws OutOfRangeException
	 * @throws InvalidArgumentException
	 * @throws \pocketmine\block\utils\InvalidBlockStateException
	 */
	public function blocks(int $palette = 0): Generator
	{
		/** @var ListTag $paletteList */
		$paletteList = $this->palettes->get($palette);
		$blockPalette = $this->paletteToBlocks($paletteList);
		/** @var CompoundTag $blockTag */
		foreach ($this->blocks as $blockTag) {
			/** @var ListTag<IntTag> $pos */
			$pos = $blockTag->getListTag("pos");
			$block = $blockPalette[$blockTag->getInt('state')];
			[$block->getPos()->x, $block->getPos()->y, $block->getPos()->z] = [$pos->get(0)->getValue(), $pos->get(1)->getValue(), $pos->get(2)->getValue()];
			yield $block;
		}
	}
//
//	/**
//	 * setBlocks sets a generator of blocks to a schematic, using a bounding box to calculate the size.
//	 *
//	 * @param            $bb AxisAlignedBB
//	 * @param Generator $blocks
//	 */
//	public function setBlocks(AxisAlignedBB $bb, Generator $blocks): void
//	{
//		/** @var Block $block */
//		$offset = new Vector3((int)$bb->minX, (int)$bb->minY, (int)$bb->minZ);
//		$max = new Vector3((int)$bb->maxX, (int)$bb->maxY, (int)$bb->maxZ);
//
//		$this->width = $max->x - $offset->x + 1;
//		$this->length = $max->z - $offset->z + 1;
//		$this->height = $max->y - $offset->y + 1;
//
//		foreach ($blocks as $block) {
//			$pos = $block->getPos()->subtractVector($offset);
//			$index = $this->blockIndex($pos->x, $pos->y, $pos->z);
//			if (strlen($this->blocks) <= $index) {
//				$this->blocks .= str_repeat(chr(0), $index - strlen($this->blocks) + 1);
//			}
//			$this->blocks[$index] = chr($block->getId());
//			$this->data[$index] = chr($block->getMeta());
//		}
//	}
//
//	/**
//	 * setBlockArray sets a block array to a schematic. The bounds of the schematic are calculated manually.
//	 *
//	 * @param Block[] $blocks
//	 */
//	public function setBlockArray(array $blocks): void
//	{
//		$min = new Vector3(0, 0, 0);
//		$max = new Vector3(0, 0, 0);
//		foreach ($blocks as $block) {
//			if ($block->getPos()->x < $min->x) {
//				$min->x = $block->getPos()->x;
//			} else if ($block->getPos()->x > $max->x) {
//				$max->x = $block->getPos()->x;
//			}
//			if ($block->getPos()->y < $min->y) {
//				$min->y = $block->getPos()->y;
//			} else if ($block->getPos()->y > $max->y) {
//				$max->y = $block->getPos()->y;
//			}
//			if ($block->getPos()->z < $min->z) {
//				$min->z = $block->getPos()->z;
//			} else if ($block->getPos()->z > $max->z) {
//				$max->z = $block->getPos()->z;
//			}
//		}
//		$this->height = $max->y - $min->y + 1;
//		$this->width = $max->x - $min->x + 1;
//		$this->length = $max->z - $min->z + 1;
//
//		foreach ($blocks as $block) {
//			$pos = $block->getPos()->subtractVector($min);
//			$index = $this->blockIndex($pos->x, $pos->y, $pos->z);
//			if (strlen($this->blocks) <= $index) {
//				$this->blocks .= str_repeat(chr(0), $index - strlen($this->blocks) + 1);
//			}
//			$this->blocks[$index] = chr($block->getId());
//			$this->data[$index] = chr($block->getMeta());
//		}
//	}

	/**
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 *
	 * @return int
	 */
	protected function blockIndex(int $x, int $y, int $z): int
	{
		return ($y * $this->size->getZ() + $z) * $this->size->getX() + $x;
	}
}
