# Guide d'utilisation Postman - 23HEC001 API

## 📦 Fichiers créés

1. **`23HEC001_Complete_API.postman_collection.json`** - Collection complète de toutes les routes API
2. **`23HEC001_Environment.postman_environment.json`** - Variables d'environnement pour les tests

---

## 🚀 Installation

### 1. Importer la Collection

1. Ouvrir Postman
2. Cliquer sur **Import** (en haut à gauche)
3. Glisser-déposer le fichier `23HEC001_Complete_API.postman_collection.json`
4. Ou cliquer sur **Upload Files** et sélectionner le fichier

### 2. Importer l'Environnement

1. Dans Postman, aller dans **Environments** (icône d'engrenage en haut à droite)
2. Cliquer sur **Import**
3. Sélectionner `23HEC001_Environment.postman_environment.json`
4. Sélectionner l'environnement **"23HEC001 - Local Development"** dans le menu déroulant

---

## 📚 Structure de la Collection

La collection contient **10 dossiers** organisés par fonctionnalité:

### 🔐 1. Authentication
- Login (sauvegarde automatiquement les tokens)
- Refresh Token
- Logout from All Devices
- Logout

### 👥 2. Users & Profiles
- Register User
- Get Current User
- Update Current User
- Get User by ID
- List Users
- Search Users

### 📅 3. Bookings
- List All Bookings
- My Bookings (as Athlete)
- My Bookings (as Creator)
- Get Booking by ID
- **Create Booking (with Promo Code)** ⭐
- Accept Booking
- Decline Booking
- Cancel Booking
- Complete Booking

### 🎁 4. Promo Codes (NEW!)
- Create Promo Code (Creator Only)
- List My Promo Codes
- **Validate Promo Code** ⭐
- Deactivate Promo Code

### 🎯 5. Services & Availability
- List Services
- Get Service by ID
- Create Service
- Update Service
- List Availability Slots
- My Slots
- Create Bulk Slots

### 💳 6. Payments
- Create Payment Intent
- Payment History
- Payment Status
- Wallet Balance
- Wallet Purchase

### 💬 7. Messages
- My Conversations
- Create Conversation
- Send Message
- Get Messages by Conversation

### 👍 8. Social (Feed, Likes, Comments)
- Get Feed
- Like Post
- Unlike Post
- Create Comment
- Follow User
- Unfollow User

### 📸 9. Media & Deliverables
- Upload Media
- Upload Deliverable
- List Deliverables

### ⭐ 10. Reviews
- Create Review
- List Reviews
- Creator Reviews

### 📊 11. Dashboard (Creator)
- Dashboard Stats
- Analytics
- Recent Bookings

---

## 🎯 Workflow Rapide

### Scénario 1: Login et Obtenir les Tokens

1. **Ouvrir:** `🔐 Authentication` > `Login`
2. **Modifier le body** (si nécessaire):
   ```json
   {
     "username": "test@example.com",
     "password": "password123"
   }
   ```
3. **Envoyer la requête**
4. ✅ Les tokens sont **automatiquement sauvegardés** dans l'environnement:
   - `access_token`
   - `refresh_token`
   - `mercure_token`

### Scénario 2: Créer un Code Promo (Créateur)

1. **Ouvrir:** `🎁 Promo Codes` > `Create Promo Code`
2. **Vérifier que vous êtes connecté en tant que créateur**
3. **Modifier le body**:
   ```json
   {
     "code": "WINTER2025",
     "discount_type": "percentage",
     "discount_value": 25,
     "description": "Promotion d'hiver",
     "max_uses": 50,
     "max_uses_per_user": 1,
     "expires_at": "2025-12-31T23:59:59Z",
     "min_amount": 5000
   }
   ```
4. **Envoyer**
5. ✅ Le `promo_code_id` et `promo_code` sont automatiquement sauvegardés

### Scénario 3: Valider un Code Promo

1. **Ouvrir:** `🎁 Promo Codes` > `Validate Promo Code`
2. **Le body utilise automatiquement les variables**:
   ```json
   {
     "code": "SUMMER2025",
     "creator_id": "{{creator_id}}",
     "amount": 10000
   }
   ```
3. **Envoyer**
4. ✅ Vous verrez:
   - `valid: true/false`
   - `discount_amount`
   - `final_amount`
   - `discount_display`

### Scénario 4: Créer une Réservation avec Code Promo

1. **D'abord, valider le code promo** (Scénario 3)
2. **Ouvrir:** `📅 Bookings` > `Create Booking (with Promo Code)`
3. **Le body inclut le code promo**:
   ```json
   {
     "creator_user_id": "{{creator_id}}",
     "service_offering_id": "{{service_id}}",
     "start_time": "2025-12-01T10:00:00Z",
     "end_time": "2025-12-01T12:00:00Z",
     "location": "Central Park, NYC",
     "promo_code": "SUMMER2025"
   }
   ```
4. **Envoyer**
5. ✅ Le `booking_id` est automatiquement sauvegardé

### Scénario 5: Payer avec Réduction

1. **Ouvrir:** `💳 Payments` > `Create Payment Intent`
2. **Le body inclut le code promo**:
   ```json
   {
     "booking_id": "{{booking_id}}",
     "amount_cents": 25000,
     "promo_code": "SUMMER2025"
   }
   ```
3. **Envoyer**
4. ✅ Vous verrez:
   - `amount`: Montant original
   - `discount_amount`: Réduction appliquée
   - `final_amount`: Montant final à payer

---

## 🔧 Variables d'Environnement

### Variables Automatiquement Mises à Jour

Ces variables sont **automatiquement remplies** par les scripts de test:

| Variable | Remplie par | Utilisée dans |
|----------|-------------|---------------|
| `access_token` | Login | Toutes les requêtes authentifiées |
| `refresh_token` | Login | Refresh Token |
| `mercure_token` | Login | Real-time messaging |
| `booking_id` | Create Booking | Payment, Reviews, Deliverables |
| `service_id` | Create Service | Create Booking |
| `promo_code_id` | Create Promo Code | Deactivate Promo Code |
| `promo_code` | Create Promo Code | Validate, Create Booking |
| `conversation_id` | Create Conversation | Send Message |
| `payment_intent_id` | Create Payment Intent | Payment Status |

### Variables à Configurer Manuellement

| Variable | Description | Exemple |
|----------|-------------|---------|
| `base_url` | URL de l'API | `http://localhost:8090` |
| `test_email` | Email de test | `test@example.com` |
| `test_password` | Mot de passe de test | `password123` |
| `user_id` | ID d'un utilisateur | UUID |
| `creator_id` | ID d'un créateur | UUID |
| `post_id` | ID d'un post | UUID |

---

## ✨ Fonctionnalités Automatiques

### 1. Auto-Save des Tokens (Login)
```javascript
if (pm.response.code === 200) {
    const response = pm.response.json();
    pm.environment.set('access_token', response.token);
    pm.environment.set('refresh_token', response.refresh_token);
    pm.environment.set('mercure_token', response.mercure_token);
    console.log('✅ Tokens saved to environment');
}
```

### 2. Auto-Save du Booking ID
```javascript
if (pm.response.code === 201) {
    const response = pm.response.json();
    pm.environment.set('booking_id', response.id);
}
```

### 3. Auto-Save du Promo Code
```javascript
if (pm.response.code === 201) {
    const response = pm.response.json();
    pm.environment.set('promo_code_id', response.id);
    pm.environment.set('promo_code', response.code);
}
```

---

## 🔑 Authentification

Toutes les requêtes (sauf Login et Register) incluent automatiquement:

```http
Authorization: Bearer {{access_token}}
```

Le token est **automatiquement inséré** depuis l'environnement.

### Si le Token Expire (après 15 minutes)

1. **Utiliser:** `🔐 Authentication` > `Refresh Token`
2. Les nouveaux tokens seront **automatiquement sauvegardés**
3. Réessayer votre requête

---

## 📝 Exemples d'Utilisation

### Créer un Service et des Disponibilités

```
1. Login
2. Services > Create Service
3. Services > Create Bulk Slots
   - Automatiquement disponible pour les réservations
```

### Workflow Complet d'une Réservation

```
1. Login (Athlete)
2. Search Users (trouver un créateur)
3. Services > Get Services by User (voir les services du créateur)
4. Promo Codes > Validate Promo Code (optionnel)
5. Bookings > Create Booking (avec promo_code si validé)
6. Payments > Create Payment Intent (avec promo_code)
7. [Payer via Stripe - frontend]
```

### Workflow Créateur

```
1. Login (Creator)
2. Services > Create Service
3. Services > Create Bulk Slots
4. Promo Codes > Create Promo Code
5. Bookings > My Bookings (as Creator)
6. Bookings > Accept Booking
7. Deliverables > Upload Deliverable
8. Dashboard > Dashboard Stats
```

---

## 🎨 Personnalisation

### Créer un Nouvel Environnement (Production)

1. Dupliquer l'environnement existant
2. Renommer en "23HEC001 - Production"
3. Modifier `base_url` vers `https://api.23hec001.com`
4. Utiliser des credentials de production

### Ajouter des Variables Personnalisées

1. Aller dans **Environments**
2. Sélectionner votre environnement
3. Cliquer sur **Add new variable**
4. Utiliser `{{variable_name}}` dans les requêtes

---

## 🐛 Résolution des Problèmes

### Erreur 401 Unauthorized

**Cause:** Token expiré ou invalide

**Solution:**
1. Utiliser `Refresh Token` pour obtenir un nouveau token
2. Ou se reconnecter avec `Login`

### Erreur 429 Too Many Requests

**Cause:** Rate limiting activé

**Détails:**
- Login: 5 tentatives / 15 minutes
- Refresh Token: 10 tentatives / heure
- API générale: 100 requêtes / minute

**Solution:**
Attendre le temps indiqué dans le header `Retry-After`

### Variables Non Définies

**Cause:** Variable d'environnement manquante

**Solution:**
1. Vérifier que l'environnement est sélectionné
2. Exécuter les requêtes dans l'ordre (ex: Login avant Create Booking)
3. Vérifier la console Postman pour les erreurs de script

### Code Promo Non Valide

**Cause:** Code promo non valide pour ce créateur

**Solution:**
1. Vérifier que `creator_id` correspond au créateur du code
2. Vérifier que le code n'est pas expiré
3. Vérifier que le montant minimum est atteint

---

## 📊 Tests Automatisés

Toutes les requêtes incluent des **tests automatiques**:

- ✅ Vérification du status code
- ✅ Sauvegarde automatique des IDs
- ✅ Logs dans la console Postman

### Voir les Logs

1. Ouvrir la **Console Postman** (en bas)
2. Exécuter une requête
3. Voir les messages comme: `✅ Tokens saved to environment`

---

## 🔗 Liens Utiles

- **Documentation Interactive:** `http://localhost:8090/api/doc`
- **OpenAPI Spec:** `http://localhost:8090/api/doc.json`
- **Guide API Complet:** `API_ROUTES_GUIDE.md`

---

## 🎯 Checklist Rapide

### Première Utilisation

- [ ] Importer la collection Postman
- [ ] Importer l'environnement
- [ ] Sélectionner l'environnement "23HEC001 - Local Development"
- [ ] Vérifier que `base_url` est correct (`http://localhost:8090`)
- [ ] Mettre à jour `test_email` et `test_password` si nécessaire
- [ ] Exécuter `Login` pour obtenir les tokens
- [ ] Vérifier que les tokens sont sauvegardés (regarder les variables d'environnement)

### Avant Chaque Session

- [ ] Vérifier que l'environnement est sélectionné
- [ ] Exécuter `Login` si les tokens sont expirés
- [ ] Vérifier la connexion à l'API

### Tester les Promo Codes

- [ ] Login en tant que créateur
- [ ] Créer un service
- [ ] Créer un code promo
- [ ] Login en tant qu'athlete
- [ ] Valider le code promo
- [ ] Créer une réservation avec le code
- [ ] Créer un payment intent avec le code

---

## 🎉 Prêt à Utiliser!

La collection Postman est maintenant configurée avec:
- ✅ **130+ requêtes** prêtes à l'emploi
- ✅ **Auto-save** des tokens et IDs
- ✅ **Tests automatiques** pour chaque requête
- ✅ **Variables d'environnement** pour faciliter les tests
- ✅ **Support complet** des promo codes
- ✅ **Tous les endpoints** de l'API

**Bon test! 🚀**
