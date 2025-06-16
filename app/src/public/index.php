<?php
// Inclure les classes nécessaires
require_once '../includes/message_manager.php';

// Initialisation
$messageManager = new MessageManager();
$messages = [];
$error_message = null;
$success_message = null;
$form_errors = [];
$pagination = null;

// Paramètres de pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [20, 50, 100, 200]) ? (int)$_GET['per_page'] : 20;

// Gestion du refresh CSRF (AJAX)
if (isset($_GET['refresh_csrf']) && $_GET['refresh_csrf'] == '1') {
    $csrf_token = $messageManager->getCSRFToken();
    header('Content-Type: text/html');
    echo '<input type="hidden" name="csrf_token" value="' . $csrf_token . '">';
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_message') {
    $content = $_POST['content'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    $result = $messageManager->addMessage($content, $csrf_token);
    
    if ($result['success']) {
        $success_message = $result['message'];
        // Redirection pour éviter la re-soumission du formulaire (retour à la page 1)
        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1&per_page=' . $per_page);
        exit;
    } else {
        $form_errors = $result['errors'];
    }
}

// Message de succès après redirection
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Message ajouté avec succès !";
}

// Récupération des messages avec pagination
$messagesResult = $messageManager->getMessagesPaginated($page, $per_page);
if ($messagesResult['success']) {
    $messages = $messagesResult['messages'];
    $pagination = $messagesResult['pagination'];
} else {
    $error_message = $messagesResult['error'];
}

