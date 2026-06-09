# Corrections API GOTFIT

Corrections intégrées dans ce zip :

- Ajout du rôle `structure` + middleware `is_structure`.
- Inscription compatible avec `admin`, `intervenant`, `client`, `structure`.
- Validation des profils intervenants/structures par l’admin (`account_status`).
- Annonces enrichies : prix, durée, catégorie, ville, adresse, online/présentiel, disponibilités, image, boost.
- Réservation liée à une annonce avec calcul automatique :
  - frais client 5 % par défaut,
  - commission intervenant 12 % par défaut,
  - montant net intervenant,
  - total payé par le client.
- Paiement Stripe corrigé : devise EUR, montant basé sur la réservation, liaison `client_id`, `intervenant_id`, `reservation_id`.
- Webhook Stripe déplacé en route publique : `POST /api/payment/webhook`.
- Documents liés à l’utilisateur + validation/refus admin avec motif.
- Messagerie accessible aux utilisateurs connectés, pas seulement aux intervenants.
- Avis clients après réservation payée et réalisée.
- Missions structures + candidatures intervenants.
- Dashboard admin KPI + paramètres financiers.
- Correction du conflit des migrations `messages` en renommant l’ancienne table directe annonce en `annonce_messages`.

## Commandes à lancer après upload sur le serveur

```bash
composer install
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan storage:link
php artisan optimize:clear
```

## Paiement

- Le **client** paie la réservation.
- GOTFIT garde les frais client + la commission.
- L’intervenant reçoit le montant net.
- Les **intervenants** et **structures** peuvent payer des boosts/mises en avant.

## Variables Stripe

Ajouter dans `.env` si nécessaire :

```env
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

## Correction inscription publique / création Structure

- L'inscription publique `/api/register` accepte maintenant uniquement les rôles `client` et `intervenant`.
- Le rôle `structure` n'est plus disponible depuis l'inscription publique.
- Le rôle `admin` n'est plus disponible depuis l'inscription publique.
- Une nouvelle route admin protégée a été ajoutée : `POST /api/admin/users`.
- Cette route permet à l'administrateur de créer manuellement un compte `client`, `intervenant`, `structure` ou `admin`.
- Par défaut :
  - `client` et `admin` sont créés avec `account_status = approved`.
  - `intervenant` et `structure` sont créés avec `account_status = pending`, sauf si l'admin envoie explicitement `account_status = approved`.

### Exemple création Structure par admin

```http
POST /api/admin/users
Authorization: Bearer TOKEN_ADMIN
Content-Type: application/json

{
  "name": "Studio Yoga Centre",
  "email": "studio@example.com",
  "password": "password123",
  "role": "structure",
  "phone": "+261000000000",
  "address": "Antananarivo",
  "account_status": "approved"
}
```
