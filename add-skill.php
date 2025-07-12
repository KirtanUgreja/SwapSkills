<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$error = '';
$success = '';

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
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("INSERT INTO skills (user_id, name, description, category, level, is_offered) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$user['id'], $name, $description, $category, $level, $is_offered])) {
                $success = 'Skill added successfully!';
                // Clear form data
                $_POST = [];
            } else {
                $error = 'Error adding skill. Please try again.';
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
    <title>Add Skill - SwapSkills</title>
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
                <h2><i class="fas fa-plus-circle"></i> Add New Skill</h2>
                <p>Share your expertise or let others know what you want to learn</p>
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
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-align-left"></i>
                        Description *
                    </label>
                    <textarea id="description" name="description" required rows="4" 
                              placeholder="Describe your skill level, experience, and what you can offer or want to learn..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">
                            <i class="fas fa-tag"></i>
                            Category *
                        </label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="Technology" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Technology') ? 'selected' : ''; ?>>Technology</option>
                            <option value="Design" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Design') ? 'selected' : ''; ?>>Design</option>
                            <option value="Business" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Business') ? 'selected' : ''; ?>>Business</option>
                            <option value="Creative" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Creative') ? 'selected' : ''; ?>>Creative Arts</option>
                            <option value="Health" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Health') ? 'selected' : ''; ?>>Health & Fitness</option>
                            <option value="Education" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Education') ? 'selected' : ''; ?>>Education</option>
                            <option value="Language" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Language') ? 'selected' : ''; ?>>Languages</option>
                            <option value="Music" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Music') ? 'selected' : ''; ?>>Music</option>
                            <option value="Cooking" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Cooking') ? 'selected' : ''; ?>>Cooking</option>
                            <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="level">
                            <i class="fas fa-signal"></i>
                            Skill Level *
                        </label>
                        <select id="level" name="level" required>
                            <option value="">Select Level</option>
                            <option value="beginner" <?php echo (isset($_POST['level']) && $_POST['level'] === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                            <option value="intermediate" <?php echo (isset($_POST['level']) && $_POST['level'] === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="expert" <?php echo (isset($_POST['level']) && $_POST['level'] === 'expert') ? 'selected' : ''; ?>>Expert</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_offered" value="1" 
                               <?php echo (isset($_POST['is_offered']) || !isset($_POST['name'])) ? 'checked' : ''; ?>>
                        <i class="fas fa-gift"></i>
                        I am offering this skill (uncheck if you want to learn this skill)
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-plus"></i>
                    Add Skill
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