# Gotfit API — changements Mobile 1.2.2

## Réservations

- validation stricte d'un créneau futur ;
- statut de paiement initial normalisé à `pending` ;
- confirmation par le coach refusée tant que le paiement n'est pas `paid` ;
- changement de date et d'heure réservé au client concerné ;
- conservation du paiement Stripe pendant le changement de créneau ;
- remise de la réservation en attente de confirmation ;
- historique complet de l'ancien et du nouveau créneau.

## Paiements Stripe

- montant calculé exclusivement à partir de la réservation en base ;
- réponse du Payment Intent avec le montant en unité minimale ;
- vérification de la signature, du montant et de la devise du webhook ;
- traitement idempotent des événements Stripe répétés ;
- création ou mise à jour de l'enregistrement de paiement ;
- notification du coach lorsqu'une demande payée attend sa confirmation.

## Messagerie

- modification du texte et remplacement ou retrait d'une image ;
- modification limitée à l'auteur ;
- suppression pour tous avec conservation d'un marqueur synchronisable ;
- suppression du média et des réactions associés ;
- lecture des messages supprimés avec `is_deleted` ;
- indicateur `is_edited`.

## Notifications

- enregistrement et suppression des jetons Expo du téléphone ;
- notification push pour les nouveaux messages ;
- notification push pour paiement et changement de créneau ;
- nettoyage automatique des jetons Expo devenus invalides ;
- envoi asynchrone par la file Laravel.

## Base de données

Trois migrations sont ajoutées :

- `reservation_reschedule_histories` ;
- `push_tokens` ;
- champs `edited_at` et `deleted_at` sur `messages`.
