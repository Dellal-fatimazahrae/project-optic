// admin.js - JavaScript pour le tableau de bord administrateur

document.addEventListener('DOMContentLoaded', function() {
    initializeAdminDashboard();
});

function initializeAdminDashboard() {
    // Navigation du menu latéral
    setupSidebarNavigation();
    
    // Chargement initial des données
    loadDashboardData();
    
    // Configuration des formulaires
    setupForms();
    
    // Configuration des filtres
    setupFilters();
    
    // Configuration de la recherche
    setupSearch();
}

// Navigation du menu latéral
function setupSidebarNavigation() {
    const menuItems = document.querySelectorAll('.menu-item');
    const sections = document.querySelectorAll('.admin-section');
    
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Retirer la classe active de tous les éléments
            menuItems.forEach(mi => mi.classList.remove('active'));
            sections.forEach(section => section.classList.remove('active'));
            
            // Ajouter la classe active à l'élément cliqué
            this.classList.add('active');
            
            // Afficher la section correspondante
            const targetSection = this.getAttribute('data-section');
            const section = document.getElementById(targetSection);
            if (section) {
                section.classList.add('active');
                
                // Charger les données spécifiques à la section
                loadSectionData(targetSection);
            }
        });
    });
}

// Chargement des données du tableau de bord
function loadDashboardData() {
    // Cette fonction sera appelée au chargement initial
    loadSectionData('dashboard');
}

// Chargement des données par section
function loadSectionData(section) {
    switch(section) {
        case 'produits':
            loadProducts();
            break;
        case 'clients':
            loadClients();
            break;
        case 'rendezvous':
            loadAppointments();
            break;
        case 'stock':
            loadStockData();
            break;
        case 'rapports':
            loadReports();
            break;
    }
}

// Chargement des produits
async function loadProducts() {
    try {
        showLoading('productsTableBody');
        
        const response = await fetch('api/get_products.php');
        const products = await response.json();
        
        const tbody = document.getElementById('productsTableBody');
        tbody.innerHTML = '';
        
        products.forEach(product => {
            const row = createProductRow(product);
            tbody.appendChild(row);
        });
        
    } catch (error) {
        console.error('Erreur lors du chargement des produits:', error);
        showNotification('Erreur lors du chargement des produits', 'error');
    }
}

// Création d'une ligne de produit
function createProductRow(product) {
    const row = document.createElement('tr');
    
    // Déterminer le statut du stock
    const stockStatus = product.quantite_stock < 5 ? 'low-stock' : 'in-stock';
    const stockText = product.quantite_stock < 5 ? 'Stock faible' : 'En stock';
    
    row.innerHTML = `
        <td>${product.produit_id}</td>
        <td><img src="${product.url_image}" alt="${product.nom_produit}" onerror="this.src='./assest/images/placeholder.jpg'"></td>
        <td>${product.nom_produit}</td>
        <td>${getCategoryName(product.categorie_id)}</td>
        <td>${product.prix} dh</td>
        <td>
            <span class="status-badge ${stockStatus}">${product.quantite_stock}</span>
        </td>
        <td>
            <div class="action-buttons">
                <button class="btn-sm btn-edit" onclick="editProduct(${product.produit_id})">Modifier</button>
                <button class="btn-sm btn-delete" onclick="deleteProduct(${product.produit_id})">Supprimer</button>
            </div>
        </td>
    `;
    
    return row;
}

// Chargement des clients
async function loadClients() {
    try {
        showLoading('clientsTableBody');
        
        const response = await fetch('api/get_clients.php');
        const clients = await response.json();
        
        const tbody = document.getElementById('clientsTableBody');
        tbody.innerHTML = '';
        
        clients.forEach(client => {
            const row = createClientRow(client);
            tbody.appendChild(row);
        });
        
    } catch (error) {
        console.error('Erreur lors du chargement des clients:', error);
        showNotification('Erreur lors du chargement des clients', 'error');
    }
}

// Création d'une ligne de client
function createClientRow(client) {
    const row = document.createElement('tr');
    
    row.innerHTML = `
        <td>${client.client_id}</td>
        <td>${client.nom_complet}</td>
        <td>${client.email}</td>
        <td>${client.numero_telephone || 'N/A'}</td>
        <td>
            <div class="action-buttons">
                <button class="btn-sm btn-edit" onclick="viewClient(${client.client_id})">Voir</button>
                <button class="btn-sm btn-delete" onclick="deleteClient(${client.client_id})">Supprimer</button>
            </div>
        </td>
    `;
    
    return row;
}

