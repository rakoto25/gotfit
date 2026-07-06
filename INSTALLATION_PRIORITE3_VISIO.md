# GotFit - Priorite 3 Visio

Ce module ajoute la base backend pour les seances visio de groupe :

- 1 coach/intervenant proprietaire de la seance.
- Minimum 2 participants clients valides/payes avant demarrage.
- Acces navigateur et mobile via token visio signe par le backend.
- Structure compatible avec LiveKit, Daily, Twilio ou autre fournisseur WebRTC.

## Fichiers ajoutes

- `database/migrations/2026_07_06_000001_create_visio_sessions_table.php`
- `database/migrations/2026_07_06_000002_create_visio_participants_table.php`
- `app/Models/VisioSession.php`
- `app/Models/VisioParticipant.php`
- `app/Http/Controllers/VisioSessionController.php`

## Fichiers modifies

- `routes/api.php`
- `app/Models/User.php`
- `config/services.php`
- `.env.example`

## Variables .env

```env
VISIO_PROVIDER=gotfit
VISIO_SERVER_URL=
VISIO_SECRET=
VISIO_TOKEN_TTL=3600
```

Pour une vraie integration fournisseur, mettre par exemple :

```env
VISIO_PROVIDER=livekit
VISIO_SERVER_URL=wss://votre-livekit.example.com
VISIO_SECRET=une_cle_secrete_longue
```

## Installation VPS

```bash
cd /var/www/gotfit
php artisan migrate
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

## Endpoints principaux

### Public

```txt
GET /api/visio/sessions
GET /api/visio/sessions/{id}
```

### Connecte

```txt
GET  /api/visio/my-sessions
POST /api/visio/sessions
PUT  /api/visio/sessions/{id}
POST /api/visio/sessions/{id}/reserve
POST /api/visio/sessions/{id}/start
POST /api/visio/sessions/{id}/join
POST /api/visio/sessions/{id}/leave
POST /api/visio/sessions/{id}/end
POST /api/visio/sessions/{id}/cancel
GET  /api/visio/sessions/{id}/participants
POST /api/visio/sessions/{id}/participants/{participantId}/paid
```

## Regle metier importante

Une seance ne peut pas demarrer tant qu'elle n'a pas au minimum 2 participants clients avec :

```txt
role = participant
status = paid / joined / left
payment_status = paid
```

Le statut de la seance passe automatiquement de `open` a `confirmed` quand le minimum est atteint.

## Test rapide

1. Se connecter avec un compte coach/intervenant.
2. Creer une seance :

```bash
curl -X POST https://api.gotfit.tech/api/visio/sessions \
  -H "Authorization: Bearer TOKEN_COACH" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Pilates Reformer en visio",
    "description": "Cours collectif GotFit",
    "start_at": "2026-07-10 10:00:00",
    "duration_minutes": 60,
    "min_participants": 2,
    "max_participants": 8,
    "price": 25,
    "currency": "EUR"
  }'
```

3. Deux clients reservent :

```bash
curl -X POST https://api.gotfit.tech/api/visio/sessions/1/reserve \
  -H "Authorization: Bearer TOKEN_CLIENT" \
  -H "Accept: application/json"
```

4. Le coach ou admin valide les paiements participants :

```bash
curl -X POST https://api.gotfit.tech/api/visio/sessions/1/participants/2/paid \
  -H "Authorization: Bearer TOKEN_COACH" \
  -H "Accept: application/json"
```

5. Le coach demarre la seance :

```bash
curl -X POST https://api.gotfit.tech/api/visio/sessions/1/start \
  -H "Authorization: Bearer TOKEN_COACH" \
  -H "Accept: application/json"
```

6. Coach et participants rejoignent la salle :

```bash
curl -X POST https://api.gotfit.tech/api/visio/sessions/1/join \
  -H "Authorization: Bearer TOKEN_USER" \
  -H "Accept: application/json"
```

La reponse contient `room_name`, `server_url`, `join_url` et `token` pour le front web/mobile.
