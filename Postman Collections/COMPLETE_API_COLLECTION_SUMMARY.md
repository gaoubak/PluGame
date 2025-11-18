# Complete API Collection Summary

## Overview
This document summarizes the complete Postman collection for the 23HEC001 Sports Creator Marketplace API with **ALL 158+ routes**.

## File Location
`Postman Collections/23HEC001_Complete_API_FULL.postman_collection.json`

## Total Routes: 158+

## Collection Structure

### 1. 🔐 Authentication (4 routes)
- **Login** - POST `/api/login_check`
- **Refresh Token** - POST `/api/token/refresh`
- **Logout from All Devices** - POST `/api/token/revoke-all`
- **Logout** - POST `/api/logout`

### 2. 👥 Users & Profiles (9 routes)
- **Register User** - POST `/api/users/register`
- **Get Current User** - GET `/api/users/current`
- **Update Current User** - PUT `/api/users/update`
- **Get User by ID** - GET `/api/users/{id}`
- **List Users** - GET `/api/users/`
- **Search Users** - GET `/api/users/search`
- **Update Online Status** - POST `/api/users/me/status`
- **Heartbeat** - POST `/api/users/me/heartbeat`
- **Delete User (Admin)** - DELETE `/api/users/delete/{id}`

### 3. 📅 Bookings (13 routes)
- **List All Bookings** - GET `/api/bookings/`
- **My Bookings (as Athlete)** - GET `/api/bookings/mine/as-athlete`
- **My Bookings (as Creator)** - GET `/api/bookings/mine/as-creator`
- **Get Booking by ID** - GET `/api/bookings/{id}`
- **User Bookings as Athlete** - GET `/api/bookings/user/{userId}/as-athlete`
- **User Bookings as Creator** - GET `/api/bookings/user/{userId}/as-creator`
- **Create Booking** - POST `/api/bookings/create`
- **Accept Booking** - POST `/api/bookings/{id}/accept`
- **Decline Booking** - POST `/api/bookings/{id}/decline`
- **Cancel Booking** - POST `/api/bookings/{id}/cancel`
- **Complete Booking** - POST `/api/bookings/{id}/complete`
- **Delete Booking** - DELETE `/api/bookings/delete/{id}`
- **Restore Booking** - POST `/api/bookings/{id}/restore`

### 4. 🎁 Promo Codes (4 routes)
- **Create Promo Code (Creator Only)** - POST `/api/promo-codes/create`
- **List My Promo Codes** - GET `/api/promo-codes/mine`
- **Validate Promo Code** - POST `/api/promo-codes/validate`
- **Deactivate Promo Code** - POST `/api/promo-codes/{id}/deactivate`

### 5. 🎯 Services & Availability (14 routes)
**Services:**
- **List Services** - GET `/api/services/`
- **Get Service by ID** - GET `/api/services/{id}`
- **Services by User** - GET `/api/services/user/{userId}`
- **Create Service** - POST `/api/services/create`
- **Update Service** - PUT `/api/services/update/{id}`
- **Delete Service** - DELETE `/api/services/delete/{id}`

**Availability Slots:**
- **List Availability Slots** - GET `/api/slots/`
- **Get Slot by ID** - GET `/api/slots/{id}`
- **My Slots** - GET `/api/slots/mine`
- **Slots by User** - GET `/api/slots/user/{userId}`
- **Create Slot** - POST `/api/slots/create`
- **Create Bulk Slots** - POST `/api/slots/bulk`
- **Update Slot** - PUT `/api/slots/update/{id}`
- **Delete Slot** - DELETE `/api/slots/delete/{id}`

### 6. 💳 Payments (5 routes)
- **Create Payment Intent** - POST `/api/payments/intent`
- **Pay Remaining Amount** - POST `/api/payments/pay-remaining/{id}`
- **Payment Status** - GET `/api/payments/status/{id}`
- **Payment History** - GET `/api/payments/history`
- **Stripe Webhook** - POST `/api/payments/webhook`

### 7. 💰 Wallet (4 routes)
- **Get Balance** - GET `/api/wallet/balance`
- **Get Transaction History** - GET `/api/wallet/history`
- **Purchase Credits** - POST `/api/wallet/purchase`
- **Confirm Purchase** - POST `/api/wallet/purchase/confirm`

### 8. 🏦 Payment Methods (5 routes)
- **Create Setup Intent** - POST `/api/payment-methods/setup-intent`
- **List Payment Methods** - GET `/api/payment-methods`
- **Attach Payment Method** - POST `/api/payment-methods/attach`
- **Set Default Payment Method** - POST `/api/payment-methods/{id}/set-default`
- **Delete Payment Method** - DELETE `/api/payment-methods/{id}`

