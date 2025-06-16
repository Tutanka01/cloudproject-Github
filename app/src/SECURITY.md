# 🔐 Documentation Sécurité - Messagerie Sécurisée

## Vue d'ensemble des protections implémentées

Cette application de messagerie implémente plusieurs couches de sécurité pour protéger contre les attaques courantes.

## 🛡️ Protections implementées

### 1. Protection CSRF (Cross-Site Request Forgery)
- **Token CSRF** : Chaque formulaire contient un token unique
- **Validation stricte** : Vérification du token à chaque soumission
- **Régénération** : Nouveaux tokens à chaque session

### 2. Protection contre l'injection SQL
- **Requêtes préparées** : Utilisation exclusive de PDO avec paramètres liés
- **Validation d'entrée** : Nettoyage strict de tous les inputs
- **Échappement** : htmlspecialchars sur toutes les sorties

### 3. Protection XSS (Cross-Site Scripting)
- **Échappement HTML** : Tous les contenus utilisateur sont échappés
- **Patterns suspects** : Détection de scripts malveillants
- **Headers CSP** : Content Security Policy restrictive

### 4. Rate Limiting
- **Limitation par IP** : Maximum 5 tentatives par 5 minutes
- **Blocage temporaire** : Suspension automatique des IPs abusives
- **Compteurs par session** : Suivi des tentatives par utilisateur

### 5. Validation de contenu
- **Longueur limitée** : Messages max 1000 caractères
- **Patterns interdits** : Détection de mots-clés suspects
- **Caractères de contrôle** : Filtrage des caractères dangereux

### 6. Headers de sécurité
```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'
```

### 7. Configuration de session sécurisée
- **HttpOnly cookies** : Protection contre le vol de session JS
- **Secure cookies** : Transmission uniquement en HTTPS
- **SameSite Strict** : Protection CSRF supplémentaire
- **Régénération d'ID** : Prévention du session fixation

## 📊 Logs de sécurité

### Événements loggés
- Tentatives de connexion échouées
- Patterns suspects détectés
- Blocages d'IP automatiques
- Messages ajoutés avec succès

### Localisation des logs
- PHP error log standard
- Serveur web logs
- Application logs custom

## ⚙️ Configuration

### Paramètres modifiables dans `security_config.php`
```php
MAX_ATTEMPTS_PER_IP = 5         // Tentatives max par IP
BLOCK_DURATION_SECONDS = 300    // Durée de blocage (secondes)
MAX_CONTENT_LENGTH = 1000       // Taille max des messages
```

## 🚨 Monitoring recommandé

### Alertes à mettre en place
1. **Trop de tentatives échouées** d'une même IP
2. **Patterns d'attaque détectés** dans les logs
3. **Pic de trafic inhabituel**
4. **Erreurs de base de données répétées**

### Métriques à surveiller
- Nombre de tentatives par minute
- Taux d'erreur des requêtes
- Utilisation CPU/mémoire
- Taille de la base de données

## 🔧 Maintenance

### Actions recommandées
1. **Mise à jour régulière** des patterns suspects
2. **Rotation des logs** pour éviter l'accumulation
3. **Monitoring des performances** de la base de données
4. **Tests de pénétration** périodiques

### Commandes utiles
```bash
# Vérifier les logs d'erreur PHP
tail -f /var/log/php_errors.log

# Vérifier les tentatives suspectes
grep "SECURITY ALERT" /var/log/php_errors.log

# Analyser le trafic
tail -f /var/log/nginx/access.log | grep "POST /index.php"
```

## 🎯 Tests de sécurité

### Tests à effectuer régulièrement
1. **Injection SQL** : Tentatives d'injection dans le formulaire
2. **XSS** : Scripts malveillants dans les messages
3. **CSRF** : Soumissions sans token valide
4. **Rate limiting** : Soumissions en masse
5. **Caractères spéciaux** : Unicode, émojis, caractères de contrôle

### Outils recommandés
- OWASP ZAP
- Burp Suite Community
- SQLMap
- Nmap

## 📞 Contact

En cas de découverte de vulnérabilité, contactez immédiatement l'équipe de sécurité.

---
*Documentation générée automatiquement - Dernière mise à jour : 2025*
