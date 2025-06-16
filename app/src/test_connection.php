<?php
/**
 * Script de test de connexion à Azure SQL Database
 * Ce script aide à diagnostiquer les problèmes de connexion
 */

require_once 'includes/db_connect.php';

echo "<h1>Test de Connexion Azure SQL Database</h1>";
echo "<h2>Vérification des Extensions PHP</h2>";

// Vérifier les extensions PHP nécessaires
$extensions = ['pdo', 'pdo_sqlsrv', 'sqlsrv'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<p><strong>$ext:</strong> " . ($loaded ? "✅ Chargée" : "❌ Non chargée") . "</p>";
}

echo "<h2>Variables d'Environnement</h2>";
echo "<p><strong>DB_SERVER:</strong> " . (defined('DB_SERVER') ? DB_SERVER : 'Non définie') . "</p>";
echo "<p><strong>DB_USERNAME:</strong> " . (defined('DB_USERNAME') ? DB_USERNAME : 'Non définie') . "</p>";
echo "<p><strong>DB_PASSWORD:</strong> " . (defined('DB_PASSWORD') && !empty(DB_PASSWORD) ? 'Définie (' . strlen(DB_PASSWORD) . ' caractères)' : 'Non définie ou vide') . "</p>";
echo "<p><strong>DB_NAME:</strong> " . (defined('DB_NAME') ? DB_NAME : 'Non définie') . "</p>";

echo "<h2>Test de Connexion</h2>";

try {
    echo "<p>Tentative de connexion...</p>";
    $conn = connect_db();
    
    if ($conn) {
        echo "<p>✅ <strong>Connexion réussie !</strong></p>";
        
        // Test d'une requête simple
        echo "<h3>Test de Requête</h3>";
        try {
            $stmt = $conn->prepare("SELECT 1 as test_value");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p>✅ Test de requête réussi : " . $result['test_value'] . "</p>";
            
            // Vérifier si la table messages existe
            echo "<h3>Vérification de la Table 'messages'</h3>";
            $stmt = $conn->prepare("SELECT COUNT(*) as table_exists FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'messages'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['table_exists'] > 0) {
                echo "<p>✅ La table 'messages' existe</p>";
                
                // Compter les enregistrements
                $stmt = $conn->prepare("SELECT COUNT(*) as message_count FROM messages");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<p>📊 Nombre de messages dans la table : " . $result['message_count'] . "</p>";
            } else {
                echo "<p>⚠️ La table 'messages' n'existe pas encore</p>";
                echo "<p>Vous pouvez l'exécuter le script setup_db.php pour la créer.</p>";
            }
            
        } catch (PDOException $e) {
            echo "<p>❌ Erreur lors du test de requête : " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        // Fermer la connexion
        $conn = null;
        echo "<p>Connexion fermée</p>";
        
    } else {
        echo "<p>❌ Échec de la connexion</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Erreur de connexion :</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Suggérer des solutions basées sur l'erreur
    $errorMsg = $e->getMessage();
    echo "<h3>Suggestions de résolution :</h3>";
    
    if (strpos($errorMsg, 'Login failed') !== false) {
        echo "<ul>";
        echo "<li>Vérifiez que le nom d'utilisateur et le mot de passe sont corrects</li>";
        echo "<li>Assurez-vous que l'utilisateur a les permissions sur la base de données</li>";
        echo "<li>Vérifiez que l'authentification SQL Server est activée</li>";
        echo "</ul>";
    }
    
    if (strpos($errorMsg, 'Cannot open server') !== false) {
        echo "<ul>";
        echo "<li>Vérifiez les règles de pare-feu Azure SQL</li>";
        echo "<li>Assurez-vous que votre adresse IP est autorisée</li>";
        echo "<li>Vérifiez que le nom du serveur est correct</li>";
        echo "</ul>";
    }
    
    if (strpos($errorMsg, 'unsupported attribute') !== false) {
        echo "<ul>";
        echo "<li>Problème avec les attributs PDO pour SQL Server</li>";
        echo "<li>Vérifiez que le driver SQL Server pour PHP est installé</li>";
        echo "<li>Ce problème devrait être résolu avec les modifications apportées</li>";
        echo "</ul>";
    }
}

echo "<h2>Informations Techniques</h2>";
echo "<p><strong>Version PHP :</strong> " . phpversion() . "</p>";
echo "<p><strong>Drivers PDO disponibles :</strong> " . implode(', ', PDO::getAvailableDrivers()) . "</p>";

?>
