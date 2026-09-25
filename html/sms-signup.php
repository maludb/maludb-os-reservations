<?php $pageTitle = 'Register for ZozoCal'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Register for ZozoCal</title>
    <meta name="description" content="Sign up for ZozoCal SMS notifications - Reservation Reminders and more." />

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
        .zc-signup-content {
            max-width: 500px;
            margin: 0 auto;
            padding: 60px 20px 80px;
        }
        .zc-signup-content h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--zc-dark);
            margin-bottom: 24px;
            text-align: center;
        }
        .zc-signup-content .form-label {
            font-weight: 600;
            font-size: 14px;
            color: var(--zc-dark);
        }
        .zc-signup-content .form-control {
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #ddd;
            font-size: 15px;
        }
        .zc-signup-content .form-control:focus {
            border-color: var(--zc-primary);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        .btn-sms-signup {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            background: #dc3545;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-sms-signup:hover {
            background: #bb2d3b;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(220,53,69,0.3);
        }
        .btn-no-thanks {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: 1px solid #ddd;
            background: #fff;
            color: #6b7280;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-no-thanks:hover {
            background: #f9fafb;
            border-color: #bbb;
        }
        .zc-disclaimer {
            margin-top: 28px;
            font-size: 12px;
            line-height: 1.7;
            color: #6b7280;
            text-align: center;
        }
        .zc-disclaimer a { color: var(--zc-primary); }
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

    <nav class="zc-legal-navbar" id="sms-navbar">
        <div class="container d-flex align-items-center justify-content-between" id="sms-navbar-container">
            <a href="/" class="zc-brand" id="sms-brand">ZozoCal</a>
            <a href="/" class="btn btn-sm btn-outline-secondary" id="sms-back-btn"><i class="feather-arrow-left me-1"></i> Back to Home</a>
        </div>
    </nav>

    <div class="zc-legal-hero" id="sms-hero">
        <h1 id="sms-hero-title">Register for ZozoCal</h1>
        <p id="sms-hero-subtitle">Stay connected with your favorite restaurants</p>
    </div>

    <div class="zc-signup-content" id="sms-content">

        <h2 id="sms-optin-heading">Opt in to SMS Messages</h2>

        <form id="sms-signup-form" method="post" action="/">
            <div class="mb-3" id="sms-field-firstname">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Enter your first name" required>
            </div>

            <div class="mb-4" id="sms-field-phone">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone" placeholder="(555) 123-4567" required>
            </div>

            <div class="mb-3" id="sms-btn-signup-wrap">
                <button type="submit" class="btn-sms-signup" id="sms-btn-signup">Sign up for SMS</button>
            </div>

            <div id="sms-btn-nothanks-wrap">
                <a href="/" class="btn-no-thanks d-block text-center text-decoration-none" id="sms-btn-nothanks">No thanks</a>
            </div>
        </form>

        <div class="zc-disclaimer" id="sms-disclaimer">
            By submitting this form and signing up for text messages (e.g. Reservation Reminders, promos) from ZozoCal. Consent is not a condition of purchase. Msg &amp; Data Rates may apply. Unsubscribe at any time by replying STOP or clicking the unsubscribe link (where available).
            <br><br>
            <a href="/privacy.php">Privacy Policy</a> &nbsp;&middot;&nbsp; <a href="/terms.php">Terms of Service</a>
        </div>

    </div>

    <footer class="zc-footer" id="sms-footer">
        <div class="container" id="sms-footer-container">
            &copy; <?php echo date('Y'); ?> Kinetic Seas Incorporated (OTCQB: KSEZ). All rights reserved.
            &nbsp;&middot;&nbsp; <a href="/terms.php">Terms of Service</a>
            &nbsp;&middot;&nbsp; <a href="/privacy.php">Privacy Policy</a>
        </div>
    </footer>

</body>
</html>
