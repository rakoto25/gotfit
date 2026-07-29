# Déploiement du correctif API 1.2.4

Cette version corrige l'erreur `SQLSTATE[42S02]` provoquée par l'absence de la
table `favorites`.

## Commandes

```bash
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

## Vérifications

```bash
php artisan migrate:status
php artisan route:list --path=api/favorites
```

Le statut de la migration `2026_07_29_000001_create_favorites_table` doit être
`Ran`. Tester ensuite, avec un utilisateur connecté :

1. la liste des favoris ;
2. l'ajout d'une annonce ;
3. un second ajout de la même annonce ;
4. la suppression du favori.
