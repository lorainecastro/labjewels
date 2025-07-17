<?php
require '../connection/config.php';
session_start();

require '../PHPMailer/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// reCAPTCHA configuration
define('RECAPTCHA_SITE_KEY', '6LethIUrAAAAANtwSmP78MGGkWBfWKHMGZdketNO'); // Replace with your actual site key
define('RECAPTCHA_SECRET_KEY', '6LethIUrAAAAAOfW1KvvZfd-s8-4SFyGzSMs276W'); // Replace with your actual secret key

$notification = ['message' => '', 'type' => ''];
$firstname = $lastname = $email = $username = $address = $phone = '';

// Function to verify reCAPTCHA
function verifyRecaptcha($recaptcha_response) {
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $resultJson = json_decode($result);
    
    return $resultJson->success;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm-password'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Validation
    if (empty($firstname) || empty($lastname) || empty($email) || empty($username) || empty($password) || empty($confirm_password) || empty($address) || empty($phone)) {
        $notification = ['message' => 'Please fill in all fields', 'type' => 'error'];
    } elseif (empty($recaptcha_response)) {
        $notification = ['message' => 'Please complete the reCAPTCHA verification', 'type' => 'error'];
    } elseif (!verifyRecaptcha($recaptcha_response)) {
        $notification = ['message' => 'reCAPTCHA verification failed. Please try again.', 'type' => 'error'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $notification = ['message' => 'Invalid email format', 'type' => 'error'];
    } elseif ($password !== $confirm_password) {
        $notification = ['message' => 'Passwords do not match', 'type' => 'error'];
    } elseif (strlen($password) < 8) {
        $notification = ['message' => 'Password must be at least 8 characters long', 'type' => 'error'];
    } elseif (!isset($_POST['terms'])) {
        $notification = ['message' => 'You must agree to the Terms of Service and Privacy Policy', 'type' => 'error'];
    } else {
        try {
            $pdo = getDBConnection();

            // Check if email or username already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetchColumn() > 0) {
                $notification = ['message' => 'Email or username already exists', 'type' => 'error'];
            } else {
                // Generate OTP
                $otp = sprintf("%06d", mt_rand(100000, 999999));
                $otp_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert user with OTP
                $stmt = $pdo->prepare("
                    INSERT INTO users (firstname, lastname, email, username, password, address, phone, otp_code, otp_purpose, otp_expires_at, isActive, isVerified, created_at, otp_created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'EMAIL_VERIFICATION', ?, 0, 0, NOW(), NOW())
                ");
                $stmt->execute([$firstname, $lastname, $email, $username, $hashed_password, $address, $phone, $otp, $otp_expires]);

                // Store user data in session for verification
                $_SESSION['signup_email'] = $email;
                $_SESSION['signup_user_id'] = $pdo->lastInsertId(); // Store user ID for session creation after verification

                // Send OTP email
                $mail = new PHPMailer(true);
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'elci.bank@gmail.com';
                    $mail->Password = 'misxfqnfsovohfwh';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Recipients
                    $mail->setFrom('castro.loraine.26@gmail.com', 'LAB Jewels');
                    $mail->addAddress($email);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Verify Your LAB Jewels Account';
                    $mail->Body = "
                        <h2>Welcome to LAB Jewels!</h2>
                        <p>Your OTP for email verification is: <strong>$otp</strong></p>
                        <p>This code is valid for 15 minutes. Please enter it on the verification page.</p>
                        <p>If you did not request this, please ignore this email.</p>
                    ";

                    $mail->send();

                    // Redirect to verification page
                    header("Location: verify-email.php");
                    exit();
                } catch (Exception $e) {
                    $notification = ['message' => 'Failed to send OTP. Please try again.', 'type' => 'error'];
                }
            }
        } catch (PDOException $e) {
            $notification = ['message' => 'Database error: ' . $e->getMessage(), 'type' => 'error'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAB Jewels - Sign Up</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- <link rel="stylesheet" href="sign.css"> -->
  <!-- Google reCAPTCHA -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
    <style>
        :root {
    --black: #1a1a1a;
    --white: #ffffff;
    --gray: #6b7280;
    --light-gray: #e5e7eb;
    --dark-gray: #4b5563;
    --shadow-sm: 0 2px 4px rgba(112, 28, 28, 0.05);
    --shadow-md: 0 6px 12px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 12px 24px rgba(0, 0, 0, 0.15);
    --transition: all 0.3s ease;
    --gradient: linear-gradient(135deg, #2c2c2c, #1a1a1a);
    --primary-color: var(--black);
    --primary-gradient: var(--gradient);
    --text-color: var(--black);
    --text-light: var(--gray);
    --bg-color: var(--white);
    --card-bg: rgba(255, 255, 255, 0.95);
    --border-color: var(--light-gray);
    --error-color: #dc2626;
    --success-color: var(--dark-gray);
    --input-bg: #f9fafb;
    --input-bg-focus: #ffffff;
  }

  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', -apple-system, sans-serif;
  }

  body {
    background-color: var(--bg-color);
    color: var(--text-color);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow-x: hidden;
    position: relative;
  }

  .bg-circle {
    position: absolute;
    border-radius: 50%;
    z-index: -1;
  }

  .bg-circle-1 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, rgba(44, 44, 44, 0.15), rgba(26, 26, 26, 0.15));
    top: -100px;
    right: -100px;
  }

  .bg-circle-2 {
    width: 200px;
    height: 200px;
    background: linear-gradient(135deg, rgba(44, 44, 44, 0.12), rgba(26, 26, 26, 0.12));
    bottom: 0px;
    left: 10%;
  }

  .bg-circle-3 {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, rgba(44, 44, 44, 0.1), rgba(26, 26, 26, 0.1));
    top: 20%;
    left: -50px;
  }

  .bg-circle-4 {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, rgba(44, 44, 44, 0.1), rgba(26, 26, 26, 0.1));
    bottom: 30%;
    right: 5%;
  }

  .button {
    background-color: var(--black);
    color: var(--white);
    padding: 14px 28px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    text-align: center;
    display: inline-block;
    text-decoration: none;
  }

  .button:hover {
    background: var(--gradient);
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
  }

  .button.secondary {
    background: var(--white);
    color: var(--black);
    border: 1px solid var(--black);
    text-decoration: none;
  }

  .button.secondary:hover {
    background: var(--light-gray);
    box-shadow: var(--shadow-md);
  }

  header {
    background-color: var(--white);
    padding: 20px 40px;
    position: sticky;
    top: 0;
    z-index: 100;
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
  }

  header.scrolled {
    padding: 15px 30px;
    box-shadow: var(--shadow-md);
  }

  nav {
    display: flex;
    align-items: center;
    max-width: 1280px;
    margin: 0 auto;
    justify-content: space-between;
  }

  nav h1 {
    font-size: 1.8rem;
    font-weight: 700;
    font-family: 'Poppins', sans-serif;
    background: var(--black);
    background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  nav ul {
    display: flex;
    list-style: none;
    align-items: center;
  }

  nav ul li {
    margin-left: 35px;
  }

  nav ul li a {
    color: var(--black);
    text-decoration: none;
    font-size: 15px;
    font-weight: 600;
    text-transform: uppercase;
    position: relative;
    transition: var(--transition);
  }

  nav ul li a:not(.button):hover {
    color: var(--dark-gray);
  }

  nav ul li a:not(.button)::before {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background-color: var(--black);
    transition: width 0.3s ease;
  }

  nav ul li a:not(.button):hover::before {
    width: 100%;
  }

  .menu-toggle {
    display: none;
    cursor: pointer;
    z-index: 1000;
    position: relative;
  }

  .bar {
    width: 28px;
    height: 3px;
    background-color: var(--dark-gray);
    margin: 6px 0;
    transition: var(--transition);
  }

  .menu-toggle.active .bar:nth-child(1) {
    transform: rotate(-45deg) translate(-6px, 6px);
  }

  .menu-toggle.active .bar:nth-child(2) {
    opacity: 0;
  }

  .menu-toggle.active .bar:nth-child(3) {
    transform: rotate(45deg) translate(-6px, -6px);
  }

  .signup-container {
    width: 100%;
    max-width: 1000px;
    background: var(--card-bg);
    border-radius: 24px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    position: relative;
    z-index: 1;
    display: flex;
    margin: 50px auto;
  }

  .signup-left {
    width: 35%;
    padding: 40px;
    background: var(--primary-gradient);
    color: var(--white);
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
  }

  .signup-left::before {
    content: '';
    position: absolute;
    top: -30%;
    left: -30%;
    width: 150%;
    height: 160%;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    transform: rotate(45deg);
    z-index: 0;
  }

  .brand-icon {
    width: 60px;
    height: 60px;
    background: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    position: relative;
    z-index: 1;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
  }

  .brand-icon svg {
    width: 30px;
    height: 30px;
    fill: var(--black);
  }

  .signup-left h1 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
  }

  .signup-left p {
    font-size: 16px;
    margin-bottom: 30px;
    opacity: 0.9;
    position: relative;
    z-index: 1;
    line-height: 1.5;
    font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
  }

  .signup-right {
    width: 80%;
    padding: 40px;
    overflow-y: auto;
    max-height: 670px;
    scrollbar-width: thin;
    scrollbar-color: var(--black) var(--light-gray);
  }

  .signup-right::-webkit-scrollbar {
    width: 8px;
  }

  .signup-right::-webkit-scrollbar-track {
    background: rgba(229, 231, 235, 0.3);
    border-radius: 10px;
  }

  .signup-right::-webkit-scrollbar-thumb {
    background: var(--primary-gradient);
    border-radius: 10px;
    transition: var(--transition);
  }

  .signup-right::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, #1a1a1a, #2c2c2c);
  }

  .form-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 25px;
    color: var(--text-color);
  }

  #notification {
    display: <?php echo $notification['message'] ? 'block' : 'none'; ?>;
    padding: 12px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
    font-size: 14px;
    font-weight: 500;
    color: var(--white);
    background-color: <?php echo $notification['type'] === 'error' ? 'var(--error-color)' : 'var(--success-color)'; ?>;
    animation: fadeIn 0.4s;
  }

  .form-columns {
    display: flex;
    gap: 20px;
  }

  .form-column {
    flex: 1;
  }

  .input-group {
    margin-bottom: 18px;
    position: relative;
  }

  .input-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: var(--text-color);
    font-size: 15px;
  }

  .input-field {
    position: relative;
  }

  .input-field input {
    width: 100%;
    padding: 15px 16px 15px 40px;
    font-size: 15px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    outline: none;
    transition: var(--transition);
    background-color: var(--input-bg);
  }

  .input-field input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.05);
    background-color: var(--input-bg-focus);
  }

  .input-field .icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-light);
    transition: var(--transition);
  }

  .input-field input:focus + .icon {
    color: var(--primary-color);
  }

  .password-strength {
    display: flex;
    height: 4px;
    margin-top: 6px;
    border-radius: 4px;
    overflow: hidden;
  }

  .strength-segment {
    flex: 1;
    height: 100%;
    background-color: var(--border-color);
    margin-right: 3px;
    transition: var(--transition);
  }

  .strength-segment:last-child {
    margin-right: 0;
  }

  .strength-segment.weak {
    background-color: #ef4444;
  }

  .strength-segment.medium {
    background-color: #f59e0b;
  }

  .strength-segment.strong {
    background-color: #10b981;
  }

  .password-feedback {
    font-size: 12px;
    margin-top: 6px;
    color: var(--text-light);
    transition: var(--transition);
  }

  .password-feedback.weak {
    color: #ef4444;
  }

  .password-feedback.medium {
    color: #f59e0b;
  }

  .password-feedback.strong {
    color: #10b981;
  }

  .toggle-password {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--text-light);
    background: none;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    transition: var(--transition);
  }

  .toggle-password:hover {
    color: var(--primary-color);
  }

  .toggle-password svg {
    width: 18px;
    height: 18px;
  }

  .terms-checkbox {
    display: flex;
    align-items: flex-start;
    margin-bottom: 20px;
    margin-top: 10px;
  }

  .terms-checkbox input {
    margin-right: 10px;
    margin-top: 3px;
    accent-color: var(--primary-color);
    width: 16px;
    height: 16px;
  }

  .terms-checkbox label {
    font-size: 15px;
    color: var(--text-light);
    line-height: 1.4;
  }

  .terms-checkbox a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: bold;
    transition: var(--transition);
  }

  .terms-checkbox a:hover {
    color: var(--dark-gray);
    text-decoration: underline;
  }

  .btn-signup {
    width: 100%;
    padding: 12px;
    background: var(--primary-gradient);
    color: var(--white);
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    position: relative;
    overflow: hidden;
    z-index: 1;
  }

  .btn-signup::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: var(--transition);
    z-index: -1;
  }

  .btn-signup:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.35);
  }

  .btn-signup:hover::before {
    left: 100%;
    transition: 0.7s;
  }

  .btn-signup:active {
    transform: translateY(0);
  }

  .btn-signup.loading {
    opacity: 0.8;
    pointer-events: none;
  }

  .btn-signup .spinner {
    display: none;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: var(--white);
    animation: spin 1s linear infinite;
    margin: 0 auto;
  }

  .btn-signup.loading .spinner {
    display: block;
  }

  .btn-signup.loading span {
    display: none;
  }

  .login-link {
    text-align: center;
    margin-top: 20px;
    font-size: 15px;
    color: var(--text-light);
  }

  .login-link a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
  }

  .login-link a:hover {
    color: var(--dark-gray);
    text-decoration: underline;
  }

  .footer {
    background: var(--gradient);
    color: var(--white);
    padding: 80px 20px;
  }

  .footer-container {
    max-width: 1280px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 3fr 1fr 1fr;
    gap: 40px;
    margin-bottom: 40px;
  }

  .brand-section {
    display: flex;
    flex-direction: column;
    gap: 25px;
  }

  .logo {
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 27px;
    font-weight: 800;
    color: var(--white);
  }

  .logo h1 {
    font-size: 1.8rem;
    font-weight: 700;
    font-family: 'Poppins', sans-serif;
    background: var(--white);
    background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .logo svg {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background-color: var(--white);
    padding: 10px;
    fill: var(--black);
  }

  .brand-description {
    font-size: 18px;
    color: var(--light-gray);
    max-width: 320px;
    font-weight: 400;
    line-height: 1.5;
  }

  .footer-column h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--white);
    margin-bottom: 25px;
    position: relative;
    padding-bottom: 12px;
  }

  .footer-column h4::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 50px;
    height: 3px;
    background-color: var(--white);
    border-radius: 2px;
  }

  .footer-links {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .footer-links a {
    color: var(--light-gray);
    text-decoration: none;
    font-size: 16px;
    transition: var(--transition);
    font-weight: 400;
  }

  .footer-links a:hover {
    color: var(--white);
    transform: translateX(5px);
  }

  .social-links {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
  }

  .social-link {
    width: 44px;
    height: 44px;
    background-color: var(--dark-gray);
    border-radius: 8px;
    display: flex;
    justify-content: center;
    align-items: center;
    color: var(--white);
    transition: var(--transition);
    font-size: 18px;
    text-decoration: none;
  }

  .social-link:hover {
    background-color: var(--white);
    color: var(--black);
    transform: translateY(-4px);
  }

  .footer-bottom {
    border-top: 1px solid var(--light-gray);
    padding-top: 25px;
    text-align: center;
  }

  .copyright {
    font-size: 16px;
    color: var(--light-gray);
    font-weight: 400;
  }

  .copyright a {
    color: var(--white);
    text-decoration: none;
  }

  .copyright a:hover {
    text-decoration: underline;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  @keyframes ripple {
    to { transform: scale(4); opacity: 0; }
  }

  .ripple {
    position: absolute;
    width: 100px;
    height: 100px;
    background-color: rgba(255, 255, 255, 0.7);
    border-radius: 50%;
    transform: scale(0);
    animation: ripple 0.6s linear;
    pointer-events: none;
  }

  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
  }

  .error-shake {
    animation: shake 0.5s cubic-bezier(.36, .07, .19, .97) both;
  }

  .brand-icon::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--white);
    z-index: -1;
    opacity: 0.6;
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0% { transform: scale(1); opacity: 0.6; }
    70% { transform: scale(1.3); opacity: 0; }
    100% { transform: scale(1.3); opacity: 0; }
  }

  @media (max-width: 992px) {
    .signup-container {
      max-width: 800px;
      margin: 20px auto;
    }
  }

  @media (max-width: 768px) {
    header {
      padding: 15px 20px;
    }

    nav ul {
      position: fixed;
      top: 0;
      right: -100%;
      width: 70%;
      height: 100vh;
      background: var(--primary-gradient);
      flex-direction: column;
      justify-content: center;
      align-items: center;
      transition: right 0.5s ease;
      z-index: 999;
    }

    nav ul.active {
      right: 0;
    }

    nav ul li {
      margin: 25px 0;
    }

    nav ul li a {
      color: var(--white);
      font-size: 18px;
    }

    .menu-toggle {
      display: block;
    }

    .signup-container {
      flex-direction: column;
      max-width: 90%;
      margin: 20px auto;
    }

    .signup-left, .signup-right {
      width: 100%;
      padding: 30px;
    }

    .form-columns {
      flex-direction: column;
      gap: 0;
    }

    .footer {
      padding: 50px 20px;
    }

    .footer-container {
      grid-template-columns: 1fr;
      text-align: center;
    }

    .footer-column h4::after {
      left: 50%;
      transform: translateX(-50%);
    }

    .footer-links {
      align-items: center;
    }

    .logo {
      flex-direction: column;
      text-align: center;
    }
  }

  @media (max-width: 576px) {
    /* .signup-left, .signup-right {
      padding: 15px;
    } */

    .signup-left, .signup-right {
      width: 100%;
      padding: 30px;
    }

    .signup-left h1 {
      font-size: 24px;
    }

    .signup-left p {
      font-size: 14px;
    }

    .form-title {
      font-size: 20px;
    }

    .input-group label {
      font-size: 14px;
    }

    .input-field input {
      padding: 12px 12px 12px 36px;
      font-size: 14px;
    }

    .input-field .icon {
      font-size: 14px;
      left: 10px;
    }

    .toggle-password {
      right: 10px;
    }

    .password-feedback {
      font-size: 11px;
    }

    .terms-checkbox label {
      font-size: 14px;
    }

    .btn-signup {
      padding: 12px;
      font-size: 15px;
    }

    .login-link {
      font-size: 14px;
    }

    .footer {
      padding: 40px 15px;
    }

    .copyright {
      font-size: 14px;
    }
  }

  @media (max-width: 480px) {
    .signup-container {
      max-width: 90%;
      border-radius: 16px;
    }

    /* .signup-left {
      padding: 10px;
    } */

    .signup-left h1 {
      font-size: 20px;
      margin-bottom: 10px;
    }

    .signup-left p {
      font-size: 13px;
      margin-bottom: 20px;
    }

    .brand-icon {
      width: 50px;
      height: 50px;
    }

    .brand-icon svg {
      width: 25px;
      height: 25px;
    }

    /* .signup-right {
      padding: 10px;
    } */

    .signup-left, .signup-right {
      width: 100%;
      padding: 30px;
    }

    .form-title {
      font-size: 18px;
      margin-bottom: 15px;
    }

    .input-group {
      margin-bottom: 20px;
    }

    .input-group label {
      font-size: 14px;
      margin-bottom: 8px;
    }

    .input-field input {
      padding: 17px 12px 15px 40px;
      font-size: 15px;
    }

    .input-field .icon {
      font-size: 15px;
      left: 15px;
    }

    .icon {
      left: 10px;
      padding: 18px 0px 15px 0px;
    }

    .toggle-password {
      right: 8px;
    }

    .password-strength {
      height: 3px;
    }

    .password-feedback {
      font-size: 15px;
    }

    .terms-checkbox {
      margin-bottom: 15px;
    }

    .terms-checkbox label {
      font-size: 15px;
    }

    .terms-checkbox input {
      width: 15px;
      height: 15px;
    }

    .btn-signup {
      padding: 15px;
      font-size: 15px;
    }

    .login-link {
      font-size: 13px;
      margin-top: 15px;
    }

    .footer {
      padding: 50px 20px;
    }

    .footer-container {
      grid-template-columns: 1fr;
      text-align: center;
    }

    /* .footer-column h4::after {
      left: 50%;
      transform: translateX(-50%);
    } */

    .footer-links {
      align-items: center;
    }

    .logo {
      flex-direction: column;
      text-align: center;
    }
  }
    </style>
