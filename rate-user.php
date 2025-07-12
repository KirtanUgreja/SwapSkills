<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();
$pdo = getDBConnection();

// Get request ID from URL
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

// Verify this is a valid completed request for the current user
$stmt = $pdo->prepare("
    SELECT sr.*, 
           requester.first_name as requester_first_name, requester.last_name as requester_last_name,
           provider.first_name as provider_first_name, provider.last_name as provider_last_name,
           provider_skill.name as provider_skill_name
    FROM swap_requests sr
    LEFT JOIN users requester ON sr.requester_id = requester.id
    LEFT JOIN users provider ON sr.provider_id = provider.id
    LEFT JOIN skills provider_skill ON sr.provider_skill_id = provider_skill.id
    WHERE sr.id = ? AND (sr.requester_id = ? OR sr.provider_id = ?) AND sr.status = 'completed'
");
$stmt->execute([$request_id, $user['id'], $user['id']]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('Location: requests.php');
    exit;
}

// Determine who to rate (the other user)
$other_user_id = ($request['requester_id'] == $user['id']) ? $request['provider_id'] : $request['requester_id'];
$other_user_name = ($request['requester_id'] == $user['id']) ? 
    $request['provider_first_name'] . ' ' . $request['provider_last_name'] :
    $request['requester_first_name'] . ' ' . $request['requester_last_name'];

// Check if rating already exists
$stmt = $pdo->prepare("SELECT rating FROM reviews WHERE swap_request_id = ? AND reviewer_id = ?");
$stmt->execute([$request_id, $user['id']]);
$existing_rating = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_rating) {
    header('Location: requests.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && verifyCSRFToken($_POST['csrf_token'])) {
    $rating = (int)$_POST['rating'];
    $feedback = trim($_POST['feedback']);
    
    if ($rating >= 1 && $rating <= 5) {
        $stmt = $pdo->prepare("
            INSERT INTO reviews (swap_request_id, reviewer_id, reviewee_id, rating, feedback) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$request_id, $user['id'], $other_user_id, $rating, $feedback]);
        
        header('Location: requests.php?rated=1');
        exit;
    } else {
        $error = "Please select a valid rating (1-5 stars).";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate User - SwapSkills</title>
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
                <a href="requests.php" class="nav-link active">Requests</a>
                <a href="chat.php" class="nav-link">Chat</a>
                <a href="profile.php" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="rating-container">
            <div class="rating-header">
                <h1><i class="fas fa-star"></i> Rate Your Experience</h1>
                <p>How was your skill exchange with <strong><?php echo htmlspecialchars($other_user_name); ?></strong>?</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="rating-card">
                <div class="exchange-summary">
                    <h3>Skill Exchange Summary</h3>
                    <div class="skill-info">
                        <div class="skill-learned">
                            <i class="fas fa-graduation-cap"></i>
                            <strong>Skill Learned:</strong> <?php echo htmlspecialchars($request['provider_skill_name']); ?>
                        </div>
                        <div class="exchange-partner">
                            <i class="fas fa-user"></i>
                            <strong>Exchange Partner:</strong> <?php echo htmlspecialchars($other_user_name); ?>
                        </div>
                        <div class="exchange-date">
                            <i class="fas fa-calendar"></i>
                            <strong>Completed:</strong> <?php echo date('M j, Y', strtotime($request['updated_at'])); ?>
                        </div>
                    </div>
                </div>

                <form method="POST" class="rating-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="rating">
                            <i class="fas fa-star"></i>
                            Rate Your Experience (1-5 Stars)
                        </label>
                        <div class="star-rating">
                            <input type="radio" name="rating" value="5" id="star5">
                            <label for="star5" class="star"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="4" id="star4">
                            <label for="star4" class="star"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="3" id="star3">
                            <label for="star3" class="star"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="2" id="star2">
                            <label for="star2" class="star"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="1" id="star1">
                            <label for="star1" class="star"><i class="fas fa-star"></i></label>
                        </div>
                        <div class="rating-labels">
                            <span>Poor</span>
                            <span>Excellent</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="feedback">
                            <i class="fas fa-comment"></i>
                            Feedback (Optional)
                        </label>
                        <textarea name="feedback" id="feedback" rows="4" 
                                  placeholder="Share your experience with this skill exchange. Was the teaching clear? Did you learn what you expected? How was the communication?"></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="requests.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Requests
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Rating
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
    .rating-container {
        max-width: 600px;
        margin: 2rem auto;
        padding: 2rem 20px;
    }

    .rating-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .rating-header h1 {
        color: #1e293b;
        margin-bottom: 0.5rem;
    }

    .rating-header p {
        color: #64748b;
        font-size: 1.1rem;
    }

    .rating-card {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .exchange-summary {
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: #f8fafc;
        border-radius: 10px;
        border-left: 4px solid #667eea;
    }

    .exchange-summary h3 {
        color: #1e293b;
        margin-bottom: 1rem;
    }

    .skill-info {
        display: grid;
        gap: 0.75rem;
    }

    .skill-learned,
    .exchange-partner,
    .exchange-date {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #64748b;
    }

    .skill-learned i,
    .exchange-partner i,
    .exchange-date i {
        color: #667eea;
        width: 20px;
    }

    .rating-form {
        display: grid;
        gap: 2rem;
    }

    .star-rating {
        display: flex;
        flex-direction: row-reverse;
        justify-content: center;
        gap: 0.25rem;
        margin: 1rem 0;
    }

    .star-rating input {
        display: none;
    }

    .star-rating label {
        font-size: 2rem;
        color: #e5e7eb;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .star-rating label:hover,
    .star-rating label:hover ~ label,
    .star-rating input:checked ~ label {
        color: #fbbf24;
        transform: scale(1.1);
    }

    .rating-labels {
        display: flex;
        justify-content: space-between;
        color: #64748b;
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        margin-top: 1rem;
    }

    @media (max-width: 768px) {
        .form-actions {
            flex-direction: column;
        }
        
        .star-rating label {
            font-size: 1.5rem;
        }
    }
    </style>

    <script src="js/main.js"></script>
</body>
</html>