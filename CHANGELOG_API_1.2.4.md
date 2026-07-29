# Gotfit API — correctif 1.2.4

## Favoris

- ajout de la migration manquante `favorites` ;
- relation sécurisée avec les utilisateurs et les annonces ;
- suppression en cascade lorsque l'utilisateur ou l'annonce est supprimé ;
- unicité du couple utilisateur/annonce pour empêcher les doublons ;
- tests des routes de liste, ajout et suppression ;
- vérification qu'un utilisateur ne peut pas supprimer le favori d'un autre.

## Déploiement

Après mise à jour du code, exécuter :

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```
