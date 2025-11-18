# PluGame - Sports Creator Marketplace Platform

A comprehensive Symfony-based API platform connecting sports creators (athletes, photographers, coaches) with fans and clients for bookings, content delivery, and social engagement.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Running the Application](#running-the-application)
- [API Documentation](#api-documentation)
- [Testing](#testing)
- [Project Structure](#project-structure)
- [Key Concepts](#key-concepts)
- [Development Guidelines](#development-guidelines)
- [Deployment](#deployment)
- [License](#license)

## Overview

PluGame is a sports-focused marketplace platform that enables:

- **Creators** (athletes, content creators, coaches) to offer services, manage bookings, and monetize their expertise
- **Athletes/Fans** to discover creators, book services, receive deliverables, and engage with content
- **Secure payments** via Stripe with wallet system and promo code support
- **Real-time messaging** with Mercure for instant communication
- **Social features** including follows, likes, comments, bookmarks, and reviews

## Features

### 🔐 Authentication & Authorization
- JWT-based authentication with refresh tokens
- Role-based access control (Creator, Athlete, Admin)
- Multi-device session management
- Secure logout and token revocation

### 👥 User Management
- User registration and profile management
- Creator and athlete profiles
- Online status and presence tracking
- User search and discovery with filters

### 📅 Booking System
- Service offering creation and management
- Availability slot scheduling (bulk creation supported)
- Booking lifecycle (pending → accepted/declined → completed/cancelled)
- Automated booking notifications
- Booking history and analytics

### 💳 Payment Processing
- Stripe integration for secure payments
- Split payments (deposit + remaining balance)
- Wallet system for credits
- Promo code support (percentage and fixed discounts)
- Payment intent creation and confirmation
- Webhook handling for payment events
- Revenue tracking and payout management

### 📸 Media & Deliverables
- R2 cloud storage integration for media assets
- Direct client-side uploads with presigned URLs
- Deliverable upload and management
- One-time download links for security
- Email tracking for deliverable notifications
- ZIP archive generation for batch downloads

### 💬 Messaging & Real-time
- Conversation management between users
- Real-time messaging with Mercure
- Message history and pagination
- Typing indicators and read receipts support

### 👍 Social Features
- Follow/unfollow users
- Bookmark favorite creators with collections
- Like and comment on posts
- Feed system with personalized content
- Reviews and ratings for completed bookings
- Batch operations for efficiency

### 📊 Creator Dashboard
- Revenue analytics and growth metrics
- Booking statistics and conversion rates
- Recent bookings and transactions
- Top-performing services
- Rating and review summaries

### 🎁 Marketing & Engagement
- Promo code creation and management
- Usage limits and expiration dates
- Minimum amount requirements
- Per-user usage tracking

## Tech Stack

### Backend
- **PHP 8.2+** - Modern PHP with typed properties and attributes
- **Symfony 7.x** - Full-stack web framework
- **Doctrine ORM** - Database abstraction and entity management
- **API Platform** (optional) - REST API tooling

### Database
- **MySQL 8.0** - Primary database
- **Redis** - Caching and session storage

### External Services
- **Stripe** - Payment processing and payouts
- **Cloudflare R2** - Object storage for media
- **Mercure** - Real-time pub/sub server
- **SMTP** - Email delivery

### Development Tools
- **Docker & Docker Compose** - Containerization
- **PHPStan** - Static analysis (Level 6)
- **PHP_CodeSniffer** - Code style enforcement
- **PHPUnit** - Testing framework
- **Symfony CLI** - Development server and tools

## Prerequisites

- **Docker** 20.10+ and **Docker Compose** 2.x
- **Git** for version control
- **Make** (optional, for convenience commands)
- **Operating System**: Linux (Ubuntu 20.04+), macOS, or Windows with WSL2

## Installation

### 1. Clone the Repository

```bash
git clone git@github.com-personal:gaoubak/PluGame.git
cd PluGame
```

### 2. Build Docker Containers

```bash
docker compose build --no-cache --pull
```

### 3. Start Services

```bash
docker-compose up -d
```

This starts:
- `alpine` - PHP 8.2 CLI container
- `nginx` - Web server
- `db` - MySQL database
- `mercure` - Real-time messaging server
- `adminer` - Database management UI

### 4. Install Dependencies

```bash
docker-compose exec alpine composer install
```

### 5. Generate JWT Keys

```bash
docker-compose exec alpine bin/console lexik:jwt:generate-keypair
```

This creates JWT public/private keys in `config/jwt/`.

### 6. Create Database

```bash
docker-compose exec alpine bin/console doctrine:database:create
docker-compose exec alpine bin/console doctrine:migrations:migrate --no-interaction
```

### 7. Load Fixtures (Optional)

```bash
docker-compose exec alpine bin/console doctrine:fixtures:load --no-interaction
```

## Configuration

### Environment Variables

Create a `.env.local` file in the project root with the following:

```bash
###> symfony/framework-bundle ###
APP_ENV=dev
APP_SECRET=your-random-secret-key-here
###< symfony/framework-bundle ###

###> doctrine/doctrine-bundle ###
DATABASE_URL="mysql://app:!ChangeMe!@db:3306/app?serverVersion=8.0.32&charset=utf8mb4"
###< doctrine/doctrine-bundle ###

###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-jwt-passphrase
JWT_TTL=3600
REFRESH_TOKEN_TTL=2592000
###< lexik/jwt-authentication-bundle ###

###> nelmio/cors-bundle ###
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
###< nelmio/cors-bundle ###

###> symfony/mercure-bundle ###
MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=your-mercure-secret
###< symfony/mercure-bundle ###

###> stripe ###
STRIPE_SECRET_KEY=sk_test_your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret
PLATFORM_FEE_PERCENT=10
###< stripe ###

###> cloudflare r2 ###
R2_ACCOUNT_ID=your-account-id
R2_ACCESS_KEY_ID=your-access-key
R2_SECRET_ACCESS_KEY=your-secret-key
R2_BUCKET=your-bucket-name
R2_PUBLIC_URL=https://your-bucket.r2.dev
###< cloudflare r2 ###

###> symfony/mailer ###
MAILER_DSN=smtp://user:pass@smtp.example.com:587
###< symfony/mailer ###
```

### Key Configuration Files

- `config/packages/security.yaml` - Authentication and authorization
- `config/packages/doctrine.yaml` - Database configuration
- `config/packages/lexik_jwt_authentication.yaml` - JWT settings
- `config/packages/nelmio_cors.yaml` - CORS configuration
- `config/packages/mercure.yaml` - Real-time messaging
- `config/services.yaml` - Service container configuration

## Running the Application

### Start All Services

```bash
docker-compose up -d
```

### Access Points

- **API Endpoint**: http://localhost:8090
- **Adminer (DB UI)**: http://localhost:8080
- **Mercure Hub**: http://localhost:3000/.well-known/mercure

### Useful Commands

```bash
# View logs
docker-compose logs -f alpine

# Access PHP container
docker-compose exec alpine sh

# Run Symfony console commands
docker-compose exec alpine bin/console [command]

# Clear cache
docker-compose exec alpine bin/console cache:clear

# Run migrations
docker-compose exec alpine bin/console doctrine:migrations:migrate

# Stop services
docker-compose down

# Stop and remove volumes
docker-compose down -v
```

## API Documentation

### Interactive API Documentation

When running in dev mode, access Swagger/OpenAPI documentation at:

```
http://localhost:8090/api/doc
```

### Postman Collection

Import the comprehensive Postman collection for testing:

```
Postman Collections/23HEC001_Complete_API_FULL.postman_collection.json
```

This includes all 158+ endpoints organized by feature with:
- Example requests and responses
- Auto-saving environment variables
- Authentication flows
- Test scripts

### Authentication Flow

1. **Register**: `POST /api/users/register`
2. **Login**: `POST /api/login_check`
3. **Use Token**: Add `Authorization: Bearer {token}` header
4. **Refresh**: `POST /api/token/refresh` with refresh token
5. **Logout**: `POST /api/logout` or `POST /api/token/revoke-all`

### Key Endpoints by Feature

See [API_ROUTES_GUIDE.md](API_ROUTES_GUIDE.md) for complete endpoint documentation.

## Testing

### Run All Tests

```bash
docker-compose exec alpine vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
docker-compose exec alpine vendor/bin/phpunit tests/Controller/BookingControllerTest.php
```

### Code Quality Checks

```bash
# PHPStan (Static Analysis)
docker-compose exec alpine vendor/bin/phpstan analyse src

# PHP CodeSniffer (Code Style)
docker-compose exec alpine vendor/bin/phpcs src

# Fix code style issues
docker-compose exec alpine vendor/bin/phpcbf src
```

### Run All Quality Checks

```bash
./scripts/check.sh
```

## Project Structure

```
├── config/                     # Configuration files
│   ├── packages/              # Bundle configurations
│   ├── routes/                # Route definitions
│   └── services.yaml          # Service container
├── migrations/                # Database migrations
├── public/                    # Web root
│   └── index.php             # Front controller
├── src/
│   ├── Controller/           # API controllers (26 controllers)
│   ├── Entity/               # Doctrine entities (25+ entities)
│   ├── Repository/           # Custom repositories
│   ├── Service/              # Business logic services
│   ├── DTO/                  # Data Transfer Objects
│   ├── Form/                 # Form types
│   ├── Security/             # Authentication & voters
│   ├── EventListener/        # Event subscribers
│   ├── Exception/            # Custom exceptions
│   └── Traits/               # Reusable traits
├── templates/                # Twig templates (emails)
├── tests/                    # PHPUnit tests
├── var/                      # Cache, logs
├── vendor/                   # Composer dependencies
├── docker-compose.yml        # Docker services
├── Dockerfile                # PHP container
├── composer.json             # PHP dependencies
└── README.md                # This file
```

## Key Concepts

### Entities

**User-related:**
- `User` - Main user entity with roles
- `CreatorProfile` - Creator-specific profile data
- `AthleteProfile` - Athlete-specific profile data
- `RefreshToken` - JWT refresh tokens

**Booking System:**
- `ServiceOffering` - Services offered by creators
- `AvailabilitySlot` - Time slots when creators are available
- `Booking` - Booking instance
- `BookingSegment` - Time segments within a booking

**Payment System:**
- `Payment` - Payment records
- `WalletCredit` - User wallet credits
- `PromoCode` - Discount codes
- `PayoutMethod` - Creator payout information

**Content & Social:**
- `MediaAsset` - Uploaded media files
- `Deal` - Posted content/deals
- `Like` - Post likes
- `Comment` - Post comments
- `Bookmark` - Bookmarked users
- `Follow` - User follows
- `Review` - Booking reviews

**Communication:**
- `Conversation` - Message threads
- `Message` - Individual messages

### Traits

- `ApiResponseTrait` - Standardized JSON responses
- `FormHandlerTrait` - Form processing utilities
- `Timestamps` - CreatedAt/UpdatedAt fields
- `SoftDeletable` - Soft delete functionality
- `UuidId` - UUID primary keys

### Services

- `StripeService` - Payment processing
- `R2Storage` - Media storage
- `MercureService` - Real-time messaging
- `EmailService` - Email notifications
- `WalletService` - Wallet management
- `PricingService` - Price calculations
- `PromoCodeService` - Promo code validation
- `CacheService` - Application caching

## Development Guidelines

### Code Style

This project follows PSR-12 coding standards. Use PHP_CodeSniffer:

```bash
./scripts/lint.sh
```

### Static Analysis

Code must pass PHPStan level 6:

```bash
docker-compose exec alpine vendor/bin/phpstan analyse src
```

### Error Handling

All API errors follow RFC 7807 Problem Details format:

```json
{
  "type": "https://example.com/probs/invalid-booking",
  "title": "Invalid Booking Request",
  "status": 400,
  "detail": "The booking time slot is no longer available"
}
```

### Database Migrations

Always create migrations for schema changes:

```bash
docker-compose exec alpine bin/console make:migration
docker-compose exec alpine bin/console doctrine:migrations:migrate
```

### Security

- Never commit `.env.local` or sensitive credentials
- Use voters for authorization checks
- Validate all user input
- Sanitize output to prevent XSS
- Use parameterized queries (Doctrine does this automatically)

## Deployment

### Production Checklist

- [ ] Set `APP_ENV=prod` in `.env.local`
- [ ] Generate strong `APP_SECRET`
- [ ] Use production database credentials
- [ ] Configure production Stripe keys
- [ ] Set up proper CORS origins
- [ ] Enable HTTPS/SSL
- [ ] Configure rate limiting
- [ ] Set up monitoring and logging
- [ ] Run `composer install --no-dev --optimize-autoloader`
- [ ] Clear and warm up cache: `bin/console cache:clear --env=prod`
- [ ] Run database migrations
- [ ] Set proper file permissions

### Performance Optimization

- Enable OPcache in production
- Use Redis for session storage
- Configure Doctrine query caching
- Enable HTTP caching headers
- Compress assets
- Use CDN for static files

## Documentation

Additional documentation available:

- [API Routes Guide](API_ROUTES_GUIDE.md)
- [Postman Collection Guide](Postman%20Collections/POSTMAN_GUIDE.md)
- [Complete API Collection Summary](Postman%20Collections/COMPLETE_API_COLLECTION_SUMMARY.md)
- [Security Implementation](SECURITY_IMPLEMENTATION_STATUS.md)
- [Database Optimization](DATABASE_OPTIMIZATION_GUIDE.md)
- [Caching Implementation](CACHING_IMPLEMENTATION_GUIDE.md)
- [DTO Implementation](DTO_IMPLEMENTATION_GUIDE.md)
- [Voter Implementation](VOTER_IMPLEMENTATION_GUIDE.md)
- [Soft Delete Guide](SOFT_DELETE_GUIDE.md)

## Troubleshooting

### Common Issues

**JWT keys not found:**
```bash
docker-compose exec alpine bin/console lexik:jwt:generate-keypair
```

**Database connection refused:**
- Check that `db` container is running: `docker-compose ps`
- Verify `DATABASE_URL` in `.env.local`
- Ensure database exists: `bin/console doctrine:database:create`

**CORS errors:**
- Update `CORS_ALLOW_ORIGIN` in `.env.local`
- Check `config/packages/nelmio_cors.yaml`

**Mercure not connecting:**
- Verify `MERCURE_URL` and `MERCURE_PUBLIC_URL`
- Check Mercure container logs: `docker-compose logs mercure`

**File upload errors:**
- Check R2 credentials
- Verify bucket permissions
- Check file size limits in `php.ini`

## Contributing

1. Create a feature branch from `main`
2. Make your changes following code style guidelines
3. Run tests and quality checks
4. Create a pull request with detailed description
5. Wait for code review

## Support

For issues and questions:
- Create an issue in the repository
- Contact the development team

## License

[Specify your license here]

---

**Version:** 2.0
**Last Updated:** 2025-01-09
**Symfony Version:** 7.x
**PHP Version:** 8.2+
