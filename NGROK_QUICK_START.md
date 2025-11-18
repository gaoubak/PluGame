# 🚀 Démarrage rapide ngrok - 3 minutes

Guide ultra-rapide pour exposer votre API Plugame sur Internet.

---

## ⚡ Commandes à exécuter (dans l'ordre)

### 1️⃣ Installer ngrok
```bash
brew install ngrok
```

### 2️⃣ Configurer votre authtoken
```bash
ngrok config add-authtoken 35bohRrwkaahOXyqh9lLPyFGz96_2wxFAX7h9sDfjnGzoHWUU
```

### 3️⃣ Démarrer ngrok
```bash
./scripts/start-ngrok.sh
```

**OU manuellement :**
```bash
ngrok http 8090
```

---

## 📋 Ce que vous verrez

```
ngrok

Session Status                online
Account                       votre@email.com
Version                       3.x.x
Region                        Europe (eu)
Latency                       -
Web Interface                 http://127.0.0.1:4040
Forwarding                    https://abc123.ngrok-free.app -> http://localhost:8090

Connections                   ttl     opn     rt1     rt5     p50     p90
                              0       0       0.00    0.00    0.00    0.00
```

---

## ✏️ Copier votre URL ngrok

**IMPORTANT** : Copiez la ligne `Forwarding` HTTPS uniquement :

✅ **Bon** : `https://abc123.ngrok-free.app`
❌ **Mauvais** : `http://abc123.ngrok-free.app` (sans le S)

---

## 🔧 Configurer Symfony

### Ouvrir `.env.local`
```bash
nano .env.local
```

### Remplacer les URLs (lignes 80-81)
```env
# AVANT
APP_URL=https://VOTRE_URL.ngrok-free.app
APP_PUBLIC_BASE_URL=https://VOTRE_URL.ngrok-free.app

# APRÈS (avec VOTRE vraie URL ngrok)
APP_URL=https://abc123.ngrok-free.app
APP_PUBLIC_BASE_URL=https://abc123.ngrok-free.app
```

### Sauvegarder
- `Ctrl + O` (écrire)
- `Entrée` (confirmer)
- `Ctrl + X` (quitter)

---

## 🔄 Redémarrer le container PHP

```bash
docker compose restart alpine
```

Attendez 5 secondes que le container redémarre.

---

## ✅ Vérifier que ça marche

### Test 1 : Ping de l'API
```bash
curl https://abc123.ngrok-free.app/api
```

Vous devriez voir une réponse JSON.

### Test 2 : Dashboard ngrok
Ouvrez dans votre navigateur : **http://localhost:4040**

Vous verrez toutes les requêtes HTTP en temps réel.

---

## 🎯 Tester le flux de livraison photo

### Étape 1 : Créer une réservation
Via Postman ou votre frontend :
```
POST https://abc123.ngrok-free.app/api/bookings
```

### Étape 2 : Créateur upload des fichiers
```
POST https://abc123.ngrok-free.app/api/deliverables/upload
Content-Type: multipart/form-data

file: photo.jpg
bookingId: {votre_booking_id}
```

### Étape 3 : Client paie le reste (70% + 15%)
Via Stripe ou votre système de paiement :
```
POST https://abc123.ngrok-free.app/api/payments/...
```

Cela définit `remainingPaidAt` → débloque les fichiers.

### Étape 4 : Client demande le téléchargement
```
POST https://abc123.ngrok-free.app/api/deliverables/request-download/{bookingId}
Authorization: Bearer {athlete_token}
```

**Résultat attendu** :
```json
{
  "message": "Download link sent to your email",
  "expiresIn": "7 days",
  "filesCount": 3
}
```

### Étape 5 : Vérifier l'email reçu

L'email contiendra :
1. **Lien de téléchargement** : `https://abc123.ngrok-free.app/download/...`
2. **Détails du paiement** :
   - Montant du service : 70,00 EUR
   - Frais Plugame (15%) : 10,50 EUR
   - **Total : 80,50 EUR**
3. **Pixel de tracking invisible** : `<img src="https://abc123.ngrok-free.app/api/deliverables/track/{token}">`

### Étape 6 : Ouvrir l'email

Quand vous ouvrez l'email dans Gmail/Outlook :
1. Le pixel de tracking se charge automatiquement
2. Backend reçoit : `GET /api/deliverables/track/{token}`
3. `deliverableDownloadedAt` est défini
4. **🎉 Payout automatique déclenché !**

### Étape 7 : Vérifier le payout

Dans le dashboard ngrok (**http://localhost:4040**), vous verrez :
```
GET /api/deliverables/track/{token}
→ 200 OK (image/gif)
```

Dans les logs Symfony :
```bash
docker compose logs -f alpine
```

Vous devriez voir :
```
[info] Payout processed for booking {id}
[info] Transfer created: tr_xxxxxxxxxx
[info] Creator receives: 70.00 EUR
```

---

## 🐛 Problèmes courants

### "ngrok not found"
```bash
# Vérifier l'installation
which ngrok

# Si vide, réinstaller
brew install ngrok
```

### "Failed to authenticate"
```bash
# Re-configurer le token
ngrok config add-authtoken 35bohRrwkaahOXyqh9lLPyFGz96_2wxFAX7h9sDfjnGzoHWUU
```

### "Connection refused"
```bash
# Vérifier que Docker tourne
docker compose ps

# Redémarrer si nécessaire
docker compose up -d
```

### "404 Not Found"
Vérifiez que l'URL dans `.env.local` est bien mise à jour avec votre URL ngrok.

### L'email n'arrive pas
```bash
# Vérifier la config Mailer
docker compose exec alpine php bin/console debug:config mailer

# Vérifier les logs
docker compose logs -f alpine | grep -i mail
```

---

## 🛑 Arrêter ngrok

Dans le terminal où ngrok tourne :
```
Ctrl + C
```

---

## 📊 Récapitulatif du flux

```
1. ngrok exposé : https://abc123.ngrok-free.app
                      ↓
2. Client paie 80,50€ (70€ + 10,50€ frais)
                      ↓
3. remainingPaidAt défini → Fichiers débloqués
                      ↓
4. Client demande téléchargement
                      ↓
5. Email envoyé avec lien + pixel tracking
                      ↓
6. Client ouvre email → Pixel chargé
                      ↓
7. GET https://abc123.ngrok-free.app/api/deliverables/track/{token}
                      ↓
8. Payout automatique : Créateur reçoit 70€
                      ↓
9. ✅ Transaction complète !
```

---

## 🎓 Ressources

- **Dashboard ngrok** : http://localhost:4040
- **Stripe Dashboard** : https://dashboard.stripe.com/test
- **Guide complet** : [NGROK_SETUP_GUIDE.md](NGROK_SETUP_GUIDE.md)

---

**✨ C'est tout ! Votre API est maintenant accessible depuis n'importe où.**
