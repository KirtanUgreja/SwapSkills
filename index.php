<?php
require_once 'config.php';

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwapSkills - Exchange Skills with Others</title>
    <meta name="description" content="SwapSkills is a platform where you can offer your skills and request others in return. Connect with people to exchange knowledge and grow together.">
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
                <?php if ($user): ?>
                    <a href="dashboard.php" class="nav-link">Dashboard</a>
                    <a href="browse.php" class="nav-link">Browse Skills</a>
                    <a href="requests.php" class="nav-link">My Requests</a>
                    <a href="profile.php" class="nav-link">Profile</a>
                    <a href="logout.php" class="nav-link">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">Login</a>
                    <a href="register.php" class="nav-link">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Exchange Skills, Grow Together</h1>
            <p>Connect with people around you to share knowledge and learn new skills through meaningful exchanges.</p>
            <?php if (!$user): ?>
                <div class="hero-buttons">
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                    <a href="browse.php" class="btn btn-outline">Browse Skills</a>
                </div>
            <?php else: ?>
                <div class="hero-buttons">
                    <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                    <a href="browse.php" class="btn btn-outline">Browse Skills</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="hero-image">
            <div class="skill-cards">
                <div class="skill-card">
                    <i class="fas fa-code"></i>
                    <span>Web Development</span>
                </div>
                <div class="skill-card">
                    <i class="fas fa-palette"></i>
                    <span>Design</span>
                </div>
                <div class="skill-card">
                    <i class="fas fa-camera"></i>
                    <span>Photography</span>
                </div>
                <div class="skill-card">
                    <i class="fas fa-dumbbell"></i>
                    <span>Fitness</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <h2>How SwapSkills Works</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Create Your Profile</h3>
                    <p>Set up your profile with skills you offer and skills you want to learn.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Find Skill Partners</h3>
                    <p>Browse and search for people with the skills you need.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3>Exchange Skills</h3>
                    <p>Send requests, connect with others, and start exchanging knowledge.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3>Rate & Review</h3>
                    <p>Leave feedback and build your reputation in the community.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <?php
                $pdo = getDBConnection();
                
                // Get total users
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
                $userCount = $stmt->fetch()['count'];
                
                // Get total skills
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM skills");
                $skillCount = $stmt->fetch()['count'];
                
                // Get total successful swaps
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM swap_requests WHERE status = 'completed'");
                $swapCount = $stmt->fetch()['count'];
                ?>
                <div class="stat-item">
                    <h3><?php echo $userCount; ?></h3>
                    <p>Active Users</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo $skillCount; ?></h3>
                    <p>Skills Available</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo $swapCount; ?></h3>
                    <p>Successful Swaps</p>
                </div>
                <div class="stat-item">
                    <h3>50+</h3>
                    <p>Skill Categories</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fa-solid fa-rotate"></i> SwapSkills</h3>
                    <p>Connecting people through skill exchange and collaborative learning.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="browse.php">Browse Skills</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Categories</h4>
                    <ul>
                        <li><a href="browse.php?category=Technology">Technology</a></li>
                        <li><a href="browse.php?category=Design">Design</a></li>
                        <li><a href="browse.php?category=Health">Health & Fitness</a></li>
                        <li><a href="browse.php?category=Creative">Creative Arts</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 SwapSkills. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js"></script>
</body>
</html>