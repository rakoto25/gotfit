# Corrections ajoutées

- Nouvelle route `/client/dashboard` avec résumé client, réservations, planning, calendrier, paiements, visios et raccourcis.
- Routes de secours `/intervenant/dashboard` et `/admin/dashboard` afin d’éviter les pages 404 depuis le profil.
- Retour Stripe compatible avec le `basePath` local ou production via `NEXT_PUBLIC_BASE_PATH`.
- Compatibilité du PaymentIntent avec `clientSecret` et `client_secret`.
- Validation du projet avec `npm run build` sous Next.js 16.2.9.

## Production

Dans `.env.local` sur le VPS :

```env
NEXT_PUBLIC_API_URL=https://api.gotfit.tech/api
NEXT_PUBLIC_APP_URL=https://gotfit.tech
NEXT_PUBLIC_BASE_PATH=/webapp
NEXT_PUBLIC_STRIPE_KEY=pk_test_...
```

Puis :

```bash
rm -rf .next
npm install
npm run build
pm2 restart gotfit-webapp --update-env
pm2 save
```
