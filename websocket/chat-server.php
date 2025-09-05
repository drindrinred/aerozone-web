<?php
require_once '../vendor/autoload.php';
require_once '../config/database.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatServer implements MessageComponentInterface {
    protected $clients;
    protected $userConnections;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data) return;

        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
            case 'message':
                $this->handleMessage($from, $data);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                break;
            }
        }
        
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function handleAuth($conn, $data) {
        $userId = $data['user_id'] ?? null;
        if ($userId) {
            $this->userConnections[$userId] = $conn;
            echo "User {$userId} authenticated\n";
        }
    }

    protected function handleMessage($conn, $data) {
        $senderId = $data['sender_id'] ?? null;
        $receiverId = $data['receiver_id'] ?? null;
        $message = $data['message'] ?? null;

        if (!$senderId || !$receiverId || !$message) return;

        // Send message to receiver if online
        if (isset($this->userConnections[$receiverId])) {
            $receiverConn = $this->userConnections[$receiverId];
            $receiverConn->send(json_encode([
                'type' => 'new_message',
                'message' => [
                    'sender_id' => $senderId,
                    'receiver_id' => $receiverId,
                    'message' => $message,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]));
        }
        
        // Send confirmation to sender
        $conn->send(json_encode([
            'type' => 'message_sent',
            'success' => true
        ]));
    }
}

// Create WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    8080
);

echo "Chat server started on port 8080\n";
$server->run();
?>
