# Intégration offres, packs et cashback

Cette version ajoute le parcours V1 suivant :

`Chat GotFit → offre → Stripe Checkout → retour GotFit → pack actif`

## Configuration

Variables nécessaires :

```dotenv
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
STRIPE_PACK_VALIDATION_DELAY_HOURS=48
FRONTEND_URL=https://gotfit.tech
```

Après déploiement :

```bash
php artisan migrate --force
php artisan config:cache
```

Le planificateur Laravel doit exécuter `schedule:run` chaque minute. La commande
`gotfit:auto-validate-prestations` valide automatiquement les séances arrivées à
échéance et retente les reversements Stripe échoués.

## Parcours frontend

Toutes les routes ci-dessous nécessitent un token Sanctum.

### Créer la carte d’offre dans le chat

`POST /api/conversations/{conversation}/offers`

```json
{
  "title": "Coaching personnalisé",
  "session_count": 5,
  "amount": 135,
  "description": "Programme sur mesure",
  "validity_days": 7
}
```

Seul le coach de la conversation peut appeler cette route. La réponse contient
à la fois `offer` et le `message` de type `offer`. Les routes existantes de
messagerie renvoient désormais `message.offer`, puis `offer.pack.sessions` après
le paiement. Le mobile ou la webapp peut donc rendre directement la carte dans
le fil sans écran Offres séparé.

### Ouvrir Stripe Checkout

`POST /api/offers/{offer}/checkout`

Seul le client destinataire peut appeler cette route. Rediriger ensuite vers
`checkout_url`. Une offre payée renvoie `already_paid: true` et ne crée jamais
un second paiement.

Le statut payé et le pack sont créés uniquement par webhook Stripe, jamais sur
la seule base du retour frontend.

### Suivre le pack et les séances

- `GET /api/packs`
- `GET /api/packs/{pack}`
- `POST /api/pack-sessions/{session}/complete` côté coach
- `POST /api/pack-sessions/{session}/schedule` avec `scheduled_at` côté coach
- `POST /api/pack-sessions/{session}/validate` côté client
- `POST /api/pack-sessions/{session}/dispute` avec `{ "reason": "..." }`
- `POST /api/pack-sessions/{session}/cancel` pour une annulation client ou coach
- `POST /api/pack-sessions/{session}/no-show` côté coach après le délai de grâce
- `POST /api/admin/pack-sessions/{session}/resolve` avec `validate` ou `cancel`

Statuts d’une séance : `pending`, `awaiting_client_confirmation`, `validated`,
`paid`, `disputed`, `cancelled`. Une séance validée déclenche au maximum un
Stripe Transfer. Le montant net coach est réparti au centime près entre les
séances.

Les règles d’annulation tardive et de no-show sont paramétrables depuis les
business settings : délai de 24 heures, délai de grâce de 15 minutes et choix
de consommer ou non la séance. Une annulation coach ou une annulation client
faite suffisamment tôt remet la séance à disposition. L’historique est conservé
dans `pack_session_cancellations`.

### Cashback

`GET /api/wallet` renvoie le solde et l’historique paginé. Le paiement confirmé
crédite 1 % du montant réellement payé sur Stripe. Pour utiliser la cagnotte,
envoyer `wallet_amount` à `POST /api/offers/{offer}/checkout`. Le solde réservé
est libéré si la création Stripe échoue, si la session Checkout expire ou si
l’offre est annulée. Un remboursement total restitue aussi la part payée avec
la cagnotte. Tous les débits, libérations, crédits et annulations restent
traçables dans `wallet_transactions`.

## Webhooks Stripe

Configurer l’URL publique `POST /api/payment/webhook` avec au minimum :

- `checkout.session.completed`
- `checkout.session.expired`
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `charge.refunded`
- `charge.dispute.created`
- `charge.dispute.closed`
- `account.updated`
- `transfer.created`
- `transfer.updated`

Chaque événement est historisé dans `stripe_events`. Les créations de pack, le
cashback et les transferts par séance utilisent des clés et contraintes
d’idempotence afin de résister aux doubles clics et aux webhooks répétés.
