# Senior Citizen and PWD booking requests

The configured hotel policy is 20% off the entire booking subtotal, including the room's calculated surcharges and extra-guest charges. It replaces any promo discount. Staff must approve eligibility before any discounted payment is accepted. This feature does not introduce an additional VAT calculation or automatic cancellation/refund calculation.

## Guest and staff flow

1. On the payment step, choose Senior Citizen or PWD, name the eligible staying guest, upload a JPG/PNG ID (maximum 2 MB), and consent to verification. Submit the request without paying.
2. The room is held under the normal `Pending Payment` inventory status. A separate discount review status is shown in the private guest page and staff Discount Requests screen. No payment record is created before approval.
3. Admin/reception staff open **Discount Requests** from their sidebar. They can approve the 20% discount, request a clearer ID, or decline with a reason. Decisions and ID views are audited.
4. Approval emails a private booking/payment-options link. Staff can copy that link for Messenger or resend email. They may also create a PayMongo checkout before sending it. Email failure is reported; approval is retained so staff can retry.
5. The guest chooses PayMongo or a configured manual GCash/bank account. Only one pending payment attempt is allowed at a time. Manual receipts require staff confirmation of the actual account transaction; uploaded proof alone cannot confirm a booking. A rejected receipt can be resubmitted.
6. A verified 50% deposit confirms the booking. The same private page accepts the remaining balance, due one day before arrival. Approved prices are used by the check-in screens and prearrival totals. Check-in cannot bypass payment for these bookings.
7. A declined request can be accepted at the regular price by the guest, or cancelled without payment. Changed stay details require a new reviewed request; calendar date edits are blocked for these bookings.

## Agila deployment

- Upload all changed/new files, not only `frontend/book.php`. The shared database bootstrap creates three additive InnoDB tables: `booking_discounts`, `discount_payments`, and `discount_history`. Back up the database before deployment and ensure the app user has CREATE TABLE permission, as required by existing migrations.
- Both database connectors now use the configured `DB_*` environment settings. Configure these for the deployed application.
- Set `PUBLIC_APP_URL=https://santafbeachclub.com` (or the actual public frontend base URL). This controls private links and payment return URLs. For local testing, use `http://localhost/SantaBeachClub-BookingSystem/frontend`.
- Set `DISCOUNT_HOLD_HOURS=24` and `DISCOUNT_ID_RETENTION_DAYS=30`, or your approved hotel values. Holds end earlier at 13:30 on arrival day. Approval does not reset the original deadline.
- Configure `PRIVATE_STORAGE_DIR` outside the public web root. PHP needs permission to create `discount_ids` and `receipts` there. IDs are served only by the staff-authorized document endpoint, with no caching and an access audit.
- Configure SMTP and PayMongo credentials. Keep the existing signed `checkout_session.payment.paid` webhook enabled for this deployment. No new event subscription is required.
- Admins configure real GCash/bank payment instructions at the bottom of **Discount Requests**. The new guest page hides incomplete methods; it does not use the old checkout's placeholder bank details.
- Schedule `php /absolute/project/path/backend/scripts/discount_maintenance.php` every five minutes in Agila Cron. Expiry and retention also run on application requests, but cron ensures cleanup when the site is idle. Unpaid expired holds are cancelled and no longer block inventory.
- The public/guest pages use unguessable private links. Treat copied links as confidential; they permit viewing and paying for that booking. Guests never receive access to the stored ID image.
- Upload the root `discount_booking.php` compatibility entry as well as the frontend page. New private links target the actual frontend path and exchange their URL fragment for a session using a CSRF-protected POST. The address bar then shows only the booking number; copied links remain confidential. Previously shared query-token links may already exist in browser history or server logs.

## Payment exceptions

An already issued PayMongo checkout can still receive a late payment after a room hold expires. The system refuses to reclaim/confirm that room and raises a staff notification when its signed webhook arrives. Staff must investigate in PayMongo and arrange a refund or new booking. Refunds and provider-session cancellation are not automated by this feature.

Once a PayMongo attempt has been created, the guest continues that checkout rather than switching to a manual method. This prevents two simultaneous deposit routes. Resolve abandoned attempts with reception/provider support before making another payment.

The displayed first-night cancellation policy remains informational: staff reconcile deposits and refunds under the hotel's policy. Do not assume changing a booking status issues a provider refund.

## Verification

- `C:/xampp/php/php.exe tests/discount-workflow.php --database` runs temporary-table tests against local MySQL on port 3307. It never creates real bookings or calls PayMongo/SMTP.
- `C:/xampp/php/php.exe tests/paymongo-confirmation.php --database` checks provider identity, amounts, currency, duplicate events and rollback using temporary tables.
- `C:/xampp/php/php.exe tests/private-storage.php` checks private storage boundaries and upload provenance.
- After deploying, use PayMongo test credentials in an isolated staging environment to verify real checkout redirects, signed delivery, email delivery, and receipt/ID uploads before accepting live payments.
