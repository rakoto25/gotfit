# GotFit — déploiement Visio + Paiement

Cette version rend le parcours applicatif 100 % visio.

## Parcours payant retenu

1. Le coach publie une annonce.
2. Le client réserve un créneau.
3. Laravel crée/vérifie le PaymentIntent Stripe.
4. Après paiement réussi, Laravel crée ou resynchronise automatiquement la session LiveKit privée et ses participants.
5. Le client et le coach ouvrent la salle depuis leur espace GotFit.

Les séances créées directement dans l'espace **Visio** sont gratuites. Cela évite l'ancien cas où une séance autonome payante pouvait rester en `unpaid` sans parcours Stripe client complet.

## Variables Laravel obligatoires

Copier `.env.example` vers `.env` puis renseigner au minimum :

```dotenv
APP_URL=https://api.votre-domaine.tld
FRONTEND_URL=https://votre-domaine.tld

STRIPE_KEY=pk_...
STRIPE_SECRET=sk_...
STRIPE_WEBHOOK_SECRET=whsec_...

VISIO_PROVIDER=livekit
VISIO_SERVER_URL=wss://votre-instance-livekit
VISIO_API_KEY=...
VISIO_API_SECRET=...
VISIO_TOKEN_TTL=3600
```

Le secret Stripe et le secret LiveKit doivent rester uniquement côté Laravel.

## Mise à jour backend

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

La migration `2026_08_28_000003_make_annonces_visio_only.php` convertit les annonces existantes en visio et neutralise les prix des anciennes visios autonomes qui n'avaient pas de parcours Stripe dédié.

## Webhook Stripe

Configurer dans Stripe l'URL :

```text
https://api.votre-domaine.tld/api/payment/webhook
```

Au minimum, activer les événements :

- `payment_intent.succeeded`
- `payment_intent.payment_failed`

## Vérifications fonctionnelles recommandées

- créer une annonce coach et vérifier qu'elle est enregistrée avec `type_prestation=visio` et `is_online=1` ;
- réserver puis payer en mode test Stripe ;
- vérifier que la réservation passe à `payment_status=paid` et possède `visio_session_id` ;
- ouvrir la visio avec un compte coach puis un compte client ;
- tester caméra, microphone, audio distant et sortie de salle ;
- tester un retour Stripe avec redirection/3DS ; la page `/reservations` doit resynchroniser le `payment_intent` ;
- créer une visio autonome et vérifier que le client peut la réserver gratuitement.

## Remarque de validation

La syntaxe PHP et les routes Laravel ont été vérifiées dans l'environnement de correction. Le build Next.js complet nécessite les dépendances npm, qui ne sont pas incluses dans l'archive d'origine et n'ont pas pu être toutes téléchargées dans cet environnement. Exécuter `npm ci && npm run build` dans l'environnement de déploiement avant mise en production.
