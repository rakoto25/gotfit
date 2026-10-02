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
- `POST /api/pack-sessions/{session}/validate` côté client
- `POST /api/pack-sessions/{session}/dispute` avec `{ "reason": "..." }`
- `POST /api/admin/pack-sessions/{session}/resolve` avec `validate` ou `cancel`

Statuts d’une séance : `pending`, `awaiting_client_confirmation`, `validated`,
`paid`, `disputed`, `cancelled`. Une séance validée déclenche au maximum un
Stripe Transfer. Le montant net coach est réparti au centime près entre les
séances.

### Cashback

`GET /api/wallet` renvoie le solde et l’historique paginé. Le paiement confirmé
crédite 1 % du montant du pack. Un remboursement total ou partiel recalcule le
cashback éligible et ajoute un mouvement d’annulation traçable.

## Webhooks Stripe

Configurer l’URL publique `POST /api/payment/webhook` avec au minimum :

- `checkout.session.completed`
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