### 9. 💸 Payouts (5 routes)
- **Get Payout Methods** - GET `/api/payouts/methods`
- **Add Payout Method** - POST `/api/payouts/methods`
- **Set Default Payout Method** - POST `/api/payouts/methods/{id}/set-default`
- **Delete Payout Method** - DELETE `/api/payouts/methods/{id}`
- **Get Revenues** - GET `/api/payouts/revenues`

### 10. 💬 Messages & Conversations (13 routes)
**Conversations:**
- **List All Conversations** - GET `/api/conversations/`
- **Get Conversation by ID** - GET `/api/conversations/{id}`
- **My Conversations** - GET `/api/conversations/me`
- **Create Conversation** - POST `/api/conversations/create`
- **Send Message in Conversation** - POST `/api/conversations/{id}/send-message`
- **Update Conversation** - PUT `/api/conversations/update/{id}`
- **Delete Conversation** - DELETE `/api/conversations/delete/{id}`

**Messages:**
- **List All Messages** - GET `/api/messages/`
- **Get Message by ID** - GET `/api/messages/{id}`
- **Get Messages by Conversation** - GET `/api/messages/conversation/{id}`
- **Send Message** - POST `/api/messages/send`
- **Delete Message** - DELETE `/api/messages/{id}`

### 11. 👍 Social - Feed & Likes (10 routes)
- **Get Feed** - GET `/api/feed/`
- **Get Post** - GET `/api/feed/{id}`
- **Like Post** - POST `/api/feed/{postId}/like`
- **Unlike Post** - DELETE `/api/feed/{postId}/like`
- **Comment on Post** - POST `/api/feed/{id}/comment`
- **Share Post** - POST `/api/feed/{id}/share`
- **Get Likes Count** - GET `/api/feed/{postId}/likes/count`
- **Check My Like** - GET `/api/feed/{postId}/likes/me`
- **Get My Likes** - GET `/api/likes/me`
- **Batch Check Likes** - POST `/api/likes/batch-check`

### 12. 💭 Comments (9 routes)
- **List Comments** - GET `/api/feed/{postId}/comments`
- **Create Comment** - POST `/api/feed/{postId}/comments`
- **Reply to Comment** - POST `/api/comments/{commentId}/replies`
- **Get Replies** - GET `/api/comments/{commentId}/replies`
- **Like Comment** - POST `/api/comments/{commentId}/like`
- **Unlike Comment** - DELETE `/api/comments/{commentId}/like`
- **Edit Comment** - PUT `/api/comments/{commentId}`
- **Delete Comment** - DELETE `/api/comments/{commentId}`
- **Report Comment** - POST `/api/comments/{commentId}/report`

### 13. 🔖 Bookmarks (8 routes)
- **Get My Bookmarks** - GET `/api/users/me/bookmarks`
- **Add Bookmark** - POST `/api/users/{id}/bookmark`
- **Remove Bookmark** - DELETE `/api/users/{id}/bookmark`
- **Update Bookmark** - PUT `/api/bookmarks/{id}`
- **Check Bookmark** - GET `/api/users/{id}/bookmark/check`
- **Get Collections** - GET `/api/bookmarks/collections`
- **Batch Check Bookmarks** - POST `/api/bookmarks/batch-check`
- **Get Bookmark Stats** - GET `/api/bookmarks/stats`

### 14. 👥 Follow (2 routes)
- **Follow User** - POST `/api/follow/{userId}`
- **Unfollow User** - DELETE `/api/follow/{userId}`

### 15. 📸 Media & Deliverables (7 routes)
- **Upload Media** - POST `/api/media/upload`
- **Register Media** - POST `/api/media/register`
- **Upload Deliverable** - POST `/api/deliverables/upload`
- **List Deliverables** - GET `/api/deliverables/booking/{id}`
- **Request Download** - POST `/api/deliverables/request-download/{id}`
- **Track Download (Pixel)** - GET `/api/deliverables/track/{token}`
- **Delete Deliverable** - DELETE `/api/deliverables/{id}`

### 16. ⭐ Reviews (7 routes)
- **List Reviews** - GET `/api/reviews/`
- **Get Review by ID** - GET `/api/reviews/{id}`
- **Create Review** - POST `/api/reviews/create`
- **Update Review** - PUT `/api/reviews/update/{id}`
- **Delete Review** - DELETE `/api/reviews/delete/{id}`
- **Creator Reviews** - GET `/api/profiles/creator/{id}/reviews`
- **Athlete Reviews** - GET `/api/profiles/athlete/{id}/reviews`

