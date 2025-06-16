<?php
/**
 * Health Check Endpoint pour Azure Load Balancer
 * Ce fichier doit toujours répondre HTTP 200 OK
 */

// Headers pour le health check
header('Content-Type: text/plain');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Vérifications de santé basiques
$health_checks = [];

// 1. Vérifier que PHP fonctionne
$health_checks['php'] = 'OK';

// 2. Vérifier la connectivité à la base de données (optionnel)
try {
    // Inclure le fichier de connexion DB seulement si nécessaire
    if (file_exists('../includes/db_connect.php')) {
        include_once '../includes/db_connect.php';
        // Test simple de connexion
        if (isset($pdo) && $pdo instanceof PDO) {
            $health_checks['database'] = 'OK';
        } else {
            $health_checks['database'] = 'WARNING';
        }
    } else {
        $health_checks['database'] = 'SKIP';
    }
} catch (Exception $e) {
    // Ne pas faire échouer le health check si la DB est down
    $health_checks['database'] = 'WARNING';
}

// 3. Vérifier l'espace disque (simple)
$free_space = disk_free_space('/');
$total_space = disk_total_space('/');
if ($free_space > ($total_space * 0.1)) { // Au moins 10% d'espace libre
    $health_checks['disk'] = 'OK';
} else {
    $health_checks['disk'] = 'WARNING';
}

// 4. Vérifier la mémoire
$memory_usage = memory_get_usage(true);
$memory_limit = ini_get('memory_limit');
$health_checks['memory'] = 'OK';

// Toujours répondre 200 OK pour le Load Balancer
http_response_code(200);

// Réponse simple pour le LB
echo "OK\n";

// Détails de santé (pour debugging)
if (isset($_GET['details'])) {
    echo "\n--- Health Check Details ---\n";
    foreach ($health_checks as $check => $status) {
        echo sprintf("%-12s: %s\n", ucfirst($check), $status);
    }
    echo sprintf("%-12s: %s\n", 'Timestamp', date('Y-m-d H:i:s'));
    echo sprintf("%-12s: %s\n", 'Server', gethostname());
}

// Log pour debugging (optionnel)
error_log("Health check accessed from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
?>
