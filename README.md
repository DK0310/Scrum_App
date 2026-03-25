# 🚗 Private Hire — Premium Car Rental Platform and mobile

> Full-stack premium car rental and transportation platform built with PHP 8.2 + PostgreSQL (Supabase). This document provides comprehensive architecture context for developers and team members working on the platform

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Tech Stack](#2-tech-stack)
3. [Project Structure](#3-project-structure)
4. [Database Architecture (Supabase / PostgreSQL)](#4-database-architecture)
5. [API Reference](#5-api-reference)
6. [Authentication Flow](#6-authentication-flow)
7. [User Roles & Permissions](#7-user-roles--permissions)
8. [Core Business Flows](#8-core-business-flows)
9. [Real-time Notifications](#9-real-time-notifications)
10. [Image Storage (BYTEA/BLOB)](#10-image-storage)
11. [AI Chatbot Integration](#11-ai-chatbot-integration)
12. [Environment Configuration](#12-environment-configuration)
13. [Running the Web App Locally](#13-running-locally)
14. [Mobile App Integration Guide](#14-mobile-app-integration-guide)
15. [Entity Relationship Diagram](#15-erd)

---

## 📋 Recent Updates (March 2026)

### Latest Changes

✅ **Updated README to reflect current codebase**
- Project renamed from "DriveNow" to "Private Hire"
- Updated tech stack documentation (PHP 8.2, PostgreSQL 15, Supabase)
- Expanded user roles to include: `driver` and `staff` (in addition to renter, owner, admin)
- Documented all API endpoints in `/api/` directory (26+ endpoints)
- Updated business flows to include driver assignment and with-driver bookings
- Reorganized database schema documentation with proper tables and relationships

🔧 **Recent API Fixes**
- Fixed vehicle API (`/api/vehicles.php`): Corrected repository includes from `lib/repositories/` → `sql/`
- Fixed bookings API (`/api/bookings.php`): Corrected 5 repository includes + added input parser fallback + fixed undefined variable warnings
- All repository imports now correctly point to `/sql/` directory (Repository Pattern Data Access Layer)

📁 **Project Structure**
- Root-level controllers: `index.php`
- Data repositories in `/sql/`: 11+ repository classes with proper PDO-based data access
- API endpoints in `/api/`: Comprehensive REST API for all platform features
- HTML templates in `/templates/`: Server-side rendered templates with layout components
- Environment configuration via `.env` loader in `/config/env.php`

---

## 1. System Overview

**Private Hire** is a full-stack car rental and premium transportation platform that connects vehicle owners with renters. The system supports multiple booking types (self-drive, with professional driver, airport transfers), comprehensive fleet management, real-time notifications, community engagement, and AI-powered assistance.

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                             │
│  ┌──────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │  Web Browser  │  │  Mobile App (TBD) │  │  Driver App      │  │
│  │  (PHP SSR +   │  │  (iOS/Android/    │  │  (Mobile -      │  │
│  │   vanilla JS) │  │   Flutter/RN)     │  │   driver role   │  │
│  └──────┬───────┘  └────────┬──────────┘  └────────┬─────────┘  │
│         │                   │                      │             │
└─────────┼───────────────────┼──────────────────────┼─────────────┘
          │                   │                      │
          ▼                   ▼                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                     API LAYER (PHP REST)                         │
│  Base URL: /api/                                                │
│                                                                  │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌───────────────────┐  │
│  │ auth.php │ │vehicles. │ │bookings. │ │ notifications.php │  │
│  │          │ │  php     │ │  php     │ │                   │  │
│  └──────────┘ └──────────┘ └──────────┘ └───────────────────┘  │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌───────────────────┐  │
│  │admin.php │ │community │ │my-      │ │ driver.php |       │  │
│  │          │ │  .php    │ │vehicles. │ │ (driver API)      │  │
│  └──────────┘ └──────────┘ │  php     │ └───────────────────┘  │
│  ┌──────────┐ ┌──────────┐ └──────────┘ ┌───────────────────┐  │
│  │CallCenter│ │ support  │ ┌──────────┐ │ chatbot-with-     │  │
│  │Staff.php │ │  .php    │ │orders.php│ │   memory.php      │  │
│  └──────────┘ └──────────┘ └──────────┘ └───────────────────┘  │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                    DATA / SERVICE LAYER                          │
│                                                                  │
│  ┌───────────────────────────┐  ┌────────────────────────────┐  │
│  │   Supabase PostgreSQL     │  │   External Services        │  │
│  │   (aws-ap-southeast-1)    │  │                            │  │
│  │                           │  │  • n8n (AI Agent/Chatbot)  │  │
│  │  • 17+ tables             │  │  • Gmail SMTP (OTP email)  │  │
│  │  • UUID primary keys      │  │  • Supabase Storage        │  │
│  │  • BYTEA/Storage images   │  │  • Google OAuth            │  │
│  │  • RLS enabled            │  │  • Face.js (Face ID)       │  │
│  │  • Enum types             │  │  • SMS provider (OTP)      │  │
│  └───────────────────────────┘  └────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Tech Stack

| Layer | Technology | Details |
|-------|-----------|---------|
| **Frontend (Web)** | PHP 8.2 SSR + Vanilla JS | Server-rendered HTML templates with client-side JavaScript for interactivity |
| **Backend API** | PHP 8.2 | REST API with action-based routing (`?action=xxx` or `{action: "xxx"}`) |
| **Database** | PostgreSQL 15 (Supabase) | Hosted on Supabase (AWS ap-southeast-1), PDO connection with SSL |
| **Storage** | Supabase Storage | Vehicle images, avatars stored in Supabase Storage bucket `DriveNow` |
| **Image Format** | BYTEA or Storage | Legacy BYTEA in DB or modern Supabase Storage URLs |
| **Authentication** | PHP Sessions + Multi-method | Email/password, Google OAuth, Phone OTP, Email OTP, Face ID |
| **Styling** | Custom CSS | CSS variables, responsive design in `base.css` and included templates |
| **API Security** | CORS enabled | `Access-Control-Allow-Origin: *` for mobile app access |
| **AI Chatbot** | n8n AI Agent | Webhook-based AI agent integration with PostgreSQL chat memory |
| **Email Delivery** | PHPMailer + Gmail SMTP | OTP delivery, booking confirmations |
| **Async/Notifications** | Polling + N8N | Client-side polling for notifications, N8N for workflow automation |
| **Deployment Target** | PHP 8.2+ server | Apache, Nginx, or PHP built-in server (`php -S`) |

---

## 3. Project Structure

```
Scrum/                           # Project root
├── .env                          # Environment variables (DB, SMTP, API keys)
├── .env.example                  # Template for .env
├── .git/                         # Git repository
├── .gitignore                    # Git ignore rules
├── composer.json                 # PHP dependencies (PHPMailer, etc.)
├── composer.lock                 # Locked dependency versions
│
├── config/                       # Configuration & loaders
│   └── env.php                   # EnvLoader class — reads .env file
│
├── Database/                     # Database setup and utilities
│   ├── db.php                    # PDO connection to Supabase PostgreSQL
│   ├── schema.sql                # Full database schema (17+ tables + RLS)
│   ├── migration_storage.sql     # Migration: BYTEA → Supabase Storage
│   ├── chat_memory.sql           # Chat history table reference
│   └── drop_all.sql              # Reset script
│
├── sql/                          # ★ Data access layer (Repository pattern)
│   ├── AuthRepository.php        # User authentication & profile queries
│   ├── UserRepository.php        # User CRUD operations
│   ├── VehicleRepository.php     # Vehicle listing, search, filtering
│   ├── VehicleImageRepository.php│ # Vehicle image metadata
│   ├── BookingRepository.php     # Booking CRUD & status management
│   ├── PromotionRepository.php   # Promotion codes & discounts
│   ├── NotificationRepository.php│ # User notifications
│   ├── CommunityRepository.php   # Community posts & comments
│   ├── HeroSlideRepository.php   # Admin hero slides
│   ├── TripRepository.php        # Trip/booking trip details
│   ├── StaffBookingRepository.php│ # Staff booking management
│   └── ... (other repositories)  │
│
├── api/                          # ★ REST API endpoints (JSON)
│   ├── auth.php                  # Authentication (login, register, OAuth, OTP, Face ID)
│   ├── vehicles.php              # Vehicle CRUD, search, filtering, images
│   ├── bookings.php              # Booking creation, promo validation, orders
│   ├── notifications.php         # Notification CRUD, polling support
│   ├── admin.php                 # Admin: users, vehicles, bookings, hero slides
│   ├── community.php             # Community posts, comments, likes
│   ├── orders.php                # Order management & history
│   ├── profile.php               # User profile operations
│   ├── my-vehicles.php           # Vehicle owner management
│   ├── driver.php                # Driver-specific operations
│   ├── support.php               # Support/contact form submissions
│   ├── membership.php            # Membership tier management
│   ├── promotions.php            # Promotion management
│   ├── login.php                 # Login/authentication endpoint
│   ├── register.php              # User registration endpoint
│   ├── face-auth.php             # Face ID registration/login
│   ├── faceid.php                # Face ID descriptor handling
│   ├── CallCenterStaff.php       # Call center staff operations
│   ├── ControlStaff.php          # Control staff operations
│   ├── chatbot-with-memory.php   # AI chatbot with memory
│   ├── n8n.php                   # N8N AI Agent connector
│   ├── supabase-storage.php      # Supabase Storage helper
│   ├── notification-helpers.php  # Shared notification utilities
│   ├── email-security.php        # Email security utilities
│   ├── session.php               # Session management
│   └── ... (additional APIs)     │
│
├── templates/                    # ★ PHP HTML templates (SSR)
│   ├── layout/
│   │   ├── header.html.php       # Navigation bar, header
│   │   └── footer.html.php       # Footer, chatbot widget
│   ├── index.html.php            # Homepage template
│   ├── cars.html.php             # Cars listing template
│   ├── booking.html.php          # Booking form template
│   ├── orders.html.php           # User orders template
│   ├── my-vehicles.html.php      # Vehicle management template
│   ├── profile.html.php          # User profile template
│   ├── admin.html.php            # Admin dashboard template
│   ├── login.html.php            # Login page template
│   ├── register.html.php         # Registration page template
│   ├── community.html.php        # Community forum template
│   ├── promotions.html.php       # Promotions display template
│   ├── membership.html.php       # Membership tiers template
│   ├── support.html.php          # Support page template
│   ├── reviews.html.php          # Reviews page template
│   ├── driver.html.php           # Driver dashboard template
│   ├── CallCenterStaff.html.php  # Call center staff interface
│   ├── ControlStaff.html.php     # Control staff interface
│   └── ... (additional templates)
│
├── resources/                    # Frontend resources
│   ├── js/                       # Client-side JavaScript
│   │   ├── booking.js            # Booking form logic
│   │   ├── vehicles.js           # Vehicle listing logic
│   │   ├── auth.js               # Authentication logic
│   │   ├── notifications.js      # Notification polling
│   │   ├── community.js          # Community interactions
│   │   └── ... (additional JS)   │
│   └── css/                      # Stylesheets
│       └── base.css              # Main stylesheet
│
├── public/                       # Public web root (if using separate web root)
│   └── (optional - currently not in use)
│
├── lib/                          # Additional libraries
│   └── (additional utilities)    │
│
├── logs/                         # Application logs
│
├── Invoice/                      # Invoice generation storage
│
├── vendor/                       # Composer dependencies
│
# Root level page controllers (URL routing)
├── index.php                     # Homepage controller
│
# Page routes now served from /api/*.php (page-view mode)
├── api/cars.php                  # Cars browsing page + API mode
├── api/admin.php                 # Admin dashboard page + API mode
├── api/profile.php               # User profile page/API
├── api/orders.php                # User orders page/API
│
# Base stylesheet at root
├── base.css                      # Root copy of stylesheet
│
# Documentation
├── README.md                     # This file
├── TIMEZONE_FIX.md               # Timezone configuration guide
├── VEHICLE_STATUS_UPDATE.md      # Vehicle status update documentation
├── check_statuses.sql            # SQL utility script
│
# Testing & Development
├── test_*.php                    # Various API test scripts
├── run_chat_sql.php              # Chat SQL runner
└── cookie.txt                    # Cookie storage for testing
```

---

## 4. Database Architecture

### Connection Details

| Property | Value |
|----------|-------|
| **Provider** | Supabase (AWS ap-southeast-1) |
| **Engine** | PostgreSQL 15.x |
| **Connection** | PDO via `pgsql:` DSN, port `6543`, `sslmode=require` |
| **Primary Keys** | UUID v4 (`uuid_generate_v4()`) — all tables use UUID |
| **Timestamps** | `TIMESTAMPTZ` with `NOW()` defaults, auto-`updated_at` triggers |
| **ORM Pattern** | Repository Pattern (Data Access Layer in `/sql/` directory) |

### Database Tables Overview

Core tables managed by the application:

| Table | Purpose |
|-------|---------|
| `users` | User accounts, roles (renter/owner/admin/driver/staff), profile data |
| `vehicles` | Vehicle listings with specifications, pricing, status, owner reference |
| `vehicle_images` | Vehicle photos and images with Supabase Storage paths or BYTEA |
| `bookings` | Rental bookings with status, dates, pricing, promo code tracking |
| `payments` | Payment records for bookings with status and method tracking |
| `reviews` | Vehicle ratings (1-5) and text reviews from renters |
| `community_posts` | User-created blog/forum posts |
| `community_comments` | Comments on community posts |
| `community_likes` | Likes/favorites on community posts (UNIQUE per user+post) |
| `notifications` | User notifications (booking, payment, promo, system events) |
| `promotions` | Promo codes with discount logic (percentage or fixed amount) |
| `memberships` | User membership tiers (free/basic/premium/corporate) |
| `hero_slides` | Admin-managed homepage banner images |
| `favorites` | Saved/favorited vehicles (UNIQUE per user+vehicle) |
| `gps_tracking` | Real-time vehicle GPS coordinates and trip tracking |
| `auth_sessions` | Session tokens for stateless authentication |
| `n8n_chat_histories` | AI chatbot conversation memory (auto-created by n8n) |
| `trip_enquiries` | Support/contact form submissions |

### Key Enum Types

```sql
user_role:         'renter' | 'owner' | 'admin' | 'driver' | 'staff'
auth_provider:     'google' | 'phone' | 'faceid' | 'email'
booking_status:    'pending' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled'
payment_status:    'pending' | 'paid' | 'refunded' | 'failed'
payment_method:    'cash' | 'bank_transfer' | 'credit_card' | 'paypal'
vehicle_status:    'available' | 'rented' | 'maintenance' | 'inactive'
membership_tier:   'free' | 'basic' | 'premium' | 'corporate'
notification_type: 'booking' | 'payment' | 'promo' | 'system' | 'alert'
```

### Entity Relationships

```
users (1) ──────────── (N) vehicles                # Owner has many vehicles
users (1) ──────────── (N) bookings               # Renter has many bookings
vehicles (1) ────────── (N) bookings              # Vehicle has many bookings
vehicles (1) ────────── (N) vehicle_images        # Vehicle has many images
vehicles (1) ────────── (N) reviews               # Vehicle has many reviews
users (1) ──────────── (N) reviews                # User writes many reviews
bookings (1) ────────── (N) payments              # Booking has many payments
users (1) ──────────── (N) notifications          # User has many notifications
users (1) ──────────── (N) community_posts        # User writes many posts
community_posts (1) ────(N) community_comments    # Post has many comments
community_posts (1) ────(N) community_likes       # Post has many likes
users (1) ──────────── (N) favorites              # User has many favorites
users (1) ──────────── (N) memberships            # User has membership history
vehicles (1) ────────── (N) gps_tracking          # Vehicle has GPS history
```

### Access Control

**Row Level Security (RLS)** is configured on sensitive tables:
- **Vehicles**: Public `SELECT` where `status = 'available'`; owner-only modifications
- **Bookings**: Users can only see their own bookings
- **Users**: Limited public profile fields only
- **All other tables**: Require authenticated user role checks

> **Note for Mobile Development**: The web app uses server-side PDO connections (not Supabase client SDK). Mobile apps can either call these same PHP APIs or implement direct Supabase client library access with appropriate RLS policies.

---

## 5. API Reference

All APIs return JSON. Base URL: `http://localhost:8000/api/`

**Action Routing**: Actions can be specified as:
- **GET parameter**: `GET /api/vehicles.php?action=list`
- **POST body**: `POST /api/vehicles.php` with `{ "action": "list", ... }`

### Response Format (Standard Across All Endpoints)

**Success Response:**
```json
{ "success": true, "message": "Action completed", "data": {...} }
```

**Error Response:**
```json
{ "success": false, "message": "Error description" }
```

**Authentication Required:**
```json
{ "success": false, "message": "Authentication required.", "require_login": true }
```

### 5.1 Auth API (`/api/auth.php`)

Handles user authentication via multiple methods: email/password, Google OAuth, SMS OTP, Email OTP, and Face ID.

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `register` | POST | No | Register with email + password |
| `login` | POST | No | Login with email/username + password |
| `check-session` | POST | No | Check if current user is logged in |
| `get-profile` | POST | Yes | Get current user's profile |
| `update-profile` | POST | Yes | Update profile fields |
| `logout` | POST | Yes | Destroy session |
| `google-login` | POST | No | Login/register via Google OAuth |
| `phone-send-otp` | POST | No | Send OTP to phone via SMS |
| `phone-verify-otp` | POST | No | Verify phone OTP code |
| `email-send-otp` | POST | No | Send OTP to email (Gmail SMTP) |
| `email-verify-otp` | POST | No | Verify email OTP code |
| `enable-faceid` | POST | Yes | Store face descriptor for Face ID |
| `disable-faceid` | POST | Yes | Remove face descriptor |
| `faceid-login` | POST | No | Login via face descriptor match |
| `check-duplicate` | POST | No | Check if email/phone/username exists |
| `upload-avatar` | POST (multipart) | Yes | Upload user avatar |
| `get-avatar` | GET | No | Get user avatar: `?action=get-avatar&id={userId}` |
| `complete-profile` | POST | Yes | Complete profile after first login |
| `email-change-send-otp` | POST | Yes | Send OTP for email change |
| `email-change-verify` | POST | Yes | Verify and change email |

### 5.2 Vehicles API (`/api/vehicles.php`)

Vehicle browsing, search, filtering, and owner management.

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `list` | POST | No | List/search vehicles with filters |
| `filter-options` | GET | No | Get available brands/categories for filters |
| `search-suggestions` | GET | No | Autocomplete: `?action=search-suggestions&q=bmw` |
| `get` | POST | No | Get single vehicle details by ID |
| `my-vehicles` | POST | Yes (owner) | List current user's vehicles |
| `add` | POST | Yes (owner) | Add new vehicle |
| `update` | POST | Yes (owner) | Update vehicle details |
| `delete` | POST | Yes (owner) | Delete vehicle |
| `upload-image` | POST (multipart) | Yes (owner) | Upload vehicle image to Supabase Storage |
| `get-image` | GET | No | Get vehicle image: `?action=get-image&id={imageId}` → Redirects to Supabase URL |
| `delete-image` | POST | Yes (owner) | Delete vehicle image |
| `public-list` | GET | No | Get public vehicle listing (available vehicles only) |

**Example: List Vehicles**
```json
POST /api/vehicles.php
{
  "action": "list",
  "search": "BMW",           // Full-text search
  "brand": "",               // Exact brand filter
  "category": "suv",         // sedan|suv|luxury|sports|van
  "transmission": "automatic",
  "fuel": "petrol",
  "max_price": 200,
  "location": "HCMC",
  "limit": 50,
  "offset": 0
}
```

### 5.3 Bookings API (`/api/bookings.php`)

Booking creation, promotion validation, and order management.

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `create` | POST | Yes | Create new booking |
| `validate-promo` | POST | No | Validate promotion code |
| `active-promos` | GET | No | List all active promotions |
| `my-orders` | POST | Yes | Get user's bookings (as renter or owner) |
| `update-status` | POST | Yes | Update booking status |
| `submit-review` | POST | Yes | Submit review for booking |
| `get-reviews` | GET | No | Get reviews for a vehicle/booking |
| `confirm-payment` | POST | Yes | Confirm payment for booking |

### 5.4 Notifications API (`/api/notifications.php`)

Real-time notification management and polling.

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `list` | GET | Yes | Get notifications: `?action=list&limit=20&offset=0` |
| `unread-count` | GET | Yes | Get unread notification count |
| `mark-read` | POST | Yes | Mark notification as read |
| `mark-all-read` | POST | Yes | Mark all as read |
| `delete` | POST | Yes | Delete notification |
| `clear-all` | POST | Yes | Delete all notifications |

### 5.5 Admin API (`/api/admin.php`)

Requires `role = 'admin'`. Full platform management.

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `hero-slides-list` | POST | Admin | List all hero slides |
| `hero-slide-upload` | POST (multipart) | Admin | Upload hero slide image |
| `hero-slide-update` | POST | Admin | Update hero slide |
| `hero-slide-delete` | POST | Admin | Delete hero slide |
| `promotions-list` | POST | Admin | List all promotions |
| `promotion-add` | POST | Admin | Create promo code |
| `promotion-update` | POST | Admin | Update promotion |
| `promotion-delete` | POST | Admin | Delete promotion |
| `admin-list-users` | POST | Admin | List all users (paginated) |
| `admin-update-user` | POST | Admin | Update user role/status |
| `admin-delete-user` | POST | Admin | Delete user account |
| `admin-list-vehicles` | POST | Admin | List all vehicles |
| `admin-delete-vehicle` | POST | Admin | Delete any vehicle |
| `admin-list-bookings` | POST | Admin | List all bookings |
| `admin-delete-booking` | POST | Admin | Delete booking |

### 5.6 Community API (`/api/community.php`)

Community posts, comments, and user engagement.

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `list-posts` | GET/POST | No | List community posts (paginated) |
| `create-post` | POST (multipart) | Yes | Create post with optional image |
| `delete-post` | POST | Yes (owner/admin) | Delete own post |
| `toggle-like` | POST | Yes | Like/unlike a post |
| `list-comments` | GET/POST | No | Get post comments |
| `add-comment` | POST | Yes | Add comment to post |
| `delete-comment` | POST | Yes (owner/admin) | Delete own comment |
| `get-user-public` | GET | No | Get public user profile |
| `get-post-image` | GET | No | Get post image: `?action=get-post-image&id={postId}` |

### 5.7 Additional APIs

| Endpoint | Description |
|----------|-------------|
| `/api/orders.php` | Order history and management |
| `/api/profile.php` | User profile operations |
| `/api/my-vehicles.php` | Owner's vehicle management |
| `/api/driver.php` | Driver-specific operations (active trips, earnings, etc.) |
| `/api/support.php` | Support/contact form submissions |
| `/api/membership.php` | Membership tier management |
| `/api/promotions.php` | Promotion endpoints |
| `/api/login.php` | Alternative login endpoint |
| `/api/register.php` | Alternative registration endpoint |
| `/api/face-auth.php` | Face ID registration/login |
| `/api/CallCenterStaff.php` | Call center staff operations |
| `/api/ControlStaff.php` | Control staff operations |
| `/api/chatbot-with-memory.php` | AI chatbot with conversation memory |
| `/api/session.php` | Session management utilities |
| `/api/supabase-storage.php` | Supabase Storage operations |

### Error Handling

All API endpoints follow consistent error handling:

```json
// Client error (invalid input)
{ "success": false, "message": "Description of validation error", "errors": {...} }

// Not authenticated
{ "success": false, "message": "Authentication required.", "require_login": true }

// Not authorized (insufficient permissions)
{ "success": false, "message": "Access denied", "error_code": "unauthorized" }

// Server error
{ "success": false, "message": "Database error" }
```

---

## 6. Authentication Flow

### Session-Based Auth (Web)

The web app uses PHP server-side sessions with native session storage. After successful authentication:

```php
// Session variables set after login
$_SESSION['logged_in']  = true;
$_SESSION['user_id']    = $user['id'];           // UUID
$_SESSION['username']   = $user['username'];
$_SESSION['full_name']  = $user['full_name'];
$_SESSION['email']      = $user['email'];
$_SESSION['role']       = $user['role'];         // 'renter' | 'owner' | 'admin' | 'driver' | 'staff'
// Optional fields
$_SESSION['phone']      = $user['phone'] ?? null;
$_SESSION['avatar_url'] = $user['avatar_url'] ?? null;
$_SESSION['membership'] = $user['membership'] ?? 'free';
```

### Authentication Methods

The system supports multiple authentication pathways:

```
┌──────────────────────────────────────────────────────────────┐
│              AUTHENTICATION METHODS                           │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  1. EMAIL + PASSWORD                                          │
│     User registers → email-password stored (hashed)           │
│     User logs in with credentials → session created           │
│                                                               │
│  2. EMAIL OTP (One-Time Password)                             │
│     email-send-otp → 6-digit code to email (Gmail SMTP)      │
│     email-verify-otp with code → session → auto-register     │
│                                                               │
│  3. PHONE OTP                                                 │
│     phone-send-otp → 6-digit code via SMS provider           │
│     phone-verify-otp with code → session → auto-register    │
│                                                               │
│  4. GOOGLE OAUTH 2.0                                          │
│     google-login with Google token → session                 │
│     Auto-creates user if first login                         │
│                                                               │
│  5. FACE ID (Optional - Register after Email/Phone login)     │
│     enable-faceid → capture & store face descriptor          │
│     faceid-login → real-time face matching → session         │
│                                                               │
│  6. MULTI-FACTOR SECONDARY VERIFICATION (future)            │
│     Additional security layer for sensitive operations       │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

### Login Flow Diagram

```
Entry Point: /api/auth.php
                │
                ▼
      ┌─────────────────────┐
      │  Check action       │
      └──────────┬──────────┘
                 │
        ┌────────┼────────┬────────┬──────────┬──────────┐
        │        │        │        │          │          │
        ▼        ▼        ▼        ▼          ▼          ▼
    register  login   google   phone-otp email-otp faceid
      │        │      login     verify    verify    login
      │        │        │        │         │         │
      ▼        ▼        ▼        ▼         ▼         ▼
    Validate Validate Verify  Verify    Verify   Match
    Email    Creds   Token   SMS Code  Email    Face
    exists   match                      Code    Descriptor
      │        │        │        │         │         │
      ▼        ▼        ▼        ▼         ▼         ▼
   Create  Find    Create    Create   Create   Find
   User    User    User      User     User     User
   Record          if New    if New   if New
      │        │        │        │         │         │
      └────────┼────────┼────────┼─────────┼─────────┘
               │        │        │        │
               ▼        ▼        ▼        ▼
           Set $_SESSION variables
           session_id() cookie sent to browser
                 │
                 ▼
         Return { success: true, user: {...} }
```

---

## 7. User Roles & Permissions

The system supports five distinct user roles with specific capabilities:

| Role | Primary Use Case | Key Capabilities |
|------|------------------|------------------|
| **renter** | Regular user browsing & booking vehicles | Browse vehicles, create bookings, manage own bookings, write reviews, community posts, favorites, request drivers |
| **owner** | Vehicle owner/fleet manager | All renter capabilities + Add/edit/delete own vehicles, upload images, view bookings for own vehicles, set pricing & availability, manage cancellations |
| **driver** | Professional driver | Accept ride requests, track GPS, manage active trips, earn commissions, view earnings dashboard, accept/decline bookings |
| **admin** | Platform administrator | Full platform access: manage users (role/status/delete), manage all vehicles/bookings/payments, create promotions, customize hero slides, view analytics |
| **staff** | Call center / support staff | Assist renters with calls, manage booking cancellations, issue refunds, view customer history, escalate issues to admin |

### Role Assignment

- **renter**: Default role for email/phone/OAuth registration
- **owner**: User must opt-in and pass verification (identity + payment method)
- **driver**: Vetted through admin dashboard (background check, license verification)
- **admin**: Assigned only by existing admin via database or `/api/admin.php`
- **staff**: Assigned by admin (suitable for team members with limited access)

### Permission Matrix

| Feature | Renter | Owner | Driver | Admin | Staff |
|---------|--------|-------|--------|-------|-------|
| Browse vehicles | ✅ | ✅ | ✅ | ✅ | ❌ |
| Create booking | ✅ | ✅ | ❌ | ✅ | ✅ |
| Add vehicle | ❌ | ✅ | ❌ | ✅ | ❌ |
| Accept ride request | ❌ | ❌ | ✅ | ❌ | ❌ |
| Manage own bookings | ✅ | ✅ | ✅ | ✅ | ✅ |
| Manage all bookings | ❌ | ❌ | ❌ | ✅ | ✅ |
| Create/manage promos | ❌ | ❌ | ❌ | ✅ | ❌ |
| View platform analytics | ❌ | ⚠️ (own vehicle stats) | ⚠️ (earnings) | ✅ | ❌ |
| Manage users | ❌ | ❌ | ❌ | ✅ | ❌ |
| Community access | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## 8. Core Business Flows

### 8.1 Vehicle Owner Onboarding

```
Step 1: User Registration
  • Register as "renter" with email/phone or OAuth
  
Step 2: Upgrade to Owner
  • New API route: /api/vehicles.php (action: "become-owner")
  • Provide identity verification details
  • Add payment method for receiving rental income
  • Account status: "pending_verification" → "verified"
  
Step 3: Add First Vehicle
  • POST /api/vehicles.php {action: "add", ...specs...}
  • Upload vehicle images (Supabase Storage)
  • Set daily/weekly/monthly prices
  • Set vehicle status: "available"
  
Step 4: Vehicle Appears in Listings
  • Vehicle shows in public listings filters
  • Appears in search results for matching criteria
```

### 8.2 Renter Booking Flow

```
Step 1: Browse & Search
  • GET /api/vehicles.php {action: "list", search: "BMW", ...filters...}
  • Display results with images from Supabase Storage
  • Show vehicle details: specs, availability, owner rating
  
Step 2: Select & View Details
  • POST /api/vehicles.php {action: "get", id: "vehicle_id"}
  • Display: full specs, reviews, image gallery, owner profile
  • Check availability calendar
  
Step 3: Create Booking
  POST /api/bookings.php {
    action: "create",
    vehicle_id: "uuid",
    booking_type: "self-drive"|"with-driver"|"airport",
    pickup_date: "2026-04-01",
    return_date: "2026-04-05",
    promo_code: optional,
    payment_method: "credit_card"|"cash"|"bank_transfer"
  }
  
  Backend processing:
  • Validate vehicle availability for date range
  • Check promo code if provided (POST /api/bookings.php {action: "validate-promo"})
  • Calculate price: rate × days - discount
  • Create booking record (status: 'pending')
  • Create payment record
  • Send notifications to renter & owner
  • If with-driver: Find available driver matching preferences
  
Step 4: Payment & Confirmation
  • Owner receives notification, reviews booking
  • Owner confirms (POST /api/bookings.php {action: "update-status", status: "confirmed"})
  • Renter receives confirmation notification
  • Payment processed (status: 'paid' or 'pending' based on payment method)
  
Step 5: Trip Execution
  • Pickup: Renter/Driver picks up vehicle (status: "in_progress")
  • Trip: Driver provides live GPS tracking (for with-driver bookings)
  • Return: Vehicle returned (status: "completed")
  
Step 6: Post-Trip
  • Renter can write review (/api/bookings.php {action: "submit-review"})
  • Review rating affects vehicle's avg_rating
  • Driver can accept new bookings
  • Owner receives payment minus commission
```

### 8.3 Driver Assignment Flow

```
If booking_type = "with-driver":
  POST /api/vehicles.php {action: "findVehicleForTier", ride_tier: "eco"|"standard"|"premium"}
  
  System finds:
  1. Available driver by tier preference
  2. Driver location vs pickup location
  3. Driver acceptance rate & rating
  
  Driver receives:
  • Push notification (or polling for web)
  • Booking details: pickup location, destination, fare
  
  Driver can:
  • Accept booking → navigation + passenger rating
  • Decline booking → system finds next driver
  
  Active Trip:
  • GPS tracking live-streamed to renter
  • Driver navigation to pickup → passenger pickup → destination
  • Real-time notifications for both parties
```

### 8.4 Booking Types

| Type | Description | Price Calculation | Use Case |
|------|-------------|-------------------|----------|
| **self-drive** | Renter operates vehicle | `price_per_day × total_days - discount` | Personal travel, long-term rental |
| **with-driver** | Professional driver provided | `price_per_day × total_days + driver_fee - discount` | Airport transfer, business meeting, safety concern |
| **airport** | Airport pickup/dropoff | `flat_transfer_fee` (calculated by distance) | Quick airport transfers, one-way trips |

### 8.5 Promotion System

Admin creates promotions via `/api/admin.php`:

```json
{
  "code": "WEEKEND20",
  "description": "20% off for weekend bookings",
  "discount_type": "percentage",      // or "fixed"
  "discount_value": 20,               // 20% off, or $20 off
  "min_booking_days": 2,              // Minimum days required
  "min_booking_amount": 100,          // Minimum booking value
  "max_uses": 100,                    // Total usage limit
  "max_uses_per_user": 1,             // Per user limit
  "applies_to": "all",                // or specific vehicle IDs
  "is_active": true,
  "created_at": "2026-02-28T10:00:00Z",
  "expires_at": "2026-03-31T23:59:59Z"
}
```

**Promotion Validation:**
- POST `/api/bookings.php` {action: "validate-promo", code: "WEEKEND20"}
- Returns: `{success, discount_amount, total_after_discount}`

---

## 9. Real-time Notifications

### Notification System Architecture

```
Application Event (Booking created, Payment processed, etc.)
         │
         ▼
   createNotification()  [in api/notification-helpers.php]
         │
         ├─→ INSERT into PostgreSQL notifications table
         │
         ├─→ Increment unread_count for user
         │
         └─→ (Optional) Send email / SMS
         
         ▼
Frontend Polling: GET /api/notifications.php?action=unread-count (every 15s)
         │
         ▼
JavaScript updates UI: Badge with unread count, notification list
```

### Notification Types

| Type | Trigger | Recipient |
|------|---------|-----------|
| `booking` | New booking created, status changed (pending/confirmed/in_progress/completed) | Renter & Owner |
| `payment` | Payment confirmation, refund processed | Renter |
| `promo` | New promotion available, ending soon | All users (broadcast) |
| `system` | Password changed, profile updated, account alerts | User |
| `alert` | Important alerts, booking at risk, driver nearby | User |
| `driver_request` | Driver assignment request, driver accepted/declined | Renter & Driver |

### Notification Management

**Endpoints:**
- `GET /api/notifications.php?action=list&limit=20&offset=0` — List notifications
- `GET /api/notifications.php?action=unread-count` — Get unread notification count
- `POST /api/notifications.php` {action: "mark-read", id: "notification_id"} — Mark as read
- `POST /api/notifications.php` {action: "mark-all-read"} — Mark all as read
- `POST /api/notifications.php` {action: "delete", id: "notification_id"} — Delete notification
- `POST /api/notifications.php` {action: "clear-all"} — Clear all notifications

### Future: Push Notifications

Currently the system uses polling. For production, extend with:
1. Add `push_token` column to `users` table (for Firebase Cloud Messaging)
2. After `createNotification()`, also send FCM push
3. Or subscribe to PostgreSQL LISTEN/NOTIFY for real-time updates

---

## 10. Image Storage & Delivery

### Architecture

Images are stored in **Supabase Storage** (recommended) or database BYTEA (legacy):

```
Supabase Storage Bucket: "DriveNow"
                     │
                     ├── vehicles/{vehicle_id}/
                     │   ├── abc123.jpg
                     │   ├── def456.png
                     │   └── ...
                     │
                     ├── avatars/
                     │   ├── user_uuid.jpg
                     │   └── ...
                     │
                     ├── hero-slides/
                     │   ├── slide_001.jpg
                     │   └── ...
                     │
                     └── community/
                         ├── post_uuid.jpg
                         └── ...
```

**CDN URL Format:**
```
https://{project_id}.supabase.co/storage/v1/object/public/DriveNow/vehicles/{vehicle_id}/image.jpg
```

### Image Upload & Serving

**Upload vehicle image:**
```
POST /api/vehicles.php
Content-Type: multipart/form-data
  action: "upload-image"
  vehicle_id: "uuid"
  image: <binary file>
  
Response:
{
  "success": true,
  "image_id": "uuid",
  "storage_path": "vehicles/vehicle-uuid/image-uuid.jpg",
  "public_url": "https://xxx.supabase.co/storage/v1/object/public/DriveNow/vehicles/..."
}
```

**Serve image:**
```
GET /api/vehicles.php?action=get-image&id={image_id}
  → Redirects (302) to Supabase Storage public URL
  → Browser downloads directly from Supabase CDN (no PHP overhead)
```

### Storage Limits

| File Type | Max Size | Format |
|-----------|----------|--------|
| Vehicle images | 5 MB | JPEG, PNG, WebP, GIF |
| User avatars | 3 MB | JPEG, PNG, WebP |
| Hero slides | 10 MB | JPEG, PNG, WebP |
| Community posts | 5 MB | JPEG, PNG, WebP, GIF |

> **Note**: Images are served directly from Supabase CDN. Mobile apps can use image URLs directly as `<img>` or `Image.network()` without session cookies needed.
    └── abc123def456.jpg
```

```
┌──────────────┐        ┌─────────────────────┐       ┌──────────────────┐
│  Client       │  POST  │ vehicles.php         │       │ Supabase Storage │
│  (multipart)  │──────→ │ action=upload-image  │──────→│ Bucket: DriveNow │
│               │        │                      │       │ PUT /object/...  │
│               │        │ 1. Validate file     │       │                  │
│               │        │ 2. Upload to Storage  │       │ Returns public   │
│               │        │ 3. Save path to DB   │       │ URL              │
│               │        │ 4. Return public URL │       │                  │
└──────────────┘        └─────────────────────┘       └──────────────────┘

┌──────────────┐        ┌──────────────────────────────────────────────────┐
│  Client       │  GET   │ Supabase Storage CDN                            │
│  <img src=.>  │──────→ │ https://{project}.supabase.co/storage/v1/       │
│               │        │   object/public/DriveNow/vehicles/{id}/img.jpg  │
│               │        │                                                  │
│               │        │ Direct CDN access — no PHP proxy needed         │
└──────────────┘        └──────────────────────────────────────────────────┘
```

### Image Endpoints

| Endpoint | Storage Folder | Notes |
|----------|---------------|-------|
| Upload: `POST /api/vehicles.php` `{action: "upload-image"}` | `vehicles/{vehicle_id}/` | Returns Supabase public URL |
| Upload: `POST /api/auth.php` (multipart, avatar) | `avatars/` | Upsert (overwrites previous) |
| Upload: `POST /api/admin.php` (multipart, hero slide) | `hero-slides/` | Returns public URL |
| Upload: `POST /api/community.php` (multipart, post image) | `community/` | Returns public URL |

### Image URLs

All API responses now return **direct Supabase Storage public URLs**:

```
https://zydpdyoinxnrlsqkeobd.supabase.co/storage/v1/object/public/DriveNow/vehicles/{vehicle_id}/image.jpg
```

Legacy `/api/vehicles.php?action=get-image&id=X` endpoints still work — they redirect (302) to the Supabase public URL.

### Image Upload Limits

- Vehicle images: **5 MB** max per image
- Avatars: **3 MB** max
- Hero slides: **10 MB** max
- Community posts: **5 MB** max
- Accepted formats: JPEG, PNG, WebP, GIF

> **Mobile Note**: Images are now served via Supabase Storage CDN, so mobile apps can use the public URLs directly as `<img src>` or `Image.network()` — no cookie/session needed for image loading.

---

## 11. AI Chatbot Integration

```
┌─────────────┐     ┌──────────────────────┐     ┌──────────────┐
│  Client      │     │ chatbot-with-        │     │  n8n Server   │
│  (JS/Mobile) │────→│ memory.php (proxy)   │────→│  (localhost:  │
│              │     │                      │     │   5678)       │
│              │     │ Adds sessionId:      │     │              │
│              │     │  user_{userId} or    │     │ AI Agent:    │
│              │     │  anon_{sessionToken} │     │ • LLM call   │
│              │     │                      │     │ • Chat memory│
│              │◄────│ Returns {response}   │◄────│ • Context    │
└─────────────┘     └──────────────────────┘     └──────────────┘
                                                        │
                                                        ▼
                                                ┌──────────────┐
                                                │ PostgreSQL    │
                                                │ Table:        │
                                                │ n8n_chat_     │
                                                │ histories     │
                                                └──────────────┘
```

### n8n Workflow Setup

1. Create an n8n webhook trigger node
2. Add PostgreSQL Chat Memory node (table: `n8n_chat_histories`)
3. Connect to AI Agent node (OpenAI/Anthropic)
4. Return `{ output: "response text" }`
5. Set the webhook URL in `.env` as `N8N_WEBHOOK_URL`

---

## 12. Environment Configuration

### `.env` File Template

```bash
# ============================================================
# DATABASE CONFIGURATION (Supabase PostgreSQL)
# ============================================================
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=6543
DB_NAME=postgres
DB_USER=postgres.your_project_ref
DB_PASSWORD=your_password
DB_SSL_MODE=require

# ============================================================
# SUPABASE STORAGE
# ============================================================
SUPABASE_URL=https://your_project_ref.supabase.co
SUPABASE_SERVICE_KEY=your_supabase_service_role_key

# ============================================================
# N8N AI CHATBOT & AUTOMATION
# ============================================================
N8N_BASE_URL=http://localhost:5678
N8N_WEBHOOK_URL=http://localhost:5678/webhook/your-webhook-id

# ============================================================
# EMAIL DELIVERY (Gmail SMTP for OTP)
# ============================================================
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=your@gmail.com
SMTP_FROM_NAME=Private Hire

# ============================================================
# AUTHENTICATION PROVIDERS (Optional)
# ============================================================
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret

# ============================================================
# APPLICATION CONFIGURATION
# ============================================================
APP_ENV=development       # 'development' or 'production'
APP_DEBUG=true           # Enable error details in development
APP_URL=http://localhost:8000

# ============================================================
# OPTIONAL: EXTERNAL SERVICES
# ============================================================
# GOOGLE_MAPS_API_KEY=your_maps_key
# SENTRY_DSN=your_sentry_dsn (for error tracking)
```

### Environment Variables Reference

| Variable | Required | Example | Usage |
|----------|----------|---------|-------|
| `DB_HOST` | Yes | `aws-0-ap-southeast-1.pooler.supabase.com` | PostgreSQL host |
| `DB_PORT` | Yes | `6543` | PostgreSQL port (Supabase uses 6543) |
| `DB_NAME` | Yes | `postgres` | Database name |
| `DB_USER` | Yes | `postgres.project_ref` | Database user (format: `postgres.project_ref`) |
| `DB_PASSWORD` | Yes | (your password) | Database password |
| `DB_SSL_MODE` | Yes | `require` | PostgreSQL SSL mode (always use `require` for Supabase) |
| `SUPABASE_URL` | Yes | `https://xxx.supabase.co` | Supabase project URL |
| `SUPABASE_SERVICE_KEY` | Yes | (service role key) | Service role API key (for file uploads) |
| `N8N_BASE_URL` | No | `http://localhost:5678` | N8N server URL (for local AI agent) |
| `N8N_WEBHOOK_URL` | No | `http://localhost:5678/webhook/xxx` | N8N webhook for chatbot |
| `SMTP_HOST` | Yes | `smtp.gmail.com` | Email SMTP server |
| `SMTP_PORT` | Yes | `587` | SMTP port (Gmail uses 587) |
| `SMTP_USERNAME` | Yes | `your@gmail.com` | Email address for sending OTPs |
| `SMTP_PASSWORD` | Yes | (app password) | Gmail app-specific password |
| `SMTP_FROM_EMAIL` | Yes | `hello@privatehire.com` | Sender email for notifications |
| `SMTP_FROM_NAME` | Yes | `Private Hire` | Sender name in emails |
| `APP_ENV` | No | `development` | Application environment mode |
| `APP_DEBUG` | No | `true` | Enable debug output (disable in production) |

---

## 13. Running Locally

### Prerequisites

- **PHP 8.2+** with extensions: `pdo_pgsql`, `mbstring`, `openssl`, `json`, `curl`
- **Composer** (for PHP dependencies like PHPMailer)
- **PostgreSQL database** (Supabase account recommended, or local PostgreSQL 13+)
- **Git** (for cloning repository)

### Setup Steps

#### 1. Clone the Repository
```bash
git clone https://github.com/DK0310/Scrum_App.git
cd Scrum_App
```

#### 2. Install PHP Dependencies
```bash
composer install
```

#### 3. Configure Environment Variables
```bash
# Copy the example environment file
cp .env.example .env

# Edit .env with your Supabase credentials:
# DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_SSL_MODE
# SUPABASE_URL, SUPABASE_SERVICE_KEY (for storage)
# SMTP settings (for Gmail OTP delivery)
# N8N_BASE_URL (for AI chatbot)
nano .env
```

**Minimal .env Configuration:**
```bash
# Database (Supabase PostgreSQL)
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=6543
DB_NAME=postgres
DB_USER=postgres.your_project
DB_PASSWORD=your_password
DB_SSL_MODE=require

# Supabase Storage (for images)
SUPABASE_URL=https://your_project.supabase.co
SUPABASE_SERVICE_KEY=your_service_role_key

# Email (Gmail SMTP for OTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=your@gmail.com
SMTP_FROM_NAME=Private Hire

# N8N (AI Chatbot - optional)
N8N_BASE_URL=http://localhost:5678
N8N_WEBHOOK_URL=http://localhost:5678/webhook/your-webhook-id

# App configuration
APP_ENV=development
APP_DEBUG=true
```

#### 4. Initialize Database
```bash
# Run the database schema against your Supabase PostgreSQL
# Option A: Use psql CLI
psql -h your_host -p 6543 -U postgres.your_project -d postgres -f Database/schema.sql

# Option B: Import via Supabase Console
# 1. Open Supabase Dashboard
# 2. Go to SQL Editor
# 3. Copy contents of Database/schema.sql and execute
```

#### 5. Start Development Server
```bash
# Using PHP's built-in server (port 8000)
php -S localhost:8000

# OR using your local Apache/Nginx (depending on your setup)
```

#### 6. Access the Application
```
Open browser: http://localhost:8000
```

### API Testing (cURL Examples)

**Check Session Status:**
```bash
curl -X POST http://localhost:8000/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"action": "check-session"}'
```

**Search Vehicles:**
```bash
curl "http://localhost:8000/api/vehicles.php?action=search-suggestions&q=bmw"
```

**List Vehicles:**
```bash
curl -X POST http://localhost:8000/api/vehicles.php \
  -H "Content-Type: application/json" \
  -d '{"action": "list", "limit": 10, "max_price": 200}'
```

**Get Active Promotions:**
```bash
curl "http://localhost:8000/api/bookings.php?action=active-promos"
```

### Troubleshooting

**Connection Failed Error:**
- Verify `.env` credentials match your Supabase project
- Ensure firewall allows connection to Supabase DB port
- Check SSL certificate access: `ssl_mode=require`

**Session Not Working:**
- Ensure `session_start()` is called at beginning of PHP files
- Check browser cookies are enabled
- Verify `$_SESSION` variables are being set during login

**Emails Not Sending:**
- Verify Gmail app-specific password (not regular password)
- Ensure "Less secure apps" is enabled in Gmail settings
- Check SMTP credentials in `.env`
- Review PHP error logs for mail errors

---

## 14. Mobile App Integration Guide

### Recommended Architecture for Mobile

```
┌─────────────────────────────────┐
│        Mobile App               │
│   (Flutter / React Native)      │
│                                 │
│  ┌────────────────────────────┐ │
│  │    API Service Layer       │ │
│  │    (HTTP Client + Cookies) │ │
│  └────────────┬───────────────┘ │
│               │                 │
│  ┌────────────▼───────────────┐ │
│  │    Same PHP REST APIs      │ │
│  │    /api/auth.php           │ │
│  │    /api/vehicles.php       │ │
│  │    /api/bookings.php       │ │
│  │    /api/notifications.php  │ │
│  │    /api/community.php      │ │
│  └────────────────────────────┘ │
└─────────────────────────────────┘
```

### Key Integration Points

#### 1. Authentication
```dart
// Use cookie jar for session management
// After login, all subsequent requests include session cookie
POST /api/auth.php { "action": "login", "email": "...", "password": "..." }
// Store the session cookie and send with all requests
```

#### 2. Vehicle Browsing
```dart
// List with filters
POST /api/vehicles.php { "action": "list", "search": "BMW", "category": "suv" }

// Autocomplete
GET /api/vehicles.php?action=search-suggestions&q=mer

// Display images — direct Supabase Storage CDN URLs
Image.network(vehicle['images'][0])  // Already a full public URL
```

#### 3. Booking
```dart
// Validate promo
POST /api/bookings.php { "action": "validate-promo", "code": "WEEKEND20" }

// Create booking
POST /api/bookings.php { "action": "create", "vehicle_id": "...", ... }

// Get my orders
POST /api/bookings.php { "action": "my-orders" }
```

#### 4. Notifications
```dart
// Poll every 15s (or implement FCM push)
GET /api/notifications.php?action=unread-count

// List notifications
GET /api/notifications.php?action=list&limit=20
```

#### 5. Face ID
For mobile Face ID, you can:
- Use device-native biometrics (iOS Face ID / Android BiometricPrompt) instead of face-api.js
- The face descriptor approach works cross-platform but face-api.js is browser-only
- Consider storing a device token + biometric flag on the server instead

### API Response Conventions

All endpoints follow this pattern:

```json
// Success
{ "success": true, "message": "...", "data": { ... } }

// Error
{ "success": false, "message": "Error description" }

// Auth required
{ "success": false, "message": "Authentication required.", "require_login": true }

// Force logout (role changed by admin)
{ "success": false, "message": "...", "force_logout": true }
```

### CORS Headers

All API files include:
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, GET, OPTIONS
Access-Control-Allow-Headers: Content-Type
```

This means mobile apps can call the APIs directly without CORS issues.

---

## 15. ERD

```
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│      users       │       │    vehicles       │       │  vehicle_images  │
├──────────────────┤       ├──────────────────┤       ├──────────────────┤
│ id (UUID) PK     │──┐    │ id (UUID) PK     │──┐    │ id (UUID) PK     │
│ email            │  │    │ owner_id (FK)  ───┼──┘    │ vehicle_id (FK)──┤
│ phone            │  │    │ brand, model      │       │ image_data BYTEA │
│ username         │  │    │ year, category    │       │ mime_type        │
│ password_hash    │  │    │ transmission      │       │ file_size        │
│ role (enum)      │  │    │ fuel_type, seats  │       │ sort_order       │
│ full_name        │  │    │ price_per_day     │       └──────────────────┘
│ face_descriptor  │  │    │ location_city     │
│ membership       │  │    │ status (enum)     │       ┌──────────────────┐
│ avatar_data      │  │    │ avg_rating        │       │    bookings      │
│ created_at       │  │    │ total_bookings    │       ├──────────────────┤
└──────────────────┘  │    └──────────────────┘       │ id (UUID) PK     │
                      │                                │ renter_id (FK)───┤
                      ├───────────────────────────────→│ vehicle_id (FK)  │
                      │                                │ owner_id (FK)    │
                      │    ┌──────────────────┐       │ booking_type     │
                      │    │    payments       │       │ pickup_date      │
                      │    ├──────────────────┤       │ return_date      │
                      │    │ id (UUID) PK     │       │ total_amount     │
                      │    │ booking_id (FK)──┼──────→│ status (enum)    │
                      ├───→│ user_id (FK)     │       │ promo_code       │
                      │    │ amount, method   │       └──────────────────┘
                      │    │ status (enum)    │
                      │    └──────────────────┘       ┌──────────────────┐
                      │                                │   reviews        │
                      │    ┌──────────────────┐       ├──────────────────┤
                      │    │  notifications   │       │ id (UUID) PK     │
                      │    ├──────────────────┤       │ user_id (FK)─────┤
                      ├───→│ user_id (FK)     │       │ vehicle_id (FK)  │
                      │    │ type (enum)      │       │ booking_id (FK)  │
                      │    │ title, message   │       │ rating (1-5)     │
                      │    │ is_read          │       │ title, content   │
                      │    └──────────────────┘       └──────────────────┘
                      │
                      │    ┌──────────────────┐       ┌──────────────────┐
                      │    │ community_posts  │       │ community_likes  │
                      │    ├──────────────────┤       ├──────────────────┤
                      ├───→│ user_id (FK)     │◄──────│ post_id (FK)     │
                      │    │ title, content   │       │ user_id (FK)     │
                      │    │ category         │       │ UNIQUE(post,user)│
                      │    │ likes_count      │       └──────────────────┘
                      │    └──────────────────┘
                      │            │                   ┌──────────────────┐
                      │            └──────────────────→│community_comments│
                      │                                ├──────────────────┤
                      │    ┌──────────────────┐       │ post_id (FK)     │
                      │    │   favorites      │       │ user_id (FK)     │
                      │    ├──────────────────┤       │ content          │
                      ├───→│ user_id (FK)     │       └──────────────────┘
                      │    │ vehicle_id (FK)  │
                      │    │ UNIQUE(user,veh) │       ┌──────────────────┐
                      │    └──────────────────┘       │   promotions     │
                      │                                ├──────────────────┤
                      │    ┌──────────────────┐       │ code UNIQUE      │
                      │    │  memberships     │       │ discount_type    │
                      │    ├──────────────────┤       │ discount_value   │
                      ├───→│ user_id (FK)     │       │ min_booking_days │
                      │    │ tier (enum)      │       │ max_uses         │
                      │    │ starts_at        │       │ expires_at       │
                      │    │ expires_at       │       └──────────────────┘
                      │    └──────────────────┘
                      │                                ┌──────────────────┐
                      │    ┌──────────────────┐       │  hero_slides     │
                      │    │  gps_tracking    │       ├──────────────────┤
                      │    ├──────────────────┤       │ image_data BYTEA │
                      │    │ vehicle_id (FK)  │       │ title, subtitle  │
                      │    │ booking_id (FK)  │       │ sort_order       │
                      │    │ lat, lng, speed  │       │ is_active        │
                      │    │ recorded_at      │       │ created_by (FK)  │
                      │    └──────────────────┘       └──────────────────┘
                      │
                      │    ┌──────────────────┐
                      │    │ trip_enquiries   │
                      │    ├──────────────────┤
                      └───→│ user_id (FK)     │
                           │ name, email      │
                           │ trip_details     │
                           │ status           │
                           └──────────────────┘
```

---

## License

This project is developed as part of a Scrum learning exercise. All rights reserved.

---

*Last updated: March 24, 2026*
*Platform Name: Private Hire — Premium Car Rental & Transportation*
