# Mise à jour notes assignées et annonces clients

## Fonctionnalités incluses

- Une note du parcours client peut être assignée à un intervenant coach.
- L’API vérifie que l’utilisateur sélectionné possède bien le rôle `intervenant`.
- Les clients peuvent publier une annonce de type `client_request` pour rechercher un coach.
- Les annonces de coach restent de type `coach_service`.
- Une recherche client ne peut pas être réservée ou envoyée au paiement comme une prestation.
- Les annonces restent soumises à la validation de l’administration.

## Déploiement API

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

La migration importante est :

`database/migrations/2026_08_24_000001_add_announcement_type_to_annonces_table.php`

Elle ajoute `annonces.announcement_type` avec la valeur par défaut
`coach_service`, afin de préserver toutes les annonces existantes.

## Vérification

```bash
php artisan test
```

La suite livrée contient des tests pour l’assignation d’une note, la validation
du rôle coach, la publication d’une recherche client et le blocage de la
réservation d’une recherche client.
