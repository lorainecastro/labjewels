<?php
require '../connection/config.php';
session_start();

// Check if user is already logged in
$currentUser = validateSession();

if ($currentUser) {
  header("Location: ../pages/userShop.php");
  exit;
}

$profileImageUrl = $currentUser['icon'] ?? 'no-icon.png';

// Load XML file
$xmlFile = __DIR__ . 'public_html/xml/products.xml';

function loadXML($file)
{
  if (!file_exists($file)) {
    die('XML file does not exist.');
  }
  $xml = simplexml_load_file($file);
  if ($xml === false) {
    die('Error loading XML file.');
  }
  return $xml;
}

// Load XML data
$xml = loadXML($xmlFile);

// Get categories
$categories = [];
if (isset($xml->categories->category)) {
  foreach ($xml->categories->category as $category) {
    $categories[] = (string)$category->name;
  }
}

// Pagination settings (used for server-side fallback)
$itemsPerPage = 6;
$maxPages = 6;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$currentCategory = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : 'all';

// Filter products by category and collect color/size options
$products = [];
if (isset($xml->products->product)) {
  foreach ($xml->products->product as $product) {
    $colors = [];
    if (isset($product->colors->color)) {
      foreach ($product->colors->color as $color) {
        $colors[] = (string)$color;
      }
    }
    $sizes = [];
    if (isset($product->sizes->size)) {
      foreach ($product->sizes->size as $size) {
        $sizes[] = (string)$size;
      }
    }
    $products[] = [
      'id' => (int)$product->id,
      'name' => (string)$product->name,
      'category' => (string)$product->category,
      'price' => (float)$product->price,
      'currency' => (string)$product->currency,
      'image' => (string)$product->image,
      'description' => (string)$product->description,
      'colors' => $colors,
      'sizes' => $sizes
    ];
  }
}

$filteredProducts = $currentCategory === 'all' ? $products : array_filter($products, function ($product) use ($currentCategory) {
  return $product['category'] === $currentCategory;
});

