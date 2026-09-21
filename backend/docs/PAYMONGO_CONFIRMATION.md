# PayMongo confirmation

Both the return page and signed checkout webhook use `paymongo_confirmation.php`.
The stored session, booking metadata, payment amount in centavos, currency and
provider payment ID must match before a single payment row can be verified.
Confirmation, the provider ID claim, booking status, history and notification
are committed together. A unique provider ID prevents reuse across checkouts;
repeated callbacks for the same verified payment do not create duplicate records.

## Deployment

- The database user needs CREATE TABLE permission for `paymongo_checkouts` on
  first use, consistent with the application's existing schema bootstrap.
  Transactional tables must use InnoDB.
- Pending legacy PayMongo payments with a stored `cs_...` transaction ID are
  copied into the checkout mapping automatically. Already verified legacy rows
  whose checkout ID was overwritten cannot be reconstructed safely; their
  return links will not show a newly verified payment. Existing booking records
  remain available through My Booking.
- Subscribe the PayMongo webhook to `checkout_session.payment.paid`. Standalone
  `payment.paid` events are acknowledged without changing bookings because
  they do not contain the required checkout binding.
- Keep `PAYMONGO_WEBHOOK_SECRET` configured. API calls now verify TLS certificates;
  configure a trusted CA bundle in PHP if the host does not provide one.
- Exercise a PayMongo test checkout and callback before production deployment.
  Local regression tests do not initiate real provider payments.

## Tests

`php tests/paymongo-confirmation.php` tests provider-response validation.
`php tests/paymongo-confirmation.php --database` additionally uses local XAMPP
MySQL on port 3307 and temporary fixture tables to test confirmation and rollback.
It does not load the application's database bootstrap or contact PayMongo.
`php tests/paymongo-signature.php` covers webhook signature rejection.
