<?php
namespace PocketDockConsole;

use pocketmine\utils\TextFormat;
use pocketmine\utils\Terminal;

ini_set('display_errors', 1);
error_reporting(E_ALL);

class PDCServer extends \pocketmine\Thread {

    public $null = NULL;
    public $buffer = "";
    public $stuffToSend = "";
    public $jsonStream = "";
    public $stuffTitle = "";
    public $stop = false;

    public function __construct($host, $port, $logger, $loader, $password, $html, $backlog, $legacy = false) {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->logger = $logger;
        $this->loader = $loader;
        $this->data = $html;
        $this->backlog = $backlog;
        $this->clienttokill = "";
        $this->sendUpate = false;;
        $this->legacy = $legacy;
        $oldloadPaths = array();
        $this->addDependency($oldloadPaths, new \ReflectionClass($logger));
        $this->addDependency($oldloadPaths, new \ReflectionClass($loader));
        $this->loadPaths = array_reverse($oldloadPaths);
        $this->start(PTHREADS_INHERIT_ALL & ~PTHREADS_INHERIT_CLASSES);
        $this->log("Started SocksServer on " . $this->host . ":" . $this->port);
    }

    protected function addDependency(array & $loadPaths, \ReflectionClass $dep) {
        if ($dep->getFileName() !== false) {
            $loadPaths[$dep->getName() ] = $dep->getFileName();
        }

        if ($dep->getParentClass() instanceof \ReflectionClass) {
            $this->addDependency($loadPaths, $dep->getParentClass());
        }

        foreach ($dep->getInterfaces() as $interface) {
            $this->addDependency($loadPaths, $interface);
        }
    }

    public function getBuffer() {
        return $this->buffer;
    }

    public function run() {
        set_exception_handler(function ($ex) {
            //var_dump($ex);
            $this->logger->debug($ex->getMessage());
        });

        foreach ($this->loadPaths as $name => $path) {
            if (!class_exists($name, false) and !interface_exists($name, false)) {
                require ($path);
            }
        }
        $this->loader->register(true);

        if (!$this->legacy) {
            Terminal::init();
        }

        $server = new \Wrench\Server('ws://' . $this->host . ':' . $this->port, array("logger" => function ($msg, $pri) {
        }), $this);

        $server->registerApplication("app", new PDCApp($this, $this->password));
        $server->addListener(\Wrench\Server::EVENT_SOCKET_CONNECT, function ($data, $other) {
            $header = $other->getSocket()->receive();
            if ($this->isHTTP($header)) {
                $other->getSocket()->send("HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n" . $this->data);
                $other->close(200);
            } else {
                $other->onData($header);
            }
        });

        while($this->stop !== true) {
            try {
                $server->run();
            } catch (\Exception $e) {

            }
        }
    }

    public function isHTTP($data) {
        if (strpos($data, "websocket")) {
            return false;
        } else {
            return true;
        }
    }

    public function stop() {
        $this->stop = true;
    }

    public function log($data) {
        $this->logger->info("[PDC] " . $data);
    }

}
