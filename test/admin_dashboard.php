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

// Get dashboard statistics with error handling
function getDashboardStats($pdo) {
    $stats = [];
    
    // Total users
    try {
        if (tableExists($pdo, 'users')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $stats['total_users'] = $stmt->fetchColumn();
            
            // Recent signups (last 7 days)
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stats['recent_signups'] = $stmt->fetchColumn();
        } else {
            $stats['total_users'] = 0;
            $stats['recent_signups'] = 0;
        }
    } catch (Exception $e) {
        $stats['total_users'] = 0;
        $stats['recent_signups'] = 0;
    }
    
    // Total products
    try {
        if (tableExists($pdo, 'produits')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM produits WHERE active = 1");
            $stats['total_products'] = $stmt->fetchColumn();
            
            // Total inventory value
            $stmt = $pdo->query("SELECT COALESCE(SUM(prix * quantite_stock), 0) FROM produits WHERE active = 1");
            $stats['total_inventory_value'] = $stmt->fetchColumn();
        } else {
            $stats['total_products'] = 0;
            $stats['total_inventory_value'] = 0;
        }
    } catch (Exception $e) {
        $stats['total_products'] = 0;
        $stats['total_inventory_value'] = 0;
    }
    
    // Total appointments
    try {
        if (tableExists($pdo, 'rendezvous')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM rendezvous");
            $stats['total_appointments'] = $stmt->fetchColumn();
            
            // Pending appointments
            $stmt = $pdo->query("SELECT COUNT(*) FROM rendezvous WHERE statut = 'pending'");
            $stats['pending_appointments'] = $stmt->fetchColumn();
        } else {
            $stats['total_appointments'] = 0;
            $stats['pending_appointments'] = 0;
        }
    } catch (Exception $e) {
        $stats['total_appointments'] = 0;
        $stats['pending_appointments'] = 0;
    }
    
    return $stats;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_login.php');
    exit();
}

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_appointment_status':
            if (tableExists($pdo, 'rendezvous')) {
                try {
                    $rdv_id = $_POST['rdv_id'];
                    $status = $_POST['status'];
                    $stmt = $pdo->prepare("UPDATE rendezvous SET statut = ? WHERE rdv_id = ?");
                    if ($stmt->execute([$status, $rdv_id])) {
                        $message = "Appointment status updated successfully.";
                    }
                } catch (Exception $e) {
                    $error = "Error updating appointment: " . $e->getMessage();
                }
            }
            break;
            
        case 'toggle_product_status':
            if (tableExists($pdo, 'produits')) {
                try {
                    $product_id = $_POST['product_id'];
                    $stmt = $pdo->prepare("UPDATE produits SET active = NOT active WHERE produit_id = ?");
                    if ($stmt->execute([$product_id])) {
                        $message = "Product status updated successfully.";
                    }
                } catch (Exception $e) {
                    $error = "Error updating product: " . $e->getMessage();
                }
            }
            break;
            
        case 'delete_user':
            if (tableExists($pdo, 'users')) {
                try {
                    $user_id = $_POST['user_id'];
                    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                    if ($stmt->execute([$user_id])) {
                        $message = "User deleted successfully.";
                    }
                } catch (Exception $e) {
                    $error = "Error deleting user: " . $e->getMessage();
                }
            }
            break;
    }
}

$stats = getDashboardStats($pdo);

// Get recent data for tables with error handling
$recent_users = [];
$recent_appointments = [];
$products = [];

