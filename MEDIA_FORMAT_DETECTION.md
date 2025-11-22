# Media Format Auto-Detection Guide

## Overview

Plugame now automatically detects whether uploaded media is an **IMAGE** or **VIDEO** based on the file's MIME type. No manual specification needed!

---

## 🎯 How It Works

### Automatic Detection

When a creator uploads media to their feed, the system:

1. **Reads the MIME type** from the uploaded file (e.g., `image/jpeg`, `video/mp4`)
2. **Auto-detects the format** using smart logic
3. **Stores the type** in the `MediaAsset.type` field (`IMAGE` or `VIDEO`)
4. **Returns the detected type** in the API response

### Detection Logic

```php
private function detectMediaType(string $mimeType): string
{
    // Primary: Check MIME type prefix
    if (str_starts_with($mimeType, 'image/')) {
        return 'IMAGE';  // image/jpeg, image/png, image/gif, etc.
    }

    if (str_starts_with($mimeType, 'video/')) {
        return 'VIDEO';  // video/mp4, video/quicktime, video/webm, etc.
    }

    // Fallback: Check file extension
    // (in case MIME type is incorrect or generic)

    // Images: jpg, jpeg, png, gif, webp, heic, heif, svg, bmp, tiff
    // Videos: mp4, mov, avi, mkv, webm, m4v, flv, wmv, 3gp

    // Default: IMAGE
}
```

---

## 📸 Supported Formats

### Images (Detected as `IMAGE`)
- **JPEG** - `image/jpeg` (`.jpg`, `.jpeg`)
- **PNG** - `image/png` (`.png`)
- **GIF** - `image/gif` (`.gif`)
- **WebP** - `image/webp` (`.webp`)
- **HEIC/HEIF** - `image/heic`, `image/heif` (iPhone photos)
- **SVG** - `image/svg+xml` (`.svg`)
- **BMP** - `image/bmp` (`.bmp`)
- **TIFF** - `image/tiff` (`.tiff`)

### Videos (Detected as `VIDEO`)
- **MP4** - `video/mp4` (`.mp4`, `.m4v`)
- **MOV** - `video/quicktime` (`.mov`) - iPhone videos
- **WebM** - `video/webm` (`.webm`)
- **AVI** - `video/x-msvideo` (`.avi`)
- **MKV** - `video/x-matroska` (`.mkv`)
- **FLV** - `video/x-flv` (`.flv`)
- **WMV** - `video/x-ms-wmv` (`.wmv`)
- **3GP** - `video/3gpp` (`.3gp`) - Old mobile videos

---

## 🔌 API Usage

### Upload Endpoint

**POST** `/api/media/upload`

```bash
curl -X POST https://your-api.ngrok-free.dev/api/media/upload \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@photo.jpg" \
  -F "purpose=CREATOR_FEED"
```

**Response:**
```json
{
  "id": "uuid",
  "purpose": "CREATOR_FEED",
  "key": "creator/uuid.jpg",
  "publicUrl": "https://cdn.plugame.com/creator/uuid.jpg",
  "owner": 123,
  "type": "IMAGE",          // ✅ Auto-detected
  "contentType": "image/jpeg"
}
```

### Register Endpoint (Direct Upload)

**POST** `/api/media/register`

```bash
curl -X POST https://your-api.ngrok-free.dev/api/media/register \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "purpose": "CREATOR_FEED",
    "key": "creator/uuid.mp4",
    "filename": "awesome_video.mp4",
    "contentType": "video/mp4",
    "bytes": 5242880
  }'
```

**Response:**
```json
{
  "id": "uuid",
  "purpose": "CREATOR_FEED",
  "key": "creator/uuid.mp4",
  "publicUrl": "https://cdn.plugame.com/creator/uuid.mp4",
  "owner": 123,
  "type": "VIDEO",          // ✅ Auto-detected
  "contentType": "video/mp4"
}
```

---

## 🎨 Frontend Integration

### React Native Example

```typescript
import { launchImageLibrary, launchCamera } from 'react-native-image-picker';

const uploadToFeed = async () => {
  // Let user pick image or video
  const result = await launchImageLibrary({
    mediaType: 'mixed',  // Allow both photos and videos
    quality: 0.8,
  });

  if (result.didCancel || !result.assets?.[0]) return;

  const asset = result.assets[0];
  const formData = new FormData();

  formData.append('file', {
    uri: asset.uri,
    type: asset.type,  // e.g., 'image/jpeg' or 'video/mp4'
    name: asset.fileName || 'upload',
  });
  formData.append('purpose', 'CREATOR_FEED');

  const response = await fetch('https://api.plugame.com/api/media/upload', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
    },
    body: formData,
  });

  const data = await response.json();

  // Backend automatically detected the type!
  console.log('Uploaded:', data.type);  // 'IMAGE' or 'VIDEO'
  console.log('URL:', data.publicUrl);
};
```

### Display in Feed

```typescript
interface FeedItem {
  id: string;
  publicUrl: string;
  type: 'IMAGE' | 'VIDEO';
  thumbnailUrl?: string;
  caption?: string;
}

const FeedPost = ({ item }: { item: FeedItem }) => {
  return (
    <View>
      {item.type === 'IMAGE' ? (
        <Image source={{ uri: item.publicUrl }} />
      ) : (
        <Video
          source={{ uri: item.publicUrl }}
          poster={item.thumbnailUrl}  // Optional thumbnail
          controls
          resizeMode="cover"
        />
      )}
      {item.caption && <Text>{item.caption}</Text>}
    </View>
  );
};
```

### Flutter Example

