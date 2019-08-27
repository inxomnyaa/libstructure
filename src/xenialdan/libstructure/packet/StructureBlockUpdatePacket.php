<?php

declare(strict_types=1);

namespace xenialdan\libstructure\packet;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\Server;

class StructureBlockUpdatePacket extends \pocketmine\network\mcpe\protocol\StructureBlockUpdatePacket
{
    /** @var int */
    public $x;
    /** @var int */
    public $y;
    /** @var int */
    public $z;
    /** @var StructureEditorData */
    public $structureEditorData;
    /** @var bool */
    public $unknownBool;//could be showInvisibleBlocks
    /** @var bool */
    public $unknownBool2;

    protected function decodePayload()
    {
        $this->getBlockPosition($this->x, $this->y, $this->z);
        $this->structureEditorData = $this->getStructureEditorData();
        $this->unknownBool = $this->getBool();
        $this->unknownBool2 = $this->getBool();
    }

    protected function encodePayload()
    {
        $this->putBlockPosition($this->x, $this->y, $this->z);
        $this->putStructureEditorData($this->structureEditorData);
        $this->putBool($this->unknownBool);
        $this->putBool($this->unknownBool2);
    }

    public function handle(NetworkSession $session): bool
    {
        return $session->handleStructureBlockUpdate($this);
    }

    private function getStructureEditorData(): StructureEditorData
    {
        $result = new StructureEditorData();

        $result->structureName = $this->getString();
        $result->string2 = $this->getString();//probably load/fileName in load mode
        $result->includePlayers = $this->getBool();
        $result->showBoundingBox = $this->getBool();
        $result->mode = $this->getVarInt();
        $result->structureSettings = $this->getStructureSettings();
        //TODO 1.13 will probably add showInvisibleBlocks (bool)

        return $result;
    }

    private function getStructureSettings(): StructureSettings
    {
        $result = new StructureSettings();

        $result->paletteName = $this->getString();
        $result->ignoreEntities = $this->getBool();
        $result->ignoreBlocks = $this->getBool();
        $this->getBlockPosition($result->sizeX, $result->sizeY, $result->sizeZ);//structure size
        $this->getBlockPosition($result->offsetX, $result->offsetY, $result->offsetZ);//structure offset
        $result->lastTouchedByPlayerId = $this->getLong();
        $result->rotation = $this->getByte();
        $result->mirror = $this->getByte();
        if (version_compare(ltrim(Server::getInstance()->getVersion(), 'v'), "1.13") === 0) {
            $result->integrityValue = $this->getFloat();
            //$result->integritySeed = $this->getUnsignedVarInt();//actually UnsignedInt
            $result->integritySeed = intval($this->getRemaining());//hack
        }

        return $result;
    }

    private function putStructureEditorData(StructureEditorData $data): void
    {
        $this->putString($data->structureName);
        $this->putString($data->string2);//probably load/fileName in load mode
        $this->putBool($data->includePlayers);
        $this->putBool($data->showBoundingBox);
        $this->putVarInt($data->mode);
        $this->putStructureSettings($data->structureSettings);
        //TODO 1.13 will probably add showInvisibleBlocks (bool)
    }

    private function putStructureSettings(StructureSettings $settings): void
    {
        $this->putString($settings->paletteName);
        $this->putBool($settings->ignoreEntities);
        $this->putBool($settings->ignoreBlocks);
        $this->putBlockPosition($settings->sizeX, $settings->sizeY, $settings->sizeZ);//structure size
        $this->putBlockPosition($settings->offsetX, $settings->offsetY, $settings->offsetZ);//structure offset
        $this->putLong($settings->lastTouchedByPlayerId);
        $this->putByte($settings->rotation);
        $this->putByte($settings->mirror);
        if (version_compare(ltrim(Server::getInstance()->getVersion(), 'v'), "1.13") === 0) {
            $this->putFloat($settings->integrityValue);
            $this->putInt($settings->integritySeed);//hack//actually UnsignedInt
        }
    }
}