<?php
// Database configuration
$host = 'localhost';
$dbname = 'opti';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch categories
$categories_query = "SELECT * FROM categories WHERE active = 1";
$categories = $pdo->query($categories_query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch featured products (new arrivals)
$new_arrivals_query = "SELECT p.*, c.nom_categorie FROM produits p 
                       JOIN categories c ON p.categorie_id = c.categorie_id 
                       WHERE p.active = 1 
                       ORDER BY p.created_at DESC 
                       LIMIT 8";
$new_arrivals = $pdo->query($new_arrivals_query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch best sellers (products with highest stock movement)
$best_sellers_query = "SELECT p.*, c.nom_categorie FROM produits p 
                       JOIN categories c ON p.categorie_id = c.categorie_id 
                       WHERE p.active = 1 AND p.quantite_stock > 0
                       ORDER BY p.quantite_stock DESC 
                       LIMIT 8";
$best_sellers = $pdo->query($best_sellers_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpticLook - Lunettes de Vue et Solaire</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #2d3748;
            background-color: #ffffff;
        }

        /* Header Styles */
        .header {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1.5rem;
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
        }

        .logo-icon {
            margin-right: 0.5rem;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-menu a {
            text-decoration: none;
            color: #4a5568;
            font-weight: 500;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: color 0.3s ease;
        }

        .nav-menu a:hover {
            color: #2d3748;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .header-actions svg {
            width: 20px;
            height: 20px;
            stroke: #4a5568;
            cursor: pointer;
            transition: stroke 0.3s ease;
        }

        .header-actions svg:hover {
            stroke: #2d3748;
        }

        .auth-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-login, .btn-signup {
            padding: 0.5rem 1rem;
            border: 1px solid #2d3748;
            border-radius: 0.375rem;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-login {
            color: #2d3748;
            background: transparent;
        }

        .btn-login:hover {
            background: #2d3748;
            color: white;
        }

        .btn-signup {
            background: #2d3748;
            color: white;
        }

        .btn-signup:hover {
            background: #1a202c;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60%;
            height: 100%;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400"><defs><linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:%23e2e8f0;stop-opacity:0.3" /><stop offset="100%" style="stop-color:%23cbd5e0;stop-opacity:0.1" /></linearGradient></defs><ellipse cx="400" cy="200" rx="150" ry="100" fill="url(%23grad1)"/><ellipse cx="500" cy="120" rx="80" ry="50" fill="url(%23grad1)"/><ellipse cx="320" cy="280" rx="100" ry="60" fill="url(%23grad1)"/></svg>');
            background-size: cover;
            background-repeat: no-repeat;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1rem;
            line-height: 1.1;
        }

        .hero-text p {
            font-size: 1.125rem;
            color: #718096;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .cta-button {
            background: #2d3748;
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .cta-button:hover {
            background: #1a202c;
            transform: translateY(-1px);
        }

        .cta-button.secondary {
            background: transparent;
            color: #2d3748;
            border: 2px solid #2d3748;
        }

        .cta-button.secondary:hover {
            background: #2d3748;
            color: white;
        }

        .hero-image {
            position: relative;
            text-align: right;
        }

        .hero-model {
            width: 100%;
            max-width: 400px;
            height: auto;
            border-radius: 1rem;
        }

        /* Categories Section */
        .categories {
            padding: 5rem 0;
            background: #ffffff;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.25rem;
            color: #2d3748;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .section-title p {
            color: #718096;
            font-size: 1.125rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .category-item {
            text-align: center;
            padding: 2rem 1.5rem;
            background: #f7fafc;
            border-radius: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .category-item:hover {
            transform: translateY(-4px);
            border-color: #e2e8f0;
            background: #ffffff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .category-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            border-radius: 50%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .category-item h3 {
            font-size: 1.25rem;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .category-item p {
            color: #718096;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        /* Products Section */
        .products {
            padding: 5rem 0;
            background: #f7fafc;
        }

        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 3rem;
            background: #ffffff;
            border-radius: 0.5rem;
            padding: 0.25rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 3rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .tab {
            flex: 1;
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            font-size: 0.875rem;
            font-weight: 600;
            color: #718096;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 0.375rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .tab.active {
            color: #ffffff;
            background: #2d3748;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .product-card {
            background: #ffffff;
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .product-image-container {
            position: relative;
            width: 100%;
            height: 240px;
            background: #f7fafc;
            overflow: hidden;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-brand {
            color: #718096;
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 600;
        }

        .product-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }

        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1rem;
        }

        .product-colors {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .color-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .color-dot:hover {
            transform: scale(1.2);
            border-color: #2d3748;
        }

        .add-to-cart {
            width: 100%;
            padding: 0.75rem;
            background: #2d3748;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .add-to-cart:hover:not(:disabled) {
            background: #1a202c;
        }

        .add-to-cart:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
        }

        .stock-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stock-status.in-stock {
            background: #c6f6d5;
            color: #22543d;
        }

        .stock-status.low-stock {
            background: #fed7aa;
            color: #9c4221;
        }

        .stock-status.out-of-stock {
            background: #fed7d7;
            color: #9b2c2c;
        }

        /* Features Section */
        .features {
            padding: 5rem 0;
            background: #ffffff;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .feature-item {
            text-align: center;
            padding: 2rem 1rem;
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f7fafc;
            border-radius: 50%;
            color: #2d3748;
        }

        .feature-item h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1rem;
        }

        .feature-item p {
            color: #718096;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            background: #2d3748;
            color: #e2e8f0;
            padding: 3rem 0 1.5rem;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1rem;
            color: #ffffff;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .footer-section p,
        .footer-section a {
            color: #a0aec0;
            text-decoration: none;
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .footer-section a:hover {
            color: #e2e8f0;
        }

        .footer-bottom {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #4a5568;
            color: #a0aec0;
            font-size: 0.875rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .hero-text h1 {
                font-size: 2.5rem;
            }
            
            .nav-menu {
                display: none;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }

            .category-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .auth-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <div class="logo">
                <svg class="logo-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12c0 6 4 10 10 10s10-4 10-10S18 6 12 6 2 6 2 12z"/>
                    <path d="M8 12h8"/>
                    <path d="M12 8v8"/>
                </svg>
                OpticLook
            </div>
            <nav>
                <ul class="nav-menu">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#eyeglasses">Eyeglasses</a></li>
                    <li><a href="#sunglasses">Sunglasses</a></li>
                    <li><a href="#brands">Brands</a></li>
                    <li><a href="#lenses">Lenses</a></li>
                    <li><a href="#about">About us</a></li>
                </ul>
            </nav>
            <div class="header-actions">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
                <div class="auth-buttons">
                    <a href="login.php" class="btn-login">Login</a>
                    <a href="signup.php" class="btn-signup">Sign Up</a>
                </div>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Cozy Comfort</h1>
                <p>Where style meets weightless wear. Découvrez notre collection de lunettes alliant confort exceptionnel et design moderne.</p>
                <div class="cta-buttons">
                    <a href="#products" class="cta-button">Shop Now</a>
                    <a href="rendezvous.php" class="cta-button secondary">Book Appointment</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="https://images.unsplash.com/photo-1508296695146-257a814070b4?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Model wearing glasses" class="hero-model">
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories">
        <div class="container">
            <div class="section-title">
                <h2>Shop By Category</h2>
                <p>Browse our extensive selection and find your perfect fit</p>
            </div>
            <div class="category-grid">
                <?php foreach($categories as $index => $category): ?>
                <div class="category-item" onclick="filterProducts('<?php echo $category['categorie_id']; ?>')">
                    <div class="category-icon">
                        <?php if($index == 0): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#4a5568" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12C2 6.5 6.5 2 12 2s10 4.5 10 10-4.5 10-10 10S2 17.5 2 12z"/>
                                <path d="M2 12h20"/>
                            </svg>
                        <?php elseif($index == 1): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#4a5568" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M12 1v6m0 6v6"/>
                                <path d="M1 12h6m6 0h6"/>
                            </svg>
                        <?php else: ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#4a5568" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="6" width="20" height="12" rx="2"/>
                                <circle cx="12" cy="12" r="2"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($category['nom_categorie']); ?></h3>
                    <p><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section class="products" id="products">
        <div class="container">
            <div class="tabs">
                <button class="tab active" onclick="showTab('new-arrivals')">New Arrival</button>
                <button class="tab" onclick="showTab('best-sellers')">Best Sellers</button>
            </div>

            <!-- New Arrivals Tab -->
            <div id="new-arrivals" class="tab-content active">
                <p style="text-align: center; color: #718096; margin-bottom: 2rem; font-size: 1.125rem;">
                    Lens by looks and Vision with New Arrivals
                </p>
                <div class="products-grid">
                    <?php foreach($new_arrivals as $product): ?>
                    <div class="product-card">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['url_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['nom_produit']); ?>" 
                                 class="product-image"
                                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22280%22 height=%22240%22><rect width=%22100%25%22 height=%22100%25%22 fill=%22%23f7fafc%22/><text x=%2250%25%22 y=%2250%25%22 font-family=%22Arial%22 font-size=%2214%22 fill=%22%23718096%22 text-anchor=%22middle%22 dy=%22.3em%22>Image non disponible</text></svg>'">
                        </div>
                        <div class="product-info">
                            <div class="product-brand"><?php echo htmlspecialchars($product['marque'] ?? 'OpticLook'); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($product['nom_produit']); ?></div>
                            <div class="product-price">$<?php echo number_format($product['prix'], 2); ?></div>
                            
                            <?php 
                            $stock_class = 'in-stock';
                            $stock_text = 'En stock';
                            if ($product['quantite_stock'] == 0) {
                                $stock_class = 'out-of-stock';
                                $stock_text = 'Rupture';
                            } elseif ($product['quantite_stock'] <= 5) {
                                $stock_class = 'low-stock';
                                $stock_text = 'Stock faible';
                            }
                            ?>
                            <div class="stock-status <?php echo $stock_class; ?>">
                                <?php echo $stock_text; ?>
                            </div>
                            
                            <div class="product-colors">
                                <div class="color-dot" style="background: #8B4513;"></div>
                                <div class="color-dot" style="background: #000000;"></div>
                                <div class="color-dot" style="background: #4682B4;"></div>
                                <div class="color-dot" style="background: #DC143C;"></div>
                            </div>
                            <button class="add-to-cart" 
                                    <?php echo ($product['quantite_stock'] == 0) ? 'disabled' : ''; ?>
                                    onclick="addToCart(<?php echo $product['produit_id']; ?>)">
                                <?php echo ($product['quantite_stock'] == 0) ? 'Indisponible' : 'Add to Cart'; ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose OpticLook?</h2>
                <p>We provide exceptional service and quality products</p>
            </div>
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 12l2 2 4-4"></path>
                            <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
                            <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
                            <path d="M3 12c0 5.523 4.477 10 10 10s10-4.477 10-10"></path>
                        </svg>
                    </div>
                    <h3>Expert Eye Tests</h3>
                    <p>Professional eye examinations by certified optometrists using the latest technology.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12,6 12,12 16,14"></polyline>
                        </svg>
                    </div>
                    <h3>Same Day Service</h3>
                    <p>Get your glasses ready the same day with our express service for most prescriptions.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                            <path d="M2 17l10 5 10-5"></path>
                            <path d="M2 12l10 5 10-5"></path>
                        </svg>
                    </div>
                    <h3>Premium Brands</h3>
                    <p>Exclusive collection from world's leading eyewear brands and designers.</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="7.5,4.21 12,6.81 16.5,4.21"></polyline>
                            <polyline points="7.5,19.79 7.5,14.6 3,12"></polyline>
                            <polyline points="21,12 16.5,14.6 16.5,19.79"></polyline>
                        </svg>
                    </div>
                    <h3>Warranty & Support</h3>
                    <p>Comprehensive warranty on all products with lifetime support and adjustments.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>OpticLook</h3>
                <p>Your specialist in prescription glasses and sunglasses. We offer a wide range of quality products for all styles and budgets.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <a href="#home">Home</a>
                <a href="#eyeglasses">Eyeglasses</a>
                <a href="#sunglasses">Sunglasses</a>
                <a href="#brands">Brands</a>
                <a href="#about">About</a>
            </div>
            <div class="footer-section">
                <h3>Customer Service</h3>
                <a href="#contact">Contact</a>
                <a href="#help">Help</a>
                <a href="#returns">Returns</a>
                <a href="#shipping">Shipping</a>
                <a href="#warranty">Warranty</a>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p>
                    <svg style="width: 16px; height: 16px; display: inline-block; margin-right: 8px; vertical-align: middle;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    info@opticlook.com
                </p>
                <p>
                    <svg style="width: 16px; height: 16px; display: inline-block; margin-right: 8px; vertical-align: middle;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    +212 5XX-XXXXXX
                </p>
                <p>
                    <svg style="width: 16px; height: 16px; display: inline-block; margin-right: 8px; vertical-align: middle;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    Casablanca, Morocco
                </p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 OpticLook. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function addToCart(productId) {
            // Simulate adding to cart
            alert('Product added to cart! (ID: ' + productId + ')');
            // Here you would typically make an AJAX call to add the product to the cart
        }

        function filterProducts(categoryId) {
            // Simulate filtering products by category
            alert('Filtering by category: ' + categoryId);
            // Here you would typically reload the page with category filter or use AJAX
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Parallax effect for hero section
            window.addEventListener('scroll', function() {
                const scrolled = window.pageYOffset;
                const hero = document.querySelector('.hero');
                if (hero) {
                    hero.style.transform = `translateY(${scrolled * 0.5}px)`;
                }
            });

            // Animate product cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe all product cards
            document.querySelectorAll('.product-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>