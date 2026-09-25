<?php $pageTitle = 'Privacy Policy'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Privacy Policy - ZozoCal</title>
    <meta name="description" content="ZozoCal Privacy Policy - How we collect, use, and protect your information" />

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
        .zc-legal-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 14px;
        }
        .zc-legal-content table th,
        .zc-legal-content table td {
            border: 1px solid #e5e7eb;
            padding: 10px 14px;
            text-align: left;
        }
        .zc-legal-content table th {
            background: #f8f9ff;
            font-weight: 600;
            color: var(--zc-dark);
        }
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

    <nav class="zc-legal-navbar" id="privacy-navbar">
        <div class="container d-flex align-items-center justify-content-between" id="privacy-navbar-container">
            <a href="/" class="zc-brand" id="privacy-brand">ZozoCal</a>
            <a href="/" class="btn btn-sm btn-outline-secondary" id="privacy-back-btn"><i class="feather-arrow-left me-1"></i> Back to Home</a>
        </div>
    </nav>

    <div class="zc-legal-hero" id="privacy-hero">
        <h1 id="privacy-hero-title">Privacy Policy</h1>
        <p id="privacy-hero-date">Last Updated: <?php echo date('F j, Y'); ?></p>
    </div>

    <div class="zc-legal-content" id="privacy-content">

        <h2 id="privacy-s1">1. Introduction</h2>
        <p id="privacy-s1-p1">Kinetic Seas Incorporated (OTCQB: KSEZ) ("Company," "we," "us," or "our") is committed to protecting the privacy of our users and their guests. This Privacy Policy explains how we collect, use, disclose, and safeguard information when you use our restaurant reservation management platform ("Service"), including our website, booking pages, and SMS text messaging features.</p>
        <p id="privacy-s1-p2">By using the Service, you consent to the practices described in this Privacy Policy. If you do not agree, please do not use the Service.</p>

        <h2 id="privacy-s2">2. Information We Collect</h2>

        <h3 id="privacy-s2a">2.1 Information from Restaurant Users</h3>
        <p id="privacy-s2a-p1">When you create a restaurant account, we collect:</p>
        <ul id="privacy-s2a-list">
            <li>Name, email address, and phone number</li>
            <li>Restaurant name, address, and contact information</li>
            <li>Account credentials (password is stored in hashed form only)</li>
            <li>Staff member information (names, emails, roles)</li>
            <li>Restaurant configuration (hours, tables, settings)</li>
        </ul>

        <h3 id="privacy-s2b">2.2 Information from Guests</h3>
        <p id="privacy-s2b-p1">When guests make reservations or are added to a waitlist, we collect:</p>
        <ul id="privacy-s2b-list">
            <li>First and last name</li>
            <li>Email address</li>
            <li>Phone number (used for SMS notifications)</li>
            <li>Reservation details (date, time, party size, special requests)</li>
            <li>Dining preferences and dietary restrictions (if provided)</li>
            <li>Visit history and reservation records</li>
        </ul>

        <h3 id="privacy-s2c">2.3 Information Collected Automatically</h3>
        <p id="privacy-s2c-p1">When you access the Service, we may automatically collect:</p>
        <ul id="privacy-s2c-list">
            <li>IP address and browser type</li>
            <li>Pages visited and features used</li>
            <li>Date and time of access</li>
            <li>Device information</li>
        </ul>

        <h2 id="privacy-s3">3. How We Use Your Information</h2>
        <p id="privacy-s3-p1">We use the information we collect for the following purposes:</p>

        <table id="privacy-s3-table">
            <thead>
                <tr><th>Purpose</th><th>Data Used</th></tr>
            </thead>
            <tbody>
                <tr><td>Provide and operate the Service</td><td>Account info, restaurant data, reservation data</td></tr>
                <tr><td>Process and manage reservations</td><td>Guest name, contact info, reservation details</td></tr>
                <tr><td>Send SMS reservation confirmations</td><td>Guest phone number, reservation details</td></tr>
                <tr><td>Send SMS reservation reminders</td><td>Guest phone number, reservation details</td></tr>
                <tr><td>Send SMS waitlist notifications</td><td>Guest phone number, waitlist status</td></tr>
                <tr><td>Send email confirmations and updates</td><td>Guest email, reservation details</td></tr>
                <tr><td>Maintain guest profiles for restaurants</td><td>Guest preferences, visit history, dietary info</td></tr>
                <tr><td>Generate reports and analytics</td><td>Aggregated reservation and usage data</td></tr>
                <tr><td>Improve and maintain the Service</td><td>Usage data, error logs</td></tr>
            </tbody>
        </table>

        <h2 id="privacy-s4">4. SMS Text Messaging Privacy</h2>
        <p id="privacy-s4-p1">This section specifically addresses how we handle information related to SMS text messaging.</p>

        <h3 id="privacy-s4a">4.1 Phone Number Collection and Use</h3>
        <p id="privacy-s4a-p1">Guest phone numbers are collected when:</p>
        <ul id="privacy-s4a-list">
            <li>A guest provides their phone number while making an online reservation</li>
            <li>Restaurant staff enters a guest's phone number when creating a reservation or adding to the waitlist</li>
        </ul>
        <p id="privacy-s4a-p2">Phone numbers are used exclusively for transactional SMS messages related to reservations and waitlist management. <strong>Phone numbers are never sold, shared with third parties for marketing purposes, or used for promotional messaging.</strong></p>

        <h3 id="privacy-s4b">4.2 Types of SMS Messages Sent</h3>
        <ul id="privacy-s4b-list">
            <li><strong>Reservation Confirmation:</strong> Confirms a new or updated reservation</li>
            <li><strong>Reservation Reminder:</strong> Reminds guests of an upcoming reservation</li>
            <li><strong>Reservation Cancellation:</strong> Confirms a cancellation</li>
            <li><strong>Waitlist Notification:</strong> Notifies guests when their table is ready</li>
        </ul>
        <p id="privacy-s4b-p1">All SMS messages are transactional in nature and directly related to a guest's reservation or waitlist interaction. We do not send marketing or promotional SMS messages.</p>

        <h3 id="privacy-s4c">4.3 SMS Consent</h3>
        <p id="privacy-s4c-p1">Guests consent to receive SMS messages by providing their phone number during the reservation or waitlist process. Consent is obtained at the time the phone number is collected. A clear disclosure is provided on booking pages stating that by providing a phone number, the guest agrees to receive reservation-related text messages.</p>
        <p id="privacy-s4c-p2">Consent to receive SMS messages is not required to make a reservation. Guests may decline to provide a phone number and will simply not receive SMS notifications.</p>

        <h3 id="privacy-s4d">4.4 Opting Out</h3>
        <p id="privacy-s4d-p1">Guests can opt out of SMS messages at any time by:</p>
        <ul id="privacy-s4d-list">
            <li>Replying <strong>STOP</strong> to any message</li>
            <li>Contacting the restaurant directly</li>
            <li>Emailing <a href="mailto:support@zozocal.com">support@zozocal.com</a></li>
        </ul>
        <p id="privacy-s4d-p2">Once opted out, guests will receive a single confirmation message and no further SMS messages will be sent. Opt-out preferences are honored across all restaurants using ZozoCal.</p>

        <h3 id="privacy-s4e">4.5 SMS Data Sharing</h3>
        <p id="privacy-s4e-p1"><strong>We do not share, sell, rent, or trade phone numbers or SMS consent information with any third parties for marketing or advertising purposes.</strong> Phone numbers are shared only with our SMS delivery provider (e.g., Twilio) solely for the purpose of delivering messages. Our SMS delivery provider is contractually obligated to use this data only for message delivery and is bound by their own privacy and security obligations.</p>

        <h3 id="privacy-s4f">4.6 Message and Data Rates</h3>
        <p id="privacy-s4f-p1">Standard message and data rates may apply depending on the guest's wireless carrier and plan. Kinetic Seas Incorporated does not charge for SMS messages. Guests should contact their carrier for details about their plan.</p>

        <h2 id="privacy-s5">5. How We Share Information</h2>
        <p id="privacy-s5-p1">We may share information in the following limited circumstances:</p>
        <ul id="privacy-s5-list">
            <li><strong>With Restaurants:</strong> Guest information is shared with the restaurant where the reservation was made, for the purpose of providing dining services</li>
            <li><strong>Service Providers:</strong> We use third-party service providers (e.g., SMS delivery, email delivery, hosting) who process data on our behalf and are bound by contractual obligations to protect your data</li>
            <li><strong>Legal Requirements:</strong> We may disclose information if required by law, regulation, legal process, or governmental request</li>
            <li><strong>Business Transfers:</strong> In the event of a merger, acquisition, or sale of assets, user data may be transferred as part of the transaction</li>
        </ul>
        <p id="privacy-s5-p2"><strong>We never sell personal information to third parties.</strong></p>

        <h2 id="privacy-s6">6. Data Security</h2>
        <p id="privacy-s6-p1">We implement appropriate technical and organizational measures to protect personal information, including:</p>
        <ul id="privacy-s6-list">
            <li>Encryption of data in transit (HTTPS/TLS)</li>
            <li>Hashed and salted password storage</li>
            <li>Role-based access controls</li>
            <li>Regular security reviews</li>
        </ul>
        <p id="privacy-s6-p2">While we strive to protect your personal information, no method of transmission over the internet or electronic storage is 100% secure.</p>

        <h2 id="privacy-s7">7. Data Retention</h2>
        <p id="privacy-s7-p1">We retain personal information for as long as necessary to provide the Service and fulfill the purposes described in this policy. Guest reservation data is retained for the restaurant's operational and record-keeping needs. Guests may request deletion of their data by contacting the restaurant or emailing <a href="mailto:support@zozocal.com">support@zozocal.com</a>.</p>

        <h2 id="privacy-s8">8. Your Rights</h2>
        <p id="privacy-s8-p1">Depending on your jurisdiction, you may have the right to:</p>
        <ul id="privacy-s8-list">
            <li>Access the personal information we hold about you</li>
            <li>Request correction of inaccurate information</li>
            <li>Request deletion of your personal information</li>
            <li>Opt out of SMS communications</li>
            <li>Request a copy of your data in a portable format</li>
        </ul>
        <p id="privacy-s8-p2">To exercise any of these rights, contact us at <a href="mailto:support@zozocal.com">support@zozocal.com</a>.</p>

        <h2 id="privacy-s9">9. Children's Privacy</h2>
        <p id="privacy-s9-p1">The Service is not directed to children under 13, and we do not knowingly collect personal information from children under 13. If we become aware that we have collected information from a child under 13, we will take steps to delete it promptly.</p>

        <h2 id="privacy-s10">10. Changes to This Policy</h2>
        <p id="privacy-s10-p1">We may update this Privacy Policy from time to time. We will notify users of material changes by posting the updated policy on this page with a revised "Last Updated" date. Your continued use of the Service after changes are posted constitutes acceptance of the updated policy.</p>

        <h2 id="privacy-s11">11. Contact Us</h2>
        <p id="privacy-s11-p1">If you have questions about this Privacy Policy, our SMS practices, or wish to exercise your privacy rights, please contact us:</p>
        <ul id="privacy-s11-list">
            <li>Email: <a href="mailto:support@zozocal.com">support@zozocal.com</a></li>
            <li>Website: <a href="https://zozocal.com">zozocal.com</a></li>
        </ul>

    </div>

    <footer class="zc-footer" id="privacy-footer">
        <div class="container" id="privacy-footer-container">
            &copy; <?php echo date('Y'); ?> Kinetic Seas Incorporated (OTCQB: KSEZ). All rights reserved.
            &nbsp;&middot;&nbsp; <a href="/terms.php">Terms of Service</a>
            &nbsp;&middot;&nbsp; <a href="/privacy.php">Privacy Policy</a>
        </div>
    </footer>

</body>
</html>
