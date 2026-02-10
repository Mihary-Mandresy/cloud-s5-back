#!/bin/bash

set -e

echo "=== Démarrage du conteneur ==="

# Attendre que PostgreSQL soit prêt (important!)
echo "Attente du démarrage de PostgreSQL..."
sleep 10

# Vérifier si .env existe, sinon copier l'exemple
if [ ! -f "/var/www/html/.env" ]; then
    echo "Création du fichier .env à partir de .env.example..."
    if [ -f "/var/www/html/.env.example" ]; then
        cp /var/www/html/.env.example /var/www/html/.env
        echo "Fichier .env créé avec succès."
    else
        echo "ERREUR: Fichier .env.example non trouvé!"
        exit 1
    fi
fi

# Vérifier que le fichier .env est accessible
if [ ! -f "/var/www/html/.env" ]; then
    echo "ERREUR: Impossible de créer/fichier .env!"
    exit 1
fi

# Afficher quelques lignes du .env pour débogage
echo "Contenu partiel du .env:"
head -20 /var/www/html/.env

# Vérifier si APP_KEY existe et n'est pas vide
if grep -q "^APP_KEY=$" /var/www/html/.env || ! grep -q "^APP_KEY=" /var/www/html/.env; then
    echo "Génération de la clé Laravel..."
    # Vérifier que le fichier .env est accessible
    ls -la /var/www/html/.env
    # Générer la clé
    php artisan key:generate --force
else
    echo "Clé Laravel déjà configurée."
    echo "APP_KEY actuelle: $(grep '^APP_KEY=' /var/www/html/.env)"
fi

# Vérifier si vendor existe, sinon l'installer
if [ ! -d "/var/www/html/vendor" ]; then
    echo "Installation des dépendances Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
fi

# Exécuter les optimisations (sans cache:clear pour éviter l'erreur de DB)
echo "Exécution des optimisations Laravel..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "=== Démarrage d'Apache ==="
exec "$@"