// Chargement des rendez-vous
async function loadAppointments(status = 'all') {
    try {
        showLoading('rdvTableBody');
        
        const response = await fetch(`api/get_appointments.php?status=${status}`);
        const appointments = await response.json();
        
        const tbody = document.getElementById('rdvTableBody');
        tbody.innerHTML = '';
        
        appointments.forEach(appointment => {
            const row = createAppointmentRow(appointment);
            tbody.appendChild(row);
        });
        
    } catch (error) {
        console.error('Erreur lors du chargement des rendez-vous:', error);
        showNotification('Erreur lors du chargement des rendez-vous', 'error');
    }
}

// Création d'une ligne de rendez-vous
function createAppointmentRow(appointment) {
    const row = document.createElement('tr');
    
    const statusClass = getAppointmentStatusClass(appointment.STATUS_RENDEZ_VOUS);
    const statusText = getAppointmentStatusText(appointment.STATUS_RENDEZ_VOUS);
    
    row.innerHTML = `
        <td>${appointment.client_nom}</td>
        <td>${appointment.produit_nom}</td>
        <td>${formatDate(appointment.DATE_RENDEZ_VOUS)}</td>
        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
        <td>
            <div class="action-buttons">
                ${appointment.STATUS_RENDEZ_VOUS == 0 ? `
                    <button class="btn-sm btn-approve" onclick="updateAppointmentStatus(${appointment.client_id}, ${appointment.produit_id}, 1)">Valider</button>
                    <button class="btn-sm btn-reject" onclick="updateAppointmentStatus(${appointment.client_id}, ${appointment.produit_id}, 2)">Refuser</button>
                ` : ''}
                <button class="btn-sm btn-delete" onclick="deleteAppointment(${appointment.client_id}, ${appointment.produit_id})">Supprimer</button>
            </div>
        </td>
    `;
    
    return row;
}

// Chargement des données de stock
async function loadStockData() {
    try {
        showLoading('stockTableBody');
        
        const response = await fetch('api/get_stock.php');
        const stockData = await response.json();
        
        const tbody = document.getElementById('stockTableBody');
        tbody.innerHTML = '';
        
        // Afficher les alertes de stock faible
        const alertsContainer = document.getElementById('stockAlerts');
        alertsContainer.innerHTML = '';
        
        const lowStockItems = stockData.filter(item => item.quantite_stock < 5);
        
        if (lowStockItems.length > 0) {
            lowStockItems.forEach(item => {
                const alert = document.createElement('div');
                alert.className = 'stock-alert-item';
                alert.innerHTML = `
                    <strong>${item.nom_produit}</strong> - Stock: ${item.quantite_stock}
                    <button class="btn-sm btn-primary" onclick="updateStock(${item.produit_id})">Réapprovisionner</button>
                `;
                alertsContainer.appendChild(alert);
            });
        } else {
            alertsContainer.innerHTML = '<p>Aucune alerte de stock.</p>';
        }
        
        // Afficher tous les produits dans le tableau
        stockData.forEach(item => {
            const row = createStockRow(item);
            tbody.appendChild(row);
        });
        
    } catch (error) {
        console.error('Erreur lors du chargement du stock:', error);
        showNotification('Erreur lors du chargement du stock', 'error');
    }
}

// Création d'une ligne de stock
function createStockRow(item) {
    const row = document.createElement('tr');
    
    const stockStatus = item.quantite_stock < 5 ? 'low-stock' : 'in-stock';
    
    row.innerHTML = `
        <td>${item.nom_produit}</td>
        <td><span class="status-badge ${stockStatus}">${item.quantite_stock}</span></td>
        <td>5</td>
        <td>
            <div class="action-buttons">
                <button class="btn-sm btn-primary" onclick="updateStock(${item.produit_id})">Modifier stock</button>
            </div>
        </td>
    `;
    
    return row;
}

// Configuration des formulaires
function setupForms() {
    // Formulaire d'ajout de produit
    const addProductForm = document.getElementById('addProductForm');
    if (addProductForm) {
        addProductForm.addEventListener('submit', handleAddProduct);
    }
}

