# 🚗 DriveNow — Car Rental Platform

> Full-stack car rental platform built with PHP 8 + PostgreSQL (Supabase). This document provides end-to-end architecture context for developers building companion mobile apps (iOS/Android/Flutter/React Native).

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

## 1. System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                             │
│  ┌──────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │  Web Browser  │  │  Mobile App (TBD) │  │  Admin Panel     │  │
│  │  (PHP SSR +   │  │  (iOS/Android/    │  │  (Web - admin    │  │
│  │   vanilla JS) │  │   Flutter/RN)     │  │   role only)     │  │
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
│  │admin.php │ │community │ │face-auth │ │ chatbot-with-     │  │
│  │          │ │  .php    │ │  .php    │ │   memory.php      │  │
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
│  │  • 17 tables              │  │  • Gmail SMTP (OTP email)  │  │
│  │  • UUID primary keys      │  │  • Mem0 (AI memory)        │  │
│  │  • BYTEA image storage    │  │  • Google OAuth             │  │
│  │  • RLS enabled            │  │  • face-api.js (Face ID)   │  │
│  │  • Enum types             │  │                            │  │
│  └───────────────────────────┘  └────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Tech Stack

| Layer | Technology | Details |
|-------|-----------|---------|
| **Frontend (Web)** | PHP 8.2 SSR + Vanilla JS | Server-rendered HTML templates with client-side JS for interactivity |
| **Styling** | Custom CSS | CSS variables, responsive design, no external UI framework |
| **Backend API** | PHP 8.2 | REST API with action-based routing (`?action=xxx` or `{action: "xxx"}`) |
| **Database** | PostgreSQL 15 (Supabase) | Hosted on Supabase, connected via PDO with SSL |
| **Image Storage** | Supabase Storage | Vehicle images, avatars, hero slides stored in Supabase Storage bucket "DriveNow" |
| **Authentication** | PHP Sessions + Multi-method | Email/password, Google OAuth, Phone OTP, Email OTP, Face ID |
| **AI Chatbot** | n8n AI Agent | n8n workflow with PostgreSQL chat memory, proxied through PHP |
| **Email** | PHPMailer + Gmail SMTP | OTP delivery, booking confirmations |
| **AI Memory** | Mem0 API | Long-term user preference storage for AI assistant |

---

## 3. Project Structure