// Génération du token CSRF pour le formulaire
$csrf_token = $messageManager->getCSRFToken();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💬 Messagerie Sécurisée</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>    <div class="container">
        <h1>💬 Messagerie Sécurisée</h1>

        <!-- Messages de statut -->
        <?php if ($success_message): ?>
            <div class="success-message">
                <p>✅ <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="error-message">
                <p>❌ <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($form_errors)): ?>
            <div class="error-message">
                <?php foreach ($form_errors as $error): ?>
                    <p>❌ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>        <!-- Formulaire d'ajout de message -->
        <?php if (!$messageManager->isBlocked()): ?>
            <div class="form-container">
                <h2>✏️ Ajouter un message</h2>
                <div class="security-status">
                    <span class="security-indicator"></span>
                    <small>🔒 Connexion sécurisée | Protection CSRF active | Rate limiting en place</small>
                </div>
                <form method="POST" action="" class="message-form">
                    <input type="hidden" name="action" value="add_message">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label for="content">Votre message :</label>
                        <textarea 
                            id="content" 
                            name="content" 
                            rows="4" 
                            maxlength="1000" 
                            placeholder="Écrivez votre message ici... (max 1000 caractères)"
                            required
                            autocomplete="off"
                            spellcheck="true"
                        ><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                        <div class="char-counter">
                            <span id="char-count">0</span>/1000 caractères
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">📤 Envoyer le message</button>
                </form>
            </div>
        <?php else: ?>
            <div class="blocked-message">
                <p>🚫 Vous avez été temporairement bloqué en raison de trop nombreuses tentatives.</p>
                <p>⏰ Temps restant : <?php echo ceil($messageManager->getBlockTimeRemaining() / 60); ?> minute(s)</p>
            </div>
        <?php endif; ?>        <!-- Affichage des messages -->
        <div class="messages-section">
            <div class="messages-header">
                <h2>📋 Messages récents</h2>                <div class="pagination-controls">
                    <label for="per-page-select">Messages par page :</label>
                    <select id="per-page-select" onchange="changePerPage(this.value)">
                        <option value="20" <?php echo $per_page == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="200" <?php echo $per_page == 200 ? 'selected' : ''; ?>>200</option>
                    </select>
                </div>
                <div class="realtime-controls">
                    <span id="realtime-status" class="realtime-status active">🟢 Actif</span>
                    <button id="realtime-toggle" class="btn btn-secondary btn-sm" onclick="toggleRealTime()">⏸️ Pause</button>
                </div>
            </div>
              <?php if ($pagination): ?>
                <div class="pagination-info">
                    <p>
                        📊 Affichage de <?php echo count($messages); ?> message(s) sur <?php echo $pagination['total_messages']; ?> total(aux)
                        | Page <?php echo $pagination['current_page']; ?> sur <?php echo $pagination['total_pages']; ?>
                    </p>
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="pagination-shortcuts">
                            <small>💡 Raccourcis : ← Précédent | → Suivant | Home Première | End Dernière</small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="messages-container messages-list">
                <?php if (empty($messages)): ?>
                    <p class="no-messages">📭 Aucun message trouvé dans la base de données.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($messages as $message): ?>
                            <li class="message-item" data-message-id="<?php echo $message['id']; ?>">
                                <div class="message-content">
                                    <p><?php echo nl2br(htmlspecialchars($message['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                                </div>
                                <div class="message-meta">
                                    <small>
                                        🆔 ID : <?php echo $message['id']; ?> | 
                                        📅 Posté le : <?php echo date('d/m/Y à H:i', strtotime($message['created_at'])); ?>
                                    </small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
              <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                <div class="pagination-wrapper">
                    <div class="pagination">
                        <?php
                        $start_page = max(1, $pagination['current_page'] - 2);
                        $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
                        ?>
                        
                        <!-- Bouton Première page -->
                        <?php if ($pagination['current_page'] > 1): ?>
                            <a href="?page=1&per_page=<?php echo $per_page; ?>" class="pagination-btn first">⏮️ Première</a>
                            <a href="?page=<?php echo $pagination['current_page'] - 1; ?>&per_page=<?php echo $per_page; ?>" class="pagination-btn prev">⬅️ Précédent</a>
                        <?php endif; ?>
                          <!-- Pages numérotées avec ellipses pour les grandes listes -->
                        <?php if ($pagination['total_pages'] > 10): ?>
                            <!-- Pagination complexe pour beaucoup de pages -->
                            <?php if ($start_page > 1): ?>
                                <a href="?page=1&per_page=<?php echo $per_page; ?>" class="pagination-btn">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i == $pagination['current_page']): ?>
                                    <span class="pagination-btn current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&per_page=<?php echo $per_page; ?>" class="pagination-btn"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $pagination['total_pages']): ?>
                                <?php if ($end_page < $pagination['total_pages'] - 1): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $pagination['total_pages']; ?>&per_page=<?php echo $per_page; ?>" class="pagination-btn"><?php echo $pagination['total_pages']; ?></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Pagination simple pour peu de pages -->
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i == $pagination['current_page']): ?>
                                    <span class="pagination-btn current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&per_page=<?php echo $per_page; ?>" class="pagination-btn"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        <?php endif; ?>
                        
                        <!-- Bouton Dernière page -->
                        <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                            <a href="?page=<?php echo $pagination['current_page'] + 1; ?>&per_page=<?php echo $per_page; ?>" class="pagination-btn next">Suivant ➡️</a>
                            <a href="?page=<?php echo $pagination['total_pages']; ?>&per_page=<?php echo $per_page; ?>" class="pagination-btn last">Dernière ⏭️</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Saut rapide à une page -->
                    <?php if ($pagination['total_pages'] > 5): ?>
                        <div class="quick-jump">
                            <label for="page-jump">Aller à la page :</label>
                            <input type="number" id="page-jump" min="1" max="<?php echo $pagination['total_pages']; ?>" 
                                   placeholder="<?php echo $pagination['current_page']; ?>" 
                                   onkeypress="handlePageJump(event)">
                            <button onclick="jumpToPage()" class="btn btn-sm btn-outline">Aller</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>    <script>
        // Compteur de caractères et validation
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('content');
            const charCount = document.getElementById('char-count');
            const form = document.querySelector('.message-form');
            const submitBtn = document.querySelector('.submit-btn');
            
            if (textarea && charCount) {
                function updateCharCount() {
                    const count = textarea.value.length;
                    charCount.textContent = count;
                    
                    // Changement de couleur si proche de la limite
                    if (count > 900) {
                        charCount.style.color = '#dc3545';
                        charCount.style.fontWeight = 'bold';
                    } else if (count > 800) {
                        charCount.style.color = '#ffc107';
                        charCount.style.fontWeight = 'bold';
                    } else {
                        charCount.style.color = '#28a745';
                        charCount.style.fontWeight = 'normal';
                    }
                }
                
                // Validation en temps réel
                function validateContent() {
                    const content = textarea.value.trim();
                    const isValid = content.length >= 1 && content.length <= 1000;
                    
                    if (submitBtn) {
                        submitBtn.disabled = !isValid;
                        submitBtn.style.opacity = isValid ? '1' : '0.6';
                        submitBtn.style.cursor = isValid ? 'pointer' : 'not-allowed';
                    }
                    
                    // Retirer les styles d'erreur si valide
                    if (isValid) {
                        textarea.classList.remove('error');
                    }
                    
                    return isValid;
                }
                
                textarea.addEventListener('input', function() {
                    updateCharCount();
                    validateContent();
                });
                
                updateCharCount();
                validateContent();
            }
            
            // Protection contre la soumission multiple
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (submitBtn.disabled) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Validation finale avant soumission
                    const content = textarea.value.trim();
                    if (content.length < 1) {
                        e.preventDefault();
                        textarea.classList.add('error');
                        showToast('Le message ne peut pas être vide', 'error');
                        return false;
                    }
                    
                    if (content.length > 1000) {
                        e.preventDefault();
                        textarea.classList.add('error');
                        showToast('Le message est trop long', 'error');
                        return false;
                    }
                    
                    // Désactiver le bouton pour éviter les doubles soumissions
                    submitBtn.disabled = true;
                    submitBtn.textContent = '📤 Envoi en cours...';
                    submitBtn.style.opacity = '0.7';
                });
            }
            
            // Auto-dismiss des messages après 5 secondes
            const alertMessages = document.querySelectorAll('.success-message, .error-message');
            alertMessages.forEach(message => {
                setTimeout(() => {
                    message.style.transition = 'opacity 0.5s ease';
                    message.style.opacity = '0';
                    setTimeout(() => message.remove(), 500);
                }, 5000);
            });
        });
        
        // Système de notifications toast
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            
            const style = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 1000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 300px;
                word-wrap: break-word;
            `;
            
            const colors = {
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107',
                info: '#17a2b8'
            };
            
            toast.style.cssText = style + `background-color: ${colors[type] || colors.info};`;
            document.body.appendChild(toast);
            
            // Animation d'apparition
            setTimeout(() => toast.style.transform = 'translateX(0)', 100);
            
            // Auto-suppression
            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);        }
        
        // Fonction pour changer le nombre d'éléments par page
        function changePerPage(newPerPage) {            // Validation de la valeur
            const validValues = [20, 50, 100, 200];
            if (!validValues.includes(parseInt(newPerPage))) {
                showToast('Valeur invalide pour le nombre d\'éléments par page', 'error');
                return;
            }
            
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('per_page', newPerPage);
            currentUrl.searchParams.set('page', '1'); // Retour à la page 1
            
            // Feedback visuel avant la redirection
            showToast(`Changement pour ${newPerPage} messages par page...`, 'info');
            
            // Redirection avec un petit délai pour le feedback
            setTimeout(() => {
                window.location.href = currentUrl.toString();
            }, 500);
        }
        
        // Fonction pour aller directement à une page spécifique
        function goToPage(pageNumber) {
            const currentUrl = new URL(window.location.href);
            const currentPerPage = currentUrl.searchParams.get('per_page') || '20';
            
            currentUrl.searchParams.set('page', pageNumber);
            currentUrl.searchParams.set('per_page', currentPerPage);
            
            window.location.href = currentUrl.toString();
        }
        
        // Gestion des raccourcis clavier pour la navigation
        document.addEventListener('keydown', function(e) {
            // Vérifier qu'on n'est pas en train de taper dans un input
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                return;
            }
            
            const currentUrl = new URL(window.location.href);
            const currentPage = parseInt(currentUrl.searchParams.get('page')) || 1;
            const currentPerPage = currentUrl.searchParams.get('per_page') || '20';
            
            // Flèche gauche ou 'p' pour page précédente
            if ((e.key === 'ArrowLeft' || e.key === 'p') && currentPage > 1) {
                e.preventDefault();
                goToPage(currentPage - 1);
            }
            
            // Flèche droite ou 'n' pour page suivante
            if (e.key === 'ArrowRight' || e.key === 'n') {
                const totalPages = document.querySelector('.pagination-btn.last')?.href?.match(/page=(\d+)/)?.[1];
                if (totalPages && currentPage < parseInt(totalPages)) {
                    e.preventDefault();
                    goToPage(currentPage + 1);
                }
            }
            
            // Home pour première page
            if (e.key === 'Home' && currentPage > 1) {
                e.preventDefault();
                goToPage(1);
            }
            
            // End pour dernière page
            if (e.key === 'End') {
                const totalPages = document.querySelector('.pagination-btn.last')?.href?.match(/page=(\d+)/)?.[1];
                if (totalPages && currentPage < parseInt(totalPages)) {
                    e.preventDefault();
                    goToPage(totalPages);
                }
            }
        });
          // Protection CSRF - Régénération automatique
        setInterval(function() {
            // Régénère le token CSRF toutes les 30 minutes
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) {
                fetch(window.location.href + '?refresh_csrf=1')
                    .then(response => response.text())
                    .then(data => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(data, 'text/html');
                        const newToken = doc.querySelector('input[name="csrf_token"]');
                        if (newToken) {
                            csrfInput.value = newToken.value;
                        }
                    })
                    .catch(() => {}); // Silencieux en cas d'erreur
            }
        }, 1800000); // 30 minutes        // ⚡ Système de mise à jour en temps réel
        let realTimeEnabled = true;
        let lastMessageId = 0;
        let updateInterval = null;
        const UPDATE_FREQUENCY = 3000; // 3 secondes
        
        // Vérifier si on est sur la première page pour les mises à jour temps réel
        const urlParams = new URLSearchParams(window.location.search);
        const currentPage = parseInt(urlParams.get('page')) || 1;
        const isFirstPage = currentPage === 1;
        
        // Désactiver le temps réel si on n'est pas sur la première page
        if (!isFirstPage) {
            realTimeEnabled = false;
            const realTimeStatus = document.getElementById('realtime-status');
            const realTimeToggle = document.getElementById('realtime-toggle');
            if (realTimeStatus) {
                realTimeStatus.textContent = '⏸️ Désactivé (page ' + currentPage + ')';
                realTimeStatus.className = 'realtime-status';
            }
            if (realTimeToggle) {
                realTimeToggle.textContent = '❌ Indisponible';
                realTimeToggle.disabled = true;
                realTimeToggle.style.opacity = '0.5';
            }
        }
        
        // Initialiser l'ID du dernier message
        function initLastMessageId() {
            const messages = document.querySelectorAll('.message-item');
            if (messages.length > 0) {
                const firstMessage = messages[0];
                const messageId = firstMessage.getAttribute('data-message-id');
                if (messageId) {
                    lastMessageId = parseInt(messageId);
                }
            }
        }
        
        // Fonction pour récupérer les nouveaux messages
        function fetchNewMessages() {
            if (!realTimeEnabled) return;
            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'api.php?action=get_messages&since_id=' + lastMessageId, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success && response.messages.length > 0) {
                                addNewMessages(response.messages);
                                updateLastMessageId(response.messages);
                            }
                        } catch (e) {
                            console.warn('Erreur parsing JSON:', e);
                        }
                    } else if (xhr.status === 429) {
                        // Rate limit atteint, ralentir les requêtes
                        clearInterval(updateInterval);
                        updateInterval = setInterval(fetchNewMessages, UPDATE_FREQUENCY * 2);
                        showToast('Ralentissement des mises à jour en temps réel', 'warning');
                    }
                }
            };
            
            xhr.send();
        }
          // Ajouter les nouveaux messages au DOM
        function addNewMessages(messages) {
            const messagesContainer = document.querySelector('.messages-container ul');
            if (!messagesContainer) {
                // Cas où il n'y a pas encore de messages, créer la structure
                const container = document.querySelector('.messages-container');
                if (container) {
                    // Supprimer le message "aucun message"
                    const noMessages = container.querySelector('.no-messages');
                    if (noMessages) {
                        noMessages.remove();
                    }
                    
                    // Créer la liste si elle n'existe pas
                    if (!container.querySelector('ul')) {
                        container.innerHTML = '<ul></ul>';
                    }
                }
                return;
            }
            
            messages.reverse().forEach(message => {
                const messageHtml = createMessageElement(message);
                messagesContainer.insertAdjacentHTML('afterbegin', messageHtml);
                
                // Animation d'apparition pour le nouveau message
                const newElement = messagesContainer.firstElementChild;
                if (newElement) {
                    newElement.style.opacity = '0';
                    newElement.style.transform = 'translateY(-20px)';
                    
                    setTimeout(() => {
                        newElement.style.transition = 'all 0.3s ease';
                        newElement.style.opacity = '1';
                        newElement.style.transform = 'translateY(0)';
                        
                        // Retirer la classe new-message après l'animation
                        setTimeout(() => {
                            newElement.classList.remove('new-message');
                        }, 2000);
                    }, 50);
                }
            });
            
            // Notification discrète
            showToast(`${messages.length} nouveau${messages.length > 1 ? 'x' : ''} message${messages.length > 1 ? 's' : ''}`, 'info');
        }
          // Créer l'élément HTML pour un message
        function createMessageElement(message) {
            const date = new Date(message.created_at);
            const formattedDate = date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit', 
                year: 'numeric'
            }) + ' à ' + date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
            
            return `
                <li class="message-item new-message" data-message-id="${message.id}">
                    <div class="message-content">
                        <p>${escapeHtml(message.content).replace(/\n/g, '<br>')}</p>
                    </div>
                    <div class="message-meta">
                        <small>
                            🆔 ID : ${message.id} | 
                            📅 Posté le : ${formattedDate}
                        </small>
                    </div>
                </li>
            `;
        }
        
        // Échapper le HTML pour sécurité
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Mettre à jour l'ID du dernier message
        function updateLastMessageId(messages) {
            if (messages.length > 0) {
                const maxId = Math.max(...messages.map(m => parseInt(m.id)));
                if (maxId > lastMessageId) {
                    lastMessageId = maxId;
                }
            }
        }
          // Contrôles pour les mises à jour en temps réel
        function toggleRealTime() {
            // Vérifier si on est sur la première page
            if (!isFirstPage) {
                showToast('Les mises à jour en temps réel ne sont disponibles que sur la première page', 'warning');
                return;
            }
            
            realTimeEnabled = !realTimeEnabled;
            const button = document.getElementById('realtime-toggle');
            const status = document.getElementById('realtime-status');
            
            if (realTimeEnabled) {
                button.textContent = '⏸️ Pause';
                button.className = 'btn btn-secondary btn-sm';
                status.textContent = '🟢 Actif';
                status.className = 'realtime-status active';
                startRealTimeUpdates();
            } else {
                button.textContent = '▶️ Reprendre';
                button.className = 'btn btn-success btn-sm';
                status.textContent = '⏸️ En pause';
                status.className = 'realtime-status paused';
                stopRealTimeUpdates();
            }
        }
          function startRealTimeUpdates() {
            if (!isFirstPage || !realTimeEnabled) return;
            if (updateInterval) clearInterval(updateInterval);
            updateInterval = setInterval(fetchNewMessages, UPDATE_FREQUENCY);
        }
        
        function stopRealTimeUpdates() {
            if (updateInterval) {
                clearInterval(updateInterval);
                updateInterval = null;
            }
        }
        
        // Initialisation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            initLastMessageId();
            startRealTimeUpdates();
            
            // Pause automatique quand l'onglet n'est pas visible
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') {
                    stopRealTimeUpdates();
                } else if (realTimeEnabled) {
                    startRealTimeUpdates();
                }
            });
        });
        
        // Nettoyage avant fermeture
        window.addEventListener('beforeunload', function() {
            stopRealTimeUpdates();
        });
        
        // Fonctions pour le saut rapide de page
        function jumpToPage() {
            const input = document.getElementById('page-jump');
            const pageNumber = parseInt(input.value);
            const maxPage = parseInt(input.max);
            
            if (isNaN(pageNumber) || pageNumber < 1 || pageNumber > maxPage) {
                showToast(`Veuillez entrer un numéro de page entre 1 et ${maxPage}`, 'error');
                input.focus();
                return;
            }
            
            showToast(`Redirection vers la page ${pageNumber}...`, 'info');
            goToPage(pageNumber);
        }
        
        function handlePageJump(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                jumpToPage();
            }
        }
        
        // Amélioration de l'indicateur de chargement pour la pagination
        function showPaginationLoading() {
            const loader = document.createElement('div');
            loader.id = 'pagination-loader';
            loader.className = 'pagination-loader';
            loader.innerHTML = `
                <div class="loader-content">
                    <div class="spinner"></div>
                    <span>Chargement des messages...</span>
                </div>
            `;
            
            document.body.appendChild(loader);
            
            // Supprimer automatiquement après 5 secondes si toujours présent
            setTimeout(() => {
                const existingLoader = document.getElementById('pagination-loader');
                if (existingLoader) {
                    existingLoader.remove();
                }
            }, 5000);
        }
        
        // Intercepter les clics sur les liens de pagination pour afficher le loader
        document.addEventListener('DOMContentLoaded', function() {
            const paginationLinks = document.querySelectorAll('.pagination-btn[href]');
            
            paginationLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    showPaginationLoading();
                });
            });
            
            // Validation en temps réel pour le saut de page
            const pageJumpInput = document.getElementById('page-jump');
            if (pageJumpInput) {
                pageJumpInput.addEventListener('input', function() {
                    const value = parseInt(this.value);
                    const max = parseInt(this.max);
                    
                    if (isNaN(value) || value < 1 || value > max) {
                        this.style.borderColor = '#dc3545';
                        this.style.backgroundColor = '#fff5f5';
                    } else {
                        this.style.borderColor = '#28a745';
                        this.style.backgroundColor = '#f8fff8';
                    }
                });
            }
        });
    </script>
</body>
</html>