</head>
<body>
  <!-- Header -->
  <header>
    <nav>
      <h1>LAB Jewels</h1>
      <div class="menu-toggle">
        <div class="bar"></div>
        <div class="bar"></div>
        <div class="bar"></div>
      </div>
      <ul>
        <li><a href="../pages/landing.php#home">Home</a></li>
        <!-- <li><a href="../pages/landing.php#shop">Shop</a></li> -->
        <li><a href="../pages/landing.php#categories">Shop</a></li>
        <li><a href="../pages/landing.php#about">About</a></li>
        <li><a href="../pages/landing.php#contact">Contact</a></li>
        <li><a href="#cart"><i class="fas fa-shopping-cart" title="Cart"></i></a></li>
        <li><a href="login.php" class="button">Login</a></li>
      </ul>
    </nav>
  </header>

  <!-- Signup Section -->
  <div class="signup-container">
    <div class="signup-left">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>
      </div>
      <h1>Create Account</h1>
      <p>Join LAB Jewels to explore our exclusive jewelry collections and personalized shopping experience.</p>
    </div>
    <div class="signup-right">
      <h2 class="form-title">Sign Up</h2>
      <div id="notification"><?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?></div>
      <form id="signupForm" method="post" action="">
        <div class="form-columns">
          <div class="form-column">
            <div class="input-group">
              <label for="firstname">First Name</label>
              <div class="input-field">
                <input type="text" id="firstname" name="firstname" placeholder="First Name" value="<?php echo isset($firstname) ? htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </div>
              </div>
            </div>
            <div class="input-group">
              <label for="address">Address</label>
              <div class="input-field">
                <input type="text" id="address" name="address" placeholder="Address" value="<?php echo isset($address) ? htmlspecialchars($address, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                  </svg>
                </div>
              </div>
            </div>
            <div class="input-group">
              <label for="email">Email Address</label>
              <div class="input-field">
                <input type="email" id="email" name="email" placeholder="your.email@example.com" value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                  </svg>
                </div>
              </div>
            </div>
            <div class="input-group">
              <label for="password">Password</label>
              <div class="input-field">
                <input type="password" id="password" name="password" placeholder="Choose a strong password" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                  </svg>
                </div>
                <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                  <svg class="eye" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="eye-off" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
              <div class="password-strength">
                <div class="strength-segment"></div>
                <div class="strength-segment"></div>
                <div class="strength-segment"></div>
                <div class="strength-segment"></div>
              </div>
              <div class="password-feedback">Password strength: Enter a password</div>
            </div>
          </div>
          <div class="form-column">
            <div class="input-group">
              <label for="lastname">Last Name</label>
              <div class="input-field">
                <input type="text" id="lastname" name="lastname" placeholder="Last Name" value="<?php echo isset($lastname) ? htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </div>
              </div>
            </div>
            <div class="input-group">
              <label for="phone">Phone Number</label>
              <div class="input-field">
                <input type="text" id="phone" name="phone" placeholder="09xxxxxxxxx" value="<?php echo isset($phone) ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                </div>
              </div>
            </div>
            <div class="input-group">
              <label for="username">Username</label>
              <div class="input-field">
                <input type="text" id="username" name="username" placeholder="Choose a username" value="<?php echo isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </div>
              </div>
              <div class="username-feedback" style="font-size: 12px; margin-top: 6px; color: var(--text-light);"></div>
            </div>
            <div class="input-group">
              <label for="confirm-password">Confirm Password</label>
              <div class="input-field">
                <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirm your password" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                  </svg>
                </div>
                <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                  <svg class="eye" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="eye-off" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <!-- reCAPTCHA -->
        <div class="recaptcha-container" style="margin: 20px 0; display: flex; justify-content: center;">
          <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
        </div>
        
        <div class="terms-checkbox">
          <input type="checkbox" id="terms" name="terms" required>
          <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
        </div>
        <button type="submit" class="btn-signup">
          <span>Create Account</span>
          <div class="spinner"></div>
        </button>
        <div class="login-link">
          Already have an account? <a href="login.php">Log in</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-container">
      <div class="brand-section">
        <div class="logo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
          </svg>
          <h1>LAB Jewels</h1>
        </div>
        <p class="brand-description">Crafting elegance since 2025. Discover jewelry that celebrates your unique style.</p>
        <div class="social-links">
          <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
        </div>
      </div>
      <div class="footer-column">
        <h4>Quick Links</h4>
        <div class="footer-links">
          <a href="../landing.php">Home</a>
          <a href="../landing.php">Shop</a>
          <a href="../landing.php">About</a>
          <a href="../landing.php#contact">Contact</a>
        </div>
      </div>
      <div class="footer-column">
        <h4>Support</h4>
        <div class="footer-links">
          <a href="#">Help Center</a>
          <a href="#">Returns</a>
          <a href="#">Shipping</a>
          <a href="#">FAQs</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="copyright">
        © 2025 <a href="#">LAB Jewels</a>. All Rights Reserved.
      </div>
    </div>
  </footer>

  <script>
    // Mobile menu toggle
    const toggleMenu = () => {
      const menu = document.querySelector('nav ul');
      const toggle = document.querySelector('.menu-toggle');
      menu.classList.toggle('active');
      toggle.classList.toggle('active');
    };
    document.querySelector('.menu-toggle').addEventListener('click', toggleMenu);

    // Header Scroll Effect
    window.addEventListener('scroll', function() {
      const header = document.querySelector('header');
      header.classList.toggle('scrolled', window.scrollY > 50);
    });

    // Smooth Scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth'
          });
        }
        const menu = document.querySelector('nav ul');
        const toggle = document.querySelector('.menu-toggle');
        menu.classList.remove('active');
        toggle.classList.remove('active');
      });
    });

    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
      button.addEventListener('click', function() {
        const input = this.parentNode.querySelector('input');
        const eyeIcon = this.querySelector('.eye');
        const eyeOffIcon = this.querySelector('.eye-off');
        if (input.type === 'password') {
          input.type = 'text';
          eyeIcon.style.display = 'none';
          eyeOffIcon.style.display = 'block';
        } else {
          input.type = 'password';
          eyeIcon.style.display = 'block';
          eyeOffIcon.style.display = 'none';
        }
        input.focus();
      });
    });

    // Password strength checker
    const passwordInput = document.getElementById('password');
    const strengthSegments = document.querySelectorAll('.strength-segment');
    const passwordFeedback = document.querySelector('.password-feedback');

    passwordInput.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;
      let feedback = '';

      strengthSegments.forEach(segment => {
        segment.className = 'strength-segment';
      });

      if (password.length === 0) {
        feedback = 'Password strength: Enter a password';
      } else {
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        if (password.length < 8) {
          feedback = 'Password is too short';
          passwordFeedback.className = 'password-feedback weak';
          strengthSegments[0].className = 'strength-segment weak';
        } else if (strength <= 2) {
          feedback = 'Password strength: Weak';
          passwordFeedback.className = 'password-feedback weak';
          strengthSegments[0].className = 'strength-segment weak';
        } else if (strength <= 3) {
          feedback = 'Password strength: Medium';
          passwordFeedback.className = 'password-feedback medium';
          strengthSegments[0].className = 'strength-segment medium';
          strengthSegments[1].className = 'strength-segment medium';
        } else if (strength <= 4) {
          feedback = 'Password strength: Good';
          passwordFeedback.className = 'password-feedback medium';
          strengthSegments[0].className = 'strength-segment medium';
          strengthSegments[1].className = 'strength-segment medium';
          strengthSegments[2].className = 'strength-segment medium';
        } else {
          feedback = 'Password strength: Strong';
          passwordFeedback.className = 'password-feedback strong';
          strengthSegments.forEach(segment => {
            segment.className = 'strength-segment strong';
          });
        }
      }

      passwordFeedback.textContent = feedback;
    });

    // Username availability check with AJAX
    const usernameInput = document.getElementById('username');
    const usernameFeedback = document.querySelector('.username-feedback');
    let usernameTimer;

    usernameInput.addEventListener('input', function() {
      clearTimeout(usernameTimer);
      usernameFeedback.textContent = '';
      if (this.value.trim().length >= 3) {
        usernameTimer = setTimeout(() => {
          const xhr = new XMLHttpRequest();
          xhr.open('POST', '../functions/check-username.php', true);
          xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
          xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
              const response = JSON.parse(xhr.responseText);
              usernameFeedback.textContent = response.message;
              usernameFeedback.style.color = response.status === 'error' ? '#dc2626' : '#10b981';
            }
          };
          xhr.send('username=' + encodeURIComponent(this.value.trim()));
        }, 800);
      }
    });

    // Form submission with client-side validation
    // Form submission with client-side validation
    const form = document.getElementById('signupForm');
    const submitButton = form.querySelector('.btn-signup');
    const spinner = submitButton.querySelector('.spinner');
    const buttonText = submitButton.querySelector('span');

    form.addEventListener('submit', function(e) {
      // Basic client-side validation
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm-password').value;
      const terms = document.getElementById('terms').checked;
      const recaptcha = grecaptcha.getResponse();

      // Clear previous notifications
      const notification = document.getElementById('notification');
      notification.textContent = '';
      notification.className = '';

      // Validate passwords match
      if (password !== confirmPassword) {
        e.preventDefault();
        showNotification('Passwords do not match', 'error');
        return false;
      }

      // Validate password strength
      if (password.length < 8) {
        e.preventDefault();
        showNotification('Password must be at least 8 characters long', 'error');
        return false;
      }

      // Validate terms acceptance
      if (!terms) {
        e.preventDefault();
        showNotification('You must agree to the Terms of Service and Privacy Policy', 'error');
        return false;
      }

      // Validate reCAPTCHA
      if (!recaptcha) {
        e.preventDefault();
        showNotification('Please complete the reCAPTCHA verification', 'error');
        return false;
      }

      // Show loading state
      submitButton.disabled = true;
      spinner.style.display = 'block';
      buttonText.textContent = 'Creating Account...';
    });

    // Password confirmation validation
    const confirmPasswordInput = document.getElementById('confirm-password');
    confirmPasswordInput.addEventListener('input', function() {
      const password = passwordInput.value;
      const confirmPassword = this.value;
      const inputField = this.parentNode;

      if (confirmPassword.length > 0) {
        if (password === confirmPassword) {
          inputField.classList.remove('error');
          inputField.classList.add('success');
        } else {
          inputField.classList.remove('success');
          inputField.classList.add('error');
        }
      } else {
        inputField.classList.remove('success', 'error');
      }
    });

    // Email validation
    const emailInput = document.getElementById('email');
    emailInput.addEventListener('blur', function() {
      const email = this.value;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      const inputField = this.parentNode;

      if (email.length > 0) {
        if (emailRegex.test(email)) {
          inputField.classList.remove('error');
          inputField.classList.add('success');
        } else {
          inputField.classList.remove('success');
          inputField.classList.add('error');
        }
      } else {
        inputField.classList.remove('success', 'error');
      }
    });

    // Phone number validation (Philippine format)
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function() {
      // Remove non-numeric characters except +
      let value = this.value.replace(/[^\d+]/g, '');
      
      // Format Philippine mobile number
      if (value.startsWith('09') && value.length <= 11) {
        this.value = value;
      } else if (value.startsWith('+639') && value.length <= 13) {
        this.value = value;
      } else if (value.startsWith('9') && value.length <= 10) {
        this.value = '0' + value;
      } else {
        // Limit to 11 digits for local format
        this.value = value.slice(0, 11);
      }
    });

    phoneInput.addEventListener('blur', function() {
      const phone = this.value;
      const phoneRegex = /^(09|\+639)\d{9}$/;
      const inputField = this.parentNode;

      if (phone.length > 0) {
        if (phoneRegex.test(phone)) {
          inputField.classList.remove('error');
          inputField.classList.add('success');
        } else {
          inputField.classList.remove('success');
          inputField.classList.add('error');
        }
      } else {
        inputField.classList.remove('success', 'error');
      }
    });

    // Real-time form validation for all required inputs
    const requiredInputs = form.querySelectorAll('input[required]');
    requiredInputs.forEach(input => {
      input.addEventListener('blur', function() {
        const inputField = this.parentNode;
        if (this.type !== 'email' && this.type !== 'password' && this.id !== 'phone') {
          if (this.value.trim().length > 0) {
            inputField.classList.remove('error');
            inputField.classList.add('success');
          } else {
            inputField.classList.remove('success');
            inputField.classList.add('error');
          }
        }
      });
    });

    // Notification function
    function showNotification(message, type) {
      const notification = document.getElementById('notification');
      notification.textContent = message;
      notification.className = type;
      notification.style.display = 'block';
      
      // Auto-hide after 5 seconds
      setTimeout(() => {
        notification.style.display = 'none';
      }, 5000);
    }

    // Show PHP notifications if they exist
    const phpNotification = document.getElementById('notification');
    if (phpNotification.textContent.trim()) {
      phpNotification.className = '<?php echo $notification["type"]; ?>';
      phpNotification.style.display = 'block';
      
      // Auto-hide after 5 seconds
      setTimeout(() => {
        phpNotification.style.display = 'none';
      }, 5000);
    }

    // Form input animations
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
      input.addEventListener('focus', function() {
        this.parentNode.classList.add('focused');
      });

      input.addEventListener('blur', function() {
        if (!this.value) {
          this.parentNode.classList.remove('focused');
        }
      });

      // Check if input has value on page load
      if (input.value) {
        input.parentNode.classList.add('focused');
      }
    });

    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }

    // Character limits for inputs
    const firstnameInput = document.getElementById('firstname');
    const lastnameInput = document.getElementById('lastname');
    const usernameInputChar = document.getElementById('username');
    const addressInput = document.getElementById('address');

    [firstnameInput, lastnameInput].forEach(input => {
      input.addEventListener('input', function() {
        if (this.value.length > 50) {
          this.value = this.value.slice(0, 50);
        }
      });
    });

    usernameInputChar.addEventListener('input', function() {
      if (this.value.length > 30) {
        this.value = this.value.slice(0, 30);
      }
    });

    addressInput.addEventListener('input', function() {
      if (this.value.length > 255) {
        this.value = this.value.slice(0, 255);
      }
    });

    // Prevent spaces in username
    usernameInputChar.addEventListener('keypress', function(e) {
      if (e.key === ' ') {
        e.preventDefault();
      }
    });

    // Auto-capitalize names
    [firstnameInput, lastnameInput].forEach(input => {
      input.addEventListener('input', function() {
        this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
      });
    });
  </script>
</body>
</html>