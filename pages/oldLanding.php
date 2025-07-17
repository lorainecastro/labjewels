<?php
// PHP function to parse XML and display categories and products
function displayCategoriesAndProducts($xmlFile)
{
  // Load XML file
  $xml = simplexml_load_file($xmlFile);
  if ($xml === false) {
    echo "<p>Error loading XML file.</p>";
    return;
  }

  // Get selected category from URL, default to 'Necklace' if not set
  $selectedCategory = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : 'Necklace';

  // Get current page from URL, default to 1
  $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
  $productsPerPage = 6;

  // Get all categories
  $categories = [];
  foreach ($xml->categories->category as $category) {
    $categories[] = (string)$category;
  }

  // Filter out invalid categories (e.g., 'new category')
  $categories = array_filter($categories, function ($cat) {
    return $cat !== 'new category';
  });

  // Get products for the selected category
  $products = [];
  foreach ($xml->products->product as $product) {
    if ((string)$product->category === $selectedCategory) {
      $products[] = $product;
    }
  }

  // Calculate pagination
  $totalProducts = count($products);
  $totalPages = max(1, ceil($totalProducts / $productsPerPage)); // Ensure at least 1 page even if empty
  $page = max(1, min($page, $totalPages)); // Ensure page is within bounds
  $start = ($page - 1) * $productsPerPage;
  $paginatedProducts = array_slice($products, $start, $productsPerPage);

  // Display categories (always show all categories, even empty ones)
  echo '<section id="categories" class="category-section">';
  echo '<h2 class="fade-in">Shop by Category</h2>';
  echo '<div class="category-grid">';
  foreach ($categories as $category) {
    $categoryLower = strtolower($category);
    $imageMap = [
      'Necklace' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60',
      'Ring' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60',
      'Bracelet' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60',
      'Earrings' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60',
      'Anklet' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60'
    ];
    $image = isset($imageMap[$category]) ? $imageMap[$category] : 'https://via.placeholder.com/500';
    echo '<div class="category-card hover-scale">';
    echo '<a href="?category=' . urlencode($category) . '" data-category="' . htmlspecialchars($category) . '" class="category-link">';
    echo '<img src="' . $image . '" alt="' . htmlspecialchars($category) . '">';
    echo '<div class="overlay"><h3>' . htmlspecialchars($category) . '</h3></div>';
    echo '</a>';
    echo '</div>';
  }
  echo '</div>';
  echo '</section>';

  // Display products
  echo '<section id="shop" class="shop">';
  echo '<h2 class="fade-in" id="shop-title">' . htmlspecialchars($selectedCategory) . ' Collection</h2>';
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
      echo '<p class="price">' . htmlspecialchars($product->currency) . ' ' . number_format((float)$product->price, 2) . '</p>';
      echo '<button class="button" onclick="alert(\'Sign up first\'); window.location.href=\'signup.php\';">Add to Cart</button>';
      echo '</div>';
    }
  }
  echo '</div>';

  // Pagination
  echo '<div class="pagination" id="pagination">';
  // Previous Button
  echo '<a href="?category=' . urlencode($selectedCategory) . '&page=' . max(1, $page - 1) . '" class="page-btn ' . ($page === 1 ? 'disabled' : '') . '" data-page="' . max(1, $page - 1) . '" data-category="' . htmlspecialchars($selectedCategory) . '">';
  echo '<i class="fas fa-chevron-left"></i>';
  echo '</a>';

  // Page Numbers
  for ($i = 1; $i <= $totalPages; $i++) {
    echo '<a href="?category=' . urlencode($selectedCategory) . '&page=' . $i . '" class="page-btn ' . ($page === $i ? 'active' : '') . '" data-page="' . $i . '" data-category="' . htmlspecialchars($selectedCategory) . '">';
    echo $i;
    echo '</a>';
  }

  // Next Button
  echo '<a href="?category=' . urlencode($selectedCategory) . '&page=' . min($totalPages, $page + 1) . '" class="page-btn ' . ($page === $totalPages ? 'disabled' : '') . '" data-page="' . min($totalPages, $page + 1) . '" data-category="' . htmlspecialchars($selectedCategory) . '">';
  echo '<i class="fas fa-chevron-right"></i>';
  echo '</a>';
  echo '</div>';
  echo '</section>';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAB Jewels - Minimalist Jewelry</title>
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
      --border-color: #e5e7eb;
      --card-bg: #ffffff;
      --primary-color: #1a1a1a;
      --inputfieldhover-color: #f3f4f6;
      --accent-color: #8b5cf6;
      --accent-hover: #4f46e5;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background-color: var(--white);
      color: var(--black);
      font-family: 'Inter', -apple-system, sans-serif;
      line-height: 1.7;
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

    .fade-in {
      animation: fadeIn 1.5s ease-in-out;
    }

    @keyframes fadeIn {
      0% {
        opacity: 0;
        transform: translateY(30px);
      }

      100% {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .hover-scale {
      transition: var(--transition);
    }

    .hover-scale:hover {
      transform: scale(1.02);
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

    .logo h1 {
      font-size: 1.8rem;
      font-weight: 700;
      font-family: 'Poppins', sans-serif;
      background: var(--white);
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

    .dropdown {
      position: relative;
    }

    .dropdown-content {
      display: none;
      position: absolute;
      background-color: var(--white);
      min-width: 120px;
      box-shadow: var(--shadow-md);
      border-radius: 6px;
      top: 100%;
      right: 0;
      z-index: 1000;
    }

    .dropdown:hover .dropdown-content {
      display: block;
    }

    .dropdown-content a {
      display: block;
      padding: 10px 15px;
      color: var(--black);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      text-transform: none;
    }

    .dropdown-content a:hover {
      background-color: var(--light-gray);
      color: var(--black);
    }

    .search-container {
      display: flex;
      align-items: center;
      position: relative;
      width: 100%;
      max-width: 300px;
    }

    .search-container input {
      width: 100%;
      padding: 12px 40px 12px 20px;
      border: 1px solid var(--light-gray);
      border-radius: 25px;
      font-size: 15px;
      font-family: 'Inter', sans-serif;
      background-color: var(--light-gray);
      transition: var(--transition);
      box-shadow: var(--shadow-sm);
    }

    .search-container input:focus {
      outline: none;
      border-color: var(--black);
      background-color: var(--white);
      box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.05);
    }

    .search-container i {
      position: absolute;
      right: 15px;
      color: var(--gray);
      font-size: 16px;
      pointer-events: none;
    }

    .secondary-nav {
      background-color: var(--white);
      padding: 10px 40px;
      border-top: 1px solid var(--light-gray);
      display: none;
      justify-content: flex-end;
      max-width: 1280px;
      margin: 0 auto;
    }

    section {
      padding: 80px 20px;
    }

    .hero {
      position: relative;
      padding: 150px 20px;
      text-align: center;
      text-decoration: none;
      overflow: hidden;
    }

    .hero video {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: -1;
      opacity: 1;
    }

    .hero .overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      z-index: -1;
    }

    .hero h1 {
      font-size: 56px;
      font-weight: 900;
      color: var(--white);
      margin-bottom: 25px;
      letter-spacing: -1px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .hero p {
      font-size: 22px;
      color: var(--white);
      max-width: 700px;
      margin: 0 auto 40px;
      font-weight: 400;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .hero .buttons {
      display: flex;
      justify-content: center;
      gap: 20px;
      text-decoration: none;
    }

    .category-section {
      max-width: 1280px;
      margin: 0 auto;
      text-align: center;
      margin-bottom: -50px;
    }

    .category-section h2 {
      font-size: 42px;
      font-weight: 800;
      color: var(--black);
      margin-bottom: 50px;
      letter-spacing: -0.5px;
    }

    .category-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 30px;
    }

    .category-card {
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      transition: var(--transition);
      cursor: pointer;
      box-shadow: var(--shadow-sm);
    }

    .category-card img {
      width: 100%;
      height: 180px;
      object-fit: cover;
      transition: var(--transition);
    }

    .category-card .overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 1;
      transition: var(--transition);
    }

    .category-card:hover .overlay {
      opacity: 1;
    }

    .category-card:hover img {
      transform: scale(1.05);
    }

    .category-card h3 {
      font-size: 20px;
      font-weight: 700;
      color: var(--white);
      text-transform: uppercase;
      letter-spacing: 1px;
      z-index: 1;
    }

    .shop,
    .about,
    .team,
    .contact {
      max-width: 1280px;
      margin: 0 auto;
    }

    .shop h2,
    .about h2,
    .team h2,
    .contact h2 {
      font-size: 42px;
      font-weight: 800;
      color: var(--black);
      text-align: center;
      margin-bottom: 50px;
      letter-spacing: -0.5px;
    }

    .shop .grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 40px;
    }

    .product-card {
      background-color: var(--white);
      border: 1px solid var(--light-gray);
      border-radius: 12px;
      padding: 25px;
      text-align: center;
      transition: var(--transition);
    }

    .product-card img {
      width: 100%;
      height: 280px;
      object-fit: cover;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .product-card h3 {
      font-size: 22px;
      font-weight: 700;
      color: var(--black);
      margin-bottom: 12px;
      font-family: poppins;
    }

    .product-card p {
      /* font-size: 18px; */
      color: var(--gray);
      margin-bottom: 20px;
      line-height: 1.5;
      font-family: poppins;
    }

    .product-card .price {
      color: var(--accent-color);
      margin-bottom: 20px;
      line-height: 1.5;
      font-family: poppins;
      font-size: 18px;
      font-weight: bold;
    }

    .product-card .button {
      width: 100%;
    }

    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      margin-top: 40px;
      gap: 8px;
    }

    .page-btn {
      min-width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      border: 1px solid var(--border-color);
      background-color: var(--card-bg);
      font-size: 14px;
      color: var(--primary-color);
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
    }

    .page-btn.active {
      background: var(--primary-color);
      color: var(--white);
      border-color: var(--primary-color);
      font-weight: 600;
    }

    .page-btn:hover:not(.active) {
      background-color: var(--inputfieldhover-color);
      transform: translateY(-1px);
    }

    .page-btn.disabled {
      opacity: 0.5;
      cursor: not-allowed;
      pointer-events: none;
    }

    .about .content {
      display: flex;
      gap: 40px;
      align-items: stretch;
      justify-content: space-between;
      min-height: 400px;
    }

    .about .content>div {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .about p {
      font-size: 18px;
      color: var(--gray);
      margin-bottom: 25px;
      font-weight: 400;
    }

    .about .card {
      background-color: var(--white);
      border: 1px solid var(--light-gray);
      border-radius: 12px;
      padding: 30px;
      position: relative;
      box-shadow: 0 0 10px #e5e7eb;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .about .card h3 {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 15px;
    }

    .team .grid {
      width: 1000px;
      display: grid;
      margin: auto;
      grid-template-columns: repeat(4, 1fr);
    }

    .grid {
      justify-content: center;
      align-items: center;
    }

    .team-card {
      background-color: var(--white);
      border-radius: 12px;
      padding: 25px;
      text-align: center;
      transition: var(--transition);
    }

    .team-card img {
      width: 150px;
      height: 150px;
      object-fit: cover;
      border-radius: 50%;
      margin: 0 auto 20px;
      display: block;
      transition: var(--transition);
    }

    .team-card img:hover {
      transform: scale(1.02);
    }

    .team-card h3 {
      font-size: 22px;
      font-weight: 700;
      color: var(--black);
      margin-bottom: 12px;
    }

    .team-card p.position {
      font-size: 18px;
      color: var(--gray);
      margin-bottom: 20px;
    }

    .team-card .social-links {
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    .team-card .social-link {
      width: 36px;
      height: 36px;
      background-color: var(--light-gray);
      border-radius: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      color: var(--black);
      transition: var(--transition);
      font-size: 16px;
      text-decoration: none;
    }

    .team-card .social-link:hover {
      background-color: var(--black);
      color: var(--white);
      transform: translateY(-2px);
    }

    .contact .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      align-items: stretch;
    }

    #about {
      margin-top: -50px;
    }

    #team {
      margin-top: -50px;
    }

    #contact {
      margin-top: -80px;
    }

    .contact .info h3,
    .contact .form h3 {
      font-size: 26px;
      font-weight: 700;
      color: var(--black);
      margin-bottom: 20px;
    }

    .contact .info p {
      font-size: 18px;
      color: var(--gray);
      margin-bottom: 15px;
      font-weight: 400;
    }

    .contact .form {
      background-color: var(--white);
      border: 1px solid var(--light-gray);
      border-radius: 12px;
      padding: 30px;
      transition: var(--transition);
      box-shadow: 0 0 10px #e5e7eb;
    }

    .contact .form input,
    .contact .form textarea {
      width: 100%;
      padding: 14px;
      margin-bottom: 25px;
      border: 2px solid var(--light-gray);
      border-radius: 8px;
      font-size: 16px;
      transition: var(--transition);
      font-family: 'Inter', sans-serif;
      background-color: var(--white);
    }

    .contact .form input:focus,
    .contact .form textarea:focus {
      outline: none;
      border-color: var(--black);
      box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.05);
    }

    .contact .form .button {
      width: 100%;
      padding: 14px;
      font-size: 15px;
    }

    .faq {
      background-color: #f9f9f9;
      color: var(--black);
      padding: 60px 20px;
    }

    .faq h2 {
      font-size: 42px;
      font-weight: 800;
      text-align: center;
      margin-bottom: 20px;
      letter-spacing: -0.5px;
    }

    .faq p {
      font-size: 18px;
      color: var(--gray);
      text-align: center;
      margin-bottom: 40px;
      max-width: 800px;
      margin-left: auto;
      margin-right: auto;
    }

    .faq .grid {
      max-width: 1280px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .faq-item {
      background-color: #f9f9f9;
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      padding: 15px;
      cursor: pointer;
      transition: var(--transition);
    }

    .faq-item:hover {
      background-color: var(--light-gray);
    }

    .faq-item h3 {
      font-size: 18px;
      font-weight: 600;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .faqQuestion .arrow {
      float: right;
      transition: transform 0.3s ease-in-out;
    }

    .faq-item .content {
      display: none;
      margin-top: 10px;
      font-size: 16px;
      color: var(--gray);
    }

    .faq-item.active .content {
      display: block;
    }

    .arrow {
      transform: rotate(90deg);
    }

    .faq-item.active .arrow {
      transform: rotate(180deg);
    }

    .footer {
      background: var(--black);
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

    @media (max-width: 992px) {
      .hero h1 {
        font-size: 42px;
      }

      .hero p {
        font-size: 18px;
      }

      .category-grid {
        grid-template-columns: repeat(3, 1fr);
      }

      .shop .grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .team .grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .contact .grid {
        grid-template-columns: 1fr;
      }

      .contact .form {
        margin-top: 30px;
      }

      .faq .grid {
        grid-template-columns: 1fr;
      }

      .about .content {
        min-height: auto;
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

      nav ul li.search-container {
        display: none;
      }

      .menu-toggle {
        display: block;
      }

      .secondary-nav {
        display: flex;
        padding: 10px 20px;
      }

      .search-container {
        max-width: 100%;
      }

      .hero {
        padding: 80px 20px;
      }

      .hero h1 {
        font-size: 36px;
      }

      .hero p {
        font-size: 16px;
      }

      .hero .buttons {
        flex-direction: column;
        gap: 15px;
      }

      .hero .button {
        padding: 10px 20px;
        font-size: 14px;
      }

      .category-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .shop h2,
      .about h2,
      .team h2,
      .contact h2 {
        font-size: 32px;
      }

      .shop .grid {
        grid-template-columns: 1fr;
      }

      .team .grid {
        grid-template-columns: 1fr;
      }

      .about .content {
        flex-direction: column;
      }

      .about .content>div {
        width: 100%;
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

      .hero video {
        display: none;
      }

      .hero {
        background: var(--light-gray);
      }

      .hero h1,
      .hero p {
        color: var(--black);
        text-shadow: none;
      }
    }

    @media (max-width: 576px) {
      section {
        padding: 50px 15px;
      }

      .hero h1 {
        font-size: 30px;
      }

      .hero p {
        font-size: 15px;
      }

      .hero .button {
        padding: 8px 16px;
        font-size: 13px;
      }

      .category-grid {
        grid-template-columns: 1fr;
      }

      .shop h2,
      .about h2,
      .team h2,
      .contact h2 {
        font-size: 26px;
      }

      .contact .info h3,
      .contact .form h3 {
        font-size: 22px;
      }

      .footer {
        padding: 40px 15px;
      }

      .copyright {
        font-size: 14px;
      }
    }

    @media (max-width: 480px) {
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

      nav ul li.search-container {
        display: none;
      }

      .menu-toggle {
        display: block;
      }

      .secondary-nav {
        display: flex;
        padding: 10px 20px;
      }

      .search-container {
        max-width: 100%;
      }

      .hero h1 {
        font-size: 26px;
      }

      .hero p {
        font-size: 14px;
      }

      .hero .button {
        padding: 6px 12px;
        font-size: 12px;
      }

      .shop h2,
      .category-section h2,
      .about h2,
      .team h2,
      .contact h2 {
        font-size: 22px;
      }

      .contact .info h3,
      .contact .form h3 {
        font-size: 20px;
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
        <li><a href="#home">Home</a></li>
        <li><a href="#categories">Shop</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="#contact">Contact</a></li>
        <li><a href="#cart"><i class="fas fa-shopping-cart" title="Cart"></i></a></li>
        <li class="dropdown">
          <a href="#"><i class="fas fa-user"></i></a>
          <div class="dropdown-content">
            <a href="login.php">Login</a>
            <a href="signup.php">Sign Up</a>
          </div>
        </li>
        <li class="search-container">
          <input type="text" placeholder="Search jewelry...">
          <i class="fas fa-search"></i>
        </li>
      </ul>
    </nav>
    <div class="secondary-nav">
      <div class="search-container">
        <input type="text" placeholder="Search jewelry...">
        <i class="fas fa-search"></i>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section id="home" class="hero">
    <video autoplay muted loop playsinline>
      <source src="../assets/video/coverr-a-girl-wearing-many-pieces-of-jewelry-5100-1080p.mp4" type="video/mp4">
      Your browser does not support the video tag.
    </video>
    <div class="overlay"></div>
    <h1 class="fade-in">Discover LAB Jewels</h1>
    <p class="fade-in">Handcrafted elegance for every moment. Explore our minimalist jewelry collections.</p>
    <div class="buttons fade-in">
      <a href="#shop" class="button">Shop Now</a>
      <a href="#about" class="button secondary">Learn More</a>
    </div>
  </section>

  <!-- Categories and Products -->
  <?php displayCategoriesAndProducts('../xml/products.xml'); ?>

  <!-- About Section -->
  <section id="about" class="about">
    <h2 class="fade-in">About LAB Jewels</h2>
    <div class="content">
      <div>
        <p>At LAB Jewels, we craft timeless pieces with passion and precision. Each jewel embodies minimalist elegance.</p>
        <p>Our collections are designed to celebrate life's special moments with understated sophistication.</p>
        <h3>Our Craft</h3>
        <p>We source the finest materials to create jewelry that shines with simplicity and durability.</p>
        <a href="#shop" class="button">Explore Collections</a>
      </div>
      <div class="card hover-scale">
        <h3>Our Mission</h3>
        <p>To create jewelry that empowers individuality and celebrates personal style with sustainable, high-quality craftsmanship.</p>
        <h3>Our Vision</h3>
        <p>To redefine minimalist jewelry by blending timeless design with ethical practices, inspiring confidence and elegance worldwide.</p>
      </div>
    </div>
  </section>

  <!-- Team Section -->
  <section id="team" class="team">
    <h2 class="fade-in">Meet Our Team</h2>
    <div class="grid">
      <div class="team-card">
        <img src="../assets/image/system/lorainecastro-yellow.png" alt="Loraine Castro" onerror="this.src='../assets/image/system/no-icon.png'">
        <h3>Loraine Castro</h3>
        <p class="position">Founder & CEO</p>
        <div class="social-links">
          <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
          <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-link"><i class="fab fa-x-twitter"></i></a>
        </div>
      </div>
      <div class="team-card">
        <img src="../assets/image/system/brian.png" alt="Brian Amador" onerror="this.src='../assets/image/system/no-icon.png'">
        <h3>Brian Amador</h3>
        <p class="position">Head of Design</p>
        <div class="social-links">
          <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
          <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-link"><i class="fab fa-x-twitter"></i></a>
        </div>
      </div>
      <div class="team-card">
        <img src="../assets/image/system/anjonette.png" alt="Anjonette Pedrajas" onerror="this.src='../assets/image/system/no-icon.png'">
        <h3>Anjonette Pedrajas</h3>
        <p class="position">Product Development</p>
        <div class="social-links">
          <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
          <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-link"><i class="fab fa-x-twitter"></i></a>
        </div>
      </div>
      <div class="team-card">
        <img src="../assets/image/system/francis" alt="Francis Javier" onerror="this.src='../assets/image/system/no-icon.png'">
        <h3>Francis Javier</h3>
        <p class="position">Marketing Director</p>
        <div class="social-links">
          <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
          <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-link"><i class="fab fa-x-twitter"></i></a>
        </div>
      </div>
    </div>
  </section>

  <!-- Contact Section -->
  <section id="contact" class="contact">
    <h2 class="fade-in">Get in Touch</h2>
    <div class="grid">
      <div class="info">
        <h3>We're Here to Help</h3>
        <p>Have a question or need assistance? Reach out to our team.</p>
        <p><strong>Email:</strong> support@labjewels.com</p>
        <p><strong>Phone:</strong> +1 (555) 987-6543</p>
        <p><strong>Address:</strong> 456 Jewel St, Elegance City, EC 67890</p>
      </div>
      <form class="form">
        <h3>Send a Message</h3>
        <input type="text" name="name" placeholder="Your Name" required>
        <input type="email" name="email" placeholder="Your Email" required>
        <textarea name="message" placeholder="Your Message" rows="5" required></textarea>
        <button type="submit" class="button">Send Message</button>
      </form>
    </div>
  </section>

  <!-- FAQ Section -->
  <section id="faq" class="faq">
    <h2 class="fade-in">Frequently Asked Questions</h2>
    <p class="fade-in">Here are answers to common questions about shopping with LAB Jewels. If you need further assistance, feel free to reach out on our Contact Page.</p>
    <div class="grid">
      <div class="faq-item" onclick="this.classList.toggle('active')">
        <h3>What materials are used in LAB Jewels' products? <span class="arrow"> ❯ </span></h3>
        <div class="content">Our jewelry is crafted using high-quality materials such as 925 sterling silver, 14k gold, and ethically sourced gemstones to ensure durability and elegance.</div>
      </div>
      <div class="faq-item" onclick="this.classList.toggle('active')">
        <h3>What is your shipping policy? <span class="arrow"> ❯ </span></h3>
        <div class="content">We offer worldwide shipping with standard delivery in 5-7 business days and express options available. Free shipping applies to orders over $100.</div>
      </div>
      <div class="faq-item" onclick="this.classList.toggle('active')">
        <h3>How can I return or exchange an item? <span class="arrow"> ❯ </span></h3>
        <div class="content">You can return or exchange items within 30 days of delivery. Items must be unworn and in original packaging. Visit our Returns page for instructions.</div>
      </div>
      <div class="faq-item" onclick="this.classList.toggle('active')">
        <h3>Do you offer custom jewelry designs? <span class="arrow"> ❯ </span></h3>
        <div class="content">Yes, we provide custom design services. Contact our team via the Contact Page to discuss your vision and create a unique piece.</div>
      </div>
      <div class="faq-item" onclick="this.classList.toggle('active')">
        <h3>How do I care for my jewelry? <span class="arrow"> ❯ </span></h3>
        <div class="content">To maintain your jewelry’s shine, store it in a dry place, avoid exposure to chemicals, and clean it gently with a soft cloth. Check our Care Guide for more tips.</div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-container">
      <div class="brand-section">
        <div class="logo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
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
          <a href="#home">Home</a>
          <a href="#shop">Shop</a>
          <a href="#about">About</a>
          <a href="#team">Team</a>
          <a href="#contact">Contact</a>
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

    // Smooth Scroll for internal links (excluding category and pagination)
    document.querySelectorAll('a[href^="#"]:not(.category-link):not(.page-btn)').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
          behavior: 'smooth'
        });
        const menu = document.querySelector('nav ul');
        const toggle = document.querySelector('.menu-toggle');
        menu.classList.remove('active');
        toggle.classList.remove('active');
      });
    });

    // AJAX for category and pagination links
    function loadProducts(category, page) {
      fetch(`fetch-products.php?category=${encodeURIComponent(category)}&page=${page}`)
        .then(response => response.text())
        .then(data => {
          // Create a temporary container to parse the response
          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = data;

          // Update product grid
          const productGrid = document.getElementById('product-grid');
          productGrid.innerHTML = tempDiv.querySelector('#product-grid').innerHTML;

          // Update pagination
          const pagination = document.getElementById('pagination');
          pagination.innerHTML = tempDiv.querySelector('#pagination').innerHTML;

          // Update shop title
          const shopTitle = document.getElementById('shop-title');
          shopTitle.textContent = `${category} Collection`;

          // Smooth scroll to shop section
          document.getElementById('shop').scrollIntoView({
            behavior: 'smooth'
          });

          // Re-attach event listeners to new pagination links
          attachPaginationListeners();
        })
        .catch(error => {
          console.error('Error loading products:', error);
          document.getElementById('product-grid').innerHTML = '<p>Error loading products.</p>';
        });
    }

    // Attach event listeners to category links
    function attachCategoryListeners() {
      document.querySelectorAll('.category-link').forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          const category = this.getAttribute('data-category');
          loadProducts(category, 1);
          // Update URL without reloading
          history.pushState(null, '', `?category=${encodeURIComponent(category)}&page=1`);
        });
      });
    }

    // Attach event listeners to pagination links
    function attachPaginationListeners() {
      document.querySelectorAll('.page-btn').forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          const category = this.getAttribute('data-category');
          const page = this.getAttribute('data-page');
          loadProducts(category, page);
          // Update URL without reloading
          history.pushState(null, '', `?category=${encodeURIComponent(category)}&page=${page}`);
        });
      });
    }

    // Initialize event listeners
    attachCategoryListeners();
    attachPaginationListeners();
  </script>
</body>

</html>





















