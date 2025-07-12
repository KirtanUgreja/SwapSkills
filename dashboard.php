<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDBConnection();

// Get user's skills
$stmt = $pdo->prepare("SELECT * FROM skills WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$userSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent swap requests
$stmt = $pdo->prepare("
    SELECT sr.*, 
           u1.first_name as requester_first_name, u1.last_name as requester_last_name,
           u2.first_name as provider_first_name, u2.last_name as provider_last_name,
           s1.name as requester_skill_name, s2.name as provider_skill_name
    FROM swap_requests sr
    LEFT JOIN users u1 ON sr.requester_id = u1.id
    LEFT JOIN users u2 ON sr.provider_id = u2.id
    LEFT JOIN skills s1 ON sr.requester_skill_id = s1.id
    LEFT JOIN skills s2 ON sr.provider_skill_id = s2.id
    WHERE sr.requester_id = ? OR sr.provider_id = ?
    ORDER BY sr.created_at DESC LIMIT 5
");
$stmt->execute([$user['id'], $user['id']]);
$recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user stats
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM skills WHERE user_id = ? AND is_offered = 1");
$stmt->execute([$user['id']]);
$offeredSkills = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM skills WHERE user_id = ? AND is_offered = 0");
$stmt->execute([$user['id']]);
$wantedSkills = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE (requester_id = ? OR provider_id = ?) AND status = 'completed'");
$stmt->execute([$user['id'], $user['id']]);
$completedSwaps = $stmt->fetch()['count'];

// Get user's average rating
$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM reviews WHERE reviewee_id = ?");
$stmt->execute([$user['id']]);
$ratingData = $stmt->fetch(PDO::FETCH_ASSOC);
$averageRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
$totalRatings = $ratingData['total_ratings'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SwapSkills</title>
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
                <a href="dashboard.php" class="nav-link active">Dashboard</a>
                <a href="browse.php" class="nav-link">Browse Skills</a>
                <a href="requests.php" class="nav-link">My Requests</a>
                <a href="chat.php" class="nav-link">
                    Chat
                </a>
                <a href="profile.php" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                <p>Manage your skills and connect with others in the community.</p>
            </div>
            <div class="quick-actions">
                <a href="add-skill.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Skill
                </a>
                <a href="browse.php" class="btn btn-outline">
                    <i class="fas fa-search"></i> Browse Skills
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $offeredSkills; ?></h3>
                    <p>Skills Offered</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $wantedSkills; ?></h3>
                    <p>Skills Wanted</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $completedSwaps; ?></h3>
                    <p>Completed Swaps</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3>
                        <?php if ($totalRatings > 0): ?>
                            <?php echo $averageRating; ?>
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo ($i <= $averageRating) ? 'filled' : 'empty'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </h3>
                    <p>Average Rating <?php echo $totalRatings > 0 ? "($totalRatings reviews)" : "(No ratings yet)"; ?></p>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <!-- Recent Requests -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2><i class="fa-solid fa-rotate"></i> Recent Requests</h2>
                    <a href="requests.php" class="view-all">View All</a>
                </div>
                <div class="requests-list">
                    <?php if (empty($recentRequests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Recent Requests</h3>
                            <p>Start browsing skills to send your first request!</p>
                            <a href="browse.php" class="btn btn-primary">Browse Skills</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentRequests as $request): ?>
                            <div class="request-item">
                                <div class="request-info">
                                    <div class="request-details">
                                        <?php if ($request['requester_id'] == $user['id']): ?>
                                            <h4>Request to <?php echo htmlspecialchars($request['provider_first_name'] . ' ' . $request['provider_last_name']); ?></h4>
                                            <p>You want: <strong><?php echo htmlspecialchars($request['provider_skill_name']); ?></strong></p>
                                            <?php if ($request['requester_skill_name']): ?>
                                                <p>You offer: <strong><?php echo htmlspecialchars($request['requester_skill_name']); ?></strong></p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <h4>Request from <?php echo htmlspecialchars($request['requester_first_name'] . ' ' . $request['requester_last_name']); ?></h4>
                                            <p>They want: <strong><?php echo htmlspecialchars($request['provider_skill_name']); ?></strong></p>
                                            <?php if ($request['requester_skill_name']): ?>
                                                <p>They offer: <strong><?php echo htmlspecialchars($request['requester_skill_name']); ?></strong></p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="request-meta">
                                        <span class="status status-<?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                        <span class="date"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Skills -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2><i class="fas fa-star"></i> My Skills</h2>
                    <a href="add-skill.php" class="btn btn-secondary">Add Skill</a>
                </div>
                <div class="skills-grid">
                    <?php if (empty($userSkills)): ?>
                        <div class="empty-state">
                            <i class="fas fa-plus-circle"></i>
                            <h3>No Skills Added Yet</h3>
                            <p>Add your first skill to start connecting with others!</p>
                            <a href="add-skill.php" class="btn btn-primary">Add Your First Skill</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($userSkills as $skill): ?>
                            <div class="skill-card">
                                <div class="skill-header">
                                    <h3><?php echo htmlspecialchars($skill['name']); ?></h3>
                                    <span class="skill-type <?php echo $skill['is_offered'] ? 'offered' : 'wanted'; ?>">
                                        <?php echo $skill['is_offered'] ? 'Offered' : 'Wanted'; ?>
                                    </span>
                                </div>
                                <p class="skill-description"><?php echo htmlspecialchars($skill['description']); ?></p>
                                <div class="skill-meta">
                                    <span class="category"><?php echo htmlspecialchars($skill['category']); ?></span>
                                    <span class="level level-<?php echo $skill['level']; ?>">
                                        <?php echo ucfirst($skill['level']); ?>
                                    </span>
                                </div>
                                <div class="skill-actions">
                                    <a href="edit-skill.php?id=<?php echo $skill['id']; ?>" class="btn btn-sm">Edit</a>
                                    <a href="delete-skill.php?id=<?php echo $skill['id']; ?>" class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this skill?')">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>