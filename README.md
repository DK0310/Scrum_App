# Private Hire — Premium Car Rental & Transportation Platform

> Full-stack premium car rental and transportation platform built with PHP 8.2 + PostgreSQL (Supabase). This document provides comprehensive architecture context for developers and team members working on the platform.

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
10. [Image Storage (Supabase Storage)](#10-image-storage)
11. [Payments & Invoicing](#11-payments--invoicing)
12. [AI Chatbot Integration](#12-ai-chatbot-integration)
13. [Environment Configuration](#13-environment-configuration)
14. [Running the Web App Locally](#14-running-locally)
15. [Mobile App Integration Guide](#15-mobile-app-integration-guide)
16. [Entity Relationship Diagram](#16-erd)

---

## 📋 Recent Updates (April 2026)

### Latest Changes

✅ **5-Role System Implemented**
- Roles upgraded from 4 to 5: `user`, `driver`, `callcenterstaff`, `controlstaff`, `admin`
- Call Center Staff: creates booking requests for customers, manages customer accounts
- Control Staff: approves/rejects requests, dispatches drivers, manages fleet vehicles

🔧 **New Features Since March 2026**
- **PayPal Payment Gateway** (`lib/payments/PayPalGateway.php`) with sandbox/mock support
- **Invoice PDF Generation** via mPDF (`Invoice/invoice_mpdf.php`) with email attachment
- **Password Reset Flow** (`api/password-change.php`) with token-based email link
- **Dedicated Reviews API** (`api/reviews.php`) with loyalty points system
- **Booking Modification** (`api/orders.php` → `modify-booking`) for cash minicab orders
- **Booking Archive System** (DB trigger auto-archives completed/cancelled bookings)
- **Vehicle Availability Subscriptions** (notify users when a vehicle becomes available)
- **Account Balance** for refunds and payments
- **Driver Dispatch Email** (automated dispatch notification via PHPMailer)
- **API Bootstrap Layer** (`api/bootstrap.php`) — shared `api_init()`, `api_action()`, `api_require_auth()`, `api_json()`
- **Loyalty Points** awarded after review submission (tier-based for Daily Hire)

📁 **Project Structure**
- Root route stubs in `/` (e.g., `cars.php`, `booking.php`) require `/pages/*` controllers
- Page controllers in `/pages/` handle SSR setup (session, role, etc.)
- Data repositories in `/sql/` (Repository Pattern — 12 repositories)
- API endpoints in `/api/` for JSON actions (29 files)
- HTML templates in `/templates/` with shared layout components
- Invoice generation in `/Invoice/` (mPDF + mailer)
- Payment gateways in `/lib/payments/` (PayPal)

---

## 1. System Overview

**Private Hire** is a full-stack car rental and premium transportation platform that connects vehicle owners with renters. The system supports minicab rides, daily hire, airport/hotel transfers, fleet management, real-time notifications, community engagement, AI-powered assistance, and staff-managed phone bookings.

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                             │
│  ┌──────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │  Web Browser  │  │  Mobile App (TBD) │  │  Driver App      │  │
│  │  (PHP SSR +   │  │  (iOS/Android/    │  │  (Mobile -      │  │
│  │   vanilla JS) │  │   Flutter/RN)     │  │   driver role)  │  │
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
│  │admin.php │ │CallCenter│ │Control   │ │ driver.php        │  │
│  │          │ │Staff.php │ │Staff.php │ │ (driver API)      │  │
│  └──────────┘ └──────────┘ └──────────┘ └───────────────────┘  │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌───────────────────┐  │
│  │orders.php│ │reviews.  │ │password- │ │ chatbot-with-     │  │
│  │          │ │  php     │ │change.php│ │   memory.php      │  │
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
│  │  • 25+ tables             │  │  • Gmail SMTP (OTP/email)  │  │
│  │  • UUID primary keys      │  │  • Supabase Storage        │  │
│  │  • Booking archive system │  │  • Google OAuth            │  │
│  │  • RLS enabled            │  │  • Face.js (Face ID)       │  │
│  │  • Enum types             │  │  • PayPal (sandbox/live)   │  │
│  │  • DB triggers            │  │  • mPDF (invoice gen)      │  │
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
| **Authentication** | PHP Sessions + Multi-method | Email/password, Google OAuth, Phone OTP, Email OTP, Face ID |
| **Payments** | PayPal + Account Balance + Cash | PayPal sandbox/live, account balance refunds, cash at pickup |
| **Invoice** | mPDF | PDF invoice generation emailed on order confirmation |
| **Styling** | Custom CSS | CSS variables, responsive design in `base.css` |
| **API Security** | CORS enabled + Bootstrap layer | `api_init()` → CORS, session, input parsing |
| **AI Chatbot** | n8n AI Agent | Webhook-based AI agent integration with PostgreSQL chat memory |
| **Email Delivery** | PHPMailer + Gmail SMTP | OTP delivery, booking confirmations, dispatch emails, invoice emails |
| **Deployment Target** | PHP 8.2+ server | Apache, Nginx, or PHP built-in server (`php -S`) |

---

## 3. Project Structure

```
Scrum/                           # Project root
├── .env                          # Environment variables (DB, SMTP, PayPal, API keys)
├── .env.example                  # Template for .env
├── composer.json                 # PHP deps (PHPMailer, Guzzle, mPDF, Google API Client)
├── composer.lock                 # Locked dependency versions
│
├── config/                       # Configuration & loaders
│   ├── env.php                  # EnvLoader class — reads .env file
│   └── chatbot-system-prompt.md # AI chatbot system prompt (placeholder)
│
├── Database/                     # Database setup and utilities
│   ├── db.php                   # PDO connection to Supabase PostgreSQL
│   ├── schema.sql               # Full database schema (25+ tables + RLS + triggers)
│   ├── migration_storage.sql    # Migration: BYTEA → Supabase Storage
│   ├── migrate_roles_and_trips.sql          # Role migration + active_trips
│   ├── migrate_roles_to_callcenter_control.sql  # 5-role migration
│   ├── migrate_vehicle_service_tier.sql     # Vehicle service tier migration
│   ├── chat_memory.sql          # Chat history table reference
│   ├── fix_test_roles.sql       # Test role fixes
│   └── drop_all.sql             # Reset script
│
├── sql/                          # ★ Data access layer (Repository pattern — 12 files)
│   ├── AuthRepository.php        # Auth, password verification, reset tokens
│   ├── UserRepository.php        # User CRUD, loyalty points, account balance
│   ├── VehicleRepository.php     # Vehicle listing, search, filtering, assignments
│   ├── VehicleImageRepository.php# Vehicle image metadata
│   ├── BookingRepository.php     # Booking CRUD, status, payments, reviews, archive
│   ├── PromotionRepository.php   # Promo codes & discounts
│   ├── NotificationRepository.php# User & driver notifications
│   ├── CommunityRepository.php   # Community posts/comments
│   ├── HeroSlideRepository.php   # Homepage hero slides
│   ├── TripRepository.php        # Driver trips & active trips
│   ├── StaffBookingRepository.php# Staff phone booking management
│   └── EnquiryRepository.php     # Customer enquiry records
│
├── api/                          # ★ REST API endpoints (JSON) — 29 files
│   ├── bootstrap.php             # Shared: api_init(), api_action(), api_require_auth(), api_json()
│   ├── admin.php                 # Admin operations (slides, promos, users, vehicles, bookings)
│   ├── auth.php                  # Legacy composite auth API (kept for compatibility)
│   ├── login.php                 # Login endpoint (email/phone + password)
│   ├── register.php              # Register endpoint (email + password + OTP)
│   ├── session.php               # Session management (check-session, logout)
│   ├── profile.php               # Profile CRUD + avatar
│   ├── bookings.php              # Booking creation, promo validation, PayPal flow
│   ├── orders.php                # Order management (my-orders, modify-booking, update-status)
│   ├── reviews.php               # Reviews + loyalty points (get-reviews, submit-review)
│   ├── vehicles.php              # Vehicle CRUD, search, filtering, images
│   ├── notifications.php         # Notification CRUD + polling
│   ├── community.php             # Community posts/comments
│   ├── promotions.php            # Promotion endpoints
│   ├── membership.php            # Membership endpoints
│   ├── support.php               # Support/contact endpoints
│   ├── customer-enquiry.php      # Customer enquiry endpoints
│   ├── driver.php                # Driver dashboard (orders, trips, notifications)
│   ├── my-vehicles.php           # Owner vehicle management
│   ├── CallCenterStaff.php       # Call center: phone bookings, customer accounts
│   ├── ControlStaff.php          # Control staff: approve orders, dispatch, fleet management
│   ├── password-change.php       # Password reset (send-reset-link, verify-token, reset-password)
│   ├── face-auth.php             # Face ID registration/login
│   ├── faceid.php                # Face descriptor handling
│   ├── email-security.php        # Email change verification
│   ├── chatbot-with-memory.php   # AI chatbot proxy
│   ├── n8n.php                   # N8N AI Agent connector
│   ├── supabase-storage.php      # Supabase Storage helper
│   └── notification-helpers.php  # Shared notification utilities
│
├── templates/                    # ★ PHP HTML templates (SSR) — 18 files + partials
│   ├── layout/
│   │   ├── header.html.php       # Navigation bar, header
│   │   └── footer.html.php       # Footer, chatbot widget
│   ├── partials/
│   │   └── vehicle-detail-modal.html.php  # Vehicle detail modal
│   ├── booking/
│   │   ├── step1-trip-details.html.php    # Booking step 1: trip details
│   │   └── step2-payment.html.php         # Booking step 2: payment
│   ├── index.html.php            # Homepage template
│   ├── cars.html.php             # Cars listing template
│   ├── booking.html.php          # Booking form template
│   ├── orders.html.php           # User orders template
│   ├── my-vehicles.html.php      # Vehicle management template
│   ├── profile.html.php          # User profile template
│   ├── admin.html.php            # Admin dashboard template
│   ├── login.html.php            # Login page template
│   ├── register.html.php         # Registration page template
│   ├── community.html.php        # Community forum template (removed)
│   ├── promotions.html.php       # Promotions display template
│   ├── membership.html.php       # Membership tiers template
│   ├── support.html.php          # Support page template
│   ├── reviews.html.php          # Reviews page template
│   ├── driver.html.php           # Driver dashboard template
│   ├── password-change.html.php  # Password change page
│   ├── CallCenterStaff.html.php  # Call center staff interface
│   ├── ControlStaff.html.php     # Control staff interface
│   └── customer-enquiry.html.php # Customer enquiry page
│
├── resources/                    # Frontend resources
│   ├── js/                       # Client-side JavaScript (20 files)
│   │   ├── app-init.js           # Global app initialization
│   │   ├── auth.js               # Authentication logic (login/register modals)
│   │   ├── auth-state.js         # Navbar auth sync module
│   │   ├── navbar.js             # Navigation bar logic
│   │   ├── header.js             # Header interactions
│   │   ├── home.js               # Homepage car grid + hero slides
│   │   ├── cars.js               # Cars listing logic
│   │   ├── booking.js            # Booking form logic (multi-step)
│   │   ├── orders.js             # Orders management (modify, cancel, status)
│   │   ├── profile.js            # Profile management
│   │   ├── admin.js              # Admin dashboard logic
│   │   ├── driver.js             # Driver dashboard logic
│   │   ├── call-center-staff.js  # Call center staff UI logic
│   │   ├── control-staff.js      # Control staff UI logic
│   │   ├── notifications.js      # Notification polling
│   │   ├── reviews.js            # Reviews display/submission
│   │   ├── customer-enquiry.js   # Customer enquiry UI
│   │   ├── chatbot.js            # Chatbot widget logic
│   │   ├── pass.js               # Password change logic
│   │   └── utils.js              # Shared utility functions
│   ├── css/
│   │   └── base.css              # Main stylesheet (56KB)
│   └── images/                   # Static images (cars, logos, CSV data)
│
├── pages/                        # Page controllers (SSR logic) — 15 files
│   ├── admin.php                 # Admin page controller
│   ├── booking.php               # Booking page controller
│   ├── call-center-staff.php     # Call center staff page
│   ├── cars.php                  # Cars page controller
│   ├── control-staff.php         # Control staff page
│   ├── customer-enquiry.php      # Customer enquiry page
│   ├── driver.php                # Driver page
│   ├── membership.php            # Membership page
│   ├── my-vehicles.php           # Owner vehicles page
│   ├── orders.php                # Orders page
│   ├── password-change.php       # Password change page
│   ├── profile.php               # Profile page
│   ├── promotions.php            # Promotions page
│   ├── reviews.php               # Reviews page
│   └── support.php               # Support page
│
├── lib/                          # Additional libraries
│   └── payments/
│       └── PayPalGateway.php     # PayPal REST API gateway (sandbox + live)
│
├── Invoice/                      # Invoice generation
│   ├── invoice_mpdf.php          # PDF invoice generation (mPDF)
│   └── mailer.php                # Email helper (PHPMailer + PDF attachment)
│
├── tests/                        # Test scripts (9 files)
│   ├── test_api_flow.php
│   ├── test_auth_flow.php
│   ├── test_db.php
│   ├── test_debug_orders.php
│   ├── test_debug_vehicles.php
│   ├── test_full_api.php
│   ├── test_full_login_flow.php
│   ├── test_login_api.php
│   └── test_login_debug.php
│
├── ReferenceUI/                  # UI reference designs
├── vendor/                       # Composer dependencies
│
# Root level route stubs (URL routing)
├── index.php                     # Homepage controller (routes /auth, loads promotions)
├── cars.php                      # Cars route → pages/cars.php
├── booking.php                   # Booking route → pages/booking.php
├── orders.php                    # Orders route → pages/orders.php
├── profile.php                   # Profile route → pages/profile.php
├── promotions.php                # Promotions route → pages/promotions.php
├── membership.php                # Membership route → pages/membership.php
├── support.php                   # Support route → pages/support.php
├── customer-enquiry.php          # Customer enquiry route
├── admin.php                     # Admin route → pages/admin.php
├── driver.php                    # Driver route → pages/driver.php
├── call-center-staff.php         # Call center staff route
├── control-staff.php             # Control staff route
├── my-vehicles.php               # Owner vehicles route
├── password-change.php           # Password change route
├── reviews.php                   # Reviews route
│
# Documentation
├── README.md                     # This file
├── TIMEZONE_FIX.md               # Timezone configuration guide
└── VEHICLE_STATUS_UPDATE.md      # Vehicle status update documentation
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

### Database Tables Overview (25+ tables)

| Table | Purpose |
|-------|---------|
| `users` | User accounts, 5 roles (user/driver/callcenterstaff/controlstaff/admin), profile, account balance, loyalty points, driver dispatch assignment |
| `vehicles` | Vehicle listings with specs, pricing, service tier (eco/standard/luxury), status, luggage capacity |
| `vehicle_images` | Vehicle photos with Supabase Storage paths or BYTEA fallback |
| `vehicle_assignments` | Driver ↔ vehicle dispatch history (staff/driver/vehicle/dates) |
| `bookings` | Rental bookings with status, dates, pricing, driver assignment, ride tracking timestamps |
| `active_trips` | Real-time minicab trip tracking (driver location, status progression) |
| `booking_archive` | Auto-archived completed/cancelled bookings (DB trigger) |
| `booking_regions` | Normalized pickup regions for archive analytics |
| `booking_deletion_audit` | Audit log for deleted bookings |
| `payments` | Payment records with PayPal/cash/account_balance, `payment_details` JSONB |
| `reviews` | Vehicle ratings (1-5) and text reviews from renters |
| `community_posts` | User-created blog/forum posts |
| `community_comments` | Comments on community posts |
| `community_likes` | Likes/favorites on community posts (UNIQUE per user+post) |
| `notifications` | User notifications (booking, payment, promo, system, alert) |
| `driver_notifications` | Driver-specific notifications with booking reference |
| `vehicle_availability_subscriptions` | Notify users when a vehicle becomes available |
| `promotions` | Promo codes with discount logic (percentage or fixed amount) |
| `memberships` | User membership tiers (free/basic/premium/corporate) |
| `hero_slides` | Admin-managed homepage banner images |
| `favorites` | Saved/favorited vehicles (UNIQUE per user+vehicle) |
| `gps_tracking` | Real-time vehicle GPS coordinates and trip tracking |
| `auth_sessions` | Session tokens for stateless authentication |
| `password_reset_tokens` | Time-limited password reset tokens (5-min expiry) |
| `customer_enquiries` | Customer enquiry submissions |
| `enquiry_replies` | Staff replies to customer enquiries |
| `trip_enquiries` | Support/contact form submissions |
| `n8n_chat_histories` | AI chatbot conversation memory (auto-created by n8n) |

### Key Enum Types

```sql
user_role_v2:      'user' | 'driver' | 'callcenterstaff' | 'controlstaff' | 'admin'
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
bookings (1) ────────── (1) active_trips          # Booking has one active trip
bookings (1) ────────── (1) booking_archive       # Booking archived on completion
users (1) ──────────── (N) notifications          # User has many notifications
users (1) ──────────── (N) driver_notifications   # Driver has dispatch notifications
users (1) ──────────── (N) vehicle_assignments    # Driver assigned vehicles
users (1) ──────────── (N) community_posts        # User writes many posts
community_posts (1) ────(N) community_comments    # Post has many comments
community_posts (1) ────(N) community_likes       # Post has many likes
users (1) ──────────── (N) favorites              # User has many favorites
users (1) ──────────── (N) memberships            # User has membership history
vehicles (1) ────────── (N) gps_tracking          # Vehicle has GPS history
users (1) ──────────── (N) vehicle_availability_subscriptions
```

---

## 5. API Reference

All APIs return JSON. Base URL: `http://localhost:8000/api/`

**Shared Bootstrap** (`api/bootstrap.php`):
- `api_init()` — Sets CORS headers, starts session, parses JSON/POST input
- `api_action($input)` — Gets action from POST body or GET param
- `api_require_auth()` — Guards authenticated endpoints
- `api_json($payload, $status)` — Sends JSON response

### 5.1 Auth APIs

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/login.php` | POST | No | Login with email/phone + password |
| `/api/register.php` | POST | No | Register with email + password + OTP |
| `/api/session.php` | POST | No | Check session or logout |
| `/api/profile.php` | POST | Yes | Profile read/update + avatar upload |
| `/api/email-security.php` | POST | Yes | Email change OTP flow |
| `/api/faceid.php` | POST | Yes | Face descriptor update |
| `/api/face-auth.php` | POST | No | Face ID login/registration |
| `/api/password-change.php` | POST | No* | Password reset flow |

**Password Reset Actions** (`/api/password-change.php`):
| Action | Auth | Description |
|--------|------|-------------|
| `send-reset-link` | No | Send reset email (generic response for privacy) |
| `send-reset-link-current-user` | Yes | Send reset link to logged-in user's email |
| `verify-token` | No | Verify reset token validity |
| `reset-password` | No | Reset password with token + new password |

### 5.2 Vehicles API (`/api/vehicles.php`)

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `list` | POST | No | List/search vehicles with filters |
| `filter-options` | GET | No | Get available brands/categories for filters |
| `search-suggestions` | GET | No | Autocomplete: `?action=search-suggestions&q=bmw` |
| `get` | POST | No | Get single vehicle details by ID |
| `my-vehicles` | POST | Yes (staff/admin) | List all vehicles for management |
| `add` | POST | Yes (staff/admin) | Add new vehicle |
| `update` | POST | Yes (staff/admin) | Update vehicle details |
| `delete` | POST | Yes (staff/admin) | Delete vehicle |
| `upload-image` | POST (multipart) | Yes (owner) | Upload vehicle image to Supabase Storage |
| `get-image` | GET | No | Get vehicle image → redirects to Supabase URL |
| `delete-image` | POST | Yes (owner) | Delete vehicle image |
| `public-list` | GET | No | Get public available vehicles |

### 5.3 Bookings API (`/api/bookings.php`)

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `create` | POST | Yes | Create new booking (with PayPal/cash/account_balance) |
| `validate-promo` | POST | No | Validate promotion code |
| `active-promos` | GET | No | List all active promotions |
| `confirm-payment` | POST | Yes | Confirm payment for booking |

### 5.4 Orders API (`/api/orders.php`)

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `my-orders` | POST | Yes | Get user's bookings (renter or owner view) |
| `modify-booking` | POST | Yes | Modify pending cash minicab booking (pickup, destination, tier, seats, date) |
| `update-status` | POST | Yes | Update booking status (confirm, in_progress, complete, cancel) with auto-refund |

### 5.5 Reviews API (`/api/reviews.php`)

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `get-reviews` | GET/POST | No | Get reviews (optionally by vehicle_id) with stats |
| `submit-review` | POST | Yes | Submit review for completed booking + earn loyalty points |

### 5.6 Notifications API (`/api/notifications.php`)

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `list` | GET | Yes | Get notifications (paginated) |
| `unread-count` | GET | Yes | Get unread notification count |
| `mark-read` | POST | Yes | Mark notification as read |
| `mark-all-read` | POST | Yes | Mark all as read |
| `delete` | POST | Yes | Delete notification |
| `clear-all` | POST | Yes | Delete all notifications |

### 5.7 Admin API (`/api/admin.php`)

Requires `role = 'admin'`. Full platform management: hero slides CRUD, promotions CRUD, user management (list/update/delete), vehicle management, booking management.

### 5.8 Driver API (`/api/driver.php`)

Requires `role = 'driver'`.

| Action | Description |
|--------|-------------|
| `get_assigned_vehicle` | Get driver's currently assigned vehicle |
| `get_current_orders` | Get active orders for driver |
| `get_past_orders` | Get completed orders history |
| `get_passenger_orders` | Get orders for a specific passenger |
| `advance_order_status` | Progress trip: on_route → on_trip → completed (with dispatch email) |
| `get_notifications` | Get driver notifications |
| `mark_notification_read` | Mark driver notification as read |

### 5.9 Call Center Staff API (`/api/CallCenterStaff.php`)

Requires `role = 'callcenterstaff'` or `'admin'`.

| Action | Description |
|--------|-------------|
| `search_customers` | Search customers by name/email/phone |
| `get_vehicles` | List available vehicles |
| `get_my_requests` | List staff's own booking requests |
| `get_request_detail` | Get detailed booking request info |
| `create_customer_account` | Create customer account (email with credentials) |
| `booking_by_request` | Create phone booking request for customer |
| `cancel_request` | Cancel own booking request (with auto-refund) |
| `delete_request` | Delete own pending/cancelled request |

### 5.10 Control Staff API (`/api/ControlStaff.php`)

Requires `role = 'controlstaff'` or `'admin'`.

| Action | Description |
|--------|-------------|
| `get_orders` | List all bookings (filterable by status) |
| `get_order` | Get single order details |
| `update_order_status` | Confirm order: pending → in_progress (auto-assigns driver+vehicle, sends invoice) |
| `reject_order` | Reject pending order (with auto-refund) |
| `get_vehicles` | List all fleet vehicles |
| `get_drivers` | List drivers with dispatch status (pending/dispatched) |
| `get_available_vehicles` | List unassigned available vehicles |
| `dispatch_driver` | Assign vehicle to driver |
| `unassign_driver` | Remove vehicle assignment from driver |
| `add_vehicle` | Add new vehicle to fleet |
| `edit_vehicle` | Edit vehicle details |
| `delete_vehicle` | Delete vehicle from fleet |

### 5.11 Additional APIs

| Endpoint | Description |
|----------|-------------|
| `/api/community.php` | Community posts/comments CRUD |
| `/api/support.php` | Support/contact form submissions |
| `/api/membership.php` | Membership tier management |
| `/api/promotions.php` | Promotion endpoints |
| `/api/customer-enquiry.php` | Customer enquiry CRUD |
| `/api/my-vehicles.php` | Owner vehicle management |
| `/api/chatbot-with-memory.php` | AI chatbot proxy (n8n) |
| `/api/supabase-storage.php` | Supabase Storage operations |

---

## 6. Authentication Flow

### Session-Based Auth (Web)

```php
$_SESSION['logged_in']  = true;
$_SESSION['user_id']    = $user['id'];           // UUID
$_SESSION['username']   = $user['full_name'] ?? $user['email'];
$_SESSION['full_name']  = $user['full_name'];
$_SESSION['email']      = $user['email'];
$_SESSION['role']       = $user['role'];         // 5-role enum
$_SESSION['phone']      = $user['phone'] ?? null;
$_SESSION['avatar_url'] = $user['avatar_url'] ?? null;
$_SESSION['membership'] = $user['membership'] ?? 'free';
```

### Authentication Methods

1. **EMAIL + PASSWORD** — Standard registration/login with hashed passwords
2. **EMAIL OTP** — 6-digit code via Gmail SMTP → auto-register if new
3. **PHONE OTP** — 6-digit code via SMS → auto-register if new
4. **GOOGLE OAUTH 2.0** — Google token → auto-create user if first login
5. **FACE ID** — Register face descriptor after login, then use for subsequent logins
6. **PASSWORD RESET** — Token-based email link (5-minute expiry, single use)

---

## 7. User Roles & Permissions

The system supports 5 roles aligned to the current `user_role_v2` enum:

| Role | Primary Use Case | Key Capabilities |
|------|------------------|------------------|
| **user** | Regular customer | Browse vehicles, create bookings, manage orders, write reviews, community posts, earn loyalty points |
| **driver** | Professional driver | View assigned vehicle, manage active trips, advance trip status, receive dispatch notifications |
| **callcenterstaff** | Call center operator | Search/create customers, create phone booking requests, cancel/delete own requests |
| **controlstaff** | Operations staff | Approve/reject booking requests, dispatch drivers to vehicles, manage fleet, send invoices |
| **admin** | Platform administrator | Full platform access: all staff capabilities + manage users, promotions, hero slides |

### Permission Matrix

| Feature | User | Driver | CallCenter | Control | Admin |
|---------|------|--------|------------|---------|-------|
| Browse vehicles | ✅ | ✅ | ✅ | ✅ | ✅ |
| Create booking (web) | ✅ | ❌ | ❌ | ❌ | ✅ |
| Create phone booking | ❌ | ❌ | ✅ | ❌ | ✅ |
| Create customer account | ❌ | ❌ | ✅ | ❌ | ✅ |
| Accept/advance trips | ❌ | ✅ | ❌ | ❌ | ❌ |
| Approve booking requests | ❌ | ❌ | ❌ | ✅ | ✅ |
| Dispatch drivers | ❌ | ❌ | ❌ | ✅ | ✅ |
| Manage fleet vehicles | ❌ | ❌ | ❌ | ✅ | ✅ |
| Manage users | ❌ | ❌ | ❌ | ❌ | ✅ |
| Manage promotions/slides | ❌ | ❌ | ❌ | ❌ | ✅ |
| Community access | ✅ | ✅ | ✅ | ✅ | ✅ |
| Modify own orders | ✅ | ❌ | ❌ | ❌ | ✅ |
| Write reviews | ✅ | ❌ | ❌ | ❌ | ❌ |

---

## 8. Core Business Flows

### 8.1 Minicab Booking Flow (Web — Customer)

```
Step 1: Select service type (Local, Long-distance, Airport, Hotel, Daily Hire)
Step 2: Choose ride tier (Eco, Standard, Luxury) + seats (4 or 7)
Step 3: Enter pickup/destination, date/time, distance
Step 4: Apply promo code (optional) → calculate fare
Step 5: Select payment (Cash, PayPal, Account Balance)
Step 6: Submit → booking created (status: pending)
Step 7: Control Staff approves → auto-assigns driver+vehicle → invoice emailed
Step 8: Driver advances: on_route → on_trip (dispatch email sent) → completed
Step 9: Customer reviews → loyalty points awarded
```

### 8.2 Phone Booking Flow (Call Center Staff)

```
Step 1: Customer calls → staff searches/creates customer account
Step 2: Staff fills booking form (tier, seats, pickup, destination, service type)
Step 3: System checks vehicle availability (time-window conflict detection)
Step 4: Booking created as "pending" → sent to Control Staff queue
Step 5: Control Staff reviews and approves/rejects
Step 6: On approval: driver+vehicle assigned, invoice emailed, trip starts
```

### 8.3 Driver Trip Lifecycle

```
Control Staff dispatches driver → assigns vehicle
Order approved → driver receives notification
Driver: on_route → (30-min before pickup window) → Start Trip
Driver: on_trip → dispatch email sent to customer
Driver: completed → vehicle released, booking archived
```

### 8.4 Fare Calculation

**Minicab (per mile):**
| Seats | Eco | Standard | Luxury |
|-------|-----|----------|--------|
| 4 | £2.00/mi | £2.50/mi | £3.50/mi |
| 7 | £3.00/mi | £3.50/mi | £4.50/mi |

**Phone Booking (per mile + £2.00 booking fee):**
| Seats | Eco | Standard | Premium |
|-------|-----|----------|---------|
| 4 | £2.50/mi | £3.00/mi | £4.00/mi |
| 7 | £3.00/mi | £3.50/mi | £5.00/mi |

**Daily Hire (flat rate):**
| Seats | Eco | Standard | Luxury |
|-------|-----|----------|--------|
| 4 | £180 | £220 | £300 |
| 7 | £220 | £270 | £400 |

### 8.5 Booking Modification Rules

- Only **pending** + **cash** + **minicab** bookings can be modified
- Must be **24+ hours before pickup**
- Editable fields: pickup, destination, service type, date/time, tier, seats
- Pickup date can only move within **±7 days** from original
- Fare auto-recalculates on distance/tier/seats changes

### 8.6 Cancellation & Refund

- Online payments (PayPal, Account Balance) → auto-refund to account balance
- PayPal payments → refund via PayPal Capture Refund API
- Cash bookings → no refund needed
- Renters must cancel **24+ hours before pickup**

---

## 9. Real-time Notifications

### Notification System Architecture

```
Application Event (Booking created, Payment processed, etc.)
         │
         ├─→ createNotification()  [api/notification-helpers.php]
         │     → INSERT into notifications table
         │
         ├─→ notifyVehicleAvailabilitySubscribers()
         │     → Notify users subscribed to vehicle availability
         │
         └─→ createDriverNotification()  [BookingRepository]
               → INSERT into driver_notifications table
         
Frontend Polling: GET /api/notifications.php?action=unread-count (every 15s)
```

### Notification Types

| Type | Trigger | Recipient |
|------|---------|-----------|
| `booking` | New booking, status change, trip confirmed by staff | Renter & Owner |
| `payment` | Payment confirmation, refund processed | Renter |
| `promo` | Loyalty points earned | User |
| `system` | Password changed, profile updated | User |
| `alert` | Booking cancelled, driver nearby | User |
| `dispatch` | Driver dispatched, order assigned | Driver |
| `dispatch_assignment` | New order assigned to driver | Driver |
| `order_status_update` | Order status changed by staff | Driver |

---

## 10. Image Storage & Delivery

Images are stored in **Supabase Storage** bucket `DriveNow`:

```
DriveNow/
├── vehicles/{vehicle_id}/      # Vehicle images
├── avatars/                     # User avatars
├── hero-slides/                 # Homepage banners
└── community/                   # Community post images
```

**CDN URL Format:**
```
https://{project_id}.supabase.co/storage/v1/object/public/DriveNow/vehicles/{vehicle_id}/image.jpg
```

| File Type | Max Size | Format |
|-----------|----------|--------|
| Vehicle images | 5 MB | JPEG, PNG, WebP, GIF |
| User avatars | 3 MB | JPEG, PNG, WebP |
| Hero slides | 10 MB | JPEG, PNG, WebP |
| Community posts | 5 MB | JPEG, PNG, WebP, GIF |

---

## 11. Payments & Invoicing

### Payment Methods

| Method | Flow | Refund |
|--------|------|--------|
| **Cash** | Pay at pickup, marked paid on completion | No refund needed |
| **PayPal** | Create order → redirect to PayPal → capture on return | Refund via PayPal Capture Refund API |
| **Account Balance** | Deduct from `users.account_balance` | Credit back to account balance |

### PayPal Integration (`lib/payments/PayPalGateway.php`)

- Supports **sandbox** and **live** mode via `PAYPAL_MODE` env var
- **Mock mode** (`PAYPAL_MOCK_ENABLED=true`) for development without PayPal credentials
- Operations: `createOrder()`, `captureOrder()`, `markCancelled()`, `refundCapture()`

### Invoice Generation (`Invoice/`)

- **mPDF** generates A4 PDF invoices with booking details
- Automatically emailed when Control Staff confirms an order (pending → in_progress)
- Contains: customer info, vehicle details, pickup/destination, pricing summary

---

## 12. AI Chatbot Integration

```
Client (JS) → chatbot-with-memory.php (proxy) → n8n Server (AI Agent)
                                                         │
                                                         ▼
                                                 PostgreSQL table:
                                                 n8n_chat_histories
```

Set `N8N_WEBHOOK_URL` in `.env` to connect to your n8n AI Agent webhook.

---

## 13. Environment Configuration

### `.env` File Template

```bash
# Database (Supabase PostgreSQL)
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=6543
DB_NAME=postgres
DB_USER=postgres.your_project
DB_PASSWORD=your_password
DB_SSL_MODE=require

# Supabase Storage
SUPABASE_URL=https://your_project.supabase.co
SUPABASE_SERVICE_KEY=your_service_role_key

# Email (Gmail SMTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=your@gmail.com
SMTP_FROM_NAME=PrivateHire

# N8N (AI Chatbot - optional)
N8N_WEBHOOK_URL=http://localhost:5678/webhook/your-webhook-id

# Mem0 (optional)
MEM0_API_KEY=your_mem0_api_key

# PayPal (Sandbox)
PAYPAL_CLIENT_ID=your_paypal_sandbox_client_id
PAYPAL_SECRET=your_paypal_sandbox_secret
PAYPAL_MODE=sandbox
PAYPAL_BASE_URL=https://api-m.sandbox.paypal.com
PAYPAL_MOCK_ENABLED=true

# Google Maps (optional)
GOOGLE_MAPS_API_KEY=your_google_maps_api_key

# App configuration
APP_ENV=development
APP_DEBUG=true
```

---

## 14. Running Locally

### Prerequisites

- **PHP 8.2+** with extensions: `pdo_pgsql`, `mbstring`, `openssl`, `json`, `curl`
- **Composer** (for PHP dependencies)
- **PostgreSQL database** (Supabase account recommended)

### Setup Steps

```bash
# 1. Clone the repository
git clone https://github.com/DK0310/Scrum_App.git
cd Scrum_App

# 2. Install PHP dependencies
composer install

# 3. Configure environment
cp .env.example .env
# Edit .env with your credentials

# 4. Initialize database
psql -h your_host -p 6543 -U postgres.your_project -d postgres -f Database/schema.sql

# 5. Start development server
php -S localhost:8000

# 6. Open browser
# http://localhost:8000
```

### Default Accounts (from seed data)

| Email | Password | Role |
|-------|----------|------|
| `admin1@drivenow.local` | `admin123` | admin |
| `controlstaff1@drivenow.local` | `staff123` | controlstaff |
| `callcenterstaff1@drivenow.local` | `staff123` | callcenterstaff |

---

## 15. Mobile App Integration Guide

All PHP REST APIs are accessible from mobile apps with CORS headers:
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, GET, OPTIONS
Access-Control-Allow-Headers: Content-Type
```

Use cookie jar for session management. Images are served via Supabase CDN (no session needed).

### API Response Conventions

```json
// Success
{ "success": true, "message": "...", "data": { ... } }

// Error
{ "success": false, "message": "Error description" }

// Auth required
{ "success": false, "message": "Authentication required.", "require_login": true }

// Force logout
{ "success": false, "message": "...", "force_logout": true }
```

---

## 16. ERD

```
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│      users       │       │    vehicles       │       │  vehicle_images  │
├──────────────────┤       ├──────────────────┤       ├──────────────────┤
│ id (UUID) PK     │──┐    │ id (UUID) PK     │──┐    │ id (UUID) PK     │
│ email            │  │    │ owner_id (FK)  ───┼──┘    │ vehicle_id (FK)──┤
│ phone            │  │    │ brand, model      │       │ storage_path     │
│ role (5 roles)   │  │    │ year, category    │       │ mime_type        │
│ full_name        │  │    │ service_tier      │       │ file_size        │
│ password_hash    │  │    │ seats, capacity   │       │ sort_order       │
│ face_descriptor  │  │    │ price_per_day     │       └──────────────────┘
│ membership       │  │    │ location_city     │
│ account_balance  │  │    │ status (enum)     │       ┌──────────────────┐
│ assigned_vehicle │  │    │ avg_rating        │       │    bookings      │
│ loyalty_point    │  │    │ total_bookings    │       ├──────────────────┤
│ createdbystaff   │  │    └──────────────────┘       │ id (UUID) PK     │
└──────────────────┘  │                                │ renter_id (FK)───┤
                      ├───────────────────────────────→│ vehicle_id (FK)  │
                      │                                │ owner_id (FK)    │
                      │    ┌──────────────────┐       │ driver_id (FK)   │
                      │    │    payments       │       │ booking_type     │
                      │    ├──────────────────┤       │ service_type     │
                      │    │ id (UUID) PK     │       │ ride_tier        │
                      │    │ booking_id (FK)──┼──────→│ pickup_date/time │
                      ├───→│ user_id (FK)     │       │ total_amount     │
                      │    │ amount, method   │       │ status (enum)    │
                      │    │ payment_details  │       │ ride_started_at  │
                      │    │ status (enum)    │       │ ride_completed_at│
                      │    └──────────────────┘       └──────────────────┘
                      │                                        │
                      │    ┌──────────────────┐       ┌────────┴─────────┐
                      │    │  active_trips    │       │ booking_archive  │
                      │    ├──────────────────┤       ├──────────────────┤
                      ├───→│ driver_id (FK)   │       │ booking_id (UQ)  │
                      │    │ booking_id (FK)──┼──────→│ status, region   │
                      │    │ vehicle_id (FK)  │       │ booking_payload  │
                      │    │ status           │       │ archived_at      │
                      │    │ pickup/dest lat  │       └──────────────────┘
                      │    │ driver lat/lng   │
                      │    └──────────────────┘       ┌──────────────────┐
                      │                                │   reviews        │
                      │    ┌──────────────────┐       ├──────────────────┤
                      │    │  notifications   │       │ user_id (FK)─────┤
                      ├───→│ user_id (FK)     │       │ vehicle_id (FK)  │
                      │    │ type (enum)      │       │ booking_id (FK)  │
                      │    │ title, message   │       │ rating (1-5)     │
                      │    │ is_read          │       │ title, content   │
                      │    └──────────────────┘       └──────────────────┘
                      │
                      │    ┌──────────────────┐       ┌──────────────────┐
                      │    │ driver_notifs    │       │vehicle_assignments│
                      │    ├──────────────────┤       ├──────────────────┤
                      ├───→│ driver_id (FK)   │       │ staff_id (FK)    │
                      │    │ booking_id (FK)  │       │ driver_id (FK)   │
                      │    │ title, message   │       │ vehicle_id (FK)  │
                      │    │ notif_type       │       │ assigned_date    │
                      │    └──────────────────┘       └──────────────────┘
                      │
                      │    ┌──────────────────┐       ┌──────────────────┐
                      │    │ community_posts  │       │   promotions     │
                      │    ├──────────────────┤       ├──────────────────┤
                      ├───→│ user_id (FK)     │       │ code UNIQUE      │
                      │    │ title, content   │       │ discount_type    │
                      │    │ category         │       │ discount_value   │
                      │    │ likes_count      │       │ min_booking_days │
                      │    └──────────────────┘       │ max_uses         │
                      │                                │ expires_at       │
                      │    ┌──────────────────┐       └──────────────────┘
                      │    │password_reset    │
                      │    │  _tokens         │       ┌──────────────────┐
                      │    ├──────────────────┤       │  hero_slides     │
                      ├───→│ user_id (FK)     │       ├──────────────────┤
                      │    │ token_hash       │       │ storage_path     │
                      │    │ expires_at       │       │ title, subtitle  │
                      │    │ is_used          │       │ sort_order       │
                      │    └──────────────────┘       │ is_active        │
                      │                                │ created_by (FK)  │
                      │    ┌──────────────────┐       └──────────────────┘
                      │    │customer_enquiries│
                      │    ├──────────────────┤
                      └───→│ customer_id (FK) │
                           │ enquiry_type     │
                           │ content, status  │
                           └──────────────────┘
```

---

## License

This project is developed as part of a Scrum learning exercise. All rights reserved.

---

*Last updated: April 7, 2026*
*Platform Name: Private Hire — Premium Car Rental & Transportation*
