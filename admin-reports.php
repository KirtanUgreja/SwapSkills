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

// Handle report downloads
if (isset($_GET['download'])) {
    $report_type = sanitizeInput($_GET['download']);
    
    switch ($report_type) {
        case 'users':
            downloadUserReport($pdo);
            break;
        case 'skills':
            downloadSkillReport($pdo);
            break;
        case 'swaps':
            downloadSwapReport($pdo);
            break;
        case 'reviews':
            downloadReviewReport($pdo);
            break;
    }
    exit;
}

function downloadUserReport($pdo) {
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email, u.first_name, u.last_name, 
               u.location, u.is_admin, u.is_banned, u.created_at, u.last_login,
               COUNT(DISTINCT s.id) as skill_count,
               COUNT(DISTINCT sr.id) as request_count,
               AVG(r.rating) as avg_rating
        FROM users u
        LEFT JOIN skills s ON u.id = s.user_id
        LEFT JOIN swap_requests sr ON u.id = sr.requester_id OR u.id = sr.provider_id
        LEFT JOIN reviews r ON u.id = r.reviewee_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Username', 'Email', 'First Name', 'Last Name', 'Location', 'Is Admin', 'Is Banned', 'Created At', 'Last Login', 'Skills Count', 'Requests Count', 'Average Rating']);
    
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['username'],
            $user['email'],
            $user['first_name'],
            $user['last_name'],
            $user['location'],
            $user['is_admin'] ? 'Yes' : 'No',
            $user['is_banned'] ? 'Yes' : 'No',
            $user['created_at'],
            $user['last_login'],
            $user['skill_count'],
            $user['request_count'],
            round($user['avg_rating'], 2)
        ]);
    }
    
    fclose($output);
}

