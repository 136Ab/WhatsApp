<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $contact_id = (int)$_POST['contact_id'];
        $user_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare("SELECT id FROM chats WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
        $stmt->execute([$user_id, $contact_id, $contact_id, $user_id]);
        $chat = $stmt->fetch();

        if ($chat) {
            echo json_encode(['chat_id' => $chat['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO chats (user1_id, user2_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $contact_id]);
            $chat_id = $pdo->lastInsertId();
            echo json_encode(['chat_id' => $chat_id]);
        }
    } catch (PDOException $e) {
        error_log("Start chat error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error']);
    }
}
?>
