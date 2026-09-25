# Balance payment deadline workflow

## Policy

- A verified 50% deposit confirms a reservation.
- The remaining balance is due 24 hours before the scheduled arrival time.
- An unpaid booking enters a 6-hour grace period, then moves to `cancellation_review`.
- Cancellation is manual by default. Staff review the booking, use the No-Show / Cancel action, and the room is released only after confirmation.
- Check-in is rejected server-side while a balance remains unless the balance payment is collected in the same check-in transaction.

The values are stored in `settings` as `deposit_percentage`, `balance_due_hours_before_checkin`, `balance_grace_period_hours`, and `auto_cancel_unpaid_balance`. Automatic cancellation is intentionally disabled.

## Guest flow

The guest opens `frontend/balance_payment.php` using the booking's private cancellation token. The page shows the authoritative total, verified payments, remaining balance, and deadline. It creates or resumes one PayMongo checkout for the exact remaining balance. The signed PayMongo webhook and provider retrieval use the existing checkout confirmation service; browser redirects alone cannot verify a payment.

## Reminders and overdue processing

Run this command every 15–30 minutes with Windows Task Scheduler or the production scheduler:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\SantaBeachClub-BookingSystem\backend\cron\process_balance_deadlines.php
```

The job sends tracked reminder/final-reminder emails, marks elapsed deadlines overdue, sends the grace-period notice, and creates an administrator notification when cancellation review is required. Runs are idempotent and never automatically cancel a booking.

The existing pre-arrival email also includes the exact deadline and secure balance-payment link.

## Staff flow

1. Open Admin Check-in and review the balance state and deadline.
2. For `overdue`, contact the guest during the grace period.
3. For `cancellation review`, either accept verified payment or use the cancellation action with the nonpayment reason.
4. Cancellation marks the booking cancelled and releases its assigned room.
5. A refund request is created only when the cancellation policy produces a positive refund. Zero-value requests cannot be submitted, approved, emailed, or recorded with PayMongo.

## Deployment

Run the forward-only migration before serving the updated application:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\SantaBeachClub-BookingSystem\backend\scripts\migrate.php
```

Configure `PUBLIC_APP_URL`, PayMongo keys/webhook signing secret, and Gmail SMTP credentials. Test a deposit, balance checkout, signed webhook, reminder, overdue transition, manual cancellation, and zero-refund rejection in a staging account before using live payments.
