<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$error = '';
$success = '';

// Get skill ID
$skill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$skill_id) {
    header('Location: dashboard.php');
    exit();
}

$pdo = getDBConnection();

// Get skill details and verify ownership
$stmt = $pdo->prepare("SELECT * FROM skills WHERE id = ? AND user_id = ?");
$stmt->execute([$skill_id, $user['id']]);
$skill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$skill) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $category = sanitizeInput($_POST['category']);
        $level = sanitizeInput($_POST['level']);
        $is_offered = isset($_POST['is_offered']) ? 1 : 0;
        
        if (empty($name) || empty($description) || empty($category) || empty($level)) {
            $error = 'Please fill in all required fields';
        } else {
            $stmt = $pdo->prepare("UPDATE skills SET name = ?, description = ?, category = ?, level = ?, is_offered = ? WHERE id = ? AND user_id = ?");
            
            if ($stmt->execute([$name, $description, $category, $level, $is_offered, $skill_id, $user['id']])) {
                $success = 'Skill updated successfully!';
                // Refresh skill data
                $stmt = $pdo->prepare("SELECT * FROM skills WHERE id = ? AND user_id = ?");
                $stmt->execute([$skill_id, $user['id']]);
                $skill = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Error updating skill. Please try again.';
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
    <title>Edit Skill - SwapSkills</title>
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
                <h2><i class="fas fa-edit"></i> Edit Skill</h2>
                <p>Update your skill information</p>
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
                    <a href="dashboard.php">Go to Dashboard</a>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="name">
                        <i class="fas fa-star"></i>
                        Skill Name *
                    </label>
                    <input type="text" id="name" name="name" required 
                           placeholder="e.g., Web Development, Photography, Guitar"
                           value="<?php echo htmlspecialchars($skill['name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-align-left"></i>
                        Description *
                    </label>
                    <textarea id="description" name="description" required rows="4" 
                              placeholder="Describe your skill level, experience, and what you can offer or want to learn..."><?php echo htmlspecialchars($skill['description']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">
                            <i class="fas fa-tag"></i>
                            Category *
                        </label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="Technology" <?php echo ($skill['category'] === 'Technology') ? 'selected' : ''; ?>>Technology</option>
                            <option value="Design" <?php echo ($skill['category'] === 'Design') ? 'selected' : ''; ?>>Design</option>
                            <option value="Business" <?php echo ($skill['category'] === 'Business') ? 'selected' : ''; ?>>Business</option>
                            <option value="Creative" <?php echo ($skill['category'] === 'Creative') ? 'selected' : ''; ?>>Creative Arts</option>
                            <option value="Health" <?php echo ($skill['category'] === 'Health') ? 'selected' : ''; ?>>Health & Fitness</option>
                            <option value="Education" <?php echo ($skill['category'] === 'Education') ? 'selected' : ''; ?>>Education</option>
                            <option value="Language" <?php echo ($skill['category'] === 'Language') ? 'selected' : ''; ?>>Languages</option>
                            <option value="Music" <?php echo ($skill['category'] === 'Music') ? 'selected' : ''; ?>>Music</option>
                            <option value="Cooking" <?php echo ($skill['category'] === 'Cooking') ? 'selected' : ''; ?>>Cooking</option>
                            <option value="Other" <?php echo ($skill['category'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="level">
                            <i class="fas fa-signal"></i>
                            Skill Level *
                        </label>
                        <select id="level" name="level" required>
                            <option value="">Select Level</option>
                            <option value="beginner" <?php echo ($skill['level'] === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                            <option value="intermediate" <?php echo ($skill['level'] === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="expert" <?php echo ($skill['level'] === 'expert') ? 'selected' : ''; ?>>Expert</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_offered" value="1" <?php echo $skill['is_offered'] ? 'checked' : ''; ?>>
                        <i class="fas fa-gift"></i>
                        I am offering this skill (uncheck if you want to learn this skill)
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-save"></i>
                    Update Skill
                </button>
            </form>
            
            <div class="auth-footer">
                <p><a href="dashboard.php">Back to Dashboard</a></p>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>