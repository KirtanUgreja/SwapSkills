<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();

// Check if user is admin
if (!$user['is_admin']) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getDBConnection();
$error = '';
$success = '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && verifyCSRFToken($_POST['csrf_token'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'send_announcement':
            $title = sanitizeInput($_POST['title']);
            $message = sanitizeInput($_POST['message']);
            $target = sanitizeInput($_POST['target']); // 'all', 'active', 'admins'
            
            if (!empty($title) && !empty($message)) {
                // Get recipients based on target
                $where_clause = '';
                if ($target == 'active') {
                    $where_clause = 'WHERE is_banned = 0';
                } elseif ($target == 'admins') {
                    $where_clause = 'WHERE is_admin = 1';
                }
                
                $stmt = $pdo->query("SELECT email, first_name FROM users $where_clause");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                require_once 'email-notifications.php';
                $sent_count = 0;
                
                foreach ($recipients as $recipient) {
                    if (sendAdminAlert($recipient['email'], $title, $message)) {
                        $sent_count++;
                    }
                }
                
                $success = "Announcement sent to $sent_count users.";
            } else {
                $error = 'Please fill in all fields.';
            }
            break;
    }
}

// Get platform statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_banned = 0");
$total_users = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_banned = 1");
$banned_users = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM skills");
$total_skills = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM swap_requests");
$total_swaps = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM swap_requests WHERE status = 'pending'");
$pending_swaps = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM reviews");
$total_reviews = $stmt->fetch()['total'];

