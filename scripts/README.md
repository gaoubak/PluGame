# 📜 Scripts Plugame

Collection de scripts utiles pour le développement.

---

## 🌐 start-ngrok.sh

Expose votre API locale sur Internet avec ngrok.

### Usage
```bash
./scripts/start-ngrok.sh
```

### Configuration requise (une seule fois)
```bash
# 1. Installer ngrok
brew install ngrok

# 2. Configurer votre authtoken
ngrok config add-authtoken 35bohRrwkaahOXyqh9lLPyFGz96_2wxFAX7h9sDfjnGzoHWUU
```

### Après démarrage
1. Copiez l'URL HTTPS affichée (ex: `https://abc123.ngrok-free.app`)
2. Mettez à jour `.env.local` :
   ```env
   APP_URL=https://abc123.ngrok-free.app
   APP_PUBLIC_BASE_URL=https://abc123.ngrok-free.app
   ```
3. Redémarrez le container :
   ```bash
   docker compose restart alpine
   ```

### Dashboard
Ouvrez **http://localhost:4040** pour voir toutes les requêtes en temps réel.

### Cas d'usage
- Tester le pixel de tracking dans les emails
- Recevoir les webhooks Stripe
- Tester depuis un appareil mobile
- Partager l'API avec votre équipe frontend

---

## 📋 generate-frontend-config.sh

Génère automatiquement un fichier JSON avec l'URL ngrok actuelle pour le frontend.

### Usage
```bash
./scripts/generate-frontend-config.sh
```

### Prérequis
- ngrok doit être démarré (`./scripts/start-ngrok.sh`)
- L'API ngrok locale doit être accessible sur `http://localhost:4040`

### Résultat
Crée le fichier `frontend-config.json` contenant :
- URL de base de l'API
- Tous les endpoints disponibles
- Configuration Mercure
- Instructions pour le frontend

### Exemple de sortie
```json
{
  "api": {
    "baseUrl": "https://abc123.ngrok-free.app",
    "endpoints": {
      "auth": { ... },
      "bookings": { ... },
      "deliverables": { ... }
    }
  },
  "mercure": {
    "hubUrl": "https://abc123.ngrok-free.app/.well-known/mercure"
  }
}
```

### Partage avec le frontend
```bash
# Afficher la config
cat frontend-config.json

# Envoyer par email/Slack
# Ou partager le contenu directement
```

---

## 🧪 test-stripe-webhook.sh

Teste que le webhook Stripe est accessible et répond correctement.

### Usage
```bash
./scripts/test-stripe-webhook.sh
```

### Prérequis
- ngrok démarré (`./scripts/start-ngrok.sh`)
- Container PHP démarré (`docker compose up -d`)

### Ce que fait le script
1. Détecte automatiquement l'URL ngrok
2. Envoie un payload de test au webhook
3. Affiche la réponse du serveur
4. Donne des instructions pour tester avec Stripe Dashboard

### Note importante
Le test sans signature Stripe retournera **400 Bad Request** - c'est normal !

Pour un test complet avec signature valide, utilisez :
- **Stripe Dashboard** → "Send test webhook"
- **Stripe CLI** → `stripe trigger payment_intent.succeeded`

---

## 📚 Documentation complète

### ngrok
- **Démarrage rapide** : [NGROK_QUICK_START.md](../NGROK_QUICK_START.md)
- **Guide complet** : [NGROK_SETUP_GUIDE.md](../NGROK_SETUP_GUIDE.md)
- **Intégration frontend** : [FRONTEND_INTEGRATION.md](../FRONTEND_INTEGRATION.md)

### Stripe Webhooks
- **Guide express (2 min)** : [STRIPE_WEBHOOK_QUICK.md](../STRIPE_WEBHOOK_QUICK.md)
- **Guide complet** : [STRIPE_WEBHOOK_SETUP.md](../STRIPE_WEBHOOK_SETUP.md)
