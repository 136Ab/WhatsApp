<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (isset($_GET['chat_id'])) {
    $chat_id = (int)$_GET['chat_id'];
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT m.*, u.username 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.chat_id = ? 
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$chat_id]);
        $messages = $stmt->fetchAll();

        $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE chat_id = ? AND sender_id != ?")
            ->execute([$chat_id, $user_id]);

        echo json_encode($messages);
    } catch (PDOException $e) {
        error_log("Get messages error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error']);
    }
} else {
    echo json_encode(['error' => 'Invalid chat ID']);
}
?>
