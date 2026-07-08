# PRIORITÉ 6 – COMMUNICATION

## Objectif

Cette correction ajoute la communication administrateur vers tous les coachs/intervenants.

Un administrateur peut maintenant envoyer le même message à tous les utilisateurs ayant le rôle :

- `intervenant`
- `coach` si ce rôle existe plus tard

Le système crée un message individuel dans la messagerie existante pour chaque coach. Chaque coach retrouve donc le message dans son espace conversation habituel.

## Fichiers modifiés / ajoutés

- `app/Http/Controllers/AdminMessageController.php`
- `app/Models/Message.php`
- `routes/api.php`
- `database/migrations/2026_07_08_000001_add_admin_communication_fields_to_messages_table.php`
- `INSTALLATION_PRIORITE6_COMMUNICATION.md`

## Nouvelles routes API

Toutes ces routes sont protégées par :

```php
middleware(['auth:sanctum', 'is_admin'])
```

### Lister les coachs destinataires

```http
GET /api/admin/messages/coaches
GET /api/messages/coaches
```

Option :

```http
GET /api/admin/messages/coaches?only_approved=1
```

### Envoyer un message à tous les coachs

```http
POST /api/admin/messages/broadcast-coaches
POST /api/messages/broadcast-coaches
POST /api/admin/messages/send-to-coaches
POST /api/messages/send-to-coaches
```

Body JSON :

```json
{
  "subject": "Information importante GotFit",
  "message": "Bonjour, voici un message administrateur envoyé à tous les coachs.",
  "only_approved": false
}
```

### Compatibilité avec l'ancienne route POST /messages

La route existante peut aussi envoyer à tous les coachs avec un flag :

```http
POST /api/admin/messages
POST /api/messages
```

Body JSON :

```json
{
  "send_to_all_coaches": true,
  "subject": "Information importante GotFit",
  "message": "Bonjour à tous les coachs."
}
```

Flags acceptés :

- `send_to_all_coaches: true`
- `broadcast_to_coaches: true`
- `to_all_coaches: true`
- `target: "coaches"`
- `target: "intervenants"`

## Tester sans envoyer

Tu peux tester les destinataires sans créer de message :

```json
{
  "dry_run": true,
  "subject": "Test",
  "message": "Simulation uniquement."
}
```

## Commandes à lancer après déploiement

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

## Exemple curl

Remplace `TON_TOKEN_ADMIN` par le token Sanctum d'un administrateur.

```bash
curl -X POST https://api.gotfit.tech/api/admin/messages/broadcast-coaches \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TON_TOKEN_ADMIN" \
  -d '{
    "subject": "Information GotFit",
    "message": "Bonjour, ceci est un message administrateur envoyé à tous les coachs.",
    "only_approved": false
  }'
```

## Réponse attendue

```json
{
  "status": 201,
  "message": "Message envoyé à tous les coachs avec succès.",
  "broadcast_group": "uuid-de-la-diffusion",
  "target_role": "intervenant",
  "recipients_count": 12,
  "sent_count": 12,
  "failed_count": 0
}
```

## Correction sécurité incluse

Les anciennes routes debug :

- `/api/debug-auth-header`
- `/api/debug-sanctum-user`

sont maintenant chargées uniquement si `APP_DEBUG=true`. Elles ne seront donc plus publiques en production si `APP_DEBUG=false`.
