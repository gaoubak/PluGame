# Comments API Guide

## ✅ How Comments Work

In PluGame, comments are linked to **Users** (creators), not to individual Post entities. Each comment is on a creator's feed/profile.

---

## 🎯 Correct API Usage

### POST `/api/feed/{userId}/comments`

**IMPORTANT:** The `{userId}` parameter must be the **User's integer ID**, not a UUID!

#### ✅ Correct Usage

```bash
POST /api/feed/25/comments
```

Where `25` is the creator's integer `user.id` from the feed response.

#### ❌ Incorrect Usage

```bash
POST /api/feed/f9bb8a4b-cad5-469c-b400-03864f0cad40/comments
```

This UUID is likely a media asset ID or another entity - **NOT** the user ID!

---

## 📱 Frontend Integration

### Getting the User ID from Feed

When you fetch the feed, you get creator profiles with user information:

```json
{
  "results": [
    {
      "creatorProfile": {
        "displayName": "Sophie Dubois",
        "user": {
          "id": 25,  // ← USE THIS for comments!
          "username": "creator1",
          "fullName": "Sophie Dubois",
          "userPhoto": "https://..."
        },
        "medias": [
          {
            "id": "f9bb8a4b-cad5-469c-b400-03864f0cad40",  // ← This is media UUID, NOT user ID!
            "type": "image",
            "url": "https://..."
          }
        ]
      }
    }
  ]
}
```

### Correct Comment Request

```typescript
// React Native Example
const createComment = async (creator, content) => {
  const userId = creator.user.id; // Integer ID, e.g., 25

  const response = await fetch(`https://api.plugame.com/api/feed/${userId}/comments`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      content: content
    })
  });

  return await response.json();
};

// Usage
createComment(creatorProfile, "Great photo!");
```

---

## 🎨 UI/UX Best Practices

### Comment Input Position

❌ **Bad UX - Input at Top:**
```
┌─────────────────────┐
│  [Comment Input]    │  ← Bad: User has to scroll up to comment
├─────────────────────┤
│                     │
│  Comment 1          │
│  Comment 2          │
│  Comment 3          │
│  ...                │
│                     │
└─────────────────────┘
```

✅ **Good UX - Input at Bottom:**
```
┌─────────────────────┐
│                     │
│  Comment 1          │
│  Comment 2          │
│  Comment 3          │
│  ...                │
├─────────────────────┤
│  [Comment Input]    │  ← Good: Natural typing position
└─────────────────────┘
```

### Recommended Layout

```typescript
// React Native Component Structure
<View style={styles.container}>
  {/* Header with creator info */}
  <CreatorHeader creator={creator} />

  {/* Scrollable comments list */}
  <FlatList
    data={comments}
    renderItem={({ item }) => <CommentItem comment={item} />}
    keyExtractor={(item) => item.id}
    inverted={false}  // Normal order (oldest first)
  />

  {/* Fixed input at bottom */}
  <KeyboardAvoidingView behavior="padding">
    <CommentInput
      onSubmit={(text) => createComment(creator.user.id, text)}
      placeholder="Tapez un message..."
    />
  </KeyboardAvoidingView>
</View>
```

---

## 📝 Complete API Reference

### 1. Get Comments

```bash
GET /api/feed/{userId}/comments
```

**Parameters:**
- `userId` (path, integer, required) - Creator's user ID
- `page` (query, integer, optional) - Page number, default: 1
- `limit` (query, integer, optional) - Items per page, default: 20
- `sortBy` (query, string, optional) - Sort order: `newest`, `oldest`, `popular`

**Response:**
```json
[
  {
    "id": "comment-uuid",
    "content": "Great content!",
    "user": {
      "id": 10,
      "username": "athlete1",
      "fullName": "Jean Martin",
      "userPhoto": "https://..."
    },
    "likesCount": 5,
    "repliesCount": 2,
    "isLiked": false,
    "createdAt": "2025-11-22T10:30:00+00:00",
    "updatedAt": "2025-11-22T10:30:00+00:00"
  }
]
```

---

### 2. Create Comment

```bash
POST /api/feed/{userId}/comments
```

**Request:**
```json
{
  "content": "Great photo!"
}
```

**Response:**
```json
{
  "id": "comment-uuid",
  "content": "Great photo!",
  "user": {
    "id": 10,
    "username": "athlete1",
    "fullName": "Jean Martin"
  },
  "likesCount": 0,
  "repliesCount": 0,
  "isLiked": false,
  "createdAt": "2025-11-22T10:30:00+00:00"
}
```

---

### 3. Reply to Comment

```bash
POST /api/comments/{commentId}/replies
```

**Request:**
```json
{
  "content": "Thanks!"
}
```

---

### 4. Like Comment

```bash
POST /api/comments/{commentId}/like
```

---

### 5. Unlike Comment

```bash
DELETE /api/comments/{commentId}/like
```

---

### 6. Edit Comment

```bash
PUT /api/comments/{commentId}
```

**Request:**
```json
{
  "content": "Updated comment text"
}
```

---

### 7. Delete Comment

```bash
DELETE /api/comments/{commentId}
```

---

## 🐛 Common Errors

### Error: "Post not found"

**Cause:** Sending a UUID instead of user ID

```bash
# ❌ Wrong
POST /api/feed/f9bb8a4b-cad5-469c-b400-03864f0cad40/comments

# ✅ Correct
POST /api/feed/25/comments
```

**Solution:** Extract `user.id` from the creator profile, not the media ID!

---

### Error: "AxiosError: Request..."

**Cause:** Invalid endpoint or authentication issue

**Solution:**
1. Verify you're using integer user ID
2. Check JWT token is valid
3. Check network connectivity
4. Verify API URL is correct

---

## 🔍 Debugging Tips

### Find the Correct User ID

```typescript
// When you have a creator profile from the feed:
console.log('Creator:', creator);
console.log('User ID:', creator.user.id);  // This is what you need!
console.log('Media IDs:', creator.medias.map(m => m.id));  // These are NOT user IDs!

// Correct usage:
const commentEndpoint = `/api/feed/${creator.user.id}/comments`;
```

---

## ✅ Summary

| Item | Value |
|------|-------|
| **Endpoint** | `/api/feed/{userId}/comments` |
| **userId Type** | Integer (e.g., `25`) |
| **NOT UUID** | Media asset IDs are UUIDs, don't use them! |
| **Get ID from** | `creator.user.id` in feed response |
| **Input Position** | Bottom of screen (better UX) |
| **Authentication** | JWT token required |

---

## 🚀 Quick Fix for Frontend

If you're getting "Post not found" errors, update your comment submission:

```typescript
// ❌ Before (wrong)
const userId = media.id; // This is a UUID!
await fetch(`/api/feed/${userId}/comments`, ...);

// ✅ After (correct)
const userId = creator.user.id; // This is an integer!
await fetch(`/api/feed/${userId}/comments`, ...);
```

---

The backend is correct - it expects integer user IDs. The issue is in the frontend sending UUIDs instead of user IDs! 🎉
