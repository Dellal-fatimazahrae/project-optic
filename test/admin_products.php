<?php session_start();

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
        case 'add_product':
            if (tableExists($pdo, 'produits')) {
                try {
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
        case 'add_product':
            if (tableExists($pdo, 'produits')) {
                try {}
            };
        };