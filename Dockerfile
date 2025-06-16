FROM php:8.1-apache

# Installation des dépendances de base
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libzip-dev \
        gnupg2 \
        curl \
        ca-certificates && \
    # Téléchargement et ajout de la clé publique Microsoft au bon format
    curl -sSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor > /usr/share/keyrings/microsoft-prod.gpg && \
    # Ajout du dépôt Microsoft avec la clé correctement référencée
    echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
        > /etc/apt/sources.list.d/mssql-release.list && \
    apt-get update && \
    # Installation du driver ODBC pour SQL Server et unixODBC
    ACCEPT_EULA=Y apt-get install -y --no-install-recommends \
        msodbcsql18 \
        unixodbc-dev && \
    # Installation des extensions PHP de base
    docker-php-ext-install pdo && \
    # Installation de l'extension pdo_sqlsrv
    pecl install sqlsrv pdo_sqlsrv && \
    docker-php-ext-enable sqlsrv pdo_sqlsrv && \
    # Nettoyage
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

COPY ./app/src /var/www/html