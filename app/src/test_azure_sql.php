<?php
/**
 * Test spécifique pour Azure SQL Database
 */

require_once 'includes/db_connect.php';

function testAzureSqlPagination() {
    try {
        echo "🔍 Test de pagination Azure SQL Database\n";
        echo str_repeat("=", 50) . "\n";
        
        $conn = connect_db();
        if (!$conn) {
            throw new Exception("Impossible de se connecter à la base de données");
        }
        
        $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        echo "Driver: $driver\n";
        
        // Compter les messages
        $countSql = "SELECT COUNT(*) as total FROM messages";
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute();
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "Total messages: $total\n";
        
        if ($total == 0) {
            echo "⚠️  Aucun message en base. Ajout de messages de test...\n";
            for ($i = 1; $i <= 10; $i++) {
                $insertSql = "INSERT INTO messages (content) VALUES (?)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->execute(["Message de test #$i pour pagination Azure SQL"]);
            }
            echo "✅ 10 messages de test ajoutés\n";
            $total = 10;
        }
        
        // Test avec syntaxe Azure SQL (OFFSET...FETCH)
        $page = 1;
        $per_page = 5;
        $offset = ($page - 1) * $per_page;
        
        echo "\nTest pagination - Page: $page, Par page: $per_page, Offset: $offset\n";
        echo "Types: offset=" . gettype($offset) . ", per_page=" . gettype($per_page) . "\n";
        
        // Forcer le type entier
        $offset = (int)$offset;
        $per_page = (int)$per_page;
        echo "Après conversion: offset=$offset (" . gettype($offset) . "), per_page=$per_page (" . gettype($per_page) . ")\n";
        
        $sql = "SELECT id, content, created_at FROM messages ORDER BY created_at DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        echo "SQL: $sql\n";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $offset, PDO::PARAM_INT);
        $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
        
        echo "Exécution de la requête...\n";
        $success = $stmt->execute();
        
        if ($success) {
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "✅ Succès! " . count($results) . " résultats obtenus\n";
            
            foreach ($results as $i => $row) {
                echo "  " . ($i + 1) . ". ID: {$row['id']}, Contenu: " . substr($row['content'], 0, 50) . "...\n";
            }
        } else {
            $errorInfo = $stmt->errorInfo();
            echo "❌ Erreur: " . $errorInfo[2] . "\n";
            echo "Code SQLSTATE: " . $errorInfo[0] . "\n";
            echo "Code erreur driver: " . $errorInfo[1] . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
}

// Exécuter le test
testAzureSqlPagination();
?>
