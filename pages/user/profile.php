<?php
require '../../connection/config.php';
session_start();

// Check if user is already logged in
$currentUser = validateSession();

if (!$currentUser) {
    header("Location: ../pages/login.php");
    exit;
}

$profileImageUrl = $currentUser['icon'] ?? 'no-icon.png'; // Fallback to default icon
$success = '';
$error = '';

// Generate CSRF token for AJAX requests
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8');
    $firstname = htmlspecialchars($_POST['firstname'], ENT_QUOTES, 'UTF-8');
    $lastname = htmlspecialchars($_POST['lastname'], ENT_QUOTES, 'UTF-8');
    
    try {
        $pdo = getDBConnection();
        // Check if username is already taken by another user
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->execute([$username, $currentUser['user_id']]);
        if ($stmt->fetch()) {
            $error = 'Username is already taken';
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, firstname = ?, lastname = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$username, $firstname, $lastname, $currentUser['user_id']]);
            
            $success = 'Profile updated successfully';
            $currentUser = validateSession(); // Refresh user data
        }
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error = 'Error updating profile';
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            $error = 'Invalid file type. Please upload JPEG, PNG, or GIF.';
        } elseif ($file['size'] > $maxSize) {
            $error = 'File size exceeds 5MB.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $currentUser['user_id'] . '_' . time() . '.' . $ext;
            $uploadDir = '../../assets/image/profile/';
            $uploadPath = $uploadDir . $filename;
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("UPDATE users SET icon = ? WHERE user_id = ?");
                    $stmt->execute([$filename, $currentUser['user_id']]);
                    $profileImageUrl = $filename;
                    $success = 'Profile picture updated successfully';
                    $currentUser = validateSession();
                } catch (PDOException $e) {
                    error_log("Profile picture update error: " . $e->getMessage());
                    $error = 'Error updating profile picture';
                }
            } else {
                $error = 'Failed to upload profile picture';
            }
        }
    } else {
        $error = 'No file uploaded or upload error occurred';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password']; // Validate later
    $new_password = $_POST['new_password']; // Validate later
    $confirm_password = $_POST['confirm_password']; // Validate later
    
    if ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $new_password)) {
        $error = 'New password must be at least 8 characters with uppercase, lowercase, and number';
    } elseif (!password_verify($current_password, $currentUser['password'])) {
        $error = 'Current password is incorrect';
    } else {
        try {
            $pdo = getDBConnection();
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$new_password_hash, $currentUser['user_id']]);
            $success = 'Password updated successfully';
        } catch (PDOException $e) {
            error_log("Password update error: " . $e->getMessage());
            $error = 'Error updating password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - Profile</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --primary-gradient: linear-gradient(#8b5cf6);
            --primary-hover: #2c2c2c;
            --secondary-color: #8b5cf6;
            --secondary-gradient: linear-gradient(135deg, #f43f5e, #ec4899);
            --nav-color: #101010;
            --background-color: #f8fafc;
            --card-bg: #ffffff;
            --division-color: #e5e7eb;
            --boxshadow-color: rgba(0, 0, 0, 0.05);
            --blackfont-color: #1a1a1a;
            --whitefont-color: #ffffff;
            --grayfont-color: #6b7280;
            --border-color: #e5e7eb;
            --inputfield-color: #f3f4f6;
            --inputfieldhover-color: #e5e7eb;
            --buttonhover-color: #2c2c2c;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 0 10px -1px rgba(0, 0, 0, 0.1), 0 0 10px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
            --danger: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }

        body {
            background-color: var(--card-bg);
            color: var(--blackfont-color);
            padding: 20px;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--blackfont-color);
            position: relative;
            padding-bottom: 10px;
        }

        h1:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            height: 3px;
            width: 60px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }

        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            margin-top: 40px;
            margin-bottom: 50px;
        }

        .profile-header {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
        }

        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-image-section {
            text-align: center;
        }

        .profile-image-container {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid var(--primary-color);
            margin: 0 auto 15px;
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: var(--whitefont-color);
            padding: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: var(--transition);
        }

        .profile-image-overlay:hover {
            background: rgba(0,0,0,0.9);
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--blackfont-color);
        }

        .profile-role {
            font-size: 16px;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }

        .profile-email {
            font-size: 14px;
            color: var(--grayfont-color);
            margin-bottom: 10px;
        }

        .profile-tabs {
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .tab-navigation {
            display: flex;
            background: var(--division-color);
            border-bottom: 1px solid var(--border-color);
        }

        .tab-button {
            background: none;
            border: none;
            padding: 15px 25px;
            font-size: 14px;
            font-weight: 500;
            color: var(--grayfont-color);
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 3px solid transparent;
        }

        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--secondary-color);
            background: var(--card-bg);
        }

        .tab-content {
            padding: 30px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--blackfont-color);
            padding-bottom: 8px;
            border-bottom: 2px solid var(--division-color);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            margin-top: 15px;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--blackfont-color);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 15px;
            background-color: var(--inputfield-color);
            transition: var(--transition);
        }

        .form-group input:hover,
        .form-group textarea:hover {
            background-color: var(--inputfieldhover-color);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            background-color: var(--inputfieldhover-color);
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: var(--whitefont-color);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5256e0, #7c4ce7);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: opacity 0.3s ease;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: var(--inputfield-color);
            transition: var(--transition);
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: var(--secondary-color);
            background: var(--inputfieldhover-color);
        }

        .file-upload-area.dragover {
            border-color: var(--secondary-color);
            background: #e5e7eb;
        }

        .upload-icon {
            font-size: 48px;
            color: var(--grayfont-color);
            margin-bottom: 15px;
        }

        .upload-text {
            font-size: 16px;
            color: var(--blackfont-color);
            margin-bottom: 5px;
        }

        .upload-subtext {
            font-size: 14px;
            color: var(--grayfont-color);
        }

        .hidden {
            display: none;
        }

        @media (max-width: 768px) {
            .profile-container {
                padding: 15px;
            }
            .profile-header-content {
                flex-direction: column;
                text-align: center;
            }
            .form-row {
                flex-direction: column;
            }
            .tab-navigation {
                flex-wrap: wrap;
            }
            .tab-button {
                flex: 1;
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <h1>Profile</h1>

        <div class="profile-header">
            <div class="profile-header-content">
                <div class="profile-image-section">
                    <div class="profile-image-container">
                        <img src="../../assets/image/profile/<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile" class="profile-image" id="profilePreview" onerror="this.src='../../assets/image/profile/no-icon.png'">
                        <div class="profile-image-overlay" onclick="document.getElementById('profileImageInput').click()">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                </div>
                <div class="profile-info">
                    <h1 class="profile-name"><?php echo htmlspecialchars($currentUser['firstname'] . ' ' . $currentUser['lastname']); ?></h1>
                    <p class="profile-role">User</p>
                    <p class="profile-email"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="profile-tabs">
            <div class="tab-navigation">
                <button class="tab-button active" data-tab="profile">
                    <i class="fas fa-user"></i> Profile Information
                </button>
                <button class="tab-button" data-tab="picture">
                    <i class="fas fa-camera"></i> Profile Picture
                </button>
                <button class="tab-button" data-tab="password">
                    <i class="fas fa-lock"></i> Change Password
                </button>
            </div>

            <div class="tab-content">
                <div class="tab-pane active" id="profile">
                    <form method="POST" action="">
                        <div class="form-section">
                            <h3 class="section-title">Basic Information</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="firstname">First Name *</label>
                                    <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($currentUser['firstname']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="lastname">Last Name *</label>
                                    <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($currentUser['lastname']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username">Username *</label>
                                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($currentUser['username']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>

                <div class="tab-pane" id="picture">
                    <div class="form-section">
                        <h3 class="section-title">Profile Picture</h3>
                        
                        <div style="display: flex; gap: 30px; align-items: flex-start;">
                            <div style="text-align: center;">
                                <div class="profile-image-container" style="margin-bottom: 15px;">
                                    <img src="../../assets/image/profile/<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Current Profile" class="profile-image" id="currentProfileImage" onerror="this.src='../../assets/image/profile/no-icon.png'">
                                </div>
                                <p style="font-size: 14px; color: var(--grayfont-color);">Current Picture</p>
                            </div>
                            
                            <div style="flex: 1;">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="file-upload-area" id="fileUploadArea">
                                        <div class="upload-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <div class="upload-text">Click to upload or drag and drop</div>
                                        <div class="upload-subtext">PNG, JPG, GIF up to 5MB</div>
                                        <input type="file" id="profileImageInput" name="profile_image" accept="image/*" class="hidden">
                                    </div>
                                    
                                    <div style="margin-top: 20px;">
                                        <button type="submit" name="upload_picture" class="btn btn-primary">
                                            <i class="fas fa-upload"></i> Upload New Picture
                                        </button>
                                    </div>
                                </form>
                                
                                <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 4px;">
                                    <h4 style="margin-bottom: 10px; font-size: 14px; color: #856404;">Image Requirements:</h4>
                                    <ul style="font-size: 13px; color: #856404; margin-left: 20px;">
                                        <li>Maximum file size: 5MB</li>
                                        <li>Supported formats: JPEG, PNG, GIF</li>
                                        <li>Recommended size: 400x400 pixels</li>
                                        <li>Square images work best</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane" id="password">
                    <form method="POST" action="">
                        <div class="form-section">
                            <h3 class="section-title">Change Password</h3>
                            
                            <div class="form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_password">New Password *</label>
                                    <input type="password" id="new_password" name="new_password" required minlength="8">
                                    <small style="color: var(--grayfont-color); font-size: 12px;">Minimum 8 characters with uppercase, lowercase, and number</small>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password *</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                                <h4 style="margin-bottom: 10px; font-size: 14px; color: #856404;"><i class="fas fa-exclamation-triangle"></i> Password Requirements:</h4>
                                <ul style="font-size: 13px; color: #856404; margin-left: 20px;">
                                    <li>At least 8 characters long</li>
                                    <li>Include uppercase letter, lowercase letter, and number</li>
                                    <li>Avoid using personal information</li>
                                    <li>Use a unique password not used elsewhere</li>
                                </ul>
                            </div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Tab handling
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const targetTab = button.getAttribute('data-tab');
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabPanes.forEach(pane => pane.classList.remove('active'));
                    button.classList.add('active');
                    document.getElementById(targetTab).classList.add('active');
                });
            });

            // File upload handling
            const fileUploadArea = document.getElementById('fileUploadArea');
            const profileImageInput = document.getElementById('profileImageInput');
            const profilePreview = document.getElementById('profilePreview');
            const currentProfileImage = document.getElementById('currentProfileImage');

            fileUploadArea.addEventListener('click', () => profileImageInput.click());

            fileUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileUploadArea.classList.add('dragover');
            });

            fileUploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('dragover');
            });

            fileUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    profileImageInput.files = files;
                    handleFileSelect(files[0]);
                }
            });

            profileImageInput.addEventListener('change', () => {
                if (profileImageInput.files && profileImageInput.files[0]) {
                    handleFileSelect(profileImageInput.files[0]);
                }
            });

            function handleFileSelect(file) {
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPEG, JPG, PNG, or GIF).');
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB.');
                    return;
                }
                const reader = new FileReader();
                reader.onload = (e) => {
                    profilePreview.src = e.target.result;
                    currentProfileImage.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }

            // Password validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');

            function validatePasswords() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            if (newPassword && confirmPassword) {
                newPassword.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }

            // Alert fadeout
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 6000);
            });
        });
    </script>
</body>
</html>