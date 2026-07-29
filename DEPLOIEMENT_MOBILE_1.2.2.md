# Gotfit API — déploiement du backend Mobile 1.2.2

Cette version ajoute le paiement obligatoire avant confirmation, le changement
de créneau avec historique, les notifications Expo et la synchronisation des
messages modifiés ou supprimés.

## 1. Sauvegarder et installer

Effectuer d'abord une sauvegarde de la base et du dossier `storage`.

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

## 2. Variables d'environnement

Vérifier au minimum les valeurs suivantes dans le `.env` de production :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.votre-domaine.tld

QUEUE_CONNECTION=database

STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

MAIL_MAILER=smtp
MAIL_HOST=smtp.exemple.tld
MAIL_PORT=587
MAIL_USERNAME=xxx
MAIL_PASSWORD=xxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@votre-domaine.tld
MAIL_FROM_NAME=Gotfit
```

Ne jamais placer les vraies clés dans Git ou dans une archive partagée.

## 3. Worker de notifications

Les notifications Expo sont envoyées par la file Laravel. Le worker doit
rester actif en production :

```bash
php artisan queue:work --tries=3 --backoff=15
```

Utiliser Supervisor, systemd ou le gestionnaire de processus de l'hébergeur
pour relancer automatiquement le worker.

## 4. Webhook Stripe

Configurer dans Stripe l'URL :

```text
https://api.votre-domaine.tld/api/payment/webhook
```

Événements utiles :

- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `charge.dispute.created`
- `charge.dispute.closed`

Le webhook vérifie sa signature, le montant et la devise avant de marquer la
réservation payée. Le coach ne peut confirmer qu'après ce traitement.

## 5. Nouvelles routes mobiles

```text
PUT    /api/reservation/{id}/reschedule
POST   /api/reservation/{id}/reschedule
PATCH  /api/reservation/{id}
PUT    /api/reservation/{id}

POST   /api/push-tokens
DELETE /api/push-tokens

PATCH  /api/message/{id}
DELETE /api/message/{id}
```

Toutes ces routes nécessitent un jeton Sanctum.

## 6. Vérification après déploiement

```bash
php artisan migrate:status
php artisan route:list --path=reservation
php artisan route:list --path=push-tokens
php artisan route:list --path=message
```

Scénario conseillé :

1. créer une réservation ;
2. vérifier qu'elle est `pending` et non payée ;
3. payer avec Stripe en mode test ;
4. vérifier que le webhook la place à `paid` ;
5. confirmer côté coach ;
6. changer le créneau côté client ;
7. vérifier l'historique et la notification du coach ;
8. modifier puis supprimer un message depuis le mobile.
