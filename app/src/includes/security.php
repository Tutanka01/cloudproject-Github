<?php
require_once 'security_config.php';

class SecurityManager {
    private $max_attempts;
    private $block_duration;
    private $max_content_length;
    
    public function __construct() {
        // Utilisation de la configuration
        $this->max_attempts = SecurityConfig::MAX_ATTEMPTS_PER_IP;
        $this->block_duration = SecurityConfig::BLOCK_DURATION_SECONDS;
        $this->max_content_length = SecurityConfig::MAX_CONTENT_LENGTH;
        
        // Configuration de la session et headers de sécurité
        SecurityConfig::applySecurityHeaders();
        SecurityConfig::configureSession();
    }
    
    /**
     * Vérification du rate limiting basé sur l'IP
     */
    public function checkRateLimit(): bool {
        $ip = $this->getClientIP();
        $key = 'rate_limit_' . md5($ip);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'last_attempt' => time()];
        }
        
        $rate_data = $_SESSION[$key];
        
        // Reset du compteur si le délai de blocage est dépassé
        if (time() - $rate_data['last_attempt'] > $this->block_duration) {
            $_SESSION[$key] = ['count' => 0, 'last_attempt' => time()];
            return true;
        }
        
        // Vérification du nombre de tentatives
        if ($rate_data['count'] >= $this->max_attempts) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Incrémente le compteur de tentatives
     */
    public function incrementAttempts(): void {
        $ip = $this->getClientIP();
        $key = 'rate_limit_' . md5($ip);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'last_attempt' => time()];
        }
        
        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last_attempt'] = time();
    }
    
    /**
     * Génération et vérification du token CSRF
     */
    public function generateCSRFToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
      /**
     * Validation et nettoyage du contenu
     */
    public function validateAndSanitizeContent(string $content): array {
        $errors = [];
        
        // Vérification de la longueur
        if (empty(trim($content))) {
            $errors[] = "Le message ne peut pas être vide.";
        } elseif (strlen($content) > $this->max_content_length) {
            $errors[] = "Le message ne peut pas dépasser " . $this->max_content_length . " caractères.";
        } elseif (strlen($content) < SecurityConfig::MIN_CONTENT_LENGTH) {
            $errors[] = "Le message doit contenir au moins " . SecurityConfig::MIN_CONTENT_LENGTH . " caractère.";
        }
        
        // Nettoyage du contenu
        $content = trim($content);
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        
        // Vérification de patterns suspects (injection potentielle)
        foreach (SecurityConfig::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = "Le contenu contient des caractères ou mots non autorisés.";
                
                // Logging de sécurité
                if (SecurityConfig::LOG_FAILED_ATTEMPTS) {
                    error_log("SECURITY ALERT: Suspicious pattern detected from IP " . $this->getClientIP() . " - Pattern: $pattern - Content: " . substr($content, 0, 100));
                }
                break;
            }
        }
        
        return [
            'content' => $content,
            'errors' => $errors,
            'is_valid' => empty($errors)
        ];
    }
    
    /**
     * Obtention de l'IP client réelle
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
     * Obtention du temps restant de blocage
     */
    public function getBlockTimeRemaining(): int {
        $ip = $this->getClientIP();
        $key = 'rate_limit_' . md5($ip);
        
        if (!isset($_SESSION[$key])) {
            return 0;
        }
        
        $rate_data = $_SESSION[$key];
        if ($rate_data['count'] < $this->max_attempts) {
            return 0;
        }
        
        $remaining = $this->block_duration - (time() - $rate_data['last_attempt']);
        return max(0, $remaining);
    }
}
?>
