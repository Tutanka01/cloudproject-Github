<?php
/**
 * Script de test pour vérifier et populer la base de données avec des messages de test
 * pour tester la pagination
 */

require_once 'includes/db_connect.php';

try {
    $conn = connect_db();
    
    // Vérifier le nombre de messages existants
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages");
    $stmt->execute();
    $current_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "<h2>🧪 Test de la Pagination</h2>";
    echo "<p>📊 Nombre actuel de messages : <strong>{$current_count}</strong></p>";
    
    // Si moins de 100 messages, en ajouter quelques-uns pour tester la pagination
    if ($current_count < 100) {
        echo "<h3>➕ Ajout de messages de test...</h3>";
        
        $test_messages = [
            "Message de test pour la pagination 📝",
            "Vérification du système de pagination avec ce message plus long qui permet de tester l'affichage et la mise en forme des contenus étendus dans notre interface.",
            "Test d'affichage 🚀",
            "Message court ✨",
            "Pagination test avec emojis 🎯📊📈📉🔄",
            "Système de navigation entre les pages - Test fonctionnel",
            "Interface utilisateur moderne et responsive 💻📱",
            "Test de performance et d'affichage",
            "Message de démonstration pour valider le bon fonctionnement",
            "Contenu de test pour la validation de la pagination avancée",
        ];
        
        $added = 0;
        $target = min(150, 100); // Ajouter jusqu'à avoir 150 messages ou ajouter 100 max
        
        for ($i = $current_count; $i < $target; $i++) {
            $message_content = $test_messages[$i % count($test_messages)] . " - #" . ($i + 1);
            
            $stmt = $conn->prepare("INSERT INTO messages (content) VALUES (?)");
            if ($stmt->execute([$message_content])) {
                $added++;
            }
        }
        
        echo "<p>✅ <strong>{$added}</strong> messages de test ajoutés avec succès!</p>";
        
        // Compter à nouveau
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages");
        $stmt->execute();
        $new_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<p>📊 Nouveau total de messages : <strong>{$new_count}</strong></p>";
    }
    
    echo "<h3>🔍 Test des différentes options de pagination :</h3>";
    echo "<ul>";
    echo "<li><a href='index.php?per_page=20&page=1' target='_blank'>📄 Test avec 20 messages par page</a></li>";
    echo "<li><a href='index.php?per_page=50&page=1' target='_blank'>📄 Test avec 50 messages par page</a></li>";
    echo "<li><a href='index.php?per_page=100&page=1' target='_blank'>📄 Test avec 100 messages par page</a></li>";
    echo "<li><a href='index.php?per_page=200&page=1' target='_blank'>📄 Test avec 200 messages par page</a></li>";
    echo "</ul>";
    
    if ($new_count > 20) {
        $last_page_20 = ceil($new_count / 20);
        $last_page_50 = ceil($new_count / 50);
        
        echo "<h3>🧪 Test de navigation :</h3>";
        echo "<ul>";
        echo "<li><a href='index.php?per_page=20&page={$last_page_20}' target='_blank'>➡️ Aller à la dernière page (20/page)</a></li>";
        echo "<li><a href='index.php?per_page=50&page={$last_page_50}' target='_blank'>➡️ Aller à la dernière page (50/page)</a></li>";
        echo "</ul>";
    }
    
    echo "<h3>📋 Fonctionnalités testées :</h3>";
    echo "<ul>";
    echo "<li>✅ Pagination avec 20, 50, 100, 200 messages par page</li>";
    echo "<li>✅ Navigation première/précédent/suivant/dernière page</li>";
    echo "<li>✅ Affichage des numéros de pages</li>";
    echo "<li>✅ Informations de pagination (X/Y messages, page A/B)</li>";
    echo "<li>✅ Sélecteur de nombre de messages par page</li>";
    echo "<li>✅ Saut rapide à une page spécifique (si plus de 5 pages)</li>";
    echo "<li>✅ Raccourcis clavier pour la navigation</li>";
    echo "<li>✅ Indicateur de chargement</li>";
    echo "<li>✅ Mises à jour temps réel (uniquement page 1)</li>";
    echo "</ul>";
    
    echo "<p><a href='index.php' style='padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>🏠 Retour à l'application</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
}

h2, h3 {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}

ul {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

li {
    margin: 10px 0;
}

p {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #3498db;
}
</style>