```
Scrum/
├── .env                          # Environment variables (DB, SMTP, API keys)
├── .env.example                  # Template for .env
├── composer.json                 # PHP dependencies (PHPMailer)
│
├── config/
│   ├── env.php                   # EnvLoader class — reads .env file
│   └── chatbot-system-prompt.md  # System prompt for AI chatbot
│
├── Database/
│   ├── db.php                    # PDO connection to Supabase PostgreSQL
│   ├── schema.sql                # Full database schema (17 tables + RLS)
│   ├── migration_storage.sql     # Migration: BYTEA → Supabase Storage columns
│   ├── chat_memory.sql           # n8n chat history table reference
│   └── drop_all.sql              # Reset script
│
├── api/                          # ★ REST API endpoints (JSON)
│   ├── auth.php                  # Authentication (login, register, OAuth, OTP, Face ID)
│   ├── vehicles.php              # Vehicle CRUD + search + image serving
│   ├── bookings.php              # Booking creation, promo codes, orders
│   ├── notifications.php         # Notification CRUD + polling
│   ├── notification-helpers.php  # Shared createNotification() function
│   ├── admin.php                 # Admin: users, vehicles, bookings, hero slides, promos
│   ├── community.php             # Community posts, comments, likes
│   ├── face-auth.php             # Face ID registration/login (face-api.js descriptors)
│   ├── chatbot-with-memory.php   # Proxy to n8n AI agent webhook
│   ├── supabase-storage.php      # ★ Supabase Storage helper (upload/delete/public URL)
│   ├── mem0.php                  # Mem0 AI memory integration class
│   ├── n8n.php                   # N8NConnector class (webhook helper)
│   └── images/                   # (empty — images stored in DB as BYTEA)
│
├── public/                       # ★ Web root (PHP built-in server serves from here)
│   ├── index.php                 # Homepage controller
│   ├── cars.php                  # Cars listing page controller
│   ├── booking.php               # Booking page controller
│   ├── orders.php                # My orders page controller
│   ├── my-vehicles.php           # Vehicle management (owners) controller
│   ├── profile.php               # User profile controller
│   ├── login.php                 # Login page controller
│   ├── register.php              # Registration page controller
│   ├── admin.php                 # Admin dashboard controller
│   ├── community.php             # Community forum controller
│   ├── promotions.php            # Promotions page controller
│   ├── membership.php            # Membership tiers controller
│   ├── support.php               # Support/contact controller
│   ├── reviews.php               # Reviews page controller
│   ├── gps.php                   # GPS tracking page controller
│   ├── base.css                  # ★ Main stylesheet (served to browser)
│   └── api/                      # Symlinked/proxied API access
│       ├── auth.php → ../api/auth.php
│       ├── vehicles.php → ../api/vehicles.php
│       ├── bookings.php → ../api/bookings.php
│       ├── notifications.php → ../api/notifications.php
│       ├── admin.php → ../api/admin.php
│       ├── community.php → ../api/community.php
│       └── chatbot-with-memory.php → ../api/chatbot-with-memory.php
│
├── templates/                    # ★ PHP HTML templates
│   ├── layout/
│   │   ├── header.html.php       # Navbar, side menu, notification panel, search bar
│   │   └── footer.html.php       # Footer, chatbot widget, shared JS (notifications, search, etc.)
│   ├── index.html.php            # Homepage template
│   ├── cars.html.php             # Cars listing + filters + detail modal
│   ├── booking.html.php          # Multi-step booking form
│   ├── orders.html.php           # Orders management
│   ├── my-vehicles.html.php      # Vehicle CRUD for owners
│   ├── profile.html.php          # User profile editor
│   ├── login.html.php            # Login (email, Google, Face ID)
│   ├── register.html.php         # Registration form
│   ├── admin.html.php            # Admin dashboard
│   ├── community.html.php        # Community posts/comments
│   ├── promotions.html.php       # Promotions display
│   ├── membership.html.php       # Membership tiers
│   ├── support.html.php          # Support page
│   ├── reviews.html.php          # Reviews
│   └── gps.html.php              # GPS tracking map
│
└── base.css                      # Root copy of base.css (kept in sync)
```

---

## 4. Database Architecture

### Connection Details

| Property | Value |
|----------|-------|
| **Provider** | Supabase (AWS ap-southeast-1) |
| **Engine** | PostgreSQL 15 |
| **Connection** | PDO via `pgsql:` DSN, port `6543`, `sslmode=require` |
| **Primary Keys** | UUID v4 (`uuid_generate_v4()`) — all tables use UUID |
| **Timestamps** | `TIMESTAMPTZ` with `NOW()` defaults, auto-`updated_at` triggers |

### Tables Overview (17 tables)

```
┌──────────────────────────────────────────────────────────────────┐
│                      CORE ENTITIES                               │
├─────────────────┬────────────────────────────────────────────────┤
│ users           │ User accounts, roles, profile, Face ID, membership │
│ vehicles        │ Car listings with specs, pricing, location, ratings │
│ vehicle_images  │ BYTEA image blobs linked to vehicles           │
│ bookings        │ Rental bookings (self-drive, with-driver, airport) │
│ payments        │ Payment records for bookings                   │
├─────────────────┼────────────────────────────────────────────────┤
│                      SOCIAL / ENGAGEMENT                         │
├─────────────────┼────────────────────────────────────────────────┤
│ reviews         │ Vehicle ratings (1-5 stars) + text reviews     │
│ community_posts │ User-created blog/forum posts                  │
│ community_comments │ Comments on community posts                 │
│ community_likes │ Like/unlike on posts (unique per user+post)    │
│ favorites       │ Saved/favorited vehicles (unique per user+vehicle) │
├─────────────────┼────────────────────────────────────────────────┤
│                      PLATFORM MANAGEMENT                         │
├─────────────────┼────────────────────────────────────────────────┤
│ notifications   │ User notifications (booking, payment, promo, system) │
│ promotions      │ Promo codes with discount logic                │
│ memberships     │ Subscription tiers (free/basic/premium/corporate) │
│ hero_slides     │ Admin-managed homepage hero images (BYTEA)     │
├─────────────────┼────────────────────────────────────────────────┤
│                      TRACKING / SUPPORT                          │
├─────────────────┼────────────────────────────────────────────────┤
│ auth_sessions   │ Session tokens for stateless auth              │
│ gps_tracking    │ Real-time vehicle GPS coordinates              │
│ trip_enquiries  │ Support/contact form submissions               │
└─────────────────┴────────────────────────────────────────────────┘

n8n-managed (auto-created):
│ n8n_chat_histories │ AI chatbot conversation memory             │
```

