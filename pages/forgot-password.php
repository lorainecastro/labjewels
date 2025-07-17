<?php
require '../connection/config.php';
require '../PHPMailer/vendor/autoload.php';
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Initialize notification
$notification = ['message' => '', 'type' => ''];
$email = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $notification = ['message' => 'Please enter your email address.', 'type' => 'error'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $notification = ['message' => 'Please enter a valid email address.', 'type' => 'error'];
    } else {
        try {
            $pdo = getDBConnection();

            // Check if email exists
            $stmt = $pdo->prepare("
                SELECT user_id, email, isActive, isVerified, isDeleted 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $notification = ['message' => 'No account found with that email.', 'type' => 'error'];
            } elseif ($user['isDeleted'] == 1) {
                $notification = ['message' => 'No account found with that email.', 'type' => 'error'];
                error_log("Password reset attempt for deleted account: $email");
            } elseif ($user['isActive'] == 0 || $user['isVerified'] == 0) {
                $notification = ['message' => 'Account is not active or verified.', 'type' => 'error'];
            } else {
                // Generate OTP
                $otp = sprintf("%06d", mt_rand(0, 999999));
                $otp_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                // Store OTP in database
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET otp_code = ?, otp_purpose = 'PASSWORD_RESET', 
                        otp_created_at = NOW(), otp_expires_at = ?, otp_is_used = 0 
                    WHERE email = ?
                ");
                $stmt->execute([$otp, $otp_expires, $email]);

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
                    $mail->Subject = 'LAB Jewels Password Reset OTP';
                    $mail->Body = "
                        <h2>LAB Jewels Password Reset</h2>
                        <p>Your OTP for password reset is: <strong>$otp</strong></p>
                        <p>This code is valid for 15 minutes. Please enter it on the password reset page.</p>
                        <p>If you did not request this, please ignore this email.</p>
                    ";

                    $mail->send();

                    $_SESSION['reset_email'] = $email;
                    // Return JSON response for AJAX
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        ob_clean();
                        echo json_encode([
                            'success' => true,
                            'message' => 'An OTP has been sent to your email.',
                            'redirect' => 'reset-password.php'
                        ]);
                        exit;
                    } else {
                        header("Location: reset-password.php");
                        exit;
                    }
                } catch (Exception $e) {
                    $notification = ['message' => 'Failed to send OTP. Please try again.', 'type' => 'error'];
                    error_log("Password reset email error: " . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            $notification = ['message' => 'An error occurred. Please try again.', 'type' => 'error'];
            error_log("Password reset error: " . $e->getMessage());
        }
    }
    
    // Return JSON response for AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $notification['message']]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAB Jewels - Forgot Password</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --black: #1a1a1a;
      --white: #ffffff;
      --gray: #6b7280;
      --light-gray: #e5e7eb;
      --dark-gray: #4b5563;
      --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
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
      --success-color: #8b5cf6;
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

    .forgot-container {
      width: 100%;
      max-width: 460px;
      background: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: 24px;
      box-shadow: var(--shadow-lg);
      padding: 48px 40px;
      position: relative;
      overflow: hidden;
      transition: var(--transition);
      z-index: 1;
      margin: auto;
      margin-top: 40px;
      margin-bottom: 40px;
    }

    .forgot-container::before {
      content: '';
      position: absolute;
      top: -1px;
      left: 50%;
      transform: translateX(-50%);
      width: 100px;
      height: 6px;
      background: var(--primary-gradient);
      border-radius: 0 0 50px 50px;
      z-index: 5;
    }

    .forgot-container:hover {
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    }

    .forgot-header {
      text-align: center;
      margin-bottom: 40px;
      position: relative;
    }

    .forgot-header .brand-icon {
      width: 70px;
      height: 70px;
      background: var(--primary-gradient);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      box-shadow: 0 10px 20px rgba(26, 26, 26, 0.3);
      position: relative;
    }

    .forgot-header .brand-icon::after {
      content: '';
      position: absolute;
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: var(--primary-gradient);
      z-index: -1;
      opacity: 0.6;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% { transform: scale(1); opacity: 0.6; }
      70% { transform: scale(1.3); opacity: 0; }
      100% { transform: scale(1.3); opacity: 0; }
    }

    .forgot-header .brand-icon svg {
      width: 35px;
      height: 35px;
      fill: var(--white);
    }

    .forgot-header h1 {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 10px;
      color: var(--text-color);
      letter-spacing: -0.5px;
    }

    .forgot-header p {
      color: var(--text-light);
      font-size: 16px;
    }

    .input-group {
      margin-bottom: 24px;
      position: relative;
    }

    .input-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--text-color);
      font-size: 15px;
    }

    .input-field {
      position: relative;
    }

    .input-field input {
      width: 100%;
      padding: 16px 20px;
      padding-left: 50px;
      font-size: 16px;
      border: 2px solid var(--border-color);
      border-radius: 12px;
      outline: none;
      transition: var(--transition);
      background-color: var(--white);
    }

    .input-field input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 4px rgba(26, 26, 26, 0.1);
    }

    .input-field .icon {
      position: absolute;
      left: 18px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
      font-size: 16px;
      transition: var(--transition);
    }

    .input-field input:focus + .icon {
      color: var(--primary-color);
    }

    .btn-submit {
      width: 100%;
      padding: 16px;
      background: var(--primary-gradient);
      color: var(--white);
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      box-shadow: var(--shadow-md);
      position: relative;
      overflow: hidden;
      z-index: 1;
    }

    .btn-submit::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.7s ease;
      z-index: -1;
    }

    .btn-submit:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-lg);
    }

    .btn-submit:hover::before {
      left: 100%;
    }

    .btn-submit.loading {
      opacity: 0.8;
      pointer-events: none;
    }

    .btn-submit .spinner {
      display: none;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: var(--white);
      animation: spin 1s linear infinite;
      margin: 0 auto;
    }

    .btn-submit.loading .spinner {
      display: block;
    }

    .btn-submit.loading span {
      display: none;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .back-link {
      text-align: center;
      margin-top: 32px;
      font-size: 15px;
      color: var(--text-light);
    }

    .back-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }

    .back-link a:hover {
      color: var(--dark-gray);
      text-decoration: underline;
    }

    .notification {
      display: none;
      padding: 16px;
      border-radius: 12px;
      margin-bottom: 25px;
      text-align: center;
      font-size: 15px;
      font-weight: 500;
      color: var(--white);
      animation: slideIn 0.4s ease;
    }

    .notification.error {
      background: linear-gradient(135deg, var(--error-color), #b91c1c);
    }

    .notification.success {
      background: var(--success-color);
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
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
        background: var(--gradient);
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

    @media (max-width: 480px) {
      .forgot-container {
        padding: 32px 25px;
        margin: 20px auto;
        border-radius: 20px;
        width: 90%;
      }

      .forgot-header h1 {
        font-size: 28px;
      }

      .input-field input {
        padding: 14px 18px;
        padding-left: 45px;
        font-size: 15px;
      }

      .input-field .icon {
        left: 15px;
      }

      .btn-submit {
        padding: 14px;
        font-size: 15px;
      }

      .footer {
        padding: 40px 15px;
      }

      .copyright {
        font-size: 15px;
      }
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
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
        <li><a href="../pages/landing.php#categories">Shop</a></li>
        <li><a href="../pages/landing.php#about">About</a></li>
        <li><a href="../pages/landing.php#contact">Contact</a></li>
        <li><a href="#cart"><i class="fas fa-shopping-cart" title="Cart"></i></a></li>
        <li><a href="signup.php" class="button">Sign Up</a></li>
      </ul>
    </nav>
  </header>

  <!-- Forgot Password Container -->
  <div class="forgot-container">
    <div class="forgot-header">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>
      </div>
      <h1>Forgot Password</h1>
      <p>Enter your email to receive a password reset OTP</p>
    </div>

    <div id="notification" class="notification <?php echo $notification['type']; ?>" style="display: <?php echo $notification['message'] ? 'block' : 'none'; ?>">
      <?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?>
    </div>

    <form id="forgot-password-form" method="post" action="">
      <div class="input-group">
        <label for="email">Email Address</label>
        <div class="input-field">
          <input type="email" id="email" name="email" placeholder="Enter your email address" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
          <i class="fas fa-envelope icon"></i>
        </div>
      </div>
      <button type="submit" id="submit-btn" class="btn-submit">
        <span>Send OTP</span>
        <div class="spinner"></div>
      </button>

      <div class="back-link">
        Back to <a href="login.php">Login</a>
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
          <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
          <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
        </div>
      </div>
      <div class="footer-column">
        <h4>Quick Links</h4>
        <div class="footer-links">
          <a href="../pages/landing.php#home">Home</a>
          <a href="../pages/landing.php#categories">Shop</a>
          <a href="../pages/landing.php#about">About</a>
          <a href="../pages/landing.php#contact">Contact</a>
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
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('forgot-password-form');
      const submitBtn = document.getElementById('submit-btn');
      const notification = document.getElementById('notification');
      const input = document.querySelector('.input-field input');
      const forgotContainer = document.querySelector('.forgot-container');

      function showNotification(message, type) {
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.style.display = 'block';
        setTimeout(() => {
          notification.style.display = 'none';
        }, 5000);
      }

      // Input focus animations
      input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
      });

      input.addEventListener('blur', function() {
        if (this.value === '') {
          this.parentElement.classList.remove('focused');
        }
      });

      if (input.value !== '') {
        input.parentElement.classList.add('focused');
      }

      // Form submission with AJAX
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        notification.style.display = 'none';
        submitBtn.classList.add('loading');

        const formData = new FormData(this);

        fetch('', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          submitBtn.classList.remove('loading');
          showNotification(data.message, data.success ? 'success' : 'error');

          if (data.success) {
            forgotContainer.style.transform = 'scale(0.95)';
            forgotContainer.style.opacity = '0.8';
            setTimeout(() => {
              window.location.href = data.redirect;
            }, 1500);
          } else {
            forgotContainer.style.animation = 'shake 0.5s ease-in-out';
            setTimeout(() => {
              forgotContainer.style.animation = '';
            }, 500);
          }
        })
        .catch(error => {
          submitBtn.classList.remove('loading');
          showNotification('An error occurred. Please try again.', 'error');
          console.error('Error:', error);
        });
      });

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

      // Add enter key support
      input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          form.dispatchEvent(new Event('submit'));
        }
      });

      // Auto-hide notification
      notification.addEventListener('click', function() {
        this.style.display = 'none';
      });

      // Input loading state
      input.addEventListener('input', function() {
        if (this.value.length > 0) {
          this.classList.add('has-value');
        } else {
          this.classList.remove('has-value');
        }
      });

      if (input.value.length > 0) {
        input.classList.add('has-value');
      }
    });
  </script>
</body>
</html>