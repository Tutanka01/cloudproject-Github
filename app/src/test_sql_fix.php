<?php
/**
 * Test rapide de la correction SQL Server
 */

require_once 'includes/message_manager.php';

header('Content-Type: application/json');

try {
    $messageManager = new MessageManager();
    
    // Test simple de pagination
    $result = $messageManager->getMessagesPaginated(1, 5);
    
    if ($result['success']) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Pagination fonctionne correctement',
            'data' => [
                'total_messages' => $result['pagination']['total_messages'],
                'current_page' => $result['pagination']['current_page'],
                'total_pages' => $result['pagination']['total_pages'],
                'messages_count' => count($result['messages'])
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erreur de pagination',
            'error' => $result['error']
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Exception attrapée',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
