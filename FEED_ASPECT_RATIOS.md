# Feed Aspect Ratio Options

## 📐 Aspect Ratio Options for Creator Feed

Here are the recommended aspect ratios for the Plugame feed. **Please validate which one(s) you want to support.**

---

## Option 1: Instagram-Style (Multiple Ratios) ✨ RECOMMENDED

Allow creators to choose from multiple aspect ratios like Instagram:

### Supported Ratios:
- **1:1 (Square)** - 1080x1080 - Classic Instagram
- **4:5 (Portrait)** - 1080x1350 - Modern Instagram feed
- **16:9 (Landscape)** - 1920x1080 - YouTube/Horizontal videos
- **9:16 (Stories/Reels)** - 1080x1920 - Vertical videos/TikTok style

### Pros:
✅ Maximum flexibility for creators
✅ Different content works better in different ratios
✅ Modern, familiar UX
✅ Works for both photos and videos

### Cons:
❌ Feed might look less uniform
❌ More complex frontend layout

---

## Option 2: Fixed 1:1 (Square) - Simple & Clean

Force all content to be square (1:1 ratio).

### Specifications:
- **Ratio**: 1:1
- **Recommended**: 1080x1080
- **Min**: 640x640
- **Max**: 2048x2048

### Pros:
✅ Uniform, clean feed (like early Instagram)
✅ Easy frontend layout
✅ Works well in grid view
✅ Simple cropping logic

### Cons:
❌ Forces cropping of landscape/portrait content
❌ Not ideal for action sports videos
❌ Less modern

---

## Option 3: Fixed 4:5 (Portrait) - Mobile-First

All content is portrait 4:5 ratio.

### Specifications:
- **Ratio**: 4:5
- **Recommended**: 1080x1350
- **Min**: 640x800
- **Max**: 1620x2025

### Pros:
✅ Optimized for mobile viewing
✅ Takes up more screen space
✅ Modern Instagram style
✅ Good for portraits and vertical videos

### Cons:
❌ Crops landscape content heavily
❌ Not ideal for wide action shots

---

## Option 4: Fixed 16:9 (Landscape) - Video-First

All content is landscape 16:9 ratio.

### Specifications:
- **Ratio**: 16:9
- **Recommended**: 1920x1080 (Full HD)
- **Min**: 1280x720 (720p)
- **Max**: 3840x2160 (4K)

### Pros:
✅ Perfect for action sports videos
✅ Professional look
✅ Great for highlights/gameplay
✅ Works well on desktop

### Cons:
❌ Not ideal for mobile-first app
❌ Takes less vertical space on phone
❌ Portrait photos get cropped

---

## Option 5: Adaptive/Smart Crop

Auto-detect uploaded ratio and display accordingly.

### How it works:
1. Creator uploads any ratio
2. System detects dimensions
3. Feed adapts height based on ratio
4. Optional: Smart crop for specific views

### Supported Ratios:
- **Portrait**: 3:4, 4:5, 9:16
- **Square**: 1:1
- **Landscape**: 16:9, 21:9

### Pros:
✅ No cropping needed
✅ Shows content as intended
✅ Flexible for creators

### Cons:
❌ Feed looks less uniform
❌ Complex responsive design

---

## 🎯 My Recommendation

### For Sports/Action Content: **Multiple Ratios (Option 1)**

Allow creators to choose between:
1. **16:9** - For landscape action videos (game footage, wide shots)
2. **9:16** - For vertical videos (stories, reels, TikTok-style)
3. **1:1** - For photos and thumbnails

This gives maximum flexibility while keeping feed organized.

### Implementation Strategy:

```typescript
// Creator uploads
interface MediaUpload {
  file: File;
  aspectRatio?: '1:1' | '4:5' | '16:9' | '9:16'; // Optional, auto-detect if not provided
}

// Feed display
interface FeedPost {
  id: string;
  type: 'IMAGE' | 'VIDEO';
  publicUrl: string;
  aspectRatio: '1:1' | '4:5' | '16:9' | '9:16';
  width: number;
  height: number;
}
```

---

## 📱 Feed Layout Examples

### Option 1: Multi-Ratio (Recommended)

```
┌─────────────────────┐
│   Header (Avatar)   │
├─────────────────────┤
│                     │
│    16:9 Video       │
│    (Landscape)      │
│                     │
├─────────────────────┤
│   Actions (Like)    │
└─────────────────────┘

┌─────────────────────┐
│   Header            │
├───────────┐         │
│           │         │
│    1:1    │         │
│  (Square) │         │
│           │         │
└───────────┘         │
│   Actions           │
└─────────────────────┘

┌─────────────────────┐
│   Header            │
│ ┌───────────┐       │
│ │           │       │
│ │   9:16    │       │
│ │ (Vertical)│       │
│ │           │       │
│ │           │       │
│ └───────────┘       │
│   Actions           │
└─────────────────────┘
```

