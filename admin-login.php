<?php
require_once 'config.php';

// If already logged in as admin, redirect to admin dashboard
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['is_admin']) {
        header('Location: admin.php');
        exit();
    } else {
        // Regular user trying to access admin login
        header('Location: dashboard.php');
        exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_admin = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if admin account is banned
            if (isset($user['is_banned']) && $user['is_banned']) {
                $error = 'This admin account has been suspended.';
            } else {
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Send admin login notification
                require_once 'email-notifications.php';
                sendAdminAlert($user['email'], 'Admin Login Alert', 'Administrator ' . $user['first_name'] . ' has logged into the admin panel.');
                
                header('Location: admin.php');
                exit();
            }
        } else {
            $error = 'Invalid admin credentials';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SwapSkills</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container admin-auth">
        <div class="auth-card admin-card">
            <div class="auth-header admin-header">
                <div class="admin-shield">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2>SwapSkills Admin</h2>
                <h3>Administrator Access</h3>
                <p>Secure login for platform administrators</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message admin-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form admin-form">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user-shield"></i>
                        Admin Username or Email
                    </label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           placeholder="Enter admin credentials">
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-key"></i>
                        Admin Password
                    </label>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter secure password">
                </div>
                
                <button type="submit" class="btn btn-admin">
                    <i class="fas fa-sign-in-alt"></i>
                    Access Admin Panel
                </button>
            </form>
            
            <div class="auth-footer admin-footer">
                <div class="security-notice">
                    <i class="fas fa-info-circle"></i>
                    <small>This is a secure area. All login attempts are logged.</small>
                </div>
                <div class="back-link">
                    <a href="index.php">
                        <i class="fas fa-arrow-left"></i>
                        Back to Main Site
                    </a>
                </div>
            </div>
        </div>
    </div>

    <style>
    .admin-auth {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
    }

    .admin-card {
        background: white;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        border: 2px solid #e5e7eb;
    }

    .admin-header {
        text-align: center;
        padding: 2rem 2rem 1.5rem;
        background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
        color: white;
        border-radius: 15px 15px 0 0;
    }

    .admin-shield {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 2rem;
        color: white;
        box-shadow: 0 8px 20px rgba(251, 191, 36, 0.3);
    }

    .admin-header h2 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 700;
    }

    .admin-header h3 {
        margin: 0.5rem 0 0.25rem;
        font-size: 1.1rem;
        font-weight: 500;
        color: #d1d5db;
    }

    .admin-header p {
        margin: 0;
        font-size: 0.9rem;
        color: #9ca3af;
    }

    .admin-error {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
        margin: 1.5rem 2rem;
    }

    .admin-form {
        padding: 2rem;
    }

    .admin-form .form-group label i {
        color: #6b7280;
        margin-right: 0.5rem;
    }

    .btn-admin {
        background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
        color: white;
        border: none;
        padding: 1rem 2rem;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 10px;
        width: 100%;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-admin:hover {
        background: linear-gradient(135deg, #374151 0%, #4b5563 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(31, 41, 55, 0.3);
    }

    .admin-footer {
        padding: 1.5rem 2rem;
        background: #f9fafb;
        border-radius: 0 0 15px 15px;
        border-top: 1px solid #e5e7eb;
    }

    .security-notice {
        text-align: center;
        margin-bottom: 1rem;
        color: #6b7280;
    }

    .security-notice i {
        margin-right: 0.5rem;
        color: #fbbf24;
    }

    .back-link {
        text-align: center;
    }

    .back-link a {
        color: #6b7280;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.3s ease;
    }

    .back-link a:hover {
        color: #374151;
    }

    .back-link i {
        margin-right: 0.5rem;
    }

    @media (max-width: 480px) {
        .admin-form {
            padding: 1.5rem;
        }
        
        .admin-header {
            padding: 1.5rem;
        }
        
        .admin-shield {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
    }
    </style>

    <script>
    // Add some security features
    document.addEventListener('DOMContentLoaded', function() {
        // Disable right-click context menu
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
        
        // Disable F12, Ctrl+Shift+I, Ctrl+U
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                (e.ctrlKey && e.key === 'u')) {
                e.preventDefault();
            }
        });
        
        // Focus on username field
        document.getElementById('username').focus();
    });
    </script>
</body>
</html>