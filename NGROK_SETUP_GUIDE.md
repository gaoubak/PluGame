# 🌐 Guide de configuration ngrok pour Plugame

Ce guide vous explique comment exposer votre API locale Plugame sur Internet avec ngrok pour tester :
- Les emails de livraison avec tracking pixel
- Les webhooks Stripe
- Les paiements automatiques
- L'accès depuis mobile/frontend

---

## 📦 Installation de ngrok

### macOS avec Homebrew
```bash
brew install ngrok/ngrok/ngrok
```

### Téléchargement manuel
1. Allez sur [ngrok.com/download](https://ngrok.com/download)
2. Téléchargez pour macOS (ARM64 ou Intel)
3. Décompressez et déplacez dans `/usr/local/bin/`

---

## 🔑 Configuration initiale

### 1. Créer un compte ngrok (gratuit)
- Allez sur [dashboard.ngrok.com](https://dashboard.ngrok.com)
- Créez un compte gratuit
- Copiez votre **authtoken**

### 2. Authentifier ngrok
```bash
ngrok config add-authtoken VOTRE_TOKEN_ICI
```

---

## 🚀 Démarrer ngrok

### Option 1 : Avec le script fourni
```bash
./scripts/start-ngrok.sh
```

### Option 2 : Manuellement
```bash
ngrok http 8090
```

Vous verrez :
```
Session Status                online
Region                        Europe (eu)
Forwarding                    https://abc123.ngrok-free.app -> http://localhost:8090
```

**⚠️ Copiez l'URL HTTPS !** (ex: `https://abc123.ngrok-free.app`)

---

## ⚙️ Configuration de l'API Symfony

### 1. Mettre à jour `.env.local`

Ouvrez `.env.local` et remplacez les URLs ngrok :

```env
# URL publique ngrok (remplacez par VOTRE vraie URL ngrok)
APP_URL=https://abc123.ngrok-free.app
APP_PUBLIC_BASE_URL=https://abc123.ngrok-free.app
```

### 2. Redémarrer les containers (optionnel)

Si vous modifiez `.env.local`, redémarrez :
```bash
docker compose restart alpine
```

---

## 🎯 Cas d'usage avec ngrok

### 1. **Tester les emails de livraison**

Quand un créateur upload des fichiers et que le client demande le téléchargement :

```bash
# L'email contiendra :
- Lien de téléchargement : https://abc123.ngrok-free.app/api/deliverables/download/...
- Pixel de tracking : https://abc123.ngrok-free.app/api/deliverables/track/{token}
```

Le pixel de tracking déclenchera automatiquement le payout Stripe !

### 2. **Configurer les webhooks Stripe**

Dans le [Dashboard Stripe](https://dashboard.stripe.com/test/webhooks) :

1. Ajoutez un endpoint :
   ```
   https://abc123.ngrok-free.app/api/stripe/webhook
   ```

2. Sélectionnez les événements :
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
   - `transfer.created`
   - `transfer.failed`

3. Copiez le **webhook secret** et ajoutez dans `.env.local` :
   ```env
   STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx
   ```

### 3. **Tester depuis un appareil mobile**

Votre frontend mobile peut maintenant se connecter à :
```
https://abc123.ngrok-free.app/api
```

Au lieu de :
```
http://localhost:8090/api  ❌ (ne fonctionne pas depuis mobile)
```

---

## 🔍 Dashboard ngrok

Accédez au dashboard local : **http://localhost:4040**

Vous y verrez :
- ✅ Toutes les requêtes HTTP reçues
- ✅ Headers, body, réponses
- ✅ Replay de requêtes
- ✅ Très utile pour debugger !

---

## 📋 Checklist de test complet

### Test du flux de livraison photo

- [ ] ngrok démarré avec `./scripts/start-ngrok.sh`
- [ ] `.env.local` mis à jour avec l'URL ngrok
- [ ] Containers Docker redémarrés
- [ ] Créateur upload des photos via API
- [ ] Client paie le reste (70% + 15% frais)
- [ ] Client demande le lien de téléchargement
- [ ] Email reçu avec lien + détails paiement
- [ ] Pixel de tracking chargé
- [ ] Payout automatique déclenché
- [ ] Créateur reçoit 70€ sur son compte Stripe Connect

### Vérifications dans le dashboard ngrok (localhost:4040)

1. **Upload de fichier** :
   ```
   POST https://abc123.ngrok-free.app/api/deliverables/upload
   ```

2. **Demande de téléchargement** :
   ```
   POST https://abc123.ngrok-free.app/api/deliverables/request-download/{id}
   ```

3. **Tracking pixel** :
   ```
   GET https://abc123.ngrok-free.app/api/deliverables/track/{token}
   → Doit retourner une image GIF 1x1
   ```

4. **Webhook Stripe** :
   ```
   POST https://abc123.ngrok-free.app/api/stripe/webhook
   ```

---

## ⚠️ Limitations du plan gratuit ngrok

- ✅ 1 tunnel simultané
- ✅ URL change à chaque redémarrage
- ✅ 40 connexions/minute
- ❌ Pas de domaine personnalisé

**Solution** : Utilisez ngrok Pro (domaine fixe) ou Cloudflare Tunnel pour la production.

---

## 🛑 Arrêter ngrok

Appuyez sur `Ctrl + C` dans le terminal où ngrok tourne.

---

## 🔧 Debugging

### Problème : "ERR_NGROK_108"
**Solution** : Votre authtoken est invalide. Ré-authentifiez :
```bash
ngrok config add-authtoken NOUVEAU_TOKEN
```

### Problème : "Failed to complete tunnel connection"
**Solution** : Vérifiez que le port 8090 est bien utilisé :
```bash
docker compose ps
# Nginx doit être sur 0.0.0.0:8090->80/tcp
```

### Problème : "Connection refused"
**Solution** : Les containers Docker ne sont pas démarrés :
```bash
docker compose up -d
```

---

## 📚 Ressources

- [ngrok Documentation](https://ngrok.com/docs)
- [Stripe Webhooks Guide](https://stripe.com/docs/webhooks)
- [Dashboard ngrok](http://localhost:4040)

---

**✨ Vous êtes prêt à tester le système de livraison photo avec tracking et payout automatique !**
