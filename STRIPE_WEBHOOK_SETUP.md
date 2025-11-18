# 🔗 Configuration des Webhooks Stripe - Guide Complet

Ce guide vous explique comment configurer les webhooks Stripe pour recevoir les notifications de paiement en temps réel.

---

## 📋 Prérequis

- [ ] Compte Stripe créé (test ou production)
- [ ] ngrok démarré et URL copiée
- [ ] Backend Symfony fonctionnel

---

## 🚀 Configuration en 5 étapes

### 1️⃣ Démarrer ngrok

```bash
./scripts/start-ngrok.sh
```

**Copiez votre URL HTTPS** : `https://abc123.ngrok-free.app`

---

### 2️⃣ Créer le webhook dans Stripe Dashboard

#### A. Accéder au dashboard Stripe

**Mode Test** : https://dashboard.stripe.com/test/webhooks
**Mode Production** : https://dashboard.stripe.com/webhooks

#### B. Cliquer sur "Add endpoint"

#### C. Configurer l'endpoint

**Endpoint URL** :
```
https://abc123.ngrok-free.app/api/stripe/webhook
```

**⚠️ Remplacez `abc123` par VOTRE vraie URL ngrok !**

#### D. Sélectionner les événements à écouter

Cochez ces événements :

```
✅ payment_intent.succeeded
✅ payment_intent.payment_failed
✅ charge.refunded
✅ transfer.created
✅ transfer.failed
```

**Ou sélectionnez "Tout envoyer" pour recevoir tous les événements.**

#### E. Cliquer sur "Add endpoint"

---

### 3️⃣ Copier le Webhook Secret

Après avoir créé l'endpoint, Stripe vous affiche le **Signing secret** :

```
whsec_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Copiez cette valeur !**

---

### 4️⃣ Mettre à jour `.env.local`

```bash
nano .env.local
```

**Remplacez la ligne 77** :

```env
# AVANT
STRIPE_WEBHOOK_SECRET=whsec_...

# APRÈS (avec votre vrai secret)
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Sauvegarder** : `Ctrl + O`, `Entrée`, `Ctrl + X`

---

### 5️⃣ Redémarrer le container PHP

```bash
docker compose restart alpine
```

Attendez 5 secondes que le container redémarre.

---

## ✅ Tester le webhook

### Option 1 : Test depuis Stripe Dashboard

1. Allez dans **Webhooks** > Votre endpoint
2. Cliquez sur l'onglet **"Send test webhook"**
3. Sélectionnez `payment_intent.succeeded`
4. Cliquez sur **"Send test webhook"**

**Résultat attendu** :
```json
{
  "status": "succeeded",
  "response": "Webhook handled"
}
```

### Option 2 : Créer un vrai paiement de test

```bash
# Via Postman ou votre frontend
POST https://abc123.ngrok-free.app/api/payments/create-payment-intent
Authorization: Bearer {token}
Content-Type: application/json

{
  "bookingId": "123",
  "paymentType": "deposit"
}
```

Utilisez une carte de test Stripe :
```
Numéro : 4242 4242 4242 4242
Date : 12/34
CVC : 123
```

---

## 🔍 Vérifier que le webhook fonctionne

### 1. Dashboard ngrok

Ouvrez **http://localhost:4040**

Vous devriez voir :
```
POST /api/stripe/webhook    200 OK
```

### 2. Logs Symfony

```bash
docker compose logs -f alpine | grep -i stripe
```

Vous devriez voir :
```
[info] Stripe webhook received {"type":"payment_intent.succeeded","id":"evt_xxxxx"}
[info] Deposit payment completed {"booking_id":"123","amount":30}
```

### 3. Stripe Dashboard

Dans **Webhooks** > Votre endpoint > **Events** :

Vous verrez la liste de tous les événements envoyés avec leur statut :
- ✅ Succeeded (200 OK)
- ❌ Failed (si erreur)

---

## 📊 Événements gérés

### `payment_intent.succeeded`
**Déclenché quand** : Un paiement réussit

**Action backend** :
- Paiement de **dépôt (30%)** → `depositPaidAt` défini, status = `deposit_paid`
- Paiement du **reste (70%)** → `remainingPaidAt` défini, status = `remaining_paid`, fichiers débloqués ✅

### `payment_intent.payment_failed`
**Déclenché quand** : Un paiement échoue

**Action backend** :
- Log l'erreur
- Peut envoyer un email à l'utilisateur (à implémenter)

### `charge.refunded`
**Déclenché quand** : Un remboursement est effectué

**Action backend** :
- Booking status = `refunded`
- Log le montant remboursé

### `transfer.created`
**Déclenché quand** : Un payout est créé vers le créateur

**Action backend** :
- Log la création du transfer
- Confirmation que le créateur recevra son argent

### `transfer.failed`
**Déclenché quand** : Un payout échoue

