#!/bin/bash

set -e

echo "=== Démarrage du conteneur ==="

# Définir le répertoire Laravel
LARAVEL_PATH="/var/www/html"

# Attendre que PostgreSQL soit prêt
echo "Attente du démarrage de PostgreSQL..."
sleep 10

# Vérifier si .env existe, sinon copier l'exemple
if [ ! -f "$LARAVEL_PATH/.env" ]; then
    echo "Création du fichier .env à partir de .env.example..."
    if [ -f "$LARAVEL_PATH/.env.example" ]; then
        cp "$LARAVEL_PATH/.env.example" "$LARAVEL_PATH/.env"
        echo "Fichier .env créé avec succès."
    else
        echo "ERREUR: Fichier .env.example non trouvé!"
        echo "Contenu du répertoire $LARAVEL_PATH :"
        ls -la "$LARAVEL_PATH/"
        exit 1
    fi
fi

# Vérifier que le fichier .env est accessible
if [ ! -f "$LARAVEL_PATH/.env" ]; then
    echo "ERREUR: Impossible de créer/fichier .env!"
    exit 1
fi

# Vérifier si vendor existe, sinon l'installer
if [ ! -d "$LARAVEL_PATH/vendor" ]; then
    echo "Installation des dépendances Composer..."
    cd "$LARAVEL_PATH" && composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
else
    echo "Vérification des mises à jour Composer..."
    cd "$LARAVEL_PATH" && composer update --no-interaction --prefer-dist --optimize-autoloader --no-dev
fi

# Afficher quelques lignes du .env pour débogage
echo "Contenu partiel du .env:"
head -20 "$LARAVEL_PATH/.env"

# Vérifier si APP_KEY existe et n'est pas vide
if grep -q "^APP_KEY=$" "$LARAVEL_PATH/.env" || ! grep -q "^APP_KEY=" "$LARAVEL_PATH/.env"; then
    echo "Génération de la clé Laravel..."
    # Générer la clé
    cd "$LARAVEL_PATH" && php artisan key:generate --force
else
    echo "Clé Laravel déjà configurée."
    echo "APP_KEY actuelle: $(grep '^APP_KEY=' $LARAVEL_PATH/.env)"
fi

# Exécuter les optimisations (sans cache:clear pour éviter l'erreur de DB)
echo "Exécution des optimisations Laravel..."
cd "$LARAVEL_PATH" && php artisan config:clear
cd "$LARAVEL_PATH" && php artisan route:clear
cd "$LARAVEL_PATH" && php artisan view:clear

# Exécuter les migrations
echo "Exécution des migrations..."
cd "$LARAVEL_PATH" && php artisan migrate --force

# Exécuter les seeders (optionnel - décommentez si nécessaire)
echo "Exécution des seeders..."
cd "$LARAVEL_PATH" && php artisan db:seed --force

# Optimiser le cache après migrations
echo "Optimisation du cache..."
cd "$LARAVEL_PATH" && php artisan optimize
# === JUSQU'ICI ===

echo "=== Démarrage d'Apache ==="
exec "$@"
