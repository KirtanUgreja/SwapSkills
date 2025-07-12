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
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $location = sanitizeInput($_POST['location']);
        $availability = sanitizeInput($_POST['availability']);
        $bio = sanitizeInput($_POST['bio']);
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        $profile_image = $user['profile_image']; // Keep current image by default
        
        // Handle profile photo upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file_type = $_FILES['profile_photo']['type'];
            $file_size = $_FILES['profile_photo']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error = 'Invalid file type. Please upload a JPEG, PNG, or GIF image.';
            } elseif ($file_size > $max_size) {
                $error = 'File size too large. Maximum size is 5MB.';
            } else {
                $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $new_filename = $user['id'] . '_' . time() . '.' . $file_extension;
                $upload_path = 'uploads/profile_photos/' . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    // Delete old profile photo if it exists
                    if ($user['profile_image'] && file_exists($user['profile_image'])) {
                        unlink($user['profile_image']);
                    }
                    $profile_image = $upload_path;
                } else {
                    $error = 'Error uploading file. Please try again.';
                }
            }
        }
        
        if (empty($first_name)) {
            $error = 'First name is required';
        } elseif (!$error) {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, location = ?, availability = ?, bio = ?, is_public = ?, profile_image = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([$first_name, $last_name, $location, $availability, $bio, $is_public, $profile_image, $user['id']])) {
                $success = 'Profile updated successfully!';
                // Refresh user data
                $user = getCurrentUser();
            } else {
                $error = 'Error updating profile. Please try again.';
            }
        }
    }
}

// Get user's skills and stats
$pdo = getDBConnection();

// Add profile_image column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {
    // Column already exists, ignore error
}