// Gestion de l'ajout de produit
async function handleAddProduct(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const productData = Object.fromEntries(formData.entries());
    
    try {
        showButtonLoading(e.target.querySelector('button[type="submit"]'));
        
        const response = await fetch('api/add_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(productData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Produit ajouté avec succès', 'success');
            closeModal('addProductModal');
            e.target.reset();
            loadProducts(); // Recharger la liste des produits
        } else {
            showNotification(result.message || 'Erreur lors de l\'ajout', 'error');
        }
        
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'ajout du produit', 'error');
    } finally {
        hideButtonLoading(e.target.querySelector('button[type="submit"]'));
    }
}

// Configuration des filtres
function setupFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Retirer la classe active des autres boutons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            
            // Ajouter la classe active au bouton cliqué
            this.classList.add('active');
            
            // Filtrer les données
            const status = this.getAttribute('data-status');
            loadAppointments(status);
        });
    });
}

// Configuration de la recherche
function setupSearch() {
    const searchInputs = document.querySelectorAll('[id^="search"]');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableId = this.id.replace('search', '').toLowerCase() + 'TableBody';
            const table = document.getElementById(tableId);
            
            if (table) {
                filterTable(table, searchTerm);
            }
        });
    });
}

// Filtrage des tableaux
function filterTable(tableBody, searchTerm) {
    const rows = tableBody.querySelectorAll('tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const shouldShow = text.includes(searchTerm);
        row.style.display = shouldShow ? '' : 'none';
    });
}

// Fonctions utilitaires
function getCategoryName(categoryId) {
    const categories = {
        1: 'Lunettes Médicales',
        2: 'Lunettes de Soleil',
        3: 'Accessoires'
    };
    return categories[categoryId] || 'Inconnu';
}

function getAppointmentStatusClass(status) {
    const statusClasses = {
        0: 'pending',
        1: 'approved',
        2: 'rejected'
    };
    return statusClasses[status] || 'pending';
}

function getAppointmentStatusText(status) {
    const statusTexts = {
        0: 'En attente',
        1: 'Validé',
        2: 'Refusé'
    };
    return statusTexts[status] || 'Inconnu';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Gestion des modals
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Fermer le modal en cliquant à l'extérieur
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

// États de chargement
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<tr><td colspan="100%" style="text-align: center; padding: 2rem;"><div class="spinner"></div> Chargement...</td></tr>';
    }
}

function showButtonLoading(button) {
    if (button) {
        button.disabled = true;
        button.innerHTML = '<div class="spinner"></div> Chargement...';
    }
}

function hideButtonLoading(button) {
    if (button) {
        button.disabled = false;
        button.innerHTML = button.getAttribute('data-original-text') || 'Soumettre';
    }
}

// Système de notifications
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Supprimer automatiquement après 3 secondes
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Actions CRUD
async function editProduct(productId) {
    // Implémenter l'édition de produit
    console.log('Éditer le produit:', productId);
}

async function deleteProduct(productId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
        try {
            const response = await fetch(`api/delete_product.php?id=${productId}`, {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Produit supprimé avec succès', 'success');
                loadProducts();
            } else {
                showNotification(result.message || 'Erreur lors de la suppression', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            showNotification('Erreur lors de la suppression', 'error');
        }
    }
}

async function updateAppointmentStatus(clientId, produitId, status) {
    try {
        const response = await fetch('api/update_appointment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                client_id: clientId,
                produit_id: produitId,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Statut mis à jour avec succès', 'success');
            loadAppointments();
        } else {
            showNotification(result.message || 'Erreur lors de la mise à jour', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la mise à jour', 'error');
    }
}

// Mise à jour du stock
async function updateStock(productId) {
    const newStock = prompt('Entrez la nouvelle quantité en stock:');
    
    if (newStock !== null && !isNaN(newStock) && newStock >= 0) {
        try {
            const response = await fetch('api/update_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    stock: parseInt(newStock)
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Stock mis à jour avec succès', 'success');
                loadStockData();
            } else {
                showNotification(result.message || 'Erreur lors de la mise à jour', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            showNotification('Erreur lors de la mise à jour du stock', 'error');
        }
    }
}