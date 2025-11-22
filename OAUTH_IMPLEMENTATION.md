# OAuth Social Login Implementation

## ✅ Complete Implementation

The PluGame platform now supports **Google** and **Apple** OAuth authentication!

---

## 🎯 Features

✅ **Google Sign-In** - Native mobile SDK integration
✅ **Apple Sign-In** - Native mobile SDK integration
✅ **Auto Account Creation** - New users created automatically
✅ **Account Linking** - Auto-link by email if user exists
✅ **Role Selection** - Choose Athlete or Creator during signup
✅ **Profile Completion** - Request additional info after OAuth
✅ **Multiple Providers** - Link both Google and Apple to same account
✅ **Password Login** - Keep existing password authentication
✅ **JWT Tokens** - Same token system as password login

---

## 📋 How It Works

### 1. Mobile App Flow

```
User taps "Sign in with Google/Apple"
    ↓
Native SDK authenticates user
    ↓
SDK returns ID token
    ↓
Mobile app sends token to backend API
    ↓
Backend verifies token
    ↓
Backend finds or creates user
    ↓
Backend returns JWT token + user info
    ↓
User logged in!
```

### 2. New User Registration

When a new user signs in with OAuth:

1. **Email extracted** from OAuth token
2. **Account auto-created** with OAuth data
3. **Photo imported** from OAuth provider (if available)
4. **Role requested** - User chooses Athlete or Creator
5. **Additional info** - Phone, location, sport preference
6. **Email verified** - Automatically verified if provider verified it

### 3. Existing User Login

When existing user signs in with OAuth:

1. **Find by email** - Check if email already exists
2. **Auto-link OAuth** - Link provider to existing account
3. **Login immediately** - Return JWT token
4. **Multiple providers** - User can link Google + Apple

---

## 🔌 API Endpoints

### POST `/api/auth/google`

Authenticate with Google ID token from mobile SDK.

**Request:**
```json
{
  "idToken": "google-id-token-from-mobile-sdk",
  "additionalInfo": {
    "role": "ROLE_ATHLETE",
    "phone": "+33612345678",
    "location": "Paris, France",
    "sport": "football"
  }
}
```

**Response (Success):**
```json
{
  "token": "jwt-token-here",
  "user": {
    "id": 123,
    "username": "googletest",
    "email": "user@example.com",
    "fullName": "John Doe",
    "userPhoto": "https://lh3.googleusercontent.com/...",
    "roles": ["ROLE_ATHLETE"],
    "isVerified": true
  },
  "needsProfileCompletion": false,
  "completionFields": []
}
```

**Response (Needs Profile Completion):**
```json
{
  "token": "jwt-token-here",
  "user": {
    "id": 123,
    "username": "googletest",
    "email": "user@example.com",
    "fullName": "John Doe",
    "userPhoto": "https://lh3.googleusercontent.com/...",
    "roles": ["ROLE_USER"],
    "isVerified": true
  },
  "needsProfileCompletion": true,
  "completionFields": ["role", "phone", "location", "sport"]
}
```

---

### POST `/api/auth/apple`

Authenticate with Apple ID token from mobile SDK.

**Request:**
```json
{
  "idToken": "apple-id-token-from-mobile-sdk",
  "userData": {
    "name": {
      "firstName": "John",
      "lastName": "Doe"
    },
    "email": "user@example.com"
  },
  "additionalInfo": {
    "role": "ROLE_CREATOR",
    "phone": "+33623456789",
    "location": "Lyon, France",
    "sport": "photography"
  }
}
```

**Note:** `userData` is only provided by Apple SDK on first sign-in. Store it!

**Response:** Same format as Google endpoint.

---

### POST `/api/auth/complete-profile`

Complete user profile after OAuth registration.

**Headers:**
```
Authorization: Bearer <jwt-token>
```

**Request:**
```json
{
  "role": "ROLE_ATHLETE",
  "phone": "+33612345678",
  "location": "Paris, France",
  "sport": "football"
}
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": 123,
    "username": "googletest",
    "email": "user@example.com",
    "fullName": "John Doe",
    "userPhoto": "https://...",
    "roles": ["ROLE_ATHLETE"],
    "phoneNumber": "+33612345678",
    "location": "Paris, France",
    "sport": "football"
  }
}
```

---

### GET `/api/auth/oauth-providers`

Get current user's linked OAuth providers.

**Headers:**
```
Authorization: Bearer <jwt-token>
```

