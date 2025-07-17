<?php
require '../connection/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    
    if (strlen($username) < 3) {
        echo json_encode(['status' => 'error', 'message' => 'Username must be at least 3 characters']);
        exit();
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Username is already taken']);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Username is available']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>