<?php
session_start();
include "conixion.php";

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: connexion.php");
    exit();
}

$error_message = "";
$success_message = "";

// Traitement des actions AJAX et formulaires
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_product':
            try {
                $nom_produit = trim($_POST['nom_produit']);
                $categorie_id = (int)$_POST['categorie_id'];
                $description_produit = trim($_POST['description_produit']);
                $prix = (float)$_POST['prix'];
                $quantite_stock = (int)$_POST['quantite_stock'];
                $genre = trim($_POST['genre']);
                $url_image = trim($_POST['url_image']);
                
                if (empty($nom_produit) || $prix <= 0 || $quantite_stock < 0) {
                    echo json_encode(['success' => false, 'message' => 'Données invalides']);
                    exit();
                }
                
                $stmt = $pdo->prepare("INSERT INTO produits (categorie_id, nom_produit, description_produit, prix, quantite_stock, genre, url_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categorie_id, $nom_produit, $description_produit, $prix, $quantite_stock, $genre, $url_image]);
                
                // Log admin action
                logAdminAction($pdo, $_SESSION['user_id'], 'add_product', "Produit ajouté: $nom_produit");
                
                echo json_encode(['success' => true, 'message' => 'Produit ajouté avec succès']);
                exit();
                
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout: ' . $e->getMessage()]);
                exit();
            }
        break;
            
        case 'edit_product':
            try {
                $product_id = (int)$_POST['edit_product_id'];
                $nom_produit = trim($_POST['nom_produit']);
                $categorie_id = (int)$_POST['categorie_id'];
                $description_produit = trim($_POST['description_produit']);
                $prix = (float)$_POST['prix'];
                $quantite_stock = (int)$_POST['quantite_stock'];
                $genre = trim($_POST['genre']);
                $url_image = trim($_POST['url_image']);
                
                $stmt = $pdo->prepare("UPDATE produits SET categorie_id=?, nom_produit=?, description_produit=?, prix=?, quantite_stock=?, genre=?, url_image=? WHERE produit_id=?");
                $stmt->execute([$categorie_id, $nom_produit, $description_produit, $prix, $quantite_stock, $genre, $url_image, $product_id]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'edit_product', "Produit modifié: $nom_produit (ID: $product_id)");
                
                echo json_encode(['success' => true, 'message' => 'Produit modifié avec succès']);
                exit();
                
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification']);
                exit();
            }
            break;
            
        case 'get_products':
            try {
                $stmt = $pdo->query("SELECT p.*, c.nom_categorie FROM produits p LEFT JOIN categories c ON p.categorie_id = c.categorie_id ORDER BY p.produit_id DESC");
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($products);
                exit();
            } catch (PDOException $e) {
                echo json_encode(['error' => $e->getMessage()]);
                exit();
            }
            break;
            
        case 'get_clients':
            try {
                $stmt = $pdo->query("SELECT * FROM clients ORDER BY client_id DESC");
                $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($clients);
                exit();
            } catch (PDOException $e) {
                echo json_encode(['error' => $e->getMessage()]);
                exit();
            }
            break;
            
        case 'get_appointments':
            try {
                $status_filter = isset($_POST['status']) && $_POST['status'] !== 'all' ? " WHERE p.STATUS_RENDEZ_VOUS = " . (int)$_POST['status'] : "";
                
                $stmt = $pdo->query("
                    SELECT p.*, c.nom_complet as client_nom, c.email as client_email, pr.nom_produit, pr.prix
                    FROM prendre p 
                    JOIN clients c ON p.client_id = c.client_id 
                    JOIN produits pr ON p.produit_id = pr.produit_id 
                    $status_filter
                    ORDER BY p.DATE_RENDEZ_VOUS DESC
                ");
                $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($appointments);
                exit();
            } catch (PDOException $e) {
                echo json_encode(['error' => $e->getMessage()]);
                exit();
            }
            break;
            
        case 'update_appointment_status':
            try {
                $client_id = (int)$_POST['client_id'];
                $produit_id = (int)$_POST['produit_id'];
                $status = (int)$_POST['status'];
                $date_rdv = $_POST['date_rdv'];
                
                $stmt = $pdo->prepare("UPDATE prendre SET STATUS_RENDEZ_VOUS = ? WHERE client_id = ? AND produit_id = ? AND DATE_RENDEZ_VOUS = ?");
                $stmt->execute([$status, $client_id, $produit_id, $date_rdv]);
                
                $status_text = ['En attente', 'Validé', 'Refusé'][$status];
                logAdminAction($pdo, $_SESSION['user_id'], 'update_appointment', "RDV $status_text - Client: $client_id");
                
                echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
                exit();
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
                exit();
            }
            break;
            
        case 'delete_product':
            try {
                $produit_id = (int)$_POST['produit_id'];
                
                // Vérifier s'il y a des rendez-vous liés
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM prendre WHERE produit_id = ?");
                $stmt->execute([$produit_id]);
                $count = $stmt->fetch()['count'];
                
                if ($count > 0) {
                    echo json_encode(['success' => false, 'message' => 'Impossible de supprimer: des rendez-vous existent pour ce produit']);
                    exit();
                }
                
                // Récupérer le nom pour le log
                $stmt = $pdo->prepare("SELECT nom_produit FROM produits WHERE produit_id = ?");
                $stmt->execute([$produit_id]);
                $product = $stmt->fetch();
                
                $stmt = $pdo->prepare("DELETE FROM produits WHERE produit_id = ?");
                $stmt->execute([$produit_id]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'delete_product', "Produit supprimé: " . $product['nom_produit']);
                
                echo json_encode(['success' => true, 'message' => 'Produit supprimé']);
                exit();
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
                exit();
            }
            break;
            
        case 'update_stock':
            try {
                $produit_id = (int)$_POST['produit_id'];
                $new_stock = (int)$_POST['new_stock'];
                
                $stmt = $pdo->prepare("UPDATE produits SET quantite_stock = ? WHERE produit_id = ?");
                $stmt->execute([$new_stock, $produit_id]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'update_stock', "Stock mis à jour - Produit: $produit_id, Nouveau stock: $new_stock");
                
                echo json_encode(['success' => true, 'message' => 'Stock mis à jour']);
                exit();
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
                exit();
            }
            break;
            
        case 'delete_appointment':
            try {
                $client_id = (int)$_POST['client_id'];
                $produit_id = (int)$_POST['produit_id'];
                $date_rdv = $_POST['date_rdv'];
                
                $stmt = $pdo->prepare("DELETE FROM prendre WHERE client_id = ? AND produit_id = ? AND DATE_RENDEZ_VOUS = ?");
                $stmt->execute([$client_id, $produit_id, $date_rdv]);
                
                logAdminAction($pdo, $_SESSION['user_id'], 'delete_appointment', "RDV supprimé - Client: $client_id, Produit: $produit_id");
                
                echo json_encode(['success' => true, 'message' => 'Rendez-vous supprimé']);
                exit();
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
                exit();
            }
            break;
            
        case 'get_admin_logs':
            try {
                $stmt = $pdo->query("
                    SELECT al.*, a.nom_complet as admin_name 
                    FROM admin_logs al 
                    JOIN administrateurs a ON al.admin_id = a.administrateur_id 
                    ORDER BY al.created_at DESC 
                    LIMIT 50
                ");
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($logs);
                exit();
            } catch (PDOException $e) {
                echo json_encode(['error' => $e->getMessage()]);
                exit();
            }
            break;
            
        case 'get_statistics':
            try {
                $stats = [];
                
                // Statistiques par catégorie
                $stmt = $pdo->query("
                    SELECT c.nom_categorie, COUNT(p.produit_id) as count 
                    FROM categories c 
                    LEFT JOIN produits p ON c.categorie_id = p.categorie_id 
                    GROUP BY c.categorie_id, c.nom_categorie
                ");
                $stats['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Rendez-vous par mois (6 derniers mois)
                $stmt = $pdo->query("
                    SELECT 
                        DATE_FORMAT(DATE_RENDEZ_VOUS, '%Y-%m') as month,
                        COUNT(*) as count
                    FROM prendre 
                    WHERE DATE_RENDEZ_VOUS >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(DATE_RENDEZ_VOUS, '%Y-%m')
                    ORDER BY month ASC
                ");
                $stats['appointments_by_month'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Top produits par rendez-vous
                $stmt = $pdo->query("
                    SELECT pr.nom_produit, COUNT(p.produit_id) as rdv_count
                    FROM prendre p
                    JOIN produits pr ON p.produit_id = pr.produit_id
                    GROUP BY p.produit_id, pr.nom_produit
                    ORDER BY rdv_count DESC
                    LIMIT 5
                ");
                $stats['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($stats);
                exit();
            } catch (PDOException $e) {
                echo json_encode(['error' => $e->getMessage()]);
                exit();
            }
            break;
    }
}

// Fonction pour logger les actions admin
function logAdminAction($pdo, $admin_id, $action, $description) {
    try {
        // Créer la table si elle n'existe pas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_logs (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                FOREIGN KEY (admin_id) REFERENCES administrateurs(administrateur_id)
            )
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_id, $action, $description, $ip_address]);
        
    } catch (Exception $e) {
        error_log("Erreur log admin: " . $e->getMessage());
    }
}

// Récupérer les statistiques pour le tableau de bord
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_produits FROM produits");
    $total_produits = $stmt->fetch()['total_produits'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_clients FROM clients");
    $total_clients = $stmt->fetch()['total_clients'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as rdv_attente FROM prendre WHERE STATUS_RENDEZ_VOUS = 0");
    $rdv_attente = $stmt->fetch()['rdv_attente'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as rupture_stock FROM produits WHERE quantite_stock < 5");
    $rupture_stock = $stmt->fetch()['rupture_stock'];
    
    $stmt = $pdo->query("SELECT SUM(quantite_stock * prix) as valeur_stock FROM produits");
    $valeur_stock = $stmt->fetch()['valeur_stock'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as rdv_confirmes FROM prendre WHERE STATUS_RENDEZ_VOUS = 1");
    $rdv_confirmes = $stmt->fetch()['rdv_confirmes'];
    
    // Derniers produits
    $stmt = $pdo->query("SELECT * FROM produits ORDER BY produit_id DESC LIMIT 5");
    $derniers_produits = $stmt->fetchAll();
    
    // Derniers clients
    $stmt = $pdo->query("SELECT nom_complet, email FROM clients ORDER BY client_id DESC LIMIT 5");
    $derniers_clients = $stmt->fetchAll();
    
    // Prochains rendez-vous
    $stmt = $pdo->query("
        SELECT p.DATE_RENDEZ_VOUS, c.nom_complet, pr.nom_produit 
        FROM prendre p 
        JOIN clients c ON p.client_id = c.client_id 
        JOIN produits pr ON p.produit_id = pr.produit_id 
        WHERE p.STATUS_RENDEZ_VOUS = 1 AND p.DATE_RENDEZ_VOUS > NOW() 
        ORDER BY p.DATE_RENDEZ_VOUS ASC 
        LIMIT 5
    ");
    $prochains_rdv = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des données.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpticLook - Tableau de bord Admin</title>
    <link rel="stylesheet" href="Csstotal.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <img class="menu" id="menu" src="./assest/images/icones/icons8-menu-50.png" alt="menu">
        <nav id="nav">
            <div class="logo_nav">opticlook</div>
            <div class="page">
                <ul>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="shop.php">Boutique</a></li>
                    <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
                </ul>
            </div>
            <div class="icone-header">
                <ul>
                    <li class="user-info">
                        <i class="fas fa-user-shield"></i>
                        <span>Admin: <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </li>
                    <li><a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="admin-container">
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-tachometer-alt"></i> Admin Panel</h3>
            </div>
            <ul class="admin-menu">
                <li><a href="#dashboard" class="menu-item active" data-section="dashboard">
                    <i class="fas fa-chart-pie"></i> Tableau de bord
                </a></li>
                <li><a href="#produits" class="menu-item" data-section="produits">
                    <i class="fas fa-box"></i> Gestion Produits
                </a></li>
                <li><a href="#clients" class="menu-item" data-section="clients">
                    <i class="fas fa-users"></i> Gestion Clients
                </a></li>
                <li><a href="#rendezvous" class="menu-item" data-section="rendezvous">
                    <i class="fas fa-calendar-alt"></i> Rendez-vous
                </a></li>
                <li><a href="#stock" class="menu-item" data-section="stock">
                    <i class="fas fa-warehouse"></i> Gestion Stock
                </a></li>
                <li><a href="#rapports" class="menu-item" data-section="rapports">
                    <i class="fas fa-chart-bar"></i> Rapports
                </a></li>
                <li><a href="#logs" class="menu-item" data-section="logs">
                    <i class="fas fa-history"></i> Historique
                </a></li>
                <li><a href="#settings" class="menu-item" data-section="settings">
                    <i class="fas fa-cog"></i> Paramètres
                </a></li>
            </ul>
        </aside>

        <main class="admin-content">
            <!-- Notifications -->
            <div id="notification" class="notification" style="display: none;"></div>
            
            <!-- Section Tableau de bord -->
            <section id="dashboard" class="admin-section active">
                <div class="section-header">
                    <h2><i class="fas fa-chart-pie"></i> Tableau de bord</h2>
                    <div class="header-actions">
                        <button class="btn-refresh" onclick="refreshDashboard()">
                            <i class="fas fa-sync-alt"></i> Actualiser
                        </button>
                        <span class="last-update">Dernière mise à jour: <span id="lastUpdate"><?php echo date('H:i:s'); ?></span></span>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_produits; ?></h3>
                            <p>Produits total</p>
                            <span class="stat-trend positive">
                                <i class="fas fa-arrow-up"></i> +2 cette semaine
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_clients; ?></h3>
                            <p>Clients inscrits</p>
                            <span class="stat-trend positive">
                                <i class="fas fa-arrow-up"></i> +5 ce mois
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $rdv_attente; ?></h3>
                            <p>RDV en attente</p>
                            <span class="stat-trend neutral">
                                <i class="fas fa-minus"></i> À traiter
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card danger">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $rupture_stock; ?></h3>
                            <p>Stock faible</p>
                            <span class="stat-trend negative">
                                <i class="fas fa-arrow-down"></i> Action requise
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card info">
                        <div class="stat-icon">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($valeur_stock, 0); ?> dh</h3>
                            <p>Valeur du stock</p>
                            <span class="stat-trend positive">
                                <i class="fas fa-arrow-up"></i> Inventaire
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card secondary">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $rdv_confirmes; ?></h3>
                            <p>RDV confirmés</p>
                            <span class="stat-trend positive">
                                <i class="fas fa-arrow-up"></i> Ce mois
                            </span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-box-open"></i> Derniers produits ajoutés</h3>
                            <a href="#produits" class="widget-link" onclick="switchSection('produits')">Voir tout</a>
                        </div>
                        <div class="widget-content">
                            <?php foreach ($derniers_produits as $produit): ?>
                                <div class="widget-item">
                                    <img src="<?php echo htmlspecialchars($produit['url_image']); ?>" alt="<?php echo htmlspecialchars($produit['nom_produit']); ?>" class="widget-img">
                                    <div class="widget-info">
                                        <h4><?php echo htmlspecialchars($produit['nom_produit']); ?></h4>
                                        <p><strong><?php echo htmlspecialchars($produit['prix']); ?> dh</strong></p>
                                        <small><i class="fas fa-cubes"></i> Stock: <?php echo $produit['quantite_stock']; ?></small>
                                    </div>
                                    <div class="widget-actions">
                                        <button class="btn-icon" onclick="editProduct(<?php echo $produit['produit_id']; ?>)" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-user-plus"></i> Derniers clients inscrits</h3>
                            <a href="#clients" class="widget-link" onclick="switchSection('clients')">Voir tout</a>
                        </div>
                        <div class="widget-content">
                            <?php foreach ($derniers_clients as $client): ?>
                                <div class="widget-item">
                                    <div class="client-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="widget-info">
                                        <h4><?php echo htmlspecialchars($client['nom_complet']); ?></h4>
                                        <p><?php echo htmlspecialchars($client['email']); ?></p>
                                        <small><i class="fas fa-clock"></i> Nouveau client</small>
                                    </div>
                                    <div class="widget-actions">
                                        <button class="btn-icon" title="Contacter">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-calendar-check"></i> Prochains rendez-vous</h3>
                            <a href="#rendezvous" class="widget-link" onclick="switchSection('rendezvous')">Voir tout</a>
                        </div>
                        <div class="widget-content">
                            <?php if (!empty($prochains_rdv)): ?>
                                <?php foreach ($prochains_rdv as $rdv): ?>
                                    <div class="widget-item">
                                        <div class="rdv-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="widget-info">
                                            <h4><?php echo htmlspecialchars($rdv['nom_complet']); ?></h4>
                                            <p><?php echo htmlspecialchars($rdv['nom_produit']); ?></p>
                                            <small><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($rdv['DATE_RENDEZ_VOUS'])); ?></small>
                                        </div>
                                        <div class="widget-actions">
                                            <span class="status-badge confirmed">Confirmé</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <p>Aucun rendez-vous programmé</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section Gestion Produits -->
            <section id="produits" class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-box"></i> Gestion des Produits</h2>
                    <div class="header-actions">
                        <button class="btn-primary" onclick="openModal('addProductModal')">
                            <i class="fas fa-plus"></i> Ajouter un produit
                        </button>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Rechercher un produit..." id="searchProduct" onkeyup="filterTable('productsTable', this.value)">
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="admin-table" id="productsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Genre</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <tr><td colspan="8" class="loading-cell">
                                <i class="fas fa-spinner fa-spin"></i> Chargement...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Section Gestion Clients -->
            <section id="clients" class="admin-section">
                    <div class="header-actions">
                        <button class="btn-secondary" onclick="exportClients()">
                            <i class="fas fa-download"></i> Exporter
                        </button>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Rechercher un client..." id="searchClient" onkeyup="filterTable('clientsTable', this.value)">
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="admin-table" id="clientsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom complet</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>RDV Total</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="clientsTableBody">
                            <tr><td colspan="7" class="loading-cell">
                                <i class="fas fa-spinner fa-spin"></i> Chargement...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Section Rendez-vous -->
            <section id="rendezvous" class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-alt"></i> Gestion des Rendez-vous</h2>
                    <div class="header-actions">
                        <button class="btn-secondary" onclick="exportAppointments()">
                            <i class="fas fa-calendar-check"></i> Planning
                        </button>
                    </div>
                </div>
                
                <div class="rdv-filters">
                    <button class="filter-btn active" onclick="filterAppointments('all')">
                        <i class="fas fa-list"></i> Tous
                    </button>
                    <button class="filter-btn" onclick="filterAppointments('0')">
                        <i class="fas fa-clock"></i> En attente
                    </button>
                    <button class="filter-btn" onclick="filterAppointments('1')">
                        <i class="fas fa-check-circle"></i> Validés
                    </button>
                    <button class="filter-btn" onclick="filterAppointments('2')">
                        <i class="fas fa-times-circle"></i> Refusés
                    </button>
                </div>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Contact</th>
                                <th>Produit</th>
                                <th>Prix</th>
                                <th>Date RDV</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="rdvTableBody">
                            <tr><td colspan="7" class="loading-cell">
                                <i class="fas fa-spinner fa-spin"></i> Chargement...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Section Gestion Stock -->
            <section id="stock" class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-warehouse"></i> Gestion du Stock</h2>
                    <div class="header-actions">
                        <button class="btn-warning" onclick="generateStockReport()">
                            <i class="fas fa-file-alt"></i> Rapport Stock
                        </button>
                    </div>
                </div>
                
                <div class="stock-alerts">
                    <div class="alert-box">
                        <div class="alert-header">
                            <h3><i class="fas fa-exclamation-triangle"></i> Alertes Stock</h3>
                            <span class="alert-count" id="alertCount">0</span>
                        </div>
                        <div id="stockAlerts" class="alert-content">
                            <i class="fas fa-spinner fa-spin"></i> Chargement...
                        </div>
                    </div>
                </div>

                <div class="stock-overview">
                    <div class="stock-card">
                        <h4><i class="fas fa-chart-pie"></i> Répartition par catégorie</h4>
                        <div class="stock-chart" id="stockChart">
                            <!-- Chart will be generated here -->
                        </div>
                    </div>
                    
                    <div class="stock-card">
                        <h4><i class="fas fa-trending-up"></i> Mouvements récents</h4>
                        <div class="stock-movements" id="stockMovements">
                            <!-- Recent movements will be shown here -->
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Catégorie</th>
                                <th>Stock actuel</th>
                                <th>Seuil minimum</th>
                                <th>Valeur</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="stockTableBody">
                            <tr><td colspan="7" class="loading-cell">
                                <i class="fas fa-spinner fa-spin"></i> Chargement...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Section Rapports -->
            <section id="rapports" class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-bar"></i> Rapports et Statistiques</h2>
                    <div class="header-actions">
                        <select class="period-selector" onchange="updateReportPeriod(this.value)">
                            <option value="7">7 derniers jours</option>
                            <option value="30" selected>30 derniers jours</option>
                            <option value="90">3 derniers mois</option>
                            <option value="365">Cette année</option>
                        </select>
                    </div>
                </div>
                
                <div class="reports-grid">
                    <div class="report-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Produits par catégorie</h3>
                            <button class="btn-icon" onclick="exportChart('category')">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                        <div id="categoryChart" class="chart-container">
                            <canvas id="categoryChartCanvas"></canvas>
                        </div>
                        <div class="chart-legend" id="categoryLegend"></div>
                    </div>
                    
                    <div class="report-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Évolution des rendez-vous</h3>
                            <button class="btn-icon" onclick="exportChart('appointments')">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                        <div id="appointmentsChart" class="chart-container">
                            <canvas id="appointmentsChartCanvas"></canvas>
                        </div>
                    </div>
                    
                    <div class="report-card">
                        <div class="card-header">
                            <h3><i class="fas fa-trophy"></i> Top Produits</h3>
                            <button class="btn-icon" onclick="exportTopProducts()">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                        <div id="topProductsChart" class="chart-container">
                            <div class="top-products-list" id="topProductsList">
                                <i class="fas fa-spinner fa-spin"></i> Chargement...
                            </div>
                        </div>
                    </div>
                    
                    <div class="report-card">
                        <div class="card-header">
                            <h3><i class="fas fa-users"></i> Activité clients</h3>
                            <button class="btn-icon" onclick="exportClientActivity()">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                        <div class="chart-container">
                            <div class="activity-metrics">
                                <div class="metric">
                                    <span class="metric-value" id="activeClients">-</span>
                                    <span class="metric-label">Clients actifs</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-value" id="newClients">-</span>
                                    <span class="metric-label">Nouveaux clients</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-value" id="avgRdvPerClient">-</span>
                                    <span class="metric-label">RDV/Client moyen</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section Historique -->
            <section id="logs" class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Historique des Actions</h2>
                    <div class="header-actions">
                        <button class="btn-secondary" onclick="exportLogs()">
                            <i class="fas fa-download"></i> Exporter logs
                        </button>
                        <select class="log-filter" onchange="filterLogs(this.value)">
                            <option value="all">Toutes les actions</option>
                            <option value="add_product">Ajout produit</option>
                            <option value="edit_product">Modification produit</option>
                            <option value="delete_product">Suppression produit</option>
                            <option value="update_appointment">Mise à jour RDV</option>
                            <option value="update_stock">Mise à jour stock</option>
                        </select>
                    </div>
                </div>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Date/Heure</th>
                                <th>Administrateur</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <tr><td colspan="5" class="loading-cell">
                                <i class="fas fa-spinner fa-spin"></i> Chargement...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Section Paramètres -->
            <section id="settings" class="admin-section">
                <div class="section-header">
                    <h2><i class="fas fa-cog"></i> Paramètres du Système</h2>
                </div>

                <div class="settings-grid">
                    <div class="settings-card">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                        <div class="setting-item">
                            <label class="switch">
                                <input type="checkbox" id="emailNotifications" checked>
                                <span class="slider"></span>
                            </label>
                            <span>Notifications par email</span>
                        </div>
                        <div class="setting-item">
                            <label class="switch">
                                <input type="checkbox" id="stockAlerts" checked>
                                <span class="slider"></span>
                            </label>
                            <span>Alertes de stock faible</span>
                        </div>
                        <div class="setting-item">
                            <label class="switch">
                                <input type="checkbox" id="rdvReminders" checked>
                                <span class="slider"></span>
                            </label>
                            <span>Rappels de rendez-vous</span>
                        </div>
                    </div>

                    <div class="settings-card">
                        <h3><i class="fas fa-database"></i> Base de données</h3>
                        <div class="setting-action">
                            <button class="btn-secondary" onclick="backupDatabase()">
                                <i class="fas fa-download"></i> Sauvegarder BD
                            </button>
                            <p>Dernière sauvegarde: <?php echo date('d/m/Y H:i'); ?></p>
                        </div>
                        <div class="setting-action">
                            <button class="btn-warning" onclick="optimizeDatabase()">
                                <i class="fas fa-tools"></i> Optimiser BD
                            </button>
                            <p>Améliore les performances</p>
                        </div>
                    </div>

                    <div class="settings-card">
                        <h3><i class="fas fa-shield-alt"></i> Sécurité</h3>
                        <div class="setting-action">
                            <button class="btn-primary" onclick="changePassword()">
                                <i class="fas fa-key"></i> Changer mot de passe
                            </button>
                        </div>
                        <div class="setting-item">
                            <label>Seuil stock minimum:</label>
                            <input type="number" value="5" min="0" max="50" onchange="updateStockThreshold(this.value)">
                        </div>
                        <div class="setting-item">
                            <label>Session timeout (minutes):</label>
                            <input type="number" value="60" min="10" max="480" onchange="updateSessionTimeout(this.value)">
                        </div>
                    </div>

                    <div class="settings-card">
                        <h3><i class="fas fa-palette"></i> Apparence</h3>
                        <div class="setting-item">
                            <label>Thème:</label>
                            <select onchange="changeTheme(this.value)">
                                <option value="light">Clair</option>
                                <option value="dark">Sombre</option>
                                <option value="auto">Automatique</option>
                            </select>
                        </div>
                        <div class="setting-item">
                            <label>Langue:</label>
                            <select onchange="changeLanguage(this.value)">
                                <option value="fr" selected>Français</option>
                                <option value="en">English</option>
                                <option value="ar">العربية</option>
                            </select>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal d'ajout/modification de produit -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-plus"></i> Ajouter un nouveau produit</h3>
                <span class="close" onclick="closeModal('addProductModal')">
                    <i class="fas fa-times"></i>
                </span>
            </div>
            
            <form id="addProductForm" class="modal-form">
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" onclick="switchTab('general')">
                        <i class="fas fa-info-circle"></i> Général
                    </button>
                    <button type="button" class="tab-btn" onclick="switchTab('details')">
                        <i class="fas fa-cogs"></i> Détails
                    </button>
                    <button type="button" class="tab-btn" onclick="switchTab('media')">
                        <i class="fas fa-image"></i> Média
                    </button>
                </div>

                <div class="tab-content active" id="general-tab">
                    <div class="form-group">
                        <label for="nom_produit"><i class="fas fa-tag"></i> Nom du produit: *</label>
                        <input type="text" id="nom_produit" name="nom_produit" required placeholder="Ex: Lunettes Ray-Ban Aviator">
                    </div>
                    
                    <div class="form-group">
                        <label for="categorie_id"><i class="fas fa-list"></i> Catégorie: *</label>
                        <select id="categorie_id" name="categorie_id" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="1">Lunettes Médicales</option>
                            <option value="2">Lunettes de Soleil</option>
                            <option value="3">Accessoires</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prix"><i class="fas fa-euro-sign"></i> Prix (dh): *</label>
                            <input type="number" id="prix" name="prix" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label for="quantite_stock"><i class="fas fa-cubes"></i> Stock: *</label>
                            <input type="number" id="quantite_stock" name="quantite_stock" min="0" required placeholder="0">
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="details-tab">
                    <div class="form-group">
                        <label for="description_produit"><i class="fas fa-align-left"></i> Description:</label>
                        <textarea id="description_produit" name="description_produit" rows="4" placeholder="Description détaillée du produit..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="genre"><i class="fas fa-venus-mars"></i> Genre:</label>
                        <select id="genre" name="genre">
                            <option value="Unisexe">Unisexe</option>
                            <option value="Homme">Homme</option>
                            <option value="Femme">Femme</option>
                        </select>
                    </div>
                </div>

                <div class="tab-content" id="media-tab">
                    <div class="form-group">
                        <label for="url_image"><i class="fas fa-image"></i> URL de l'image:</label>
                        <input type="url" id="url_image" name="url_image" placeholder="https://exemple.com/image.jpg">
                    </div>
                    
                    <div class="image-preview" id="imagePreview">
                        <i class="fas fa-image"></i>
                        <p>Aperçu de l'image</p>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('addProductModal')">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> <span id="submitText">Ajouter le produit</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Actions Modal -->
    <div id="quickActionsModal" class="modal">
        <div class="modal-content small">
            <div class="modal-header">
                <h3><i class="fas fa-bolt"></i> Actions Rapides</h3>
                <span class="close" onclick="closeModal('quickActionsModal')">
                    <i class="fas fa-times"></i>
                </span>
            </div>
            <div class="quick-actions">
                <button class="quick-action-btn" onclick="quickAddStock()">
                    <i class="fas fa-plus"></i>
                    <span>Ajouter Stock</span>
                </button>
                <button class="quick-action-btn" onclick="quickApproveAll()">
                    <i class="fas fa-check-double"></i>
                    <span>Approuver Tout</span>
                </button>
                <button class="quick-action-btn" onclick="quickExport()">
                    <i class="fas fa-download"></i>
                    <span>Export Rapide</span>
                </button>
                <button class="quick-action-btn" onclick="quickBackup()">
                    <i class="fas fa-shield-alt"></i>
                    <span>Sauvegarde</span>
                </button>
            </div>
        </div>
    </div>

    <script src="js.js"></script>
    <script src="admin_enhanced.js"></script>
</body>
</html>