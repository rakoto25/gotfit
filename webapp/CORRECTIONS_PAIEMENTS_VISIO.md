# Corrections paiements et visio

- Le total payé du profil utilise désormais les réservations payées si l’endpoint `/payments` ne retourne rien au client.
- Les prochaines visios du dashboard n’affichent plus toutes les sessions publiques : seulement les sessions liées à une réservation payée ou auxquelles le client participe.
- Une réservation en ligne payée sans `visio_session_id` affiche « Visio en préparation » au lieu d’ouvrir une session sans rapport.
- La page détail visio tente automatiquement d’inscrire le client lorsque la réservation payée est bien liée à la session.

## Contrat backend recommandé

Lorsqu’un paiement de réservation en ligne passe à `paid`, Laravel doit :
1. créer ou retrouver la `visio_session`;
2. renseigner `reservations.visio_session_id`;
3. créer/upsert le participant avec `payment_status=paid` et `status=paid`;
4. recalculer `paid_participants_count`;
5. passer la session à `confirmed` lorsque le minimum est atteint.
