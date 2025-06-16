# Configure the Azure Provider
terraform {
  required_version = ">= 1.0"
  required_providers {
    azurerm = {
      source  = "hashicorp/azurerm"
      version = "~> 3.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.1"
    }
  }
}

# Configure the Microsoft Azure Provider
provider "azurerm" {
  features {}
}

# Variables
variable "resource_group_name" {
  description = "Name of the resource group"
  type        = string
  default     = "rg-multizone-architecture"
}

variable "location" {
  description = "Azure location"
  type        = string
  default     = "France Central"
}

variable "environment" {
  description = "Environment name"
  type        = string
  default     = "dev"
  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "Environment must be dev, staging, or prod."
  }
}

variable "project_name" {
  description = "Project name for resource naming"
  type        = string
  default     = "multizone"
}

variable "admin_username" {
  description = "Admin username for VMs"
  type        = string
  default     = "azureuser"
}

variable "ssh_public_key" {
  description = "SSH public key for VM authentication"
  type        = string
}

variable "allowed_ssh_ips" {
  description = "List of IP addresses allowed for SSH access"
  type        = list(string)
  default     = []
  validation {
    condition     = length(var.allowed_ssh_ips) > 0
    error_message = "At least one IP address must be specified for SSH access."
  }
}

variable "sql_admin_username" {
  description = "SQL Server admin username"
  type        = string
  default     = "sqladmin"
}

variable "sql_admin_password" {
  description = "SQL Server admin password"
  type        = string
  sensitive   = true
  validation {
    condition     = length(var.sql_admin_password) >= 12
    error_message = "SQL admin password must be at least 12 characters long."
  }
}

variable "allowed_sql_ips" {
  description = "List of IP addresses allowed for SQL Server access"
  type        = list(string)
  default     = []
}

# Local values for consistent naming
locals {
  common_tags = {
    Environment = var.environment
    Project     = var.project_name
    ManagedBy   = "Terraform"
    CreatedDate = timestamp()
  }
}

# Random string for unique naming
resource "random_string" "unique" {
  length  = 8
  special = false
  upper   = false
}

# Resource Group
resource "azurerm_resource_group" "main" {
  name     = var.resource_group_name
  location = var.location
  tags     = local.common_tags
}

# Virtual Network avec DNS personnalisé
resource "azurerm_virtual_network" "main" {
  name                = "${var.project_name}-vnet-${var.environment}"
  address_space       = ["10.0.0.0/16"]
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  tags                = local.common_tags

  # Configuration DNS pour utiliser Azure DNS privé
  dns_servers = ["168.63.129.16"]  # Azure DNS privé pour résolution Private Endpoint
}

# Public Subnets
resource "azurerm_subnet" "public_subnet_1" {
  name                 = "${var.project_name}-snet-public-az1-${var.environment}"
  resource_group_name  = azurerm_resource_group.main.name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = ["10.0.1.0/24"]
}

resource "azurerm_subnet" "public_subnet_2" {
  name                 = "${var.project_name}-snet-public-az2-${var.environment}"
  resource_group_name  = azurerm_resource_group.main.name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = ["10.0.2.0/24"]
}

# Private Subnets
resource "azurerm_subnet" "private_subnet_1" {
  name                 = "${var.project_name}-snet-private-az1-${var.environment}"
  resource_group_name  = azurerm_resource_group.main.name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = ["10.0.3.0/24"]
}

resource "azurerm_subnet" "private_subnet_2" {
  name                 = "${var.project_name}-snet-private-az2-${var.environment}"
  resource_group_name  = azurerm_resource_group.main.name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = ["10.0.4.0/24"]
}

# Gateway Subnet for VPN Gateway
resource "azurerm_subnet" "gateway_subnet" {
  name                 = "GatewaySubnet"
  resource_group_name  = azurerm_resource_group.main.name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = ["10.0.5.0/27"]
}

# NAT Gateway Subnet
resource "azurerm_subnet" "nat_subnet" {
  name                 = "${var.project_name}-snet-nat-${var.environment}"
  resource_group_name  = azurerm_resource_group.main.name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = ["10.0.7.0/24"]
}

# Suppression du NAT Gateway - Le Load Balancer servira de NAT
# Plus besoin de NAT Gateway séparé selon le diagramme

