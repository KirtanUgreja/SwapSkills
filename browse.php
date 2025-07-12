<?php
require_once 'config.php';

$user = getCurrentUser();
$pdo = getDBConnection();

// Get search parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$level = isset($_GET['level']) ? sanitizeInput($_GET['level']) : '';
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'offered';

// Build query
$whereConditions = ["s.is_offered = " . ($type === 'offered' ? '1' : '0')];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(s.name LIKE ? OR s.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $whereConditions[] = "s.category = ?";
    $params[] = $category;
}

if (!empty($level)) {
    $whereConditions[] = "s.level = ?";
    $params[] = $level;
}

// Exclude current user's skills if logged in
if ($user) {
    $whereConditions[] = "s.user_id != ?";
    $params[] = $user['id'];
}

$whereClause = implode(' AND ', $whereConditions);

$stmt = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name, u.location, u.profile_image,
           AVG(r.rating) as avg_rating, COUNT(r.rating) as total_ratings
    FROM skills s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN reviews r ON u.id = r.reviewee_id
    WHERE $whereClause
    GROUP BY s.id, u.id
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->query("SELECT DISTINCT category FROM skills WHERE category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Skills - SwapSkills</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2><i class="fas fa-exchange-alt"></i> SwapSkills</h2>
            </div>
            <div class="nav-menu">
                <?php if ($user): ?>
                    <a href="dashboard.php" class="nav-link">Dashboard</a>
                    <a href="browse.php" class="nav-link active">Browse Skills</a>
                    <a href="requests.php" class="nav-link">My Requests</a>
                    <a href="chat.php" class="nav-link">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                    <a href="profile.php" class="nav-link">Profile</a>
                    <a href="logout.php" class="nav-link">Logout</a>
                <?php else: ?>
                    <a href="index.php" class="nav-link">Home</a>
                    <a href="browse.php" class="nav-link active">Browse Skills</a>
                    <a href="login.php" class="nav-link">Login</a>
                    <a href="register.php" class="nav-link">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="browse-container">
        <!-- Header -->
        <div class="browse-header">
            <h1>Browse Skills</h1>
            <p>Discover amazing skills offered by our community members</p>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters">
            <form method="GET" class="search-form">
                <div class="search-row">
                    <div class="search-input-group">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search skills..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Type:</label>
                        <select name="type" onchange="this.form.submit()">
                            <option value="offered" <?php echo $type === 'offered' ? 'selected' : ''; ?>>Skills Offered</option>
                            <option value="wanted" <?php echo $type === 'wanted' ? 'selected' : ''; ?>>Skills Wanted</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Category:</label>
                        <select name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo $category === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Level:</label>
                        <select name="level" onchange="this.form.submit()">
                            <option value="">All Levels</option>
                            <option value="beginner" <?php echo $level === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                            <option value="intermediate" <?php echo $level === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="expert" <?php echo $level === 'expert' ? 'selected' : ''; ?>>Expert</option>
                        </select>
                    </div>
                </div>
                
                <!-- Hidden inputs to maintain other search parameters -->
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            </form>
        </div>

        <!-- Results -->
        <div class="browse-results">
            <div class="results-header">
                <h2>
                    <?php echo count($skills); ?> Skills Found
                    <?php if ($search || $category || $level): ?>
                        <a href="browse.php?type=<?php echo $type; ?>" class="clear-filters">Clear Filters</a>
                    <?php endif; ?>
                </h2>
            </div>

            <?php if (empty($skills)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No Skills Found</h3>
                    <p>Try adjusting your search criteria or check back later for new skills.</p>
                    <?php if (!$user): ?>
                        <a href="register.php" class="btn btn-primary">Join SwapSkills</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="skills-grid">
                    <?php foreach ($skills as $skill): ?>
                        <div class="skill-card">
                            <div class="skill-user">
                                <div class="user-avatar">
                                    <?php if ($skill['profile_image']): ?>
                                        <img src="<?php echo htmlspecialchars($skill['profile_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($skill['first_name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($skill['first_name'] . ' ' . $skill['last_name']); ?></h4>
                                    <?php if ($skill['location']): ?>
                                        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($skill['location']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($skill['total_ratings'] > 0): ?>
                                        <div class="user-rating">
                                            <div class="rating-display">
                                                <?php 
                                                $avgRating = round($skill['avg_rating'], 1);
                                                for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo ($i <= $avgRating) ? 'filled' : 'empty'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="rating-text"><?php echo $avgRating; ?> (<?php echo $skill['total_ratings']; ?> reviews)</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="user-rating">
                                            <span class="rating-text">No ratings yet</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="skill-content">
                                <div class="skill-header">
                                    <h3><?php echo htmlspecialchars($skill['name']); ?></h3>
                                    <span class="skill-type <?php echo $skill['is_offered'] ? 'offered' : 'wanted'; ?>">
                                        <?php echo $skill['is_offered'] ? 'Offered' : 'Wanted'; ?>
                                    </span>
                                </div>
                                
                                <p class="skill-description"><?php echo htmlspecialchars($skill['description']); ?></p>
                                
                                <div class="skill-meta">
                                    <span class="category">
                                        <i class="fas fa-tag"></i>
                                        <?php echo htmlspecialchars($skill['category']); ?>
                                    </span>
                                    <span class="level level-<?php echo $skill['level']; ?>">
                                        <i class="fas fa-signal"></i>
                                        <?php echo ucfirst($skill['level']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="skill-actions">
                                <?php if ($user): ?>
                                    <a href="send-request.php?skill_id=<?php echo $skill['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i>
                                        Send Request
                                    </a>
                                    <a href="profile.php?user_id=<?php echo $skill['user_id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-user"></i>
                                        View Profile
                                    </a>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i>
                                        Login to Connect
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>