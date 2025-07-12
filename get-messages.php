
<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDBConnection();

// Set proper headers for JSON response
header('Content-Type: application/json');

// Create messages table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            message_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    echo json_encode(['error' => 'Database setup error']);
    exit;
}

if (isset($_GET['chat_with']) && isset($_GET['last_id'])) {
    $chat_with = $_GET['chat_with'];
    $last_id = (int)$_GET['last_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, u.first_name, u.last_name, u.profile_image 
            FROM messages m 
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
            AND m.id > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([(int)$user['id'], (int)$chat_with, (int)$chat_with, (int)$user['id'], $last_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($messages);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error fetching messages']);
    }
} else {
    echo json_encode([]);
}
?>
