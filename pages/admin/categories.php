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

$xmlFile = __DIR__ . '/../../xml/products.xml';

function loadXML($file) {
    if (!file_exists($file)) {
        die('XML file does not exist.');
    }
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        die('Error loading XML file.');
    }
    return $xml;
}

function saveXML($xml, $file) {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    return $dom->save($file);
}

function createCategory($xml, $data) {
    $categories = $xml->categories;
    $category = $categories->addChild('category');
    $category->addChild('name', htmlspecialchars($data['name']));
    $category->addChild('description', htmlspecialchars($data['description']));
    return saveXML($xml, $GLOBALS['xmlFile']);
}

function updateCategory($xml, $id, $data) {
    $index = 0;
    foreach ($xml->categories->category as $category) {
        if ($index == $id) {
            $category->name = htmlspecialchars($data['name']);
            $category->description = htmlspecialchars($data['description']);
            return saveXML($xml, $GLOBALS['xmlFile']);
        }
        $index++;
    }
    return false;
}

function deleteCategory($xml, $id, $password) {
    $pdo = getDBConnection();
    
    try {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([1]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            return false;
        }
        
        if (!password_verify($password, $admin['password'])) {
            return false;
        }
        
        $index = 0;
        foreach ($xml->categories->category as $category) {
            if ($index == $id) {
                unset($xml->categories->category[$index]);
                return saveXML($xml, $GLOBALS['xmlFile']);
            }
            $index++;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Delete category error: " . $e->getMessage());
        return false;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $xml = loadXML($xmlFile);
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $data = [
                    'name' => $_POST['name'],
                    'description' => $_POST['description']
                ];
                
                if (createCategory($xml, $data)) {
                    $_SESSION['message'] = 'Category created successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to create category.';
                }
                break;
                
            case 'update':
                $data = [
                    'name' => $_POST['name'],
                    'description' => $_POST['description']
                ];
                
                if (updateCategory($xml, $_POST['id'], $data)) {
                    $_SESSION['message'] = 'Category updated successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to update category.';
                }
                break;
                
            case 'delete':
                if (deleteCategory($xml, $_POST['id'], $_POST['password'])) {
                    $_SESSION['message'] = 'Category deleted successfully!';
                } else {
                    $_SESSION['error'] = 'Failed to delete category. Check password or category ID.';
                }
                break;
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Load XML for display
$xml = loadXML($xmlFile);

// Calculate dashboard metrics
$totalCategories = count($xml->categories->category);
$productsPerCategory = [];
$totalStocksPerCategory = [];
foreach ($xml->categories->category as $category) {
    $categoryName = (string)$category->name;
    $count = 0;
    $totalStock = 0;
    foreach ($xml->products->product as $product) {
        if ((string)$product->category === $categoryName) {
            $count++;
            $totalStock += (int)$product->stock;
        }
    }
    $productsPerCategory[$categoryName] = $count;
    $totalStocksPerCategory[$categoryName] = $totalStock;
}

// Calculate new metrics
$highestStockCategory = ['name' => 'N/A', 'stock' => 0];
$lowestStockCategory = ['name' => 'N/A', 'stock' => 0];
$outOfStockCategories = ['names' => '', 'stock' => 0];

if (!empty($totalStocksPerCategory)) {
    // Highest Stock Category
    $highestStockCategory['stock'] = max($totalStocksPerCategory);
    $highestStockCategory['name'] = array_search($highestStockCategory['stock'], $totalStocksPerCategory);
    
    // Lowest Stock Category (non-zero)
    $nonZeroStocks = array_filter($totalStocksPerCategory, function($stock) {
        return $stock > 0;
    });
    if (!empty($nonZeroStocks)) {
        $lowestStockCategory['stock'] = min($nonZeroStocks);
        $lowestStockCategory['name'] = array_search($lowestStockCategory['stock'], $totalStocksPerCategory);
    }
    
    // Out of Stock Categories
    $outOfStockNames = array_keys(array_filter($totalStocksPerCategory, function($stock) {
        return $stock == 0;
    }));
    if (!empty($outOfStockNames)) {
        $outOfStockCategories['names'] = implode(', ', $outOfStockNames);
        $outOfStockCategories['stock'] = 0;
    }
}

// Calculate total customers from the database
$pdo = getDBConnection();
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as customer_count FROM users WHERE isActive = 1 AND isDeleted = 0");
    $stmt->execute();
    $totalCustomers = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching customer count: " . $e->getMessage());
    $totalCustomers = 0; // Fallback to 0 on error
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management</title>
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
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px RGBA(0, 0, 0, 0.05);
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

        .bg-pink {
            background: var(--secondary-gradient);
        }

        .bg-red {
            background: linear-gradient(135deg, #ef4444, #f87171);
        }

        .bg-orange {
            background: linear-gradient(135deg, #f59e0b, #f3ad35ff);
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

        .categories-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .category-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow-x: auto;
            display: block;
        }

        .category-table th {
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

        .category-table td {
            padding: 12px 28px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            vertical-align: middle;
        }

        .category-table tr:last-child td {
            border-bottom: none;
        }

        .category-table tr:hover {
            background-color: var(--inputfield-color);
        }

        .category-table th:nth-child(1),
        .category-table td:nth-child(1) { width: 10%; } /* ID column */
        .category-table th:nth-child(2),
        .category-table td:nth-child(2) { width: 20%; } /* Name column */
        .category-table th:nth-child(3),
        .category-table td:nth-child(3) { width: 30%; } /* Description column */
        .category-table th:nth-child(4),
        .category-table td:nth-child(4) { width: 15%; } /* Total Products column */
        .category-table th:nth-child(5),
        .category-table td:nth-child(5) { width: 15%; } /* Total Stocks column */
        .category-table th:nth-child(6),
        .category-table td:nth-child(6) { width: 20%; } /* Actions column */

        .action-cell {
            display: flex;
            gap: 12px;
            margin-top: 18px;
        }

        @media (max-width: 768px) {
            .category-table th,
            .category-table td {
                padding: 6px 8px;
            }

            .action-cell {
                flex-direction: column;
            }

            .action-cell .btn {
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

        .form-control:focus {
            outline: none;
            background-color: var(--inputfieldhover-color);
            border-color: var(--primary-focus);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
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
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2'%%3E");
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
            flex: 1;
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

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .category-table {
                display: block;
                overflow-x: auto;
            }

            .action-cell {
                flex-direction: column;
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
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
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
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Category Management</h1>
            <button class="btn btn-primary" onclick="openForm('create')">
                <i class="fas fa-plus"></i> Add New Category
            </button>
        </header>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Categories</div>
                        <div class="card-value"><?php echo number_format($totalCategories); ?></div>
                    </div>
                    <div class="card-icon bg-purple">
                        <i class="fas fa-tags"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Highest Stock Category</div>
                        <div class="card-value"><?php echo htmlspecialchars($highestStockCategory['name']) . ' (' . number_format($highestStockCategory['stock']) . ')'; ?></div>
                    </div>
                    <div class="card-icon bg-green">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Lowest Stock Category</div>
                        <div class="card-value"><?php echo htmlspecialchars($lowestStockCategory['name']) . ' (' . number_format($lowestStockCategory['stock']) . ')'; ?></div>
                    </div>
                    <div class="card-icon bg-orange">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Out of Stock Category</div>
                        <div class="card-value"><?php echo htmlspecialchars($outOfStockCategories['names']) . ' (' . number_format($outOfStockCategories['stock']) . ')'; ?></div>
                    </div>
                    <div class="card-icon bg-red">
                        <i class="fas fa-ban"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="searchCategory" placeholder="Search categories..." onkeyup="filterCategories()">
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

        <div class="categories-container">
            <table class="category-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Category Description</th>
                        <th>Total Products</th>
                        <th>Total Stocks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="categoryTableBody">
                    <?php
                    if (isset($xml->categories->category) && count($xml->categories->category) > 0) {
                        $index = 0;
                        foreach ($xml->categories->category as $category) {
                            $categoryName = (string)$category->name;
                            $categoryDescription = (string)$category->description;
                            echo "<tr class='category-row' data-category='" . htmlspecialchars($categoryName) . "'>";
                            echo "<td>" . $index . "</td>";
                            echo "<td class='category-name'>" . htmlspecialchars($categoryName) . "</td>";
                            echo "<td class='category-description'>" . htmlspecialchars($categoryDescription) . "</td>";
                            echo "<td>" . $productsPerCategory[$categoryName] . "</td>";
                            echo "<td>" . $totalStocksPerCategory[$categoryName] . "</td>";
                            echo "<td class='action-cell'>";
                            echo "<button class='btn btn-primary' onclick=\"openForm('update', $index)\"><i class='fas fa-edit'></i> Edit</button>";
                            echo "<button class='btn btn-secondary' onclick=\"openDeleteModal($index, '" . htmlspecialchars($categoryName, ENT_QUOTES) . "')\"><i class='fas fa-trash'></i> Delete</button>";
                            echo "</td>";
                            echo "</tr>";
                            $index++;
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align: center; padding: 20px;'>No categories found. Add your first category!</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="pagination" id="pagination"></div>

        <!-- Add/Edit Category Modal -->
        <div class="modal-backdrop" id="categoryModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">Add New Category</h3>
                    <button class="close-modal" onclick="closeForm()">×</button>
                </div>
                <div class="modal-body">
                    <form id="categoryForm" method="POST">
                        <input type="hidden" name="action" id="formAction">
                        <input type="hidden" name="id" id="categoryId">
                        
                        <div class="form-group">
                            <label for="name">Category Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Category Description</label>
                            <input type="text" name="description" id="description" class="form-control" required>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeForm()">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="saveCategoryBtn">Save Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal-backdrop delete-modal" id="deleteModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Delete Category</h3>
                    <button class="close-modal" onclick="closeDeleteModal()">×</button>
                </div>
                <div class="modal-body">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ff6b6b; margin-bottom: 15px;"></i>
                        <p style="font-size: 18px; margin: 0;">Are you sure you want to delete</p>
                        <p style="font-size: 18px; font-weight: bold; color: #333; margin: 5px 0;"><span id="deleteCategoryName"></span>?</p>
                        <p style="color: #666; margin: 0;">This action cannot be undone.</p>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteCategoryId">
                        <div class="form-group">
                            <label for="password">Admin Password</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter admin password" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const categoryModal = document.getElementById('categoryModal');
        const deleteModal = document.getElementById('deleteModal');
        const itemsPerPage = 5;
        let currentPage = 1;

        // Category data from PHP
        const categories = <?php
            $categories = [];
            if (isset($xml->categories->category)) {
                $index = 0;
                foreach ($xml->categories->category as $category) {
                    $categories[] = [
                        'id' => $index++,
                        'name' => (string)$category->name,
                        'description' => (string)$category->description
                    ];
                }
            }
            echo json_encode($categories);
        ?>;

        function openForm(action, id = null) {
            document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Category' : 'Edit Category';
            document.getElementById('saveCategoryBtn').textContent = action === 'create' ? 'Add Category' : 'Update Category';
            document.getElementById('categoryForm').reset();
            document.getElementById('formAction').value = action;
            document.getElementById('categoryId').value = id || '';
            
            if (action === 'update' && id !== null) {
                fillFormData(id);
            }
            
            categoryModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeForm() {
            if (formChanged) {
                if (confirm('You have unsaved changes. Are you sure you want to close?')) {
                    categoryModal.classList.remove('show');
                    document.body.style.overflow = 'auto';
                    formChanged = false;
                }
            } else {
                categoryModal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        }

        function openDeleteModal(id, name) {
            document.getElementById('deleteCategoryName').textContent = name;
            document.getElementById('deleteCategoryId').value = id;
            document.getElementById('password').value = '';
            deleteModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function fillFormData(id) {
            const category = categories.find(c => c.id === id);
            if (category) {
                document.getElementById('name').value = category.name;
                document.getElementById('description').value = category.description;
            }
        }

        // Search and filter functionality
        function filterCategories() {
            const searchTerm = document.getElementById('searchCategory').value.toLowerCase();
            const rows = document.querySelectorAll('.category-row');
            let visibleRows = [];

            rows.forEach(row => {
                const categoryName = row.querySelector('.category-name').textContent.toLowerCase();
                const categoryDescription = row.querySelector('.category-description').textContent.toLowerCase();
                const showRow = !searchTerm || categoryName.includes(searchTerm) || categoryDescription.includes(searchTerm);
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
            const allRows = document.querySelectorAll('.category-row');

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
            if (e.target === categoryModal) {
                closeForm();
            }
            if (e.target === deleteModal) {
                closeDeleteModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (categoryModal.classList.contains('show')) {
                    closeForm();
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
            filterCategories(); // Initialize pagination
        });

        // Form validation
        document.getElementById('categoryForm').addEventListener('submit', (e) => {
            const name = document.getElementById('name').value.trim();
            const description = document.getElementById('description').value.trim();
            
            if (!name || !description) {
                e.preventDefault();
                alert('Category name and description are required');
                return;
            }
        });

        // Enhanced search with debouncing
        let searchTimeout;
        document.getElementById('searchCategory').addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterCategories, 300);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                openForm('create');
            }
            
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchCategory').focus();
            }
        });

        // Confirm before leaving page with unsaved changes
        let formChanged = false;
        const formInputs = document.querySelectorAll('#categoryForm input');
        
        formInputs.forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
        });

        document.getElementById('categoryForm').addEventListener('submit', () => {
            formChanged = false;
        });

        // Add loading state to buttons
        function addLoadingState(button, text = 'Loading...') {
            button.disabled = true;
            button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`;
        }

        function removeLoadingState(button, originalText) {
            button.disabled = false;
            button.innerHTML = originalText;
        }

        document.getElementById('categoryForm').addEventListener('submit', (e) => {
            const submitButton = document.getElementById('saveCategoryBtn');
            const originalText = submitButton.innerHTML;
            addLoadingState(submitButton, 'Saving...');
            
            setTimeout(() => {
                if (submitButton.disabled) {
                    removeLoadingState(submitButton, originalText);
                }
            }, 3000);
        });

        // Add tooltips for action buttons
        function addTooltips() {
            const editButtons = document.querySelectorAll('.btn-primary');
            const deleteButtons = document.querySelectorAll('.btn-secondary');
            
            editButtons.forEach(btn => {
                if (btn.innerHTML.includes('Edit')) {
                    btn.title = 'Edit this category';
                }
            });
            
            deleteButtons.forEach(btn => {
                if (btn.innerHTML.includes('Delete')) {
                    btn.title = 'Delete this category (requires admin password)';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', addTooltips);
    </script>
</body>
</html>