# Synchronisation réservation payée → visio privée

Cette version crée automatiquement une séance LiveKit individuelle lorsqu'une réservation en ligne est payée.

## Flux

1. Stripe confirme `payment_intent.succeeded`.
2. La réservation passe à `payment_status=paid`.
3. `ReservationVisioService` crée ou synchronise une `visio_session` privée.
4. Le client payeur et l'intervenant sont ajoutés dans `visio_participants`.
5. La réservation reçoit `visio_session_id`.
6. `/api/visio/sessions/{id}/join` vérifie l'accès et retourne le token LiveKit.

## Commande de reprise des anciennes réservations

```bash
php artisan gotfit:sync-paid-visio
```

Pour une seule réservation :

```bash
php artisan gotfit:sync-paid-visio --reservation=13
```

## Variables requises

```env
VISIO_PROVIDER=livekit
VISIO_SERVER_URL=wss://votre-projet.livekit.cloud
VISIO_API_KEY=...
VISIO_API_SECRET=...
VISIO_TOKEN_TTL=3600
```

## Nouveaux endpoints

- `GET /api/my-payments`
- `GET /api/payments/me`
- `POST /api/visio/sessions/{id}/join`

Les séances individuelles sont privées et ne sont plus exposées dans la liste publique des visios.
