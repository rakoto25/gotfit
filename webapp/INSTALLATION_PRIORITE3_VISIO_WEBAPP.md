# GotFit Webapp - Priorite 3 Visio

Ce correctif ajoute le front web pour les seances visio :

- Page liste : `/visio`
- Page detail : `/visio/[id]`
- Creation de seance pour les coachs/intervenants
- Reservation d'une place pour les clients
- Validation paiement test par le coach
- Demarrage / fin de seance par le coach
- Bouton rejoindre avec affichage du provider, room name et token retourne par l'API
- Liens ajoutes dans le header et le footer

## Fichiers ajoutes

- `src/lib/visio.ts`
- `src/app/visio/page.tsx`
- `src/app/visio/[id]/page.tsx`

## Fichiers modifies

- `src/components/layout/Header.tsx`
- `src/components/layout/Footer.tsx`

## Verification locale

```bash
npm install
npm run build
```

Dans l'environnement de correction, `npx tsc --noEmit` passe correctement. Le build Next complet peut necessiter le paquet SWC adapte au serveur Linux.

## Deploiement VPS

```bash
cd /var/www/gotfit-webapp
git pull --rebase origin main
npm install
npm run build
pm2 restart gotfit-webapp
```

Verifier ensuite :

```txt
https://gotfit.tech/webapp/visio
```