// Get recent users (last 7 days)
$stmt = $pdo->query("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$new_users = $stmt->fetch()['count'];

// Get top categories
$stmt = $pdo->query("
    SELECT category, COUNT(*) as count 
    FROM skills 
    WHERE category IS NOT NULL 
    GROUP BY category 
    ORDER BY count DESC 
    LIMIT 5
");
$top_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$stmt = $pdo->query("
    SELECT 'registration' as type, CONCAT(first_name, ' ', last_name) as title, created_at, 'New user registered' as description
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    UNION ALL
    SELECT 'skill' as type, s.name as title, s.created_at, CONCAT('Skill added by ', u.first_name, ' ', u.last_name) as description
    FROM skills s
    JOIN users u ON s.user_id = u.id
    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    UNION ALL
    SELECT 'swap' as type, CONCAT('Swap Request #', sr.id) as title, sr.created_at, 
           CONCAT(u1.first_name, ' requested skill from ', u2.first_name) as description
    FROM swap_requests sr
    JOIN users u1 ON sr.requester_id = u1.id
    JOIN users u2 ON sr.provider_id = u2.id
    WHERE sr.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending actions that need attention
$stmt = $pdo->query("
    SELECT COUNT(*) as count 
    FROM skills 
    WHERE LENGTH(description) < 20 OR description LIKE '%spam%' OR description LIKE '%test%'
");
$flagged_content = $stmt->fetch()['count'];

$stmt = $pdo->query("
    SELECT COUNT(*) as count 
    FROM swap_requests 
    WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$old_pending = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SwapSkills</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-page">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2><i class="fas fa-shield-alt"></i> SwapSkills Admin</h2>
            </div>
            <div class="nav-menu">
                <a href="admin.php" class="nav-link active">Dashboard</a>
                <a href="admin-users.php" class="nav-link">Users</a>
                <a href="admin-skills.php" class="nav-link">Skills</a>
                <a href="admin-reports.php" class="nav-link">Reports</a>
                <div class="nav-profile">
                    <span>Admin: <?php echo htmlspecialchars($user['first_name']); ?></span>
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <p>Platform overview and management tools</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Active Users</p>
                    <small>+<?php echo $new_users; ?> this week</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_skills; ?></h3>
                    <p>Total Skills</p>
                    <small><?php echo $flagged_content; ?> flagged</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    

                </div>
                <div class="stat-content">
                    <h3><?php echo $total_swaps; ?></h3>
                    <p>Swap Requests</p>
                    <small><?php echo $pending_swaps; ?> pending</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_reviews; ?></h3>
                    <p>Reviews</p>
                    <small>Platform feedback</small>
                </div>
            </div>
        </div>

        <div class="admin-content">
            <!-- Quick Actions -->
            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                    <p>Common administrative tasks</p>
                </div>
                <div class="quick-actions-grid">
                    <a href="admin-users.php" class="quick-action">
                        <i class="fas fa-users"></i>
                        <h4>Manage Users</h4>
                        <p>Ban, promote, or manage user accounts</p>
                        <?php if ($banned_users > 0): ?>
                            <span class="action-badge"><?php echo $banned_users; ?> banned</span>
                        <?php endif; ?>
                    </a>
                    <a href="admin-skills.php" class="quick-action">
                        <i class="fas fa-list"></i>
                        <h4>Moderate Skills</h4>
                        <p>Review and manage skill listings</p>
                        <?php if ($flagged_content > 0): ?>
                            <span class="action-badge"><?php echo $flagged_content; ?> flagged</span>
                        <?php endif; ?>
                    </a>
                    <a href="admin-reports.php" class="quick-action">
                        <i class="fas fa-chart-line"></i>
                        <h4>View Reports</h4>
                        <p>Analytics and downloadable reports</p>
                    </a>
                    <a href="dashboard.php" class="quick-action">
                        <i class="fas fa-eye"></i>
                        <h4>View as User</h4>
                        <p>See the platform from user perspective</p>
                    </a>
                </div>
            </div>

            <!-- Platform Announcements -->
            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-bullhorn"></i> Send Announcement</h2>
                    <p>Send email notifications to users</p>
                </div>
                <form method="POST" class="announcement-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="send_announcement">
                    
                    <div class="form-group">
                        <label for="title">Announcement Title</label>
                        <input type="text" id="title" name="title" required 
                               placeholder="Enter announcement title">
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="4" required 
                                  placeholder="Enter your announcement message"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="target">Send To</label>
                        <select id="target" name="target" required>
                            <option value="all">All Users</option>
                            <option value="active">Active Users Only</option>
                            <option value="admins">Administrators Only</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Send Announcement
                    </button>
                </form>
            </div>

            <!-- Recent Activity -->
            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Recent Activity (Last 24 Hours)</h2>
                </div>
                <div class="activity-list">
                    <?php if (!empty($recent_activity)): ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon activity-<?php echo $activity['type']; ?>">
                                    <i class="fas <?php 
                                        echo $activity['type'] == 'registration' ? 'fa-user-plus' : 
                                             ($activity['type'] == 'skill' ? 'fa-plus' : 'fa-exchange-alt'); 
                                    ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <h4><?php echo htmlspecialchars($activity['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <span class="activity-time"><?php echo date('g:i A', strtotime($activity['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Recent Activity</h3>
                            <p>No activity in the last 24 hours</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Categories -->
            <?php if (!empty($top_categories)): ?>
            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-pie"></i> Top Skill Categories</h2>
                </div>
                <div class="category-stats">
                    <?php foreach ($top_categories as $category): ?>
                        <div class="category-item">
                            <span class="category-name"><?php echo htmlspecialchars($category['category']); ?></span>
                            <span class="category-count"><?php echo $category['count']; ?> skills</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    .quick-action {
        display: block;
        padding: 2rem;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 15px;
        text-decoration: none;
        color: #1e293b;
        transition: all 0.3s ease;
        position: relative;
    }

    .quick-action:hover {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        color: #1e293b;
    }

    .quick-action i {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: #667eea;
    }

    .quick-action h4 {
        margin: 0 0 0.5rem;
        font-size: 1.2rem;
        font-weight: 600;
    }

    .quick-action p {
        margin: 0;
        color: #64748b;
        font-size: 0.9rem;
    }

    .action-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: #ef4444;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .nav-profile {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-left: auto;
        color: white;
    }

    .nav-profile span {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    .activity-registration { background: #3b82f6; }
    .activity-skill { background: #10b981; }
    .activity-swap { background: #f59e0b; }

    @media (max-width: 768px) {
        .quick-actions-grid {
            grid-template-columns: 1fr;
        }
        
        .nav-profile {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .admin-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>

    <script src="js/admin.js"></script>
    <script src="js/main.js"></script>
</body>
</html>