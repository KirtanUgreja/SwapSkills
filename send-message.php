<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['receiver_id']) && isset($input['message'])) {
        $receiver_id = $input['receiver_id'];
        $message_text = trim($input['message']);
        
        if (!empty($message_text)) {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $receiver_id, $message_text]);
            
            // Get receiver info for email notification
            $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
            $stmt->execute([$receiver_id]);
            $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($receiver) {
                // Send chat notification email
                require_once 'email-notifications.php';
                sendChatNotification($receiver['email'], $user['first_name'], $receiver['first_name']);
            }
            
            // Get the inserted message with user details
            $message_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("
                SELECT m.*, u.first_name, u.last_name, u.profile_image 
                FROM messages m 
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.id = ?
            ");
            $stmt->execute([$message_id]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            exit;
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Invalid data']);
?>