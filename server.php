<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
require dirname(__DIR__) . '/vendor/autoload.php';
require_once 'config.php';
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class Chat implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        global $pdo;
        try {
            $data = json_decode($msg, true);

            if ($data['type'] === 'message') {
                $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, content) VALUES (?, ?, ?)");
                $stmt->execute([$data['chat_id'], $data['sender_id'], $data['content']]);
                $message_id = $pdo->lastInsertId();

                $pdo->prepare("UPDATE chats SET last_message_id = ? WHERE id = ?")
                    ->execute([$message_id, $data['chat_id']]);

                foreach ($this->clients as $client) {
                    $client->send(json_encode($data));
                }
            }
        } catch (PDOException $e) {
            error_log("WebSocket message error: " . $e->getMessage());
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        error_log("WebSocket error: " . $e->getMessage());
        $conn->close();
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    8080
);
$server->run();
?>
