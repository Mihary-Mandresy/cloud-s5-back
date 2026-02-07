#!/bin/bash
# Attendre que la base de données soit prête (optionnel mais recommandé)
# Décommentez et adaptez si vous utilisez PostgreSQL
# echo "Attente de la base de données..."
# while ! nc -z ${DB_HOST:-db} ${DB_PORT:-5432}; do
#   sleep 1
# done
# echo "Base de données prête !"

# Vérifier si Laravel est installé
if [ -f "artisan" ]; then
    echo "Laravel détecté, exécution des migrations et seeders..."
    
    # Option 1: Migrations + Seeders (décommentez celle que vous voulez)
    
    # A. Migrations seulement
    # php artisan migrate --force
    
    # B. Migrations + Seeders (tous les seeders)
    php artisan migrate --force --seed
    
    # C. Migrations + Seeders spécifiques
    # php artisan migrate --force
    # php artisan db:seed --class=UserSeeder --force
    # php artisan db:seed --class=ProductSeeder --force
    
    # D. Refresh de la base (ATTENTION: supprime toutes les données!)
    # php artisan migrate:refresh --seed --force
    
    echo "Migrations et seeders terminés !"
fi

# Démarrer Apache
echo "Démarrage d'Apache..."
exec apache2-foreground