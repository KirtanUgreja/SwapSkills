  
  <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2><i class="fas fa-exchange-alt"></i> SwapSkills</h2>
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
