<?php
namespace PocketDockConsole;

use pocketmine\utils\Terminal;

class Attachment extends \ThreadedLoggerAttachment implements \LoggerAttachment {

    public function __construct($thread) {
        $this->stream = "";
        $this->thread = $thread;
    }

    public function log($level, $message) {
        $this->stream.= Terminal::toANSI($message) . "\r\n";
        $this->thread->stuffToSend = $this->stream;
    }

}
