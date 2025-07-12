<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDBConnection();

// Create messages table if it doesn't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id VARCHAR(50) NOT NULL,
        receiver_id VARCHAR(50) NOT NULL,
        message_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

// Fetch users to chat with (exclude current user)
$stmt = $pdo->prepare("SELECT id, username, first_name, last_name, profile_image FROM users WHERE id != ? ORDER BY first_name");
$stmt->execute([$user['id']]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['receiver_id'], $_POST['message_text'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $message_text = sanitizeInput($_POST['message_text']);
        if (!empty($message_text)) {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $_POST['receiver_id'], $message_text]);
            
            // Redirect to prevent form resubmission
            header("Location: chat.php?chat_with=" . $_POST['receiver_id']);
            exit;
        }
    }
}

// Fetch conversation with selected user
$chat_with = $_GET['chat_with'] ?? null;
$messages = [];
$chat_user = null;
if ($chat_with) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username, u.first_name, u.last_name, u.profile_image 
        FROM messages m 
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) 
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$user['id'], $chat_with, $chat_with, $user['id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get chat user details
    $stmt = $pdo->prepare("SELECT username, first_name, last_name, profile_image FROM users WHERE id = ?");
    $stmt->execute([$chat_with]);
    $chat_user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - SwapSkills</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2><i class="fa-solid fa-rotate"></i> SwapSkills</h2>
            </div>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="browse.php" class="nav-link">Browse Skills</a>
                <a href="requests.php" class="nav-link">My Requests</a>
                <a href="chat.php" class="nav-link active">Chat</a>
                <a href="profile.php" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <div class="chat-container">
        <div class="chat-layout">
            <!-- Contacts Sidebar -->
            <div class="contacts-sidebar">
                <div class="contacts-header">
                    <h3><i class="fas fa-users"></i> Contacts</h3>
                </div>
                <div class="contacts-list">
                    <?php foreach ($users as $contact): ?>
                        <a href="chat.php?chat_with=<?= $contact['id'] ?>" 
                          class="contact-item <?= ($chat_with == $contact['id']) ? 'active' : '' ?>">
                            <div class="contact-avatar">
                                <?php if ($contact['profile_image']): ?>
                                    <img src="<?= htmlspecialchars($contact['profile_image']) ?>" 
                                        alt="<?= htmlspecialchars($contact['first_name']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="contact-info">
                                <h4><?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></h4>
                                <p>@<?= htmlspecialchars($contact['username']) ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    
                    <?php if (count($users) == 0): ?>
                        <div class="empty-contacts">
                            <i class="fas fa-user-slash"></i>
                            <p>No contacts available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-area">
                <?php if ($chat_with && $chat_user): ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="chat-user-info">
                            <div class="chat-avatar">
                                <?php if ($chat_user['profile_image']): ?>
                                    <img src="<?= htmlspecialchars($chat_user['profile_image']) ?>" 
                                        alt="<?= htmlspecialchars($chat_user['first_name']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="chat-user-details">
                                <h3><?= htmlspecialchars($chat_user['first_name'] . ' ' . $chat_user['last_name']) ?></h3>
                                <p>@<?= htmlspecialchars($chat_user['username']) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div id="chat-messages" class="chat-messages">
                        <?php 
                        $date_shown = '';
                        foreach ($messages as $message):
                            $message_date = date('Y-m-d', strtotime($message['created_at']));
                            $is_current_user = $message['sender_id'] == $user['id'];
                            
                            // Show date separator if it's a new date
                            if ($date_shown != $message_date):
                                $date_shown = $message_date;
                                $formatted_date = date('F j, Y', strtotime($message_date));
                        ?>
                            <div class="date-separator">
                                <span><?= $formatted_date ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="message-wrapper <?= $is_current_user ? 'sent' : 'received' ?>" data-message-id="<?= $message['id'] ?>">
                            <?php if (!$is_current_user): ?>
                                <div class="message-avatar">
                                    <?php if ($message['profile_image']): ?>
                                        <img src="<?= htmlspecialchars($message['profile_image']) ?>" 
                                            alt="<?= htmlspecialchars($message['first_name']) ?>">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="message-content">
                                <div class="message-bubble">
                                    <?= nl2br(htmlspecialchars($message['message_text'])) ?>
                                </div>
                                <div class="message-time">
                                    <?= date('g:i A', strtotime($message['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($messages) == 0): ?>
                            <div class="empty-chat">
                                <i class="fas fa-comments"></i>
                                <h3>No messages yet</h3>
                                <p>Send a message to start the conversation with <?= htmlspecialchars($chat_user['first_name']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Message Input -->
                    <div class="message-input-area">
                        <form method="POST" class="message-form">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="receiver_id" value="<?= $chat_with ?>">
                            <div class="input-group">
                                <textarea name="message_text" placeholder="Type your message..." required rows="1"></textarea>
                                <button type="submit" class="send-button">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- No Chat Selected -->
                    <div class="no-chat-selected">
                        <i class="fas fa-comment-alt"></i>
                        <h2>Select a contact to start chatting</h2>
                        <p>Choose a contact from the list to begin a conversation and exchange knowledge!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
    .chat-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 20px;
        height: calc(100vh - 120px);
    }
    
    .chat-layout {
        display: grid;
        grid-template-columns: 300px 1fr;
        height: 100%;
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    /* Contacts Sidebar */
    .contacts-sidebar {
        background: #f8fafc;
        border-right: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
    }
    
    .contacts-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .contacts-header h3 {
        margin: 0;
        font-size: 1.1rem;
    }
    
    .contacts-list {
        flex: 1;
        overflow-y: auto;
    }
    
    .contact-item {
        display: flex;
        align-items: center;
        padding: 1rem 1.5rem;
        text-decoration: none;
        color: #374151;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.3s ease;
    }
    
    .contact-item:hover {
        background: #e2e8f0;
        color: #1e293b;
    }
    
    .contact-item.active {
        background: #667eea;
        color: white;
    }
    
    .contact-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        overflow: hidden;
        color: #64748b;
    }
    
    .contact-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .contact-info h4 {
        margin: 0 0 0.25rem 0;
        font-size: 0.95rem;
        font-weight: 600;
    }
    
    .contact-info p {
        margin: 0;
        font-size: 0.8rem;
        opacity: 0.7;
    }
    
    .empty-contacts {
        text-align: center;
        padding: 3rem 1rem;
        color: #64748b;
    }
    
    .empty-contacts i {
        font-size: 2rem;
        margin-bottom: 1rem;
        display: block;
    }
    
    /* Chat Area */
    .chat-area {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .chat-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        background: white;
    }
    
    .chat-user-info {
        display: flex;
        align-items: center;
    }
    
    .chat-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        overflow: hidden;
        color: #64748b;
    }
    
    .chat-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .chat-user-details h3 {
        margin: 0 0 0.25rem 0;
        color: #1e293b;
        font-size: 1.1rem;
    }
    
    .chat-user-details p {
        margin: 0;
        color: #64748b;
        font-size: 0.9rem;
    }
    
    .chat-messages {
        flex: 1;
        padding: 1.5rem;
        overflow-y: auto;
        background: #f8fafc;
    }
    
    .date-separator {
        text-align: center;
        margin: 1.5rem 0;
    }
    
    .date-separator span {
        background: #64748b;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .message-wrapper {
        display: flex;
        margin-bottom: 1.5rem;
        align-items: flex-end;
    }
    
    .message-wrapper.sent {
        justify-content: flex-end;
    }
    
    .message-wrapper.received {
        justify-content: flex-start;
    }
    
    .message-avatar {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.75rem;
        overflow: hidden;
        color: #64748b;
        font-size: 0.8rem;
    }
    
    .message-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .message-content {
        max-width: 70%;
    }
    
    .message-bubble {
        padding: 0.75rem 1rem;
        border-radius: 18px;
        word-wrap: break-word;
        line-height: 1.4;
    }
    
    .message-wrapper.sent .message-bubble {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-bottom-right-radius: 4px;
    }
    
    .message-wrapper.received .message-bubble {
        background: white;
        color: #374151;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
    }
    
    .message-time {
        font-size: 0.75rem;
        color: #94a3b8;
        margin-top: 0.25rem;
        padding: 0 0.5rem;
    }
    
    .message-wrapper.sent .message-time {
        text-align: right;
    }
    
    .empty-chat {
        text-align: center;
        padding: 4rem 2rem;
        color: #64748b;
    }
    
    .empty-chat i {
        font-size: 4rem;
        margin-bottom: 1rem;
        color: #cbd5e1;
    }
    
    .empty-chat h3 {
        margin-bottom: 0.5rem;
        color: #475569;
    }
    
    .no-chat-selected {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        text-align: center;
        color: #64748b;
        padding: 2rem;
    }
    
    .no-chat-selected i {
        font-size: 5rem;
        margin-bottom: 1.5rem;
        color: #cbd5e1;
    }
    
    .no-chat-selected h2 {
        color: #475569;
        margin-bottom: 0.5rem;
    }
    
    /* Message Input */
    .message-input-area {
        padding: 1.5rem;
        border-top: 1px solid #e2e8f0;
        background: white;
    }
    
    .input-group {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
    }
    
    .input-group textarea {
        flex: 1;
        border: 2px solid #e2e8f0;
        border-radius: 25px;
        padding: 0.75rem 1rem;
        resize: none;
        max-height: 120px;
        font-family: inherit;
        transition: border-color 0.3s ease;
    }
    
    .input-group textarea:focus {
        outline: none;
        border-color: #667eea;
    }
    
    .send-button {
        width: 45px;
        height: 45px;
        border: none;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .send-button:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .chat-container {
            padding: 1rem;
            height: calc(100vh - 100px);
        }
        
        .chat-layout {
            grid-template-columns: 1fr;
            grid-template-rows: auto 1fr;
        }
        
        .contacts-sidebar {
            display: none;
        }
        
        .chat-header {
            padding: 1rem;
        }
        
        .chat-messages {
            padding: 1rem;
        }
        
        .message-input-area {
            padding: 1rem;
        }
        
        .message-content {
            max-width: 85%;
        }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatMessages = document.getElementById('chat-messages');
        const messageForm = document.querySelector('.message-form');
        const textarea = document.querySelector('textarea[name="message_text"]');
        
        let lastMessageId = 0;
        let currentChatUserId = null;
        
        // Get chat user ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        currentChatUserId = urlParams.get('chat_with');
        
        // Get last message ID from existing messages
        const existingMessages = document.querySelectorAll('[data-message-id]');
        if (existingMessages.length > 0) {
            const lastMessage = existingMessages[existingMessages.length - 1];
            lastMessageId = parseInt(lastMessage.getAttribute('data-message-id')) || 0;
        }
        
        // Scroll to bottom of chat on load
        function scrollToBottom() {
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        scrollToBottom();
        
        // Auto resize textarea
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Submit form on Enter (but not Shift+Enter)
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }
        
        // Handle form submission
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                sendMessage();
            });
        }
        
        // Send message function
        function sendMessage() {
            if (!textarea || !currentChatUserId) return;
            
            const message = textarea.value.trim();
            if (!message) return;
            
            const sendButton = document.querySelector('.send-button');
            const originalText = sendButton.innerHTML;
            sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            sendButton.disabled = true;
            
            fetch('send-message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    receiver_id: currentChatUserId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    textarea.value = '';
                    textarea.style.height = 'auto';
                    addMessageToChat(data.message, true);
                    scrollToBottom();
                } else {
                    alert('Failed to send message. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending message. Please try again.');
            })
            .finally(() => {
                sendButton.innerHTML = originalText;
                sendButton.disabled = false;
                textarea.focus();
            });
        }
        
        // Add message to chat
        function addMessageToChat(message, isSent) {
            if (!chatMessages) return;
            
            const messageWrapper = document.createElement('div');
            messageWrapper.className = `message-wrapper ${isSent ? 'sent' : 'received'}`;
            messageWrapper.setAttribute('data-message-id', message.id);
            
            let messageHTML = '';
            
            if (!isSent) {
                messageHTML += `
                    <div class="message-avatar">
                        ${message.profile_image ? 
                            `<img src="${message.profile_image}" alt="${message.first_name}">` :
                            '<i class="fas fa-user"></i>'
                        }
                    </div>
                `;
            }
            
            messageHTML += `
                <div class="message-content">
                    <div class="message-bubble">
                        ${message.message_text.replace(/\n/g, '<br>')}
                    </div>
                    <div class="message-time">
                        ${formatTime(message.created_at)}
                    </div>
                </div>
            `;
            
            messageWrapper.innerHTML = messageHTML;
            
            // Remove empty chat message if exists
            const emptyChat = chatMessages.querySelector('.empty-chat');
            if (emptyChat) {
                emptyChat.remove();
            }
            
            chatMessages.appendChild(messageWrapper);
            lastMessageId = Math.max(lastMessageId, parseInt(message.id));
        }
        
        // Format time
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
        }
        
        // Poll for new messages
        function pollForMessages() {
            if (!currentChatUserId) return;
            
            fetch(`get-messages.php?chat_with=${currentChatUserId}&last_id=${lastMessageId}`)
                .then(response => response.json())
                .then(messages => {
                    if (messages && messages.length > 0) {
                        const wasAtBottom = chatMessages.scrollHeight - chatMessages.clientHeight <= chatMessages.scrollTop + 1;
                        
                        messages.forEach(message => {
                            addMessageToChat(message, message.sender_id === '<?php echo $user['id']; ?>');
                        });
                        
                        if (wasAtBottom) {
                            scrollToBottom();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error polling messages:', error);
                });
        }
        
        // Start polling for new messages every 2 seconds
        if (currentChatUserId) {
            setInterval(pollForMessages, 2000);
        }
        
        // Focus on textarea when page loads
        if (textarea) {
            textarea.focus();
        }
    });
    </script>

    <script src="js/main.js"></script>
</body>
</html>