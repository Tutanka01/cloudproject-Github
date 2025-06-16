<?php
// Démarrer la capture de sortie pour éviter les problèmes de headers
ob_start();

/**
 * Script de diagnostic pour vérifier la compatibilité de la base de données
 */

require_once 'includes/message_manager.php';

echo "<h2>🔍 Diagnostic de Base de Données</h2>";

try {
    $messageManager = new MessageManager();
    $dbInfo = $messageManager->getDatabaseInfo();
    
    if (!$dbInfo['connected']) {
        echo "<div style='color: red; background: #ffe6e6; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ <strong>Erreur de connexion :</strong> " . htmlspecialchars($dbInfo['error']);
        echo "</div>";
        exit;
    }
    
    echo "<div style='background: #e6f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>📊 Informations de Connexion</h3>";
    echo "<ul>";
    echo "<li><strong>Statut :</strong> ✅ Connecté</li>";    echo "<li><strong>Driver :</strong> " . htmlspecialchars($dbInfo['driver'] ?? 'Non détecté') . "</li>";
    
    if (isset($dbInfo['version'])) {
        echo "<li><strong>Version :</strong> " . htmlspecialchars($dbInfo['version']) . "</li>";
    }
    
    echo "<li><strong>Type :</strong> " . (($dbInfo['is_sql_server'] ?? false) ? 'SQL Server' : 'MySQL/MariaDB/PostgreSQL') . "</li>";
    
    if (isset($dbInfo['connection_status'])) {
        echo "<li><strong>Statut de connexion :</strong> " . htmlspecialchars($dbInfo['connection_status']) . "</li>";
    }
    
    echo "</ul>";
    echo "</div>";
    
    // Test de la pagination
    echo "<h3>🧪 Test de Pagination</h3>";
    
    $testResult = $messageManager->getMessagesPaginated(1, 5);
    
    if ($testResult['success']) {
        echo "<div style='color: green; background: #e6ffe6; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "✅ <strong>Test de pagination réussi !</strong><br>";
        echo "📊 Nombre de messages récupérés : " . count($testResult['messages']) . "<br>";
        echo "📄 Page courante : " . $testResult['pagination']['current_page'] . "<br>";
        echo "📋 Total messages : " . $testResult['pagination']['total_messages'] . "<br>";
        echo "📃 Total pages : " . $testResult['pagination']['total_pages'] . "<br>";
        echo "</div>";
        
        if (!empty($testResult['messages'])) {
            echo "<h4>📝 Exemples de messages :</h4>";
            echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            foreach (array_slice($testResult['messages'], 0, 3) as $i => $message) {
                echo "<div style='border-bottom: 1px solid #dee2e6; padding: 10px 0;'>";
                echo "<strong>Message " . ($i + 1) . " :</strong> " . htmlspecialchars(substr($message['content'], 0, 100)) . 
                     (strlen($message['content']) > 100 ? '...' : '') . "<br>";
                echo "<small>ID: " . $message['id'] . " | Date: " . $message['created_at'] . "</small>";
                echo "</div>";
            }
            echo "</div>";
        }
        
    } else {
        echo "<div style='color: red; background: #ffe6e6; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ <strong>Erreur lors du test de pagination :</strong><br>";
        echo htmlspecialchars($testResult['error']);
        echo "</div>";
    }
    
    // Recommandations
    echo "<h3>💡 Recommandations</h3>";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
      if (($dbInfo['is_sql_server'] ?? false)) {
        echo "🔧 <strong>SQL Server détecté :</strong> La pagination utilise maintenant la syntaxe OFFSET...FETCH compatible.<br>";
        echo "📌 Vérifiez que votre version de SQL Server supporte cette syntaxe (SQL Server 2012+).<br>";
    } else {
        echo "🔧 <strong>Base de données MySQL/MariaDB/PostgreSQL :</strong> La pagination utilise la syntaxe LIMIT...OFFSET standard.<br>";
    }
    
    echo "✅ Les corrections ont été appliquées au code pour supporter les deux types de bases de données.<br>";
    echo "🔄 Rechargez votre application pour voir les changements.";
    echo "</div>";
    
    // Liens de test
    echo "<h3>🧪 Tests de Pagination</h3>";
    echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #dee2e6;'>";
    echo "<p>Testez les différentes options de pagination :</p>";
    echo "<ul>";
    echo "<li><a href='public/index.php?per_page=20&page=1' target='_blank'>📄 20 messages par page</a></li>";
    echo "<li><a href='public/index.php?per_page=50&page=1' target='_blank'>📄 50 messages par page</a></li>";
    echo "<li><a href='public/index.php?per_page=100&page=1' target='_blank'>📄 100 messages par page</a></li>";
    echo "<li><a href='public/index.php?per_page=200&page=1' target='_blank'>📄 200 messages par page</a></li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #ffe6e6; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ <strong>Erreur inattendue :</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
    color: #333;
}

h2, h3, h4 {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}

ul {
    margin: 0;
    padding-left: 20px;
}

li {
    margin: 5px 0;
}

a {
    color: #3498db;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>
