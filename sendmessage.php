<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chat_id = (int)$_POST['chat_id'];
    $content = trim($_POST['content']);
    $user_id = $_SESSION['user_id'];

    if ($content && $chat_id) {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$chat_id, $user_id, $content]);
            $message_id = $pdo->lastInsertId();

            $pdo->prepare("UPDATE chats SET last_message_id = ? WHERE id = ?")
                ->execute([$message_id, $chat_id]);

            echo json_encode(['success' => true, 'message_id' => $message_id]);
        } catch (PDOException $e) {
            error_log("Send message error: " . $e->getMessage());
            echo json_encode(['error' => 'Database error']);
        }
    } else {
        echo json_encode(['error' => 'Invalid input']);
    }
}
?>
