# Corrections calendrier, planning et visio

## Calendrier
- Le bouton n'ouvre plus directement l'URL API `/calendar.ics` dans un nouvel onglet.
- Il télécharge maintenant un fichier `.ics` compatible Google Calendar, Outlook et Apple Calendar.
- L'appel API est tenté avec le token utilisateur.
- Si l'API ne renvoie pas correctement le fichier ou si CORS bloque la réponse, un fichier `.ics` est généré côté navigateur à partir de la réservation.
- Le calendrier n'est disponible qu'après paiement et reste désactivé pour les réservations annulées ou remboursées.

## Planning
- Le planning reste la page interne de consultation des rendez-vous Gotfit.
- Il ne remplace pas le calendrier externe : le fichier `.ics` permet d'ajouter le rendez-vous au calendrier personnel de l'utilisateur.
- Pour une prestation en ligne payée, un accès vers la visio est affiché.

## Visio
- Si la réservation contient `visio_session_id` ou `visio_session.id`, le bouton ouvre directement `/visio/{id}`.
- Sinon il ouvre la liste `/visio`, car la réponse API de réservation ne fournit pas encore de liaison directe avec une session.

## Backend recommandé
Pour une liaison automatique complète réservation → visio, Laravel devrait renvoyer dans chaque réservation :

```json
{
  "visio_session_id": 12,
  "visio_session": { "id": 12, "status": "confirmed" }
}
```

Une session visio doit être créée ou associée uniquement après paiement confirmé, pour les annonces en ligne.
