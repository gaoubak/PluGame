# Complete API Routes Guide

**Project:** 23HEC001 Sports Creator Marketplace
**Last Updated:** November 8, 2025
**Base URL:** `http://localhost:8090` (dev) | `https://api.23hec001.com` (prod)

---

## 📚 Table of Contents

1. [Authentication](#authentication)
2. [Users & Profiles](#users--profiles)
3. [Bookings](#bookings)
4. [Services & Availability](#services--availability)
5. [Payments & Wallet](#payments--wallet)
6. [Promo Codes](#promo-codes-new)
7. [Messages & Conversations](#messages--conversations)
8. [Social Features](#social-features)
9. [Media & Deliverables](#media--deliverables)
10. [Reviews](#reviews)
11. [Dashboard](#dashboard)
12. [Webhooks](#webhooks)

---

## 🔐 Authentication

All endpoints require `Authorization: Bearer {token}` header unless marked as **Public**.

### Login
```http
POST /api/login_check
Content-Type: application/json

{
  "username": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",      // Access token (15 min)
  "refresh_token": "3f2504e04f8911e3...",   // Refresh token (7 days)
  "mercure_token": "eyJ0eXAiOiJKV1Q...",    // Real-time messaging token
  "expires_in": 900,
  "token_type": "Bearer"
}
```

### Refresh Access Token
```http
POST /api/token/refresh
Content-Type: application/json

{
  "refresh_token": "3f2504e04f8911e3..."
}
```

**Response:** Same as login (new tokens)

### Logout from All Devices
```http
POST /api/token/revoke-all
Authorization: Bearer {token}
```

**Response:**
```json
{
  "message": "All refresh tokens have been revoked",
  "revoked_count": 5
}
```

### Logout (Current Device)
```http
POST /api/logout
Authorization: Bearer {token}
```

---

## 👥 Users & Profiles

### Register New User
```http
POST /api/users/register
Content-Type: application/json

{
  "email": "newuser@example.com",
  "password": "SecurePass123!",
  "firstName": "John",
  "lastName": "Doe",
  "username": "johndoe",
  "role": "ROLE_ATHLETE"  // or "ROLE_CREATOR"
}
```

### Get Current User
```http
GET /api/users/current
Authorization: Bearer {token}
```

### Update Current User
```http
PUT /api/users/update
Authorization: Bearer {token}
Content-Type: application/json

{
  "firstName": "Updated Name",
  "bio": "New bio text",
  "location": "New York, NY"
}
```

### Get User by ID
```http
GET /api/users/{id}
Authorization: Bearer {token}
```

### List All Users
```http
GET /api/users/
Authorization: Bearer {token}
```

### Search Users
```http
GET /api/users/search?q=john&role=creator&sport=basketball
Authorization: Bearer {token}
```

**Query Parameters:**
- `q` - Search term
- `role` - Filter by role (athlete/creator)
- `sport` - Filter by sport
- `location` - Filter by location

### Delete User
```http
DELETE /api/users/delete/{id}
Authorization: Bearer {token}
```

### Update Online Status
```http
POST /api/users/me/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "online"  // online, away, offline
}
```

### Heartbeat (Keep Alive)
```http
POST /api/users/me/heartbeat
Authorization: Bearer {token}
```

---

## 📅 Bookings

### List All Bookings
```http
GET /api/bookings/
Authorization: Bearer {token}
```

### Get My Bookings (as Athlete)
```http
GET /api/bookings/mine/as-athlete
Authorization: Bearer {token}
```

### Get My Bookings (as Creator)
```http
GET /api/bookings/mine/as-creator
Authorization: Bearer {token}
```

### Get Booking by ID
```http
GET /api/bookings/{id}
Authorization: Bearer {token}
```

### Create Booking
```http
POST /api/bookings/create
Authorization: Bearer {token}
Content-Type: application/json

{
  "creator_user_id": "creator-uuid",
  "service_offering_id": "service-uuid",
  "start_time": "2025-12-01T10:00:00Z",
  "end_time": "2025-12-01T12:00:00Z",
  "location": "Central Park, NYC",
  "notes": "Need action shots for portfolio",
  "promo_code": "SUMMER2025"  // Optional
}
```

### Accept Booking (Creator Only)
```http
POST /api/bookings/{id}/accept
Authorization: Bearer {token}
```

### Decline Booking (Creator Only)
```http
POST /api/bookings/{id}/decline
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Already booked for that time"
}
```

### Cancel Booking
```http
POST /api/bookings/{id}/cancel
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Need to reschedule"
}
```

### Complete Booking (Creator Only)
```http
POST /api/bookings/{id}/complete
Authorization: Bearer {token}
```

### Delete Booking (Soft Delete)
```http
DELETE /api/bookings/delete/{id}
Authorization: Bearer {token}
```

### Restore Deleted Booking
```http
POST /api/bookings/{id}/restore
Authorization: Bearer {token}
```

### Get User's Bookings
```http
GET /api/bookings/user/{userId}/as-athlete
GET /api/bookings/user/{userId}/as-creator
Authorization: Bearer {token}
```

---

## 🎯 Services & Availability

### List All Services
```http
GET /api/services/
Authorization: Bearer {token}
```

### Get Service by ID
```http
GET /api/services/{id}
Authorization: Bearer {token}
```

### Get Services by User/Creator
```http
GET /api/services/user/{userId}
Authorization: Bearer {token}
```

### Create Service Offering (Creator Only)
```http
POST /api/services/create
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Professional Sports Photography",
  "description": "2-hour photo session with 50+ edited photos",
  "base_price_cents": 25000,  // $250.00
  "duration_minutes": 120,
  "category": "photography",
  "is_active": true
}
```

### Update Service
```http
PUT /api/services/update/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated title",
  "base_price_cents": 30000
}
```

### Delete Service (Soft Delete)
```http
DELETE /api/services/delete/{id}
Authorization: Bearer {token}
```

### List Availability Slots
```http
GET /api/slots/
Authorization: Bearer {token}
```

### Get My Slots (Creator)
```http
GET /api/slots/mine
Authorization: Bearer {token}
```

### Get Slots by User
```http
GET /api/slots/user/{userId}
Authorization: Bearer {token}
```

### Create Single Slot
```http
POST /api/slots/create
Authorization: Bearer {token}
Content-Type: application/json

{
  "start_time": "2025-12-01T10:00:00Z",
  "end_time": "2025-12-01T12:00:00Z",
  "is_available": true
}
```

### Create Multiple Slots (Bulk)
```http
POST /api/slots/bulk
Authorization: Bearer {token}
Content-Type: application/json

{
  "start_date": "2025-12-01",
  "end_date": "2025-12-31",
  "time_slots": [
    {"start": "09:00", "end": "11:00"},
    {"start": "14:00", "end": "16:00"}
  ],
  "days_of_week": [1, 2, 3, 4, 5]  // Monday-Friday
}
```

### Update Slot
```http
PUT /api/slots/update/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "is_available": false
}
```

### Delete Slot
```http
DELETE /api/slots/delete/{id}
Authorization: Bearer {token}
```

---

## 💳 Payments & Wallet

### Create Payment Intent
```http
POST /api/payments/intent
Authorization: Bearer {token}
Content-Type: application/json

{
  "booking_id": "booking-uuid",
  "amount_cents": 25000,
  "promo_code": "SUMMER2025"  // Optional
}
```

**Response:**
```json
{
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx",
  "amount": 25000,
  "discount_amount": 5000,     // If promo code applied
  "final_amount": 20000,
  "currency": "usd"
}
```

### Pay Remaining Balance
```http
POST /api/payments/pay-remaining/{bookingId}
Authorization: Bearer {token}
Content-Type: application/json

{
  "payment_method_id": "pm_xxx"
}
```

### Get Payment Status
```http
GET /api/payments/status/{paymentId}
Authorization: Bearer {token}
```

### Get Payment History
```http
GET /api/payments/history
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 20)
- `status` - Filter by status (pending, completed, failed, refunded)

### Payment Methods

#### List Payment Methods
```http
GET /api/payment-methods
Authorization: Bearer {token}
```

#### Create Setup Intent (Add Card)
```http
POST /api/payment-methods/setup-intent
Authorization: Bearer {token}
```

#### Attach Payment Method
```http
POST /api/payment-methods/attach
Authorization: Bearer {token}
Content-Type: application/json

{
  "payment_method_id": "pm_xxx"
}
```

#### Set Default Payment Method
```http
POST /api/payment-methods/{id}/set-default
Authorization: Bearer {token}
```

#### Delete Payment Method
```http
DELETE /api/payment-methods/{id}
Authorization: Bearer {token}
```

### Wallet

#### Get Wallet Balance
```http
GET /api/wallet/balance
Authorization: Bearer {token}
```

**Response:**
```json
{
  "balance_cents": 50000,
  "balance_formatted": "$500.00",
  "currency": "USD"
}
```

#### Get Wallet History
```http
GET /api/wallet/history
Authorization: Bearer {token}
```

#### Purchase Wallet Credits
```http
POST /api/wallet/purchase
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount_cents": 10000,  // $100.00
  "payment_method_id": "pm_xxx"
}
```

#### Confirm Wallet Purchase
```http
POST /api/wallet/purchase/confirm
Authorization: Bearer {token}
Content-Type: application/json

{
  "payment_intent_id": "pi_xxx"
}
```

### Payouts (Creator Only)

#### Get Payout Methods
```http
GET /api/payouts/methods
Authorization: Bearer {token}
```

#### Add Payout Method
```http
POST /api/payouts/methods
Authorization: Bearer {token}
Content-Type: application/json

{
  "type": "bank_account",
  "account_number": "000123456789",
  "routing_number": "110000000",
  "account_holder_name": "John Doe"
}
```

#### Set Default Payout Method
```http
POST /api/payouts/methods/{id}/set-default
Authorization: Bearer {token}
```

#### Delete Payout Method
```http
DELETE /api/payouts/methods/{id}
Authorization: Bearer {token}
```

#### Get Revenues
```http
GET /api/payouts/revenues
Authorization: Bearer {token}
```

**Query Parameters:**
- `start_date` - Filter from date (YYYY-MM-DD)
- `end_date` - Filter to date (YYYY-MM-DD)

---

## 🎁 Promo Codes (NEW!)

### Create Promo Code (Creator Only)
```http
POST /api/promo-codes/create
Authorization: Bearer {token}
Content-Type: application/json

{
  "code": "SUMMER2025",
  "discount_type": "percentage",     // or "fixed_amount"
  "discount_value": 20,               // 20% or $20.00 (2000 cents)
  "description": "Summer sale",
  "max_uses": 100,                    // null = unlimited
  "max_uses_per_user": 1,             // null = unlimited
  "expires_at": "2025-12-31T23:59:59Z",  // null = no expiration
  "min_amount": 5000                  // $50.00 minimum (null = no min)
}
```

**Response:**
```json
{
  "id": "uuid",
  "code": "SUMMER2025",
  "discount_type": "percentage",
  "discount_value": 20,
  "discount_display": "20%",
  "stripe_coupon_id": "promo_summer2025_abc123",
  "max_uses": 100,
  "used_count": 0,
  "is_active": true
}
```

### List My Promo Codes (Creator Only)
```http
GET /api/promo-codes/mine
Authorization: Bearer {token}
```

**Response:**
```json
[
  {
    "id": "uuid",
    "code": "SUMMER2025",
    "discount_display": "20%",
    "used_count": 15,
    "max_uses": 100,
    "is_active": true,
    "is_valid": true,
    "expires_at": "2025-12-31T23:59:59Z"
  }
]
```

### Validate Promo Code
```http
POST /api/promo-codes/validate
Authorization: Bearer {token}
Content-Type: application/json

{
  "code": "SUMMER2025",
  "creator_id": "creator-uuid",  // IMPORTANT: Must match code owner!
  "amount": 10000                 // $100.00 booking amount
}
```

**Response (Valid):**
```json
{
  "valid": true,
  "discount_amount": 2000,      // $20.00
  "final_amount": 8000,          // $80.00
  "discount_display": "20%",
  "promo_code_id": "uuid",
  "stripe_coupon_id": "promo_summer2025_abc123"
}
```

**Response (Invalid):**
```json
{
  "valid": false,
  "error": "This promo code is not valid for this creator"
}
```

### Deactivate Promo Code
```http
POST /api/promo-codes/{id}/deactivate
Authorization: Bearer {token}
```

**🔒 Security Note:** Promo codes are **creator-specific**. An athlete can only use a code on the creator who created it.

---

## 💬 Messages & Conversations

### List My Conversations
```http
GET /api/conversations/me
Authorization: Bearer {token}
```

### List All Conversations
```http
GET /api/conversations/
Authorization: Bearer {token}
```

### Get Conversation by ID
```http
GET /api/conversations/{id}
Authorization: Bearer {token}
```

### Create Conversation
```http
POST /api/conversations/create
Authorization: Bearer {token}
Content-Type: application/json

{
  "participant_id": "user-uuid",
  "booking_id": "booking-uuid"  // Optional
}
```

### Send Message in Conversation
```http
POST /api/conversations/{id}/send-message
Authorization: Bearer {token}
Content-Type: application/json

{
  "content": "Hello! When would you be available?"
}
```

### Delete Conversation
```http
DELETE /api/conversations/delete/{id}
Authorization: Bearer {token}
```

### List All Messages
```http
GET /api/messages/
Authorization: Bearer {token}
```

### Get Messages by Conversation
```http
GET /api/messages/conversation/{conversationId}
Authorization: Bearer {token}
```

### Send Message
```http
POST /api/messages/send
Authorization: Bearer {token}
Content-Type: application/json

{
  "conversation_id": "conversation-uuid",
  "content": "Message text here"
}
```

### Delete Message
```http
DELETE /api/messages/{id}
Authorization: Bearer {token}
```

---

## 👍 Social Features

### Feed

#### Get Feed
```http
GET /api/feed/
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` - Page number
- `limit` - Items per page
- `type` - Filter by type (all, following, trending)

#### Get Single Post
```http
GET /api/feed/{id}
Authorization: Bearer {token}
```

#### Like Post
```http
POST /api/feed/{postId}/like
Authorization: Bearer {token}
```

#### Unlike Post
```http
DELETE /api/feed/{postId}/like
Authorization: Bearer {token}
```

#### Check if I Liked Post
```http
GET /api/feed/{postId}/likes/me
Authorization: Bearer {token}
```

#### Get Likes Count
```http
GET /api/feed/{postId}/likes/count
Authorization: Bearer {token}
```

#### Get My Likes
```http
GET /api/likes/me
Authorization: Bearer {token}
```

#### Batch Check Likes
```http
POST /api/likes/batch-check
Authorization: Bearer {token}
Content-Type: application/json

{
  "post_ids": ["uuid1", "uuid2", "uuid3"]
}
```

#### Share Post
```http
POST /api/feed/{id}/share
Authorization: Bearer {token}
Content-Type: application/json

{
  "caption": "Check this out!"
}
```

### Comments

#### List Comments on Post
```http
GET /api/feed/{postId}/comments
Authorization: Bearer {token}
```

#### Create Comment
```http
POST /api/feed/{postId}/comments
Authorization: Bearer {token}
Content-Type: application/json

{
  "content": "Great shot!"
}
```

#### Reply to Comment
```http
POST /api/comments/{commentId}/replies
Authorization: Bearer {token}
Content-Type: application/json

{
  "content": "Thanks!"
}
```

#### Get Comment Replies
```http
GET /api/comments/{commentId}/replies
Authorization: Bearer {token}
```

#### Edit Comment
```http
PUT /api/comments/{commentId}
Authorization: Bearer {token}
Content-Type: application/json

{
  "content": "Updated comment text"
}
```

#### Delete Comment
```http
DELETE /api/comments/{commentId}
Authorization: Bearer {token}
```

#### Like Comment
```http
POST /api/comments/{commentId}/like
Authorization: Bearer {token}
```

#### Unlike Comment
```http
DELETE /api/comments/{commentId}/like
Authorization: Bearer {token}
```

#### Report Comment
```http
POST /api/comments/{commentId}/report
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "spam"  // spam, abuse, inappropriate, other
}
```

### Follow System

#### Follow User
```http
POST /api/follow/{userId}
Authorization: Bearer {token}
```

#### Unfollow User
```http
DELETE /api/follow/{userId}
Authorization: Bearer {token}
```

### Bookmarks

#### Get My Bookmarks
```http
GET /api/users/me/bookmarks
Authorization: Bearer {token}
```

#### Add Bookmark
```http
POST /api/users/{id}/bookmark
Authorization: Bearer {token}
Content-Type: application/json

{
  "collection": "favorites"  // Optional collection name
}
```

#### Remove Bookmark
```http
DELETE /api/users/{id}/bookmark
Authorization: Bearer {token}
```

#### Check if Bookmarked
```http
GET /api/users/{id}/bookmark/check
Authorization: Bearer {token}
```

#### Batch Check Bookmarks
```http
POST /api/bookmarks/batch-check
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_ids": ["uuid1", "uuid2", "uuid3"]
}
```

#### Get Bookmark Collections
```http
GET /api/bookmarks/collections
Authorization: Bearer {token}
```

#### Get Bookmark Stats
```http
GET /api/bookmarks/stats
Authorization: Bearer {token}
```

#### Update Bookmark
```http
PUT /api/bookmarks/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "collection": "new-collection-name"
}
```

---

## 📸 Media & Deliverables

### Media Upload

#### Register Media Asset
```http
POST /api/media/register
Authorization: Bearer {token}
Content-Type: application/json

{
  "filename": "photo.jpg",
  "size_bytes": 2048000,
  "mime_type": "image/jpeg",
  "purpose": "deliverable"  // deliverable, profile, post
}
```

#### Upload Media
```http
POST /api/media/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "file": <binary>,
  "purpose": "deliverable",
  "booking_id": "booking-uuid"  // If deliverable
}
```

#### Get One-Time Download Link
```http
POST /api/media/{id}/one-time-link
Authorization: Bearer {token}
```

**Response:**
```json
{
  "download_url": "https://api.example.com/api/media/one-time/{token}",
  "expires_at": "2025-12-01T12:00:00Z"
}
```

#### Download with One-Time Link
```http
GET /api/media/one-time/{token}
```
**Note:** No authentication required, but token expires after use or 24 hours

### Deliverables

#### Upload Deliverable (Creator Only)
```http
POST /api/deliverables/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "file": <binary>,
  "booking_id": "booking-uuid",
  "title": "Final edited photos",
  "description": "50 edited photos from the session"
}
```

#### List Deliverables for Booking
```http
GET /api/deliverables/booking/{bookingId}
Authorization: Bearer {token}
```

#### Request Download Token
```http
POST /api/deliverables/request-download/{deliverableId}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "download_token": "unique-token",
  "download_url": "/api/deliverables/track/{token}",
  "expires_at": "2025-12-01T12:00:00Z"
}
```

#### Track Download
```http
GET /api/deliverables/track/{token}
```
**Note:** Redirects to actual file and tracks download

#### Delete Deliverable
```http
DELETE /api/deliverables/{id}
Authorization: Bearer {token}
```

---

## ⭐ Reviews

### List All Reviews
```http
GET /api/reviews/
Authorization: Bearer {token}
```

### Get Review by ID
```http
GET /api/reviews/{id}
Authorization: Bearer {token}
```

### Get Creator's Reviews
```http
GET /api/profiles/creator/{id}/reviews
Authorization: Bearer {token}
```

### Get Athlete's Reviews
```http
GET /api/profiles/athlete/{id}/reviews
Authorization: Bearer {token}
```

### Create Review
```http
POST /api/reviews/create
Authorization: Bearer {token}
Content-Type: application/json

{
  "booking_id": "booking-uuid",
  "rating": 5,                    // 1-5 stars
  "comment": "Excellent service! Very professional and delivered amazing photos.",
  "would_recommend": true
}
```

### Update Review
```http
PUT /api/reviews/update/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "rating": 5,
  "comment": "Updated review text"
}
```

### Delete Review
```http
DELETE /api/reviews/delete/{id}
Authorization: Bearer {token}
```

---

## 📊 Dashboard (Creator Only)

### Get Dashboard Stats
```http
GET /api/dashboard/stats
Authorization: Bearer {token}
```

**Response:**
```json
{
  "total_bookings": 45,
  "pending_bookings": 3,
  "completed_bookings": 38,
  "total_revenue_cents": 112500,
  "total_revenue": "$1,125.00",
  "average_rating": 4.8,
  "total_reviews": 32
}
```

### Get Analytics
```http
GET /api/dashboard/analytics
Authorization: Bearer {token}
```

**Query Parameters:**
- `start_date` - Start date (YYYY-MM-DD)
- `end_date` - End date (YYYY-MM-DD)
- `period` - day, week, month, year

### Get Recent Bookings
```http
GET /api/dashboard/recent-bookings
Authorization: Bearer {token}
```

**Query Parameters:**
- `limit` - Number of bookings to return (default: 10)

### Get Recent Revenues
```http
GET /api/dashboard/recent-revenues
Authorization: Bearer {token}
```

**Query Parameters:**
- `limit` - Number of revenue entries (default: 10)

---

## 🔔 Webhooks

### Stripe Webhook (Public)
```http
POST /api/payments/webhook
Stripe-Signature: {signature}
Content-Type: application/json

{...stripe event data...}
```

**Note:** This endpoint is called by Stripe, not by your app

---

## 📖 API Documentation

### Interactive Swagger UI
```http
GET /api/doc
```
**Note:** No authentication required

### OpenAPI JSON Spec
```http
GET /api/doc.json
```

---

## 🔒 Security & Rate Limiting

### Rate Limits
- **Login:** 5 attempts per 15 minutes (by IP + username)
- **Password Reset:** 3 attempts per hour (by IP)
- **Token Refresh:** 10 attempts per hour (by IP)
- **Registration:** 3 attempts per hour (by IP)
- **General API:** 100 requests per minute (by IP)

### Rate Limit Headers
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1699564800
Retry-After: 60
```

### Error Response (429)
```json
{
  "type": "https://api.23hec001.com/errors/rate-limit-exceeded",
  "title": "Rate Limit Exceeded",
  "status": 429,
  "detail": "Too many login attempts. Please try again later.",
  "instance": "/api/login_check",
  "retry_after": 600
}
```

---

## 🎯 Common Workflows

### 1. User Registration & Login
```bash
# Register
POST /api/users/register
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "role": "ROLE_ATHLETE"
}

# Login
POST /api/login_check
{
  "username": "user@example.com",
  "password": "SecurePass123!"
}

# Store tokens
access_token = response.token
refresh_token = response.refresh_token
```

### 2. Book a Service with Promo Code
```bash
# 1. Find creator and their services
GET /api/creators
GET /api/services/user/{creatorId}

# 2. Check availability
GET /api/slots/user/{creatorId}

# 3. Validate promo code (optional)
POST /api/promo-codes/validate
{
  "code": "SUMMER2025",
  "creator_id": "{creatorId}",
  "amount": 25000
}

# 4. Create booking
POST /api/bookings/create
{
  "creator_user_id": "{creatorId}",
  "service_offering_id": "{serviceId}",
  "start_time": "2025-12-01T10:00:00Z",
  "end_time": "2025-12-01T12:00:00Z",
  "promo_code": "SUMMER2025"
}

# 5. Create payment intent
POST /api/payments/intent
{
  "booking_id": "{bookingId}",
  "amount_cents": 20000,  // After discount
  "promo_code": "SUMMER2025"
}

# 6. Process payment with Stripe
# (Use client_secret with Stripe.js)
```

### 3. Creator Workflow
```bash
# 1. Create service offering
POST /api/services/create
{
  "title": "Sports Photography Session",
  "base_price_cents": 25000,
  "duration_minutes": 120
}

# 2. Create availability slots
POST /api/slots/bulk
{
  "start_date": "2025-12-01",
  "end_date": "2025-12-31",
  "time_slots": [
    {"start": "09:00", "end": "11:00"},
    {"start": "14:00", "end": "16:00"}
  ]
}

# 3. Create promo code
POST /api/promo-codes/create
{
  "code": "WELCOME20",
  "discount_type": "percentage",
  "discount_value": 20
}

# 4. Accept booking
POST /api/bookings/{id}/accept

# 5. Upload deliverables
POST /api/deliverables/upload
```

---

## 📝 Response Formats

### Success Response
```json
{
  "data": {...},
  "status": 200
}
```

### Paginated Response
```json
{
  "data": [...],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 150,
    "totalPages": 8,
    "hasNext": true,
    "hasPrev": false
  }
}
```

### Error Response (RFC 7807)
```json
{
  "type": "https://api.23hec001.com/errors/validation",
  "title": "Validation Error",
  "status": 400,
  "detail": "The provided data is invalid",
  "instance": "/api/bookings/create",
  "violations": [
    {
      "field": "start_time",
      "message": "Start time is required"
    }
  ]
}
```

---

## 🚀 Quick Reference

### Authentication Required
Most endpoints require `Authorization: Bearer {token}` header

### Public Endpoints
- `POST /api/users/register`
- `POST /api/login_check`
- `POST /api/token/refresh`
- `GET /api/doc`
- `GET /api/doc.json`
- `POST /api/payments/webhook` (Stripe only)
- `GET /api/media/one-time/{token}`

### Creator-Only Endpoints
- `POST /api/services/create`
- `POST /api/slots/create`
- `POST /api/promo-codes/create`
- `POST /api/deliverables/upload`
- `POST /api/bookings/{id}/accept`
- `POST /api/bookings/{id}/complete`
- `GET /api/dashboard/*`

### Content Types
- **JSON:** `Content-Type: application/json`
- **File Upload:** `Content-Type: multipart/form-data`

### Date Formats
- **ISO 8601:** `2025-12-01T10:00:00Z`
- **Date Only:** `2025-12-01`

### Currency
- All amounts in **cents** (e.g., 25000 = $250.00)
- Default currency: **USD**

---

**📚 For detailed examples and schemas, visit:** `http://localhost:8090/api/doc`

**🔒 Security Note:** Always use HTTPS in production!
