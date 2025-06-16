<?php
// API endpoint pour les mises à jour en temps réel
require_once '../includes/message_manager.php';

// Headers CORS et sécurité
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Vérifier que c'est une requête AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Limiter aux requêtes GET pour la récupération de messages
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Vérifier le paramètre d'action
$action = $_GET['action'] ?? '';

if ($action !== 'get_messages') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// Rate limiting spécifique pour l'API (plus permissif que le formulaire)
session_start();
$current_time = time();
$api_key = 'api_requests_' . $_SERVER['REMOTE_ADDR'];

if (!isset($_SESSION[$api_key])) {
    $_SESSION[$api_key] = [];
}

// Nettoyer les anciennes tentatives (60 secondes)
$_SESSION[$api_key] = array_filter($_SESSION[$api_key], function($timestamp) use ($current_time) {
    return ($current_time - $timestamp) < 60;
});

// Limiter à 30 requêtes par minute pour l'API
if (count($_SESSION[$api_key]) >= 30) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded', 'retry_after' => 60]);
    exit;
}

$_SESSION[$api_key][] = $current_time;

try {
    $messageManager = new MessageManager();
    
    // Paramètres de pagination pour l'API
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [20, 50, 100, 200]) ? (int)$_GET['per_page'] : 20;
    
    // Paramètre optionnel pour récupérer seulement les nouveaux messages
    $since_id = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
    
    if ($since_id > 0) {
        // Mode temps réel : récupérer seulement les nouveaux messages
        $result = $messageManager->getMessages($since_id);
    } else {
        // Mode pagination normale
        $result = $messageManager->getMessagesPaginated($page, $per_page);
    }
    
    if ($result['success']) {
        $response = [
            'success' => true,
            'messages' => $result['messages'],
            'count' => count($result['messages']),
            'timestamp' => time()
        ];
        
        // Ajouter les infos de pagination si disponibles
        if (isset($result['pagination'])) {
            $response['pagination'] = $result['pagination'];
        }
        
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Unable to fetch messages'
        ]);
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>
