<?php
/*
 * SÉCURITÉ RENFORCÉE - CONNEXION SQL SERVER PRIVÉE
 * 
 * IMPORTANT : Ce code utilise le Private Endpoint pour Azure SQL Server
 * 
 * Variables d'environnement requises :
 * - DB_SERVER : multizone-sqlserver-dev-XXXXX.database.windows.net (FQDN privé)
 * - DB_USERNAME : sqladmin
 * - DB_PASSWORD : mot_de_passe_sécurisé
 * - DB_NAME : multizone-db-app-az1-dev (ou az2)
 * 
 * RÉSOLUTION DNS :
 * - Le FQDN *.database.windows.net sera résolu par la Private DNS Zone
 * - Adresse IP privée : 10.0.3.x (dans le subnet privé)
 * - AUCUN accès depuis Internet - SEULEMENT depuis le VNet
 * 
 * VÉRIFICATION DE SÉCURITÉ :
 * - telnet multizone-sqlserver-dev-XXXXX.database.windows.net 1433
 *   -> DOIT échouer depuis Internet
 *   -> DOIT fonctionner depuis les VMs du VNet
 */

// Paramètres de connexion recuperes depuis les variables d'environnement système
define('DB_SERVER', $_ENV['DB_SERVER'] ?? $_SERVER['DB_SERVER'] ?? $_ENV['SQL_SERVER'] ?? $_SERVER['SQL_SERVER'] ?? '');
define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? $_ENV['SQL_USER'] ?? $_SERVER['SQL_USER'] ?? '');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? $_ENV['SQL_PASSWORD'] ?? $_SERVER['SQL_PASSWORD'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? '');

// Fonction de debogage (à supprimer en production)
function debug_env_vars() {
    echo "DB_SERVER: " . DB_SERVER . "\n";
    echo "DB_USERNAME: " . DB_USERNAME . "\n";
    echo "DB_PASSWORD: " . (empty(DB_PASSWORD) ? 'VIDE' : 'DeFINI') . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
}

// Fonction de test temporaire pour diagnostiquer Azure SQL

function connect_db() {
    
    // Verification des variables avant connexion
    if (empty(DB_SERVER) || empty(DB_USERNAME) || empty(DB_PASSWORD) || empty(DB_NAME)) {
        error_log("Variables d'environnement manquantes pour la connexion DB");
        debug_env_vars(); // Affichage des variables pour diagnostic
        die("ERREUR : Configuration de base de donnees incomplète.");
    }

    try {
        // Construction de la chaîne de connexion pour SQL Server
        $serverName = DB_SERVER;
        
        // Si le port n'est pas specifie dans DB_SERVER, on l'ajoute
        if (strpos($serverName, ',') === false && strpos($serverName, ':') === false) {
            $serverName .= ',1433';
        }          // Configuration basique pour Azure SQL Database
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        
        // Pour Azure SQL Database, ajout des paramètres de securite
        if (strpos($serverName, '.database.windows.net') !== false) {
            $dsn = "sqlsrv:server={$serverName};Database=" . DB_NAME . ";Encrypt=yes;TrustServerCertificate=no;";
        } else {
            $dsn = "sqlsrv:server={$serverName};Database=" . DB_NAME;
        }
        
        // Log des informations de connexion (sans mot de passe)
        error_log("Tentative de connexion à Azure SQL Database");
        error_log("Serveur: " . $serverName);
        error_log("Base de donnees: " . DB_NAME);
        error_log("Utilisateur: " . DB_USERNAME);
        error_log("DSN: " . $dsn);
        
        $conn = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        
        error_log("Connexion à la base de donnees reussie");
        return $conn;
        
    } catch (PDOException $e) {
        $errorMessage = $e->getMessage();
        error_log("Erreur de connexion à la base de donnees SQL Server : " . $errorMessage);
        error_log("Utilisateur tente: " . DB_USERNAME);
        error_log("Serveur: " . $serverName);
        error_log("DSN utilise: " . $dsn);
        
        // Messages d'erreur specifiques pour Azure SQL
        if (strpos($errorMessage, 'Login failed') !== false) {
            error_log("DIAGNOSTIC: echec d'authentification - Verifiez les credentials Azure SQL");
        }
        if (strpos($errorMessage, 'Cannot open server') !== false) {
            error_log("DIAGNOSTIC: Impossible d'atteindre le serveur - Verifiez le firewall Azure");
        }
        
        die("ERREUR CRITIQUE : Connexion à la base impossible.");
    }
}
?>