#!/bin/bash

# Script de déploiement rapide pour l'application Cloud Project
# Usage: ./deploy.sh [environment]

set -e

ENVIRONMENT=${1:-production}
PLAYBOOK="deploy.yml"
INVENTORY="inventory.yml"

echo "🚀 Démarrage du déploiement pour l'environnement: $ENVIRONMENT"
echo "=================================================="

# Vérification des prérequis
if ! command -v ansible-playbook &> /dev/null; then
    echo "❌ Ansible n'est pas installé. Installation..."
    if [[ "$OSTYPE" == "darwin"* ]]; then
        brew install ansible
    elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
        sudo apt update && sudo apt install -y ansible
    else
        echo "Veuillez installer Ansible manuellement"
        exit 1
    fi
fi

# Vérification de la connectivité
echo "🔍 Vérification de la connectivité vers les serveurs..."
ansible all -i $INVENTORY -m ping

if [ $? -eq 0 ]; then
    echo "✅ Connectivité OK"
else
    echo "❌ Problème de connectivité. Vérifiez votre configuration SSH."
    exit 1
fi

# Exécution du playbook
echo "📦 Lancement du déploiement..."
ansible-playbook -i $INVENTORY $PLAYBOOK -v

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Déploiement terminé avec succès !"
    echo ""
    echo "🌐 Votre application est maintenant accessible sur :"
    echo "   - http://IP_DE_VOTRE_VM:8080"
    echo ""
    echo "📋 Commandes utiles :"
    echo "   - Voir les logs: ansible all -i $INVENTORY -a 'docker logs cloudproject-php-web'"
    echo "   - Redémarrer: ansible all -i $INVENTORY -a 'docker-compose -f /opt/cloudproject/docker-compose.yml restart'"
    echo "   - Status: ansible all -i $INVENTORY -a 'docker ps'"
else
    echo "❌ Échec du déploiement. Consultez les logs ci-dessus."
    exit 1
fi