// Calculate pagination (server-side fallback)
$totalItems = count($filteredProducts);
$totalPages = min(ceil($totalItems / $itemsPerPage), $maxPages);
$currentPage = min($currentPage, $totalPages ?: 1);
$start = ($currentPage - 1) * $itemsPerPage;
$paginatedProducts = array_slice($filteredProducts, $start, $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAB Jewels - Minimalist Jewelry</title>
  <link rel="icon" type="image/png" href="../assets/image/system/logo.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Your existing CSS remains unchanged */
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
      margin: 0 auto;
      grid-template-columns: repeat(4, 1fr);
      justify-content: center;
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
      margin: 0 auto;
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

    .container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 40px 20px;
    }

    .navigation {
      text-align: center;
      margin-bottom: 30px;
    }

    .nav-link {
      color: var(--accent-color);
      text-decoration: none;
      font-weight: 600;
      font-size: 16px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition);
    }

    .nav-link:hover {
      color: var(--accent-hover);
      transform: translateX(5px);
    }

    .category-section {
      text-align: center;
      margin-bottom: 50px;
    }

    .category-section h2 {
      font-size: 42px;
      font-weight: 800;
      color: var(--primary-color);
      margin-bottom: 30px;
      letter-spacing: -0.5px;
    }

    .category-menu {
      display: flex;
      justify-content: center;
      margin-bottom: 30px;
      flex-wrap: wrap;
      gap: 15px;
    }

    .category-btn {
      padding: 10px 20px;
      background-color: var(--card-bg);
      border: 1px solid var(--primary-color);
      border-radius: 25px;
      font-size: 14px;
      font-weight: 600;
      color: var(--primary-color);
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
    }

    .category-btn:hover,
    .category-btn.active {
      background-color: var(--primary-color);
      color: var(--white);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .shop h2 {
      font-size: 42px;
      font-weight: 800;
      color: var(--primary-color);
      text-align: center;
      margin-bottom: 50px;
      letter-spacing: -0.5px;
    }

    .shop .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 40px;
      margin-bottom: 40px;
    }

    .product-card {
      background-color: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: 12px;
      padding: 25px;
      text-align: center;
      transition: var(--transition);
      box-shadow: var(--shadow-sm);
    }

    .product-card:hover {
      box-shadow: var(--shadow-lg);
      transform: translateY(-5px);
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
      color: var(--primary-color);
      margin-bottom: 12px;
    }

    .product-card .description {
      font-size: 14px;
      color: var(--gray);
      margin-bottom: 20px;
      line-height: 1.5;
    }

    .product-card .price {
      font-size: 20px;
      font-weight: 600;
      color: var(--accent-color);
      margin-bottom: 20px;
    }

    .add-to-cart-btn {
      width: 100%;
      background-color: var(--black);
      color: var(--white);
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .add-to-cart-btn:hover {
      background-color: var(--black);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
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



    /* Updated media queries */
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
        /* 2 columns for medium screens */
        gap: 25px;
        max-width: 800px;
        /* Reduced max-width for medium screens */
        margin: 0 auto;
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
        /* 1 column for smaller screens */
        gap: 20px;
        max-width: 500px;
        /* Centered for smaller screens */
        margin: 0 auto;
      }

      .team-card {
        max-width: 280px;
        /* Slightly smaller cards for mobile */
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

      .team .grid {
        grid-template-columns: 1fr;
        gap: 15px;
        max-width: 350px;
        /* Adjusted for smaller screens */
      }

      .team-card {
        max-width: 260px;
        /* Smaller cards for very small screens */
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

      .team .grid {
        grid-template-columns: 1fr;
        gap: 15px;
        max-width: 300px;
        /* Even smaller for tiny screens */
      }

      .team-card {
        max-width: 240px;
        /* Adjusted for smallest screens */
      }
    }

    .empty-state {
      text-align: center;
      padding: 4rem 0;
      color: var(--grayfont-color);
    }

    .empty-state i {
      font-size: 4rem;
      margin-bottom: 1.5rem;
      color: var(--border-color);
    }

    .empty-state h3 {
      font-size: 1.5rem;
      margin-bottom: 1rem;
      color: var(--blackfont-color);
    }

    .empty-state p {
      font-size: 1.1rem;
      margin-bottom: 2rem;
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
  <div class="container">
    <!-- Messages -->
    <?php if (isset($_GET['message'])): ?>
      <div class="message success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_GET['message']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="message error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($_GET['error']); ?>
      </div>
    <?php endif; ?>

    <!-- Shop by Category Section -->
    <div id="categories" class="category-section">
      <h2 class="fade-in">Shop by Category</h2>
      <div class="category-menu" id="categoryMenu">
        <a href="#categories" class="category-btn <?php echo $currentCategory === 'all' ? 'active' : ''; ?>" data-category="all">All Products</a>
        <?php foreach ($categories as $category): ?>
          <a href="#categories" class="category-btn <?php echo $currentCategory === $category ? 'active' : ''; ?>" data-category="<?php echo htmlspecialchars($category); ?>">
            <?php echo htmlspecialchars($category); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Product Showcase -->
    <div id="shop" class="shop">
      <h2 id="categoryTitle" class="fade-in"><?php echo $currentCategory === 'all' ? 'All Products' : htmlspecialchars($currentCategory); ?></h2>
      <div class="grid" id="productGrid">
        <?php if (empty($paginatedProducts)): ?>
          <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>No Products Found</h3>
            <p>No products found in this category. Please try a different category.</p>
            <a href="#categories" class="button" data-category="all">View All Products</a>
          </div>
        <?php else: ?>
          <?php foreach ($paginatedProducts as $product): ?>
            <div class="product-card hover-scale">
              <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
              <h3><?php echo htmlspecialchars($product['name']); ?></h3>
              <div class="description"><?php echo htmlspecialchars($product['description']); ?></div>
              <div class="price"><?php echo htmlspecialchars($product['currency']); ?> <?php echo number_format($product['price'], 2); ?></div>
              <button class="add-to-cart-btn"
                data-product-id="<?php echo $product['id']; ?>"
                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                data-product-image="<?php echo htmlspecialchars($product['image']); ?>"
                data-product-price="<?php echo $product['price']; ?>"
                data-product-colors='<?php echo json_encode($product['colors']); ?>'
                data-product-sizes='<?php echo json_encode($product['sizes']); ?>'>
                <i class="fas fa-cart-plus"></i>
                Add to Cart
              </button>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Pagination -->
      <div class="pagination" id="pagination">
        <!-- Client-side pagination will be populated here -->
      </div>
    </div>
  </div>

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
        <img src="../assets/image/system/loraine.png" alt="Loraine Castro" onerror="this.src='../assets/image/system/no-icon.png'">
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

    // Smooth Scroll for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        if (!this.classList.contains('category-btn') && !this.classList.contains('page-btn') && !this.classList.contains('button')) {
          e.preventDefault();
          document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
          });
          const menu = document.querySelector('nav ul');
          const toggle = document.querySelector('.menu-toggle');
          menu.classList.remove('active');
          toggle.classList.remove('active');
        }
      });
    });

    // Add loading animation, category/pagination handling, and search functionality
    document.addEventListener('DOMContentLoaded', function() {
      const productCards = document.querySelectorAll('.product-card');
      productCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
          card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
          card.style.opacity = '1';
          card.style.transform = 'translateY(0)';
        }, index * 100);
      });

      // Add to Cart button functionality
      const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
      addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          alert("You're currently logged out. Log in to continue shopping.");
          window.location.href = 'login.php';
        });
      });

      // Client-side pagination, category filtering, and search
      const allProducts = <?php echo json_encode($products); ?>;
      const itemsPerPage = 6;
      const maxPages = 6;
      let currentCategory = '<?php echo $currentCategory; ?>';
      let currentPage = <?php echo $currentPage; ?>;
      let searchQuery = '';

      function renderProducts(products, page) {
        const productGrid = document.getElementById('productGrid');
        const pagination = document.getElementById('pagination');
        productGrid.innerHTML = '';

        // Calculate pagination
        const totalItems = products.length;
        const totalPages = Math.min(Math.ceil(totalItems / itemsPerPage), maxPages);
        currentPage = Math.min(currentPage, totalPages || 1);
        const start = (currentPage - 1) * itemsPerPage;
        const paginatedProducts = products.slice(start, start + itemsPerPage);

        // Render products
        if (paginatedProducts.length === 0) {
          productGrid.innerHTML = `
        <div class="empty-state" aria-live="polite">
          <i class="fas fa-box-open"></i>
          <h3>No Products Found</h3>
          <p>No products found${searchQuery ? ' for this search.' : ' in this category.'} Please try a different ${searchQuery ? 'search term' : 'category'}.</p>
          <a href="#categories" class="button" data-category="all">View All Products</a>
        </div>
      `;

          // Attach event listener to "View All Products" button
          const viewAllButton = document.querySelector('.empty-state .button[data-category="all"]');
          if (viewAllButton) {
            viewAllButton.addEventListener('click', function(e) {
              e.preventDefault();
              currentCategory = 'all';
              currentPage = 1;
              searchQuery = '';
              document.querySelectorAll('.search-container input').forEach(input => input.value = '');
              const categoryTitle = document.getElementById('categoryTitle');
              categoryTitle.textContent = 'All Products';

              // Update active category button
              const categoryButtons = document.querySelectorAll('.category-btn');
              categoryButtons.forEach(btn => btn.classList.remove('active'));
              const allCategoryButton = document.querySelector('.category-btn[data-category="all"]');
              if (allCategoryButton) allCategoryButton.classList.add('active');

              // Render all products
              renderProducts(allProducts, currentPage);
              document.querySelector('#categories').scrollIntoView({
                behavior: 'smooth'
              });
            });
          }
        } else {
          paginatedProducts.forEach(product => {
            productGrid.innerHTML += `
          <div class="product-card hover-scale">
            <img src="${product.image}" alt="${product.name}">
            <h3>${product.name}</h3>
            <div class="description">${product.description}</div>
            <div class="price">${product.currency} ${parseFloat(product.price).toFixed(2)}</div>
            <button class="add-to-cart-btn"
              data-product-id="${product.id}"
              data-product-name="${product.name}"
              data-product-image="${product.image}"
              data-product-price="${product.price}"
              data-product-colors='${JSON.stringify(product.colors)}'
              data-product-sizes='${JSON.stringify(product.sizes)}'>
              <i class="fas fa-cart-plus"></i>
              Add to Cart
            </button>
          </div>
        `;
          });
        }

        // Re-attach add-to-cart event listeners
        const newAddToCartButtons = document.querySelectorAll('.add-to-cart-btn');
        newAddToCartButtons.forEach(button => {
          button.addEventListener('click', function(e) {
            e.preventDefault();
            alert("You're currently logged out. Log in to continue shopping.");
            window.location.href = 'login.php';
          });
        });

        // Render pagination
        pagination.innerHTML = '';
        if (totalPages > 1) {
          // Previous Button
          pagination.innerHTML += `
        <a href="#categories" class="page-btn ${currentPage === 1 ? 'disabled' : ''}" data-page="${Math.max(1, currentPage - 1)}">
          <i class="fas fa-chevron-left"></i>
        </a>
      `;

          // Page Numbers
          for (let i = 1; i <= totalPages; i++) {
            pagination.innerHTML += `
          <a href="#categories" class="page-btn ${currentPage === i ? 'active' : ''}" data-page="${i}">${i}</a>
        `;
          }

          // Next Button
          pagination.innerHTML += `
        <a href="#categories" class="page-btn ${currentPage === totalPages ? 'disabled' : ''}" data-page="${Math.min(totalPages, currentPage + 1)}">
          <i class="fas fa-chevron-right"></i>
        </a>
      `;
        }

        // Attach pagination event listeners
        const pageButtons = document.querySelectorAll('.page-btn');
        pageButtons.forEach(button => {
          button.addEventListener('click', function(e) {
            e.preventDefault();
            const newPage = parseInt(this.getAttribute('data-page'));
            if (newPage !== currentPage) {
              currentPage = newPage;
              const filteredProducts = getFilteredProducts();
              renderProducts(filteredProducts, currentPage);
              document.querySelector('#categories').scrollIntoView({
                behavior: 'smooth'
              });
            }
          });
        });

        // Apply loading animation to new product cards
        const newProductCards = document.querySelectorAll('.product-card');
        newProductCards.forEach((card, index) => {
          card.style.opacity = '0';
          card.style.transform = 'translateY(20px)';
          setTimeout(() => {
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
          }, index * 100);
        });
      }

      // Helper function to get filtered products based on category and search query
      function getFilteredProducts() {
        let filteredProducts = currentCategory === 'all' ?
          allProducts :
          allProducts.filter(product => product.category === currentCategory);

        if (searchQuery) {
          filteredProducts = filteredProducts.filter(product =>
            product.name.toLowerCase().includes(searchQuery.toLowerCase())
          );
        }

        return filteredProducts;
      }

      // Handle category button clicks
      const categoryButtons = document.querySelectorAll('.category-btn');
      categoryButtons.forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();

          // Update active class
          categoryButtons.forEach(btn => btn.classList.remove('active'));
          this.classList.add('active');

          // Get selected category
          currentCategory = this.getAttribute('data-category');
          currentPage = 1; // Reset to first page on category change
          searchQuery = ''; // Reset search query on category change
          document.querySelectorAll('.search-container input').forEach(input => input.value = '');
          const categoryTitle = document.getElementById('categoryTitle');
          categoryTitle.textContent = currentCategory === 'all' ? 'All Products' : currentCategory;

          // Filter and render products
          const filteredProducts = getFilteredProducts();
          renderProducts(filteredProducts, currentPage);

          // Scroll to #categories
          document.querySelector('#categories').scrollIntoView({
            behavior: 'smooth'
          });
        });
      });

      // Search functionality
      const searchInputs = document.querySelectorAll('.search-container input');
      searchInputs.forEach(input => {
        // Scroll to categories section on focus
        input.addEventListener('focus', function() {
          document.querySelector('#categories').scrollIntoView({
            behavior: 'smooth'
          });
        });

        // Filter products on input
        input.addEventListener('input', function() {
          searchQuery = this.value.trim();
          currentPage = 1; // Reset to first page on search
          const filteredProducts = getFilteredProducts();
          renderProducts(filteredProducts, currentPage);

          // Sync search input value across both inputs (primary and secondary nav)
          searchInputs.forEach(otherInput => {
            if (otherInput !== this) {
              otherInput.value = searchQuery;
            }
          });
        });
      });

      // Initial render based on URL parameters
      const filteredProducts = getFilteredProducts();
      renderProducts(filteredProducts, currentPage);
    });
  </script>
</body>

</html>