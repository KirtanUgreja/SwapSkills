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
        case 'delete_skill':
            $skill_id = (int)$_POST['skill_id'];
            $stmt = $pdo->prepare("DELETE FROM skills WHERE id = ?");
            if ($stmt->execute([$skill_id])) {
                $success = 'Skill deleted successfully.';
            } else {
                $error = 'Failed to delete skill.';
            }
            break;
            
        case 'bulk_delete':
            if (isset($_POST['skill_ids']) && is_array($_POST['skill_ids'])) {
                $skill_ids = array_map('intval', $_POST['skill_ids']);
                $placeholders = str_repeat('?,', count($skill_ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM skills WHERE id IN ($placeholders)");
                if ($stmt->execute($skill_ids)) {
                    $success = count($skill_ids) . ' skills deleted successfully.';
                } else {
                    $error = 'Failed to delete selected skills.';
                }
            }
            break;
    }
}

// Get skills with search and filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$flag_filter = isset($_GET['flag']) ? sanitizeInput($_GET['flag']) : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(s.name LIKE ? OR s.description LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($category)) {
    $where_conditions[] = "s.category = ?";
    $params[] = $category;
}

// Flag inappropriate content automatically
if ($flag_filter === 'flagged') {
    $where_conditions[] = "(LENGTH(s.description) < 20 OR s.description LIKE '%spam%' OR s.description LIKE '%test%' OR s.description LIKE '%xxx%' OR s.description LIKE '%fake%')";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $pdo->prepare("
    SELECT s.*, 
           u.first_name, u.last_name, u.username, u.email, u.is_banned,
           COUNT(DISTINCT sr.id) as request_count
    FROM skills s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN swap_requests sr ON s.id = sr.provider_skill_id OR s.id = sr.requester_skill_id
    $where_clause
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->query("SELECT DISTINCT category FROM skills WHERE category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM skills");
$total_skills = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM skills WHERE LENGTH(description) < 20 OR description LIKE '%spam%' OR description LIKE '%test%'");
$flagged_skills = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT category, COUNT(*) as count FROM skills GROUP BY category ORDER BY count DESC LIMIT 5");
$top_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skills Management - SwapSkills Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2><i class="fas fa-list"></i> Skills Management</h2>
            </div>
            <div class="nav-menu">
                <a href="admin.php" class="nav-link">Admin Dashboard</a>
                <a href="admin-users.php" class="nav-link">Users</a>
                <a href="admin-skills.php" class="nav-link active">Skills</a>
                <a href="admin-reports.php" class="nav-link">Reports</a>
                <a href="admin.php" class="nav-link">Back to Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-list"></i> Skills Management</h1>
            <div class="admin-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $total_skills; ?></span>
                    <span class="stat-label">Total Skills</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $flagged_skills; ?></span>
                    <span class="stat-label">Flagged Skills</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($categories); ?></span>
                    <span class="stat-label">Categories</span>
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
                    <input type="text" name="search" placeholder="Search skills..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="filter-group">
                    <select name="category" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                    <?php echo $category == $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="flag" onchange="this.form.submit()">
                        <option value="">All Skills</option>
                        <option value="flagged" <?php echo $flag_filter == 'flagged' ? 'selected' : ''; ?>>Flagged Skills</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Top Categories -->
        <?php if (!empty($top_categories)): ?>
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-bar"></i> Top Categories</h2>
            </div>
            <div class="category-stats">
                <?php foreach ($top_categories as $cat): ?>
                    <div class="category-item">
                        <span class="category-name"><?php echo htmlspecialchars($cat['category']); ?></span>
                        <span class="category-count"><?php echo $cat['count']; ?> skills</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Skills Management -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-cogs"></i> Skills List</h2>
                <div class="bulk-actions">
                    <button type="button" id="selectAll" class="btn btn-sm btn-secondary">
                        <i class="fas fa-check-square"></i> Select All
                    </button>
                    <button type="button" id="deleteSelected" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                </div>
            </div>

            <form method="POST" id="bulkForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="bulk_delete">
                
                <div class="skills-grid">
                    <?php if (empty($skills)): ?>
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h3>No Skills Found</h3>
                            <p>Try adjusting your search criteria</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($skills as $skill): ?>
                            <div class="skill-card <?php echo (strlen($skill['description']) < 20 || strpos(strtolower($skill['description']), 'spam') !== false) ? 'flagged' : ''; ?>">
                                <div class="skill-header">
                                    <div class="skill-select">
                                        <input type="checkbox" name="skill_ids[]" value="<?php echo $skill['id']; ?>" class="skill-checkbox">
                                    </div>
                                    <div class="skill-info">
                                        <h4><?php echo htmlspecialchars($skill['name']); ?></h4>
                                        <div class="skill-meta">
                                            <span class="category"><?php echo htmlspecialchars($skill['category']); ?></span>
                                            <span class="level level-<?php echo $skill['level']; ?>"><?php echo ucfirst($skill['level']); ?></span>
                                            <span class="type"><?php echo $skill['is_offered'] ? 'Offering' : 'Wanting'; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="skill-description">
                                    <p><?php echo htmlspecialchars(substr($skill['description'], 0, 150)); ?><?php echo strlen($skill['description']) > 150 ? '...' : ''; ?></p>
                                </div>
                                
                                <div class="skill-user">
                                    <div class="user-info">
                                        <strong><?php echo htmlspecialchars($skill['first_name'] . ' ' . $skill['last_name']); ?></strong>
                                        <small>@<?php echo htmlspecialchars($skill['username']); ?></small>
                                        <?php if ($skill['is_banned']): ?>
                                            <span class="badge badge-banned">Banned User</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="skill-stats">
                                        <span class="stat">
                                            <i class="fas fa-exchange-alt"></i>
                                            <?php echo $skill['request_count']; ?> requests
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="skill-actions">
                                    <small class="skill-date">
                                        Created: <?php echo date('M j, Y', strtotime($skill['created_at'])); ?>
                                    </small>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="delete_skill">
                                        <input type="hidden" name="skill_id" value="<?php echo $skill['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Are you sure you want to delete this skill?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <style>
    .category-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-top: 1rem;
    }

    .category-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 10px;
        min-width: 120px;
    }

    .category-name {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }

    .category-count {
        font-size: 0.9rem;
        color: #6b7280;
    }

    .bulk-actions {
        display: flex;
        gap: 0.5rem;
    }

    .skills-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    .skill-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 15px;
        padding: 1.5rem;
        transition: all 0.3s ease;
    }

    .skill-card:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .skill-card.flagged {
        border-color: #f87171;
        background: #fef2f2;
    }

    .skill-header {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .skill-select input {
        width: 18px;
        height: 18px;
    }

    .skill-info h4 {
        margin: 0 0 0.5rem;
        color: #1f2937;
        font-size: 1.1rem;
    }

    .skill-meta {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .skill-meta span {
        padding: 0.2rem 0.6rem;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .category {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .level-beginner { background: #dcfce7; color: #16a34a; }
    .level-intermediate { background: #fef3c7; color: #d97706; }
    .level-expert { background: #fee2e2; color: #dc2626; }

    .type {
        background: #f3e8ff;
        color: #7c3aed;
    }

    .skill-description {
        margin: 1rem 0;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 8px;
    }

    .skill-description p {
        margin: 0;
        color: #4b5563;
        line-height: 1.5;
    }

    .skill-user {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 1rem 0;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }

    .badge-banned {
        background: #fee2e2;
        color: #dc2626;
        padding: 0.2rem 0.5rem;
        border-radius: 10px;
        font-size: 0.7rem;
        margin-left: 0.5rem;
    }

    .skill-stats .stat {
        font-size: 0.8rem;
        color: #6b7280;
    }

    .skill-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }

    .skill-date {
        color: #9ca3af;
        font-size: 0.8rem;
    }

    @media (max-width: 768px) {
        .skills-grid {
            grid-template-columns: 1fr;
        }
        
        .search-filter-form {
            flex-direction: column;
            gap: 1rem;
        }
        
        .bulk-actions {
            flex-direction: column;
        }
    }
    </style>

    <script>
    // Bulk selection functionality
    document.getElementById('selectAll').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.skill-checkbox');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
        
        this.innerHTML = allChecked ? 
            '<i class="fas fa-check-square"></i> Select All' : 
            '<i class="fas fa-square"></i> Deselect All';
    });

    document.getElementById('deleteSelected').addEventListener('click', function() {
        const selected = document.querySelectorAll('.skill-checkbox:checked');
        
        if (selected.length === 0) {
            alert('Please select skills to delete.');
            return;
        }
        
        if (confirm(`Are you sure you want to delete ${selected.length} selected skills?`)) {
            document.getElementById('bulkForm').submit();
        }
    });
    </script>

    <script src="js/main.js"></script>
</body>
</html>