### Option 2: Fixed 1:1

```
┌─────────────────────┐
│   Header            │
├─────────────────────┤
│                     │
│      1:1            │
│    (Square)         │
│                     │
├─────────────────────┤
│   Actions           │
└─────────────────────┘

┌─────────────────────┐
│   Header            │
├─────────────────────┤
│                     │
│      1:1            │
│    (Square)         │
│                     │
├─────────────────────┤
│   Actions           │
└─────────────────────┘
```

---

## 🎨 Frontend Implementation Example

### React Native - Multi-Ratio

```typescript
const FeedPost = ({ post }) => {
  const getAspectRatio = () => {
    switch (post.aspectRatio) {
      case '1:1': return 1;
      case '4:5': return 4/5;
      case '16:9': return 16/9;
      case '9:16': return 9/16;
      default: return 1;
    }
  };

  const screenWidth = Dimensions.get('window').width;
  const height = screenWidth / getAspectRatio();

  return (
    <View>
      <View style={{ width: screenWidth, height }}>
        {post.type === 'IMAGE' ? (
          <Image
            source={{ uri: post.publicUrl }}
            style={{ width: '100%', height: '100%' }}
            resizeMode="cover"
          />
        ) : (
          <Video
            source={{ uri: post.publicUrl }}
            style={{ width: '100%', height: '100%' }}
            resizeMode="cover"
          />
        )}
      </View>
    </View>
  );
};
```

---

## 📊 Backend Implementation

### Database Schema

```sql
ALTER TABLE media_asset
ADD COLUMN aspect_ratio VARCHAR(10) DEFAULT '1:1';
-- Values: '1:1', '4:5', '16:9', '9:16'
```

### Auto-Detection

```php
private function detectAspectRatio(int $width, int $height): string
{
    if ($width === 0 || $height === 0) {
        return '1:1'; // Default
    }

    $ratio = $width / $height;

    // 1:1 (Square) - ratio ~1.0
    if ($ratio >= 0.95 && $ratio <= 1.05) {
        return '1:1';
    }

    // 16:9 (Landscape) - ratio ~1.78
    if ($ratio >= 1.7 && $ratio <= 1.85) {
        return '16:9';
    }

    // 4:5 (Portrait) - ratio ~0.8
    if ($ratio >= 0.75 && $ratio <= 0.85) {
        return '4:5';
    }

    // 9:16 (Vertical) - ratio ~0.56
    if ($ratio >= 0.5 && $ratio <= 0.6) {
        return '9:16';
    }

    // Default to closest
    if ($ratio > 1) {
        return '16:9'; // Landscape
    } else {
        return '4:5'; // Portrait
    }
}
```

---

## ❓ Questions for Validation

Please decide:

1. **Which aspect ratio(s) should we support?**
   - [ ] Option 1: Multiple ratios (1:1, 4:5, 16:9, 9:16) - RECOMMENDED
   - [ ] Option 2: Fixed 1:1 (Square only)
   - [ ] Option 3: Fixed 4:5 (Portrait only)
   - [ ] Option 4: Fixed 16:9 (Landscape only)
   - [ ] Option 5: Adaptive/Smart crop

2. **Should creators choose the ratio or should it be auto-detected?**
   - [ ] Auto-detect from upload
   - [ ] Let creator choose
   - [ ] Both (auto-detect with manual override)

3. **Should we allow cropping/editing before upload?**
   - [ ] Yes - Show crop tool in app
   - [ ] No - Just auto-detect and upload

4. **What about videos?**
   - [ ] Same rules as photos
   - [ ] Always 16:9 for landscape videos
   - [ ] Always 9:16 for vertical videos

5. **Grid view (profile page)?**
   - [ ] Force 1:1 thumbnails in grid
   - [ ] Keep original aspect ratio in grid
   - [ ] Show ratio indicator on thumbnails

---

## 🎯 Recommendation Summary

**For Plugame (Sports Platform):**

I recommend **Option 1: Multiple Ratios** with:
- **16:9** for landscape action videos and game footage
- **9:16** for vertical videos (stories/reels style)
- **1:1** for profile photos and square content
- **Auto-detect** aspect ratio on upload
- **Force 1:1 thumbnails** in grid view

This gives creators maximum flexibility while keeping the feed professional and modern.

---

## 📝 Next Steps

Once you validate the aspect ratio choice, I'll implement:
1. ✅ Auto-detection logic
2. ✅ Database field for aspect ratio
3. ✅ API response with aspect ratio
4. ✅ Validation rules
5. ✅ Frontend integration guide

**Please let me know which option you prefer!** 🎯
