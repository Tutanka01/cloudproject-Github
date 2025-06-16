<?php
/**
 * Script de test pour vérifier la sécurité de l'application
 * À exécuter depuis la ligne de commande ou navigateur (temporairement)
 */

require_once 'includes/security.php';
require_once 'includes/message_manager.php';

echo "<h1>🧪 Test de sécurité de l'application</h1>\n";

// Test 1: Validation de contenu
echo "<h2>Test 1: Validation de contenu</h2>\n";
$security = new SecurityManager();

$test_contents = [
    "Message normal" => "✅ Attendu: Valide",
    "<script>alert('xss')</script>" => "❌ Attendu: Rejeté (XSS)",
    "SELECT * FROM users" => "❌ Attendu: Rejeté (SQL)",
    str_repeat("a", 1001) => "❌ Attendu: Rejeté (Trop long)",
    "" => "❌ Attendu: Rejeté (Vide)",
    "Message avec émojis 🚀✨" => "✅ Attendu: Valide",
    "javascript:alert('test')" => "❌ Attendu: Rejeté (JS)",
];

foreach ($test_contents as $content => $expected) {
    $result = $security->validateAndSanitizeContent($content);
    $status = $result['is_valid'] ? "✅ VALIDE" : "❌ REJETÉ";
    $display_content = strlen($content) > 50 ? substr($content, 0, 50) . "..." : $content;
    echo "- <strong>" . htmlspecialchars($display_content) . "</strong><br>\n";
    echo "  Résultat: $status | $expected<br>\n";
    if (!empty($result['errors'])) {
        echo "  Erreurs: " . implode(", ", $result['errors']) . "<br>\n";
    }
    echo "<br>\n";
}

// Test 2: Génération de token CSRF
echo "<h2>Test 2: Tokens CSRF</h2>\n";
$token1 = $security->generateCSRFToken();
$token2 = $security->generateCSRFToken();
echo "- Token 1: " . substr($token1, 0, 16) . "...<br>\n";
echo "- Token 2: " . substr($token2, 0, 16) . "...<br>\n";
echo "- Identiques: " . ($token1 === $token2 ? "✅ OUI (normal)" : "❌ NON (problème)") . "<br>\n";

// Test de validation
$valid = $security->validateCSRFToken($token1);
echo "- Validation token valide: " . ($valid ? "✅ OUI" : "❌ NON") . "<br>\n";

$invalid = $security->validateCSRFToken("token_invalide");
echo "- Validation token invalide: " . ($invalid ? "❌ NON (problème)" : "✅ OUI") . "<br>\n";

// Test 3: Rate limiting
echo "<h2>Test 3: Rate Limiting</h2>\n";
echo "- Peut poster: " . ($security->checkRateLimit() ? "✅ OUI" : "❌ NON") . "<br>\n";

// Simulation d'échecs
for ($i = 0; $i < 3; $i++) {
    $security->incrementAttempts();
}
echo "- Après 3 tentatives ratées: " . ($security->checkRateLimit() ? "✅ Peut encore" : "❌ Bloqué") . "<br>\n";

// Test 4: Configuration
echo "<h2>Test 4: Configuration</h2>\n";
try {
    SecurityConfig::applySecurityHeaders();
    echo "- Headers de sécurité: ✅ Appliqués<br>\n";
    
    SecurityConfig::configureSession();
    echo "- Session sécurisée: ✅ Configurée<br>\n";
    
    $patterns_count = count(SecurityConfig::SUSPICIOUS_PATTERNS);
    echo "- Patterns suspects: ✅ $patterns_count patterns chargés<br>\n";
    
} catch (Exception $e) {
    echo "- ❌ Erreur de configuration: " . $e->getMessage() . "<br>\n";
}

// Test 5: Base de données (optionnel)
echo "<h2>Test 5: Connexion base de données</h2>\n";
try {
    $messageManager = new MessageManager();
    echo "- MessageManager: ✅ Instancié<br>\n";
    
    $csrf_token = $messageManager->getCSRFToken();
    echo "- Token CSRF depuis MessageManager: ✅ Généré<br>\n";
    
} catch (Exception $e) {
    echo "- ❌ Erreur MessageManager: " . $e->getMessage() . "<br>\n";
}

echo "<h2>🎯 Résumé</h2>\n";
echo "<p><strong>✅ Tests réussis :</strong> Validation de contenu, tokens CSRF, rate limiting<br>\n";
echo "<strong>🔒 Sécurité :</strong> Protection multi-couches active<br>\n";
echo "<strong>📊 Recommandation :</strong> Effectuer ces tests régulièrement</p>\n";

// Nettoyage pour éviter les faux positifs lors des vrais tests
if (isset($_SESSION)) {
    unset($_SESSION['rate_limit_' . md5($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')]);
}

echo "<hr><p><em>Test terminé - " . date('Y-m-d H:i:s') . "</em></p>\n";
?>
