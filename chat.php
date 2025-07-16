<?php
require_once 'config.php';

try {
    if (!isset($_SESSION['user_id'])) {
        error_log("chat.php: No user_id in session, redirecting to index.php");
        echo "<script>window.location.href='index.php';</script>";
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    error_log("chat.php: Logged in as user_id $user_id");

    // Query 1: Fetch contacts
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id != ? ORDER BY username");
    $stmt->execute([$user_id]);
    $contacts = $stmt->fetchAll();
    error_log("chat.php: Fetched " . count($contacts) . " contacts");

    // Query 2: Fetch chats
    $stmt = $pdo->prepare("
        SELECT c.id, c.user1_id, c.user2_id, u.username, m.content AS last_message,
               COALESCE(m.created_at, c.created_at) AS last_message_time
        FROM chats c
        JOIN users u ON u.id = CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END
        LEFT JOIN messages m ON c.last_message_id = m.id
        WHERE c.user1_id = ? OR c.user2_id = ?
        ORDER BY COALESCE(m.created_at, c.created_at) DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $chat_list = $stmt->fetchAll();
    error_log("chat.php: Fetched " . count($chat_list) . " chats");

    // Query 3: Fetch messages for selected chat
    $messages = [];
    $selected_chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
    if ($selected_chat_id) {
        // Query 4: Validate chat_id
        $stmt = $pdo->prepare("SELECT id FROM chats WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
        $stmt->execute([$selected_chat_id, $user_id, $user_id]);
        if ($stmt->rowCount() === 0) {
            error_log("chat.php: Invalid chat_id $selected_chat_id for user $user_id");
            $selected_chat_id = 0;
        } else {
            $stmt = $pdo->prepare("
                SELECT m.id, m.chat_id, m.sender_id, m.content, m.is_read, m.created_at, u.username
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.chat_id = ?
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$selected_chat_id]);
            $messages = $stmt->fetchAll();
            error_log("chat.php: Fetched " . count($messages) . " messages for chat_id $selected_chat_id");

            // Query 5: Mark messages as read
            $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE chat_id = ? AND sender_id != ?");
            $stmt->execute([$selected_chat_id, $user_id]);
            error_log("chat.php: Marked messages as read for chat_id $selected_chat_id");
        }
    }
} catch (PDOException $e) {
    error_log("chat.php: Database error: " . $e->getMessage());
    die("Error loading chats. Please try again later.");
} catch (Exception $e) {
    error_log("chat.php: General error: " . $e->getMessage());
    die("An unexpected error occurred. Please try again.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Chat App</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            background: #f0f2f5;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        .container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            height: 100%;
        }
        .sidebar {
            width: 300px;
            background: white;
            border-right: 1px solid #ddd;
            padding: 1rem;
            overflow-y: auto;
        }
        .sidebar h2 {
            margin: 0 0 1rem;
            color: #333;
            font-weight: 600;
        }
        .chat-list {
            list-style: none;
            padding: 0;
        }
        .chat-list li {
            padding: 0.8rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        .chat-list li:hover {
            background: #f5f5f5;
        }
        .chat-list li.active {
            background: #e6f0ff;
        }
        .chat-list li .username {
            font-weight: bold;
            color: #333;
        }
        .chat-list li .last-message {
            color: #777;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }
        .chat-header {
            padding: 1rem;
            border-bottom: 1px solid #ddd;
            background: #6b48ff;
            color: white;
            font-weight: 500;
        }
        .chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVQYV2NgAAIAAAUAAarVyFEAAAAASUVORK5CYII=') repeat;
        }
        .message {
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
        }
        .message.sent {
            align-items: flex-end;
        }
        .message.received {
            align-items: flex-start;
        }
        .message .content {
            max-width: 60%;
            padding: 0.8rem;
            border-radius: 10px;
            font-size: 1rem;
        }
        .message.sent .content {
            background: #6b48ff;
            color: white;
        }
        .message.received .content {
            background: #e5e5ea;
            color: #333;
        }
        .message .meta {
            font-size: 0.8rem;
            color: #777;
            margin-top: 0.2rem;
        }
        .message.sent .meta {
            text-align: right;
        }
        .chat-input {
            padding: 1rem;
            border-top: 1px solid #ddd;
            display: flex;
        }
        .chat-input input {
            flex: 1;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        .chat-input button {
            padding: 0.8rem 1rem;
            background: #6b48ff;
            border: none;
            border-radius: 8px;
            color: white;
            margin-left: 0.5rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        .chat-input button:hover {
            background: #5a3ad4;
        }
        .logout {
            text-align: center;
            padding: 1rem;
        }
        .logout a {
            color: #6b48ff;
            text-decoration: none;
            font-weight: bold;
        }
        .logout a:hover {
            text-decoration: underline;
        }
        @media (max-width: 800px) {
            .container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                max-height: 30vh;
            }
            .chat-area {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Chats</h2>
            <?php if (empty($chat_list)): ?>
                <p>No chats available. Start a new chat below.</p>
            <?php else: ?>
                <ul class="chat-list">
                    <?php foreach ($chat_list as $chat): ?>
                        <li class="<?= $selected_chat_id === $chat['id'] ? 'active' : '' ?>" 
                            onclick="window.location.href='chat.php?chat_id=<?= htmlspecialchars($chat['id']) ?>'">
                            <div class="username"><?= htmlspecialchars($chat['username']) ?></div>
                            <div class="last-message"><?= htmlspecialchars($chat['last_message'] ?? 'No messages yet') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <h2>Contacts</h2>
            <ul class="chat-list">
                <?php foreach ($contacts as $contact): ?>
                    <?php
                    $chat_exists = false;
                    foreach ($chat_list as $chat) {
                        if ($chat['user1_id'] == $contact['id'] || $chat['user2_id'] == $contact['id']) {
                            $chat_exists = true;
                            break;
                        }
                    }
                    if (!$chat_exists): ?>
                        <li onclick="startChat(<?= htmlspecialchars($contact['id']) ?>)">
                            <div class="username"><?= htmlspecialchars($contact['username']) ?></div>
                            <div class="last-message">Start a new chat</div>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div class="logout">
                <a href="logout.php">Logout</a>
            </div>
        </div>
        <div class="chat-area">
            <?php if ($selected_chat_id): ?>
                <div class="chat-header">
                    <h3>Chat with <?= htmlspecialchars($chat_list[array_search($selected_chat_id, array_column($chat_list, 'id'))]['username'] ?? 'Unknown') ?></h3>
                </div>
                <div class="chat-messages" id="chat-messages">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?= $message['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                            <div class="content"><?= htmlspecialchars($message['content']) ?></div>
                            <div class="meta">
                                <?= date('h:i A', strtotime($message['created_at'])) ?>
                                <?php if ($message['sender_id'] == $user_id): ?>
                                    <?= $message['is_read'] ? '✓✓' : '✓' ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="chat-input">
                    <input type="text" id="message-input" placeholder="Type a message...">
                    <button onclick="sendMessage(<?= htmlspecialchars($selected_chat_id) ?>)">Send</button>
                </div>
            <?php else: ?>
                <div class="chat-header">
                    <h3>Select a chat to start messaging</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="chat.js"></script>
</body>
</html>