# Network Security Groups
resource "azurerm_network_security_group" "public_nsg" {
  name                = "${var.project_name}-nsg-public-${var.environment}"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  tags                = local.common_tags

  security_rule {
    name                       = "SSH"
    priority                   = 1001
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "22"
    source_address_prefixes    = var.allowed_ssh_ips
    destination_address_prefix = "*"
  }

  security_rule {
    name                       = "HTTP"
    priority                   = 1002
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "80"
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }

  security_rule {
    name                       = "HTTPS"
    priority                   = 1003
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "443"
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }
}

# NSG pour les subnets privés - Accès SQL seulement
resource "azurerm_network_security_group" "private_nsg" {
  name                = "${var.project_name}-nsg-private-${var.environment}"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  tags                = local.common_tags

  # Autoriser le trafic depuis les subnets publics (VMSS) vers SQL
  security_rule {
    name                       = "AllowSQLFromPublicSubnets"
    priority                   = 1001
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "1433"
    source_address_prefixes    = ["10.0.1.0/24", "10.0.2.0/24"]
    destination_address_prefix = "*"
  }

  # Autoriser le trafic interne du VNet
  security_rule {
    name                       = "AllowVNetInbound"
    priority                   = 1002
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "*"
    source_port_range          = "*"
    destination_port_range     = "*"
    source_address_prefix      = "VirtualNetwork"
    destination_address_prefix = "*"
  }

  # Bloquer tout le reste
  security_rule {
    name                       = "DenyAllInbound"
    priority                   = 4096
    direction                  = "Inbound"
    access                     = "Deny"
    protocol                   = "*"
    source_port_range          = "*"
    destination_port_range     = "*"
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }
}

# Associate NSGs with Subnets
resource "azurerm_subnet_network_security_group_association" "public_1" {
  subnet_id                 = azurerm_subnet.public_subnet_1.id
  network_security_group_id = azurerm_network_security_group.public_nsg.id
}

resource "azurerm_subnet_network_security_group_association" "public_2" {
  subnet_id                 = azurerm_subnet.public_subnet_2.id
  network_security_group_id = azurerm_network_security_group.public_nsg.id
}

resource "azurerm_subnet_network_security_group_association" "private_1" {
  subnet_id                 = azurerm_subnet.private_subnet_1.id
  network_security_group_id = azurerm_network_security_group.private_nsg.id
}

resource "azurerm_subnet_network_security_group_association" "private_2" {
  subnet_id                 = azurerm_subnet.private_subnet_2.id
  network_security_group_id = azurerm_network_security_group.private_nsg.id
}

# Public IPs - Seulement 2 IPs publiques selon le diagramme
# 1. Load Balancer (qui sert aussi de NAT Gateway)
resource "azurerm_public_ip" "lb_pip" {
  name                = "${var.project_name}-pip-lb-${var.environment}"
  resource_group_name = azurerm_resource_group.main.name
  location            = azurerm_resource_group.main.location
  allocation_method   = "Static"
  sku                 = "Standard"
  tags                = local.common_tags
}

# 2. Bastion Host
resource "azurerm_public_ip" "bastion_pip" {
  name                = "${var.project_name}-pip-bastion-${var.environment}"
  resource_group_name = azurerm_resource_group.main.name
  location            = azurerm_resource_group.main.location
  allocation_method   = "Static"
  sku                 = "Standard"
  tags                = local.common_tags
}

# Network Interface pour Bastion Host
resource "azurerm_network_interface" "bastion_nic" {
  name                = "${var.project_name}-nic-bastion-${var.environment}"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  tags                = local.common_tags

  ip_configuration {
    name                          = "internal"
    subnet_id                     = azurerm_subnet.public_subnet_1.id
    private_ip_address_allocation = "Dynamic"
    public_ip_address_id          = azurerm_public_ip.bastion_pip.id
  }
}

# Bastion Host - VM classique avec IP publique
resource "azurerm_linux_virtual_machine" "bastion" {
  name                = "${var.project_name}-vm-bastion-${var.environment}"
  resource_group_name = azurerm_resource_group.main.name
  location            = azurerm_resource_group.main.location
  size                = "Standard_B1s"
  admin_username      = var.admin_username
  tags                = merge(local.common_tags, {
    Role = "Bastion"
  })

  disable_password_authentication = true

  network_interface_ids = [
    azurerm_network_interface.bastion_nic.id,
  ]

  admin_ssh_key {
    username   = var.admin_username
    public_key = var.ssh_public_key
  }

  os_disk {
    caching              = "ReadWrite"
    storage_account_type = "Standard_LRS"
  }

  source_image_reference {
    publisher = "Canonical"
    offer     = "0001-com-ubuntu-server-jammy"
    sku       = "22_04-lts-gen2"
    version   = "latest"
  }
}

