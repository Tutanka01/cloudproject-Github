<?php
/**
 * Configuration de sécurité pour l'application de messagerie
 * Modifiez ces paramètres selon vos besoins de sécurité
 */

class SecurityConfig {
    // Paramètres de rate limiting
    const MAX_ATTEMPTS_PER_IP = 5;           // Nombre max de tentatives par IP
    const BLOCK_DURATION_SECONDS = 300;      // Durée de blocage en secondes (5 minutes)
    const RATE_LIMIT_WINDOW = 3600;          // Fenêtre de temps pour le rate limiting (1 heure)
    
    // Paramètres de contenu
    const MAX_CONTENT_LENGTH = 1000;         // Longueur maximale d'un message
    const MIN_CONTENT_LENGTH = 1;            // Longueur minimale d'un message
    
    // Paramètres de session
    const SESSION_LIFETIME = 3600;           // Durée de vie de la session (1 heure)
    const CSRF_TOKEN_LIFETIME = 7200;        // Durée de vie du token CSRF (2 heures)
    
    // Paramètres de logging
    const ENABLE_SECURITY_LOGGING = true;    // Activer les logs de sécurité
    const LOG_FAILED_ATTEMPTS = true;        // Logger les tentatives échouées
    const LOG_SUCCESSFUL_POSTS = true;       // Logger les posts réussis
    
    // Patterns de sécurité (regex)
    const SUSPICIOUS_PATTERNS = [
        '/\b(script|javascript|vbscript|onload|onerror|onclick|onmouseover|onfocus)\b/i',
        '/\b(select|insert|update|delete|drop|create|alter|exec|union|declare|cast)\b/i',
        '/<[^>]*>/i',                         // Tags HTML
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', // Caractères de contrôle
        '/\b(eval|exec|system|shell_exec|passthru|file_get_contents)\b/i',
        '/[\'\"]\s*(or|and)\s*[\'\"]/i',      // Patterns SQL injection basiques
        '/\bunion\s+select\b/i',              // Union SELECT
        '/\bdrop\s+table\b/i'                 // Drop table
    ];
    
    // Headers de sécurité
    const SECURITY_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;"
    ];
    
    // IPs autorisées pour l'administration (vide = toutes)
    const ADMIN_ALLOWED_IPS = [];
    
    // Activer le mode strict (plus de vérifications)
    const STRICT_MODE = true;
    
    /**
     * Applique les headers de sécurité
     */
    public static function applySecurityHeaders(): void {
        foreach (self::SECURITY_HEADERS as $header => $value) {
            header($header . ': ' . $value);
        }
    }
    
    /**
     * Configuration sécurisée de session
     */
    public static function configureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuration sécurisée de la session
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', self::SESSION_LIFETIME);
            ini_set('session.use_strict_mode', 1);
            
            session_start();
            
            // Régénération périodique de l'ID de session
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
}
?>