### Key Enum Types

```sql
user_role:        'renter' | 'owner' | 'admin'
auth_provider:    'google' | 'phone' | 'faceid' | 'email'
booking_status:   'pending' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled'
payment_status:   'pending' | 'paid' | 'refunded' | 'failed'
payment_method:   'cash' | 'bank_transfer' | 'credit_card' | 'paypal'
vehicle_status:   'available' | 'rented' | 'maintenance' | 'inactive'
membership_tier:  'free' | 'basic' | 'premium' | 'corporate'
notification_type:'booking' | 'payment' | 'promo' | 'system' | 'alert'
```

### Entity Relationships

```
users (1) ──────── (N) vehicles           # Owner has many vehicles
users (1) ──────── (N) bookings           # Renter has many bookings
vehicles (1) ───── (N) bookings           # Vehicle has many bookings
vehicles (1) ───── (N) vehicle_images     # Vehicle has many images
vehicles (1) ───── (N) reviews            # Vehicle has many reviews
users (1) ──────── (N) reviews            # User writes many reviews
bookings (1) ───── (N) payments           # Booking has many payments
users (1) ──────── (N) notifications      # User has many notifications
users (1) ──────── (N) community_posts    # User writes many posts
community_posts (1)─(N) community_comments
community_posts (1)─(N) community_likes
users (1) ──────── (N) favorites          # User favorites many vehicles
users (1) ──────── (N) memberships        # User has membership history
vehicles (1) ───── (N) gps_tracking       # Vehicle has GPS history
```

### Row Level Security (RLS)

RLS is enabled on ALL tables in Supabase. Key policies:
- **Vehicles**: Public `SELECT` where `status = 'available'`
- **Promotions**: Public `SELECT` where `is_active = TRUE`
- **Community posts/Reviews**: Public `SELECT`
- **All other tables**: Require authenticated user (enforced via PHP session, not Supabase auth)

> **Important for Mobile**: The web app connects to Supabase via direct PDO connection (server-side), NOT via Supabase client SDK. For mobile, you can either:
> 1. Continue calling the same PHP REST APIs (recommended for consistency)
> 2. Use Supabase JS/Flutter SDK directly (requires setting up Supabase Auth + RLS policies per-role)

---

## 5. API Reference

All APIs return JSON. Base URL: `http://localhost:8000/api/`

Action is specified either as:
- **GET parameter**: `GET /api/vehicles.php?action=list`
- **POST body**: `POST /api/vehicles.php` with `{ "action": "list", ... }`

### 5.1 Auth API (`/api/auth.php`)

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `check-duplicate` | POST | No | Check if email/phone/username already exists |
| `register` | POST | No | Register with email + password (after email OTP verification) |
| `login` | POST | No | Login with email/username + password |
| `google-login` | POST | No | Login/register via Google OAuth token |
| `phone-send-otp` | POST | No | Send OTP to phone number |
| `phone-verify-otp` | POST | No | Verify phone OTP → login/register |
| `email-send-otp` | POST | No | Send OTP to email (via Gmail SMTP) |
| `email-verify-otp` | POST | No | Verify email OTP code |
| `enable-faceid` | POST | Yes | Store face descriptor for Face ID login |
| `disable-faceid` | POST | Yes | Remove face descriptor |
| `faceid-login` | POST | No | Login via face descriptor matching |
| `check-session` | POST | No | Check if current session is authenticated |
| `get-profile` | POST | Yes | Get current user's full profile |
| `update-profile` | POST | Yes | Update profile fields |
| `upload-avatar` | POST (multipart) | Yes | Upload avatar image (stored as BYTEA) |
| `get-avatar` | GET | No | Serve avatar image: `?action=get-avatar&id={userId}` |
| `complete-profile` | POST | Yes | Complete profile after first login |
| `email-change-send-otp` | POST | Yes | Send OTP for email change |
| `email-change-verify` | POST | Yes | Verify OTP and change email |
| `logout` | POST | Yes | Destroy session |

#### Key Request/Response Examples

