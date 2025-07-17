<?php
require '../../connection/config.php';
session_start();

// Check if user is already logged in
$currentUser = validateSession();

if (!$currentUser) {
    header("Location: ../pages/login.php");
    exit;
}

$profileImageUrl = $currentUser['icon'] ?? 'default-icon.png'; // Fallback to default icon

// Initialize PDO connection
$pdo = getDBConnection();

// Dashboard metrics
try {
    // Total Users (excluding deleted)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE isDeleted = 0 AND user_id != 1");
    $stmt->execute();
    $totalUsers = $stmt->fetchColumn();

    // Active Users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE isActive = 1 AND isDeleted = 0 AND user_id != 1");
    $stmt->execute();
    $activeUsers = $stmt->fetchColumn();

    // New This Month
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) AND isDeleted = 0 AND user_id != 1");
    $stmt->execute();
    $newThisMonth = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching dashboard metrics: " . $e->getMessage());
    $totalUsers = $activeUsers = $newThisMonth = 0;
}

// Delete Function
function deleteUser($pdo, $id, $password) {
    try {
        // Verify admin password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([1]);
        $admin = $stmt->fetch();
        
        if (!$admin || !password_verify($password, $admin['password'])) {
            return false;
        }
        
        // Soft delete user
        $stmt = $pdo->prepare("UPDATE users SET isDeleted = 1 WHERE user_id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Delete user error: " . $e->getMessage());
        return false;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if (deleteUser($pdo, $_POST['id'], $_POST['password'])) {
                    $_SESSION['message'] = 'User deleted successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to delete user. Check password or user ID.';
                }
                break;
                
            case 'export_pdf':
                  require_once('../../TCPDF-main/TCPDF-main/tcpdf.php');
                
                $pdf = new TCPDF();
                $pdf->SetCreator(PDF_CREATOR);
                $pdf->SetAuthor('LAB Jewels');
                $pdf->SetTitle('Users Report');
                $pdf->SetHeaderData('', 0, 'Users Report', 'Generated on ' . date('Y-m-d H:i:s'));
                $pdf->setHeaderFont(['helvetica', '', 10]);
                $pdf->setFooterFont(['helvetica', '', 8]);
                $pdf->SetMargins(10, 20, 10);
                $pdf->SetAutoPageBreak(true, 10);
                $pdf->AddPage();
                
                $html = '<h1>Users Report</h1><table border="1" cellpadding="5">
                    <thead>
                        <tr style="background-color: #f0f0f0;">
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>';
                
                $stmt = $pdo->prepare("SELECT user_id, CONCAT(firstname, ' ', lastname) as full_name, email, username, isActive FROM users WHERE isDeleted = 0");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $status = $row['isActive'] ? 'Active' : 'Inactive';
                    $html .= "<tr>
                        <td>{$row['user_id']}</td>
                        <td>" . htmlspecialchars($row['full_name']) . "</td>
                        <td>" . htmlspecialchars($row['email']) . "</td>
                        <td>" . htmlspecialchars($row['username']) . "</td>
                        <td>{$status}</td>
                    </tr>";
                }
                
                $html .= '</tbody></table>';
                $pdf->writeHTML($html, true, false, true, false, '');
                $pdf->Output('users_report.pdf', 'D');
                exit;
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Fetch users for display
try {
    $stmt = $pdo->prepare("SELECT user_id, CONCAT(firstname, ' ', lastname) as full_name, email, username, address, phone, isActive, isVerified, icon, created_at, updated_at FROM users WHERE isDeleted = 0 AND user_id != 1");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB Jewels - User Management</title>
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
            /* --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); */
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
            flex-wrap: wrap;
            gap: 10px;
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

        .btn-pdf {
            background: var(--primary-gradient);
            color: var(--whitefont-color);
            box-shadow: var(--shadow-sm);
        }

        .btn-pdf:hover {
            background: linear-gradient(135deg, #5256e0, #7c4ce7);
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

        .bg-blue {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
        }

        .bg-green {
            background: linear-gradient(135deg, #10b981, #34d399);
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

        .users-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .user-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow-x: auto;
            display: block;
        }

        .user-table th {
            background-color: var(--division-color);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--grayfont-color);
            font-size: 14px;
            position: sticky;
            top: 0;
            background: var(--card-bg);
            z-index: 1;
        }

        .user-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            vertical-align: middle;
        }

        .user-table tr:last-child td {
            border-bottom: none;
        }

        .user-table tr:hover {
            background-color: var(--inputfield-color);
        }

        .user-table th:nth-child(1),
        .user-table td:nth-child(1) { width: 5%; } /* ID */
        .user-table th:nth-child(2),
        .user-table td:nth-child(2) { width: 20%; } /* Full Name */
        .user-table th:nth-child(3),
        .user-table td:nth-child(3) { width: 20%; } /* Email */
        .user-table th:nth-child(4),
        .user-table td:nth-child(4) { width: 15%; } /* Username */
        .user-table th:nth-child(5),
        .user-table td:nth-child(5) { width: 10%; } /* Icon */
        .user-table th:nth-child(6),
        .user-table td:nth-child(6) { width: 10%; } /* Status */
        .user-table th:nth-child(7),
        .user-table td:nth-child(7) { width: 20%; } /* Actions */

        .action-cell {
            display: flex;
            gap: 12px;
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

        .form-control[readonly] {
            background-color: var(--division-color);
            cursor: default;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            background-color: var(--division-color);
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

        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 16px;
            box-shadow: var(--shadow-md);
        }

        .search-box {
            flex: 2;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 14px;
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-focus);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .search-box::before {
            content: "";
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }

        .filter-box {
            flex: 1;
        }

        .filter-box select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--inputfield-color);
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-box select:focus {
            outline: none;
            border-color: var(--primary-focus);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 40px;
        }

        .pagination button {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            font-size: 14px;
            color: var(--primary-color);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .pagination button:hover {
            background-color: rgba(99, 102, 241, 0.2);
        }

        .pagination button.active {
            background: var(--primary-gradient);
            color: var(--whitefont-color);
            border-color: var(--primary-color);
            border: 1px solid var(--primary-focus);

        }

        .pagination button.active:hover {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }


        .pagination button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .user-table {
                display: block;
                overflow-x: auto;
            }

            .action-cell {
                flex-direction: column;
                gap: 8px;
            }

            .filter-section {
                flex-direction: column;
                padding: 12px;
            }
        }

        @media (max-width: 576px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>User Management</h1>
            <div>
                <button class="btn btn-pdf" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> Export Users
                </button>
            </div>
        </header>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Users</div>
                        <div class="card-value"><?php echo number_format($totalUsers); ?></div>
                    </div>
                    <div class="card-icon bg-purple">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Active Users</div>
                        <div class="card-value"><?php echo number_format($activeUsers); ?></div>
                    </div>
                    <div class="card-icon bg-blue">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">New This Month</div>
                        <div class="card-value"><?php echo number_format($newThisMonth); ?></div>
                    </div>
                    <div class="card-icon bg-green">
                        <i class="fas fa-user-plus"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="searchUser" placeholder="Search users..." onkeyup="filterUsers()">
            </div>
            <div class="filter-box">
                <select id="statusFilter" onchange="filterUsers()">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="status-message success-message show" id="notification">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="status-message error-message show" id="notification">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="users-container">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Icon</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <?php
                    if (!empty($users)) {
                        foreach ($users as $user) {
                            $status = $user['isActive'] ? 'Active' : 'Inactive';
                            echo "<tr class='user-row' 
                                data-fullname='" . htmlspecialchars($user['full_name']) . "' 
                                data-email='" . htmlspecialchars($user['email']) . "' 
                                data-username='" . htmlspecialchars($user['username']) . "' 
                                data-status='" . ($user['isActive'] ? 'active' : 'inactive') . "'>";
                            echo "<td>{$user['user_id']}</td>";
                            echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                            // echo "<td><img src='../../assets/image/profile/" . htmlspecialchars($user['icon']) . "' alt='User Icon' style='width: 45px; height: 45px; border-radius: 50%; object-fit: cover;'></td>";
                            echo "<td><img src='../../assets/image/profile/" . htmlspecialchars($user['icon'] ?: 'no-icon.png') . "' alt='User Icon' style='width: 45px; height: 45px; border-radius: 50%; object-fit: cover;' onerror=\"this.src='../../assets/image/profile/no-icon.png';\"></td>";
                            echo "<td>$status</td>";
                            echo "<td class='action-cell'>";
                            echo "<button class='btn btn-primary' onclick=\"openViewModal({$user['user_id']})\"><i class='fas fa-eye'></i> View</button>";
                            echo "<button class='btn btn-secondary' onclick=\"openDeleteModal({$user['user_id']}, '" . htmlspecialchars($user['full_name'], ENT_QUOTES) . "')\"><i class='fas fa-trash'></i> Delete</button>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align: center; padding: 20px;'>No users found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="pagination" id="pagination"></div>

        <!-- View User Modal -->
        <div class="modal-backdrop" id="viewModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">User Details</h3>
                    <button class="close-modal" onclick="closeViewModal()">×</button>
                </div>
                <div class="modal-body">
                    <div class="form-group" style="text-align: center; margin-bottom: 20px;">
                        <img id="viewIcon" src="" alt="User Icon" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--border-color);">
                    </div>
                    <div class="form-group">
                        <label>Full Name:</label>
                        <input type="text" id="viewFullName" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="text" id="viewEmail" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" id="viewUsername" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Address:</label>
                        <input type="text" id="viewAddress" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Phone:</label>
                        <input type="text" id="viewPhone" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Status:</label>
                        <input type="text" id="viewStatus" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Verified:</label>
                        <input type="text" id="viewVerified" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Member Since:</label>
                        <input type="text" id="viewCreatedAt" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Last Updated:</label>
                        <input type="text" id="viewUpdatedAt" class="form-control" readonly>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal-backdrop delete-modal" id="deleteModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Delete User</h3>
                    <button class="close-modal" onclick="closeDeleteModal()">×</button>
                </div>
                <div class="modal-body">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ff6b6b; margin-bottom: 15px;"></i>
                        <p style="font-size: 18px; margin: 0;">Are you sure you want to delete</p>
                        <p style="font-size: 18px; font-weight: bold; color: #333; margin: 5px 0;"><span id="deleteUserName"></span>?</p>
                        <p style="color: #666; margin: 0;">This action cannot be undone.</p>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteUserId">
                        <div class="form-group">
                            <label for="password">Admin Password</label>
                            <input type="password" name="password" id="deletePassword" class="form-control" placeholder="Enter admin password" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                            <button type="submit" class="btn btn-secondary">Delete User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const viewModal = document.getElementById('viewModal');
        const deleteModal = document.getElementById('deleteModal');
        const itemsPerPage = 6;
        let currentPage = 1;

        // User data from PHP
        const users = <?php echo json_encode($users); ?>;

        function openViewModal(id) {
            const user = users.find(u => u.user_id == id);
            if (user) {
                document.getElementById('viewIcon').src = '../../assets/image/profile/' + (user.icon || 'no-icon.png');
                document.getElementById('viewFullName').value = user.full_name || '';
                document.getElementById('viewEmail').value = user.email || '';
                document.getElementById('viewUsername').value = user.username || '';
                document.getElementById('viewAddress').value = user.address || '';
                document.getElementById('viewPhone').value = user.phone || '';
                document.getElementById('viewStatus').value = user.isActive ? 'Active' : 'Inactive';
                document.getElementById('viewVerified').value = user.isVerified ? 'Yes' : 'No';
                document.getElementById('viewCreatedAt').value = user.created_at || 'N/A';
                document.getElementById('viewUpdatedAt').value = user.updated_at || 'N/A';
            }
            
            viewModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeViewModal() {
            viewModal.classList.remove('show');
            document.body.style.overflow = '';
        }

        function openDeleteModal(id, name) {
            document.getElementById('deleteUserName').textContent = name;
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deletePassword').value = '';
            deleteModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('show');
            document.body.style.overflow = '';
        }

        function exportPDF() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'export_pdf';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        // Search and filter functionality
        function filterUsers() {
            const searchTerm = document.getElementById('searchUser').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.user-row');
            let visibleRows = [];

            rows.forEach(row => {
                const fullname = row.dataset.fullname.toLowerCase();
                const email = row.dataset.email.toLowerCase();
                const username = row.dataset.username.toLowerCase();
                const status = row.dataset.status;
                
                const matchesSearch = !searchTerm || 
                    fullname.includes(searchTerm) || 
                    email.includes(searchTerm) || 
                    username.includes(searchTerm);
                
                const matchesStatus = statusFilter === 'all' || 
                    (statusFilter === 'active' && status === 'active') || 
                    (statusFilter === 'inactive' && status === 'inactive');
                
                const showRow = matchesSearch && matchesStatus;
                row.style.display = showRow ? '' : 'none';
                if (showRow) visibleRows.push(row);
            });

            updatePagination(visibleRows);
        }

        // Pagination functionality
        function updatePagination(rows) {
            const totalItems = rows.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';

            if (totalPages <= 1) {
                rows.forEach(row => row.style.display = '');
                return;
            }

            // Previous button
            const prevButton = document.createElement('button');
            prevButton.textContent = '←';
            prevButton.disabled = currentPage === 1;
            prevButton.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    displayPage(rows);
                }
            });
            pagination.appendChild(prevButton);

            // Page number buttons
            for (let i = 1; i <= totalPages; i++) {
                const pageButton = document.createElement('button');
                pageButton.textContent = i;
                pageButton.className = i === currentPage ? 'active' : '';
                pageButton.addEventListener('click', () => {
                    currentPage = i;
                    displayPage(rows);
                });
                pagination.appendChild(pageButton);
            }

            // Next button
            const nextButton = document.createElement('button');
            nextButton.textContent = '→';
            nextButton.disabled = currentPage === totalPages;
            nextButton.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    displayPage(rows);
                }
            });
            pagination.appendChild(nextButton);

            displayPage(rows);
        }

        function displayPage(rows) {
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const allRows = document.querySelectorAll('.user-row');

            allRows.forEach(row => row.style.display = 'none');
            rows.slice(start, end).forEach(row => row.style.display = '');

            // Update active page button
            document.querySelectorAll('.pagination button').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent == currentPage && !btn.textContent.includes('←') && !btn.textContent.includes('→')) {
                    btn.classList.add('active');
                }
            });
        }

        // Close modals when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === viewModal) {
                closeViewModal();
            }
            if (e.target === deleteModal) {
                closeDeleteModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (viewModal.classList.contains('show')) {
                    closeViewModal();
                }
                if (deleteModal.classList.contains('show')) {
                    closeDeleteModal();
                }
            }
        });

        // Auto-hide notification messages
        document.addEventListener('DOMContentLoaded', () => {
            const notification = document.getElementById('notification');
            if (notification && notification.classList.contains('show')) {
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 5000);
            }
            filterUsers(); // Initialize pagination
        });

        // Enhanced search with debouncing
        let searchTimeout;
        document.getElementById('searchUser').addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterUsers, 300);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchUser').focus();
            }
        });

        // Add tooltips for action buttons
        function addTooltips() {
            const viewButtons = document.querySelectorAll('.btn-primary');
            const deleteButtons = document.querySelectorAll('.btn-secondary');
            const pdfButton = document.querySelector('.btn-pdf');
            
            viewButtons.forEach(btn => {
                if (btn.innerHTML.includes('View')) {
                    btn.title = 'View user details';
                }
            });
            
            deleteButtons.forEach(btn => {
                if (btn.innerHTML.includes('Delete')) {
                    btn.title = 'Delete this user (requires admin password)';
                }
            });
            
            if (pdfButton) {
                pdfButton.title = 'Export users to PDF';
            }
        }

        document.addEventListener('DOMContentLoaded', addTooltips);
    </script>
</body>
</html>