<?php
namespace PocketDockConsole;

use pocketmine\utils\TextFormat;

class PDCApp extends \Wrench\Application\Application {

    protected $clients = array();
    protected $lastTimestamp = null;
    protected $autharray = array();
    protected $connectedips;

    public function __construct($thread, $password) {
        $this->thread = $thread;
        $this->password = $password;
    }
    /**
     * @see Wrench\Application.Application::onConnect()
     */
    public function onConnect($client) {
        $this->thread->log(TextFormat::AQUA . "Connection from: " . $client->getIp());
        $client->send(TextFormat::toANSI(TextFormat::AQUA . "[PocketDockConsole] " . json_encode(array('info' => $client->getIp() . ' connected')) . "\r\n"));
        $client->send(TextFormat::toANSI(TextFormat::YELLOW . "[PocketDockConsole] Please authenticate " . $client->getIp() . ". Type your password and press enter.\r\n"));
        $this->thread->connectedips.= $client->getIp() . ";";
        $this->clients[] = $client;
    }
    /**
     * @see Wrench\Application.Application::onUpdate()
     */
    public function onUpdate() {
        $this->lastTimestamp = time();
        foreach ($this->autharray as $sendto) {
            $stuffArray = explode("\n", $this->thread->stuffToSend);
            if (count($stuffArray) == $this->thread->lastLine) {
            } else {
                for ($i = $this->thread->lastLine - 1;$i <= count($stuffArray);$i++) {
                    if (isset($stuffArray[$i])) {
                        $line = trim($stuffArray[$i]) . "\r\n";
                        if ($line === "\r\n") {
                        } else {
                            $sendto->send($line);
                        }
                    }
                }
                //$this->thread->lastLine = count($stuffArray);
                $this->thread->lastLine = 1;
                $this->thread->stuffToSend = "";
                $this->thread->clearstream = true;
                $sendto->send($this->thread->stuffTitle);
            }
            $jsonArray = explode("\n", $this->thread->jsonStream);
            if (count($jsonArray) == $this->thread->lastLineJSON) {
            } else {
                for ($i = $this->thread->lastLineJSON - 1;$i <= count($jsonArray);$i++) {
                    if (isset($jsonArray[$i])) {
                        $line = trim($jsonArray[$i]) . "\r\n";
                        if ($line === "\r\n") {
                        } else {
                            $sendto->send($line);
                        }
                    }
                }
                //$this->thread->lastLineJSON = count($jsonArray);
                $this->thread->lastLineJSON = 1;
                $this->thread->jsonStream = "";
                $sendto->send($this->thread->stuffTitle);
            }
        }
        if ($this->thread->clienttokill !== "") {
            foreach ($this->clients as $authed) {
                $ip = $authed->getIp();
                if ($ip == $this->thread->clienttokill) {
                    $iparray = explode(";", $this->thread->connectedips);
                    unset($iparray[array_search($ip, $iparray) ]);
                    $this->thread->connectedips = implode(";", $iparray);
                    $authed->close();
                    unset($this->autharray[array_search($authed, $this->autharray) ]);
                    unset($this->clients[array_search($authed, $this->clients) ]);
                    $this->thread->clienttokill = "";
                }
            }
        }
    }
    /**
     * Handle data received from a client
     *
     * @param Payload    $payload A payload object, that supports __toString()
     * @param Connection $connection
     */
    public function onData($payload, $connection) {
        $payloado = $payload;
        $payload = trim($payload);
        if (in_array($connection, $this->autharray)) {
            $this->thread->buffer.= $payloado;
            if (stripos($payloado, "json") != - 1) {

            } else {
                $connection->send($payload . "\r\n");
            }
        } elseif ($this->tryAuth($payload)) {
            $this->autharray[] = $connection;
            $connection->send(TextFormat::toANSI(TextFormat::DARK_GREEN . "[PocketDockConsole] Authenticated! Now accepting commands\r\n"));
            $this->thread->log(TextFormat::DARK_GREEN . "Successful login from: " . $connection->getIp() . "!");
            $stuffArray = explode("\n", $this->thread->stuffToSend);
            $stuffArray = array_reverse($stuffArray);
            for ($i = $this->thread->backlog;$i >= 0;$i--) {
                if (isset($stuffArray[$i])) {
                    $line = trim($stuffArray[$i]) . "\r\n";
                    if ($line === "\r\n") {

                    } else {
                        $connection->send($line);
                    }
                }
            }
            $connection->send($this->thread->stuffTitle);
        } else {
            $connection->send(TextFormat::toANSI(TextFormat::DARK_RED . "[PocketDockConsole] Failed login attempt, this event will be recorded!\r\n"));
            $this->thread->log(TextFormat::DARK_RED . "Failed login attempt from: " . $connection->getIp() . "!");
        }
    }

    public function tryAuth($password) {
        if ($password == $this->password) {
            $this->thread->sendUpdate = true;
            return true;
        } else {
            return false;
        }
    }

    public function onDisconnect($conn) {
        $ip = $conn->getIp();
        $iparray = explode(";", $this->thread->connectedips);
        unset($iparray[array_search($ip, $iparray) ]);
        $this->thread->connectedips = implode(";", $iparray);
        unset($this->autharray[array_search($conn, $this->autharray) ]);
        unset($this->clients[array_search($conn, $this->clients) ]);
    }
}