**Register:**
```json
// POST /api/auth.php
{
  "action": "register",
  "username": "john_doe",
  "email": "john@example.com",
  "password": "securepass123",
  "full_name": "John Doe",
  "phone": "+84123456789",
  "role": "renter"
}
// Response:
{
  "success": true,
  "message": "Account created successfully!",
  "user": { "id": "uuid", "username": "john_doe", "email": "john@example.com", "role": "renter" }
}
```

**Login:**
```json
// POST /api/auth.php
{ "action": "login", "email": "john@example.com", "password": "securepass123" }
// Response:
{
  "success": true,
  "message": "Login successful!",
  "user": {
    "id": "uuid", "username": "john_doe", "email": "john@example.com",
    "full_name": "John Doe", "role": "renter", "membership": "free",
    "faceid_enabled": false, "avatar_url": "/api/auth.php?action=get-avatar&id=uuid"
  }
}
```

**Check Session:**
```json
// POST /api/auth.php
{ "action": "check-session" }
// Response (logged in):
{
  "success": true, "logged_in": true,
  "user": { "id": "uuid", "username": "...", "role": "renter", ... }
}
// Response (not logged in):
{ "success": true, "logged_in": false }
```

### 5.2 Vehicles API (`/api/vehicles.php`)

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `list` | POST | No | List/search vehicles with filters |
| `get` | POST | No | Get single vehicle details by ID |
| `filter-options` | GET | No | Get available brands/categories for filters |
| `search-suggestions` | GET | No | Autocomplete suggestions: `?action=search-suggestions&q=bm` |
| `my-vehicles` | POST | Yes (owner) | List current user's vehicles |
| `add` | POST | Yes (owner) | Add new vehicle |
| `update` | POST | Yes (owner) | Update vehicle |
| `delete` | POST | Yes (owner) | Delete vehicle |
| `upload-image` | POST (multipart) | Yes | Upload vehicle image (BYTEA storage) |
| `get-image` | GET | No | Serve vehicle image: `?action=get-image&id={imageId}` |
| `delete-image` | POST | Yes (owner) | Delete a vehicle image |

#### Vehicle List (Search & Filter)

```json
// POST /api/vehicles.php
{
  "action": "list",
  "search": "BMW",           // Full-text search (brand + model + year)
  "brand": "",               // Filter by exact brand
  "category": "suv",         // sedan|suv|luxury|sports|electric|van
  "transmission": "automatic",
  "fuel": "petrol",
  "max_price": 200,          // Max price per day
  "location": "",
  "limit": 50
}
// Response:
{
  "success": true,
  "vehicles": [
    {
      "id": "uuid",
      "brand": "BMW", "model": "X5", "year": 2024,
      "category": "suv", "transmission": "automatic", "fuel_type": "petrol",
      "seats": 5, "color": "Black",
      "engine_size": "3L", "consumption": "9.5L/100km",
      "price_per_day": "150.00", "price_per_week": "900.00", "price_per_month": "3200.00",
      "location_city": "Ho Chi Minh City", "location_address": "123 Nguyen Hue",
      "status": "available",
      "avg_rating": "4.5", "total_reviews": 12, "total_bookings": 30,
      "features": ["GPS", "A/C", "Bluetooth", "Backup Camera"],
      "images": ["/api/vehicles.php?action=get-image&id=uuid1", "..."],
      "image_ids": ["uuid1", "uuid2"],
      "owner_name": "John Doe",
      "license_plate": "51A-12345"
    }
  ],
  "total": 1
}
```

#### Search Suggestions (Autocomplete)

```
GET /api/vehicles.php?action=search-suggestions&q=mer
```
```json
{
  "success": true,
  "suggestions": [
    { "type": "brand", "text": "Mercedes", "label": "Mercedes", "sub": "5 vehicles available" },
    { "type": "vehicle", "text": "Mercedes C300 2024", "label": "Mercedes C300 2024", "sub": "Luxury • $180/day", "id": "uuid" }
  ]
}
```

### 5.3 Bookings API (`/api/bookings.php`)

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `validate-promo` | POST | No | Validate a promotion code |
| `active-promos` | GET | No | List all active promotions |
| `create` | POST | Yes | Create a new booking |
| `my-orders` | POST | Yes | Get current user's bookings (as renter or owner) |
| `update-status` | POST | Yes | Update booking status (confirm/cancel/complete) |