$stmt = $pdo->prepare("SELECT * FROM skills WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$userSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM swap_requests WHERE (requester_id = ? OR provider_id = ?) AND status = 'completed'");
$stmt->execute([$user['id'], $user['id']]);
$completedSwaps = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE reviewee_id = ?");
$stmt->execute([$user['id']]);
$ratingData = $stmt->fetch();
$avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
$reviewCount = $ratingData['review_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SwapSkills</title>
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
                <a href="profile.php" class="nav-link active">Profile</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php if ($user['profile_image']): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-details">
                    <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                    <?php if ($user['location']): ?>
                        <p class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['location']); ?></p>
                    <?php endif; ?>
                    <div class="profile-stats">
                        <span class="stat">
                            <i class="fas fa-star"></i>
                            <?php echo $avgRating; ?> (<?php echo $reviewCount; ?> reviews)
                        </span>
                        <span class="stat">
                            <i class="fas fa-handshake"></i>
                            <?php echo $completedSwaps; ?> completed swaps
                        </span>
                        <span class="stat">
                            <i class="fas fa-calendar"></i>
                            Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-content">
            <!-- Profile Settings -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-cog"></i> Profile Settings</h2>
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
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="profile-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Profile Photo Upload -->
                    <div class="form-group profile-photo-section">
                        <label for="profile_photo">
                            <i class="fas fa-camera"></i>
                            Profile Photo
                        </label>
                        <div class="photo-upload-container">
                            <div class="current-photo">
                                <?php if ($user['profile_image']): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Current Profile Photo" id="current-photo-preview">
                                <?php else: ?>
                                    <div class="no-photo" id="current-photo-preview">
                                        <i class="fas fa-user"></i>
                                        <p>No photo uploaded</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="photo-upload-controls">
                                <input type="file" id="profile_photo" name="profile_photo" accept="image/*" style="display: none;">
                                <button type="button" class="btn btn-outline" onclick="document.getElementById('profile_photo').click();">
                                    <i class="fas fa-upload"></i>
                                    Choose Photo
                                </button>
                                <p class="upload-info">JPG, PNG, or GIF. Max 5MB.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">
                                <i class="fas fa-user"></i>
                                First Name *
                            </label>
                            <input type="text" id="first_name" name="first_name" required 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">
                                <i class="fas fa-user"></i>
                                Last Name
                            </label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">
                            <i class="fas fa-map-marker-alt"></i>
                            Location
                        </label>
                        <input type="text" id="location" name="location" 
                               placeholder="e.g., New York, NY"
                               value="<?php echo htmlspecialchars($user['location']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="availability">
                            <i class="fas fa-clock"></i>
                            Availability
                        </label>
                        <input type="text" id="availability" name="availability" 
                               placeholder="e.g., Weekends, Evenings, Flexible"
                               value="<?php echo htmlspecialchars($user['availability']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bio">
                            <i class="fas fa-info-circle"></i>
                            Bio
                        </label>
                        <textarea id="bio" name="bio" rows="4" 
                                  placeholder="Tell others about yourself, your interests, and what you're passionate about..."><?php echo htmlspecialchars($user['bio']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_public" <?php echo $user['is_public'] ? 'checked' : ''; ?>>
                            <i class="fas fa-globe"></i>
                            Make my profile public (others can find and contact me)
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Profile
                    </button>
                </form>
            </div>

            <!-- My Skills Summary -->
            <div class="profile-section">
                <div class="section-header">
                    <h2><i class="fas fa-star"></i> My Skills</h2>
                    <a href="add-skill.php" class="btn btn-secondary">Add Skill</a>
                </div>
                
                <?php if (empty($userSkills)): ?>
                    <div class="empty-state">
                        <i class="fas fa-plus-circle"></i>
                        <h3>No Skills Added Yet</h3>
                        <p>Add your skills to start connecting with others!</p>
                        <a href="add-skill.php" class="btn btn-primary">Add Your First Skill</a>
                    </div>
                <?php else: ?>
                    <div class="skills-summary">
                        <?php
                        $offeredSkills = array_filter($userSkills, function($skill) { return $skill['is_offered']; });
                        $wantedSkills = array_filter($userSkills, function($skill) { return !$skill['is_offered']; });
                        ?>
                        
                        <div class="skills-category">
                            <h3>Skills I Offer (<?php echo count($offeredSkills); ?>)</h3>
                            <div class="skills-list">
                                <?php foreach ($offeredSkills as $skill): ?>
                                    <div class="skill-tag offered">
                                        <span class="skill-name"><?php echo htmlspecialchars($skill['name']); ?></span>
                                        <span class="skill-level"><?php echo ucfirst($skill['level']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="skills-category">
                            <h3>Skills I Want to Learn (<?php echo count($wantedSkills); ?>)</h3>
                            <div class="skills-list">
                                <?php foreach ($wantedSkills as $skill): ?>
                                    <div class="skill-tag wanted">
                                        <span class="skill-name"><?php echo htmlspecialchars($skill['name']); ?></span>
                                        <span class="skill-level"><?php echo ucfirst($skill['level']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
    .profile-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 2rem 20px;
    }
    
    .profile-header {
        background: white;
        padding: 3rem 2rem;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        margin-bottom: 3rem;
    }
    
    .profile-info {
        display: flex;
        align-items: center;
        gap: 2rem;
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        overflow: hidden;
    }
    
    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .profile-details h1 {
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .location {
        color: #64748b;
        margin-bottom: 1rem;
    }
    
    .profile-stats {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
    }
    
    .stat {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #64748b;
    }
    
    .stat i {
        color: #667eea;
    }
    
    .profile-content {
        display: grid;
        gap: 3rem;
    }
    
    .profile-section {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .profile-form {
        display: grid;
        gap: 1.5rem;
    }
    
    .skills-summary {
        display: grid;
        gap: 2rem;
    }
    
    .skills-category h3 {
        color: #1e293b;
        margin-bottom: 1rem;
    }
    
    .skills-list {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .skill-tag {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .skill-tag.offered {
        background: #dcfce7;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }
    
    .skill-tag.wanted {
        background: #dbeafe;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }
    
    .skill-level {
        background: rgba(255,255,255,0.5);
        padding: 0.25rem 0.5rem;
        border-radius: 10px;
        font-size: 0.8rem;
    }
    
    @media (max-width: 768px) {
        .profile-info {
            flex-direction: column;
            text-align: center;
        }
        
        .profile-stats {
            justify-content: center;
        }
        
        .skills-list {
            justify-content: center;
        }
    }
    </style>

    <script>
    // Profile photo preview functionality
    document.getElementById('profile_photo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('current-photo-preview');
        
        if (file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type.toLowerCase())) {
                alert('Please select a valid image file (JPEG, PNG, or GIF).');
                this.value = '';
                return;
            }
            
            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB.');
                this.value = '';
                return;
            }
            
            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">`;
            };
            reader.readAsDataURL(file);
        }
    });
    </script>
    
    <script src="js/main.js"></script>
</body>
</html>