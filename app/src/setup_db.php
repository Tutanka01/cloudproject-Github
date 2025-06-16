<?php
require_once 'includes/db_connect.php';

$conn = connect_db(); // Tente la connexion. Si elle échoue, le script s'arrête ici.

// Requête SQL pour créer la table 'messages' (syntaxe SQL Server)
$sql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='messages' AND xtype='U')
        CREATE TABLE messages (
            id INT IDENTITY(1,1) PRIMARY KEY,
            content NVARCHAR(MAX) NOT NULL,
            created_at DATETIME2 DEFAULT GETDATE()
        )";

try {
    $conn->exec($sql);
    echo "Table 'messages' créée avec succès ou existe déjà.<br>";
    echo "Vous pouvez maintenant ajouter manuellement des données dans la table 'messages' via SQL Server Management Studio ou un client SQL pour tester l'affichage.";
} catch (PDOException $e) {
    echo "ERREUR : Impossible d'exécuter la requête de création de table. " . $e->getMessage();
}

// Préparation de la requête d'insertion
$content = "ayoub the boss";
$insert_sql = "INSERT INTO messages (content) VALUES (?)";

try {
    // Préparation de la requête avec PDO
    $stmt = $conn->prepare($insert_sql);
    
    // Exécution de la requête avec le paramètre
    if ($stmt->execute([$content])) {
        echo "<br>Nouveau message ajouté avec succès: '$content'";
    } else {
        echo "<br>ERREUR: Impossible d'ajouter le message.";
    }
    
} catch (PDOException $e) {
    echo "<br>ERREUR: Impossible de préparer ou d'exécuter la requête. " . $e->getMessage();
}

// Avec PDO, la connexion se ferme automatiquement à la fin du script
// Mais on peut explicitement la fermer si nécessaire
$conn = null;
?>