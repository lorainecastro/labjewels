<?php
header('Content-Type: text/html; charset=UTF-8');

// PHP function to fetch products and pagination
function fetchProducts($xmlFile, $category, $page) {
    // Load XML file
    $xml = simplexml_load_file($xmlFile);
    if ($xml === false) {
        echo "<p>Error loading XML file.</p>";
        return;
    }

    $productsPerPage = 6;

    // Get products for the selected category
    $products = [];
    foreach ($xml->products->product as $product) {
        if ((string)$product->category === $category) {
            $products[] = $product;
        }
    }

    // Calculate pagination
    $totalProducts = count($products);
    $totalPages = max(1, ceil($totalProducts / $productsPerPage)); // Ensure at least 1 page
    $page = max(1, min($page, $totalPages)); // Ensure page is within bounds
    $start = ($page - 1) * $productsPerPage;
    $paginatedProducts = array_slice($products, $start, $productsPerPage);

    // Output product grid
    ob_start();
    echo '<div class="grid" id="product-grid">';
    if (empty($paginatedProducts)) {
        echo '<p>No products found in this category.</p>';
    } else {
        foreach ($paginatedProducts as $product) {
            // Skip products with incomplete data
            if (!isset($product->name) || !isset($product->description) || !isset($product->price) || !isset($product->image)) {
                continue;
            }
            echo '<div class="product-card hover-scale">';
            echo '<img src="' . htmlspecialchars($product->image) . '" alt="' . htmlspecialchars($product->name) . '" onerror="this.src=\'https://via.placeholder.com/500\'">';
            echo '<h3>' . htmlspecialchars($product->name) . '</h3>';
            echo '<p>' . htmlspecialchars($product->description) . '</p>';
            echo '<p>' . htmlspecialchars($product->currency) . ' ' . number_format((float)$product->price, 2) . '</p>';
            echo '<button class="button" onclick="alert(\'Sign up first\'); window.location.href=\'signup.php\';">Add to Cart</button>';
            echo '</div>';
    }
    }
    echo '</div>';

    // Output pagination
    echo '<div class="pagination" id="pagination">';
    // Previous Button
    echo '<a href="?category=' . urlencode($category) . '&page=' . max(1, $page - 1) . '" class="page-btn ' . ($page === 1 ? 'disabled' : '') . '" data-page="' . max(1, $page - 1) . '" data-category="' . htmlspecialchars($category) . '">';
    echo '<i class="fas fa-chevron-left"></i>';
    echo '</a>';
    
    // Page Numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        echo '<a href="?category=' . urlencode($category) . '&page=' . $i . '" class="page-btn ' . ($page === $i ? 'active' : '') . '" data-page="' . $i . '" data-category="' . htmlspecialchars($category) . '">';
        echo $i;
        echo '</a>';
    }
    
    // Next Button
    echo '<a href="?category=' . urlencode($category) . '&page=' . min($totalPages, $page + 1) . '" class="page-btn ' . ($page === $totalPages ? 'disabled' : '') . '" data-page="' . min($totalPages, $page + 1) . '" data-category="' . htmlspecialchars($category) . '">';
    echo '<i class="fas fa-chevron-right"></i>';
    echo '</a>';
    echo '</div>';

    $output = ob_get_clean();
    echo $output;
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['category']) && isset($_GET['page'])) {
    $category = htmlspecialchars($_GET['category']);
    $page = (int)$_GET['page'];
    fetchProducts('../xml/products.xml', $category, $page);
}
?>