**Action backend** :
- Log l'erreur
- Alerte l'admin (à implémenter)

---

## 🔐 Sécurité

### Vérification de signature

Le webhook **vérifie automatiquement** que la requête vient bien de Stripe :

```php
// src/Controller/StripeWebhookController.php:48
$event = Webhook::constructEvent(
    $payload,
    $sigHeader,
    $this->stripeWebhookSecret  // ← Vérifie la signature
);
```

**Si la signature est invalide** → 400 Bad Request

**Ne JAMAIS désactiver cette vérification en production !**

---

## 🔄 Flux complet de paiement avec webhooks

### Scénario : Client paie le reste (70% + 15%)

```
1. Frontend appelle : POST /api/payments/create-payment-intent
   └─> Backend crée un PaymentIntent Stripe
       └─> Retourne clientSecret au frontend

2. Frontend confirme le paiement avec Stripe SDK
   └─> Stripe traite la carte

3. ✅ Paiement réussi
   └─> Stripe envoie webhook : payment_intent.succeeded
       └─> POST https://abc123.ngrok-free.app/api/stripe/webhook
           └─> Backend reçoit l'événement
               ├─> Vérifie la signature ✅
               ├─> Met à jour booking.remainingPaidAt
               ├─> Change status → "remaining_paid"
               └─> Fichiers débloqués ! 🎉

4. Client demande le téléchargement
   └─> POST /api/deliverables/request-download/{id}
       └─> ✅ Vérification : booking.isDeliverablesUnlocked() = true
           └─> Email envoyé avec lien de téléchargement

5. Client ouvre l'email
   └─> Pixel de tracking chargé
       └─> Payout automatique au créateur
           └─> Stripe envoie webhook : transfer.created
               └─> Backend log la confirmation
```

---

## 🐛 Debugging

### Webhook non reçu

**Vérifier** :
1. ngrok est bien démarré : `curl http://localhost:4040/api/tunnels`
2. L'URL webhook dans Stripe contient la bonne URL ngrok
3. Le container PHP est démarré : `docker compose ps`

### Webhook reçu mais erreur 500

**Vérifier les logs** :
```bash
docker compose logs -f alpine | grep -i error
```

**Erreurs courantes** :
- `STRIPE_WEBHOOK_SECRET` vide ou invalide
- Booking introuvable
- Erreur de base de données

### Webhook reçu mais signature invalide

**Cause** : Le `STRIPE_WEBHOOK_SECRET` ne correspond pas

**Solution** :
1. Copier le bon secret depuis Stripe Dashboard
2. Mettre à jour `.env.local`
3. Redémarrer : `docker compose restart alpine`

---

## 📝 Tester avec Stripe CLI (optionnel)

### Installation
```bash
brew install stripe/stripe-cli/stripe
```

### Login
```bash
stripe login
```

### Écouter les webhooks localement
```bash
stripe listen --forward-to http://localhost:8090/api/stripe/webhook
```

**Stripe CLI vous donnera un webhook secret** :
```
Ready! Your webhook signing secret is whsec_xxxxx
```

**Utilisez ce secret dans `.env.local` pour les tests locaux.**

### Déclencher un événement de test
```bash
stripe trigger payment_intent.succeeded
```

---

## 🌍 Production

### Différences en production

1. **URL fixe** : Utilisez votre domaine réel (pas ngrok)
   ```
   https://api.plugame.app/api/stripe/webhook
   ```

2. **Webhook secret différent** : Créez un nouveau webhook en mode Live

3. **HTTPS obligatoire** : Stripe n'accepte que HTTPS en production

4. **Retry automatique** : Si votre serveur répond 500, Stripe réessaie automatiquement

---

## 📋 Checklist finale

- [ ] ngrok démarré avec URL copiée
- [ ] Webhook créé dans Stripe Dashboard
- [ ] URL webhook : `https://VOTRE_URL.ngrok-free.app/api/stripe/webhook`
- [ ] Événements sélectionnés (payment_intent, transfer, refund)
- [ ] Webhook secret copié depuis Stripe
- [ ] `.env.local` mis à jour avec le webhook secret
- [ ] Container PHP redémarré
- [ ] Test webhook envoyé depuis Stripe Dashboard → ✅ 200 OK
- [ ] Dashboard ngrok montre la requête POST
- [ ] Logs Symfony affichent "Stripe webhook received"

---

## 🔗 Ressources

- **Stripe Webhooks Doc** : https://stripe.com/docs/webhooks
- **Stripe Test Cards** : https://stripe.com/docs/testing
- **Stripe Dashboard (Test)** : https://dashboard.stripe.com/test/webhooks
- **ngrok Dashboard** : http://localhost:4040

---

**✨ Vos webhooks Stripe sont configurés ! Les paiements seront maintenant traités automatiquement en temps réel.**