function downloadSkillReport($pdo) {
    $stmt = $pdo->query("
        SELECT s.*, u.username, u.first_name, u.last_name,
               COUNT(DISTINCT sr.id) as request_count
        FROM skills s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN swap_requests sr ON s.id = sr.provider_skill_id OR s.id = sr.requester_skill_id
        GROUP BY s.id
        ORDER BY s.created_at DESC
    ");
    
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="skills_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Description', 'Category', 'Level', 'Is Offered', 'User', 'Username', 'Request Count', 'Created At']);
    
    foreach ($skills as $skill) {
        fputcsv($output, [
            $skill['id'],
            $skill['name'],
            $skill['description'],
            $skill['category'],
            $skill['level'],
            $skill['is_offered'] ? 'Yes' : 'No',
            $skill['first_name'] . ' ' . $skill['last_name'],
            $skill['username'],
            $skill['request_count'],
            $skill['created_at']
        ]);
    }
    
    fclose($output);
}

function downloadSwapReport($pdo) {
    $stmt = $pdo->query("
        SELECT sr.*, 
               u1.username as requester_username, u1.first_name as requester_first, u1.last_name as requester_last,
               u2.username as provider_username, u2.first_name as provider_first, u2.last_name as provider_last,
               s1.name as requester_skill, s2.name as provider_skill
        FROM swap_requests sr
        LEFT JOIN users u1 ON sr.requester_id = u1.id
        LEFT JOIN users u2 ON sr.provider_id = u2.id
        LEFT JOIN skills s1 ON sr.requester_skill_id = s1.id
        LEFT JOIN skills s2 ON sr.provider_skill_id = s2.id
        ORDER BY sr.created_at DESC
    ");
    
    $swaps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="swaps_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Requester', 'Requester Username', 'Provider', 'Provider Username', 'Requester Skill', 'Provider Skill', 'Status', 'Message', 'Created At', 'Updated At']);
    
    foreach ($swaps as $swap) {
        fputcsv($output, [
            $swap['id'],
            $swap['requester_first'] . ' ' . $swap['requester_last'],
            $swap['requester_username'],
            $swap['provider_first'] . ' ' . $swap['provider_last'],
            $swap['provider_username'],
            $swap['requester_skill'],
            $swap['provider_skill'],
            $swap['status'],
            $swap['message'],
            $swap['created_at'],
            $swap['updated_at']
        ]);
    }
    
    fclose($output);
}

function downloadReviewReport($pdo) {
    $stmt = $pdo->query("
        SELECT r.*, sr.id as swap_id,
               u1.username as reviewer_username, u1.first_name as reviewer_first, u1.last_name as reviewer_last,
               u2.username as reviewee_username, u2.first_name as reviewee_first, u2.last_name as reviewee_last
        FROM reviews r
        JOIN swap_requests sr ON r.swap_request_id = sr.id
        LEFT JOIN users u1 ON r.reviewer_id = u1.id
        LEFT JOIN users u2 ON r.reviewee_id = u2.id
        ORDER BY r.created_at DESC
    ");
    
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reviews_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Swap ID', 'Reviewer', 'Reviewer Username', 'Reviewee', 'Reviewee Username', 'Rating', 'Feedback', 'Created At']);
    
    foreach ($reviews as $review) {
        fputcsv($output, [
            $review['id'],
            $review['swap_id'],
            $review['reviewer_first'] . ' ' . $review['reviewer_last'],
            $review['reviewer_username'],
            $review['reviewee_first'] . ' ' . $review['reviewee_last'],
            $review['reviewee_username'],
            $review['rating'],
            $review['feedback'],
            $review['created_at']
        ]);
    }
    
    fclose($output);
}

// Get dashboard data
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_banned = 0");
$total_users = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM skills");
$total_skills = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM swap_requests");
$total_swaps = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM reviews");
$total_reviews = $stmt->fetch()['total'];

// Get recent activity
$stmt = $pdo->query("
    SELECT 'user' as type, username as title, created_at, 'User registered' as description
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'skill' as type, s.name as title, s.created_at, CONCAT('Skill added by ', u.username) as description
    FROM skills s
    JOIN users u ON s.user_id = u.id
    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'swap' as type, CONCAT('Swap #', sr.id) as title, sr.created_at, CONCAT('Request from ', u1.username, ' to ', u2.username) as description
    FROM swap_requests sr
    JOIN users u1 ON sr.requester_id = u1.id
    JOIN users u2 ON sr.provider_id = u2.id
    WHERE sr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
    LIMIT 20
");
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly stats
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as users
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$monthly_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as swaps
    FROM swap_requests 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$monthly_swaps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - SwapSkills Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2><i class="fas fa-chart-line"></i> Reports & Analytics</h2>
            </div>
            <div class="nav-menu">
                <a href="admin.php" class="nav-link">Admin Dashboard</a>
                <a href="admin-users.php" class="nav-link">Users</a>
                <a href="admin-skills.php" class="nav-link">Skills</a>
                <a href="admin-reports.php" class="nav-link active">Reports</a>
                <a href="admin.php" class="nav-link">Back to Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-chart-line"></i> Reports & Analytics</h1>
            <p>Platform insights and downloadable reports</p>
        </div>

        <!-- Download Reports -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-download"></i> Download Reports</h2>
                <p>Export platform data for analysis</p>
            </div>
            <div class="download-grid">
                <div class="download-card">
                    <div class="download-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="download-info">
                        <h3>User Report</h3>
                        <p>Complete user data with statistics</p>
                        <span class="download-count"><?php echo $total_users; ?> users</span>
                    </div>
                    <a href="?download=users" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download CSV
                    </a>
                </div>

                <div class="download-card">
                    <div class="download-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="download-info">
                        <h3>Skills Report</h3>
                        <p>All skills with user information</p>
                        <span class="download-count"><?php echo $total_skills; ?> skills</span>
                    </div>
                    <a href="?download=skills" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download CSV
                    </a>
                </div>

                <div class="download-card">
                    <div class="download-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="download-info">
                        <h3>Swap Requests Report</h3>
                        <p>All swap requests and their status</p>
                        <span class="download-count"><?php echo $total_swaps; ?> swaps</span>
                    </div>
                    <a href="?download=swaps" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download CSV
                    </a>
                </div>

                <div class="download-card">
                    <div class="download-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="download-info">
                        <h3>Reviews Report</h3>
                        <p>All user reviews and ratings</p>
                        <span class="download-count"><?php echo $total_reviews; ?> reviews</span>
                    </div>
                    <a href="?download=reviews" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download CSV
                    </a>
                </div>
            </div>
        </div>

        <!-- Platform Statistics -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-bar"></i> Platform Statistics</h2>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $total_users; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $total_skills; ?></h3>
                        <p>Total Skills</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $total_swaps; ?></h3>
                        <p>Swap Requests</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $total_reviews; ?></h3>
                        <p>Reviews</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Growth -->
        <div class="reports-grid">
            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-line"></i> User Growth</h2>
                </div>
                <div class="chart-container">
                    <?php if (!empty($monthly_users)): ?>
                        <div class="simple-chart">
                            <?php foreach (array_reverse($monthly_users) as $data): ?>
                                <div class="chart-bar">
                                    <div class="bar" style="height: <?php echo min(100, ($data['users'] / max(array_column($monthly_users, 'users'))) * 100); ?>%"></div>
                                    <span class="bar-label"><?php echo date('M', strtotime($data['month'] . '-01')); ?></span>
                                    <span class="bar-value"><?php echo $data['users']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No user data available</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-area"></i> Swap Activity</h2>
                </div>
                <div class="chart-container">
                    <?php if (!empty($monthly_swaps)): ?>
                        <div class="simple-chart">
                            <?php foreach (array_reverse($monthly_swaps) as $data): ?>
                                <div class="chart-bar">
                                    <div class="bar bar-swap" style="height: <?php echo min(100, ($data['swaps'] / max(array_column($monthly_swaps, 'swaps'))) * 100); ?>%"></div>
                                    <span class="bar-label"><?php echo date('M', strtotime($data['month'] . '-01')); ?></span>
                                    <span class="bar-value"><?php echo $data['swaps']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No swap data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-clock"></i> Recent Activity (Last 7 Days)</h2>
            </div>
            <div class="activity-list">
                <?php if (!empty($recent_activity)): ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon activity-<?php echo $activity['type']; ?>">
                                <i class="fas <?php echo $activity['type'] == 'user' ? 'fa-user-plus' : ($activity['type'] == 'skill' ? 'fa-plus' : 'fa-exchange-alt'); ?>"></i>
                            </div>
                            <div class="activity-content">
                                <h4><?php echo htmlspecialchars($activity['title']); ?></h4>
                                <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                <span class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No Recent Activity</h3>
                        <p>No activity in the last 7 days</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
    .reports-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .download-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    .download-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        background: #f8fafc;
        border-radius: 15px;
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }

    .download-card:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .download-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }

    .download-info {
        flex: 1;
    }

    .download-info h3 {
        margin: 0 0 0.5rem;
        color: #1f2937;
        font-size: 1.1rem;
    }

    .download-info p {
        margin: 0 0 0.5rem;
        color: #6b7280;
        font-size: 0.9rem;
    }

    .download-count {
        font-size: 0.8rem;
        color: #3b82f6;
        font-weight: 600;
    }

    .chart-container {
        margin-top: 1.5rem;
    }

    .simple-chart {
        display: flex;
        align-items: end;
        gap: 1rem;
        height: 200px;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 10px;
    }

    .chart-bar {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        height: 100%;
    }

    .bar {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        width: 30px;
        border-radius: 4px 4px 0 0;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
    }

    .bar-swap {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .bar-label {
        font-size: 0.8rem;
        color: #6b7280;
        margin-bottom: 0.25rem;
    }

    .bar-value {
        font-size: 0.7rem;
        color: #374151;
        font-weight: 600;
    }

    .activity-list {
        max-height: 500px;
        overflow-y: auto;
    }

    .activity-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        transition: background-color 0.3s ease;
    }

    .activity-item:hover {
        background: #f8fafc;
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1rem;
    }

    .activity-user { background: #3b82f6; }
    .activity-skill { background: #10b981; }
    .activity-swap { background: #f59e0b; }

    .activity-content h4 {
        margin: 0 0 0.25rem;
        color: #1f2937;
        font-size: 1rem;
    }

    .activity-content p {
        margin: 0 0 0.5rem;
        color: #6b7280;
        font-size: 0.9rem;
    }

    .activity-time {
        font-size: 0.8rem;
        color: #9ca3af;
    }

    .no-data {
        text-align: center;
        color: #6b7280;
        font-style: italic;
        padding: 2rem;
    }

    @media (max-width: 768px) {
        .reports-grid {
            grid-template-columns: 1fr;
        }
        
        .download-grid {
            grid-template-columns: 1fr;
        }
        
        .download-card {
            flex-direction: column;
            text-align: center;
        }
        
        .simple-chart {
            gap: 0.5rem;
        }
        
        .bar {
            width: 20px;
        }
    }
    </style>

    <script src="js/main.js"></script>
</body>
</html>