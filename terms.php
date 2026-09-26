<?php
/**
 * terms.php - Privacy Policy and Terms & Conditions
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="32x32" href="frontend/assets/icons/favicon-circle-32.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="frontend/assets/icons/favicon-circle-180.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy & Terms - Santa Fe Beach Club</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="frontend/assets/css/styles.css">
    <style>
        .terms-back-bar {
            max-width: 1120px;
            margin: 0 auto;
            padding: 28px 24px 0;
        }
        .terms-back-button {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            min-height: 46px;
            padding: 0 18px;
            border: 1px solid rgba(124, 83, 60, 0.28);
            border-radius: 999px;
            background: #fff;
            color: #5f3f2f;
            font: 600 14px/1 'Outfit', sans-serif;
            text-decoration: none;
            box-shadow: 0 8px 22px rgba(76, 49, 34, 0.08);
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .terms-back-button:hover {
            transform: translateX(-2px);
            border-color: #9c6a48;
            box-shadow: 0 10px 24px rgba(76, 49, 34, 0.13);
        }
        .terms-back-button:focus-visible {
            outline: 3px solid rgba(156, 106, 72, 0.3);
            outline-offset: 3px;
        }
        .terms-container {
            max-width: 900px;
            margin: 60px auto;
            padding: 40px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            font-family: 'Outfit', sans-serif;
            color: #334155;
            line-height: 1.6;
        }
        .terms-container h1 { font-size: 28px; color: #0F172A; margin-bottom: 24px; text-align: center; }
        .terms-container h2 { font-size: 20px; color: #1E293B; margin-top: 32px; margin-bottom: 16px; border-bottom: 1px solid #E2E8F0; padding-bottom: 8px; }
        .terms-container p { margin-bottom: 16px; font-size: 15px; }
        .terms-container ul { margin-bottom: 16px; padding-left: 20px; }
        .terms-container li { margin-bottom: 8px; font-size: 15px; }
        .terms-container h2[id] { scroll-margin-top: 24px; }
        .terms-page { background: #f7f2eb; }
        .terms-page .terms-container { margin-top: 28px; }
        @media (max-width: 640px) {
            .terms-back-bar { padding: 18px 12px 0; }
            .terms-back-button { min-height: 42px; padding: 0 15px; }
            .terms-container { margin: 20px 12px 40px; padding: 26px 20px; }
            .terms-container h1 { font-size: 24px; }
        }
    </style>
</head>
<body class="terms-page">
    <div class="terms-back-bar">
        <a class="terms-back-button" href="frontend/index" id="termsBackButton" aria-label="Go back to the previous page">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
            <span>Back</span>
        </a>
    </div>

    <div class="terms-container">
        <h1>Privacy Policy & Terms of Service</h1>
        <p>Last Updated: <?php echo date('F j, Y'); ?></p>

        <h2 id="privacy">1. Data Collection & Compliance (Philippine Data Privacy Act of 2012 - RA 10173)</h2>
        <p>Santa Fe Beach Club complies with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong> of the Philippines and its Implementing Rules and Regulations (IRR). In accordance with the principles of transparency, legitimate purpose, and proportionality (data minimization), we only collect the minimum personal information required to process, confirm, and manage your reservation:</p>
        <ul>
            <li>Full Name</li>
            <li>Email Address (for booking confirmation, receipts, and secure OTP verification)</li>
            <li>Phone Number (for emergency contact and reservation coordination)</li>
            <li>Country of Origin (for demographic records and tourism reporting)</li>
            <li>Payment Proof (e.g., GCash / Bank transfer receipts and transaction reference numbers)</li>
        </ul>
        <p>We do <strong>not</strong> collect or store credit/debit card numbers or CVVs. All electronic payments are processed through customer-initiated transfers (GCash / Bank Transfer) or completed in person at check-in.</p>

        <h2>2. Data Retention & Deletion</h2>
        <p>Your personal data is retained only for as long as necessary to fulfill legitimate business, accounting, and legal requirements pursuant to Philippine statutory regulations:</p>
        <ul>
            <li><strong>Booking Data:</strong> Retained for accounting and legal compliance for a period of up to 2 years after checkout.</li>
            <li><strong>Payment Receipts:</strong> Securely deleted 90 days after checkout verification.</li>
            <li><strong>OTP Codes & Auth Tokens:</strong> Cryptographically hashed and automatically invalidated after 10–15 minutes or immediately upon use.</li>
            <li><strong>Audit & Security Logs:</strong> Retained for 90 days to monitor system integrity and investigate unauthorized access attempts.</li>
        </ul>

        <h2>3. Access Control & Security Safeguards</h2>
        <p>Your personal data is strictly protected by organizational, physical, and technical security measures pursuant to RA 10173. Access to guest profiles, contact information, and payment receipts is restricted exclusively to authorized Management and Reception staff using Role-Based Access Control (RBAC), multi-factor authentication, and encrypted sessions. Your data is never sold, leased, or disclosed to unauthorized third parties.</p>

        <h2 id="terms">4. Cancellation & Refund Policy</h2>
        <p>You may cancel your booking using the unique secure link provided in your confirmation email. Cancellations must be made prior to the payment deadline. Refunds for paid reservations are subject to resort management terms and policy approval.</p>
        
        <h2>5. Your Data Subject Rights (RA 10173)</h2>
        <p>Under the Philippine Data Privacy Act (RA 10173), you are entitled to statutory data subject rights, including:</p>
        <ul>
            <li><strong>Right to be Informed:</strong> To know how your personal data will be processed.</li>
            <li><strong>Right to Access:</strong> To request a copy of the personal information we hold about you.</li>
            <li><strong>Right to Rectification:</strong> To request corrections or updates to any inaccurate or incomplete details.</li>
            <li><strong>Right to Erasure or Blocking:</strong> To request the deletion or removal of your personal data upon completion of purpose or expiration of retention periods.</li>
            <li><strong>Right to Damages and to Lodge a Complaint:</strong> To lodge a complaint with the National Privacy Commission (NPC) if your privacy rights have been infringed.</li>
        </ul>
        <p>To exercise any of these rights or for privacy-related inquiries, contact our Data Protection Officer / Resort Administration at <a href="mailto:Justinebatuhan017@gmail.com">Justinebatuhan017@gmail.com</a>.</p>
    </div>
    <script>
        document.getElementById('termsBackButton').addEventListener('click', function (event) {
            if (document.referrer) {
                try {
                    if (new URL(document.referrer).origin === window.location.origin) {
                        event.preventDefault();
                        window.history.back();
                    }
                } catch (error) {
                    // Keep the Home fallback if the referrer cannot be parsed.
                }
            }
        });
    </script>
</body>
</html>
