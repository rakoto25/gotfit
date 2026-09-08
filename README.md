# Gotfit API

API Laravel 10 de la plateforme GotFit. Elle gère l'authentification, les rôles,
les profils coach, le parcours client, la marketplace Stripe Connect, les
réservations, les visios, la messagerie et l'administration.

## Prérequis

- PHP 8.1 ou supérieur
- Composer
- MySQL 8 ou MariaDB récent

## Installation locale

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

L'API est alors disponible sur `http://localhost:8000/api`.

## Inscription par formulaire

`POST /api/register` accepte le nom, l'email, le téléphone facultatif, le mot
de passe confirmé et le rôle `client` ou `intervenant`. Une inscription valide
retourne directement l'utilisateur et un token Laravel Sanctum pour ouvrir sa
session. Les comptes coach restent en attente de validation administrateur.

## Connexion Google

1. Dans Google Cloud Console, créer un client OAuth 2.0 de type
   **Application Web**.
2. Ajouter l'origine JavaScript `http://localhost:3000` pour le développement.
3. Copier l'identifiant dans `.env` :

```dotenv
GOOGLE_CLIENT_ID=000000000000-example.apps.googleusercontent.com
CORS_ALLOWED_ORIGINS=http://localhost:3000
```

Le navigateur envoie le jeton Google Identity Services à
`POST /api/auth/google`. L'API vérifie l'audience, l'émetteur, l'expiration et
l'adresse email, puis :

- rattache un compte existant ayant la même adresse email ;
- ou crée automatiquement un compte client/intervenant ;
- conserve les rôles d'un compte existant ;
- retourne un token Laravel Sanctum.

Les comptes coach créés via Google restent en attente de validation
administrateur.

## Forum privé des coachs

La migration crée les canaux, discussions, réponses imbriquées, réactions,
mentions, notifications et signalements. Elle ajoute automatiquement le canal
officiel **Annonces GotFit** et reprend les anciens messages du forum dans le
canal Général.

Toutes les routes `/api/forum/*` sont réservées aux coachs dont le compte est
`approved` et aux administrateurs. Les routes `/api/admin/forum/*` permettent à
l'administration de gérer les canaux, les signalements, l'épinglage et le
verrouillage des discussions.

## Visio après paiement

Une réservation payée crée sa séance LiveKit, mais le client attend que le
coach intervenant génère le lien privé depuis ses réservations. Une salle de
groupe accepte au maximum quatre coachés et un coach, soit cinq personnes.

Configurer notamment :

```dotenv
FRONTEND_URL=https://gotfit.tech
VISIO_PROVIDER=livekit
VISIO_SERVER_URL=wss://votre-instance-livekit
VISIO_API_KEY=
VISIO_API_SECRET=
```

En production, exécuter les migrations avant de déployer la webapp :

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

## Tests

```bash
php artisan test
```

Les tests couvrent notamment les droits du forum, son cycle CRUD, les réponses
imbriquées, les réactions, mentions, signalements, la modération, la création
du lien visio après paiement et la limite de cinq participants.
