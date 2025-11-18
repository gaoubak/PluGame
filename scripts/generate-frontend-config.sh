#!/bin/bash

# Script pour générer la config frontend avec l'URL ngrok actuelle
# Usage: ./scripts/generate-frontend-config.sh

echo "🔍 Recherche de l'URL ngrok active..."
echo ""

# Récupérer l'URL ngrok depuis l'API locale
NGROK_URL=$(curl -s http://localhost:4040/api/tunnels | grep -o 'https://[a-z0-9-]*\.ngrok-free\.app' | head -1)

if [ -z "$NGROK_URL" ]; then
    echo "❌ Aucun tunnel ngrok actif détecté !"
    echo ""
    echo "Assurez-vous que ngrok est démarré :"
    echo "  ./scripts/start-ngrok.sh"
    echo ""
    exit 1
fi

echo "✅ URL ngrok trouvée : $NGROK_URL"
echo ""

# Générer le fichier de config
cat > frontend-config.json <<EOF
{
  "api": {
    "baseUrl": "$NGROK_URL",
    "endpoints": {
      "auth": {
        "login": "/api/auth/login",
        "register": "/api/auth/register",
        "refreshToken": "/api/auth/refresh"
      },
      "bookings": {
        "list": "/api/bookings",
        "create": "/api/bookings",
        "detail": "/api/bookings/{id}",
        "accept": "/api/bookings/{id}/accept",
        "decline": "/api/bookings/{id}/decline",
        "cancel": "/api/bookings/{id}/cancel"
      },
      "deliverables": {
        "upload": "/api/deliverables/upload",
        "list": "/api/deliverables/booking/{id}",
        "requestDownload": "/api/deliverables/request-download/{id}",
        "delete": "/api/deliverables/{id}"
      },
      "payments": {
        "createPaymentIntent": "/api/payments/create-payment-intent",
        "confirmPayment": "/api/payments/confirm",
        "paymentMethods": "/api/payments/methods"
      },
      "profile": {
        "me": "/api/users/me",
        "update": "/api/users/me",
        "uploadAvatar": "/api/media/avatar"
      }
    }
  },
  "mercure": {
    "hubUrl": "$NGROK_URL/.well-known/mercure",
    "topics": {
      "bookingStatusChanged": "/bookings/{id}/status",
      "newMessage": "/conversations/{id}/messages",
      "deliverableUploaded": "/bookings/{id}/deliverables",
      "paymentReceived": "/bookings/{id}/payment"
    }
  },
  "cors": {
    "enabled": true,
    "allowedOrigins": [
      "http://localhost:3000",
      "http://localhost:19006",
      "exp://192.168.1.X:19000"
    ]
  },
  "notes": {
    "generatedAt": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "ngrokUrl": "⚠️ Cette URL change à chaque redémarrage de ngrok !",
    "howToUpdate": "Relancez ce script après chaque redémarrage de ngrok"
  }
}
EOF

echo "📝 Fichier frontend-config.json généré avec succès !"
echo ""
echo "📋 Configuration pour le frontend :"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "  API Base URL : $NGROK_URL"
echo "  Mercure URL  : $NGROK_URL/.well-known/mercure"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📤 Envoyez ce fichier à votre équipe frontend :"
echo "  cat frontend-config.json"
echo ""
echo "💡 Pour mettre à jour .env.local backend :"
echo "  sed -i '' 's|APP_URL=.*|APP_URL=$NGROK_URL|' .env.local"
echo "  sed -i '' 's|APP_PUBLIC_BASE_URL=.*|APP_PUBLIC_BASE_URL=$NGROK_URL|' .env.local"
echo "  docker compose restart alpine"
echo ""
