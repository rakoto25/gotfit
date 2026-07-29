# Déploiement VPS – GotFit API 1.3.0

Ces commandes sont prévues pour `/var/www/gotfit` après publication de la version sur la branche `main`.

## 1. Sauvegarde

```bash
cd /var/www/gotfit

BACKUP_DIR="/root/gotfit-pre-v1.3.0-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -a .env "$BACKUP_DIR/.env"
tar -czf "$BACKUP_DIR/storage.tar.gz" storage/app/public

mysqldump -u VOTRE_UTILISATEUR -p VOTRE_BASE \
  > "$BACKUP_DIR/database.sql"
```

Remplacer `VOTRE_UTILISATEUR` et `VOTRE_BASE` par les valeurs du VPS. Ne pas écrire le mot de passe dans la commande.

## 2. Vérifications Git

```bash
git status
git fetch origin main
git log --oneline --left-right HEAD...origin/main
```

Le répertoire doit être propre avant la mise à jour. Ne pas supprimer `.env` ni les fichiers uploadés.

## 3. Mise à jour

```bash
php artisan down --retry=60

git pull --ff-only origin main

COMPOSER_ALLOW_SUPERUSER=1 composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction

php artisan optimize:clear
php artisan migrate --force

test -L public/storage || php artisan storage:link

chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

## 4. Contrôles après déploiement

```bash
php artisan migrate:status
php artisan route:list --path=api/coach/credentials
php artisan route:list --path=api/fitness-assessment
php artisan route:list --path=api/users
tail -n 100 storage/logs/laravel.log
```

Vérifier ensuite depuis l’application :

1. inscription d’un coach avec SIRET ;
2. envoi d’un PDF de diplôme ;
3. affichage du justificatif dans l’administration ;
4. création d’une visio avec deux places ;
5. refus du troisième coaché ;
6. récupération puis soumission d’un bilan de forme.

## Point important concernant les anciennes visios

La migration plafonne à deux le champ `max_participants` des sessions existantes. Elle ne supprime aucun participant déjà payé. Si une ancienne session possède déjà plus de deux coachés, elle doit être traitée manuellement par l’administrateur afin de ne pas annuler silencieusement un paiement.

## Retour arrière

En production, privilégier la restauration de la sauvegarde SQL et du code précédent. Éviter un `migrate:rollback` aveugle, car il supprimerait les bilans déjà enregistrés.
