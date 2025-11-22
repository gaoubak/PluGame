# User Photo Changes - Summary

## ✅ Changes Made

### 1. **Fixtures Updated** - No More Cover Photos

#### Athletes ([AppFixtures.php](src/DataFixtures/AppFixtures.php) line 223)
- ✅ Changed from `avatarUrl` to `userPhoto`
- ✅ No cover photos for athletes

**Before:**
```php
$user->setAvatarUrl("https://i.pravatar.cc/300?img={$i}");
```

**After:**
```php
$user->setUserPhoto("https://i.pravatar.cc/300?img={$i}");
```

#### Creators ([AppFixtures.php](src/DataFixtures/AppFixtures.php) line 274-275)
- ✅ Changed from `avatarUrl` to `userPhoto`
- ✅ Removed `setCoverUrl()` call
- ✅ Removed `setCoverPhoto()` from CreatorProfile (line 295)

**Before:**
```php
$user->setAvatarUrl("https://i.pravatar.cc/300?img=" . ($count + $i));
$user->setCoverUrl("https://picsum.photos/1200/400?random={$i}");
// ...
$profile->setCoverPhoto("https://picsum.photos/1200/300?random=creator_cover_{$i}");
```

**After:**
```php
$user->setUserPhoto("https://i.pravatar.cc/300?img=" . ($count + $i));
// No setCoverUrl
// No setCoverPhoto
```

---

### 2. **Feed Updated** - Returns userPhoto

The feed now returns complete user information including `userPhoto`:

#### CreatorFeedService ([src/Service/Feed/CreatorFeedService.php](src/Service/Feed/CreatorFeedService.php) line 123-129)

**Response format:**
```json
{
  "results": [
    {
      "creatorProfile": {
        "displayName": "Sophie Dubois",
        "city": "Paris",
        "rating": "4.5",
        "ratingsCount": 12,
        "user": {
          "id": 123,
          "username": "creator1",
          "fullName": "Sophie Dubois",
          "userPhoto": "https://i.pravatar.cc/300?img=21",
          "isVerified": true
        },
        "medias": [
          {
            "id": "uuid",
            "type": "image",
            "url": "https://...",
            "aspectRatio": "16:9",
            "width": 1920,
            "height": 1080
          }
        ]
      }
    }
  ]
}
```

**Fields added:**
- ✅ `fullName` - User's full name
- ✅ `userPhoto` - User's photo URL (already existed, now populated)
- ✅ `isVerified` - Verification status

---

## 🗑️ Cover Photos Removed

### What Was Removed

1. **User.coverUrl** - No longer set in fixtures
2. **User.coverPhoto** - No longer set in fixtures
3. **CreatorProfile.coverPhoto** - No longer set in fixtures

### Database Fields Still Exist

The database columns still exist but are not populated:
- `user.cover_url` - Will be `NULL`
- `user.cover_photo` - Will be `NULL`
- `creator_profile.cover_photo` - Will be `NULL`

### Optional: Drop Cover Photo Columns

If you want to completely remove cover photos from the database, create a migration:

```bash
docker compose exec alpine php bin/console doctrine:migrations:diff
```

This will generate a migration that drops:
- `user.cover_url`
- `user.cover_photo`
- `creator_profile.cover_photo`

**Note:** Only do this if you're sure you'll never use cover photos!

---

## 📊 User Photo Priority Logic

The `getAvatarUrl()` method in User entity already uses the correct priority:

```php
public function getAvatarUrl(): ?string
{
    // Priority: userPhoto first, then fallback to avatarUrl
    return $this->userPhoto ?? $this->avatarUrl;
}
```

This means:
1. ✅ If `userPhoto` exists → use it
2. ✅ If `userPhoto` is null → fallback to `avatarUrl`

---

## 🧪 Testing

### 1. Reload Fixtures

```bash
docker compose exec alpine php bin/console doctrine:fixtures:load --no-interaction
```

### 2. Check User Photos

All users should now have `userPhoto` populated:

```bash
docker compose exec alpine php bin/console dbal:run-sql \
  "SELECT id, username, user_photo, avatar_url, cover_url FROM user LIMIT 10"
```

**Expected result:**
- `user_photo`: `https://i.pravatar.cc/300?img=X`
- `avatar_url`: `NULL`
- `cover_url`: `NULL`

### 3. Check Feed API

**GET** `/api/feed`

```json
{
  "results": [
    {
      "creatorProfile": {
        "user": {
          "userPhoto": "https://i.pravatar.cc/300?img=21",
          "fullName": "Sophie Dubois",
          "isVerified": true
        }
      }
    }
  ]
}
```

---

## 📝 Summary

### What Changed

✅ All users now use `userPhoto` instead of `avatarUrl`
✅ No cover photos are created in fixtures
✅ Feed returns `userPhoto`, `fullName`, and `isVerified`
✅ Feed includes aspect ratio for all media

### Database State After Fixtures

| Field | Value |
|-------|-------|
| `user.user_photo` | `https://i.pravatar.cc/300?img=X` ✅ |
| `user.avatar_url` | `NULL` |
| `user.cover_url` | `NULL` |
| `user.cover_photo` | `NULL` |
| `creator_profile.cover_photo` | `NULL` |

### API Response

The feed now returns:
```json
{
  "user": {
    "id": 123,
    "username": "creator1",
    "fullName": "Sophie Dubois",
    "userPhoto": "https://i.pravatar.cc/300?img=21",
    "isVerified": true
  }
}
```

---

## 🚀 Ready to Use!

Run the fixtures and test the feed:

```bash
docker compose exec alpine php bin/console doctrine:fixtures:load --no-interaction
```

Then call the feed API - all creator profiles will have `userPhoto` populated! 🎉
