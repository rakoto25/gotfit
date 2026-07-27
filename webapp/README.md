# Gotfit Webapp

Webapp Next.js 16 de Gotfit, pensée pour les clients, coachs, structures et
administrateurs.

## Prérequis

- Node.js 20 ou supérieur
- npm
- L'API Gotfit Laravel

## Installation locale

```bash
npm install
cp .env.example .env.local
npm run dev
```

Ouvrir `http://localhost:3000`.

## Configuration

```dotenv
NEXT_PUBLIC_API_URL=http://localhost:8000/api
NEXT_PUBLIC_SITE_URL=http://localhost:3000
NEXT_PUBLIC_GOOGLE_CLIENT_ID=000000000000-example.apps.googleusercontent.com
```

`NEXT_PUBLIC_GOOGLE_CLIENT_ID` doit contenir le même identifiant OAuth que
`GOOGLE_CLIENT_ID` dans Laravel.

## Expérience d'authentification

- Le bouton **S'inscrire** du header ouvre une page qui présente deux parcours.
- L'utilisateur peut remplir le formulaire classique ou choisir
  **Continuer avec Google**.
- Le choix **Client** ou **Coach** est disponible dans les deux parcours.
- L'inscription classique ouvre immédiatement la session après la création du
  compte.
- Les comptes existants peuvent utiliser Google si l'adresse email correspond.
- La connexion email/mot de passe existante reste disponible.

## Vérification

```bash
npm run build
```

La nouvelle interface inclut une page d'accueil complète, une navigation
responsive, un centre d'aide, les écrans Google et une carte sociale dédiée.