# Virtual Machine Scale Set - Configuration fixe simple
resource "azurerm_linux_virtual_machine_scale_set" "main_vmss" {
  name                = "${var.project_name}-vmss-app-${var.environment}"
  resource_group_name = azurerm_resource_group.main.name
  location            = azurerm_resource_group.main.location
  sku                 = "Standard_F1s"  # Comme spécifié dans votre demande
  instances           = 2              # Fixe à 2 instances (pas d'auto-scaling)
  zones               = ["1", "2"]     # Répartition sur les deux zones
  admin_username      = var.admin_username
  tags                = merge(local.common_tags, {
    Role = "WebServer"
  })

  disable_password_authentication = true

  admin_ssh_key {
    username   = var.admin_username
    public_key = var.ssh_public_key
  }

  source_image_reference {
    publisher = "Canonical"
    offer     = "0001-com-ubuntu-server-jammy"
    sku       = "22_04-lts-gen2"
    version   = "latest"
  }

  os_disk {
    storage_account_type = "Standard_LRS"
    caching              = "ReadWrite"
  }

  # Network interface dans les subnets publics avec IPs privées seulement
  network_interface {
    name    = "nic-vmss-main"
    primary = true

    ip_configuration {
      name      = "internal"
      primary   = true
      subnet_id = azurerm_subnet.public_subnet_1.id
      load_balancer_backend_address_pool_ids = [azurerm_lb_backend_address_pool.backend_pool.id]
      # Pas d'IP publique - seulement IPs privées
    }
  }
  # Auto-scaling configuration
  # automatic_os_upgrade_policy {
  #   disable_automatic_rollback  = false
  #   enable_automatic_os_upgrade = true
  # }
  # Mode de mise à jour manuel - Configuration simple
  upgrade_mode = "Manual"

  # Pas d'extension de santé pour simplifier la configuration
  # Les VMs vont démarrer sans health check complexe
}

# Auto-scaling supprimé - Configuration fixe à 2 instances
# resource "azurerm_monitor_autoscale_setting" "vmss_autoscale" {
#   name                = "${var.project_name}-autoscale-${var.environment}"
#   resource_group_name = azurerm_resource_group.main.name
#   location            = azurerm_resource_group.main.location
#   target_resource_id  = azurerm_linux_virtual_machine_scale_set.main_vmss.id
#   tags                = local.common_tags
#
#   profile {
#     name = "defaultProfile"
#
#     capacity {
#       default = 2
#       minimum = 1
#       maximum = 2
#     }
#
#     rule {
#       metric_trigger {
#         metric_name        = "Percentage CPU"
#         metric_resource_id = azurerm_linux_virtual_machine_scale_set.main_vmss.id
#         time_grain         = "PT1M"
#         statistic          = "Average"
#         time_window        = "PT5M"
#         time_aggregation   = "Average"
#         operator           = "GreaterThan"
#         threshold          = 75
#         metric_namespace   = "Microsoft.Compute/virtualMachineScaleSets"
#       }
#
#       scale_action {
#         direction = "Increase"
#         type      = "ChangeCount"
#         value     = "1"
#         cooldown  = "PT5M"
#       }
#     }
#
#     rule {
#       metric_trigger {
#         metric_name        = "Percentage CPU"
#         metric_resource_id = azurerm_linux_virtual_machine_scale_set.main_vmss.id
#         time_grain         = "PT1M"
#         statistic          = "Average"
#         time_window        = "PT5M"
#         time_aggregation   = "Average"
#         operator           = "LessThan"
#         threshold          = 25
#         metric_namespace   = "Microsoft.Compute/virtualMachineScaleSets"
#       }
#
#       scale_action {
#         direction = "Decrease"
#         type      = "ChangeCount"
#         value     = "1"
#         cooldown  = "PT5M"
#       }
#     }
#   }
# }

