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
        case 'ban_user':
            $user_id = (int)$_POST['user_id'];
            $stmt = $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success = 'User banned successfully.';
            } else {
                $error = 'Failed to ban user.';
            }
            break;
            
        case 'unban_user':
            $user_id = (int)$_POST['user_id'];
            $stmt = $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success = 'User unbanned successfully.';
            } else {
                $error = 'Failed to unban user.';
            }
            break;
            
        case 'make_admin':
            $user_id = (int)$_POST['user_id'];
            $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success = 'User promoted to admin successfully.';
            } else {
                $error = 'Failed to promote user to admin.';
            }
            break;
            
        case 'remove_admin':
            $user_id = (int)$_POST['user_id'];
            if ($user_id == $user['id']) {
                $error = 'You cannot remove your own admin privileges.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET is_admin = 0 WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $success = 'Admin privileges removed successfully.';
                } else {
                    $error = 'Failed to remove admin privileges.';
                }
            }
            break;
    }
}

// Get users with search and filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

switch ($filter) {
    case 'banned':
        $where_conditions[] = "is_banned = 1";
        break;
    case 'admin':
        $where_conditions[] = "is_admin = 1";
        break;
    case 'active':
        $where_conditions[] = "is_banned = 0";
        break;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT s.id) as skill_count,
           COUNT(DISTINCT sr.id) as request_count,
           AVG(r.rating) as avg_rating
    FROM users u
    LEFT JOIN skills s ON u.id = s.user_id
    LEFT JOIN swap_requests sr ON u.id = sr.requester_id OR u.id = sr.provider_id
    LEFT JOIN reviews r ON u.id = r.reviewee_id
    $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_banned = 0");
$total_active = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_banned = 1");
$total_banned = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 1");
$total_admin = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - SwapSkills Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2><i class="fas fa-users-cog"></i> User Management</h2>
            </div>
            <div class="nav-menu">
                <a href="admin.php" class="nav-link">Admin Dashboard</a>
                <a href="admin-users.php" class="nav-link active">Users</a>
                <a href="admin-skills.php" class="nav-link">Skills</a>
                <a href="admin-reports.php" class="nav-link">Reports</a>
                <a href="admin.php" class="nav-link">Back to Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-users"></i> User Management</h1>
            <div class="admin-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $total_active; ?></span>
                    <span class="stat-label">Active Users</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $total_banned; ?></span>
                    <span class="stat-label">Banned Users</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $total_admin; ?></span>
                    <span class="stat-label">Administrators</span>
                </div>
            </div>
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

        <!-- Search and Filter -->
        <div class="search-filter-section">
            <form method="GET" class="search-filter-form">
                <div class="search-group">
                    <input type="text" name="search" placeholder="Search users..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="filter-group">
                    <select name="filter" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Users</option>
                        <option value="active" <?php echo $filter == 'active' ? 'selected' : ''; ?>>Active Users</option>
                        <option value="banned" <?php echo $filter == 'banned' ? 'selected' : ''; ?>>Banned Users</option>
                        <option value="admin" <?php echo $filter == 'admin' ? 'selected' : ''; ?>>Administrators</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="admin-section">
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Stats</th>
                            <th>Rating</th>
                            <th>Joined</th>
                            <th>Last Login</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user_data): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <?php if ($user_data['profile_image']): ?>
                                            <img src="uploads/profile_photos/<?php echo htmlspecialchars($user_data['profile_image']); ?>" 
                                                 alt="Profile" class="user-avatar">
                                        <?php else: ?>
                                            <div class="user-avatar-placeholder">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></strong>
                                            <br><small>@<?php echo htmlspecialchars($user_data['username']); ?></small>
                                            <?php if ($user_data['is_admin']): ?>
                                                <span class="badge badge-admin">Admin</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user_data['email']); ?></td>
                                <td>
                                    <small>
                                        Skills: <?php echo $user_data['skill_count']; ?><br>
                                        Requests: <?php echo $user_data['request_count']; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($user_data['avg_rating']): ?>
                                        <div class="rating-display">
                                            <?php
                                            $rating = round($user_data['avg_rating'], 1);
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } else {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                            }
                                            ?>
                                            <span><?php echo $rating; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No ratings</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user_data['created_at'])); ?></td>
                                <td>
                                    <?php echo $user_data['last_login'] ? date('M j, Y', strtotime($user_data['last_login'])) : 'Never'; ?>
                                </td>
                                <td>
                                    <span class="status <?php echo $user_data['is_banned'] ? 'banned' : 'active'; ?>">
                                        <?php echo $user_data['is_banned'] ? 'Banned' : 'Active'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user_data['is_banned']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="unban_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user_data['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="ban_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user_data['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to ban this user?')">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($user_data['is_admin']): ?>
                                            <?php if ($user_data['id'] != $user['id']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="remove_admin">
                                                    <input type="hidden" name="user_id" value="<?php echo $user_data['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning" 
                                                            onclick="return confirm('Remove admin privileges from this user?')">
                                                        <i class="fas fa-user-minus"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="make_admin">
                                                <input type="hidden" name="user_id" value="<?php echo $user_data['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-info" 
                                                        onclick="return confirm('Make this user an administrator?')">
                                                    <i class="fas fa-user-plus"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
    .user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .user-avatar, .user-avatar-placeholder {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    .user-avatar-placeholder {
        background: #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
    }

    .badge {
        padding: 0.2rem 0.5rem;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    .badge-admin {
        background: #fef3c7;
        color: #d97706;
    }

    .search-filter-section {
        background: white;
        padding: 1.5rem;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }

    .search-filter-form {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .search-group {
        display: flex;
        flex: 1;
        gap: 0.5rem;
    }

    .search-group input {
        flex: 1;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
    }

    .filter-group select {
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: white;
    }

    .rating-display {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .rating-display .fas.fa-star {
        color: #fbbf24;
    }

    .rating-display .far.fa-star {
        color: #d1d5db;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    @media (max-width: 768px) {
        .search-filter-form {
            flex-direction: column;
            align-items: stretch;
        }
        
        .action-buttons {
            flex-direction: column;
        }
    }
    </style>

    <script src="js/main.js"></script>
</body>
</html>