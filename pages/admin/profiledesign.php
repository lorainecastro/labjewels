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
    $username = ($_POST['username']);
    $firstname = ($_POST['firstname']);
    $lastname = ($_POST['lastname']);
    
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
    $current_password = $_POST['current_password'];
    $new_password = ($_POST['new_password']);
    $confirm_password = $_POST['confirm_password'];
    
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --primary-gradient: linear-gradient(#8b5cf6);
            --primary-focus: #6366f1;
            --primary-hover: #2c2c2c;
            --secondary-color: #f43f5e;
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

        .container {
            max-width: 1440px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: var(--whitefont-color);
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5256e0, #7c4ce7);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--secondary-gradient);
            color: var(--whitefont-color);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #ec4899, #f43f5e);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .bg-purple {
            background: var(--primary-gradient);
        }

        .profile-image-container {
            position: relative;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--primary-color);
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-title {
            font-size: 14px;
            color: var(--grayfont-color);
            margin-bottom: 5px;
        }

        .card-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--blackfont-color);
        }

        .profile-tabs {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
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

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--blackfont-color);
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 15px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background-color: var(--inputfieldhover-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .status-message {
            position: fixed;
            bottom: 25px;
            right: 25px;
            padding: 16px 28px 16px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            z-index: 1100;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-message.show {
            opacity: 1;
            transform: translateY(0);
        }

        .success-message {
            background-color: #10b981;
            border-left: 6px solid #059669;
        }

        .success-message::before {
            content: "";
            width: 24px;
            height: 24px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }

        .error-message {
            background-color: #ef4444;
            border-left: 6px solid #dc2626;
        }

        .error-message::before {
            content: "";
            width: 24px;
            height: 24px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }

        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .modal-backdrop.show {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            width: 650px;
            max-width: 90%;
            background-color: var(--card-bg);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            transform: translateY(-20px) scale(0.98);
            transition: var(--transition);
        }

        .modal-backdrop.show .modal {
            transform: translateY(0) scale(1);
        }

        .modal-header {
            padding: 24px 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.08));
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--blackfont-color);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--grayfont-color);
            transition: var(--transition);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover {
            color: var(--blackfont-color);
            background-color: rgba(0, 0, 0, 0.05);
        }

        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            background-color: var(--division-color);
        }

        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
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

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
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

        @media (max-width: 576px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Profile Management</h1>
        </header>

        <?php if ($success): ?>
            <div class="status-message success-message show" id="notification">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php elseif ($error): ?>
            <div class="status-message error-message show" id="notification">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">User Profile</div>
                        <div class="card-value"><?php echo htmlspecialchars($currentUser['firstname'] . ' ' . $currentUser['lastname']); ?></div>
                        <div style="font-size: 14px; color: var(--grayfont-color); margin-top: 5px;"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                        <div style="font-size: 14px; color: var(--secondary-color); margin-top: 5px;">Admin</div>
                        <button class="btn btn-primary" style="margin-top: 10px;" onclick="openImageModal()">
                            <i class="fas fa-camera"></i> Change Profile Picture
                        </button>
                    </div>
                    <div class="card-icon bg-purple">
                        <div class="profile-image-container">
                            <img src="../../assets/image/profile/<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile" class="profile-image" id="profilePreview">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-tabs">
            <div class="tab-navigation">
                <button class="tab-button active" data-tab="profile">
                    <i class="fas fa-user"></i> Profile Information
                </button>
                <button class="tab-button" data-tab="password">
                    <i class="fas fa-lock"></i> Change Password
                </button>
            </div>

            <div class="tab-content">
                <div class="tab-pane active" id="profile">
                    <form id="profileForm" method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstname">First Name *</label>
                                <input type="text" id="firstname" name="firstname" class="form-control" value="<?php echo htmlspecialchars($currentUser['firstname']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="lastname">Last Name *</label>
                                <input type="text" id="lastname" name="lastname" class="form-control" value="<?php echo htmlspecialchars($currentUser['lastname']); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($currentUser['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email (Read-only)</label>
                            <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($currentUser['email']); ?>" readonly>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary" id="saveProfileBtn">
                            <i class="fas fa-save"></i> Save Profile
                        </button>
                    </form>
                </div>

                <div class="tab-pane" id="password">
                    <form id="passwordForm" method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                                <div style="font-size: 12px; color: var(--grayfont-color); margin-top: 5px;">
                                    Minimum 8 characters with uppercase, lowercase, and number
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                            </div>
                        </div>
                        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 12px;">
                            <h4 style="margin-bottom: 10px; font-size: 14px; color: #856404;">
                                <i class="fas fa-exclamation-triangle"></i> Password Requirements
                            </h4>
                            <ul style="font-size: 13px; color: #856404; margin-left: 20px;">
                                <li>At least 8 characters long</li>
                                <li>Include uppercase letter, lowercase letter, and number</li>
                                <li>Avoid using personal information</li>
                                <li>Use a unique password not used elsewhere</li>
                            </ul>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary" id="savePasswordBtn">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Profile Picture Modal -->
        <div class="modal-backdrop" id="imageModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Change Profile Picture</h3>
                    <button class="close-modal" onclick="closeImageModal()">×</button>
                </div>
                <div class="modal-body">
                    <form id="imageForm" method="POST" enctype="multipart/form-data">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div class="profile-image-container" style="width: 120px; height: 120px; margin: 0 auto;">
                                <img src="../../assets/image/profile/<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Current Profile" class="profile-image" id="modalProfilePreview">
                            </div>
                            <p style="font-size: 14px; color: var(--grayfont-color); margin-top: 10px;">Current Picture</p>
                        </div>
                        <div class="file-upload-area" id="fileUploadArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">Click to upload or drag and drop</div>
                            <div class="upload-subtext">PNG, JPG, GIF up to 5MB</div>
                            <input type="file" id="profileImageInput" name="profile_image" accept="image/*" class="hidden">
                        </div>
                        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 12px;">
                            <h4 style="margin-bottom: 10px; font-size: 14px; color: #856404;">Image Requirements</h4>
                            <ul style="font-size: 13px; color: #856404; margin-left: 20px;">
                                <li>Maximum file size: 5MB</li>
                                <li>Supported formats: JPEG, PNG, GIF</li>
                                <li>Recommended size: 400x400 pixels</li>
                                <li>Square images work best</li>
                            </ul>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeImageModal()">Cancel</button>
                            <button type="submit" name="upload_picture" class="btn btn-primary" id="saveImageBtn">
                                <i class="fas fa-upload"></i> Upload Picture
                            </button>
                        </div>
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

            // Modal handling
            const imageModal = document.getElementById('imageModal');
            function openImageModal() {
                imageModal.classList.add('show');
                document.body.style.overflow = 'hidden';
                document.getElementById('imageForm').reset();
                document.getElementById('modalProfilePreview').src = '../../assets/image/profile/<?php echo htmlspecialchars($profileImageUrl); ?>';
            }

            function closeImageModal() {
                if (formChanged) {
                    if (confirm('You have unsaved changes. Are you sure you want to close?')) {
                        imageModal.classList.remove('show');
                        document.body.style.overflow = 'auto';
                        formChanged = false;
                    }
                } else {
                    imageModal.classList.remove('show');
                    document.body.style.overflow = 'auto';
                }
            }

            window.addEventListener('click', (e) => {
                if (e.target === imageModal) {
                    closeImageModal();
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && imageModal.classList.contains('show')) {
                    closeImageModal();
                }
            });

            // File upload handling
            const fileUploadArea = document.getElementById('fileUploadArea');
            const profileImageInput = document.getElementById('profileImageInput');
            const profilePreview = document.getElementById('profilePreview');
            const modalProfilePreview = document.getElementById('modalProfilePreview');

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
                    modalProfilePreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
                formChanged = true;
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

            // Form validation and loading state
            let formChanged = false;
            const formInputs = document.querySelectorAll('#profileForm input, #passwordForm input, #imageForm input');
            formInputs.forEach(input => {
                input.addEventListener('change', () => {
                    formChanged = true;
                });
            });

            document.getElementById('profileForm').addEventListener('submit', (e) => {
                const submitButton = document.getElementById('saveProfileBtn');
                const originalText = submitButton.innerHTML;
                addLoadingState(submitButton, 'Saving...');
                
                setTimeout(() => {
                    if (submitButton.disabled) {
                        removeLoadingState(submitButton, originalText);
                    }
                }, 3000);
                formChanged = false;
            });

            document.getElementById('passwordForm').addEventListener('submit', (e) => {
                const submitButton = document.getElementById('savePasswordBtn');
                const originalText = submitButton.innerHTML;
                addLoadingState(submitButton, 'Saving...');
                
                setTimeout(() => {
                    if (submitButton.disabled) {
                        removeLoadingState(submitButton, originalText);
                    }
                }, 3000);
                formChanged = false;
            });

            document.getElementById('imageForm').addEventListener('submit', (e) => {
                const submitButton = document.getElementById('saveImageBtn');
                const originalText = submitButton.innerHTML;
                addLoadingState(submitButton, 'Uploading...');
                
                setTimeout(() => {
                    if (submitButton.disabled) {
                        removeLoadingState(submitButton, originalText);
                    }
                }, 3000);
                formChanged = false;
            });

            function addLoadingState(button, text) {
                button.disabled = true;
                button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`;
            }

            function removeLoadingState(button, originalText) {
                button.disabled = false;
                button.innerHTML = originalText;
            }

            // Auto-hide notification messages
            const notification = document.getElementById('notification');
            if (notification && notification.classList.contains('show')) {
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 5000);
            }
        });
    </script>
</body>
</html>