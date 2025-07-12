<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$error = '';
$success = '';

// Get skill details
$skill_id = isset($_GET['skill_id']) ? (int)$_GET['skill_id'] : 0;
if (!$skill_id) {
    header('Location: browse.php');
    exit();
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name, u.location, u.profile_image,
           AVG(r.rating) as avg_rating, COUNT(r.rating) as total_ratings
    FROM skills s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN reviews r ON u.id = r.reviewee_id
    WHERE s.id = ? AND s.user_id != ?
    GROUP BY s.id, u.id
");
$stmt->execute([$skill_id, $user['id']]);
$skill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$skill) {
    header('Location: browse.php');
    exit();
}

// Get user's offered skills (for exchange)
$stmt = $pdo->prepare("SELECT * FROM skills WHERE user_id = ? AND is_offered = 1");
$stmt->execute([$user['id']]);
$userSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $message = sanitizeInput($_POST['message']);
        $requester_skill_id = !empty($_POST['requester_skill_id']) ? (int)$_POST['requester_skill_id'] : null;
        
        if (empty($message)) {
            $error = 'Please include a message with your request';
        } else {
            // Check if request already exists
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM swap_requests 
                WHERE requester_id = ? AND provider_id = ? AND provider_skill_id = ? 
                AND status IN ('pending', 'accepted')
            ");
            $stmt->execute([$user['id'], $skill['user_id'], $skill_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'You already have a pending or accepted request for this skill';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO swap_requests (requester_id, provider_id, requester_skill_id, provider_skill_id, message) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$user['id'], $skill['user_id'], $requester_skill_id, $skill_id, $message])) {
                    $success = 'Request sent successfully!';
                } else {
                    $error = 'Error sending request. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Request - SwapSkills</title>
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
                <a href="chat.php" class="nav-link">
                    Chat
                </a>
                <a href="profile.php" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <div class="auth-container">
        <div class="auth-card" style="max-width: 600px;">
            <div class="auth-header">
                <h2><i class="fas fa-paper-plane"></i> Send Skill Request</h2>
                <p>Connect with <?php echo htmlspecialchars($skill['first_name']); ?> to learn or exchange skills</p>
            </div>
            
            <!-- Skill Details -->
            <div class="skill-preview">
                <h3><?php echo htmlspecialchars($skill['name']); ?></h3>
                <p><?php echo htmlspecialchars($skill['description']); ?></p>
                <div class="skill-meta">
                    <span class="category"><?php echo htmlspecialchars($skill['category']); ?></span>
                    <span class="level level-<?php echo $skill['level']; ?>">
                        <?php echo ucfirst($skill['level']); ?>
                    </span>
                </div>
                <div class="skill-provider">
                    <div class="provider-info">
                        <div class="provider-avatar">
                            <?php if ($skill['profile_image']): ?>
                                <img src="<?php echo htmlspecialchars($skill['profile_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($skill['first_name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="provider-details">
                            <strong><?php echo htmlspecialchars($skill['first_name'] . ' ' . $skill['last_name']); ?></strong>
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
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                    <a href="requests.php">View My Requests</a>
                </div>
            <?php else: ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="requester_skill_id">
                            <i class="fas fa-gift"></i>
                            Skill to Offer in Exchange (Optional)
                        </label>
                        <select id="requester_skill_id" name="requester_skill_id">
                            <option value="">No skill exchange - just asking for help</option>
                            <?php foreach ($userSkills as $userSkill): ?>
                                <option value="<?php echo $userSkill['id']; ?>">
                                    <?php echo htmlspecialchars($userSkill['name']); ?> (<?php echo ucfirst($userSkill['level']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($userSkills)): ?>
                            <small>You don't have any offered skills yet. <a href="add-skill.php">Add a skill</a> to offer in exchange.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">
                            <i class="fas fa-comment"></i>
                            Message *
                        </label>
                        <textarea id="message" name="message" required rows="6" 
                                  placeholder="Introduce yourself and explain why you're interested in learning this skill. Be specific about what you'd like to learn and what you can offer in return."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fas fa-paper-plane"></i>
                        Send Request
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p><a href="browse.php">Back to Browse</a></p>
            </div>
        </div>
    </div>

    <style>
    .skill-preview {
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 2rem;
        border: 1px solid #e2e8f0;
    }
    
    .skill-preview h3 {
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .skill-preview p {
        color: #64748b;
        margin-bottom: 1rem;
    }
    
    .skill-provider {
        margin-top: 1rem;
        color: #475569;
        font-size: 0.9rem;
    }
    </style>

    <script src="js/main.js"></script>
</body>
</html>