# Azure SQL Server and Databases
# SÉCURITÉ RENFORCÉE - SQL Server complètement privé
# ATTENTION : Changement du nom pour forcer la recréation avec nouveau mot de passe
resource "azurerm_mssql_server" "sql_server" {
  name                         = "${var.project_name}-sqlserver-v3-${var.environment}-${random_string.unique.result}"
  resource_group_name          = azurerm_resource_group.main.name
  location                     = azurerm_resource_group.main.location
  version                      = "12.0"
  administrator_login          = var.sql_admin_username
  administrator_login_password = var.sql_admin_password
  tags                         = local.common_tags

  # INTERDIRE COMPLÈTEMENT L'ACCÈS PUBLIC DÈS LA CRÉATION
  public_network_access_enabled = false
    # Désactiver l'accès minimal TLS pour éviter tout accès externe
  minimum_tls_version = "1.2"
}

resource "azurerm_mssql_database" "db1" {
  name           = "${var.project_name}-db-app-az1-${var.environment}"
  server_id      = azurerm_mssql_server.sql_server.id
  collation      = "SQL_Latin1_General_CP1_CI_AS"
  license_type   = "LicenseIncluded"
  sku_name       = "S0"
  zone_redundant = false  # S0 ne supporte pas la redondance de zone
  tags           = local.common_tags

  threat_detection_policy {
    state                      = "Enabled"
    email_account_admins       = "Enabled"
    retention_days             = 30
  }
}



# Private DNS Zone pour SQL Server - Résolution DNS interne
resource "azurerm_private_dns_zone" "sql_dns_zone" {
  name                = "privatelink.database.windows.net"
  resource_group_name = azurerm_resource_group.main.name
  tags                = local.common_tags
}

# Liaison de la DNS Zone privée avec le VNet - CRITIQUE pour résolution DNS
resource "azurerm_private_dns_zone_virtual_network_link" "sql_dns_vnet_link" {
  name                  = "${var.project_name}-sql-dns-link-${var.environment}"
  resource_group_name   = azurerm_resource_group.main.name
  private_dns_zone_name = azurerm_private_dns_zone.sql_dns_zone.name
  virtual_network_id    = azurerm_virtual_network.main.id
  registration_enabled  = true  # CHANGÉ à true pour enregistrement automatique
  tags                  = local.common_tags
}

# Private Endpoint for SQL Server - ACCÈS PRIVÉ SEULEMENT
resource "azurerm_private_endpoint" "sql_pe" {
  name                = "${var.project_name}-pe-sql-${var.environment}"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  subnet_id           = azurerm_subnet.private_subnet_1.id
  tags                = local.common_tags

  private_service_connection {
    name                           = "sql-privateserviceconnection"
    private_connection_resource_id = azurerm_mssql_server.sql_server.id
    subresource_names              = ["sqlServer"]
    is_manual_connection           = false
  }

  private_dns_zone_group {
    name                 = "sql-dns-zone-group"
    private_dns_zone_ids = [azurerm_private_dns_zone.sql_dns_zone.id]
  }

  depends_on = [azurerm_private_dns_zone_virtual_network_link.sql_dns_vnet_link]
}

# SUPPRESSION COMPLÈTE des règles de firewall SQL
# Aucune règle de firewall = aucun accès Internet possible
# L'accès se fait UNIQUEMENT via Private Endpoint

# Load Balancer avec IP publique - Sert aussi de NAT Gateway
resource "azurerm_lb" "main" {
  name                = "${var.project_name}-lb-${var.environment}"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  sku                 = "Standard"
  tags                = merge(local.common_tags, {
    Role = "LoadBalancer-NAT"
  })

  frontend_ip_configuration {
    name                 = "public"
    public_ip_address_id = azurerm_public_ip.lb_pip.id
  }
}

resource "azurerm_lb_backend_address_pool" "backend_pool" {
  loadbalancer_id = azurerm_lb.main.id
  name            = "BackEndAddressPool"
}

resource "azurerm_lb_probe" "health_probe" {
  loadbalancer_id = azurerm_lb.main.id
  name            = "http-probe"
  port            = 80
  protocol        = "Http"
  request_path    = "/health.php"
}

resource "azurerm_lb_rule" "lb_rule" {
  loadbalancer_id                = azurerm_lb.main.id
  name                           = "HTTPRule"
  protocol                       = "Tcp"
  frontend_port                  = 80
  backend_port                   = 80
  frontend_ip_configuration_name = "public"
  backend_address_pool_ids       = [azurerm_lb_backend_address_pool.backend_pool.id]
  probe_id                       = azurerm_lb_probe.health_probe.id
  enable_floating_ip             = false
  idle_timeout_in_minutes        = 4
  disable_outbound_snat          = true  # Désactiver SNAT car on a une règle sortante
}

