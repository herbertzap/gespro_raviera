#!/bin/bash

# Script de prueba para el webhook de GitHub
# Este script simula un webhook de GitHub para probar la configuración

echo "=== Probando Webhook de GitHub ==="

# URL del webhook local
WEBHOOK_URL="http://admin-base.test/webhook/github"

# Generar un secret de prueba (debería coincidir con el del .env)
SECRET="test_secret_123"

# Payload de ejemplo (simulando un push a main)
PAYLOAD='{
  "ref": "refs/heads/main",
  "commits": [
    {
      "id": "abc123",
      "message": "Test commit",
      "author": {
        "name": "Test User",
        "email": "test@example.com"
      }
    }
  ],
  "pusher": {
    "name": "Test User"
  }
}'

# Generar la firma HMAC
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" -binary | base64)
SIGNATURE="sha256=$SIGNATURE"

echo "Enviando webhook a: $WEBHOOK_URL"
echo "Payload: $PAYLOAD"
echo "Signature: $SIGNATURE"

# Enviar el webhook
curl -X POST "$WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: $SIGNATURE" \
  -d "$PAYLOAD"

echo ""
echo "=== Webhook enviado ==="
echo "Revisa los logs en: /var/log/gespro_deploy.log"
echo "Para ver los logs en tiempo real: tail -f /var/log/gespro_deploy.log"
