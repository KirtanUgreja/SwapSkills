<?php
require_once 'config.php';
requireAuth();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user = getCurrentUser();
$pdo = getDBConnection();

// Set proper headers for JSON response
header('Content-Type: application/json');

// Log the request for debugging
error_log("Send message request received. Method: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Log raw input for debugging
    error_log("Raw input: " . file_get_contents('php://input'));

    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_msg = 'Invalid JSON data: ' . json_last_error_msg();
        error_log($error_msg);
        echo json_encode([
            'success' => false,
            'error' => $error_msg
        ]);
        exit;
    }

    // Log decoded input
    error_log("Decoded input: " . print_r($input, true));

    if (isset($input['receiver_id']) && isset($input['message'])) {
        $receiver_id = $input['receiver_id'];
        $message_text = trim($input['message']);

        // Validate receiver_id
        if (!is_numeric($receiver_id) || $receiver_id <= 0) {
            $error_msg = 'Invalid receiver ID';
            error_log($error_msg);
            echo json_encode([
                'success' => false,
                'error' => $error_msg
            ]);
            exit;
        }

        // Validate message length
        if (empty($message_text)) {
            $error_msg = 'Message cannot be empty';
            error_log($error_msg);
            echo json_encode([
                'success' => false,
                'error' => $error_msg
            ]);
            exit;
        }

        // Check if receiver exists
        try {
            $stmt = $pdo->prepare("SELECT id, email, first_name FROM users WHERE id = ?");
            $stmt->execute([(int)$receiver_id]);
            $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$receiver) {
                $error_msg = 'Receiver not found';
                error_log($error_msg);
                echo json_encode([
                    'success' => false,
                    'error' => $error_msg
                ]);
                exit;
            }

            // Check if user exists and has valid ID
            if (!isset($user['id']) || !is_numeric($user['id'])) {
                $error_msg = 'Invalid user session';
                error_log($error_msg);
                echo json_encode([
                    'success' => false,
                    'error' => $error_msg
                ]);
                exit;
            }

            // Insert message
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, created_at) VALUES (?, ?, ?, NOW())");
            $result = $stmt->execute([(int)$user['id'], (int)$receiver_id, $message_text]);

            if (!$result) {
                $error_msg = 'Failed to insert message';
                error_log($error_msg);
                echo json_encode([
                    'success' => false,
                    'error' => $error_msg
                ]);
                exit;
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

            if (!$message) {
                $error_msg = 'Failed to retrieve sent message';
                error_log($error_msg);
                echo json_encode([
                    'success' => false,
                    'error' => $error_msg
                ]);
                exit;
            }

            // Send chat notification email (only if file exists)
            try {
                if (file_exists('email-notifications.php')) {
                    require_once 'email-notifications.php';
                    // Check if function exists before calling
                    if (function_exists('sendChatNotification')) {
                        sendChatNotification($receiver['email'], $user['first_name'], $receiver['first_name']);
                    }
                }
            } catch (Exception $e) {
                // Don't fail the message send if email fails
                error_log("Email notification error: " . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            exit;

        } catch (PDOException $e) {
            $error_msg = 'Database error: ' . $e->getMessage();
            error_log("Send message database error: " . $error_msg);
            echo json_encode([
                'success' => false, 
                'error' => 'Database connection error'
            ]);
            exit;
        } catch (Exception $e) {
            $error_msg = 'General error: ' . $e->getMessage();
            error_log("Send message general error: " . $error_msg);
            echo json_encode([
                'success' => false, 
                'error' => 'An unexpected error occurred'
            ]);
            exit;
        }
    } else {
        $missing_fields = [];
        if (!isset($input['receiver_id'])) $missing_fields[] = 'receiver_id';
        if (!isset($input['message'])) $missing_fields[] = 'message';
        
        $error_msg = 'Missing required fields: ' . implode(', ', $missing_fields);
        error_log($error_msg);
        echo json_encode([
            'success' => false, 
            'error' => $error_msg
        ]);
        exit;
    }
} else {
    $error_msg = 'Invalid request method. Expected POST, got ' . $_SERVER['REQUEST_METHOD'];
    error_log($error_msg);
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid request method'
    ]);
    exit;
}
?>