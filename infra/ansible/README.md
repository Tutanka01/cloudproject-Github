# Ansible Cloud Project Deployment

Ce dossier contient tous les fichiers Ansible nécessaires pour déployer automatiquement votre application PHP sur des VMs Ubuntu dans Azure.

## 📁 Structure des fichiers

```
ansible/
├── ansible.cfg          # Configuration Ansible
├── inventory.yml        # Inventaire des serveurs (IPs, credentials)
├── deploy.yml          # Playbook principal de déploiement
├── deploy.sh           # Script bash pour un déploiement simplifié
├── requirements.txt    # Dépendances Python/Ansible
└── README.md          # Cette documentation
```

## 🚀 Installation et Configuration

### 1. Installation d'Ansible

**Sur Ubuntu/Debian :**
```bash
sudo apt update
sudo apt install -y ansible python3-pip
pip3 install -r requirements.txt
```

**Sur Windows (WSL) :**
```bash
sudo apt update && sudo apt install -y ansible
pip install -r requirements.txt
```

**Sur macOS :**
```bash
brew install ansible
pip install -r requirements.txt
```

### 2. Configuration SSH

Assurez-vous d'avoir votre clé SSH privée pour les VMs Azure :

```bash
# Copier votre clé SSH (générée lors de la création des VMs)
cp /path/to/your/azure_vm_key ~/.ssh/
chmod 600 ~/.ssh/azure_vm_key
```

### 3. Configuration de l'inventaire

Modifiez le fichier `inventory.yml` avec les vraies IPs de vos VMs :

```yaml
webservers:
  hosts:
    web-vm-1:
      ansible_host: VOTRE_IP_VM_1  # Remplacez par l'IP réelle
    web-vm-2:
      ansible_host: VOTRE_IP_VM_2  # Remplacez par l'IP réelle
```

## 🎯 Déploiement

### Méthode 1: Script automatisé (Recommandé)

```bash
# Rendre le script exécutable
chmod +x deploy.sh

# Lancer le déploiement
./deploy.sh
```

### Méthode 2: Commandes Ansible manuelles

```bash
# Test de connectivité
ansible all -i inventory.yml -m ping

# Déploiement complet
ansible-playbook -i inventory.yml deploy.yml -v
```

## 🔧 Ce que fait le playbook

1. **Mise à jour système** : Met à jour Ubuntu et tous les paquets
2. **Installation Docker** : Installe Docker et Docker Compose
3. **Déploiement application** : 
   - Crée la structure de fichiers
   - Copie le Dockerfile et docker-compose.yml
   - Déploie le code PHP simplifié
   - Build et lance les conteneurs
4. **Vérifications** : Teste que l'application est accessible

## 🌐 Accès à l'application

Après déploiement réussi, votre application sera accessible sur :
- `http://IP_DE_VOS_VMS:8080`

## 📋 Commandes de maintenance

```bash
# Vérifier le statut des conteneurs
ansible all -i inventory.yml -a "docker ps"

# Voir les logs de l'application
ansible all -i inventory.yml -a "docker logs cloudproject-php-web"

# Redémarrer l'application
ansible all -i inventory.yml -a "cd /opt/cloudproject && docker-compose restart"

# Mise à jour de l'application
ansible-playbook -i inventory.yml deploy.yml --tags "deploy"

# Arrêter l'application
ansible all -i inventory.yml -a "cd /opt/cloudproject && docker-compose down"
```

## 🛠️ Personnalisation

### Variables importantes dans `inventory.yml` :

- `app_port` : Port d'exposition de l'application (défaut: 8080)
- `mysql_host`, `mysql_user`, `mysql_password` : Variables d'environnement pour simulation DB

### Pour utiliser votre propre image Docker :

1. Modifiez la variable `app_image` dans `deploy.yml`
2. Ou buildez votre propre image en modifiant le Dockerfile dans le playbook

## 🐛 Dépannage

### Problème de connexion SSH
```bash
# Test de connexion manuelle
ssh -i ~/.ssh/azure_vm_key azureuser@IP_DE_VOTRE_VM

# Vérifier la configuration
ansible-config dump --only-changed
```

### Problème de permissions Docker
```bash
# Se reconnecter après installation Docker
ansible all -i inventory.yml -a "newgrp docker"
```

### Application non accessible
```bash
# Vérifier les ports
ansible all -i inventory.yml -a "netstat -tlnp | grep 8080"

# Vérifier les conteneurs
ansible all -i inventory.yml -a "docker ps -a"
```

## 📈 Améliorations possibles

- Ajouter une vraie base de données (MySQL/PostgreSQL)
- Intégrer un reverse proxy (Nginx)
- Ajouter un monitoring (Prometheus/Grafana)
- Configurer HTTPS avec Let's Encrypt
- Ajouter des backups automatiques