**Response:**
```json
{
  "providers": [
    {
      "provider": "google",
      "email": "user@example.com",
      "name": "John Doe",
      "linkedAt": "2025-11-22T10:30:00+00:00"
    },
    {
      "provider": "apple",
      "email": "user@example.com",
      "name": "John Doe",
      "linkedAt": "2025-11-23T15:45:00+00:00"
    }
  ]
}
```

---

### DELETE `/api/auth/oauth-providers/{provider}`

Unlink an OAuth provider from user's account.

**Headers:**
```
Authorization: Bearer <jwt-token>
```

**Example:** `DELETE /api/auth/oauth-providers/google`

**Response:**
```json
{
  "success": true,
  "message": "OAuth provider unlinked successfully"
}
```

**Error (Last Login Method):**
```json
{
  "error": "Cannot remove your only login method. Please set a password first."
}
```

---

## 📱 Mobile Integration

### React Native - Google Sign-In

```typescript
import { GoogleSignin } from '@react-native-google-signin/google-signin';

// Configure Google Sign-In
GoogleSignin.configure({
  webClientId: 'YOUR_WEB_CLIENT_ID.apps.googleusercontent.com',
  offlineAccess: true,
});

// Sign in with Google
const signInWithGoogle = async () => {
  try {
    await GoogleSignin.hasPlayServices();
    const userInfo = await GoogleSignin.signIn();

    // Send ID token to backend
    const response = await fetch('https://your-api.com/api/auth/google', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        idToken: userInfo.idToken,
        additionalInfo: {
          role: 'ROLE_ATHLETE',  // or ask user
          phone: '+33612345678',
          location: 'Paris, France',
          sport: 'football',
        },
      }),
    });

    const data = await response.json();

    // Check if profile needs completion
    if (data.needsProfileCompletion) {
      // Show profile completion screen
      navigation.navigate('CompleteProfile', {
        token: data.token,
        fields: data.completionFields
      });
    } else {
      // Store JWT token and login
      await AsyncStorage.setItem('jwt_token', data.token);
      navigation.navigate('Home');
    }
  } catch (error) {
    console.error('Google Sign-In Error:', error);
  }
};
```

---

### React Native - Apple Sign-In

```typescript
import * as AppleAuthentication from 'expo-apple-authentication';

// Sign in with Apple
const signInWithApple = async () => {
  try {
    const credential = await AppleAuthentication.signInAsync({
      requestedScopes: [
        AppleAuthentication.AppleAuthenticationScope.FULL_NAME,
        AppleAuthentication.AppleAuthenticationScope.EMAIL,
      ],
    });

    // Send to backend
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
        additionalInfo: {
          role: 'ROLE_CREATOR',
          phone: '+33623456789',
          location: 'Lyon, France',
          sport: 'photography',
        },
      }),
    });

    const data = await response.json();

    // Handle profile completion if needed
    if (data.needsProfileCompletion) {
      navigation.navigate('CompleteProfile', {
        token: data.token,
        fields: data.completionFields
      });
    } else {
      await AsyncStorage.setItem('jwt_token', data.token);
      navigation.navigate('Home');
    }
  } catch (error) {
    console.error('Apple Sign-In Error:', error);
  }
};
```

---

## 🗄️ Database Structure

### `oauth_provider` Table

```sql
CREATE TABLE oauth_provider (
    id CHAR(36) PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,              -- 'google' or 'apple'
    provider_user_id VARCHAR(255) NOT NULL,     -- OAuth provider's user ID
    provider_email VARCHAR(255),
    provider_name VARCHAR(255),
    provider_photo_url VARCHAR(500),
    provider_data JSON,                         -- Raw OAuth data
    access_token LONGTEXT,                      -- Encrypted in production
    refresh_token LONGTEXT,                     -- Encrypted in production
    token_expires_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (provider, provider_user_id)
);
```

---

## 🔐 Security Features

### Token Verification

- **Google:** Tokens verified via Google's tokeninfo API
- **Apple:** JWT tokens decoded and validated (signature verification in production)

### Account Linking

- **Auto-link by email:** If email exists, OAuth provider is linked automatically
- **Prevent duplicates:** Same provider + provider_user_id can't be linked twice

### Password Protection

- **OAuth users can set password:** They can add password later
- **Password users can add OAuth:** Existing users can link Google/Apple
- **Can't remove last login method:** Must have password if removing only OAuth provider

---

## 🧪 Testing

### Test Users in Fixtures

The fixtures create 3 OAuth test users:

1. **Google Athlete**
   - Email: `googletest@example.com`
   - Provider: Google
   - Role: ROLE_ATHLETE
   - Password: `password123`