#### Create Booking

```json
// POST /api/bookings.php
{
  "action": "create",
  "vehicle_id": "uuid",
  "booking_type": "self-drive",    // "self-drive" | "with-driver" | "airport"
  "pickup_date": "2026-03-15",
  "return_date": "2026-03-20",
  "pickup_location": "123 Main St",
  "return_location": "456 Airport Rd",
  "airport_name": "",              // Only for airport transfers
  "special_requests": "Need child seat",
  "driver_requested": false,
  "payment_method": "credit_card", // "cash" | "bank_transfer" | "credit_card" | "paypal"
  "promo_code": "WEEKEND20",
  "distance_km": null,             // For airport transfers only
  "transfer_cost": null            // For airport transfers only
}
// Response:
{
  "success": true,
  "message": "Booking created successfully!",
  "booking": {
    "id": "uuid", "status": "pending",
    "total_days": 5, "price_per_day": "150.00",
    "subtotal": "750.00", "discount_amount": "150.00",
    "total_amount": "600.00", "promo_code": "WEEKEND20"
  }
}
```

#### My Orders

```json
// POST /api/bookings.php
{ "action": "my-orders" }
// Response:
{
  "success": true,
  "orders": [
    {
      "id": "uuid", "booking_type": "self-drive",
      "status": "confirmed",
      "pickup_date": "2026-03-15", "return_date": "2026-03-20",
      "total_amount": "600.00",
      "vehicle_brand": "BMW", "vehicle_model": "X5", "vehicle_year": 2024,
      "vehicle_image": "/api/vehicles.php?action=get-image&id=uuid",
      "renter_name": "John Doe", "owner_name": "Jane Smith",
      "created_at": "2026-02-28T10:00:00Z"
    }
  ],
  "stats": { "total": 5, "active": 2, "completed": 3, "cancelled": 0, "total_spent": "3200.00" }
}
```

### 5.4 Notifications API (`/api/notifications.php`)

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `list` | GET | Yes | Get notifications: `?action=list&limit=20&offset=0` |
| `unread-count` | GET | Yes | Get unread notification count |
| `mark-read` | POST | Yes | Mark one notification as read |
| `mark-all-read` | POST | Yes | Mark all as read |
| `delete` | POST | Yes | Delete a notification |
| `clear-all` | POST | Yes | Delete all notifications |

```json
// GET /api/notifications.php?action=list&limit=20
{
  "success": true,
  "notifications": [
    {
      "id": "uuid",
      "type": "booking",
      "title": "New Booking",
      "message": "Your BMW X5 was booked by John Doe",
      "is_read": false,
      "created_at": "2026-02-28T10:00:00Z",
      "time_ago": "5 minutes ago"
    }
  ],
  "unread_count": 3,
  "total": 15
}
```

**Polling**: The web app polls `unread-count` every 15 seconds. Mobile apps should use the same polling or implement push notifications via Firebase + a webhook.

### 5.5 Admin API (`/api/admin.php`)

Requires `role = 'admin'`.

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `hero-slides-public` | GET/POST | No | Get active hero slides for homepage |
| `hero-slide-image` | GET | No | Serve hero slide image: `?action=hero-slide-image&id=uuid` |
| `hero-slides-list` | POST | Admin | List all hero slides (admin) |
| `hero-slide-upload` | POST (multipart) | Admin | Upload new hero slide |
| `hero-slide-update` | POST | Admin | Update hero slide metadata |
| `hero-slide-delete` | POST | Admin | Delete hero slide |
| `promotions-list` | POST | Admin | List all promotions |
| `promotion-add` | POST | Admin | Create new promo code |
| `promotion-update` | POST | Admin | Update promotion |
| `promotion-delete` | POST | Admin | Delete promotion |
| `admin-list-users` | POST | Admin | List all users |
| `admin-update-user` | POST | Admin | Update user role/status |
| `admin-delete-user` | POST | Admin | Delete user |
| `admin-list-vehicles` | POST | Admin | List all vehicles |
| `admin-delete-vehicle` | POST | Admin | Delete any vehicle |
| `admin-list-bookings` | POST | Admin | List all bookings |
| `admin-delete-booking` | POST | Admin | Delete any booking |

