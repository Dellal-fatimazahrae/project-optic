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

// Get date range for reports
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Initialize analytics data
$analytics = [
    'users' => ['total' => 0, 'new_this_month' => 0, 'growth_rate' => 0],
    'products' => ['total' => 0, 'active' => 0, 'low_stock' => 0, 'out_of_stock' => 0],
    'appointments' => ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0],
    'categories' => ['total' => 0, 'active' => 0],
    'revenue' => ['total_inventory_value' => 0, 'avg_product_price' => 0]
];

// Get user analytics
if (tableExists($pdo, 'users')) {
    try {
        // Total users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $analytics['users']['total'] = $stmt->fetchColumn();
        
        // New users this month
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at <= ?");
        $stmt->execute([$start_date, $end_date . ' 23:59:59']);
        $analytics['users']['new_this_month'] = $stmt->fetchColumn();
        
        // Growth rate calculation
        $last_month_start = date('Y-m-01', strtotime('-1 month'));
        $last_month_end = date('Y-m-t', strtotime('-1 month'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at <= ?");
        $stmt->execute([$last_month_start, $last_month_end . ' 23:59:59']);
        $last_month_users = $stmt->fetchColumn();
        
        if ($last_month_users > 0) {
            $analytics['users']['growth_rate'] = round((($analytics['users']['new_this_month'] - $last_month_users) / $last_month_users) * 100, 1);
        }
        
        // User registration trends (last 12 months)
        $user_trends = $pdo->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM users 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ")->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $user_trends = [];
    }
}

// Get product analytics
if (tableExists($pdo, 'produits')) {
    try {
        // Total products
        $stmt = $pdo->query("SELECT COUNT(*) FROM produits");
        $analytics['products']['total'] = $stmt->fetchColumn();
        
        // Active products
        $stmt = $pdo->query("SELECT COUNT(*) FROM produits WHERE active = 1");
        $analytics['products']['active'] = $stmt->fetchColumn();
        
        // Low stock products (stock <= 5)
        $stmt = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite_stock <= 5 AND quantite_stock > 0");
        $analytics['products']['low_stock'] = $stmt->fetchColumn();
        
        // Out of stock products
        $stmt = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite_stock = 0");
        $analytics['products']['out_of_stock'] = $stmt->fetchColumn();
        
        // Revenue analytics
        $stmt = $pdo->query("SELECT SUM(prix * quantite_stock) FROM produits WHERE active = 1");
        $analytics['revenue']['total_inventory_value'] = $stmt->fetchColumn() ?? 0;
        
        $stmt = $pdo->query("SELECT AVG(prix) FROM produits WHERE active = 1");
        $analytics['revenue']['avg_product_price'] = $stmt->fetchColumn() ?? 0;
        
        // Top selling categories (by product count)
        if (tableExists($pdo, 'categories')) {
            $top_categories = $pdo->query("
                SELECT c.nom_categorie, COUNT(p.produit_id) as product_count
                FROM categories c
                LEFT JOIN produits p ON c.categorie_id = p.categorie_id AND p.active = 1
                GROUP BY c.categorie_id, c.nom_categorie
                ORDER BY product_count DESC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $top_categories = [];
        }
        
    } catch (Exception $e) {
        $top_categories = [];
    }
}

// Get appointment analytics
if (tableExists($pdo, 'rendezvous')) {
    try {
        // Total appointments
        $stmt = $pdo->query("SELECT COUNT(*) FROM rendezvous");
        $analytics['appointments']['total'] = $stmt->fetchColumn();
        
        // Appointments by status
        $stmt = $pdo->query("SELECT statut, COUNT(*) as count FROM rendezvous GROUP BY statut");
        $appointment_statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($appointment_statuses as $status) {
            $analytics['appointments'][$status['statut']] = $status['count'];
        }
        
        // Appointment trends (last 6 months)
        $appointment_trends = $pdo->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM rendezvous 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Popular appointment types
        $appointment_types = $pdo->query("
            SELECT type_rdv, COUNT(*) as count
            FROM rendezvous
            GROUP BY type_rdv
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $appointment_trends = [];
        $appointment_types = [];
    }
}

// Get category analytics
if (tableExists($pdo, 'categories')) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
        $analytics['categories']['total'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM categories WHERE active = 1");
        $analytics['categories']['active'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Handle error
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
    <title>Reports & Analytics - OpticLook Admin</title>
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

        /* Date Filter */
        .date-filter {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .filter-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #4a5568;
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
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .stat-change {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive {
            color: #22543d;
        }

        .stat-change.negative {
            color: #9b2c2c;
        }

        .stat-change.neutral {
            color: #718096;
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

        .btn-danger {
            background: #e53e3e;
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        /* Forms */
        .form-control {
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

        /* Charts */
        .chart-container {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f7fafc;
            border-radius: 0.5rem;
            color: #718096;
            margin-bottom: 1rem;
        }

        .chart-placeholder {
            text-align: center;
        }

        /* Data Lists */
        .data-list {
            list-style: none;
        }

        .data-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-list li:last-child {
            border-bottom: none;
        }

        .data-item-name {
            font-weight: 500;
            color: #2d3748;
        }

        .data-item-value {
            font-weight: 600;
            color: #4a5568;
        }

        /* Progress Bars */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2d3748, #4a5568);
            transition: width 0.3s ease;
        }

        /* Grid Layouts */
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-warning {
            background: #fffbeb;
            color: #d69e2e;
            border: 1px solid #fed7aa;
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

            .reports-grid {
                grid-template-columns: 1fr;
            }

            .filter-controls {
                flex-direction: column;
                align-items: flex-start;
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
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Appointments
                        </a>
                    </li>
                    <li>
                        <a href="admin_categories.php">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <path d="M9 3v18"></path>
                                <path d="M15 3v18"></path>
                            </svg>
                            Categories
                        </a>
                    </li>
                    <li>
                        <a href="admin_reports.php" class="active">
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
                <h1 class="page-title">Reports & Analytics</h1>
                <div class="admin-info">
                    <span>Welcome, <?php echo $_SESSION['admin_email'] ?? 'Administrator'; ?></span>
                    <div class="admin-avatar">A</div>
                    <a href="?logout=1" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
                </div>
            </header>

            <!-- Date Filter -->
            <div class="date-filter">
                <form method="GET" action="">
                    <div class="filter-controls">
                        <div class="filter-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                        </div>
                        <div class="filter-group">
                            <label for="end_date">End Date:</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                        </div>
                        <div class="filter-group" style="margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="M21 21l-4.35-4.35"></path>
                                </svg>
                                Update Report
                            </button>
                        </div>
                        <div class="filter-group" style="margin-top: 1.5rem;">
                            <button type="button" class="btn btn-success" onclick="exportReport()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7,10 12,15 17,10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                Export Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Users</div>
                        <div class="stat-icon" style="background: #ebf8ff; color: #2b6cb0;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($analytics['users']['total']); ?></div>
                    <div class="stat-change <?php echo $analytics['users']['growth_rate'] > 0 ? 'positive' : ($analytics['users']['growth_rate'] < 0 ? 'negative' : 'neutral'); ?>">
                        <?php if ($analytics['users']['growth_rate'] > 0): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23,6 13.5,15.5 8.5,10.5 1,18"></polyline>
                                <polyline points="17,6 23,6 23,12"></polyline>
                            </svg>
                            +<?php echo $analytics['users']['growth_rate']; ?>%
                        <?php elseif ($analytics['users']['growth_rate'] < 0): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23,18 13.5,8.5 8.5,13.5 1,6"></polyline>
                                <polyline points="17,18 23,18 23,12"></polyline>
                            </svg>
                            <?php echo $analytics['users']['growth_rate']; ?>%
                        <?php else: ?>
                            No change
                        <?php endif; ?>
                        this month
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Active Products</div>
                        <div class="stat-icon" style="background: #f0fff4; color: #22543d;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 12C2 6.5 6.5 2 12 2s10 4.5 10 10-4.5 10-10 10S2 17.5 2 12z"/>
                                <path d="M2 12h20"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($analytics['products']['active']); ?></div>
                    <div class="stat-change neutral">
                        <?php echo $analytics['products']['low_stock']; ?> low stock, <?php echo $analytics['products']['out_of_stock']; ?> out of stock
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Appointments</div>
                        <div class="stat-icon" style="background: #fffbeb; color: #d69e2e;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($analytics['appointments']['total']); ?></div>
                    <div class="stat-change neutral">
                        <?php echo $analytics['appointments']['pending']; ?> pending
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Inventory Value</div>
                        <div class="stat-icon" style="background: #fdf2f8; color: #b83280;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value">$<?php echo number_format($analytics['revenue']['total_inventory_value'], 0); ?></div>
                    <div class="stat-change neutral">
                        Avg: $<?php echo number_format($analytics['revenue']['avg_product_price'], 2); ?> per product
                    </div>
                </div>
            </div>

            <!-- Reports Grid -->
            <div class="reports-grid">
                <!-- User Analytics -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">User Analytics</h2>
                    </div>
                    <div class="section-content">
                        <div class="chart-container">
                            <div class="chart-placeholder">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline>
                                </svg>
                                <p>User Registration Trends</p>
                                <small>Chart integration recommended (Chart.js)</small>
                            </div>
                        </div>
                        <ul class="data-list">
                            <li>
                                <span class="data-item-name">New Users This Month</span>
                                <span class="data-item-value"><?php echo $analytics['users']['new_this_month']; ?></span>
                            </li>
                            <li>
                                <span class="data-item-name">Growth Rate</span>
                                <span class="data-item-value <?php echo $analytics['users']['growth_rate'] > 0 ? 'positive' : ($analytics['users']['growth_rate'] < 0 ? 'negative' : 'neutral'); ?>">
                                    <?php echo $analytics['users']['growth_rate']; ?>%
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Appointment Analytics -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Appointment Analytics</h2>
                    </div>
                    <div class="section-content">
                        <div class="chart-container">
                            <div class="chart-placeholder">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <p>Appointment Status Distribution</p>
                                <small>Pie chart recommended</small>
                            </div>
                        </div>
                        <ul class="data-list">
                            <li>
                                <span class="data-item-name">Pending</span>
                                <span class="data-item-value"><?php echo $analytics['appointments']['pending']; ?></span>
                            </li>
                            <li>
                                <span class="data-item-name">Confirmed</span>
                                <span class="data-item-value"><?php echo $analytics['appointments']['confirmed']; ?></span>
                            </li>
                            <li>
                                <span class="data-item-name">Completed</span>
                                <span class="data-item-value"><?php echo $analytics['appointments']['completed']; ?></span>
                            </li>
                            <li>
                                <span class="data-item-name">Cancelled</span>
                                <span class="data-item-value"><?php echo $analytics['appointments']['cancelled']; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Product Categories -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Top Categories</h2>
                    </div>
                    <div class="section-content">
                        <?php if (!empty($top_categories)): ?>
                            <ul class="data-list">
                                <?php foreach ($top_categories as $category): ?>
                                <li>
                                    <span class="data-item-name"><?php echo htmlspecialchars($category['nom_categorie']); ?></span>
                                    <span class="data-item-value"><?php echo $category['product_count']; ?> products</span>
                                </li>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $analytics['products']['total'] > 0 ? ($category['product_count'] / $analytics['products']['total'] * 100) : 0; ?>%"></div>
                                </div>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="text-align: center; color: #718096; padding: 2rem;">No category data available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Popular Services -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Popular Services</h2>
                    </div>
                    <div class="section-content">
                        <?php if (!empty($appointment_types)): ?>
                            <ul class="data-list">
                                <?php foreach ($appointment_types as $type): ?>
                                <li>
                                    <span class="data-item-name"><?php echo ucfirst(str_replace('_', ' ', $type['type_rdv'])); ?></span>
                                    <span class="data-item-value"><?php echo $type['count']; ?> bookings</span>
                                </li>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $analytics['appointments']['total'] > 0 ? ($type['count'] / $analytics['appointments']['total'] * 100) : 0; ?>%"></div>
                                </div>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="text-align: center; color: #718096; padding: 2rem;">No appointment data available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Status -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">System Status</h2>
                    </div>
                    <div class="section-content">
                        <ul class="data-list">
                            <li>
                                <span class="data-item-name">Database Tables</span>
                                <span class="data-item-value">
                                    <?php 
                                    $tables = ['users', 'produits', 'categories', 'rendezvous'];
                                    $existing_tables = 0;
                                    foreach ($tables as $table) {
                                        if (tableExists($pdo, $table)) $existing_tables++;
                                    }
                                    echo $existing_tables . '/' . count($tables);
                                    ?>
                                </span>
                            </li>
                            <li>
                                <span class="data-item-name">Data Integrity</span>
                                <span class="data-item-value" style="color: #22543d;">✓ Good</span>
                            </li>
                            <li>
                                <span class="data-item-name">Last Report Generated</span>
                                <span class="data-item-value"><?php echo date('M j, Y H:i'); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Quick Actions</h2>
                    </div>
                    <div class="section-content">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <a href="admin_users.php" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                Manage Users
                            </a>
                            <a href="admin_products.php" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 12C2 6.5 6.5 2 12 2s10 4.5 10 10-4.5 10-10 10S2 17.5 2 12z"/>
                                    <path d="M2 12h20"/>
                                </svg>
                                Manage Products
                            </a>
                            <a href="admin_appointments.php" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                Manage Appointments
                            </a>
                            <button class="btn btn-success" onclick="generateDetailedReport()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14,2 14,8 20,8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10,9 9,9 8,9"></polyline>
                                </svg>
                                Generate Detailed Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Reports Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Monthly Performance Summary</h2>
                </div>
                <div class="section-content">
                    <div class="reports-grid">
                        <div>
                            <h4 style="margin-bottom: 1rem; color: #2d3748;">Business Highlights</h4>
                            <ul class="data-list">
                                <li>
                                    <span class="data-item-name">User Acquisition</span>
                                    <span class="data-item-value"><?php echo $analytics['users']['new_this_month']; ?> new users</span>
                                </li>
                                <li>
                                    <span class="data-item-name">Appointment Conversion</span>
                                    <span class="data-item-value">
                                        <?php 
                                        $conversion_rate = $analytics['appointments']['total'] > 0 ? 
                                            round(($analytics['appointments']['completed'] / $analytics['appointments']['total']) * 100, 1) : 0;
                                        echo $conversion_rate . '%';
                                        ?>
                                    </span>
                                </li>
                                <li>
                                    <span class="data-item-name">Product Availability</span>
                                    <span class="data-item-value">
                                        <?php 
                                        $availability_rate = $analytics['products']['total'] > 0 ? 
                                            round((($analytics['products']['total'] - $analytics['products']['out_of_stock']) / $analytics['products']['total']) * 100, 1) : 0;
                                        echo $availability_rate . '%';
                                        ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                        
                        <div>
                            <h4 style="margin-bottom: 1rem; color: #2d3748;">Areas for Improvement</h4>
                            <ul class="data-list">
                                <?php if ($analytics['products']['out_of_stock'] > 0): ?>
                                <li>
                                    <span class="data-item-name">Out of Stock Items</span>
                                    <span class="data-item-value" style="color: #e53e3e;"><?php echo $analytics['products']['out_of_stock']; ?> products</span>
                                </li>
                                <?php endif; ?>
                                
                                <?php if ($analytics['appointments']['pending'] > 5): ?>
                                <li>
                                    <span class="data-item-name">Pending Appointments</span>
                                    <span class="data-item-value" style="color: #d69e2e;"><?php echo $analytics['appointments']['pending']; ?> awaiting confirmation</span>
                                </li>
                                <?php endif; ?>
                                
                                <?php if ($analytics['products']['low_stock'] > 0): ?>
                                <li>
                                    <span class="data-item-name">Low Stock Alert</span>
                                    <span class="data-item-value" style="color: #d69e2e;"><?php echo $analytics['products']['low_stock']; ?> items need restocking</span>
                                </li>
                                <?php endif; ?>
                                
                                <?php if ($analytics['products']['out_of_stock'] == 0 && $analytics['appointments']['pending'] <= 5 && $analytics['products']['low_stock'] == 0): ?>
                                <li>
                                    <span class="data-item-name">System Status</span>
                                    <span class="data-item-value" style="color: #22543d;">✓ All systems running smoothly</span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function exportReport() {
            // Create a comprehensive report data object
            const reportData = {
                generated_at: new Date().toISOString(),
                date_range: {
                    start: document.getElementById('start_date').value,
                    end: document.getElementById('end_date').value
                },
                analytics: <?php echo json_encode($analytics); ?>,
                system_info: {
                    php_version: '<?php echo PHP_VERSION; ?>',
                    database: 'MySQL',
                    total_tables: <?php echo $existing_tables ?? 0; ?>
                }
            };
            
            // Convert to CSV format for download
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "OpticLook Analytics Report\n\n";
            csvContent += "Generated: " + new Date().toLocaleString() + "\n";
            csvContent += "Date Range: " + reportData.date_range.start + " to " + reportData.date_range.end + "\n\n";
            
            csvContent += "Metric,Value\n";
            csvContent += "Total Users," + reportData.analytics.users.total + "\n";
            csvContent += "New Users This Month," + reportData.analytics.users.new_this_month + "\n";
            csvContent += "User Growth Rate," + reportData.analytics.users.growth_rate + "%\n";
            csvContent += "Total Products," + reportData.analytics.products.total + "\n";
            csvContent += "Active Products," + reportData.analytics.products.active + "\n";
            csvContent += "Low Stock Products," + reportData.analytics.products.low_stock + "\n";
            csvContent += "Out of Stock Products," + reportData.analytics.products.out_of_stock + "\n";
            csvContent += "Total Appointments," + reportData.analytics.appointments.total + "\n";
            csvContent += "Pending Appointments," + reportData.analytics.appointments.pending + "\n";
            csvContent += "Confirmed Appointments," + reportData.analytics.appointments.confirmed + "\n";
            csvContent += "Completed Appointments," + reportData.analytics.appointments.completed + "\n";
            csvContent += "Cancelled Appointments," + reportData.analytics.appointments.cancelled + "\n";
            csvContent += "Total Inventory Value,$" + reportData.analytics.revenue.total_inventory_value + "\n";
            csvContent += "Average Product Price,$" + reportData.analytics.revenue.avg_product_price + "\n";
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "opticlook_analytics_report_" + new Date().toISOString().split('T')[0] + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Show success message
            alert('Analytics report exported successfully as CSV file!');
        }

        function generateDetailedReport() {
            const confirmation = confirm('Generate a comprehensive business intelligence report? This will include all available data and metrics.');
            if (confirmation) {
                // In a real implementation, this would trigger a server-side report generation
                alert('Detailed report generation initiated. This feature would typically:\n\n• Generate PDF reports with charts\n• Include trend analysis\n• Provide business recommendations\n• Export to Excel with multiple sheets\n• Email reports to stakeholders\n\nImplement with libraries like TCPDF, PhpSpreadsheet, or Chart.js for full functionality.');
            }
        }

        // Auto-refresh data every 5 minutes
        let refreshInterval = setInterval(function() {
            const shouldRefresh = confirm('Auto-refresh: Update dashboard data with latest information?');
            if (shouldRefresh) {
                location.reload();
            }
        }, 300000); // 5 minutes

        // Stop auto-refresh if user navigates away
        window.addEventListener('beforeunload', function() {
            clearInterval(refreshInterval);
        });

        // Add real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            const clockElement = document.querySelector('.admin-info span');
            if (clockElement) {
                clockElement.innerHTML = 'Welcome, <?php echo $_SESSION['admin_email'] ?? 'Administrator'; ?> | ' + timeString;
            }
        }
        
        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock(); // Initial call
    </script>
</body>
</html>