# Règle NAT sortante pour permettre aux VMs d'accéder à Internet
resource "azurerm_lb_outbound_rule" "outbound_rule" {
  name                    = "OutboundRule"
  loadbalancer_id         = azurerm_lb.main.id
  protocol                = "All"
  backend_address_pool_id = azurerm_lb_backend_address_pool.backend_pool.id

  frontend_ip_configuration {
    name = "public"
  }
}

# Suppression de l'Application Gateway - Non présent dans le diagramme

# VPN Gateway - Commenté temporairement pour respecter la limite de 3 IPs publiques
# Décommentez si nécessaire et commentez une autre ressource utilisant une IP publique
/*
resource "azurerm_virtual_network_gateway" "vpn" {
  name                = "${var.project_name}-vgw-${var.environment}"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  tags                = local.common_tags

  type     = "Vpn"
  vpn_type = "RouteBased"

  active_active = false
  enable_bgp    = false
  sku           = "VpnGw1"

  ip_configuration {
    name                          = "vnetGatewayConfig"
    public_ip_address_id          = azurerm_public_ip.vpn_pip.id
    private_ip_address_allocation = "Dynamic"
    subnet_id                     = azurerm_subnet.gateway_subnet.id
  }
}
*/

# Route Table pour les subnets publics - Utilisation du Load Balancer comme NAT
resource "azurerm_route_table" "public_rt" {
  name                = "${var.project_name}-rt-public-${var.environment}"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  tags                = local.common_tags

  # Route par défaut via Internet (le Load Balancer gérera le NAT)
  route {
    name           = "DefaultRoute"
    address_prefix = "0.0.0.0/0"
    next_hop_type  = "Internet"
  }
}

# Association des route tables aux subnets publics
resource "azurerm_subnet_route_table_association" "public_1" {
  subnet_id      = azurerm_subnet.public_subnet_1.id
  route_table_id = azurerm_route_table.public_rt.id
}

resource "azurerm_subnet_route_table_association" "public_2" {
  subnet_id      = azurerm_subnet.public_subnet_2.id
  route_table_id = azurerm_route_table.public_rt.id
}

# Outputs - Configuration selon le diagramme
output "load_balancer_public_ip" {
  description = "Public IP address of the Load Balancer - Point d'entrée principal"
  value       = azurerm_public_ip.lb_pip.ip_address
}

output "bastion_public_ip" {
  description = "Public IP address of the Bastion Host - Accès SSH"
  value       = azurerm_public_ip.bastion_pip.ip_address
}

output "bastion_private_ip" {
  description = "Private IP address of the Bastion Host"
  value       = azurerm_network_interface.bastion_nic.private_ip_address
}

output "sql_server_fqdn" {
  description = "FQDN PRIVÉ du SQL Server - Accessible seulement depuis le VNet"
  value       = azurerm_mssql_server.sql_server.fully_qualified_domain_name
}

output "sql_server_private_endpoint_ip" {
  description = "Adresse IP privée du SQL Server via Private Endpoint"
  value       = azurerm_private_endpoint.sql_pe.private_service_connection[0].private_ip_address
}

output "private_dns_zone_name" {
  description = "Nom de la zone DNS privée"
  value       = azurerm_private_dns_zone.sql_dns_zone.name
}

output "diagnostic_info" {
  description = "Informations de diagnostic pour la résolution DNS"
  value = {
    "DEPUIS_BASTION_TESTEZ" = "nslookup ${azurerm_mssql_server.sql_server.fully_qualified_domain_name}"
    "IP_PRIVEE_ATTENDUE"    = azurerm_private_endpoint.sql_pe.private_service_connection[0].private_ip_address
    "ZONE_DNS_PRIVEE"       = azurerm_private_dns_zone.sql_dns_zone.name
  }
}

output "sql_credentials" {
  description = "Credentials SQL pour test (ATTENTION: sensible en production)"
  value = {
    "server"   = azurerm_mssql_server.sql_server.fully_qualified_domain_name
    "username" = var.sql_admin_username
    "database" = azurerm_mssql_database.db1.name
  }
  sensitive = false  # Pour debugging seulement
}

output "vmss_name" {
  description = "Name of the Virtual Machine Scale Set"
  value       = azurerm_linux_virtual_machine_scale_set.main_vmss.name
}