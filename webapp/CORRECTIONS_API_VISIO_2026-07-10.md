# Corrections Webapp après mise à jour Laravel Visio

- Compatibilité avec `participant_token` et `token` pour LiveKit.
- Compatibilité avec `scheduled_at`/`start_at`, `minimum_participants`/`min_participants`, `intervenant_id`/`coach_id`.
- Une réservation payée en ligne n'affiche le bouton visio que si `visio_session_id` existe.
- La page visio vérifie le participant renvoyé par Laravel et ne crée plus artificiellement une inscription côté frontend.
- Le client et l'intervenant rejoignent la même salle grâce à `POST /api/visio/sessions/{id}/join`.
- Ajout de `RoomAudioRenderer` pour entendre les pistes audio distantes.
- Le profil essaie d'abord `/my-payments` puis `/payments/me` avant les anciens endpoints.
- Normalisation des réponses API pour accepter les enveloppes `data`, `session`, `sessions`.
- Correction d'une erreur JSX dans `src/app/visio/[id]/page.tsx`.

## Variables VPS

```env
NEXT_PUBLIC_API_URL=https://api.gotfit.tech/api
NEXT_PUBLIC_APP_URL=https://gotfit.tech
NEXT_PUBLIC_BASE_PATH=/webapp
NEXT_PUBLIC_STRIPE_KEY=pk_test_...
```
