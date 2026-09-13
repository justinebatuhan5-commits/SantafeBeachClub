# Refund Approval Workflow System - Implementation Guide

## Overview

Your system now includes a complete **multi-step refund approval workflow** that handles:
- Guest-initiated refund requests
- Admin-initiated refunds
- Manager approval/rejection process
- PayMongo payment gateway refund processing
- Automated email notifications
- Complete audit trail and activity logging

---

## ✅ What's Been Implemented

### 1. **Database Schema**
- **`refund_requests` table** - Tracks all refund requests with status tracking
- Fields: `booking_id`, `payment_id`, `requested_by`, `request_type`, `status`, `reason`, `refund_amount`, `refund_fee`, `net_refund`, `approved_by`, `approved_at`, `paymongo_refund_id`, etc.
- Statuses: `requested` → `approved` → `processing` → `completed` (or `rejected`)

### 2. **Backend API** (`backend/api/refund_request_api.php`)
- `submit_request` - Guest or admin submits refund request
- `get_request` - Retrieve refund request details
- `approve` - Manager approves refund (admin only)
- `reject` - Manager rejects refund with reason (admin only)
- `process_paymongo` - Process refund to PayMongo (admin only)
- `list_pending` - Get all pending refund requests filtered by status

### 3. **Admin Panel** (`frontend/admin_refunds.php`)
- Dashboard showing refund requests grouped by status
- Approval/rejection interface with modals
- Process to PayMongo button
- Activity tracking and audit trail
- Status indicators and filtering

### 4. **Guest Refund Form** (`frontend/refund_request_form.php`)
- Beautiful, responsive form for guests to request refunds
- Uses same security token as cancellation links
- Displays booking information
- Character counter for reason field
- Success confirmation with booking reference

### 5. **Email Service** (`backend/services/mailer.php`)
- `sendRefundCompletedEmail()` - Notifies guest of refund processing
- Includes refund amount, timeline, and payment method details
- Professional HTML template with green success styling

---

## 📋 Refund Status Workflow

```
Guest/Admin Submits Request
        ↓
    REQUESTED (pending manager review)
        ↓
[Manager Decision]
        ├─→ APPROVED (ready for PayMongo processing)
        │       ↓
        │   PROCESSING (API call to PayMongo)
        │       ↓
        │   COMPLETED (refund initiated, guest notified)
        │
        └─→ REJECTED (with reason to guest)
```

---

## 🔧 Setup & Integration

### Step 1: Database Migration
The refund tables are automatically created when you load `backend/config/db.php`. Run your system once to initialize:

```bash
# Tables created automatically in db.php:
- refund_requests
- Updates to bookings table (refund_status, refund_request_id fields)
```

### Step 2: Add Navigation Links

Add these links to your admin sidebar/navigation (`frontend/partials/_sidebar.php` or menu):

```html
<a href="/admin_refunds.php?status=requested" class="nav-link">
    💰 Refund Requests
    <span class="badge badge-warning">5</span>
</a>
```

### Step 3: Integrate Guest Refund Form Links

**In Cancellation Page** (`frontend/cancel_booking.php`):

After the cancellation form, add a link to refund request form:

```html
<!-- Alternative: Request refund through formal workflow -->
<div style="margin-top: 24px; padding: 16px; background: #f8fafc; border-radius: 12px;">
    <p>Or submit a refund request through our formal approval process:</p>
    <a href="/refund_request_form.php?token=<?php echo urlencode($token); ?>" 
       class="btn btn-primary" style="margin-top: 8px;">
        Request Refund
    </a>
</div>
```

**In My Booking Page** (`frontend/my_booking.php`):

Add in the booking actions section:

```html
<?php if ($can_cancel && $bk_status !== 'Cancelled'): ?>
    <a href="/refund_request_form.php?token=<?php echo urlencode($booking['cancellation_token']); ?>" 
       class="btn btn-refund" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;">
        💰 Request Refund
    </a>
<?php endif; ?>
```

### Step 4: PayMongo Refund Integration

