<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDBConnection();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $request_id = (int)$_POST['request_id'];
        $action = $_POST['action'];
        
        if ($action === 'complete') {
            // Verify user is part of this request and it's accepted
            $stmt = $pdo->prepare("SELECT * FROM swap_requests WHERE id = ? AND (requester_id = ? OR provider_id = ?) AND status = 'accepted'");
            $stmt->execute([$request_id, $user['id'], $user['id']]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                $stmt = $pdo->prepare("UPDATE swap_requests SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$request_id]);
                $success = "Request marked as completed! You can now rate the other user.";
            }
        } else {
            // Verify user owns this request (as provider)
            $stmt = $pdo->prepare("SELECT * FROM swap_requests WHERE id = ? AND provider_id = ?");
            $stmt->execute([$request_id, $user['id']]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request && in_array($action, ['accept', 'reject'])) {
                $status = ($action === 'accept') ? 'accepted' : 'rejected';
                $stmt = $pdo->prepare("UPDATE swap_requests SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $request_id]);
                
                $message = ($action === 'accept') ? 'Request accepted!' : 'Request rejected.';
                $success = $message;
            }
        }
    }
}

// Get all requests (sent and received) with rating info
$stmt = $pdo->prepare("
    SELECT sr.*, 
           requester.first_name as requester_first_name, requester.last_name as requester_last_name,
           provider.first_name as provider_first_name, provider.last_name as provider_last_name,
           requester_skill.name as requester_skill_name,
           provider_skill.name as provider_skill_name,
           r1.rating as user_rating_given,
           r2.rating as other_rating_given
    FROM swap_requests sr
    LEFT JOIN users requester ON sr.requester_id = requester.id
    LEFT JOIN users provider ON sr.provider_id = provider.id
    LEFT JOIN skills requester_skill ON sr.requester_skill_id = requester_skill.id
    LEFT JOIN skills provider_skill ON sr.provider_skill_id = provider_skill.id
    LEFT JOIN reviews r1 ON sr.id = r1.swap_request_id AND r1.reviewer_id = ?
    LEFT JOIN reviews r2 ON sr.id = r2.swap_request_id AND r2.reviewer_id != ?
    WHERE sr.requester_id = ? OR sr.provider_id = ?
    ORDER BY sr.created_at DESC
");
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate sent and received requests
$sentRequests = array_filter($requests, function($req) use ($user) {
    return $req['requester_id'] == $user['id'];
});

$receivedRequests = array_filter($requests, function($req) use ($user) {
    return $req['provider_id'] == $user['id'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - SwapSkills</title>
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
                <a href="requests.php" class="nav-link active">My Requests</a>
                <a href="chat.php" class="nav-link">
                    Chat
                </a>
                <a href="profile.php" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>My Requests</h1>
                <p>Manage your skill exchange requests</p>
            </div>
            <div class="quick-actions">
                <a href="browse.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Send New Request
                </a>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="success-message" style="margin-bottom: 2rem;">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="requests-container">
            <!-- Received Requests -->
            <div class="requests-section">
                <div class="section-header">
                    <h2><i class="fas fa-inbox"></i> Received Requests (<?php echo count($receivedRequests); ?>)</h2>
                </div>
                
                <?php if (empty($receivedRequests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Received Requests</h3>
                        <p>When someone wants to learn your skills, their requests will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="requests-list">
                        <?php foreach ($receivedRequests as $request): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="requester-info">
                                        <h4><?php echo htmlspecialchars($request['requester_first_name'] . ' ' . $request['requester_last_name']); ?></h4>
                                        <span class="request-date"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></span>
                                    </div>
                                    <span class="status status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="request-details">
                                    <div class="skill-exchange">
                                        <div class="wanted-skill">
                                            <strong>Wants to learn:</strong> <?php echo htmlspecialchars($request['provider_skill_name']); ?>
                                        </div>
                                        <?php if ($request['requester_skill_name']): ?>
                                            <div class="offered-skill">
                                                <strong>Offers in exchange:</strong> <?php echo htmlspecialchars($request['requester_skill_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="request-message">
                                        <strong>Message:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($request['message'])); ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($request['status'] === 'pending'): ?>
                                    <div class="request-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-check"></i> Accept
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($request['status'] === 'accepted'): ?>
                                    <div class="request-actions">
                                        <span class="accepted-note">
                                            <i class="fas fa-handshake"></i>
                                            Request accepted! You can now connect directly.
                                        </span>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-flag-checkered"></i> Mark as Complete
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($request['status'] === 'completed'): ?>
                                    <div class="request-actions">
                                        <span class="completed-note">
                                            <i class="fas fa-check-double"></i>
                                            Skill exchange completed!
                                        </span>
                                        <?php if (!$request['user_rating_given']): ?>
                                            <a href="rate-user.php?request_id=<?php echo $request['id']; ?>" class="btn btn-warning">
                                                <i class="fas fa-star"></i> Rate User
                                            </a>
                                        <?php else: ?>
                                            <span class="rating-given">
                                                <i class="fas fa-star"></i> Rating given (<?php echo $request['user_rating_given']; ?>/5)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sent Requests -->
            <div class="requests-section">
                <div class="section-header">
                    <h2><i class="fas fa-paper-plane"></i> Sent Requests (<?php echo count($sentRequests); ?>)</h2>
                </div>
                
                <?php if (empty($sentRequests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-paper-plane"></i>
                        <h3>No Sent Requests</h3>
                        <p>Start browsing skills to send your first request!</p>
                        <a href="browse.php" class="btn btn-primary">Browse Skills</a>
                    </div>
                <?php else: ?>
                    <div class="requests-list">
                        <?php foreach ($sentRequests as $request): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="provider-info">
                                        <h4>To: <?php echo htmlspecialchars($request['provider_first_name'] . ' ' . $request['provider_last_name']); ?></h4>
                                        <span class="request-date"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></span>
                                    </div>
                                    <span class="status status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="request-details">
                                    <div class="skill-exchange">
                                        <div class="wanted-skill">
                                            <strong>You want to learn:</strong> <?php echo htmlspecialchars($request['provider_skill_name']); ?>
                                        </div>
                                        <?php if ($request['requester_skill_name']): ?>
                                            <div class="offered-skill">
                                                <strong>You offered in exchange:</strong> <?php echo htmlspecialchars($request['requester_skill_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="request-message">
                                        <strong>Your message:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($request['message'])); ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($request['status'] === 'pending'): ?>
                                    <div class="request-status">
                                        <i class="fas fa-clock"></i>
                                        Waiting for response...
                                    </div>
                                <?php elseif ($request['status'] === 'accepted'): ?>
                                    <div class="request-status accepted">
                                        <i class="fas fa-check-circle"></i>
                                        Request accepted! You can now connect.
                                        <form method="POST" style="display: inline; margin-left: 1rem;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-flag-checkered"></i> Mark as Complete
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($request['status'] === 'completed'): ?>
                                    <div class="request-status completed">
                                        <i class="fas fa-check-double"></i>
                                        Skill exchange completed!
                                        <?php if (!$request['user_rating_given']): ?>
                                            <a href="rate-user.php?request_id=<?php echo $request['id']; ?>" class="btn btn-warning btn-sm" style="margin-left: 1rem;">
                                                <i class="fas fa-star"></i> Rate User
                                            </a>
                                        <?php else: ?>
                                            <span class="rating-given" style="margin-left: 1rem;">
                                                <i class="fas fa-star"></i> Rated <?php echo $request['user_rating_given']; ?>/5
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($request['status'] === 'rejected'): ?>
                                    <div class="request-status rejected">
                                        <i class="fas fa-times-circle"></i>
                                        Request was declined.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
    .requests-container {
        display: grid;
        gap: 3rem;
    }
    
    .requests-section {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .request-card {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    
    .request-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .request-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .requester-info h4,
    .provider-info h4 {
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    
    .request-date {
        color: #64748b;
        font-size: 0.9rem;
    }
    
    .request-details {
        margin-bottom: 1.5rem;
    }
    
    .skill-exchange {
        margin-bottom: 1rem;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 8px;
    }
    
    .wanted-skill,
    .offered-skill {
        margin-bottom: 0.5rem;
        color: #374151;
    }
    
    .request-message {
        color: #374151;
    }
    
    .request-message p {
        margin-top: 0.5rem;
        color: #64748b;
        line-height: 1.5;
    }
    
    .request-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .request-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .request-status {
        background: #fef3c7;
        color: #d97706;
    }
    
    .request-status.accepted {
        background: #dcfce7;
        color: #16a34a;
    }
    
    .request-status.rejected {
        background: #fee2e2;
        color: #dc2626;
    }
    
    .accepted-note {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #16a34a;
        font-weight: 500;
    }
    </style>

    <script src="js/main.js"></script>
</body>
</html>