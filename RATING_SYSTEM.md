# Rating System - Implementation Guide

## 📊 How Creator Ratings Work

Creator ratings are calculated based on **reviews left by athletes** after completing bookings.

---

## 🎯 Overview

### Entities Involved

1. **Review** ([src/Entity/Review.php](src/Entity/Review.php))
   - Links to a Booking (one-to-one)
   - Has a rating (1-5 stars)
   - Has an optional comment
   - Links reviewer (athlete) and creator

2. **CreatorProfile** ([src/Entity/CreatorProfile.php](src/Entity/CreatorProfile.php))
   - Stores `avgRating` (string, e.g., "4.5")
   - Stores `ratingsCount` (int)

---

## 🔄 Rating Calculation Flow

### Automatic Calculation

When reviews are created or updated, the creator's rating is recalculated:

```
Reviews for Creator
    ↓
Calculate Average
    ↓
Update CreatorProfile.avgRating
Update CreatorProfile.ratingsCount
```

### Example Calculation

```php
// Creator has 5 reviews: 5, 5, 4, 4, 3
// Total: 21
// Average: 21 / 5 = 4.2
// Stored as: "4.2"
```

---

## 🛠️ Implementation

### 1. RatingService

The [RatingService](src/Service/RatingService.php) handles all rating calculations:

```php
use App\Service\RatingService;

// Update a single creator's rating
$result = $ratingService->updateCreatorRating($creator);
// Returns: ['avgRating' => "4.5", 'ratingsCount' => 42]

// Recalculate all creators
$stats = $ratingService->recalculateAllRatings();
// Returns: ['total_creators' => 15, 'updated' => 12, 'no_ratings' => 3]

// Get detailed statistics
$stats = $ratingService->getRatingStatistics($creator);
// Returns breakdown by star rating
```

### 2. Console Command

Recalculate all ratings from the command line:

```bash
docker compose exec alpine php bin/console app:recalculate-ratings
```

**Output:**
```
 Recalculating Creator Ratings
 ==============================

 This will update avgRating and ratingsCount for all creators based on their reviews...

 [OK] Ratings recalculated successfully!

 ---------- -------
  Metric     Value
 ---------- -------
  Total Creators    15
  With Ratings      12
  Without Ratings   3
 ---------- -------
```

---

## 📝 When to Update Ratings

### Automatically

The RatingService should be called when:
- ✅ A new review is created
- ✅ A review is updated
- ✅ A review is deleted

### Manually

Run the command to recalculate all ratings:
- After importing data
- After database migrations
- When fixing data inconsistencies

---

## 🎯 API Usage

### Create a Review (After Booking Completion)

**POST** `/api/reviews`

```json
{
  "bookingId": "booking-uuid",
  "rating": 5,
  "comment": "Excellent service! Very professional."
}
```

**Response:**
```json
{
  "id": "review-uuid",
  "booking": "booking-uuid",
  "reviewer": {
    "id": "athlete-uuid",
    "fullName": "Jean Martin"
  },
  "creator": {
    "id": "creator-uuid",
    "fullName": "Sophie Dubois",
    "avgRating": "4.7",
    "ratingsCount": 43
  },
  "rating": 5,
  "comment": "Excellent service! Very professional.",
  "createdAt": "2025-11-22T10:30:00+00:00"
}
```

### Get Creator Profile (with Ratings)

**GET** `/api/creators/{id}`

```json
{
  "id": "creator-uuid",
  "displayName": "Sophie Dubois",
  "avgRating": "4.7",
  "ratingsCount": 43,
  "specialties": ["football", "action", "editorial"]
}
```

### Get Rating Statistics

**GET** `/api/creators/{id}/ratings`

```json
{
  "totalReviews": 43,
  "avgRating": 4.7,
  "breakdown": {
    "5": {
      "count": 28,
      "percentage": 65.1
    },
    "4": {
      "count": 10,
      "percentage": 23.3
    },
    "3": {
      "count": 4,
      "percentage": 9.3
    },
    "2": {
      "count": 1,
      "percentage": 2.3
    },
    "1": {
      "count": 0,
      "percentage": 0
    }
  }
}
```

