# Gotfit Webapp — Correction Priorité 5 profil coach/intervenant

Corrections intégrées dans ce ZIP :

- liste des intervenants branchée sur `GET /api/intervenants` ;
- suppression de la liste de spécialités codée en dur dans `src/app/intervenants/page.tsx` ;
- spécialités générées dynamiquement depuis les champs API, avec priorité à `coach_speciality` ;
- prise en charge des champs :
  - `coach_title`
  - `coach_short_description`
  - `coach_speciality`
  - `coach_experience_years`
  - `coach_certifications`
  - `coach_languages`
  - `presentation_video`
  - `presentation_video_url`
  - `presentation_video_duration_seconds`
- page détail intervenant enrichie avec vidéo, expérience, certifications et langues ;
- formulaire profil enrichi pour les comptes intervenants ;
- contrôle côté front de la vidéo de présentation : 60 secondes maximum avant upload ;
- conservation des pages déjà présentes : annonces, réservations, messagerie, parcours client, onboarding, planning, visio, contact, profil, auth.

## Fichiers principaux modifiés

- `src/lib/intervenants.ts`
- `src/app/intervenants/page.tsx`
- `src/app/intervenants/[id]/page.tsx`
- `src/app/profile/page.tsx`
- `src/types/auth.ts`

## Installation locale

```bash
npm install
npm run dev
```

## Build production

```bash
npm run build
pm2 restart gotfit-webapp
```

Si le VPS utilise déjà `/var/www/gotfit-webapp`, remplace les fichiers du projet par ceux du ZIP, puis relance :

```bash
cd /var/www/gotfit-webapp
npm install
npm run build
pm2 restart gotfit-webapp
```
