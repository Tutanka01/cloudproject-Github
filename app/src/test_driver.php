<?php
/**
 * Test simple de détection du driver de base de données
 */

require_once 'includes/db_connect.php';

try {
    echo "Test de détection du driver de base de données\n";
    echo "============================================\n\n";
    
    $conn = connect_db();
    
    if ($conn) {
        echo "✅ Connexion réussie\n";
        
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            echo "🔍 Driver détecté: " . $driver . "\n";
            
            $version = $conn->getAttribute(PDO::ATTR_SERVER_VERSION);
            echo "📋 Version: " . $version . "\n";
            
            $isSqlServer = ($driver === 'sqlsrv' || $driver === 'mssql');
            echo "🔧 Est SQL Server: " . ($isSqlServer ? 'OUI' : 'NON') . "\n";
            
            // Test de la syntaxe appropriée
            echo "\n📝 Test de requête de pagination:\n";
            
            if ($isSqlServer) {
                echo "   Utilisation syntaxe SQL Server (OFFSET...FETCH)\n";
                $sql = "SELECT TOP 1 id, content, created_at FROM messages ORDER BY created_at DESC";
                
                // Test de comptage
                $countSql = "SELECT COUNT(*) as total FROM messages";
                $countStmt = $conn->prepare($countSql);
                $countStmt->execute();
                $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                echo "   Total messages: $total\n";
                
                if ($total > 0) {
                    // Test pagination avec syntaxe SQL Server
                    $testSql = "SELECT id, content, created_at FROM messages ORDER BY created_at DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
                    $testStmt = $conn->prepare($testSql);
                    $testStmt->bindValue(1, 0, PDO::PARAM_INT);
                    $testStmt->bindValue(2, 5, PDO::PARAM_INT);
                    $testStmt->execute();
                    $results = $testStmt->fetchAll(PDO::FETCH_ASSOC);
                    echo "   ✅ Test pagination réussi: " . count($results) . " résultats\n";
                } else {
                    echo "   ⚠️  Aucun message en base pour tester la pagination\n";
                }
                
            } else {
                echo "   Utilisation syntaxe MySQL/MariaDB (LIMIT...OFFSET)\n";
                $sql = "SELECT id, content, created_at FROM messages ORDER BY created_at DESC LIMIT 1";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo "✅ Test de requête réussi\n";
                echo "   Premier message ID: " . $result['id'] . "\n";
            } else {
                echo "⚠️  Aucun message trouvé (table vide)\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Erreur lors des tests: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "❌ Échec de la connexion\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test terminé\n";
?>
