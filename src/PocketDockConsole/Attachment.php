<?php
namespace PocketDockConsole;

use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class Attachment extends \ThreadedLoggerAttachment implements \LoggerAttachment {

    public function __construct($thread) {
        $this->stream = "";
        $this->thread = $thread;
    }

    public function log($level, $message) {
        $this->stream.= $message . "\r\n";
        $this->thread->stuffToSend = $this->stream;
    }

}
