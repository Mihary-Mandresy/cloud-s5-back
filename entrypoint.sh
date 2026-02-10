#!/bin/bash

set -e

echo "=== Démarrage du conteneur ==="

# Vérifier si vendor existe, sinon l'installer
if [ ! -d "/var/www/html/vendor" ]; then
    echo "Installation des dépendances Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
else
    echo "Mise à jour des dépendances Composer..."
    composer update --no-interaction --prefer-dist --optimize-autoloader --no-dev
fi

# Vérifier si .env existe, sinon copier l'exemple
if [ ! -f "/var/www/html/.env" ] && [ -f "/var/www/html/.env.example" ]; then
    echo "Création du fichier .env..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Générer la clé Laravel si nécessaire
echo "Génération de la clé Laravel..."
php artisan key:generate --force

# Fixer les permissions
echo "Configuration des permissions..."
chown -R www-data:www-data /var/www/html \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /var/www/html/vendor

chmod -R 775 /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /var/www/html/vendor

echo "=== Démarrage d'Apache ==="
exec "$@"