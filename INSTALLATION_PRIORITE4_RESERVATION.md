# GotFit - Priorite 4 Reservation

Corrections ajoutees :

- Notifications email Laravel pour les evenements de reservation.
- Anti-conflit planning cote coach/intervenant.
- Endpoint planning commun client/coach/admin.
- Export calendrier ICS par reservation.
- Validation prestation apres seance seulement.
- Deadline de validation lancee quand le coach marque la seance comme realisee.
- Auto-validation horaire conservee et notification ajoutee.

## Routes principales

Routes connectees communes :

- `GET /api/planning`
- `GET /api/planning?from=2026-07-01&to=2026-07-31`
- `GET /api/reservation/{id}`
- `GET /api/reservation/{id}/calendar.ics`

Routes client :

- `PUT /api/annonces/{id}/reserve`
- `POST /api/create-payment-intent`
- `POST /api/reservation/{id}/confirm-prestation`
- `POST /api/reservation/{id}/dispute`

Routes intervenant :

- `GET /api/reservation/intervenant`
- `PUT /api/reservation/{id}/valider`
- `PUT /api/reservation/{id}/refuser`
- `PUT /api/reservation/{id}/terminer`

Routes admin :

- `GET /api/reservation/all`
- `POST /api/reservation/{id}/validate-prestation`
- `POST /api/reservation/{id}/transfer-to-coach`
- `POST /api/reservation/{id}/resolve-dispute`
- `POST /api/reservation/{id}/refund`

## Flux recommande

1. Le client reserve une annonce.
2. Le backend bloque le creneau si le client ou le coach est deja pris.
3. Le client paie.
4. Le webhook Stripe passe la reservation en `paid`.
5. Apres le creneau, le coach clique sur terminer.
6. La reservation passe en `realise` et `pending_validation`.
7. Le client confirme ou ouvre un litige.
8. Si le client ne fait rien, `gotfit:auto-validate-prestations` valide automatiquement apres le delai configure.
9. L'admin peut declencher le reversement coach apres validation.

## Configuration email

Verifier `.env` :

```env
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=contact@gotfit.tech
MAIL_FROM_NAME="GotFit"
FRONTEND_URL=https://gotfit.tech/webapp
STRIPE_VALIDATION_DELAY_HOURS=72
```

## Commandes apres deploiement

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

Verifier le cron Laravel :

```bash
* * * * * cd /var/www/gotfit && php artisan schedule:run >> /dev/null 2>&1
```

