# Priorite 5 - Profil coach

Cette mise a jour ajoute les champs de profil coach/intervenant et la video de presentation limitee a 60 secondes.

## Champs ajoutes

- `presentation_video`
- `presentation_video_duration_seconds`
- `coach_title`
- `coach_short_description`
- `coach_speciality`
- `coach_experience_years`
- `coach_certifications`
- `coach_languages`

## Routes concernees

- `GET /api/profile`
- `POST /api/profile/update`
- `GET /api/intervenants`
- routes admin utilisateurs existantes

## Upload video

Champ multipart a envoyer :

- `presentation_video` : fichier video `mp4`, `mov`, `webm` ou `mkv`
- duree maximum : 60 secondes
- taille maximum : 50 Mo

Le serveur verifie la duree avec `ffprobe`. Installer ffmpeg si necessaire :

```bash
sudo apt update
sudo apt install -y ffmpeg
```

## Deploiement

```bash
cd /var/www/gotfit
php artisan migrate
php artisan storage:link
php artisan optimize:clear
```

Verifier ensuite :

```bash
which ffprobe
php artisan route:list | grep profile
```
