<?php
/**
 * terms.php - Privacy Policy and Terms & Conditions
 */
require_once 'frontend/partials/_page_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy & Terms - Santa Fe Beach Club</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="frontend/assets/css/style.css">
    <style>
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
    </style>
</head>
<body>
    <?php render_header("Privacy & Terms", "Santa Fe Beach Club"); ?>

    <div class="terms-container">
        <h1>Privacy Policy & Terms of Service</h1>
        <p>Last Updated: <?php echo date('F j, Y'); ?></p>

        <h2>1. Data Collection & Compliance (Philippine Data Privacy Act of 2012 - RA 10173)</h2>
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

        <h2>4. Cancellation & Refund Policy</h2>
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
        <p>To exercise any of these rights or for privacy-related inquiries, contact our Data Protection Officer / Resort Administration at <a href="mailto:info@santafebeachclub.com">info@santafebeachclub.com</a>.</p>
    </div>
</body>
</html>
