# ⚡ Webhook Stripe - Guide Express (2 minutes)

---

## 🎯 Commandes exactes à exécuter

### 1. Démarrer ngrok
```bash
./scripts/start-ngrok.sh
```
**Copier l'URL** : `https://abc123.ngrok-free.app`

---

### 2. Créer le webhook dans Stripe

**URL** : https://dashboard.stripe.com/test/webhooks

**Cliquer** : "Add endpoint"

**Endpoint URL** :
```
https://abc123.ngrok-free.app/api/stripe/webhook
```

**Événements à sélectionner** :
```
✅ payment_intent.succeeded
✅ payment_intent.payment_failed
✅ charge.refunded
✅ transfer.created
✅ transfer.failed
```

**Cliquer** : "Add endpoint"

---

### 3. Copier le Webhook Secret

Stripe affiche :
```
Signing secret
whsec_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Copier cette valeur !**

---

### 4. Mettre à jour .env.local

```bash
nano .env.local
```

**Ligne 77 - Remplacer** :
```env
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Sauvegarder** : `Ctrl+O`, `Entrée`, `Ctrl+X`

---

### 5. Redémarrer le container

```bash
docker compose restart alpine
```

---

## ✅ Tester

### Dashboard Stripe
1. **Webhooks** > Votre endpoint
2. **"Send test webhook"**
3. Sélectionner `payment_intent.succeeded`
4. **"Send test webhook"**

**Résultat attendu** : ✅ Succeeded (200 OK)

### Dashboard ngrok
**URL** : http://localhost:4040

**Vous verrez** :
```
POST /api/stripe/webhook    200 OK
```

---

## 🎉 C'est tout !

Vos webhooks Stripe fonctionnent. Les paiements seront traités automatiquement.

---

## 📚 Guide complet

Pour plus de détails : [STRIPE_WEBHOOK_SETUP.md](STRIPE_WEBHOOK_SETUP.md)
