<?php
require_once 'config.php';
requireAuth();

$user = getCurrentUser();

// Get skill ID
$skill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$skill_id) {
    header('Location: dashboard.php');
    exit();
}

$pdo = getDBConnection();

// Verify ownership and delete
$stmt = $pdo->prepare("DELETE FROM skills WHERE id = ? AND user_id = ?");
if ($stmt->execute([$skill_id, $user['id']])) {
    $_SESSION['success_message'] = 'Skill deleted successfully!';
} else {
    $_SESSION['error_message'] = 'Error deleting skill.';
}

header('Location: dashboard.php');
exit();
?>