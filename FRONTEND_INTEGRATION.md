# 📱 Guide d'intégration Frontend - Plugame API

Guide pour connecter votre application frontend (React Native, React, Flutter) à l'API Plugame via ngrok.

---

## 🔗 URL de l'API

### Obtenir l'URL ngrok actuelle

```bash
# Démarrer ngrok
./scripts/start-ngrok.sh

# Générer automatiquement la config frontend
./scripts/generate-frontend-config.sh
```

**Résultat** : Vous obtiendrez l'URL complète, par exemple :
```
https://abc123.ngrok-free.app
```

---

## ⚙️ Configuration Frontend

### React Native / Expo

**Fichier : `src/config/api.js`**
```javascript
// Configuration API
export const API_CONFIG = {
  BASE_URL: 'https://abc123.ngrok-free.app',
  ENDPOINTS: {
    // Auth
    LOGIN: '/api/auth/login',
    REGISTER: '/api/auth/register',
    REFRESH_TOKEN: '/api/auth/refresh',

    // Bookings
    BOOKINGS: '/api/bookings',
    BOOKING_DETAIL: (id) => `/api/bookings/${id}`,
    BOOKING_ACCEPT: (id) => `/api/bookings/${id}/accept`,
    BOOKING_DECLINE: (id) => `/api/bookings/${id}/decline`,

    // Deliverables (Photo delivery)
    DELIVERABLE_UPLOAD: '/api/deliverables/upload',
    DELIVERABLE_LIST: (bookingId) => `/api/deliverables/booking/${bookingId}`,
    DELIVERABLE_REQUEST_DOWNLOAD: (bookingId) => `/api/deliverables/request-download/${bookingId}`,

    // Payments
    CREATE_PAYMENT_INTENT: '/api/payments/create-payment-intent',
    CONFIRM_PAYMENT: '/api/payments/confirm',
  }
};

// Mercure (notifications temps réel)
export const MERCURE_CONFIG = {
  HUB_URL: 'https://abc123.ngrok-free.app/.well-known/mercure',
  TOPICS: {
    BOOKING_STATUS: (bookingId) => `/bookings/${bookingId}/status`,
    NEW_MESSAGE: (conversationId) => `/conversations/${conversationId}/messages`,
    DELIVERABLE_UPLOADED: (bookingId) => `/bookings/${bookingId}/deliverables`,
  }
};
```

**Service API : `src/services/api.service.js`**
```javascript
import { API_CONFIG } from '../config/api';

class ApiService {
  constructor() {
    this.baseUrl = API_CONFIG.BASE_URL;
    this.token = null;
  }

  setToken(token) {
    this.token = token;
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers,
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const response = await fetch(url, {
      ...options,
      headers,
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.detail || 'Request failed');
    }

    return response.json();
  }

  // Auth
  async login(username, password) {
    return this.request(API_CONFIG.ENDPOINTS.LOGIN, {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    });
  }

  // Deliverables
  async uploadDeliverable(bookingId, file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('bookingId', bookingId);

    const url = `${this.baseUrl}${API_CONFIG.ENDPOINTS.DELIVERABLE_UPLOAD}`;
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.token}`,
      },
      body: formData,
    });

    return response.json();
  }

  async requestDownload(bookingId) {
    return this.request(
      API_CONFIG.ENDPOINTS.DELIVERABLE_REQUEST_DOWNLOAD(bookingId),
      { method: 'POST' }
    );
  }
}

export default new ApiService();
```

### React / Next.js

**Fichier : `.env.local`**
```env
NEXT_PUBLIC_API_URL=https://abc123.ngrok-free.app
NEXT_PUBLIC_MERCURE_URL=https://abc123.ngrok-free.app/.well-known/mercure
```

**Service API : `lib/api.js`**
```javascript
const API_URL = process.env.NEXT_PUBLIC_API_URL;

