# Automatic Aspect Ratio Detection - Proposal

## 🎯 How It Works

When a creator uploads media, the system:

1. **Reads the dimensions** (width × height)
2. **Calculates the ratio** (width ÷ height)
3. **Matches to nearest standard ratio**
4. **Stores the detected ratio** in database

---

## 📐 Detection Logic

```php
private function detectAspectRatio(int $width, int $height): string
{
    if ($width === 0 || $height === 0) {
        return '1:1'; // Default fallback
    }

    $ratio = $width / $height;

    // 1:1 (Square) - ratio = 1.0
    // Examples: 1080x1080, 800x800
    if ($ratio >= 0.95 && $ratio <= 1.05) {
        return '1:1';
    }

    // 4:5 (Portrait) - ratio = 0.8
    // Examples: 1080x1350, 800x1000
    if ($ratio >= 0.75 && $ratio <= 0.85) {
        return '4:5';
    }

    // 9:16 (Vertical/Stories) - ratio = 0.5625
    // Examples: 1080x1920, 720x1280
    if ($ratio >= 0.5 && $ratio <= 0.6) {
        return '9:16';
    }

    // 16:9 (Landscape/Video) - ratio = 1.778
    // Examples: 1920x1080, 1280x720
    if ($ratio >= 1.7 && $ratio <= 1.85) {
        return '16:9';
    }

    // 21:9 (Ultra-wide) - ratio = 2.333
    // Examples: 2560x1080
    if ($ratio >= 2.2 && $ratio <= 2.5) {
        return '21:9';
    }

    // Fallback logic
    if ($ratio > 1.4) {
        return '16:9'; // Wide content → landscape
    } else if ($ratio < 0.7) {
        return '9:16'; // Tall content → vertical
    } else {
        return '1:1'; // In-between → square
    }
}
```

---

## 📊 Examples

### Real-World Uploads

| Upload Dimensions | Calculated Ratio | Detected As | Use Case |
|------------------|------------------|-------------|----------|
| 1080 × 1080 | 1.0 | **1:1** | Instagram square |
| 1080 × 1350 | 0.8 | **4:5** | Instagram portrait |
| 1920 × 1080 | 1.78 | **16:9** | YouTube video |
| 1080 × 1920 | 0.56 | **9:16** | TikTok/Stories |
| 3840 × 2160 | 1.78 | **16:9** | 4K landscape |
| 720 × 1280 | 0.56 | **9:16** | Old phone vertical |
| 800 × 600 | 1.33 | **16:9** | Fallback landscape |
| 600 × 800 | 0.75 | **4:5** | Portrait photo |

---

## ✅ Advantages

### 1. **No Creator Input Needed**
- Creator just uploads
- System handles everything
- Faster upload flow

### 2. **Always Correct**
- Uses actual image dimensions
- No human error
- Respects original format

### 3. **Flexible**
- Works with any camera/phone
- Handles iPhone, Android, DSLR
- No format restrictions

### 4. **Smart Fallbacks**
- Handles unusual ratios
- Rounds to nearest standard
- Never fails

---

## ⚠️ Potential Issues & Solutions

### Issue 1: Creator uploads wrong orientation

**Example**: Horizontal video uploaded as vertical (rotated wrong)

**Solution**:
- Check EXIF orientation data
- Auto-rotate if needed
- Or: Let creator preview before posting

### Issue 2: Unusual ratios

**Example**: 1000×700 (ratio 1.43) - Between 1:1 and 16:9

**Solution**:
- Round to nearest standard (16:9)
- Or: Store exact ratio + display ratio separately
- Or: Allow creator to choose if ratio is ambiguous

### Issue 3: Cropped content

**Example**: 16:9 landscape photo but creator wants 1:1

**Solution Option A**: Auto-detect only
```
Upload 1920×1080 → Detected: 16:9 → Display: 16:9 (no choice)
```

**Solution Option B**: Auto-detect + manual override
```
Upload 1920×1080 → Detected: 16:9 → Creator can change to 1:1
If 1:1 chosen → Show crop tool → Save cropped version
```

---

## 🎨 Two Implementation Options

### **Option A: Pure Auto-Detection** ⚡ SIMPLE

**Flow**:
1. Creator uploads photo/video
2. System detects dimensions
3. System assigns ratio
4. No creator input needed

**Pros**:
- ✅ Fast upload
- ✅ No UI needed
- ✅ Simple implementation
- ✅ Works immediately

**Cons**:
- ❌ No control for creator
- ❌ Can't crop/adjust
- ❌ Wrong orientation issues

---

### **Option B: Auto-Detect + Manual Override** 🎯 FLEXIBLE

**Flow**:
1. Creator uploads photo/video
2. System detects ratio: "Detected: 16:9"
3. Creator sees preview with ratio options
4. Creator can keep or change ratio
5. If changed → show crop tool
6. Upload final version

**Example UI**:
```
┌─────────────────────────────────┐
│  Preview of your upload         │
│                                 │
│  ┌─────────────────────────┐   │
│  │                         │   │
│  │    Your Image/Video     │   │
│  │                         │   │
│  └─────────────────────────┘   │
│                                 │
│  Detected: 16:9 ✓               │
│                                 │
│  Change ratio:                  │
│  [ 1:1 ] [ 4:5 ] [16:9] [ 9:16 ]│
│                    ^^^           │
│                  selected        │
│                                 │
│  [ Cancel ]        [ Post ]     │
└─────────────────────────────────┘
```

