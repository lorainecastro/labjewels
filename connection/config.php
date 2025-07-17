<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Database configuration
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3307');
    define('DB_NAME', 'jewels');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'u801377270_labjewels_db');
    define('DB_USER', 'u801377270_labjewels_db');
    define('DB_PASS', 'Labjewels@2025');
}

// Create database connection
function getDBConnection()
{
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Connection failed: " . $e->getMessage());
        die("Connection failed. Please try again later. $dsn");
    }
}

// Utility functions
function generateSessionToken($length = 64)
{
    try {
        return bin2hex(random_bytes($length / 2));
    } catch (Exception $e) {
        error_log("Token generation error: " . $e->getMessage());
        die("Failed to generate session token.");
    }
}

// Session management
function createUserSession($userId)
{
    $pdo = getDBConnection();
    $token = generateSessionToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days')); // Session expires in 30 days

    try {
        // Delete any existing sessions for the user
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);

        // Create new session
        $stmt = $pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $token, $expiresAt]);

        // Store session data
        session_start(); // Ensure session is started
        $_SESSION['user_id'] = $userId;
        $_SESSION['session_token'] = $token;

        return $token;
    } catch (PDOException $e) {
        error_log("Session creation error: " . $e->getMessage());
        return false;
    }
}

// function validateSession() {
//     // session_start(); // Ensure session is started
//     if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
//         return false;
//     }

//     $pdo = getDBConnection();
//     try {
//         $stmt = $pdo->prepare("
//             SELECT * FROM user_sessions 
//             WHERE user_id = ? AND session_token = ? AND expires_at > NOW()
//         ");
//         $stmt->execute([$_SESSION['user_id'], $_SESSION['session_token']]);
//         $session = $stmt->fetch();

//         if ($session) {
//             return true;
//         } else {
//             destroySession();
//             return false;
//         }
//     } catch (PDOException $e) {
//         error_log("Session validation error: " . $e->getMessage());
//         return false;
//     }
// }

function validateSession()
{
    // session_start(); // Ensure session is started
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        return false;
    }

    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, s.expires_at, s.session_token
            FROM users u 
            JOIN user_sessions s ON u.user_id = s.user_id 
            WHERE s.user_id = ? AND s.session_token = ? AND s.expires_at > NOW()
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['session_token']]);
        $user = $stmt->fetch();

        if ($user) {
            return $user;
        } else {
            destroySession();
            return false;
        }
    } catch (PDOException $e) {
        error_log("Session validation error: " . $e->getMessage());
        return false;
    }
}

function destroySession()
{
    $pdo = getDBConnection(); // Use getDBConnection instead of global $pdo
    session_start(); // Ensure session is started
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {
            error_log("Session deletion error: " . $e->getMessage());
        }
    }
    // Clear session data
    $_SESSION = array(); // Clear all session variables
}