export const api = {
  async login(credentials) {
    const res = await fetch(`${API_URL}/api/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(credentials),
    });
    return res.json();
  },

  async uploadDeliverable(bookingId, file, token) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('bookingId', bookingId);

    const res = await fetch(`${API_URL}/api/deliverables/upload`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
      },
      body: formData,
    });
    return res.json();
  },

  async requestDownload(bookingId, token) {
    const res = await fetch(`${API_URL}/api/deliverables/request-download/${bookingId}`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    });
    return res.json();
  },
};
```

### Flutter

**Fichier : `lib/config/api_config.dart`**
```dart
class ApiConfig {
  static const String baseUrl = 'https://abc123.ngrok-free.app';

  // Auth endpoints
  static String get loginUrl => '$baseUrl/api/auth/login';
  static String get registerUrl => '$baseUrl/api/auth/register';

  // Deliverables endpoints
  static String get deliverableUploadUrl => '$baseUrl/api/deliverables/upload';
  static String deliverableListUrl(String bookingId) =>
    '$baseUrl/api/deliverables/booking/$bookingId';
  static String deliverableRequestDownloadUrl(String bookingId) =>
    '$baseUrl/api/deliverables/request-download/$bookingId';

  // Mercure
  static const String mercureUrl = '$baseUrl/.well-known/mercure';
}
```

**Service API : `lib/services/api_service.dart`**
```dart
import 'package:http/http.dart' as http;
import 'dart:convert';
import '../config/api_config.dart';

class ApiService {
  String? _token;

  void setToken(String token) {
    _token = token;
  }

  Future<Map<String, dynamic>> login(String username, String password) async {
    final response = await http.post(
      Uri.parse(ApiConfig.loginUrl),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'username': username,
        'password': password,
      }),
    );

    return jsonDecode(response.body);
  }

  Future<Map<String, dynamic>> uploadDeliverable(
    String bookingId,
    String filePath
  ) async {
    var request = http.MultipartRequest(
      'POST',
      Uri.parse(ApiConfig.deliverableUploadUrl),
    );

    request.headers['Authorization'] = 'Bearer $_token';
    request.fields['bookingId'] = bookingId;
    request.files.add(await http.MultipartFile.fromPath('file', filePath));

    var response = await request.send();
    var responseData = await response.stream.bytesToString();
    return jsonDecode(responseData);
  }
}
```

---

## 🔄 Flux de livraison photo (Frontend)

### 1. Créateur upload des fichiers

```javascript
// React Native exemple
const uploadFiles = async (bookingId, files) => {
  try {
    const results = [];

    for (const file of files) {
      const result = await apiService.uploadDeliverable(bookingId, file);
      results.push(result);
    }

    console.log(`${results.length} fichiers uploadés avec succès`);
    return results;
  } catch (error) {
    console.error('Upload failed:', error);
    throw error;
  }
};
```

### 2. Client paie le reste (70% + 15% frais)

```javascript
// Créer un payment intent
const payRemainingAmount = async (bookingId) => {
  try {
    // 1. Créer le payment intent
    const { clientSecret, amount } = await apiService.request(
      API_CONFIG.ENDPOINTS.CREATE_PAYMENT_INTENT,
      {
        method: 'POST',
        body: JSON.stringify({ bookingId, type: 'remaining' })
      }
    );

    // 2. Utiliser Stripe SDK pour confirmer le paiement
    const { paymentIntent, error } = await stripe.confirmCardPayment(
      clientSecret,
      { payment_method: paymentMethodId }
    );

    if (error) {
      throw new Error(error.message);
    }

    // 3. Confirmer au backend
    await apiService.request(API_CONFIG.ENDPOINTS.CONFIRM_PAYMENT, {
      method: 'POST',
      body: JSON.stringify({
        bookingId,
        paymentIntentId: paymentIntent.id
      })
    });

    console.log('Paiement réussi, fichiers débloqués !');
  } catch (error) {
    console.error('Payment failed:', error);
  }
};
```

### 3. Client demande le lien de téléchargement

```javascript
const requestDownloadLink = async (bookingId) => {
  try {
    const response = await apiService.requestDownload(bookingId);

    // Backend envoie l'email avec le lien
    alert(`Email envoyé ! Le lien expire dans ${response.expiresIn}`);
  } catch (error) {
    if (error.message.includes('payment')) {
      alert('Vous devez payer le reste avant de télécharger');
    } else {
      console.error('Download request failed:', error);
    }
  }
};
```

### 4. Écouter les notifications Mercure

```javascript
// Écouter les changements de statut du booking
const subscribeToBookingUpdates = (bookingId) => {
  const topic = MERCURE_CONFIG.TOPICS.BOOKING_STATUS(bookingId);
  const url = new URL(MERCURE_CONFIG.HUB_URL);
  url.searchParams.append('topic', topic);

  const eventSource = new EventSource(url.toString());

  eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);

    console.log('Booking updated:', data);

    // Mettre à jour l'UI
    if (data.status === 'deliverables_uploaded') {
      showNotification('Vos fichiers sont prêts !');
    } else if (data.status === 'payout_completed') {
      showNotification('Paiement reçu !');
    }
  };

  return eventSource;
};
```

---

## 📋 Checklist d'intégration

- [ ] ngrok démarré et URL copiée
- [ ] Config frontend mise à jour avec l'URL ngrok
- [ ] Test de connexion (login)
- [ ] Test d'upload de fichiers (créateur)
- [ ] Test de paiement (client)
- [ ] Test de demande de téléchargement (client)
- [ ] Email reçu avec lien et détails paiement
- [ ] Mercure notifications fonctionnent
- [ ] Pixel de tracking déclenche le payout

---

## 🚨 Erreurs courantes

### Error 402: "You must pay the remaining amount"

**Cause** : Le client n'a pas payé le reste (70% + 15%).

**Solution** : Compléter le paiement avant de demander le téléchargement.

### Error 403: "Only the athlete can request downloads"

**Cause** : Vous essayez de télécharger avec le token du créateur.

**Solution** : Utiliser le token de l'athlète (client).

### CORS Error

**Cause** : L'origine du frontend n'est pas autorisée.

**Solution** : Vérifier la config CORS dans le backend Symfony.

---

## 🔄 Workflow quotidien

### Chaque matin (si ngrok redémarre)

```bash
# 1. Backend : Démarrer ngrok
./scripts/start-ngrok.sh
# URL: https://xyz789.ngrok-free.app

# 2. Backend : Générer la config
./scripts/generate-frontend-config.sh

# 3. Backend : Envoyer au frontend
cat frontend-config.json

# 4. Frontend : Mettre à jour l'URL
# React Native : src/config/api.js
# React : .env.local
# Flutter : lib/config/api_config.dart

# 5. Frontend : Redémarrer l'app
```

---

## 📞 Support

Si vous rencontrez des problèmes :

1. Vérifiez le **dashboard ngrok** : http://localhost:4040
2. Vérifiez les logs backend : `docker compose logs -f alpine`
3. Vérifiez que l'URL ngrok est bien à jour dans la config frontend

---

**🎉 Votre frontend est maintenant connecté à l'API Plugame !**
