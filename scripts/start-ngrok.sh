#!/bin/bash

# Script pour démarrer ngrok et exposer l'API Plugame
# Usage: ./scripts/start-ngrok.sh

echo "🚀 Démarrage de ngrok pour Plugame API..."
echo ""
echo "📝 Instructions APRÈS démarrage:"
echo "1. Copiez l'URL HTTPS affichée (ex: https://abc123.ngrok-free.app)"
echo "2. Ouvrez .env.local et remplacez VOTRE_URL par cette URL:"
echo "   APP_URL=https://abc123.ngrok-free.app"
echo "   APP_PUBLIC_BASE_URL=https://abc123.ngrok-free.app"
echo "3. Redémarrez le container PHP:"
echo "   docker compose restart alpine"
echo ""
echo "🌐 Dashboard ngrok: http://localhost:4040"
echo "📧 Test du pixel de tracking: ouvrez l'email de livraison"
echo ""
echo "Appuyez sur Ctrl+C pour arrêter ngrok"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Démarrer ngrok sur le port 8090 (Nginx expose Symfony)
ngrok http 8090