### 5.6 Community API (`/api/community.php`)

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `list-posts` | GET/POST | No | List community posts (with pagination) |
| `create-post` | POST (multipart) | Yes | Create post with optional image |
| `delete-post` | POST | Yes (owner/admin) | Delete own post |
| `toggle-like` | POST | Yes | Like/unlike a post |
| `list-comments` | GET/POST | No | Get comments for a post |
| `add-comment` | POST | Yes | Add comment to a post |
| `delete-comment` | POST | Yes (owner/admin) | Delete own comment |
| `get-user-public` | GET | No | Get public user profile |
| `get-post-image` | GET | No | Serve post image: `?action=get-post-image&id=uuid` |

### 5.7 Chatbot API (`/api/chatbot-with-memory.php`)

```json
// POST /api/chatbot-with-memory.php
{ "message": "I need an SUV for next weekend" }
// Response:
{ "success": true, "response": "I'd be happy to help you find an SUV! ..." }
```

The chatbot proxies messages to an **n8n AI Agent** webhook. n8n handles:
- Conversation history (stored in `n8n_chat_histories` table)
- LLM processing (connected to OpenAI/Anthropic via n8n)
- Context-aware responses

---

## 6. Authentication Flow

### Session-Based Auth (Web)

The web app uses PHP server-side sessions. After successful authentication:

```php
$_SESSION['logged_in']  = true;
$_SESSION['user_id']    = $user['id'];      // UUID
$_SESSION['username']   = $user['username'];
$_SESSION['full_name']  = $user['full_name'];
$_SESSION['email']      = $user['email'];
$_SESSION['role']       = $user['role'];     // 'renter' | 'owner' | 'admin'
```

### Auth Methods

```
┌─────────────────────────────────────────────────┐
│              AUTHENTICATION METHODS              │
├─────────────────────────────────────────────────┤
│                                                  │
│  1. EMAIL + PASSWORD                             │
│     register → login → session                   │
│                                                  │
│  2. EMAIL OTP                                    │
│     email-send-otp → email-verify-otp → session  │
│     (6-digit code via Gmail SMTP)                │
│                                                  │
│  3. PHONE OTP                                    │
│     phone-send-otp → phone-verify-otp → session  │
│     (6-digit code, SMS provider required)        │
│                                                  │
│  4. GOOGLE OAUTH                                 │
│     google-login (with google token) → session   │
│     (creates user if first login)                │
│                                                  │
│  5. FACE ID (optional, enable after login)       │
│     enable-faceid (store descriptor) →           │
│     faceid-login (match descriptor) → session    │
│     Uses face-api.js (client-side) + JSONB       │
│     descriptor stored in users.face_descriptor   │
│                                                  │
└─────────────────────────────────────────────────┘
```

### For Mobile Apps

Since the web app uses PHP sessions (cookies), mobile apps should:

1. **Option A (Recommended)**: Call the same PHP APIs with cookie-based session management. Use a cookie jar / persistent cookie storage in your HTTP client.
2. **Option B**: Implement token-based auth by extending the `auth_sessions` table — generate a JWT or bearer token on login, pass it via `Authorization` header. The `auth_sessions` table already exists for this purpose.
3. **Option C**: Use Supabase Auth SDK directly (requires additional RLS policy configuration).

---

## 7. User Roles & Permissions

| Role | Capabilities |
|------|-------------|
| **renter** | Browse cars, book vehicles, manage own bookings, write reviews, community posts, favorites |
| **owner** | All renter capabilities + Add/edit/delete own vehicles, view bookings for own vehicles, manage vehicle images |
| **admin** | All capabilities + Manage all users/vehicles/bookings, hero slides, promotions, system notifications |

Role is set during registration (`role` field) and stored in `users.role` as a PostgreSQL enum.

---

## 8. Core Business Flows

### 8.1 Vehicle Listing Flow

```
Owner registers (role=owner)
  → Owner adds vehicle (POST vehicles.php {action: "add"})
    → Upload images (POST vehicles.php {action: "upload-image"})
    → Vehicle appears in listings (status='available')
```

### 8.2 Booking Flow

```
Renter browses cars (GET vehicles.php {action: "list"})
  → Renter selects car → views detail (POST vehicles.php {action: "get"})
  → Renter creates booking (POST bookings.php {action: "create"})
    → Validates promo code if provided
    → Calculates pricing (daily rate × days - discount)
    → Creates booking record (status='pending')
    → Creates payment record
    → Sends notifications to both renter and vehicle owner
  → Owner confirms booking (POST bookings.php {action: "update-status", status: "confirmed"})
  → Renter picks up car (status: "in_progress")
  → Renter returns car (status: "completed")
  → Renter can write review
```

