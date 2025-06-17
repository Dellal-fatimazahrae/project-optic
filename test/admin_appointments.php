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
        case 'update_appointment_status':
            if (tableExists($pdo, 'rendezvous')) {
                try {
                    $rdv_id = $_POST['rdv_id'];
                    $status = $_POST['status'];
                    $stmt = $pdo->prepare("UPDATE rendezvous SET statut = ?, updated_at = NOW() WHERE rdv_id = ?");
                    if ($stmt->execute([$status, $rdv_id])) {
                        $message = "Appointment status updated successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Error updating appointment status: " . $e->getMessage();
                }
            }
            break;
            
        case 'delete_appointment':
            if (tableExists($pdo, 'rendezvous')) {
                try {
                    $rdv_id = $_POST['rdv_id'];
                    $stmt = $pdo->prepare("DELETE FROM rendezvous WHERE rdv_id = ?");
                    if ($stmt->execute([$rdv_id])) {
                        $message = "Appointment deleted successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Error deleting appointment: " . $e->getMessage();
                }
            }
            break;
            
        case 'add_appointment':
            if (tableExists($pdo, 'rendezvous')) {
                try {
                    $nom = trim($_POST['nom']);
                    $prenom = trim($_POST['prenom']);
                    $email = trim($_POST['email']);
                    $telephone = trim($_POST['telephone']);
                    $date_rdv = $_POST['date_rdv'];
                    $heure_rdv = $_POST['heure_rdv'];
                    $type_rdv = $_POST['type_rdv'];
                    $notes = trim($_POST['notes']);
                    
                    $stmt = $pdo->prepare("INSERT INTO rendezvous (nom, prenom, email, telephone, date_rdv, heure_rdv, type_rdv, notes, statut, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())");
                    if ($stmt->execute([$nom, $prenom, $email, $telephone, $date_rdv, $heure_rdv, $type_rdv, $notes])) {
                        $message = "Appointment added successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Error adding appointment: " . $e->getMessage();
                }
            }
            break;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? '';

// Get appointments data
$appointments = [];
$appointments_count = 0;
$pending_count = 0;
$confirmed_count = 0;
$completed_count = 0;
$cancelled_count = 0;

if (tableExists($pdo, 'rendezvous')) {
    try {
        // Build query with filters
        $where_conditions = [];
        $params = [];
        
        if ($status_filter !== 'all') {
            $where_conditions[] = "statut = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($date_filter)) {
            $where_conditions[] = "date_rdv = ?";
            $params[] = $date_filter;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "SELECT * FROM rendezvous {$where_clause} ORDER BY date_rdv DESC, heure_rdv DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $appointments_count = count($appointments);
        
        // Get status counts
        $status_counts = $pdo->query("SELECT statut, COUNT(*) as count FROM rendezvous GROUP BY statut")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($status_counts as $status) {
            switch ($status['statut']) {
                case 'pending':
                    $pending_count = $status['count'];
                    break;
                case 'confirmed':
                    $confirmed_count = $status['count'];
                    break;
                case 'completed':
                    $completed_count = $status['count'];
                    break;
                case 'cancelled':
                    $cancelled_count = $status['count'];
                    break;
            }
        }
    } catch (Exception $e) {
        $appointments = [];
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
    <title>Appointment Management - OpticLook Admin</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        /* Filters */
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #4a5568;
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

        .form-control, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
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

        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fed7aa;
            color: #9c4221;
        }

        .status-confirmed {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-completed {
            background: #bee3f8;
            color: #2c5282;
        }

        .status-cancelled {
            background: #fed7d7;
            color: #9b2c2c;
        }

        /* Service Type badges */
        .service-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            background: #edf2f7;
            color: #4a5568;
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

            .filters {
                flex-direction: column;
                align-items: flex-start;
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
                        <a href="admin_appointments.php" class="active">
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
                <h1 class="page-title">Appointment Management</h1>
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

            <?php if (!tableExists($pdo, 'rendezvous')): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Appointments table not found!</strong> Please create the 'rendezvous' table in your database to manage appointments.
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Appointments</div>
                        <div class="stat-icon" style="background: #ebf8ff; color: #2b6cb0;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $appointments_count; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Pending</div>
                        <div class="stat-icon" style="background: #fffbeb; color: #d69e2e;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12,6 12,12 16,14"></polyline>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $pending_count; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Confirmed</div>
                        <div class="stat-icon" style="background: #f0fff4; color: #22543d;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9,11 12,14 22,4"></polyline>
                                <path d="M21,12v7a2,2 0 0,1 -2,2H5a2,2 0 0,1 -2,-2V5a2,2 0 0,1 2,-2h11"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $confirmed_count; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Completed</div>
                        <div class="stat-icon" style="background: #e6fffa; color: #234e52;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22,4 12,14.01 9,11.01"></polyline>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $completed_count; ?></div>
                </div>
            </div>

            <!-- Add Appointment Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Add New Appointment</h2>
                    <button class="btn btn-primary" onclick="toggleAddAppointmentForm()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Appointment
                    </button>
                </div>
                <div class="section-content" id="addAppointmentForm" style="display: none;">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_appointment">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nom">Last Name *</label>
                                <input type="text" id="nom" name="nom" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="prenom">First Name *</label>
                                <input type="text" id="prenom" name="prenom" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="telephone">Phone *</label>
                                <input type="tel" id="telephone" name="telephone" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="date_rdv">Date *</label>
                                <input type="date" id="date_rdv" name="date_rdv" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="heure_rdv">Time *</label>
                                <select id="heure_rdv" name="heure_rdv" class="form-select" required>
                                    <option value="">Select Time</option>
                                    <option value="09:00">09:00 AM</option>
                                    <option value="09:30">09:30 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="10:30">10:30 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="11:30">11:30 AM</option>
                                    <option value="14:00">02:00 PM</option>
                                    <option value="14:30">02:30 PM</option>
                                    <option value="15:00">03:00 PM</option>
                                    <option value="15:30">03:30 PM</option>
                                    <option value="16:00">04:00 PM</option>
                                    <option value="16:30">04:30 PM</option>
                                    <option value="17:00">05:00 PM</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="type_rdv">Service Type *</label>
                                <select id="type_rdv" name="type_rdv" class="form-select" required>
                                    <option value="">Select Service</option>
                                    <option value="eye_exam">Eye Examination</option>
                                    <option value="consultation">Eyewear Consultation</option>
                                    <option value="fitting">Frame Fitting</option>
                                    <option value="repair">Repair Service</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">Add Appointment</button>
                        <button type="button" class="btn btn-danger" onclick="toggleAddAppointmentForm()">Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Filters and Appointments List -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">All Appointments (<?php echo $appointments_count; ?>)</h2>
                    <div class="filters">
                        <div class="filter-group">
                            <label for="statusFilter">Filter by Status:</label>
                            <select id="statusFilter" class="form-select" onchange="filterAppointments()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="dateFilter">Filter by Date:</label>
                            <input type="date" id="dateFilter" class="form-control" value="<?php echo $date_filter; ?>" onchange="filterAppointments()">
                        </div>
                        <button class="btn btn-primary" onclick="clearFilters()">Clear Filters</button>
                    </div>
                </div>
                <div class="section-content">
                    <?php if (empty($appointments)): ?>
                        <p style="text-align: center; color: #718096; padding: 2rem;">No appointments found.</p>
                    <?php else: ?>
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
                                <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td>#<?php echo $appointment['rdv_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appointment['prenom'] . ' ' . $appointment['nom']); ?></strong>
                                        <?php if (!empty($appointment['notes'])): ?>
                                            <br><small style="color: #718096;">Note: <?php echo htmlspecialchars(substr($appointment['notes'], 0, 30)) . (strlen($appointment['notes']) > 30 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($appointment['email']); ?></div>
                                        <small style="color: #718096;"><?php echo htmlspecialchars($appointment['telephone']); ?></small>
                                    </td>
                                    <td>
                                        <span class="service-badge">
                                            <?php echo ucfirst(str_replace('_', ' ', $appointment['type_rdv'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><strong><?php echo date('M j, Y', strtotime($appointment['date_rdv'])); ?></strong></div>
                                        <small style="color: #718096;"><?php echo date('g:i A', strtotime($appointment['heure_rdv'])); ?></small>
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
                                            <select name="status" onchange="this.form.submit()" class="form-select" style="width: auto; padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                                                <option value="pending" <?php echo $appointment['statut'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $appointment['statut'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="completed" <?php echo $appointment['statut'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $appointment['statut'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </form>
                                        <br><br>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this appointment?')">
                                            <input type="hidden" name="action" value="delete_appointment">
                                            <input type="hidden" name="rdv_id" value="<?php echo $appointment['rdv_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleAddAppointmentForm() {
            const form = document.getElementById('addAppointmentForm');
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function filterAppointments() {
            const status = document.getElementById('statusFilter').value;
            const date = document.getElementById('dateFilter').value;
            
            let url = 'admin_appointments.php?';
            if (status !== 'all') {
                url += 'status=' + status + '&';
            }
            if (date) {
                url += 'date=' + date;
            }
            
            window.location.href = url;
        }

        function clearFilters() {
            window.location.href = 'admin_appointments.php';
        }

        // Auto-update status with confirmation for important changes
        document.addEventListener('change', function(e) {
            if (e.target.name === 'status') {
                const newStatus = e.target.value;
                const currentStatus = e.target.querySelector('option[selected]')?.value;
                
                if ((currentStatus === 'confirmed' && newStatus === 'cancelled') || 
                    (currentStatus === 'pending' && newStatus === 'cancelled')) {
                    if (!confirm('Are you sure you want to cancel this appointment?')) {
                        e.target.value = currentStatus;
                        return false;
                    }
                }
            }
        });
    </script>
</body>
</html>