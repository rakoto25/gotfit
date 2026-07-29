# Déploiement du correctif API 1.2.3

Après avoir remplacé les fichiers de l’API sur le VPS, exécutez ces commandes
depuis le dossier Laravel :

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

Vérifiez ensuite les routes :

```bash
php artisan route:list --path=api/profile
php artisan route:list --path=api/message
```

Les routes suivantes doivent apparaître :

- `POST api/profile`
- `POST api/profile/update`
- `PUT|PATCH api/profile`
- `PUT|PATCH api/message/{message_id}`
- `POST api/message/{message_id}/update`

Le `php artisan optimize:clear` est obligatoire : sans lui, Laravel peut
continuer à utiliser l’ancien cache dans lequel la route `PATCH` n’existe pas.
