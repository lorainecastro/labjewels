<?php
require '../connection/config.php';
session_start();

require '../PHPMailer/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$notification = ['message' => '', 'type' => ''];
$email = isset($_SESSION['signup_email']) ? $_SESSION['signup_email'] : '';

// Redirect if email is not set
if (empty($email)) {
    session_unset();
    session_destroy();
    header("Location: ../pages/landing.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDBConnection();

    if (isset($_POST['action']) && $_POST['action'] === 'resend') {
        try {
            // Generate new OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $otp_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Update OTP in database
            $stmt = $pdo->prepare("
                UPDATE users 
                SET otp_code = ?, otp_purpose = 'EMAIL_VERIFICATION', otp_expires_at = ?, otp_is_used = 0
                WHERE email = ? AND isVerified = 0
            ");
            $stmt->execute([$otp, $otp_expires, $email]);

            // Send OTP email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'elci.bank@gmail.com';
                $mail->Password = 'misxfqnfsovohfwh';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('castro.loraine.26@gmail.com', 'LAB Jewels');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Verify Your LAB Jewels Account';
                $mail->Body = "
                    <h2>Welcome to LAB Jewels!</h2>
                    <p>Your new OTP for email verification is: <strong>$otp</strong></p>
                    <p>This code is valid for 15 minutes. Please enter it on the verification page.</p>
                    <p>If you did not request this, please ignore this email.</p>
                ";

                $mail->send();
                $notification = ['message' => 'OTP resent successfully', 'type' => 'success'];
            } catch (Exception $e) {
                $notification = ['message' => 'Failed to resend OTP. Please try again.', 'type' => 'error'];
            }
        } catch (PDOException $e) {
            $notification = ['message' => 'Database error: ' . $e->getMessage(), 'type' => 'error'];
        }
    } else {
        // Verify OTP
        $otp = filter_var($_POST['otp'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

        if (empty($otp)) {
            $notification = ['message' => 'Please enter the OTP', 'type' => 'error'];
        } elseif (!preg_match('/^\d{6}$/', $otp)) {
            $notification = ['message' => 'OTP must be a 6-digit number', 'type' => 'error'];
        } else {
            try {
                // Check OTP
                $stmt = $pdo->prepare("
                    SELECT otp_code, otp_expires_at, otp_is_used 
                    FROM users 
                    WHERE email = ? AND otp_purpose = 'EMAIL_VERIFICATION'
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $notification = ['message' => 'Invalid or expired OTP', 'type' => 'error'];
                } elseif ($user['otp_is_used']) {
                    $notification = ['message' => 'OTP has already been used', 'type' => 'error'];
                } elseif (strtotime($user['otp_expires_at']) < time()) {
                    $notification = ['message' => 'OTP has expired. Please request a new one.', 'type' => 'error'];
                } elseif ($user['otp_code'] !== $otp) {
                    $notification = ['message' => 'Incorrect OTP', 'type' => 'error'];
                } else {
                    // OTP is valid, verify user
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET isVerified = 1, isActive = 1, otp_is_used = 1, otp_code = NULL, otp_expires_at = NULL
                        WHERE email = ?
                    ");
                    $stmt->execute([$email]);

                    // Clear session
                    unset($_SESSION['signup_email']);
                    
                    // Set success notification (redirect handled by JavaScript)
                    $notification = ['message' => 'Email verified successfully! Redirecting to login', 'type' => 'success'];
                    // No exit() here to allow page to render
                }
            } catch (PDOException $e) {
                $notification = ['message' => 'Database error: ' . $e->getMessage(), 'type' => 'error'];
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
  <title>LAB Jewels - Verify Email</title>
  <link rel="icon" type="image/png" href="../assets/image/system/logo.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
      --success-color: #10b981; /* Green for success */
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

    .verify-container {
      width: 100%;
      max-width: 500px;
      background: var(--card-bg);
      border-radius: 24px;
      box-shadow: var(--shadow-lg);
      overflow: hidden;
      position: relative;
      z-index: 1;
      margin: 50px auto;
      padding: 40px;
    }

    .form-title {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: 15px;
      color: var(--text-color);
      text-align: center;
    }

    .form-description {
      font-size: 16px;
      color: var(--text-light);
      margin-bottom: 25px;
      text-align: center;
      line-height: 1.5;
    }

    #notification {
      display: <?php echo $notification['message'] ? 'block' : 'none'; ?>;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      font-size: 16px;
      font-weight: 500;
      color: var(--white);
      background-color: <?php echo $notification['type'] === 'error' ? 'var(--error-color)' : 'var(--success-color)'; ?>;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 400px;
      margin-left: auto;
      margin-right: auto;
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
      text-align: center;
    }

    .otp-input-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
      background: rgba(0, 0, 0, 0.02);
      padding: 12px;
      border-radius: 12px;
      border: 1px solid var(--border-color);
    }

    .otp-inputs {
      display: flex;
      gap: 8px;
      justify-content: center;
      align-items: center;
    }

    .otp-inputs input {
      width: 55px;
      height: 55px;
      padding: 10px;
      font-size: 20px;
      font-weight: 600;
      border: 2px solid var(--border-color);
      border-radius: 8px;
      outline: none;
      transition: var(--transition);
      background-color: var(--input-bg);
      text-align: center;
      letter-spacing: 0;
    }

    .otp-inputs input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 4px rgba(26, 26, 26, 0.1);
      background-color: var(--input-bg-focus);
    }

    .btn-verify {
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

    .btn-verify::before {
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

    .btn-verify:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.35);
    }

    .btn-verify:hover::before {
      left: 100%;
      transition: 0.7s;
    }

    .btn-verify:active {
      transform: translateY(0);
    }

    .btn-verify.loading {
      opacity: 0.8;
      pointer-events: none;
    }

    .btn-verify .spinner {
      display: none;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: var(--white);
      animation: spin 1s linear infinite;
      margin: 0 auto;
    }

    .btn-verify.loading .spinner {
      display: block;
    }

    .btn-verify.loading span {
      display: none;
    }

    .resend-link {
      text-align: center;
      margin-top: 20px;
      font-size: 15px;
      color: var(--text-light);
    }

    .resend-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }

    .resend-link a:hover {
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

      .verify-container {
        max-width: 90%;
        margin: 20px auto;
        padding: 30px;
      }

      .otp-inputs input {
        width: 45px;
        height: 45px;
        font-size: 18px;
      }

      .otp-input-wrapper {
        padding: 10px;
      }

      .otp-inputs {
        gap: 6px;
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
      .verify-container {
        padding: 20px;
      }

      .form-title {
        font-size: 20px;
      }

      .form-description {
        font-size: 14px;
      }

      .input-group label {
        font-size: 14px;
      }

      .otp-inputs input {
        width: 40px;
        height: 40px;
        font-size: 16px;
        padding: 8px;
      }

      .otp-input-wrapper {
        padding: 8px;
      }

      .otp-inputs {
        gap: 5px;
      }

      .btn-verify {
        padding: 12px;
        font-size: 15px;
      }

      .resend-link {
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
      .verify-container {
        max-width: 95%;
        border-radius: 16px;
        padding: 15px;
      }

      .form-title {
        font-size: 18px;
        margin-bottom: 10px;
      }

      .form-description {
        font-size: 13px;
        margin-bottom: 15px;
      }

      .input-group {
        margin-bottom: 12px;
      }

      .input-group label {
        font-size: 13px;
        margin-bottom: 6px;
      }

      .otp-inputs input {
        width: 35px;
        height: 35px;
        font-size: 14px;
        padding: 6px;
      }

      .otp-input-wrapper {
        padding: 6px;
      }

      .otp-inputs {
        gap: 4px;
      }

      .btn-verify {
        padding: 10px;
        font-size: 14px;
      }

      .resend-link {
        font-size: 13px;
        margin-top: 15px;
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
        <li><a href="../landing.php">Home</a></li>
        <li><a href="../landing.php">Shop</a></li>
        <li><a href="../landing.php">About</a></li>
        <li><a href="../landing.php#contact">Contact</a></li>
        <li><a href="#cart"><i class="fas fa-shopping-cart"></i></a></li>
        <li><a href="login.php" class="button">Login</a></li>
      </ul>
    </nav>
  </header>

  <!-- Verify Email Section -->
  <div class="verify-container">
    <h2 class="form-title">Verify Your Email</h2>
    <p class="form-description">
      We've sent a 6-digit OTP to <strong><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong>. 
      Please enter it below to verify your account.
    </p>
    <div id="notification"><?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?></div>
    <form id="verifyForm" method="post" action="">
      <div class="input-group">
        <label for="otp1">Enter OTP</label>
        <div class="otp-input-wrapper">
          <div class="otp-inputs">
            <input type="text" id="otp1" name="otp1" maxlength="1" aria-label="OTP digit 1" required>
            <input type="text" id="otp2" name="otp2" maxlength="1" aria-label="OTP digit 2" required>
            <input type="text" id="otp3" name="otp3" maxlength="1" aria-label="OTP digit 3" required>
            <input type="text" id="otp4" name="otp4" maxlength="1" aria-label="OTP digit 4" required>
            <input type="text" id="otp5" name="otp5" maxlength="1" aria-label="OTP digit 5" required>
            <input type="text" id="otp6" name="otp6" maxlength="1" aria-label="OTP digit 6" required>
          </div>
          <input type="hidden" id="otp" name="otp">
        </div>
      </div>
      <button type="submit" class="btn-verify">
        <span>Verify Email</span>
        <div class="spinner"></div>
      </button>
      <div class="resend-link">
        Didn't receive the OTP? <a href="#" id="resendOtp">Resend OTP</a>
      </div>
    </form>
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
      header.classList.toggle('scrolled', window.scrollY > 0);
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

    // OTP input handling
    const otpInputs = document.querySelectorAll('.otp-inputs input:not([type=hidden])');
    const hiddenOtpInput = document.getElementById('otp');
    const form = document.getElementById('verifyForm');
    const notification = document.getElementById('notification');
    const submitButton = document.querySelector('.btn-verify');

    otpInputs.forEach((input, index) => {
      input.addEventListener('input', function(e) {
        const value = e.target.value;

        // Only allow digits
        if (!/^\d$/.test(value) && value !== '') {
          e.target.value = '';
          return;
        }

        // Update hidden OTP input
        updateHiddenOTP();

        // Move to next input if a digit is entered
        if (value && index < otpInputs.length - 1) {
          otpInputs[index + 1].focus();
        }

        // Auto-submit if last input is filled
        if (index === otpInputs.length - 1 && value && hiddenOtpInput.value.length === 6) {
          submitButton.classList.add('loading');
          form.submit();
        }
      });

      input.addEventListener('keydown', function(e) {
        const value = e.target.value;

        // Move to previous input on backspace if empty
        if (e.key === 'Backspace' && !value && index > 0) {
          otpInputs[index - 1].focus();
        }

        // Handle paste event
        if (e.key === 'v' && e.ctrlKey) {
          navigator.clipboard.readText().then(text => {
            if (/^\d{6}$/.test(text)) {
              text.split('').forEach((digit, i) => {
                if (i < otpInputs.length) {
                  otpInputs[i].value = digit;
                }
              });
              otpInputs[otpInputs.length - 1].focus();
              updateHiddenOTP();
              // Auto-submit after paste
              if (hiddenOtpInput.value.length === 6) {
                submitButton.classList.add('loading');
                form.submit();
              }
            }
          });
        }
      });

      // Prevent non-numeric input
      input.addEventListener('keypress', function(e) {
        if (!/^\d$/.test(e.key)) {
          e.preventDefault();
        }
      });
    });

    function updateHiddenOTP() {
      const otp = Array.from(otpInputs).map(input => input.value).join('');
      hiddenOtpInput.value = otp;
    }

    // Form submission with client-side validation
    form.addEventListener('submit', function(e) {
      const otp = hiddenOtpInput.value;

      if (!otp) {
        e.preventDefault();
        notification.textContent = 'Please enter the OTP';
        notification.style.backgroundColor = 'var(--error-color)';
        notification.style.display = 'block';
        otpInputs.forEach(input => input.classList.add('error-shake'));
        setTimeout(() => {
          otpInputs.forEach(input => input.classList.remove('error-shake'));
          notification.style.display = 'none';
        }, 1000);
        return;
      }

      if (!/^\d{6}$/.test(otp)) {
        e.preventDefault();
        notification.textContent = 'OTP must be a 6-digit number';
        notification.style.backgroundColor = 'var(--error-color)';
        notification.style.display = 'block';
        otpInputs.forEach(input => input.classList.add('error-shake'));
        setTimeout(() => {
          otpInputs.forEach(input => input.classList.remove('error-shake'));
          notification.style.display = 'none';
        }, 1000);
        return;
      }

      // Show loading state
      submitButton.classList.add('loading');
    });

    // Resend OTP via AJAX
    document.getElementById('resendOtp').addEventListener('click', function(e) {
      e.preventDefault();
      const resendLink = this;
      resendLink.style.pointerEvents = 'none';
      resendLink.textContent = 'Sending...';

      const formData = new FormData();
      formData.append('action', 'resend');
      formData.append('email', '<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>');

      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(data => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(data, 'text/html');
        const newNotification = doc.getElementById('notification');
        notification.textContent = newNotification.textContent;
        notification.style.backgroundColor = newNotification.style.backgroundColor;
        notification.style.display = 'block';
        setTimeout(() => { notification.style.display = 'none'; }, 5000);
        resendLink.style.pointerEvents = 'auto';
        resendLink.textContent = 'Resend OTP';
        // Clear OTP inputs after resend
        otpInputs.forEach(input => input.value = '');
        hiddenOtpInput.value = '';
        otpInputs[0].focus();
      })
      .catch(error => {
        console.error('Resend OTP error:', error);
        notification.textContent = 'Failed to resend OTP. Please try again.';
        notification.style.backgroundColor = 'var(--error-color)';
        notification.style.display = 'block';
        setTimeout(() => { notification.style.display = 'none'; }, 5000);
        resendLink.style.pointerEvents = 'auto';
        resendLink.textContent = 'Resend OTP';
      });
    });

    // Add ripple effect to buttons
    document.querySelector('.btn-verify').addEventListener('click', function(e) {
      const ripple = document.createElement('span');
      ripple.classList.add('ripple');
      this.appendChild(ripple);

      const x = e.clientX - e.target.getBoundingClientRect().left;
      const y = e.clientY - e.target.getBoundingClientRect().top;

      ripple.style.left = `${x}px`;
      ripple.style.top = `${y}px`;

      setTimeout(() => {
        ripple.remove();
      }, 600);
    });

    // Auto-focus first OTP input and handle success redirect
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('otp1').focus();
      const notification = document.getElementById('notification');
      if (notification.textContent === 'Email verified successfully! Redirecting to login') {
        setTimeout(function() {
          window.location.href = 'login.php';
        }, 3000); // 3-second delay
      }
    });
  </script>
</body>
</html>