**Pros**:
- ✅ Automatic but flexible
- ✅ Creator has control
- ✅ Can fix orientation
- ✅ Professional result

**Cons**:
- ❌ Extra UI step
- ❌ More development
- ❌ Crop tool needed

---

## 🔧 Implementation Details

### Database Schema

```sql
ALTER TABLE media_asset ADD COLUMN aspect_ratio VARCHAR(10) DEFAULT '1:1';
-- Stores: '1:1', '4:5', '16:9', '9:16', '21:9'

ALTER TABLE media_asset ADD COLUMN original_aspect_ratio VARCHAR(10) NULL;
-- Stores original detected ratio (if creator changed it)
```

### Controller Update

```php
// In MediaAssetController::upload()

// Get image dimensions
$imageInfo = getimagesize($file->getPathname());
$width = $imageInfo[0] ?? 0;
$height = $imageInfo[1] ?? 0;

// Auto-detect aspect ratio
$detectedRatio = $this->detectAspectRatio($width, $height);

$asset = (new MediaAsset())
    ->setOwner($user)
    ->setType($mediaType)
    ->setWidth($width)
    ->setHeight($height)
    ->setAspectRatio($detectedRatio)  // Auto-detected
    ->setOriginalAspectRatio($detectedRatio); // Store original
```

### API Response

```json
{
  "id": "uuid",
  "type": "IMAGE",
  "publicUrl": "https://...",
  "width": 1920,
  "height": 1080,
  "aspectRatio": "16:9",  // ✅ Auto-detected
  "contentType": "image/jpeg"
}
```

---

## 📱 Frontend Display

### React Native Example

```typescript
const FeedPost = ({ post }) => {
  const getHeight = () => {
    const screenWidth = Dimensions.get('window').width;

    switch (post.aspectRatio) {
      case '1:1': return screenWidth;
      case '4:5': return screenWidth * 1.25;
      case '16:9': return screenWidth * 0.5625;
      case '9:16': return screenWidth * 1.778;
      default: return screenWidth;
    }
  };

  return (
    <View style={{ width: '100%', height: getHeight() }}>
      <Image
        source={{ uri: post.publicUrl }}
        style={{ width: '100%', height: '100%' }}
        resizeMode="cover"
      />
    </View>
  );
};
```

---

## 🎯 My Recommendations

### For Plugame (Sports Platform):

**Phase 1: Launch with Option A (Pure Auto-Detection)**
- Simple, fast to implement
- Works immediately
- No UI complexity
- Most creators won't care about ratio

**Phase 2: Add Option B (Manual Override) if needed**
- Add based on creator feedback
- Only if they complain about crops
- Can be added later without breaking changes

---

## ❓ Questions for Validation

**Please decide**:

1. **Detection Method**:
   - [ ] Option A: Pure auto-detection (simple, no creator choice)
   - [ ] Option B: Auto-detect + manual override (flexible, more work)

2. **Supported Ratios**:
   - [ ] 4 ratios: 1:1, 4:5, 16:9, 9:16 (RECOMMENDED)
   - [ ] 3 ratios: 1:1, 16:9, 9:16 (simpler)
   - [ ] 5 ratios: 1:1, 4:5, 16:9, 9:16, 21:9 (more options)

3. **Fallback Behavior**:
   - [ ] Unusual ratios → Round to nearest standard
   - [ ] Unusual ratios → Always use 1:1
   - [ ] Unusual ratios → Store exact ratio

4. **Grid View (Profile)**:
   - [ ] Force all thumbnails to 1:1 in grid
   - [ ] Keep original aspect ratio in grid
   - [ ] Show ratio badge on thumbnails

5. **Videos vs Photos**:
   - [ ] Same detection logic for both
   - [ ] Force videos to 16:9 or 9:16 only
   - [ ] Allow any ratio for photos, restrict videos

---

## 💡 Example Scenarios

### Scenario 1: Creator uploads iPhone video
```
iPhone records: 1080×1920 (vertical)
→ Detected: 9:16
→ Displayed: Full vertical in feed
→ Perfect for stories/reels style ✅
```

### Scenario 2: Creator uploads GoPro footage
```
GoPro records: 1920×1080 (16:9)
→ Detected: 16:9
→ Displayed: Landscape video
→ Perfect for action shots ✅
```

### Scenario 3: Creator uploads DSLR photo
```
DSLR photo: 3000×2000 (3:2 ratio)
→ Detected: → Falls between 16:9 and 4:5
→ Rounded to: 16:9 (landscape)
→ Displayed: Landscape photo ✅
```

### Scenario 4: Creator uploads square Instagram export
```
Instagram export: 1080×1080
→ Detected: 1:1
→ Displayed: Square
→ Perfect! ✅
```

---

## 🚀 Next Steps

Once you validate:
1. I'll implement the detection logic
2. Add database field for aspect_ratio
3. Update API responses
4. Create frontend integration guide
5. Add validation tests

**Please confirm which option you prefer!** 🎯

### Quick Decision Helper:

**Choose Option A if**:
- You want fast implementation
- Creators don't need control
- Simplicity is key

**Choose Option B if**:
- Creators need flexibility
- Professional control is important
- You have time for UI development

**What do you think?** 🤔
