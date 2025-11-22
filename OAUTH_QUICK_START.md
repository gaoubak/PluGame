# OAuth Quick Start Guide

## 🚀 Get OAuth Running in 5 Minutes

### Step 1: Install Dependencies

```bash
composer install
```

The required packages are already added to `composer.json`:
- `knpuniversity/oauth2-client-bundle`
- `league/oauth2-client`
- `league/oauth2-google`

---

### Step 2: Run Database Migration

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

This creates the `oauth_provider` table.

---

### Step 3: Load Test Data (Optional)

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

This creates 3 OAuth test users:
- **Google Athlete:** `googletest@example.com`
- **Apple Creator:** `appletest@example.com`
- **Multi OAuth:** `multioauth@example.com` (both Google + Apple)

All passwords: `password123`

---

### Step 4: Test the Endpoints

#### Test Google Login

```bash
curl -X POST http://localhost/api/auth/google \
  -H "Content-Type: application/json" \
  -d '{
    "idToken": "mock-google-token",
    "additionalInfo": {
      "role": "ROLE_ATHLETE",
      "phone": "+33612345678",
      "location": "Paris, France",
      "sport": "football"
    }
  }'
```

#### Test Apple Login

```bash
curl -X POST http://localhost/api/auth/apple \
  -H "Content-Type: application/json" \
  -d '{
    "idToken": "mock-apple-token",
    "userData": {
      "name": {
        "firstName": "John",
        "lastName": "Doe"
      },
      "email": "john@example.com"
    },
    "additionalInfo": {
      "role": "ROLE_CREATOR"
    }
  }'
```

---

## 📱 Mobile Integration

### Google Sign-In (React Native)

```bash
npm install @react-native-google-signin/google-signin
```

```typescript
import { GoogleSignin } from '@react-native-google-signin/google-signin';

GoogleSignin.configure({
  webClientId: 'YOUR_WEB_CLIENT_ID.apps.googleusercontent.com',
});

const { idToken } = await GoogleSignin.signIn();

const response = await fetch('https://your-api.com/api/auth/google', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    idToken,
    additionalInfo: { role: 'ROLE_ATHLETE' }
  }),
});

const { token, user } = await response.json();
```

---

### Apple Sign-In (React Native with Expo)

```bash
expo install expo-apple-authentication
```

```typescript
import * as AppleAuthentication from 'expo-apple-authentication';

const credential = await AppleAuthentication.signInAsync({
  requestedScopes: [
    AppleAuthentication.AppleAuthenticationScope.FULL_NAME,
    AppleAuthentication.AppleAuthenticationScope.EMAIL,
  ],
});

const response = await fetch('https://your-api.com/api/auth/apple', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    idToken: credential.identityToken,
    userData: credential.fullName ? {
      name: {
        firstName: credential.fullName.givenName,
        lastName: credential.fullName.familyName,
      },
      email: credential.email,
    } : null,
    additionalInfo: { role: 'ROLE_CREATOR' }
  }),
});

const { token, user } = await response.json();
```

---

## 🔑 Get OAuth Credentials

### Google OAuth

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select existing)
3. Enable **Google Sign-In API**
4. Create **OAuth 2.0 Client ID**
5. Add your mobile app's package name
6. Download JSON config
7. Use **Web Client ID** for token verification

### Apple Sign-In

1. Go to [Apple Developer Portal](https://developer.apple.com/)
2. Enable **Sign in with Apple** capability
3. Create a **Service ID**
4. Register your app's bundle ID
5. Create a **Key** for Sign in with Apple
6. Download the private key

---

## 📋 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/google` | Login/Register with Google |
| POST | `/api/auth/apple` | Login/Register with Apple |
| POST | `/api/auth/complete-profile` | Complete profile after OAuth |
| GET | `/api/auth/oauth-providers` | List linked OAuth providers |
| DELETE | `/api/auth/oauth-providers/{provider}` | Unlink provider |

---

## ✅ What You Get

✅ Google and Apple authentication
✅ Auto account creation
✅ Auto account linking by email
✅ Role selection (Athlete/Creator)
✅ Profile completion flow
✅ JWT token generation
✅ Multiple providers per user
✅ Password + OAuth coexistence

---

## 🎯 User Flow

```
Mobile App → Google/Apple SDK → Get ID Token
    ↓
Send token to /api/auth/google or /api/auth/apple
    ↓
Backend verifies token → Creates or finds user
    ↓
Returns JWT token + user data
    ↓
App stores JWT token → User logged in!
```

---

## 🧪 Testing

### Test with Real Tokens (Development)

1. **Google:** Get ID token from Google Sign-In SDK
2. **Apple:** Get identity token from Apple Sign-In
3. Send to API endpoints
4. Check response for JWT token

### Test with Fixtures

```bash
# Login as Google test user
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"username": "googletest@example.com", "password": "password123"}'

# Get their OAuth providers
curl -X GET http://localhost/api/auth/oauth-providers \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

## 📖 Full Documentation

See [OAUTH_IMPLEMENTATION.md](OAUTH_IMPLEMENTATION.md) for complete details on:
- API request/response formats
- Security features
- Database schema
- Mobile integration examples
- Error handling
- Account linking logic

---

## 🚀 You're Ready!

OAuth authentication is now live. Start integrating Google and Apple Sign-In in your mobile app! 🎉