---

## 📊 Fixtures Data

The fixtures create realistic reviews:

### Review Distribution

- **50%** are 5 stars ⭐⭐⭐⭐⭐
- **30%** are 4 stars ⭐⭐⭐⭐
- **15%** are 3 stars ⭐⭐⭐
- **4%** are 2 stars ⭐⭐
- **1%** are 1 star ⭐

### Review Creation

- **70%** of completed bookings receive reviews
- **60%** of reviews include comments
- Reviews are only created for `COMPLETED` bookings

### Example Output

```
⭐ Athletes leave reviews...
   ✅ Created 35 reviews

📊 Calculating creator ratings...
   ✅ Updated ratings for all creators

📊 DETAILED SUMMARY:
   ⭐ Reviews: 35
```

---

## 🔍 Database Schema

### Review Table

```sql
CREATE TABLE review (
    id CHAR(36) PRIMARY KEY,
    booking_id CHAR(36) UNIQUE NOT NULL,
    reviewer_id INT NOT NULL,
    creator_id INT NOT NULL,
    rating SMALLINT NOT NULL,
    comment TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES booking(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES user(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES user(id) ON DELETE CASCADE
);
```

### CreatorProfile Fields

```sql
ALTER TABLE creator_profile
ADD COLUMN avg_rating VARCHAR(4),  -- e.g., "4.5"
ADD COLUMN ratings_count INT;
```

---

## 🎨 Frontend Display

### React Native Example

```typescript
interface CreatorProfile {
  id: string;
  displayName: string;
  avgRating: string | null;  // "4.5" or null
  ratingsCount: number;
}

const CreatorCard = ({ creator }: { creator: CreatorProfile }) => {
  const renderStars = () => {
    if (!creator.avgRating) return null;

    const rating = parseFloat(creator.avgRating);
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;

    return (
      <View style={styles.rating}>
        {[...Array(fullStars)].map((_, i) => (
          <Icon key={i} name="star" color="gold" />
        ))}
        {hasHalfStar && <Icon name="star-half" color="gold" />}
        <Text>{creator.avgRating} ({creator.ratingsCount} reviews)</Text>
      </View>
    );
  };

  return (
    <View>
      <Text>{creator.displayName}</Text>
      {renderStars()}
    </View>
  );
};
```

---

## 🧪 Testing

### Test the Rating System

1. **Load fixtures with reviews:**
   ```bash
   docker compose exec alpine php bin/console doctrine:fixtures:load --no-interaction
   ```

2. **Check creator ratings:**
   ```bash
   docker compose exec alpine php bin/console dbal:run-sql \
     "SELECT display_name, avg_rating, ratings_count FROM creator_profile WHERE avg_rating IS NOT NULL"
   ```

3. **Recalculate ratings:**
   ```bash
   docker compose exec alpine php bin/console app:recalculate-ratings
   ```

---

## ✅ Implementation Checklist

- [x] Review entity created
- [x] RatingService implemented
- [x] Console command for recalculation
- [x] Fixtures create reviews
- [x] Fixtures update creator ratings
- [x] avgRating and ratingsCount in CreatorProfile
- [ ] Review controller (API endpoints)
- [ ] Auto-update ratings on review create/update/delete
- [ ] Frontend integration

---

## 📚 Related Files

- [src/Entity/Review.php](src/Entity/Review.php) - Review entity
- [src/Entity/CreatorProfile.php](src/Entity/CreatorProfile.php) - Creator profile with ratings
- [src/Service/RatingService.php](src/Service/RatingService.php) - Rating calculation service
- [src/Command/RecalculateRatingsCommand.php](src/Command/RecalculateRatingsCommand.php) - CLI command
- [src/DataFixtures/AppFixtures.php](src/DataFixtures/AppFixtures.php) - Creates reviews and calculates ratings

---

## 🚀 Next Steps

1. **Run fixtures** to generate reviews and calculate ratings
2. **Test the feed** - ratings should appear in creator profiles
3. **Implement Review API** endpoints for creating/updating reviews
4. **Add event listener** to auto-update ratings when reviews change

**The rating system is ready to use!** 🎉
