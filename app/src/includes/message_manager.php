<?php
require_once 'db_connect.php';
require_once 'security.php';

class MessageManager {
    private $conn;
    private $security;
    
    public function __construct() {
        $this->security = new SecurityManager();
    }
    
    /**
     * Initialisation de la connexion DB
     */
    private function initConnection(): bool {
        try {
            $this->conn = connect_db();
            return true;
        } catch (Exception $e) {
            error_log("Erreur de connexion DB dans MessageManager: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ajout sécurisé d'un message
     */
    public function addMessage(string $content, string $csrf_token): array {
        $result = [
            'success' => false,
            'message' => '',
            'errors' => []
        ];
        
        // Vérification du rate limiting
        if (!$this->security->checkRateLimit()) {
            $remaining = $this->security->getBlockTimeRemaining();
            $result['errors'][] = "Trop de tentatives. Veuillez attendre " . ceil($remaining / 60) . " minute(s).";
            return $result;
        }
        
        // Vérification du token CSRF
        if (!$this->security->validateCSRFToken($csrf_token)) {
            $this->security->incrementAttempts();
            $result['errors'][] = "Token de sécurité invalide. Veuillez recharger la page.";
            return $result;
        }
        
        // Validation et nettoyage du contenu
        $validation = $this->security->validateAndSanitizeContent($content);
        if (!$validation['is_valid']) {
            $this->security->incrementAttempts();
            $result['errors'] = $validation['errors'];
            return $result;
        }
        
        // Initialisation de la connexion
        if (!$this->initConnection()) {
            $result['errors'][] = "Erreur de connexion à la base de données.";
            return $result;
        }
        
        try {
            // Préparation et exécution de la requête avec paramètres liés
            $sql = "INSERT INTO messages (content) VALUES (?)";
            $stmt = $this->conn->prepare($sql);
            
            if ($stmt->execute([$validation['content']])) {
                $result['success'] = true;
                $result['message'] = "Message ajouté avec succès !";
                
                // Log de sécurité
                error_log("Message ajouté avec succès depuis IP: " . $this->getClientIP());
            } else {
                $result['errors'][] = "Erreur lors de l'ajout du message.";
                $this->security->incrementAttempts();
            }
            
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de l'ajout de message: " . $e->getMessage());
            $result['errors'][] = "Erreur technique lors de l'ajout du message.";
            $this->security->incrementAttempts();
        } finally {
            $this->conn = null;
        }
        
        return $result;
    }    /**
     * Récupération sécurisée des messages
     * @param int $since_id ID minimum pour récupérer seulement les nouveaux messages
     */
    public function getMessages(int $since_id = 0): array {
        $result = [
            'success' => false,
            'messages' => [],
            'error' => null
        ];
        
        if (!$this->initConnection()) {
            $result['error'] = "Erreur de connexion à la base de données.";
            return $result;
        }
        
        try {
            if ($since_id > 0) {
                // Récupérer seulement les messages plus récents que since_id
                $sql = "SELECT id, content, created_at FROM messages WHERE id > ? ORDER BY created_at DESC";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$since_id]);
            } else {
                // Récupérer tous les messages
                $sql = "SELECT id, content, created_at FROM messages ORDER BY created_at DESC";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute();
            }
            
            $result['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result['success'] = true;
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des messages: " . $e->getMessage());
            $result['error'] = "Erreur lors de la récupération des messages.";
        } finally {
            $this->conn = null;
        }
        
        return $result;
    }
      /**
     * Récupération paginée des messages
     * @param int $page Numéro de page (commence à 1)
     * @param int $per_page Nombre de messages par page
     * @return array
     */    public function getMessagesPaginated(int $page = 1, int $per_page = 20): array {        // Validation et typage strict des paramètres pour Azure SQL
        $page = max(1, (int)$page);
        $per_page = (int)$per_page;
        $per_page = in_array($per_page, [20, 50, 100, 200]) ? $per_page : 20;
        
        $result = [
            'success' => false,
            'messages' => [],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_messages' => 0,
                'total_pages' => 0
            ],
            'error' => null
        ];
        
        if (!$this->initConnection()) {
            $result['error'] = "Erreur de connexion à la base de données.";
            return $result;
        }
        
        try {
            // Compter le nombre total de messages
            $count_sql = "SELECT COUNT(*) as total FROM messages";
            $count_stmt = $this->conn->prepare($count_sql);
            $count_stmt->execute();            $total_messages = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
              $total_pages = ceil($total_messages / $per_page);
            $offset = (int)(($page - 1) * $per_page); // Conversion explicite en entier pour Azure SQL            // Récupérer les messages pour la page demandée
            // Utiliser la syntaxe appropriée selon le type de base de données
            $driver = $this->getDatabaseDriver();
            $isSqlServer = $this->isSqlServer();
            
            // Debug pour Azure SQL
            error_log("Debug pagination - Driver: $driver, isSqlServer: " . ($isSqlServer ? 'true' : 'false'));
            error_log("Debug pagination - offset: $offset (" . gettype($offset) . "), per_page: $per_page (" . gettype($per_page) . ")");
            
            if ($isSqlServer || $driver === 'sqlsrv') {
                // Syntaxe SQL Server - IMPORTANT: convertir explicitement en entiers pour Azure SQL
                $sql = "SELECT id, content, created_at FROM messages ORDER BY created_at DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(1, (int)$offset, PDO::PARAM_INT);
                $stmt->bindValue(2, (int)$per_page, PDO::PARAM_INT);
                error_log("Utilisation syntaxe SQL Server avec offset=" . (int)$offset . ", per_page=" . (int)$per_page);
                $stmt->execute();
            } else {
                // Syntaxe MySQL/MariaDB/PostgreSQL
                $sql = "SELECT id, content, created_at FROM messages ORDER BY created_at DESC LIMIT ? OFFSET ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(1, (int)$per_page, PDO::PARAM_INT);
                $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
                error_log("Utilisation syntaxe MySQL/MariaDB avec per_page=" . (int)$per_page . ", offset=" . (int)$offset);
                $stmt->execute();
            }
            
            $result['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result['pagination'] = [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_messages' => $total_messages,
                'total_pages' => $total_pages
            ];
            $result['success'] = true;
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des messages paginés: " . $e->getMessage());
            $result['error'] = "Erreur lors de la récupération des messages.";
        } finally {
            $this->conn = null;
        }
        
        return $result;
    }
    
    /**
     * Compter le nombre total de messages
     */
    public function getTotalMessagesCount(): int {
        if (!$this->initConnection()) {
            return 0;
        }
        
        try {
            $sql = "SELECT COUNT(*) as total FROM messages";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['total'];
        } catch (PDOException $e) {
            error_log("Erreur lors du comptage des messages: " . $e->getMessage());
            return 0;
        } finally {
            $this->conn = null;
        }
    }
    
    /**
     * Génération du token CSRF
     */
    public function getCSRFToken(): string {
        return $this->security->generateCSRFToken();
    }
    
    /**
     * Vérification si l'utilisateur est bloqué
     */
    public function isBlocked(): bool {
        return !$this->security->checkRateLimit();
    }
    
    /**
     * Temps restant de blocage
     */
    public function getBlockTimeRemaining(): int {
        return $this->security->getBlockTimeRemaining();
    }
    
    /**
     * Obtention de l'IP client
     */
    private function getClientIP(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
      /**
     * Obtenir le driver de base de données
     */
    private function getDatabaseDriver(): string {
        try {
            if ($this->conn) {
                return $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            }
            return 'unknown';
        } catch (Exception $e) {
            error_log("Erreur getDatabaseDriver: " . $e->getMessage());
            return 'unknown';
        }
    }
    
    /**
     * Vérifier si la base de données est SQL Server
     */
    private function isSqlServer(): bool {
        $driver = $this->getDatabaseDriver();
        return $driver === 'sqlsrv' || $driver === 'mssql';
    }
    
    /**
     * Diagnostic de la base de données
     */
    public function getDatabaseInfo(): array {
        if (!$this->initConnection()) {
            return [
                'connected' => false,
                'error' => 'Impossible de se connecter à la base de données'
            ];
        }
        
        try {
            $driver = $this->getDatabaseDriver();
            $version = $this->conn->getAttribute(PDO::ATTR_SERVER_VERSION);
            $dsn = $this->conn->getAttribute(PDO::ATTR_CONNECTION_STATUS);
            
            return [
                'connected' => true,
                'driver' => $driver,
                'version' => $version,
                'is_sql_server' => $this->isSqlServer(),
                'connection_status' => $dsn
            ];
        } catch (Exception $e) {
            return [
                'connected' => true,
                'error' => 'Erreur lors de la récupération des informations : ' . $e->getMessage(),
                'driver' => $this->getDatabaseDriver(),
                'is_sql_server' => $this->isSqlServer()
            ];
        } finally {
            $this->conn = null;
        }
    }
}
?>
