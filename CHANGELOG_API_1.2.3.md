# Gotfit API 1.2.3

- Ajoute des routes compatibles `POST`, `PUT` et `PATCH` pour le profil.
- Ajoute `POST /api/message/{message_id}/update` pour les clients mobiles.
- Conserve `PUT|PATCH /api/message/{message_id}` pour les clients REST.
- Autorise la modification d’une photo sans renvoyer tout le profil.
- Accepte JPEG, PNG, WebP, HEIC et HEIF jusqu’à 8 Mo pour l’avatar.
- Ne supprime plus l’ancien média avant l’enregistrement réussi du nouveau.
- Ajoute des tests de régression pour l’upload multipart et les messages.
- Documente la purge obligatoire du cache des routes sur le VPS.
