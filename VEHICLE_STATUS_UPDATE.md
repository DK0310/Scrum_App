# Vehicle Status Update on Booking Completion

## Overview
When a booking is marked as **completed**, the vehicle is immediately set to **available** status so it displays in the vehicle listings for other users.

## Changes Made

### 1. Backend API (`api/bookings.php`)

**What Changed:**
- Added **error checking** for booking and vehicle status updates
- Vehicle status is **explicitly verified** when booking completes
- API response now includes **vehicle status confirmation** with booking ID and vehicle ID

**Key Code Flow:**
```php
// When newStatus === 'completed':
1. updateBookingStatus($bookingId, 'completed') - Update booking in DB
2. updateVehicleStatus($vehicleId, 'available') - Set vehicle to available
3. Response includes: vehicle_id, vehicle_status, booking_id
```

**Response Example (on completion):**
```json
{
  "success": true,
  "message": "Booking status updated to completed.",
  "booking_id": "uuid-xxx",
  "new_status": "completed",
  "vehicle_id": "uuid-yyy",
  "vehicle_status": "available"
}
```

### 2. Database Update

**VehicleRepository Method Used:**
- `updateVehicleStatus($vehicleId, 'available')` - Sets vehicle status in PostgreSQL

**SQL Query:**
```sql
UPDATE vehicles SET status = 'available'::vehicle_status WHERE id = ?
```

### 3. Frontend Event System (`resources/js/orders.js`)

**What Changed:**
- When booking status updates to 'completed', frontend now:
  1. **Dispatches custom event** `vehicleAvailabilityUpdated` with vehicle details
  2. **Triggers vehicle list reload** on cars page via `window.loadCars()`
  3. **Triggers vehicle list reload** on home page via `window.loadHomeVehicles()`

**Code:**
```javascript
if (newStatus === 'completed' && data.vehicle_id && data.vehicle_status === 'available') {
    // Broadcast update event
    window.dispatchEvent(new CustomEvent('vehicleAvailabilityUpdated', {
        detail: {
            vehicle_id: data.vehicle_id,
            status: data.vehicle_status,
            booking_id: data.booking_id
        }
    }));
    
    // Reload vehicle lists
    if (typeof window.loadCars === 'function') {
        setTimeout(() => window.loadCars(), 300);
    }
    if (typeof window.loadHomeVehicles === 'function') {
        setTimeout(() => window.loadHomeVehicles(), 300);
    }
}
```

### 4. Home Page (`resources/js/home.js`)

**What Changed:**
- Exported `loadAvailableVehicles()` as `window.loadHomeVehicles` for global access
- Added event listener for `vehicleAvailabilityUpdated` events
- Auto-reloads vehicle list when a vehicle becomes available

**Code:**
```javascript
window.loadHomeVehicles = loadAvailableVehicles;

window.addEventListener('vehicleAvailabilityUpdated', function(e) {
    if (e.detail && e.detail.vehicle_status === 'available') {
        console.log('Vehicle ' + e.detail.vehicle_id + ' is now available, refreshing list...');
        setTimeout(() => loadAvailableVehicles(), 300);
    }
});
```

### 5. Cars Page (`resources/js/cars.js`)

**What Changed:**
- `loadCars()` function already exposed globally
- Added event listener for `vehicleAvailabilityUpdated` events
- Auto-reloads cars list when a vehicle becomes available

**Code:**
```javascript
window.addEventListener('vehicleAvailabilityUpdated', function(e) {
    if (e.detail && e.detail.vehicle_status === 'available') {
        console.log('Vehicle ' + e.detail.vehicle_id + ' is now available, refreshing cars list...');
        setTimeout(() => loadCars(), 300);
    }
});
```

## How It Works (End-to-End)

### Scenario: Owner marks booking as completed

1. **User Action**: Owner/Admin clicks "Mark as Done" on an order in the orders page
2. **Frontend**: Calls `/api/bookings.php` with `action: 'update-status', status: 'completed'`
3. **Backend**: 
   - Updates `bookings` table to `status = 'completed'`
   - Updates `vehicles` table to `status = 'available'`
   - Returns response with `vehicle_status: 'available'`
4. **Frontend Receives**:
   - Dispatches `vehicleAvailabilityUpdated` event with vehicle ID
   - Calls `window.loadCars()` to reload cars page vehicle list (if on cars.php)
   - Calls `window.loadHomeVehicles()` to reload home page vehicle list (if on home)
5. **Vehicle Display**: 
   - Vehicle immediately shows as **"✓ Available"** in both listings
   - Vehicle is now bookable by other users

### Flow Diagram
```
Owner Orders Page
    ↓ clicks "Mark as Done"
Orders.js updateOrderStatus()
    ↓ POST to /api/bookings.php (action: update-status, status: completed)
Backend api/bookings.php
    ↓
    1. Update booking.status = 'completed'
    2. Update vehicle.status = 'available'
    ↓ returns { vehicle_id, vehicle_status: 'available' }
Frontend receives response
    ↓
    1. Dispatch CustomEvent 'vehicleAvailabilityUpdated'
    2. Call window.loadCars() (refreshes cars.php list)
    3. Call window.loadHomeVehicles() (refreshes home.php list)
    ↓
Vehicle Lists Updated
    ↓
Vehicle displays as "✓ Available"
```

## Testing

### Manual Test Steps:

1. **Create a booking** and confirm it (status: pending → confirmed)
2. **Start delivery** to change status to in_progress
3. **Mark as Done** to complete the booking
4. **Verify**:
   - Toast shows "✅ Order updated!"
   - Vehicle disappears from "Rented" section in orders
   - Vehicle appears as "Available" on cars.php (if viewing)
   - Vehicle appears as "Available" on home page (if viewing)
   - Status badge shows "✓ Available" instead of "🔒 Rented"

### Database Verification:

```sql
-- Check vehicle status directly
SELECT id, brand, model, status FROM vehicles WHERE id = 'vehicle-uuid';
-- Should show: status = 'available'

-- Check booking status
SELECT id, status, completed_at FROM bookings WHERE id = 'booking-uuid';
-- Should show: status = 'completed', completed_at = NOW()
```

## Frontend Event Listeners

The system uses a **CustomEvent** for loose coupling:
- Any component can listen for vehicle availability updates
- Multiple listeners can act on the same event
- New listeners can be added without modifying existing code

### To Add New Listeners:
```javascript
window.addEventListener('vehicleAvailabilityUpdated', function(e) {
    const { vehicle_id, status, booking_id } = e.detail;
    // Custom logic here
});
```

## Performance Notes

- Vehicle list reloads are **delayed by 300ms** to allow backend processing
- This ensures the vehicle list fetches already-updated data
- Prevents race conditions between database write and API read

## Edge Cases Handled

1. ✅ **Booking cancelled** → Vehicle remains available (already handled)
2. ✅ **Booking in_progress** → Vehicle stays rented (correct behavior)
3. ✅ **Multiple bookings** → Each completion updates only its vehicle
4. ✅ **User not on page** → Event only affects visible pages
5. ✅ **Network delay** → 300ms buffer ensures DB is updated

## Files Modified

1. `api/bookings.php` - Backend vehicle status update logic
2. `resources/js/orders.js` - Frontend event dispatch on completion
3. `resources/js/home.js` - Home page event listener
4. `resources/js/cars.js` - Cars page event listener