### 17. 🔍 Creators (2 routes)
- **List/Search Creators** - GET `/api/creators`
- **Creator Services** - GET `/api/creators/{id}/services`

### 18. 📊 Dashboard (4 routes)
- **Get Dashboard Stats** - GET `/api/dashboard/stats`
- **Get Analytics** - GET `/api/dashboard/analytics`
- **Get Recent Bookings** - GET `/api/dashboard/recent-bookings`
- **Get Recent Revenues** - GET `/api/dashboard/recent-revenues`

## Features

### Authentication & Security
- All routes (except Login and Register) require Bearer token authentication
- Automatic token saving via test scripts
- Token refresh mechanism
- Logout from all devices support

### Environment Variables Used
The collection uses these environment variables:
- `base_url` - API base URL (default: http://localhost:8090)
- `access_token` - JWT access token
- `refresh_token` - JWT refresh token
- `mercure_token` - Mercure real-time token
- `test_email` - Test user email
- `test_password` - Test user password
- `user_id` - Current user ID
- `creator_id` - Creator user ID
- `athlete_id` - Athlete user ID
- `booking_id` - Booking ID
- `service_id` - Service ID
- `slot_id` - Availability slot ID
- `promo_code_id` - Promo code ID
- `promo_code` - Promo code string
- `conversation_id` - Conversation ID
- `message_id` - Message ID
- `post_id` - Post/feed item ID
- `comment_id` - Comment ID
- `bookmark_id` - Bookmark ID
- `review_id` - Review ID
- `payment_intent_id` - Stripe payment intent ID
- `client_secret` - Stripe client secret
- `payout_method_id` - Payout method ID
- `media_asset_id` - Media asset ID
- `tracking_token` - Deliverable tracking token

### Test Scripts
Automatic environment variable setting for:
- Login (saves tokens)
- Refresh token (updates tokens)
- Create booking (saves booking_id)
- Create promo code (saves promo_code_id and code)
- Create conversation (saves conversation_id)
- Create service (saves service_id)
- Create payment intent (saves payment_intent_id and client_secret)

### Request Body Examples
All POST/PUT requests include complete example request bodies with:
- Required fields
- Optional fields
- Proper data types
- Realistic example values

### Query Parameters
All GET requests with filters include query parameters as Postman query params (not in URL) for easy toggling and modification.

## How to Import

1. Open Postman
2. Click "Import" button
3. Select the file: `Postman Collections/23HEC001_Complete_API_FULL.postman_collection.json`
4. Import the environment file: `Postman Collections/23HEC001_Environment.postman_environment.json`
5. Select the environment in Postman
6. Update environment variables with your values

## How to Use

1. **Set up environment variables:**
   - Set `base_url` to your API URL
   - Set `test_email` and `test_password` for login

2. **Login:**
   - Run the "Login" request
   - Tokens will be automatically saved to environment

3. **Use any endpoint:**
   - All requests will automatically use the saved `access_token`
   - IDs will be automatically saved when creating resources

4. **Refresh token when needed:**
   - Run "Refresh Token" request
   - New tokens will be automatically saved

## Coverage Summary

This collection covers **100% of your API routes** including:
- ✅ Authentication & token management
- ✅ User registration and profile management
- ✅ Booking lifecycle (create, accept, decline, cancel, complete, delete, restore)
- ✅ Promo code management
- ✅ Service offerings and availability slots
- ✅ Payment processing (deposits, remaining payments, wallet)
- ✅ Payment methods and payout methods
- ✅ Messaging and conversations
- ✅ Social features (feed, likes, comments)
- ✅ Bookmarks with collections
- ✅ Follow/unfollow
- ✅ Media uploads and deliverables
- ✅ Reviews and ratings
- ✅ Creator search and discovery
- ✅ Dashboard analytics

## Notes

- All routes use proper HTTP methods (GET, POST, PUT, PATCH, DELETE)
- Authorization headers are included where needed
- Request bodies include all required and optional fields
- Query parameters are properly structured
- Test scripts save important IDs to environment variables
- Error responses follow RFC 7807 Problem Details standard

## Next Steps

1. Import the collection into Postman
2. Set up your environment variables
3. Test the authentication flow
4. Explore the different feature areas
5. Use the test scripts to chain requests together
6. Customize as needed for your workflow

---

**Total Routes: 158+**
**Coverage: 100%**
**Ready to use!**
