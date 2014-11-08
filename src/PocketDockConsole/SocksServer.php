<?php
namespace PocketDockConsole;

use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class SocksServer extends \Thread {

    public $null = NULL;
    public $buffer = "";
    public $stuffToSend = "";
    public $jsonStream = "";
    public $stuffTitle = "";
    public $loadPaths = array();
    public $connectedips = "";

    public function __construct($host, $port, $logger, $loader, $password, $html, $backlog) {
        $this->host = $host;
        $this->port = $port;
        $this->stop = false;
        $this->password = $password;
        $this->logger = $logger;
        $this->loader = $loader;
        $this->data = $html;
        $this->backlog = $backlog;
        $this->clienttokill = "";
        $this->sendUpate = false;
        $loadPaths = array();
        $this->addDependency($loadPaths, new \ReflectionClass($logger));
        $this->addDependency($loadPaths, new \ReflectionClass($loader));
        $this->loadPaths = array_reverse($loadPaths);
        $this->start(PTHREADS_INHERIT_ALL & ~PTHREADS_INHERIT_CLASSES);
        $this->log("Started SocksServer on " . $this->host . ":" . $this->port);
        if ($this->password === "PocketDockRules!") {
            $this->log("You are using the default password! Please change the password in config.yml");
        }
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

    public function stop() {
        $this->stop = true;
    }

    public function getBuffer() {
        return $this->buffer;
    }

    public function run() {
        $socket = stream_socket_server("tcp://$this->host:$this->port", $errno, $errorMessage);
        $this->socket = $socket;
        foreach ($this->loadPaths as $name => $path) {
            if (!class_exists($name, false) and !class_exists($name, false)) {
                require ($path);
            }
        }
        $this->loader->register();
        $clients = array($this->socket);
        $autharray = array();
        while ($this->stop === false) {
            $changed = $clients;
            stream_select($changed, $this->null, $this->null, 0);

            foreach ($changed as $c_sock) {
                if ($c_sock == $this->socket) {
                    $socket_new = stream_socket_accept($this->socket);
                    $ip = stream_socket_get_name($socket_new, true);
                    $this->log(TextFormat::toANSI(TextFormat::AQUA . "Connection from: $ip"));
                    $header = fread($socket_new, 10240);

                    if ($this->isHTTP($header)) {
                        fwrite($socket_new, "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n" . $this->data);
                        fclose($socket_new);
                    } else {
                        $this->startConn($header, $socket_new, $this->host, $this->port);
                        $response = $this->encode(TextFormat::toANSI(TextFormat::AQUA . "[PocketDockConsole] " . json_encode(array('info' => $ip . ' connected')) . "\r\n"));
                        $this->sendSingle($response, $socket_new);
                        $this->sendSingle($this->encode(TextFormat::toANSI(TextFormat::YELLOW . "[PocketDockConsole] Please authenticate $ip. Type your password and press enter.\r\n")), $socket_new);
                        array_push($clients, $socket_new);
                        $this->connectedips.= stream_socket_get_name($socket_new, true) . ";";
                    }
                } elseif (in_array($c_sock, $autharray)) {
                    $sock_data = fread($c_sock, 2048);
                    if ($sock_data == "") {
                        $iparray = explode(";", $this->connectedips);
                        unset($iparray[array_search(stream_socket_get_name($c_sock, true), $iparray) ]);
                        $this->connectedips = implode(";", $iparray);
                        fclose($c_sock);
                        unset($autharray[array_search($c_sock, $autharray) ]);
                        unset($clients[array_search($c_sock, $clients) ]);
                    }
                    $data = $this->decode($sock_data);
                    $this->buffer = $this->buffer . $data;
                    if (substr($this->buffer, -1) == "\r") {
                        $this->send($this->encode(trim($this->buffer) . "\r\n"), $autharray);
                    }
                } elseif ($c_sock) {
                    $sock_data = fread($c_sock, 2048);
                    if ($sock_data == "") {
                        $iparray = explode(";", $this->connectedips);
                        unset($iparray[array_search(stream_socket_get_name($c_sock, true), $iparray) ]);
                        $this->connectedips = implode(";", $iparray);
                        fclose($c_sock);
                        unset($autharray[array_search($c_sock, $autharray) ]);
                        unset($clients[array_search($c_sock, $clients) ]);
                    }
                    $data = $this->decode($sock_data);
                    if (strpos($data, "\r")) {
                        if ($this->tryAuth($c_sock, trim($data))) {
                            array_push($autharray, $c_sock);
                            $this->sendSingle($this->encode(TextFormat::toANSI(TextFormat::DARK_GREEN . "[PocketDockConsole] Authenticated! Now accepting commands\r\n")), $c_sock);
                            $stuffArray = explode("\n", $this->stuffToSend);
                            $stuffArray = array_reverse($stuffArray);
                            for ($i = $this->backlog;$i >= 0;$i--) {
                                if (isset($stuffArray[$i])) {
                                    $line = trim($stuffArray[$i]) . "\r\n";
                                    if ($line === "\r\n") {

                                    } else {
                                        $this->sendSingle($this->encode($line), $c_sock);
                                    }
                                }
                            }
                            $this->log(TextFormat::toANSI(TextFormat::DARK_GREEN . "Successful login from: $ip!"));
                            $this->send($this->encode($this->stuffTitle), $autharray);
                        } else {
                            $this->sendSingle($this->encode(TextFormat::toANSI(TextFormat::DARK_RED . "[PocketDockConsole] Failed login attempt, this event will be recorded!\r\n")), $c_sock);
                            $this->log(TextFormat::DARK_RED . "Failed login attempt from: $ip!");
                        }
                    }
                }
            }
            $stuffArray = explode("\n", $this->stuffToSend);
            if (count($stuffArray) == $this->lastLine) {
            } else {
                for ($i = $this->lastLine - 1;$i <= count($stuffArray);$i++) {
                    if (isset($stuffArray[$i])) {
                        $line = trim($stuffArray[$i]) . "\r\n";
                        if ($line === "\r\n") {

                        } else {
                            $this->send($this->encode($line), $autharray);
                        }
                    }
                }
                $this->lastLine = count($stuffArray);
                $this->send($this->encode($this->stuffTitle), $autharray);
            }
            $jsonArray = explode("\n", $this->jsonStream);
            if (count($jsonArray) == $this->lastLineJSON) {
            } else {
                for ($i = $this->lastLineJSON - 1;$i <= count($jsonArray);$i++) {
                    if (isset($jsonArray[$i])) {
                        $line = trim($jsonArray[$i]) . "\r\n";
                        if ($line === "\r\n") {

                        } else {
                            $this->send($this->encode($line), $autharray);
                        }
                    }
                }
                $this->lastLineJSON = count($jsonArray);
                $this->send($this->encode($this->stuffTitle), $autharray);
            }
            if ($this->clienttokill !== "") {
                foreach ($clients as $authed) {
                    $ip = stream_socket_get_name($authed, true);
                    if ($ip == $this->clienttokill) {
                        $iparray = explode(";", $this->connectedips);
                        unset($iparray[array_search($ip, $iparray) ]);
                        $this->connectedips = implode(";", $iparray);
                        fclose($authed);
                        unset($autharray[array_search($authed, $autharray) ]);
                        unset($clients[array_search($authed, $clients) ]);
                        $this->clienttokill = "";
                    }
                }
            }
            usleep(5000);
        }
        fclose($this->socket);
        exit(0);
    }

    public function getStr($str) {
        return $str;
    }

    public function tryAuth($socket, $password) {
        if ($password === $this->password) {
            $this->sendUpdate = true;
            return true;
        } else {
            return false;
        }
    }

    public function isHTTP($data) {
        if (strpos($data, "websocket")) {
            return false;
        } else {
            return true;
        }
    }

    public function send($msg, $clients) {
        foreach ($clients as $changed_socket) {
            fwrite($changed_socket, $msg);
        }
        return true;
    }

    public function sendSingle($msg, $socket) {
        fwrite($socket, $msg);
        return true;
    }

    public function decode($text) {
        if (isset($text[1])) {
            $length = ord($text[1]) & 127;
            if ($length == 126) {
                $encodes = substr($text, 4, 4);
                $data = substr($text, 8);
            } elseif ($length == 127) {
                $encodes = substr($text, 10, 4);
                $data = substr($text, 14);
            } else {
                $encodes = substr($text, 2, 4);
                $data = substr($text, 6);
            }
            $text = "";
            for ($i = 0;$i < strlen($data);++$i) {
                $text.= $data[$i] ^ $encodes[$i % 4];
            }
            return $text;
        } else {
            return false;
        }
    }

    public function encode($text) {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);
        if ($length <= 125) $header = pack('CC', $b1, $length);
        elseif ($length > 125 && $length < 65536) $header = pack('CCn', $b1, 126, $length);
        elseif ($length >= 65536) $header = pack('CCNN', $b1, 127, $length);
        return $header . $text;
    }

    public function startConn($receved_header, $client_conn, $host, $port) {
        $headers = array();
        $lines = preg_split("/\r\n/", $receved_header);
        foreach ($lines as $line) {
            $line = chop($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" . "Upgrade: websocket\r\n" . "Connection: Upgrade\r\n" . "WebSocket-Origin: *\r\n" . "WebSocket-Location: ws://localhost:9090\r\n" . "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        fwrite($client_conn, $upgrade, strlen($upgrade));
    }

    public function log($data) {
        $this->logger->info("[PDC] " . $data);
    }

}
