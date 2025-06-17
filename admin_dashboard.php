<?php
session_start();
include "conixion.php";

// Vérifier si l'utilisateur est un admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: connexion.php");
    exit();
}

// Récupérer les statistiques
try {
    // Nombre total de produits
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits");
    $total_produits = $stmt->fetch()['total'];
    
    // Nombre total de clients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients");
    $total_clients = $stmt->fetch()['total'];
    
    // Nombre de rendez-vous en attente
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM prendre WHERE STATUS_RENDEZ_VOUS = 0");
    $rdv_en_attente = $stmt->fetch()['total'];
    
    // Nombre de produits en stock faible
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE quantite_stock < 5");
    $stock_faible = $stmt->fetch()['total'];
    
    // Derniers rendez-vous
    $stmt = $pdo->query("
        SELECT p.DATE_RENDEZ_VOUS, p.STATUS_RENDEZ_VOUS, c.nom_complet, pr.nom_produit 
        FROM prendre p 
        JOIN clients c ON p.client_id = c.client_id 
        JOIN produits pr ON p.produit_id = pr.produit_id 
        ORDER BY p.DATE_RENDEZ_VOUS DESC 
        LIMIT 5
    ");
    $derniers_rdv = $stmt->fetchAll();
    
    // Derniers clients
    $stmt = $pdo->query("SELECT nom_complet, email FROM clients ORDER BY client_id DESC LIMIT 5");
    $derniers_clients = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $total_produits = 0;
    $total_clients = 0;
    $rdv_en_attente = 0;
    $stock_faible = 0;
    $derniers_rdv = [];
    $derniers_clients = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpticLook - Tableau de Bord Admin</title>
    <link rel="stylesheet" href="Csstotal.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <h3>📊 Admin Panel</h3>
            <nav class="admin-menu">
                <a href="#dashboard" class="menu-item active" data-section="dashboard">
                    📈 Tableau de Bord
                </a>
                <a href="#produits" class="menu-item" data-section="produits">
                    👓 Produits
                </a>
                <a href="#clients" class="menu-item" data-section="clients">
                    👥 Clients
                </a>
                <a href="#rendezvous" class="menu-item" data-section="rendezvous">
                    📅 Rendez-vous
                </a>
                <a href="#stock" class="menu-item" data-section="stock">
                    📦 Gestion Stock
                </a>
                <a href="#rapports" class="menu-item" data-section="rapports">
                    📊 Rapports
                </a>
                <a href="index.php" class="menu-item">
                    🏠 Retour au site
                </a>
                <a href="logout.php" class="menu-item">
                    🚪 Déconnexion
                </a>
            </nav>
        </aside>

        <!-- Contenu principal -->
        <main class="admin-content">
            <!-- Header avec info utilisateur -->
            <div class="section-header">
                <h1>Tableau de Bord Administrateur</h1>
                <div class="user-info">
                    Bonjour, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
            </div>

            <!-- Section Dashboard -->
            <section id="dashboard" class="admin-section active">
                <h2>📈 Vue d'ensemble</h2>
                
                <!-- Statistiques -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👓</div>
                        <div class="stat-info">
                            <h3><?php echo $total_produits; ?></h3>
                            <p>Produits Total</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3><?php echo $total_clients; ?></h3>
                            <p>Clients Inscrits</p>
                        </div>
                    </div>
                    
                    <div class="stat-card <?php echo $rdv_en_attente > 0 ? 'alert' : ''; ?>">
                        <div class="stat-icon">📅</div>
                        <div class="stat-info">
                            <h3><?php echo $rdv_en_attente; ?></h3>
                            <p>RDV en Attente</p>
                        </div>
                    </div>
                    
                    <div class="stat-card <?php echo $stock_faible > 0 ? 'alert' : ''; ?>">
                        <div class="stat-icon">📦</div>
                        <div class="stat-info">
                            <h3><?php echo $stock_faible; ?></h3>
                            <p>Stock Faible</p>
                        </div>
                    </div>
                </div>

                <!-- Widgets du tableau de bord -->
                <div class="dashboard-grid">
                    <!-- Derniers rendez-vous -->
                    <div class="dashboard-widget">
                        <h3>📅 Derniers Rendez-vous</h3>
                        <div class="widget-content">
                            <?php if (!empty($derniers_rdv)): ?>
                                <?php foreach ($derniers_rdv as $rdv): ?>
                                    <div class="widget-item">
                                        <div class="widget-info">
                                            <h4><?php echo htmlspecialchars($rdv['nom_complet']); ?></h4>
                                            <p><?php echo htmlspecialchars($rdv['nom_produit']); ?></p>
                                            <small><?php echo date('d/m/Y H:i', strtotime($rdv['DATE_RENDEZ_VOUS'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Aucun rendez-vous récent.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Derniers clients -->
                    <div class="dashboard-widget">
                        <h3>👥 Nouveaux Clients</h3>
                        <div class="widget-content">
                            <?php if (!empty($derniers_clients)): ?>
                                <?php foreach ($derniers_clients as $client): ?>
                                    <div class="widget-item">
                                        <div class="client-avatar">
                                            <?php echo strtoupper(substr($client['nom_complet'], 0, 1)); ?>
                                        </div>
                                        <div class="widget-info">
                                            <h4><?php echo htmlspecialchars($client['nom_complet']); ?></h4>
                                            <p><?php echo htmlspecialchars($client['email']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Aucun nouveau client.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section Produits -->
            <section id="produits" class="admin-section">
                <div class="section-header">
                    <h2>👓 Gestion des Produits</h2>
                    <button class="btn-primary" onclick="openModal('addProductModal')">
                        ➕ Ajouter un Produit
                    </button>
                </div>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <!-- Les produits seront chargés ici via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Section Clients -->
            <section id="clients" class="admin-section">
                <div class="section-header">
                    <h2>👥 Gestion des Clients</h2>
                    <div class="search-box">
                        <input type="text" id="searchClients" placeholder="Rechercher un client...">
                    </div>
                </div>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom Complet</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="clientsTableBody">
                            <!-- Les clients seront chargés ici via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Section Rendez-vous -->
            <section id="rendezvous" class="admin-section">
                <div class="section-header">
                    <h2>📅 Gestion des Rendez-vous</h2>
                    <div class="rdv-filters">
                        <button class="filter-btn active" data-status="all">Tous</button>
                        <button class="filter-btn" data-status="0">En attente</button>
                        <button class="filter-btn" data-status="1">Validés</button>
                        <button class="filter-btn" data-status="2">Refusés</button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Produit</th>
                                <th>Date/Heure</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="rdvTableBody">
                            <!-- Les rendez-vous seront chargés ici via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Section Stock -->
            <section id="stock" class="admin-section">
                <h2>📦 Gestion du Stock</h2>
                
                <div class="stock-alerts">
                    <div class="alert-box">
                        <h3>⚠️ Alertes Stock Faible</h3>
                        <div id="stockAlerts">
                            <!-- Les alertes seront chargées ici -->
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Stock Actuel</th>
                                <th>Stock Minimum</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="stockTableBody">
                            <!-- Le stock sera chargé ici via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Section Rapports -->
            <section id="rapports" class="admin-section">
                <h2>📊 Rapports et Statistiques</h2>
                
                <div class="reports-grid">
                    <div class="report-card">
                        <h3>📈 Ventes par Mois</h3>
                        <div class="chart-container">
                            <p>Graphique des ventes mensuelles</p>
                        </div>
                    </div>
                    
                    <div class="report-card">
                        <h3>👓 Produits Populaires</h3>
                        <div class="chart-container">
                            <p>Top des produits les plus demandés</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal d'ajout de produit -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addProductModal')">&times;</span>
            <form class="modal-form" id="addProductForm">
                <h3>➕ Ajouter un Nouveau Produit</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom_produit">Nom du Produit:</label>
                        <input type="text" id="nom_produit" name="nom_produit" required>
                    </div>
                    <div class="form-group">
                        <label for="categorie_id">Catégorie:</label>
                        <select id="categorie_id" name="categorie_id" required>
                            <option value="1">Lunettes Médicales</option>
                            <option value="2">Lunettes de Soleil</option>
                            <option value="3">Accessoires</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="prix">Prix (dh):</label>
                        <input type="number" id="prix" name="prix" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="quantite_stock">Quantité en Stock:</label>
                        <input type="number" id="quantite_stock" name="quantite_stock" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="url_image">URL de l'Image:</label>
                    <input type="url" id="url_image" name="url_image">
                </div>
                
                <div class="form-group">
                    <label for="description_produit">Description:</label>
                    <textarea id="description_produit" name="description_produit" rows="3"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-success">Ajouter</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('addProductModal')">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js.js"></script>
    <script src="admin.js"></script>
</body>
</html>