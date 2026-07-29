# Intégration mobile – API GotFit 1.3.0

Toutes les routes ci-dessous sont préfixées par `/api`. Les routes privées utilisent le token Sanctum :

```http
Authorization: Bearer VOTRE_TOKEN
Accept: application/json
```

## 1. Inscription classique d’un coach

`POST /register`

```json
{
  "name": "Moussa Coach",
  "email": "coach@example.com",
  "password": "mot-de-passe-solide",
  "password_confirmation": "mot-de-passe-solide",
  "role": "intervenant",
  "siret": "123 456 789 00012",
  "device_name": "gotfit-mobile"
}
```

Le SIRET peut être saisi avec des espaces ; l’API le renvoie sous la forme `12345678900012`.

Après création, utiliser le token reçu pour envoyer au moins un diplôme ou une certification. La réponse d’inscription indique :

```json
{
  "professional_profile": {
    "requires_completion": true,
    "missing_fields": ["diploma_or_certification"]
  }
}
```

## 2. Inscription Google d’un coach

`POST /auth/google`

Le champ `siret` est facultatif lors de la première authentification Google. Si le compte professionnel est incomplet, l’application doit rediriger le coach vers l’écran « Compléter mon profil professionnel » en lisant :

```json
{
  "professional_profile": {
    "requires_completion": true,
    "missing_fields": ["siret", "diploma_or_certification"]
  }
}
```

Le SIRET peut ensuite être envoyé avec `PUT`, `PATCH` ou `POST /profile`.

## 3. Diplômes et certifications

### Envoyer un justificatif

`POST /coach/credentials`

Requête `multipart/form-data` :

| Champ | Obligatoire | Exemple |
| --- | --- | --- |
| `name` | oui | `BPJEPS Activités de la forme` |
| `document_type` | oui | `diploma` |
| `document_number` | non | `BP-2026-001` |
| `issuing_organization` | non | `DRAJES` |
| `issued_at` | non | `2025-07-29` |
| `expires_at` | non | `2030-07-29` |
| `file` | oui | fichier local |

Valeurs de `document_type` :

- `diploma`
- `certification`
- `professional_card`
- `identity`
- `other`

### Consulter les justificatifs

`GET /coach/credentials`

### Supprimer son justificatif

`DELETE /coach/credentials/{id}`

## 4. Visio V1

Lors de la création d’une session :

```json
{
  "title": "Coaching petit groupe",
  "start_at": "2026-08-05T18:00:00+02:00",
  "min_participants": 1,
  "max_participants": 2,
  "price": 20
}
```

`max_participants` représente les coachés. La réponse contient aussi :

```json
{
  "effective_max_participants": 2,
  "max_attendees": 3,
  "available_places": 2
}
```

Une troisième réservation reçoit une réponse HTTP `422`.

## 5. Bilan de forme

### Récupérer le formulaire actif

`GET /fitness-assessment/form`

Si le client n’a pas encore transmis ses questions, l’API renvoie `404` avec `form: null`. L’application peut afficher « Le formulaire sera bientôt disponible ».

### Enregistrer un brouillon

`PUT /fitness-assessment`

```json
{
  "form_id": 1,
  "status": "draft",
  "answers": {
    "objectif": "Perdre du poids",
    "douleur_actuelle": false
  }
}
```

### Soumettre le bilan

Même route avec `"status": "submitted"`. Les questions marquées `required` sont alors contrôlées par l’API.

### Relire son dernier bilan

`GET /fitness-assessment`

### Consultation par un coach ou un administrateur

`GET /clients/{clientId}/fitness-assessments`

Un coach n’y accède que s’il possède une réservation avec ce coaché.

### Ajouter l’analyse du coach

`PUT /fitness-assessments/{assessmentId}/review`

```json
{
  "coach_notes": "Reprise progressive recommandée sur quatre semaines."
}
```

## 6. Administration des formulaires

- `GET /admin/fitness-assessment/forms`
- `POST /admin/fitness-assessment/forms`
- `PUT|PATCH /admin/fitness-assessment/forms/{id}`

Exemple de question :

```json
{
  "key": "objectif",
  "label": "Quel est votre objectif principal ?",
  "type": "textarea",
  "required": true
}
```

Activer une nouvelle version désactive automatiquement les précédentes.