try {
    if (tableExists($pdo, 'users')) {
        $recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $recent_users = [];
}

try {
    if (tableExists($pdo, 'rendezvous')) {
        $recent_appointments = $pdo->query("SELECT * FROM rendezvous ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $recent_appointments = [];
}

try {
    if (tableExists($pdo, 'produits')) {
        if (tableExists($pdo, 'categories')) {
            $products = $pdo->query("SELECT p.*, c.nom_categorie FROM produits p LEFT JOIN categories c ON p.categorie_id = c.categorie_id ORDER BY p.created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $products = $pdo->query("SELECT *, NULL as nom_categorie FROM produits ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    $products = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - OpticLook</title>
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

        .dashboard-container {
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

        .dashboard-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .dashboard-title {
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
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-change {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .stat-change.positive {
            color: #22543d;
        }

        .stat-change.negative {
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

        /* Content Sections */
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

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
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

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
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
        }

        .btn-success {
            background: #22543d;
            color: white;
        }

        .btn-success:hover {
            background: #1a202c;
        }

        .btn-warning {
            background: #d69e2e;
            color: white;
        }

        .btn-warning:hover {
            background: #b7791f;
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
        }

        .btn-danger:hover {
            background: #c53030;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-pending {
            background: #fed7aa;
            color: #9c4221;
        }

        .status-confirmed {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-cancelled {
            background: #fed7d7;
            color: #9b2c2c;
        }

        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-inactive {
            background: #fed7d7;
            color: #9b2c2c;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .tab.active {
            background: #2d3748;
            color: white;
            border-color: #2d3748;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .quick-action:hover {
            border-color: #2d3748;
            transform: translateY(-2px);
        }

        .quick-action-icon {
            width: 48px;
            height: 48px;
            background: #f7fafc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: #2d3748;
        }

        .quick-action h3 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }

        .quick-action p {
            font-size: 0.875rem;
            color: #718096;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
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
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        .chart-container {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f7fafc;
            border-radius: 0.5rem;
            color: #718096;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
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
                        <a href="#dashboard" class="nav-link active" data-tab="dashboard">
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
                        <a href="#users" class="nav-link" data-tab="users">
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
                        <a href="#products" class="nav-link" data-tab="products">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12C2 6.5 6.5 2 12 2s10 4.5 10 10-4.5 10-10 10S2 17.5 2 12z"/>
                                <path d="M2 12h20"/>
                            </svg>
                            Products
                        </a>
                    </li>
                    <li>
                        <a href="#appointments" class="nav-link" data-tab="appointments">
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
                        <a href="#analytics" class="nav-link" data-tab="analytics">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline>
                            </svg>
                            Analytics
                        </a>
                    </li>
                    <li>
                        <a href="#settings" class="nav-link" data-tab="settings">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M12 1v6m0 6v6"></path>
                                <path d="M21 12h-6m-6 0H3"></path>
                            </svg>
                            Settings
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
            <header class="dashboard-header">
                <h1 class="dashboard-title">Dashboard</h1>
                <div class="admin-info">
                    <span>Welcome, <?php echo $_SESSION['admin_email'] ?? 'Administrator'; ?></span>
                    <div class="admin-avatar">A</div>
                    <a href="?logout=1" class="btn btn-danger btn-sm" style="margin-left: 1rem;" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
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

            <?php
            // Check for missing tables and show warning
            $required_tables = ['users', 'produits', 'categories', 'rendezvous'];
            $missing_tables = [];
            foreach ($required_tables as $table) {
                if (!tableExists($pdo, $table)) {
                    $missing_tables[] = $table;
                }
            }
            
            if (!empty($missing_tables)): ?>
                <div class="alert" style="background: #fffbeb; color: #d69e2e; border: 1px solid #fed7aa;">
                    <strong>⚠️ Missing Tables:</strong> The following tables are missing from your database: 
                    <strong><?php echo implode(', ', $missing_tables); ?></strong>
                    <br><small>Some features may not work correctly. Please create these tables or contact your developer.</small>
                </div>
            <?php endif; ?>

            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content active">
                <!-- Stats Cards -->
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
                        <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-change positive">+<?php echo $stats['recent_signups']; ?> this week</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">Total Products</div>
                            <div class="stat-icon" style="background: #f0fff4; color: #22543d;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 12C2 6.5 6.5 2 12 2s10 4.5 10 10-4.5 10-10 10S2 17.5 2 12z"/>
                                    <path d="M2 12h20"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
                        <div class="stat-change positive">Active products</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-title">Appointments</div>
                            <div class="stat-icon" style="background: #fffbeb; color: #d69e2e;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total_appointments']); ?></div>
                        <div class="stat-change"><?php echo $stats['pending_appointments']; ?> pending</div>
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
                        <div class="stat-value">$<?php echo number_format($stats['total_inventory_value'], 2); ?></div>
                        <div class="stat-change positive">Total value</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <div class="quick-action" onclick="showTab('products')">
                        <div class="quick-action-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="16"></line>
                                <line x1="8" y1="12" x2="16" y2="12"></line>
                            </svg>
                        </div>
                        <h3>Add Product</h3>
                        <p>Add new eyewear to inventory</p>
                    </div>
                    
                    <div class="quick-action" onclick="showTab('appointments')">
                        <div class="quick-action-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        <h3>Manage Appointments</h3>
                        <p>View and update appointments</p>
                    </div>
                    
                    <div class="quick-action" onclick="showTab('users')">
                        <div class="quick-action-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <h3>User Management</h3>
                        <p>Manage customer accounts</p>
                    </div>
                    
                    <div class="quick-action" onclick="showTab('analytics')">
                        <div class="quick-action-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline>
                            </svg>
                        </div>
                        <h3>View Analytics</h3>
                        <p>Business insights and reports</p>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Recent Activity</h2>
                    </div>
                    <div class="section-content">
                        <div class="tabs">
                            <div class="tab active" onclick="showSubTab('recent-users', this)">New Users</div>
                            <div class="tab" onclick="showSubTab('recent-appointments', this)">Recent Appointments</div>
                        </div>
                        
                        <div id="recent-users" class="tab-content active">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['telephone']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div id="recent-appointments" class="tab-content">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Service</th>
                                        <th>Date & Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($appointment['prenom'] . ' ' . $appointment['nom']); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $appointment['type_rdv'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($appointment['date_rdv'])) . ' at ' . $appointment['heure_rdv']; ?></td>
                                        <td><span class="status-badge status-<?php echo $appointment['statut']; ?>"><?php echo ucfirst($appointment['statut']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Tab -->
            <div id="users" class="tab-content">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">User Management</h2>
                        <button class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Add User
                        </button>
                    </div>
                    <div class="section-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $all_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($all_users as $user): 
                                ?>
                                <tr>
                                    <td>#<?php echo $user['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['telephone']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editUser(<?php echo $user['user_id']; ?>)">Edit</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Products Tab -->
            <div id="products" class="tab-content">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Product Management</h2>
                        <button class="btn btn-primary" onclick="showAddProductForm()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Add Product
                        </button>
                    </div>
                    <div class="section-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>#<?php echo $product['produit_id']; ?></td>
                                    <td><?php echo htmlspecialchars($product['nom_produit']); ?></td>
                                    <td><?php echo htmlspecialchars($product['nom_categorie'] ?? 'N/A'); ?></td>
                                    <td>$<?php echo number_format($product['prix'], 2); ?></td>
                                    <td><?php echo $product['quantite_stock']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $product['active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $product['active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo $product['produit_id']; ?>)">Edit</button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_product_status">
                                            <input type="hidden" name="product_id" value="<?php echo $product['produit_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                <?php echo $product['active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Appointments Tab -->
            <div id="appointments" class="tab-content">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Appointment Management</h2>
                        <div>
                            <button class="btn btn-primary">Export</button>
                        </div>
                    </div>
                    <div class="section-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Contact</th>
                                    <th>Service</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $all_appointments = $pdo->query("SELECT * FROM rendezvous ORDER BY date_rdv DESC, heure_rdv DESC")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($all_appointments as $appointment): 
                                ?>
                                <tr>
                                    <td>#<?php echo $appointment['rdv_id']; ?></td>
                                    <td><?php echo htmlspecialchars($appointment['prenom'] . ' ' . $appointment['nom']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($appointment['email']); ?><br>
                                        <small><?php echo htmlspecialchars($appointment['telephone']); ?></small>
                                    </td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $appointment['type_rdv'])); ?></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($appointment['date_rdv'])); ?><br>
                                        <small><?php echo $appointment['heure_rdv']; ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $appointment['statut']; ?>">
                                            <?php echo ucfirst($appointment['statut']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_appointment_status">
                                            <input type="hidden" name="rdv_id" value="<?php echo $appointment['rdv_id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="btn btn-sm">
                                                <option value="pending" <?php echo $appointment['statut'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $appointment['statut'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="cancelled" <?php echo $appointment['statut'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="completed" <?php echo $appointment['statut'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Analytics Tab -->
            <div id="analytics" class="tab-content">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Analytics & Reports</h2>
                        <div>
                            <button class="btn btn-primary">Generate Report</button>
                        </div>
                    </div>
                    <div class="section-content">
                        <div class="stats-grid" style="margin-bottom: 2rem;">
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-title">Monthly Revenue</div>
                                    <div class="stat-icon" style="background: #ebf8ff; color: #2b6cb0;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="1" x2="12" y2="23"></line>
                                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="stat-value">$12,450</div>
                                <div class="stat-change positive">+15% from last month</div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-title">Conversion Rate</div>
                                    <div class="stat-icon" style="background: #f0fff4; color: #22543d;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"></polyline>
                                        </svg>
                                    </div>
                                </div>
                                <div class="stat-value">3.2%</div>
                                <div class="stat-change positive">+0.5% this month</div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-title">Avg. Order Value</div>
                                    <div class="stat-icon" style="background: #fffbeb; color: #d69e2e;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                            <line x1="3" y1="6" x2="21" y2="6"></line>
                                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="stat-value">$185</div>
                                <div class="stat-change negative">-2% from last month</div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-header">
                                    <div class="stat-title">Customer Retention</div>
                                    <div class="stat-icon" style="background: #fdf2f8; color: #b83280;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="stat-value">78%</div>
                                <div class="stat-change positive">+5% improvement</div>
                            </div>
                        </div>

                        <div class="chart-container">
                            <p>📊 Revenue Chart (Integration with Chart.js recommended)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="settings" class="tab-content">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">System Settings</h2>
                    </div>
                    <div class="section-content">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                            <div>
                                <h3 style="margin-bottom: 1rem; color: #2d3748;">Store Information</h3>
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Store Name</label>
                                    <input type="text" value="OpticLook" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.375rem;">
                                </div>
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Contact Email</label>
                                    <input type="email" value="info@opticlook.com" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.375rem;">
                                </div>
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Phone Number</label>
                                    <input type="tel" value="+212 5XX-XXXXXX" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.375rem;">
                                </div>
                            </div>

                            <div>
                                <h3 style="margin-bottom: 1rem; color: #2d3748;">Business Hours</h3>
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Monday - Friday</label>
                                    <input type="text" value="9:00 AM - 6:00 PM" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.375rem;">
                                </div>
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Saturday</label>
                                    <input type="text" value="9:00 AM - 4:00 PM" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.375rem;">
                                </div>
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Sunday</label>
                                    <input type="text" value="Closed" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.375rem;">
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
                            <button class="btn btn-primary">Save Settings</button>
                            <button class="btn btn-danger" style="margin-left: 1rem;">Reset to Defaults</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Tab navigation
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all nav links
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => link.classList.remove('active'));
            
            // Show selected tab
            const selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.classList.add('active');
            }
            
            // Add active class to corresponding nav link
            const navLink = document.querySelector(`[data-tab="${tabName}"]`);
            if (navLink) {
                navLink.classList.add('active');
            }

            // Update dashboard title
            const titles = {
                'dashboard': 'Dashboard',
                'users': 'User Management',
                'products': 'Product Management',
                'appointments': 'Appointment Management',
                'analytics': 'Analytics & Reports',
                'settings': 'System Settings'
            };
            
            const titleElement = document.querySelector('.dashboard-title');
            if (titleElement && titles[tabName]) {
                titleElement.textContent = titles[tabName];
            }
        }

        // Sub-tab navigation for recent activity
        function showSubTab(tabName, element) {
            const tabContents = document.querySelectorAll('#dashboard .tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            const tabs = document.querySelectorAll('#dashboard .tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            element.classList.add('active');
        }

        // Navigation click handlers
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabName = this.getAttribute('data-tab');
                    showTab(tabName);
                });
            });
        });

        // Placeholder functions for actions
        function editUser(userId) {
            alert(`Edit user functionality for ID: ${userId} would be implemented here.`);
        }

        function editProduct(productId) {
            alert(`Edit product functionality for ID: ${productId} would be implemented here.`);
        }

        function showAddProductForm() {
            alert('Add product form would be shown here. This could be a modal or a separate page.');
        }

        // Auto-refresh data every 30 seconds (optional)
        // setInterval(function() {
        //     location.reload();
        // }, 30000);
    </script>
</body>
</html>