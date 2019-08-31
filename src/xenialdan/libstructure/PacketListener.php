<?php

namespace xenialdan\libstructure;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\StructureTemplateDataExportRequestPacket;
use pocketmine\network\mcpe\protocol\StructureTemplateDataExportResponsePacket;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use xenialdan\libstructure\packet\StructureBlockUpdatePacket;

class PacketListener implements Listener
{
    /** @var Plugin|null */
    private static $registrant;

    public static function isRegistered(): bool
    {
        return self::$registrant instanceof Plugin;
    }

    public static function getRegistrant(): Plugin
    {
        return self::$registrant;
    }

    public static function unregister(): void
    {
        self::$registrant = null;
    }

    /**
     * @param Plugin $plugin
     */
    public static function register(Plugin $plugin): void
    {
        if (self::isRegistered()) {
            return;//silent return
        }

        self::$registrant = $plugin;
        $plugin->getServer()->getPluginManager()->registerEvents(new self, $plugin);
        PacketPool::registerPacket(new StructureBlockUpdatePacket());
    }

    public function onDataPacketReceiveEvent(DataPacketReceiveEvent $e)
    {
        if ($e->getPacket() instanceof StructureBlockUpdatePacket) $this->onStructureBlockUpdatePacket($e);
        if ($e->getPacket() instanceof StructureTemplateDataExportRequestPacket) $this->onStructureTemplateDataExportRequestPacket($e);
        if ($e->getPacket() instanceof StructureTemplateDataExportResponsePacket) $this->onStructureTemplateDataExportResponsePacket($e);
    }

    private function onStructureBlockUpdatePacket(DataPacketReceiveEvent $e)
    {
        if (!($pk = $e->getPacket()) instanceof StructureBlockUpdatePacket) throw new \InvalidArgumentException(get_class($pk) . " is not a " . StructureBlockUpdatePacket::class);
        /** @var StructureBlockUpdatePacket $pk */
        var_dump($e->getPacket());
    }

    private function onStructureTemplateDataExportRequestPacket(DataPacketReceiveEvent $e)
    {
    }

    private function onStructureTemplateDataExportResponsePacket(DataPacketReceiveEvent $e)
    {
    }

}