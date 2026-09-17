# Santa Beach Club Booking System — Project Flowcharts

Based on the current PHP implementation. These diagrams summarize the main user journeys; they do not describe every validation or error handler. Open in a Markdown viewer with Mermaid support.

## 1. Online reservation and guest stay

```mermaid
flowchart TD
    A([Start]) --> B[Browse accommodations]
    B --> C[Choose room type, dates and guest count]
    C --> D{Rooms available?}
    D -- No --> C
    D -- Yes --> E[Enter guest details and arrival time]
    E --> F[Review price, discount and deposit]
    F --> G[Choose payment method and submit]
    G --> H{Details and availability valid?}
    H -- No --> I[Show error and revise booking]
    I --> C
    H -- Yes --> J[Save booking: Pending Payment]
    J --> K{Payment method?}
    K -- PayMongo Online --> L[Open secure checkout]
    L --> M{Payment confirmed?}
    M -- Cancelled or incomplete --> F
    M -- Yes --> Q[Verify payment and confirm booking]
    K -- Bank Deposit or GCash QR --> N[Record payment reference and receipt]
    N --> O[Staff reviews payment]
    O --> P{Staff verifies payment?}
    P -- Not yet --> O
    P -- Yes --> Q
    Q --> R[Send confirmation email and check-in QR]
    R --> S[Guest arrives; staff finds booking or scans QR]
    S --> T{Remaining balance?}
    T -- Yes --> U[Collect and record balance payment]
    T -- No --> V[Mark booking Checked In and room occupied]
    U --> V
    V --> W[Guest stay]
    W --> X[Staff checks guest out]
    X --> Y[Mark booking Checked Out and room maintenance]
    Y --> Z([Stay complete])
```

Manual payment evidence is collected in the booking form before the pending booking is saved; its review is shown separately for readability. PayMongo confirmation is handled by the return page and webhook. The staff checkout page also marks verified payment revenue as earned.

## 2. Guest portal, cancellation and refund request

```mermaid
flowchart TD
    A([Open My Booking]) --> B[Enter email and booking reference]
    B --> C{Matching booking?}
    C -- No --> B
    C -- Yes --> D[Send email OTP]
    D --> E{OTP verified?}
    E -- No --> D
    E -- Yes --> F[View booking, payment status and QR]
    F --> G{Guest action?}
    G -- View or download --> H[View details or download QR]
    G -- Rebook --> I[Open booking form with guest details]
    G -- Cancel --> J[Open secure cancellation link]
    J --> K{Booking eligible for cancellation?}
    K -- No --> L[Display unavailable cancellation]
    K -- Yes --> M[Submit cancellation reason]
    M --> N[Mark Cancelled and notify staff]
    N --> O{Request refund?}
    O -- No --> Z([End])
    O -- Yes --> P[Submit refund request and apply policy]
    P --> Q[Manager reviews request]
    Q --> R{Approved?}
    R -- No --> S[Record rejection and notify guest]
    R -- Yes --> T[Record approval and notify guest]
    T --> U[Refund processing step]
    U --> V[Record completion and cancellation]
    H --> Z
    L --> Z
    S --> Z
    V --> Z
```

The secure cancellation link can also be opened directly from the booking email. Cancellation accepts Pending, Pending Payment or Confirmed bookings. Refund completion here describes application status changes: the current refund endpoint contains a simulated processing step and is not proof that a payment provider has transferred funds.

## 3. Staff and administrator operations

```mermaid
flowchart TD
    A([Staff or administrator]) --> B[Log in]
    B --> C{Authentication accepted?}
    C -- No --> B
    C -- Yes --> D[Open dashboard with role permissions]
    D --> E{Choose operation}
    E -- Reservations --> F[Review bookings and calendar]
    E -- Payments --> G[Review and verify pending payments]
    E -- Arrivals --> H{Walk-in guest?}
    H -- Yes --> I[Select available room and enter guest details]
    I --> J[Collect full payment and create booking]
    H -- No --> K[Find reservation and collect balance if due]
    J --> L[Check in guest and mark room occupied]
    K --> L
    E -- Departures --> M[Check out guest and mark room maintenance]
    E -- Rooms --> N[Manage room availability and maintenance status]
    E -- Refunds --> O[Review refund requests and processing status]
    E -- Administration --> P[Manage staff, room types, promotions and content]
    E -- Reporting --> Q[View reports, analytics, notifications and logs]
    F --> D
    G --> D
    L --> D
    M --> D
    N --> D
    O --> D
    P --> D
    Q --> D
    D --> R[Log out]
    R --> S([End])
```

## Implementation references

- Booking and payment: `frontend/book.php`, `frontend/payments.php`, `frontend/payment_success.php`, `backend/api/paymongo_webhook.php`.
- Guest access: `frontend/my_booking.php`, `backend/api/guest_lookup_api.php`, `backend/api/guest_verify_otp_api.php`.
- Cancellation and refunds: `frontend/cancel_booking.php`, `backend/api/refund_request_api.php`.
- Arrival and departure: `frontend/checkin.php`, `frontend/checkout.php`, `frontend/admin_checkin.php`, `frontend/admin_checkout.php`.
