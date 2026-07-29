# GotFit API 1.3.0

Date : 29 juillet 2026

## Évolutions V1 intégrées

### Inscription et validation des coachs

- ajout du champ `siret` sur les comptes ;
- SIRET obligatoire à l’inscription classique d’un coach (`intervenant`) ;
- normalisation automatique des espaces et séparateurs ;
- validation sur exactement 14 chiffres et unicité ;
- compte coach toujours créé avec le statut `pending` ;
- inscription Google conservée : si le SIRET ou les justificatifs manquent, la réponse contient `professional_profile.requires_completion = true` ;
- possibilité pour l’administrateur de marquer un SIRET comme vérifié ;
- toute modification du SIRET annule automatiquement sa vérification précédente.

### Diplômes et certifications

- endpoints coach dédiés à l’envoi et à la consultation des justificatifs ;
- types reconnus : diplôme, certification, carte professionnelle, identité et autre ;
- métadonnées : numéro du document, organisme émetteur, date d’émission et date d’expiration ;
- fichiers acceptés : PDF, DOC, DOCX, PNG, JPG, JPEG, WEBP, HEIC et HEIF ;
- taille maximale : 10 Mo ;
- statut initial `en_attente`, puis validation ou refus par l’administrateur ;
- URL publique du fichier et indicateur d’expiration ajoutés aux réponses.

### Visio V1

- maximum de deux coachés par session ;
- le coach est compté séparément : trois personnes maximum dans la salle ;
- validation appliquée à la création et à la modification ;
- refus automatique de la troisième inscription ;
- verrou transactionnel pour empêcher un dépassement lors d’inscriptions simultanées ;
- migration de compatibilité qui plafonne les anciennes sessions à deux coachés ;
- réponses enrichies avec `effective_max_participants` et `max_attendees`.

### Bilan de forme

- formulaires administrables et versionnés ;
- questions stockées en JSON afin d’intégrer le contenu client sans nouvelle migration ;
- prise en charge des champs texte, texte long, nombre, date, choix simple, choix multiple et booléen ;
- enregistrement en brouillon ou soumission ;
- contrôle dynamique des questions obligatoires ;
- consultation par le coach uniquement s’il accompagne réellement le coaché ;
- analyse du bilan et notes du coach ;
- historique des versions et des réponses.

### Parcours client existant

Les migrations `client_notes` et `client_onboardings`, déjà présentes sur le dépôt/VPS, sont réintégrées dans l’archive complète afin qu’une installation neuve possède bien toutes les tables utilisées par le code.

## Compatibilité

- Laravel 10 ;
- PHP 8.1 ou supérieur ;
- routes historiques de profil, documents, messages, réservations et visio conservées ;
- aucune nouvelle variable obligatoire dans `.env`.

## Validation

- 25 tests automatisés réussis ;
- 119 assertions ;
- tests couverts : inscription, Google, SIRET, justificatifs, profil mobile, messagerie, favoris, réservation/paiement, replanification, visio et bilan de forme.
