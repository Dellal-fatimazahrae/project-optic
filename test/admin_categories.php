<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

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

// Function to check if table exists
function tableExists($pdo, $tableName) {
    try {
        $result = $pdo->query("SELECT 1 FROM {$tableName} LIMIT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_category':
            if (tableExists($pdo, 'categories')) {
                try {
                    $nom_categorie = trim($_POST['nom_categorie']);
                    $description = trim($_POST['description']);
                    
                    // Check if category already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE nom_categorie = ?");
                    $stmt->execute([$nom_categorie]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $error = "Category already exists!";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO categories (nom_categorie, description, active, created_at) VALUES (?, ?, 1, NOW())");
                        if ($stmt->execute([$nom_categorie, $description])) {
                            $message = "Category added successfully!";
                        }
                    }
                } catch (Exception $e) {
                    $error = "Error adding category: " . $e->getMessage();
                }
            }
            break;
            
        case 'update_category':
            if (tableExists($pdo, 'categories')) {
                try {
                    $categorie_id = $_POST['categorie_id'];
                    $nom_categorie = trim($_POST['nom_categorie']);
                    $description = trim($_POST['description']);
                    
                    $stmt = $pdo->prepare("UPDATE categories SET nom_categorie = ?, description = ?, updated_at = NOW() WHERE categorie_id = ?");
                    if ($stmt->execute([$nom_categorie, $description, $categorie_id])) {
                        $message = "Category updated successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Error updating category: " . $e->getMessage();
                }
            }
            break;
            
        case 'toggle_category_status':
            if (tableExists($pdo, 'categories')) {
                try {
                    $categorie_id = $_POST['categorie_id'];
                    $stmt = $pdo->prepare("UPDATE categories SET active = NOT active, updated_at = NOW() WHERE categorie_id = ?");
                    if ($stmt->execute([$categorie_id])) {
                        $message = "Category status updated successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Error updating category status: " . $e->getMessage();
                }
            }
            break;
            
        case 'delete_category':
            if (tableExists($pdo, 'categories')) {
                try {
                    $categorie_id = $_POST['categorie_id'];
                    
                    // Check if category has products
                    if (tableExists($pdo, 'produits')) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE categorie_id = ?");
                        $stmt->execute([$categorie_id]);
                        $product_count = $stmt->fetchColumn();
                        
                        if ($product_count > 0) {
                            $error = "Cannot delete category with existing products! Please reassign or delete products first.";
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM categories WHERE categorie_id = ?");
                            if ($stmt->execute([$categorie_id])) {
                                $message = "Category deleted successfully!";
                            }
                        }
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM categories WHERE categorie_id = ?");
                        if ($stmt->execute([$categorie_id])) {
                            $message = "Category deleted successfully!";
                        }
                    }
                } catch (Exception $e) {
                    $error = "Error deleting category: " . $e->getMessage();
                }
            }
            break;
    }
}

// Get categories data
$categories = [];
$categories_count = 0;
$active_categories = 0;

if (tableExists($pdo, 'categories')) {
    try {
        if (tableExists($pdo, 'produits')) {
            $categories = $pdo->query("
                SELECT c.*, 
                       COUNT(p.produit_id) as product_count,
                       COALESCE(SUM(CASE WHEN p.active = 1 THEN 1 ELSE 0 END), 0) as active_products
                FROM categories c 
                LEFT JOIN produits p ON c.categorie_id = p.categorie_id 
                GROUP BY c.categorie_id 
                ORDER BY c.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $categories = $pdo->query("SELECT *, 0 as product_count, 0 as active_products FROM categories ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $categories_count = count($categories);
        $active_categories = count(array_filter($categories, function($cat) { return $cat['active'] == 1; }));
    } catch (Exception $e) {
        $categories = [];
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - OpticLook Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #2d3748;
        }

        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: #2d3748;
            color: white;
            padding: 1.5rem 0;
            position: fixed;
            width: 250px;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid #4a5568;
            margin-bottom: 1rem;
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .logo-icon {
            margin-right: 0.5rem;
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 0.25rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #e2e8f0;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: #4a5568;
            color: white;
        }

        .sidebar-nav svg {
            margin-right: 0.75rem;
            width: 18px;
            height: 18px;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            background: #2d3748;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-title {
            font-size: 0.875rem;
            color: #718096;
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
        }

        /* Content Section */
        .content-section {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
        }

        .section-content {
            padding: 1.5rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #2d3748;
            color: white;
        }

        .btn-primary:hover {
            background: #1a202c;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #22543d;
            color: white;
        }

        .btn-warning {
            background: #d69e2e;
            color: white;
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #2d3748;
            box-shadow: 0 0 0 3px rgba(45, 55, 72, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.875rem;
        }

        .data-table th {
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .data-table tr:hover {
            background: #f7fafc;
        }

        /* Category Cards */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .category-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .category-card.inactive {
            opacity: 0.6;
            background: #f7fafc;
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .category-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .category-description {
            color: #718096;
            font-size: 0.875rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .category-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .category-stat {
            text-align: center;
        }

        .category-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2d3748;
        }

        .category-stat-label {
            font-size: 0.75rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .category-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-inactive {
            background: #fed7d7;
            color: #9b2c2c;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .alert-danger {
            background: #fed7d7;
            color: #9b2c2c;
            border: 1px solid #feb2b2;
        }

        .alert-warning {
            background: #fffbeb;
            color: #d69e2e;
            border: 1px solid #fed7aa;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 1rem;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
        }

        .close {
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            color: #718096;
        }

        .close:hover {
            color: #2d3748;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .categories-grid {
                grid-template-columns: 1fr;
            }

            .data-table {
                font-size: 0.75rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <svg class="logo-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12c0 6 4 10 10 10s10-4 10-10S18 6 12 6 2 6 2 12z"/>
                        <path d="M8 12h8"/>
                        <path d="M12 8v8"/>
                    </svg>
                    OpticLook Admin
                </div>
            </div>
            <nav>
                <ul class="sidebar-nav">
                    <li>
                        <a href="admin_dashboard.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="admin_users.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Users
                        </a>
                    </li>
                    <li>
                        <a href="admin_products.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12C2 6.5 6.5 2 12 2s10 4.5 10 10-4.5 10-10 10S2 17.5 2 12z"/>
                                <path d="M2 12h20"/>
                            </svg>
                            Products
                        </a>
                    </li>
                    <li>
                        <a href="admin_appointments.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Appointments
                        </a>
                    </li>
                    <li>
                        <a href="admin_categories.php" class="active">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <path d="M9 3v18"></path>
                                <path d="M15 3v18"></path>
                            </svg>
                            Categories
                        </a>
                    </li>
                    <li>
                        <a href="admin_reports.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline>
                            </svg>
                            Reports
                        </a>
                    </li>
                </ul>
            </nav>
            <div style="position: absolute; bottom: 1rem; left: 1.5rem; right: 1.5rem;">
                <a href="index.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9,22 9,12 15,12 15,22"></polyline>
                    </svg>
                    Back to Website
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1 class="page-title">Category Management</h1>
                <div class="admin-info">
                    <span>Welcome, <?php echo $_SESSION['admin_email'] ?? 'Administrator'; ?></span>
                    <div class="admin-avatar">A</div>
                    <a href="?logout=1" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!tableExists($pdo, 'categories')): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Categories table not found!</strong> Please create the 'categories' table in your database to manage categories.
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Categories</div>
                        <div class="stat-icon" style="background: #ebf8ff; color: #2b6cb0;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <path d="M9 3v18"></path>
                                <path d="M15 3v18"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $categories_count; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Active Categories</div>
                        <div class="stat-icon" style="background: #f0fff4; color: #22543d;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9,11 12,14 22,4"></polyline>
                                <path d="M21,12v7a2,2 0 0,1 -2,2H5a2,2 0 0,1 -2,-2V5a2,2 0 0,1 2,-2h11"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $active_categories; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Products</div>
                        <div class="stat-icon" style="background: #fffbeb; color: #d69e2e;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 12C2 6.5 6.5 2 12 2s10 4.5 10 10-4.5 10-10 10S2 17.5 2 12z"/>
                                <path d="M2 12h20"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo array_sum(array_column($categories, 'product_count')); ?></div>
                </div>
            </div>

            <!-- Add Category Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Add New Category</h2>
                    <button class="btn btn-primary" onclick="toggleAddCategoryForm()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Category
                    </button>
                </div>
                <div class="section-content" id="addCategoryForm" style="display: none;">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_category">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nom_categorie">Category Name *</label>
                                <input type="text" id="nom_categorie" name="nom_categorie" class="form-control" 
                                       placeholder="e.g., Sunglasses, Reading Glasses" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" 
                                      placeholder="Category description..." rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">Add Category</button>
                        <button type="button" class="btn btn-danger" onclick="toggleAddCategoryForm()">Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Categories Grid -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">All Categories (<?php echo $categories_count; ?>)</h2>
                </div>
                <div class="section-content">
                    <?php if (empty($categories)): ?>
                        <p style="text-align: center; color: #718096; padding: 2rem;">No categories found. Add your first category to get started!</p>
                    <?php else: ?>
                        <div class="categories-grid">
                            <?php foreach ($categories as $category): ?>
                            <div class="category-card <?php echo !$category['active'] ? 'inactive' : ''; ?>">
                                <div class="category-header">
                                    <div>
                                        <div class="category-name"><?php echo htmlspecialchars($category['nom_categorie']); ?></div>
                                        <span class="status-badge <?php echo $category['active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $category['active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($category['description'])): ?>
                                <div class="category-description">
                                    <?php echo htmlspecialchars($category['description']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="category-stats">
                                    <div class="category-stat">
                                        <div class="category-stat-value"><?php echo $category['product_count']; ?></div>
                                        <div class="category-stat-label">Total Products</div>
                                    </div>
                                    <div class="category-stat">
                                        <div class="category-stat-value"><?php echo $category['active_products']; ?></div>
                                        <div class="category-stat-label">Active Products</div>
                                    </div>
                                </div>
                                
                                <div class="category-actions">
                                    <button class="btn btn-sm btn-primary" onclick="editCategory(<?php echo $category['categorie_id']; ?>, '<?php echo addslashes($category['nom_categorie']); ?>', '<?php echo addslashes($category['description']); ?>')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        Edit
                                    </button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_category_status">
                                        <input type="hidden" name="categorie_id" value="<?php echo $category['categorie_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <?php echo $category['active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.')">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="categorie_id" value="<?php echo $category['categorie_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3,6 5,6 21,6"></polyline>
                                                <path d="m19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2"></path>
                                            </svg>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Category Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Category</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="" id="editCategoryForm">
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="categorie_id" id="editCategoryId">
                <div class="form-group">
                    <label for="editNomCategorie">Category Name *</label>
                    <input type="text" id="editNomCategorie" name="nom_categorie" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="editDescription">Description</label>
                    <textarea id="editDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-success">Update Category</button>
                <button type="button" class="btn btn-danger" onclick="closeEditModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function toggleAddCategoryForm() {
            const form = document.getElementById('addCategoryForm');
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function editCategory(id, name, description) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editNomCategorie').value = name;
            document.getElementById('editDescription').value = description;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>