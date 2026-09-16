# Corrections retour client - API GotFit

- Prix coach obligatoire et strictement positif pour une prestation.
- Disponibilités coach obligatoires et validation serveur du jour/créneau choisi à la réservation.
- Endpoint authentifié `GET /api/annonces/my` pour gérer ses annonces.
- Ajout du pseudo public `display_name` aux profils.
- Email automatique lors du passage d’un compte coach au statut `approved`.
- Les annonces restent forcées en visioconférence (`is_online=true`).
