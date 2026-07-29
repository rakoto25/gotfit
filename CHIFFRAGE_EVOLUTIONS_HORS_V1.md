# Chiffrage indicatif – évolutions hors V1

Date : 29 juillet 2026

Ce chiffrage est une estimation de cadrage, avant atelier fonctionnel et maquettes. Les budgets utilisent un taux journalier indicatif de **500 à 700 € HT**. Ils devront être recalculés avec le taux réel de l’équipe.

## 1. Programme de fidélité / cashback

### Périmètre MVP envisagé

- cagnotte interne exprimée en crédits ou en euros ;
- crédit après réalisation et validation d’une séance ;
- utilisation partielle ou totale lors d’une future réservation ;
- annulation ou remboursement d’un crédit en cas de litige ;
- historique infalsifiable des opérations ;
- solde visible dans les applications ;
- réglages administrateur : taux, plafond, expiration et campagnes ;
- notifications de crédit et d’expiration ;
- tableaux de suivi et export comptable simple.

### Charge estimée

| Lot | Charge |
| --- | ---: |
| Ateliers, règles métier et parcours | 2 à 3 j |
| Modèle de registre comptable et API | 4 à 6 j |
| Gain, utilisation, annulation, remboursement | 5 à 8 j |
| Intégration paiement et cas de litige | 4 à 7 j |
| Administration, mobile et web | 5 à 8 j |
| Sécurité, tests, recette et déploiement | 4 à 6 j |
| **Total MVP** | **24 à 38 jours** |

### Budget indicatif

**12 000 à 26 600 € HT**

### Niveau de complexité

Élevé. Une cagnotte doit être conçue comme un registre comptable : chaque mouvement est ajouté, jamais simplement écrasé. Les remboursements Stripe, les commissions, l’expiration des crédits et les obligations fiscales doivent être validés avant développement.

### Version complète possible

Avec parrainage, niveaux de fidélité, campagnes promotionnelles, règles multi-pays, exports comptables avancés et anti-fraude : **35 à 55 jours**, soit environ **17 500 à 38 500 € HT**.

## 2. Annonces inversées

### Périmètre MVP envisagé

- publication du besoin par le coaché ;
- catégorie, objectif, contraintes, localisation, visio/présentiel et budget ;
- modération et expiration ;
- recherche et notifications aux coachs pertinents ;
- trois coachs positionnés au maximum ;
- retrait d’une candidature libérant une place ;
- conversation avec chaque coach intéressé ;
- choix final du coach par le coaché ;
- transformation du choix en annonce/réservation GotFit ;
- suivi administrateur et signalement.

### Charge estimée

| Lot | Charge |
| --- | ---: |
| Ateliers, règles et maquettes | 2 à 3 j |
| Modèle, API et modération | 4 à 6 j |
| Candidatures avec plafond transactionnel de trois | 3 à 4 j |
| Matching et notifications | 4 à 6 j |
| Messagerie et conversion en réservation | 5 à 8 j |
| Interfaces mobile, web et administration | 7 à 11 j |
| Tests, recette et déploiement | 4 à 6 j |
| **Total MVP** | **29 à 44 jours** |

### Budget indicatif

**14 500 à 30 800 € HT**

### Niveau de complexité

Moyen à élevé. Le point critique est le plafond de trois coachs, qui doit être protégé contre les candidatures simultanées, puis raccordé proprement à la messagerie, au paiement et à la réservation.

## 3. Ordre recommandé

1. lancer les annonces inversées en premier : valeur utilisateur visible et réutilisation de la messagerie/réservation existantes ;
2. mesurer l’adoption et les conversions ;
3. cadrer ensuite le cashback avec un expert comptable et le prestataire de paiement ;
4. réaliser le cashback seulement après validation des règles de remboursement, expiration et fiscalité.

## 4. Éléments non inclus

- frais Stripe ou d’un prestataire tiers ;
- abonnements d’envoi SMS/e-mail/push ;
- travaux juridiques, fiscaux ou comptables ;
- traduction multilingue ;
- migration complexe de données historiques ;
- support et maintenance au-delà de la période de garantie convenue.
