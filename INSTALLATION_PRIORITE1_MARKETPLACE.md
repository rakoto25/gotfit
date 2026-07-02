# Gotfit - Priorite 1 Marketplace Stripe Connect

Ce patch ajoute le flux marketplace sécurisé :

- paiement client sur le compte plateforme Gotfit ;
- validation de prestation par le client ou par l'admin ;
- validation automatique apres delai sans litige ;
- blocage du reversement en cas de litige ;
- reversement coach via Stripe Connect apres validation ;
- commission Gotfit et frais client conserves cote plateforme ;
- remboursement total ou partiel par l'admin.

## Variables .env

```env
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
STRIPE_CONNECT_COUNTRY=FR
STRIPE_VALIDATION_DELAY_HOURS=72
```

Important : le webhook refuse maintenant les appels sans signature Stripe valide.

## Migration

```bash
php artisan migrate
```

## Scheduler

La validation automatique utilise la commande :

```bash
php artisan gotfit:auto-validate-prestations
```

En production, ajouter le cron Laravel :

```bash
* * * * * cd /var/www/gotfit && php artisan schedule:run >> /dev/null 2>&1
```

## Routes principales

Client :

```txt
POST /api/create-payment-intent
GET  /api/payment/status/{payment_intent_id}
POST /api/reservation/{id}/confirm-prestation
POST /api/reservation/{id}/dispute
```

Admin :

```txt
POST /api/reservation/{id}/validate-prestation
POST /api/reservation/{id}/transfer-to-coach
POST /api/reservation/{id}/resolve-dispute
POST /api/reservation/{id}/refund
```

Intervenant :

```txt
POST /api/stripe/connect/onboarding
GET  /api/stripe/connect/status
GET  /api/intervenant/commission
```

## Cycle recommande

1. Le client reserve une annonce.
2. Le client paie avec `create-payment-intent`.
3. Stripe appelle `/api/payment/webhook`.
4. La reservation passe en `payment_status=paid`, `prestation_status=paid`.
5. Le client confirme la prestation, ou l'admin valide, ou la commande auto valide apres delai.
6. L'admin declenche `transfer-to-coach`.
7. En cas de probleme, le client cree un litige et le reversement reste bloque.

## Notes de securite

- Le client ne peut valider ou contester que ses propres reservations.
- Les routes de remboursement, validation admin et reversement restent sous middleware `is_admin`.
- Le reversement Stripe utilise une cle d'idempotence par reservation pour eviter un double transfert.
- Le transfert utilise `source_transaction` quand le `stripe_charge_id` est disponible.
- Le webhook verifie obligatoirement la signature `Stripe-Signature`.
