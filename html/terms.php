<?php $pageTitle = 'Terms of Service'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Terms of Service - ZozoCal</title>
    <meta name="description" content="ZozoCal Terms of Service - Restaurant Reservation Management Platform" />

    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon-vt.png" />
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/vendor/feather.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --zc-primary: #667eea;
            --zc-secondary: #764ba2;
            --zc-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --zc-dark: #1a1a2e;
        }
        body { font-family: 'Inter', sans-serif; color: #333; }
        .zc-legal-navbar {
            background: #fff;
            padding: 16px 0;
            border-bottom: 1px solid #eee;
        }
        .zc-brand {
            font-size: 24px;
            font-weight: 800;
            background: var(--zc-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
        }
        .zc-legal-hero {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 50%, #f0e6ff 100%);
            padding: 80px 0 40px;
            text-align: center;
        }
        .zc-legal-hero h1 {
            font-size: 36px;
            font-weight: 800;
            color: var(--zc-dark);
        }
        .zc-legal-hero p { color: #6b7280; font-size: 15px; }
        .zc-legal-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 60px 20px 80px;
            font-size: 15px;
            line-height: 1.8;
            color: #444;
        }
        .zc-legal-content h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--zc-dark);
            margin-top: 40px;
            margin-bottom: 16px;
        }
        .zc-legal-content h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--zc-dark);
            margin-top: 28px;
            margin-bottom: 12px;
        }
        .zc-legal-content ul { padding-left: 20px; }
        .zc-legal-content ul li { margin-bottom: 8px; }
        .zc-legal-content a { color: var(--zc-primary); }
        .zc-footer {
            background: var(--zc-dark);
            padding: 30px 0;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            text-align: center;
        }
        .zc-footer a { color: rgba(255,255,255,0.6); text-decoration: none; }
        .zc-footer a:hover { color: #fff; }
    </style>
</head>
<body>

    <nav class="zc-legal-navbar" id="terms-navbar">
        <div class="container d-flex align-items-center justify-content-between" id="terms-navbar-container">
            <a href="/" class="zc-brand" id="terms-brand">ZozoCal</a>
            <a href="/" class="btn btn-sm btn-outline-secondary" id="terms-back-btn"><i class="feather-arrow-left me-1"></i> Back to Home</a>
        </div>
    </nav>

    <div class="zc-legal-hero" id="terms-hero">
        <h1 id="terms-hero-title">Terms of Service</h1>
        <p id="terms-hero-date">Last Updated: <?php echo date('F j, Y'); ?></p>
    </div>

    <div class="zc-legal-content" id="terms-content">

        <h2 id="terms-s1">1. Acceptance of Terms</h2>
        <p id="terms-s1-p1">By accessing or using ZozoCal ("Service"), operated by Kinetic Seas Incorporated (OTCQB: KSEZ) ("Company," "we," "us," or "our"), you agree to be bound by these Terms of Service ("Terms"). If you do not agree to these Terms, do not use the Service.</p>
        <p id="terms-s1-p2">These Terms apply to all users of the Service, including restaurant owners, managers, staff ("Restaurant Users"), and restaurant guests ("Guests") who interact with the Service through booking pages, SMS messages, or email communications.</p>

        <h2 id="terms-s2">2. Description of Service</h2>
        <p id="terms-s2-p1">ZozoCal is a restaurant reservation management platform that enables restaurants to:</p>
        <ul id="terms-s2-list">
            <li>Accept and manage online reservations</li>
            <li>Manage tables, floor plans, and waitlists</li>
            <li>Communicate with guests via email and SMS text messages</li>
            <li>Maintain guest profiles and preferences</li>
            <li>Generate reports and analytics</li>
        </ul>

        <h2 id="terms-s3">3. Account Registration</h2>
        <p id="terms-s3-p1">To use the Service as a Restaurant User, you must create an account. You agree to provide accurate, current, and complete information and to keep your account credentials secure. You are responsible for all activity that occurs under your account.</p>
        <p id="terms-s3-p2">You must be at least 18 years old to create an account. By creating an account, you represent that you have the authority to bind the restaurant or business you represent to these Terms.</p>

        <h2 id="terms-s4">4. SMS Text Messaging Terms</h2>
        <p id="terms-s4-p1">The Service includes SMS text messaging functionality for restaurant-to-guest communications. By using the SMS features of the Service, you agree to the following:</p>

        <h3 id="terms-s4a">4.1 Types of SMS Messages</h3>
        <p id="terms-s4a-p1">ZozoCal sends the following types of SMS text messages on behalf of restaurants:</p>
        <ul id="terms-s4a-list">
            <li><strong>Reservation Confirmations:</strong> Sent when a reservation is created or confirmed</li>
            <li><strong>Reservation Reminders:</strong> Sent prior to a scheduled reservation (typically 24 hours before)</li>
            <li><strong>Reservation Updates:</strong> Sent when a reservation is modified or cancelled</li>
            <li><strong>Waitlist Notifications:</strong> Sent to notify guests when their table is ready</li>
        </ul>

        <h3 id="terms-s4b">4.2 Guest Consent for SMS</h3>
        <p id="terms-s4b-p1">Guests provide consent to receive SMS messages when they:</p>
        <ul id="terms-s4b-list">
            <li>Make a reservation through the ZozoCal online booking system and provide their phone number</li>
            <li>Provide their phone number to restaurant staff for a reservation or waitlist entry</li>
            <li>Are added to a restaurant's waitlist and provide their phone number</li>
        </ul>
        <p id="terms-s4b-p2">By providing a phone number during any of these interactions, the guest expressly consents to receive transactional SMS messages related to their reservation or waitlist status from ZozoCal on behalf of the restaurant.</p>
        <p id="terms-s4b-p3">Consent to receive SMS messages is not a condition of making a reservation. Guests may make reservations without providing a phone number, though they will not receive SMS notifications.</p>

        <h3 id="terms-s4c">4.3 Message Frequency</h3>
        <p id="terms-s4c-p1">Message frequency varies based on reservation activity. Guests will typically receive 1-3 messages per reservation (confirmation, reminder, and/or updates). Waitlist notifications are limited to 1-2 messages per waitlist entry. No more than 5 messages will be sent per guest per day.</p>

        <h3 id="terms-s4d">4.4 Opting Out of SMS</h3>
        <p id="terms-s4d-p1">Guests may opt out of receiving SMS messages at any time by:</p>
        <ul id="terms-s4d-list">
            <li>Replying <strong>STOP</strong> to any SMS message received from ZozoCal</li>
            <li>Contacting the restaurant directly to request removal</li>
            <li>Emailing <a href="mailto:support@zozocal.com">support@zozocal.com</a> with the subject "SMS Opt-Out"</li>
        </ul>
        <p id="terms-s4d-p2">After opting out, guests will receive a final confirmation message and will no longer receive SMS messages from ZozoCal. Opting out of SMS does not cancel any existing reservations.</p>

        <h3 id="terms-s4e">4.5 Message and Data Rates</h3>
        <p id="terms-s4e-p1">Standard message and data rates may apply. Kinetic Seas Incorporated does not charge guests for SMS messages, but carrier charges may apply depending on your wireless plan. Contact your wireless carrier for details about your messaging plan.</p>

        <h3 id="terms-s4f">4.6 SMS Help</h3>
        <p id="terms-s4f-p1">For help with SMS messages, guests may reply <strong>HELP</strong> to any message received from ZozoCal, or contact <a href="mailto:support@zozocal.com">support@zozocal.com</a>.</p>

        <h3 id="terms-s4g">4.7 Supported Carriers</h3>
        <p id="terms-s4g-p1">SMS messages are supported on all major U.S. wireless carriers including AT&T, Verizon, T-Mobile, Sprint, and most regional carriers. Carriers are not liable for delayed or undelivered messages.</p>

        <h3 id="terms-s4h">4.8 Restaurant Obligations for SMS</h3>
        <p id="terms-s4h-p1">Restaurant Users agree to:</p>
        <ul id="terms-s4h-list">
            <li>Only use SMS features for legitimate transactional communications related to reservations and waitlist management</li>
            <li>Not use SMS features for marketing, promotional, or advertising purposes</li>
            <li>Ensure guest phone numbers are collected with proper consent</li>
            <li>Honor all opt-out requests promptly</li>
            <li>Comply with all applicable laws regarding SMS communications, including the Telephone Consumer Protection Act (TCPA)</li>
        </ul>

        <h2 id="terms-s5">5. Acceptable Use</h2>
        <p id="terms-s5-p1">You agree not to:</p>
        <ul id="terms-s5-list">
            <li>Use the Service for any unlawful purpose</li>
            <li>Send unsolicited marketing messages through the SMS features</li>
            <li>Attempt to gain unauthorized access to the Service or its related systems</li>
            <li>Interfere with or disrupt the Service</li>
            <li>Use the Service to store or transmit malicious code</li>
            <li>Impersonate any person or entity</li>
        </ul>

        <h2 id="terms-s6">6. Data and Privacy</h2>
        <p id="terms-s6-p1">Your use of the Service is also governed by our <a href="/privacy.php">Privacy Policy</a>, which describes how we collect, use, and protect personal information including phone numbers used for SMS communications.</p>

        <h2 id="terms-s7">7. Intellectual Property</h2>
        <p id="terms-s7-p1">The Service and its original content, features, and functionality are owned by Kinetic Seas Incorporated (OTCQB: KSEZ) and are protected by copyright, trademark, and other intellectual property laws. Restaurant data, guest information, and content uploaded by Restaurant Users remain the property of the respective Restaurant User.</p>

        <h2 id="terms-s8">8. Limitation of Liability</h2>
        <p id="terms-s8-p1">To the fullest extent permitted by law, Kinetic Seas Incorporated shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including loss of profits, data, or business opportunities, whether arising from your use of the Service, SMS messaging features, or otherwise.</p>
        <p id="terms-s8-p2">Kinetic Seas Incorporated does not guarantee the delivery of SMS messages. Message delivery is dependent on wireless carrier networks and is subject to carrier policies and technical limitations.</p>

        <h2 id="terms-s9">9. Termination</h2>
        <p id="terms-s9-p1">We may terminate or suspend your account at any time, without prior notice, for conduct that we determine violates these Terms or is harmful to other users, the Service, or third parties. Upon termination, your right to use the Service will immediately cease.</p>

        <h2 id="terms-s10">10. Changes to Terms</h2>
        <p id="terms-s10-p1">We reserve the right to modify these Terms at any time. We will notify users of material changes by posting the updated Terms on this page with a revised "Last Updated" date. Your continued use of the Service after changes are posted constitutes acceptance of the updated Terms.</p>

        <h2 id="terms-s11">11. Contact Us</h2>
        <p id="terms-s11-p1">If you have questions about these Terms of Service, please contact us:</p>
        <ul id="terms-s11-list">
            <li>Email: <a href="mailto:support@zozocal.com">support@zozocal.com</a></li>
            <li>Website: <a href="https://zozocal.com">zozocal.com</a></li>
        </ul>

    </div>

    <footer class="zc-footer" id="terms-footer">
        <div class="container" id="terms-footer-container">
            &copy; <?php echo date('Y'); ?> Kinetic Seas Incorporated (OTCQB: KSEZ). All rights reserved.
            &nbsp;&middot;&nbsp; <a href="/terms.php">Terms of Service</a>
            &nbsp;&middot;&nbsp; <a href="/privacy.php">Privacy Policy</a>
        </div>
    </footer>

</body>
</html>
