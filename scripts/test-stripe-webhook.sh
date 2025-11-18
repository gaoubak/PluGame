#!/bin/bash

# Script pour tester le webhook Stripe localement
# Usage: ./scripts/test-stripe-webhook.sh

echo "🧪 Test du webhook Stripe..."
echo ""

# Vérifier que ngrok tourne
NGROK_URL=$(curl -s http://localhost:4040/api/tunnels 2>/dev/null | grep -o 'https://[a-z0-9-]*\.ngrok-free\.app' | head -1)

if [ -z "$NGROK_URL" ]; then
    echo "❌ ngrok n'est pas démarré !"
    echo ""
    echo "Démarrez ngrok d'abord :"
    echo "  ./scripts/start-ngrok.sh"
    echo ""
    exit 1
fi

echo "✅ ngrok détecté : $NGROK_URL"
echo ""

# Webhook URL
WEBHOOK_URL="$NGROK_URL/api/stripe/webhook"

echo "🔗 URL du webhook : $WEBHOOK_URL"
echo ""

# Créer un payload de test (payment_intent.succeeded simulé)
PAYLOAD=$(cat <<'EOF'
{
  "id": "evt_test_webhook",
  "object": "event",
  "api_version": "2023-10-16",
  "created": 1234567890,
  "data": {
    "object": {
      "id": "pi_test_123",
      "object": "payment_intent",
      "amount": 3000,
      "amount_received": 3000,
      "currency": "eur",
      "status": "succeeded",
      "metadata": {
        "booking_id": "123",
        "payment_type": "deposit"
      }
    }
  },
  "livemode": false,
  "type": "payment_intent.succeeded"
}
EOF
)

echo "📤 Envoi du webhook de test..."
echo ""

# Note: Ce test enverra le payload SANS signature Stripe
# Il échouera avec "Invalid signature" ce qui est normal
# Pour tester avec signature, utilisez Stripe CLI

RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST \
  "$WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -H "stripe-signature: test_signature" \
  -d "$PAYLOAD")

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d':' -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

echo "📥 Réponse du serveur :"
echo "  Status: $HTTP_CODE"
echo "  Body: $BODY"
echo ""

if [ "$HTTP_CODE" = "400" ]; then
    echo "⚠️  Erreur 400 - C'est NORMAL sans Stripe CLI !"
    echo ""
    echo "Le webhook requiert une signature Stripe valide."
    echo ""
    echo "Pour tester avec une vraie signature :"
    echo ""
    echo "1️⃣  Installer Stripe CLI :"
    echo "    brew install stripe/stripe-cli/stripe"
    echo ""
    echo "2️⃣  Login Stripe :"
    echo "    stripe login"
    echo ""
    echo "3️⃣  Écouter les webhooks :"
    echo "    stripe listen --forward-to http://localhost:8090/api/stripe/webhook"
    echo ""
    echo "4️⃣  Déclencher un événement de test :"
    echo "    stripe trigger payment_intent.succeeded"
    echo ""
elif [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Webhook reçu avec succès !"
    echo ""
    echo "Vérifiez les logs :"
    echo "  docker compose logs -f alpine | grep -i stripe"
    echo ""
else
    echo "❌ Erreur $HTTP_CODE"
    echo ""
    echo "Vérifiez que :"
    echo "  - Docker est démarré : docker compose ps"
    echo "  - Le container PHP répond : curl http://localhost:8090/api"
    echo ""
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📊 Pour tester avec Stripe Dashboard :"
echo ""
echo "1. Allez sur https://dashboard.stripe.com/test/webhooks"
echo "2. Cliquez sur votre endpoint"
echo "3. Onglet 'Send test webhook'"
echo "4. Sélectionnez 'payment_intent.succeeded'"
echo "5. Cliquez 'Send test webhook'"
echo ""
echo "🌐 Dashboard ngrok : http://localhost:4040"
echo "   Vous verrez toutes les requêtes POST /api/stripe/webhook"
echo ""
