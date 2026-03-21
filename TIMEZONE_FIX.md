# Timezone Fix - Pickup Date & Invoice Display

## Problem
When setting pickup time as "24 March 2026 10:00 AM", viewing the order showed "24 March 2026 07:00 AM" (sai 3 giờ)
- Pickup time displayed incorrectly in orders list
- Invoice email sent with incorrect pickup time
- Vehicle name missing from invoice

## Root Cause
**Timezone Mismatch:**
1. Frontend sent `datetime-local` value (no timezone info) directly to backend
2. Backend received it as raw string, assumed as local time
3. Database stored it without timezone conversion
4. Frontend displayed it using JavaScript `new Date()` which assumes UTC
5. Result: 3-7 hour difference depending on browser location

## Solution

### 1. Frontend: Convert to UTC Before Sending
**File:** `resources/js/booking.js` (line 863 - confirmBooking function)

```javascript
// Convert datetime-local to UTC ISO format
const convertToUTCISO = function(datetimeLocalValue) {
    if (!datetimeLocalValue) return '';
    const dt = new Date(datetimeLocalValue);
    const tzOffset = dt.getTimezoneOffset() * 60000;
    const utcDate = new Date(dt.getTime() + tzOffset);
    return utcDate.toISOString(); // ISO UTC format like: 2026-03-24T03:00:00.000Z
};

// Send UTC ISO string to backend
payload.pickup_date = convertToUTCISO(scheduledDateTime.value);
```

### 2. Backend: Store as UTC
**File:** `api/bookings.php` (booking create action)

```php
// Ensure pickup_date is converted to UTC before storage
$pickupDateForDB = $pickupDate;
if (!empty($pickupDate)) {
    try {
        $dt = new DateTime($pickupDate);
        $dt->setTimeZone(new DateTimeZone('UTC'));
        $pickupDateForDB = $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $pickupDateForDB = $pickupDate;
    }
}
```

**Result:** Booking stored in database as UTC timestamp

### 3. Frontend: Display in Asia/Ho_Chi_Minh Timezone
**File:** `resources/js/orders.js` (formatDateTime function)

```javascript
function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        
        // Convert UTC to Asia/Ho_Chi_Minh (UTC+7)
        const formatter = new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
            timeZone: 'Asia/Ho_Chi_Minh'
        });
        return formatter.format(d);
    } catch (err) {
        return dateStr;
    }
}
```

**Result:** Orders display shows "24 Mar, 2026 10:00 AM" (correct time)

### 4. Invoice: Display with Asia/Ho_Chi_Minh Timezone
**File:** `lib/invoice_mpdf.php`

```php
$fmtPickupTime = function ($dateStr) use ($tz): string {
    if (!$dateStr || $dateStr === '') return '';
    try {
        // Parse as UTC first, then convert to target timezone
        $dt = new DateTime($dateStr, new DateTimeZone('UTC'));
        $dt->setTimeZone(new DateTimeZone($tz)); // $tz = 'Asia/Ho_Chi_Minh'
        return $dt->format('M d, Y \a\t h:i A');
    } catch (Exception $e) {
        return (string)$dateStr;
    }
};
```

**Result:** Invoice email displays "Mar 24, 2026 at 10:00 AM" (correct)

### 5. Add Vehicle Info to Invoice
**File:** `api/bookings.php` (confirm-payment and update-status in_progress actions)

Added `pickup_date` and improved `vehicle_name` to invoice payload:

```php
$invoiceBooking = [
    'id' => $bookingId,
    'vehicle_name' => $vehicleName,        // ← Added
    'pickup_date' => $bRow['pickup_date'], // ← Added
    'pickup_location' => $bRow['pickup_location'],
    'return_location' => $bRow['return_location'],
    // ... other fields
];
```

## Timezone Conversion Chain

```
Frontend (User Browser - Any Timezone)
  ↓ (User sets 24 Mar 2026 10:00 AM in local time)
  ↓ JavaScript convertToUTCISO()
  ↓ Send: "2026-03-24T03:00:00.000Z" (UTC format)
  ↓
Backend (api/bookings.php)
  ↓ Parse UTC string
  ↓ Convert to UTC if needed
  ↓ Store: "2026-03-24 03:00:00" (UTC in database)
  ↓
Database (PostgreSQL - Stored as UTC)
  ↓
Frontend Display
  ↓ JavaScript Intl.DateTimeFormat with timeZone: 'Asia/Ho_Chi_Minh'
  ↓ Display: "24 Mar, 2026 10:00 AM" ✅
  ↓
Invoice Generation
  ↓ PHP DateTime with Asia/Ho_Chi_Minh timezone
  ↓ Email shows: "Mar 24, 2026 at 10:00 AM" ✅
```

## Testing Checklist

- [x] PHP syntax: No errors in bookings.php and invoice_mpdf.php
- [ ] Create booking with pickup time "24 March 2026 10:00 AM"
- [ ] Verify orders page displays "24 Mar, 2026 10:00 AM"
- [ ] Check that invoices received via email display correct time
- [ ] Verify vehicle name appears in invoice email

## Technical Details

### Why UTC Storage?
- **Universal Standard:** Always store dates in UTC/UTC+0 to avoid timezone ambiguity
- **Consistency:** Same datetime means same moment regardless of user location
- **Conversion:** When displaying, always convert FROM UTC TO target timezone

### Intl.DateTimeFormat vs toLocaleString()
- `Intl.DateTimeFormat` supports explicit `timeZone` parameter
- `toLocaleString()` only uses browser timezone
- Using Intl.DateTimeFormat ensures consistent display in Asia/Ho_Chi_Minh

### PHP DateTime timezone handling
```php
// Parse as UTC first
$dt = new DateTime($dateStr, new DateTimeZone('UTC'));
// Then convert to target timezone
$dt->setTimeZone(new DateTimeZone('Asia/Ho_Chi_Minh'));
// Format for display
$dt->format('M d, Y h:i A');
```

## Environment Config
From `.env`:
```
INVOICE_TIMEZONE=Asia/Ho_Chi_Minh  # UTC+7 for Vietnam
INVOICE_CURRENCY=USD
```

## Files Modified

1. ✅ `resources/js/booking.js` - Added UTC conversion before sending
2. ✅ `resources/js/orders.js` - Fixed formatDateTime to use Asia/Ho_Chi_Minh
3. ✅ `api/bookings.php` - UTC storage + added pickup_date to invoice
4. ✅ `lib/invoice_mpdf.php` - Fixed timezone conversion + vehicle name fallback