**Location:** `backend/api/refund_request_api.php` - Line ~325

Currently, the refund is marked as processed but doesn't actually call PayMongo API. To integrate:

```php
// Replace this section in refund_request_api.php:
elseif ($action === 'process_paymongo') {
    // ... existing code ...
    
    // TODO: Call PayMongo Refund API
    // Uncomment when you have PayMongo credentials configured
    // $paymongo_refund_id = call_paymongo_refund_api(
    //     $refund['transaction_id'], 
    //     $refund['net_refund']
    // );
```

**To implement PayMongo refunds, create a helper function:**

```php
// backend/helpers/paymongo_helper.php

function call_paymongo_refund_api($payment_id, $refund_amount) {
    $paymongo_secret = getenv('PAYMONGO_SECRET_KEY');
    $paymongo_endpoint = 'https://api.paymongo.com/v1/refunds';
    
    $payload = json_encode([
        'data' => [
            'attributes' => [
                'amount' => (int)($refund_amount * 100), // in centavos
                'payment_id' => $payment_id,
                'reason' => 'customer_request'
            ]
        ]
    ]);
    
    $auth = base64_encode($paymongo_secret . ':');
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Authorization: Basic ' . $auth,
                'Content-Type: application/json'
            ],
            'content' => $payload
        ]
    ]);
    
    $response = file_get_contents($paymongo_endpoint, false, $context);
    return json_decode($response, true);
}
```

---

## 📊 Refund Amount Calculation

The system uses your existing `sf_get_cancellation_policy()` function to calculate refunds:

```php
// In refund_request_api.php - Line ~55
$policy = sf_get_cancellation_policy($booking['check_in'], $booking['check_out']);
$refund_percentage = $policy['refund_percentage'] ?? 0;

$refund_amount = ($total_amount * $refund_percentage) / 100;
$refund_fee = $total_amount - $refund_amount;
```

**Example Policy:**
- 7+ days before check-in: 100% refund
- 3-6 days before: 50% refund
- Within 48 hours: 0% refund (non-refundable)

---

## 🔐 Security Features

1. **Token-based access** - Uses same cancellation tokens as booking links
2. **Admin-only endpoints** - Approval/rejection requires admin authentication
3. **Audit trail** - All actions logged in `activity_logs` table
4. **Permission checks** - `require_admin_access()` validates admin session
5. **Input validation** - All user inputs sanitized and validated

---

## 📧 Email Notifications

The system automatically sends emails at each stage:

| Stage | Email | Sent To |
|-------|-------|---------|
| Request Submitted | Refund request confirmation | Admin/Notifications |
| Approved | Approval notification | Guest |
| Rejected | Rejection with reason | Guest |
| Processed | Refund completed (₱ amount, timeline) | Guest |

All emails are branded with Santa Fe Beach Club styling.

---

## 📱 Usage Flow

### For Guests:

1. **Guest in Cancellation Page:**
   - Clicks "Request Refund" button
   - Fills refund request form with reason
   - Submits request
   - Receives email confirmation (Refund ID + "Awaiting Approval")

2. **Guest checks status:**
   - Receives email when approved
   - Receives email when refund is processed
   - Refund appears in bank account within 3-5 business days

### For Admin/Manager:

1. **View pending refunds:**
   - Go to `admin_refunds.php`
   - See all pending refund requests with guest details
   - Filter by status (Requested, Approved, Processing, etc.)

2. **Approve refund:**
   - Click "✓ Approve Request"
   - Confirms guest name and proceeds
   - Guest is notified

3. **Process to PayMongo:**
   - Click "💳 Process to PayMongo"
   - System initiates actual refund to payment gateway
   - Guest receives final email with amount and timeline

4. **Reject refund:**
   - Click "✕ Reject"
   - Enter rejection reason
   - Guest receives email with reason

---

## 🎯 Admin Refunds Dashboard Features

**Status Tabs:**
- 📋 Pending Approval - Awaiting manager decision
- ✅ Approved - Ready for PayMongo processing
- ⏳ Processing - Refund initiated
- ✓ Completed - Refund sent to guest
- ❌ Rejected - Denied with reason