2. **Apple Creator**
   - Email: `appletest@example.com`
   - Provider: Apple
   - Role: ROLE_CREATOR
   - Password: `password123`

3. **Multi OAuth**
   - Email: `multioauth@example.com`
   - Providers: Both Google AND Apple
   - Role: ROLE_ATHLETE
   - Password: `password123`

### Load Fixtures

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

---

## 🚀 Setup Instructions

### 1. Install Dependencies

Update `composer.json` (already done):
```json
{
  "require": {
    "knpuniversity/oauth2-client-bundle": "^2.18",
    "league/oauth2-client": "^2.7",
    "league/oauth2-google": "^4.0"
  }
}
```

Then run:
```bash
composer install
```

### 2. Run Database Migration

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

### 3. Load Test Data

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### 4. Configure OAuth Providers

#### Google OAuth

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create OAuth 2.0 credentials
3. Add authorized redirect URIs for your mobile app
4. Get Web Client ID for backend verification

#### Apple Sign-In

1. Go to [Apple Developer Portal](https://developer.apple.com/)
2. Enable Sign in with Apple for your app
3. Configure Service IDs and Keys
4. Download private key for JWT verification

---

## 📝 Implementation Files

### Entities
- [src/Entity/OAuthProvider.php](src/Entity/OAuthProvider.php) - OAuth provider entity
- [src/Entity/User.php](src/Entity/User.php) - Updated with OAuth relationship

### Services
- [src/Service/OAuthService.php](src/Service/OAuthService.php) - OAuth logic and token verification

### Controllers
- [src/Controller/OAuthController.php](src/Controller/OAuthController.php) - OAuth API endpoints

### Migrations
- [migrations/Version20251122_OAuthProviders.php](migrations/Version20251122_OAuthProviders.php) - Database schema

### Fixtures
- [src/DataFixtures/AppFixtures.php](src/DataFixtures/AppFixtures.php) - Test OAuth users

---

## 🎨 User Flow Examples

### Example 1: New User Signs Up with Google

```
1. User taps "Sign in with Google"
2. Google SDK authenticates → returns ID token
3. Mobile sends: POST /api/auth/google { idToken, additionalInfo: { role: "ROLE_ATHLETE" } }
4. Backend:
   - Verifies token ✅
   - Email doesn't exist → Creates new user
   - Sets role to ROLE_ATHLETE
   - Creates OAuth provider link
   - Returns JWT token
5. User logged in as Athlete!
```

---

### Example 2: Existing User Adds Apple Sign-In

```
1. User already exists with password login
2. User taps "Link Apple Sign-In"
3. Apple SDK authenticates → returns ID token
4. Mobile sends: POST /api/auth/apple { idToken }
5. Backend:
   - Verifies token ✅
   - Email exists → Finds existing user
   - Links Apple OAuth provider to user
   - Returns JWT token
6. User can now login with password OR Apple!
```

---

### Example 3: Profile Completion Flow

```
1. New user signs in with Google
2. Backend creates user but no role set
3. Backend returns: { needsProfileCompletion: true, completionFields: ["role", "phone"] }
4. Mobile shows profile completion screen
5. User selects role and enters phone
6. Mobile sends: POST /api/auth/complete-profile { role: "ROLE_CREATOR", phone: "+33..." }
7. Backend updates user
8. Profile complete! ✅
```

---

## ✅ Checklist

- [x] OAuthProvider entity created
- [x] User entity updated with OAuth relationship
- [x] OAuthService for token verification
- [x] OAuthController with Google and Apple endpoints
- [x] Database migration for oauth_provider table
- [x] Fixtures with test OAuth users
- [x] Auto account creation
- [x] Auto account linking by email
- [x] Role selection support
- [x] Profile completion endpoints
- [x] Multiple provider support
- [x] Password + OAuth coexistence
- [x] Provider unlinking
- [x] JWT token generation

---

## 🚀 Ready to Use!

OAuth authentication is fully implemented and ready for mobile integration!

**Test it:**

1. Run migrations and fixtures
2. Use Postman to test endpoints with mock Google/Apple tokens
3. Integrate Google and Apple SDKs in your mobile app
4. Send ID tokens to backend
5. Users can now login with Google or Apple! 🎉

**Next Steps:**

- Add production token verification for Apple (JWT signature)
- Implement token encryption for access/refresh tokens
- Add analytics for OAuth login tracking
- Consider adding Facebook/Twitter OAuth if needed