```dart
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;

Future<void> uploadToFeed() async {
  final picker = ImagePicker();

  // Let user choose image or video
  final XFile? file = await showDialog<XFile?>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text('Choose media'),
      actions: [
        TextButton(
          onPressed: () async {
            final image = await picker.pickImage(source: ImageSource.gallery);
            Navigator.pop(context, image);
          },
          child: Text('Photo'),
        ),
        TextButton(
          onPressed: () async {
            final video = await picker.pickVideo(source: ImageSource.gallery);
            Navigator.pop(context, video);
          },
          child: Text('Video'),
        ),
      ],
    ),
  );

  if (file == null) return;

  var request = http.MultipartRequest(
    'POST',
    Uri.parse('https://api.plugame.com/api/media/upload'),
  );

  request.headers['Authorization'] = 'Bearer $token';
  request.fields['purpose'] = 'CREATOR_FEED';
  request.files.add(await http.MultipartFile.fromPath('file', file.path));

  var response = await request.send();
  var responseData = await response.stream.bytesToString();
  var json = jsonDecode(responseData);

  print('Type: ${json['type']}');  // Auto-detected!
  print('URL: ${json['publicUrl']}');
}
```

---

## 🔍 Feed Query

### Get Creator Feed

**GET** `/api/feed/creator/{creatorId}`

```bash
curl -X GET https://your-api.ngrok-free.dev/api/feed/creator/123 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Response:**
```json
{
  "posts": [
    {
      "id": "uuid-1",
      "type": "IMAGE",
      "publicUrl": "https://cdn.plugame.com/creator/photo.jpg",
      "contentType": "image/jpeg",
      "caption": "Great training session today! 💪",
      "width": 1080,
      "height": 1920,
      "createdAt": "2025-11-21T10:30:00Z"
    },
    {
      "id": "uuid-2",
      "type": "VIDEO",
      "publicUrl": "https://cdn.plugame.com/creator/video.mp4",
      "contentType": "video/mp4",
      "thumbnailUrl": "https://cdn.plugame.com/creator/video-thumb.jpg",
      "caption": "Check out this amazing play!",
      "durationSec": 45,
      "createdAt": "2025-11-20T15:00:00Z"
    }
  ]
}
```

---

## 🎯 Optional: Manual Override

If you need creators to **manually specify** the type (rare), you can add an optional `type` parameter:

### Upload with Manual Type

```bash
curl -X POST https://your-api.ngrok-free.dev/api/media/upload \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@media.mov" \
  -F "purpose=CREATOR_FEED" \
  -F "type=VIDEO"  # Optional override
```

### Implementation

```php
// In upload() method, after auto-detection:
$mediaType = $this->detectMediaType($contentType);

// Allow manual override if provided
$manualType = $req->request->get('type');
if ($manualType && in_array($manualType, [MediaAsset::TYPE_IMAGE, MediaAsset::TYPE_VIDEO], true)) {
    $mediaType = $manualType;
}

$asset->setType($mediaType);
```

---

## 📊 Database Schema

```sql
-- MediaAsset table already has:
type VARCHAR(5) DEFAULT 'IMAGE',  -- 'IMAGE' or 'VIDEO'
contentType VARCHAR(255),         -- 'image/jpeg', 'video/mp4', etc.
width INT NULL,                   -- For images
height INT NULL,                  -- For images
durationSec INT NULL,             -- For videos
thumbnailUrl VARCHAR(2048) NULL   -- Video thumbnail
```

---

## ✅ Benefits

### For Creators
✅ **No manual work** - Just upload, system handles the rest
✅ **Supports all formats** - iPhone photos, Android videos, professional cameras
✅ **Fast uploads** - No extra processing steps
✅ **Reliable** - MIME type detection is industry standard

### For Frontend
✅ **Easy rendering** - Just check `type` field
✅ **Proper display** - Show images vs videos correctly
✅ **Better UX** - Video players, image galleries work automatically
✅ **Performance** - Videos can lazy-load, images can be optimized

### For Backend
✅ **Automatic** - No developer intervention needed
✅ **Consistent** - Always stores correct type
✅ **Extensible** - Easy to add new formats
✅ **Accurate** - Uses MIME types + fallback extension check

---

## 🐛 Edge Cases Handled

### Incorrect MIME Types
Some clients send `application/octet-stream` - we fall back to extension check

### iPhone Media
HEIC photos and MOV videos are correctly detected

### Missing Extensions
MIME type prefix check catches these

### Unknown Formats
Defaults to `IMAGE` (safest fallback for feed content)

---

## 🚀 Testing

### Test Image Upload
```bash
# Upload a JPEG
curl -X POST http://localhost/api/media/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@test.jpg" \
  -F "purpose=CREATOR_FEED"

# Check response: should have "type": "IMAGE"
```

### Test Video Upload
```bash
# Upload an MP4
curl -X POST http://localhost/api/media/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@test.mp4" \
  -F "purpose=CREATOR_FEED"

# Check response: should have "type": "VIDEO"
```

### Test Feed Display
```bash
# Get creator feed
curl -X GET http://localhost/api/feed/creator/123 \
  -H "Authorization: Bearer $TOKEN"

# Verify each item has correct "type" field
```

---

## 📝 Summary

The system now **automatically detects** whether uploaded media is a photo or video:

1. ✅ **No creator action needed** - Upload works the same
2. ✅ **Accurate detection** - Uses MIME types with extension fallback
3. ✅ **All formats supported** - Images, videos, iPhone media
4. ✅ **Frontend-ready** - Just check the `type` field
5. ✅ **Production-ready** - Handles edge cases

Just upload media and let the backend handle the rest! 🎉
