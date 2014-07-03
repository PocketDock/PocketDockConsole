<?php

namespace PocketDockConsole;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\scheduler\PluginTask;

class Main extends PluginBase {

    public function onLoad() {
        $this->getLogger()->info(TextFormat::WHITE . "Loaded");
    }

    public function onEnable() {
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->getLogger()->info(TextFormat::DARK_GREEN . "Enabled");
        $this->thread = new SocksServer("0.0.0.0", $this->getConfig()->get("port"), $this->getServer()->getLogger(), $this->getServer()->getLoader(), $this->getConfig()->get("password"), stream_get_contents($this->getResource("PluginIndex.html")));
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new RunCommand($this), 1);
        $this->lastBufferLine = "";
        $attachment = new Attachment($this->thread);
        $this->getServer()->getLogger()->addAttachment($attachment);
    }

    public function onDisable() {
        $this->getLogger()->info(TextFormat::DARK_RED . "Disabled");
        $this->thread->stop();
    }

}