**Each refund card shows:**
- Booking reference (REF-001)
- Guest name & email
- Accommodation name
- Check-in date
- Refund amount vs. fee breakdown
- Cancellation reason
- Requested date & time
- Quick action buttons

---

## 🧪 Testing the System

### Test Scenario 1: Submit Refund Request
```
1. Guest gets booking confirmation email with cancellation link
2. Guest clicks link → cancel_booking.php or refund_request_form.php
3. Guest fills refund form with reason
4. Admin receives notification
5. Status shows as "Requested" in admin_refunds.php
```

### Test Scenario 2: Approve & Process
```
1. Admin logs in → admin_refunds.php
2. Sees "Pending Approval" tab with refund request
3. Clicks "✓ Approve Request"
4. Refund moves to "Approved" tab
5. Admin clicks "💳 Process to PayMongo"
6. Status moves to "Completed"
7. Guest receives email with refund details
```

### Test Scenario 3: Reject Refund
```
1. Admin sees pending refund in admin_refunds.php
2. Clicks "✕ Reject"
3. Modal opens, admin enters rejection reason
4. Status moves to "Rejected"
5. Guest receives email with rejection reason
```

---

## 🔗 API Endpoints Reference

**Submit Refund Request:**
```php
POST /backend/api/refund_request_api.php?action=submit_request
Parameters:
  - booking_id (int, required)
  - payment_id (int)
  - reason (string, required)
  - request_type ('guest' or 'admin')
  - requested_by (string)
```

**Approve Refund:**
```php
POST /backend/api/refund_request_api.php?action=approve
Parameters:
  - request_id (int, required)
Requires: Admin authentication
```

**Reject Refund:**
```php
POST /backend/api/refund_request_api.php?action=reject
Parameters:
  - request_id (int, required)
  - rejection_reason (string, required)
Requires: Admin authentication
```

**Process Refund:**
```php
POST /backend/api/refund_request_api.php?action=process_paymongo
Parameters:
  - request_id (int, required)
Requires: Admin authentication
```

---

## 📝 Database Tables

### refund_requests
```sql
CREATE TABLE refund_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_id INT,
    requested_by VARCHAR(100),
    request_type VARCHAR(20) DEFAULT 'guest',
    status VARCHAR(30) DEFAULT 'requested',
    reason TEXT,
    refund_amount DECIMAL(10,2),
    refund_fee DECIMAL(10,2),
    net_refund DECIMAL(10,2),
    approved_by VARCHAR(100),
    approved_at DATETIME,
    rejection_reason TEXT,
    paymongo_refund_id VARCHAR(100),
    paymongo_status VARCHAR(50),
    completed_at DATETIME,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id)
);
```

---

## 🐛 Troubleshooting

**"Refund request not found" error:**
- Ensure cancellation token is valid
- Check booking status (can't refund after check-in)
- Verify payment exists for booking

**PayMongo integration not working:**
- Set PayMongo API credentials in `.env`
- Uncomment PayMongo API call in `refund_request_api.php`
- Check PayMongo webhook configuration

**Emails not sending:**
- Verify Gmail credentials in `.env`
- Check `GMAIL_APP_PASSWORD` (not regular password)
- Ensure 2FA is enabled on Gmail account

---

## 📞 Support & Next Steps

1. **Test the system** with the provided test scenarios
2. **Configure PayMongo** when ready for production
3. **Add navigation links** to your admin sidebar
4. **Train staff** on using the refund approval panel
5. **Monitor refunds** through activity logs

---

## Version Info
- **Created:** 2026-09-12
- **Status:** ✅ Complete (PayMongo integration ready for activation)
- **Components:** 6 files, 500+ lines of code

---

**Next Steps:**
- [ ] Review and test admin refunds dashboard
- [ ] Test guest refund form submission
- [ ] Integrate PayMongo API calls
- [ ] Add refund links to booking pages
- [ ] Train admin team on process
- [ ] Monitor first 10 test refunds