### 8.3 Booking Types

| Type | Description | Price Calculation |
|------|-------------|-------------------|
| `self-drive` | Renter drives themselves | `price_per_day × total_days` |
| `with-driver` | Professional driver included | `price_per_day × total_days + driver_fee` |
| `airport` | Airport pickup/dropoff transfer | `transfer_cost` (flat fee based on distance) |

### 8.4 Promotion System

```json
{
  "code": "WEEKEND20",
  "discount_type": "percentage",  // or "fixed"
  "discount_value": 20,           // 20% off, or $20 off
  "min_booking_days": 2,
  "max_uses": 100,
  "expires_at": "2026-03-31"
}
```

---

## 9. Real-time Notifications

### Architecture

```
Booking created / Status changed / Auth event / Promo broadcast
  → PHP calls createNotification($pdo, $userId, $type, $title, $message)
    → INSERT INTO notifications table
      → Frontend polls GET /api/notifications.php?action=unread-count (every 15s)
        → Badge updates on bell icon
```

### Notification Types

| Type | Trigger |
|------|---------|
| `booking` | New booking created, status changed |
| `payment` | Payment processed |
| `promo` | New promotion available (broadcast) |
| `system` | Account changes, admin messages |
| `alert` | Important alerts |

### For Mobile Push Notifications

The current system uses polling. For mobile, extend with:
1. Add a `push_token` column to `users` table
2. After `createNotification()`, also call Firebase Cloud Messaging (FCM)
3. Or use Supabase Realtime to subscribe to `notifications` table changes

---

## 10. Image Storage

### Architecture (Supabase Storage Bucket)

Images are stored in the **Supabase Storage** bucket named `DriveNow`, organized by folder:

```
DriveNow/                        ← Supabase Storage Bucket
├── vehicles/{vehicle_id}/       ← Vehicle photos
│   ├── abc123def456.jpg
│   └── 789ghi012jkl.png
├── avatars/                     ← User profile pictures
│   └── {user_id}.jpg
├── hero-slides/                 ← Admin homepage banners
│   └── abc123def456.jpg
└── community/                   ← Community post images
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

### `.env` File

```bash
# Database (Supabase PostgreSQL)
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=6543
DB_NAME=postgres
DB_USER=postgres.your_project_ref
DB_PASSWORD=your_password
DB_SSL_MODE=require

# Supabase Storage
SUPABASE_URL=https://your_project_ref.supabase.co
SUPABASE_SERVICE_KEY=your_supabase_service_role_key

# n8n AI Chatbot
N8N_WEBHOOK_URL=http://localhost:5678/webhook/your-webhook-id

# Mem0 AI Memory (optional)
MEM0_API_KEY=your_mem0_key

# Email (Gmail SMTP for OTPs)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=your@gmail.com
SMTP_FROM_NAME=DriveNow

# App
APP_ENV=development
APP_DEBUG=true
GOOGLE_MAPS_API_KEY=your_maps_key
```

---

## 13. Running Locally

### Prerequisites

- PHP 8.2+ with extensions: `pdo_pgsql`, `mbstring`, `openssl`, `json`
- Composer (for PHPMailer)
- PostgreSQL database (Supabase account or local)
- n8n (optional, for chatbot)

### Setup

```bash
# 1. Clone the repository
git clone https://github.com/DK0310/Scrum_App.git
cd Scrum_App

# 2. Install PHP dependencies
composer install

# 3. Configure environment
cp .env.example .env
# Edit .env with your Supabase credentials

# 4. Initialize database
# Run Database/schema.sql against your Supabase PostgreSQL

# 5. Start PHP development server
php -S localhost:8000 -t public

# 6. Open http://localhost:8000
```

### API Testing

```bash
# Check session
curl -X POST http://localhost:8000/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"action": "check-session"}'

# List vehicles
curl -X POST http://localhost:8000/api/vehicles.php \
  -H "Content-Type: application/json" \
  -d '{"action": "list", "limit": 10}'

# Search suggestions
curl "http://localhost:8000/api/vehicles.php?action=search-suggestions&q=bmw"
```

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

*Last updated: February 28, 2026*
