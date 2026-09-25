<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ZozoCal - Restaurant Reservation Management</title>
    <meta name="description" content="Streamline your restaurant reservations with ZozoCal. Online bookings, table management, guest CRM, and more." />

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
            --zc-gray: #6b7280;
            --zc-light: #f8f9ff;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            overflow-x: hidden;
        }

        .zc-navbar {
            background: #fff;
            padding: 16px 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            transition: box-shadow 0.3s;
        }

        .zc-navbar.scrolled {
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
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

        .zc-nav-link {
            color: var(--zc-dark);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            padding: 8px 16px;
            transition: color 0.2s;
        }

        .zc-nav-link:hover { color: var(--zc-primary); }

        .btn-zc-outline {
            border: 2px solid var(--zc-primary);
            color: var(--zc-primary);
            font-weight: 600;
            padding: 8px 24px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-zc-outline:hover {
            background: var(--zc-primary);
            color: #fff;
        }

        .btn-zc-primary {
            background: var(--zc-gradient);
            color: #fff;
            font-weight: 600;
            padding: 10px 28px;
            border-radius: 8px;
            border: none;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            display: inline-block;
        }

        .btn-zc-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.4);
            color: #fff;
        }

        .btn-zc-primary-lg {
            padding: 14px 40px;
            font-size: 16px;
            border-radius: 10px;
        }

        .btn-zc-white {
            background: #fff;
            color: var(--zc-primary);
            font-weight: 600;
            padding: 14px 40px;
            border-radius: 10px;
            border: none;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 16px;
            display: inline-block;
        }

        .btn-zc-white:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            color: var(--zc-secondary);
        }

        .zc-hero {
            padding: 160px 0 100px;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 50%, #f0e6ff 100%);
            position: relative;
            overflow: hidden;
        }

        .zc-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(102,126,234,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .zc-hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(118,75,162,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .zc-hero h1 {
            font-size: 52px;
            font-weight: 800;
            line-height: 1.15;
            color: var(--zc-dark);
            margin-bottom: 24px;
        }

        .zc-hero h1 span {
            background: var(--zc-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .zc-hero p {
            font-size: 20px;
            color: var(--zc-gray);
            line-height: 1.6;
            max-width: 520px;
        }

        .zc-hero-visual {
            position: relative;
            z-index: 1;
        }

        .zc-hero-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(102,126,234,0.15);
            padding: 32px;
            position: relative;
        }

        .zc-hero-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #eee;
        }

        .zc-hero-card-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .zc-hero-card-row {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
            font-size: 14px;
        }

        .zc-hero-card-row:last-child { border-bottom: none; }

        .zc-status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .zc-floating-badge {
            position: absolute;
            background: #fff;
            border-radius: 12px;
            padding: 12px 18px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            font-size: 13px;
            font-weight: 600;
            z-index: 2;
        }

        .zc-stats {
            padding: 30px 0;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
        }

        .zc-stat-card {
            text-align: center;
            padding: 24px 16px;
        }

        .zc-stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: var(--zc-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: var(--zc-primary);
            font-size: 24px;
        }

        .zc-stat-card h3 {
            font-size: 28px;
            font-weight: 800;
            color: var(--zc-dark);
            margin-bottom: 4px;
        }

        .zc-stat-card p {
            font-size: 14px;
            color: var(--zc-gray);
            margin: 0;
        }

        .zc-features {
            padding: 100px 0;
            background: var(--zc-light);
        }

        .zc-section-label {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--zc-primary);
            margin-bottom: 12px;
        }

        .zc-section-title {
            font-size: 38px;
            font-weight: 800;
            color: var(--zc-dark);
            margin-bottom: 16px;
        }

        .zc-section-subtitle {
            font-size: 18px;
            color: var(--zc-gray);
            max-width: 600px;
            margin: 0 auto 60px;
        }

        .zc-feature-card {
            background: #fff;
            border-radius: 16px;
            padding: 36px 28px;
            height: 100%;
            transition: all 0.3s;
            border: 1px solid #eee;
        }

        .zc-feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(102,126,234,0.12);
            border-color: rgba(102,126,234,0.2);
        }

        .zc-feature-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 20px;
            color: #fff;
        }

        .zc-feature-card h4 {
            font-size: 18px;
            font-weight: 700;
            color: var(--zc-dark);
            margin-bottom: 10px;
        }

        .zc-feature-card p {
            font-size: 14px;
            color: var(--zc-gray);
            line-height: 1.7;
            margin: 0;
        }

        .zc-how {
            padding: 100px 0;
            background: #fff;
        }

        .zc-step {
            text-align: center;
            padding: 20px;
            position: relative;
        }

        .zc-step-number {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--zc-gradient);
            color: #fff;
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .zc-step h4 {
            font-size: 20px;
            font-weight: 700;
            color: var(--zc-dark);
            margin-bottom: 10px;
        }

        .zc-step p {
            font-size: 15px;
            color: var(--zc-gray);
            line-height: 1.7;
        }

        .zc-step-connector {
            display: none;
        }

        @media (min-width: 768px) {
            .zc-step-connector {
                display: block;
                position: absolute;
                top: 52px;
                right: -40px;
                width: 80px;
                height: 2px;
                background: linear-gradient(90deg, var(--zc-primary), var(--zc-secondary));
                opacity: 0.3;
            }
        }

        .zc-benefits {
            padding: 100px 0;
            background: var(--zc-light);
        }

        .zc-benefit-block {
            padding: 40px 0;
        }

        .zc-benefit-block h3 {
            font-size: 28px;
            font-weight: 800;
            color: var(--zc-dark);
            margin-bottom: 16px;
        }

        .zc-benefit-block p {
            font-size: 16px;
            color: var(--zc-gray);
            line-height: 1.7;
        }

        .zc-benefit-visual {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }

        .zc-check-list {
            list-style: none;
            padding: 0;
            margin: 20px 0 0;
        }

        .zc-check-list li {
            padding: 8px 0;
            font-size: 15px;
            color: #444;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .zc-check-list li i {
            color: #10b981;
            font-size: 16px;
        }

        .zc-cta {
            padding: 100px 0;
            background: var(--zc-gradient);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .zc-cta::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .zc-cta::after {
            content: '';
            position: absolute;
            bottom: -150px;
            left: -50px;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .zc-cta h2 {
            font-size: 40px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 16px;
        }

        .zc-cta p {
            font-size: 18px;
            color: rgba(255,255,255,0.85);
            margin-bottom: 36px;
        }

        .zc-footer {
            background: var(--zc-dark);
            padding: 60px 0 30px;
            color: rgba(255,255,255,0.6);
        }

        .zc-footer-brand {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 12px;
        }

        .zc-footer p {
            font-size: 14px;
            line-height: 1.7;
        }

        .zc-footer h6 {
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }

        .zc-footer-links {
            list-style: none;
            padding: 0;
        }

        .zc-footer-links li { margin-bottom: 10px; }

        .zc-footer-links a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .zc-footer-links a:hover { color: #fff; }

        .zc-footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 24px;
            margin-top: 40px;
            font-size: 13px;
        }

        @media (max-width: 991px) {
            .zc-hero h1 { font-size: 38px; }
            .zc-hero p { font-size: 17px; }
            .zc-section-title { font-size: 30px; }
            .zc-cta h2 { font-size: 30px; }
            .zc-hero { padding: 130px 0 60px; }
        }

        @media (max-width: 767px) {
            .zc-hero h1 { font-size: 32px; }
            .zc-hero p { font-size: 16px; }
            .zc-hero-visual { margin-top: 40px; }
            .zc-nav-links { display: none; }
            .zc-section-title { font-size: 26px; }
            .zc-benefit-visual { margin-top: 30px; }
            .zc-cta h2 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <nav class="zc-navbar" id="landing-navbar">
        <div class="container" id="landing-navbar-container">
            <div class="d-flex align-items-center justify-content-between" id="landing-navbar-inner">
                <a href="/" class="zc-brand" id="landing-brand">ZozoCal</a>

                <div class="d-flex align-items-center gap-3 zc-nav-links" id="landing-nav-links">
                    <a href="/sms-signup.php" class="zc-nav-link" id="landing-nav-reservation">Make a Reservation</a>
                    <a href="#features" class="zc-nav-link" id="landing-nav-features">Features</a>
                    <a href="#how-it-works" class="zc-nav-link" id="landing-nav-how">How It Works</a>
                </div>

                <div class="d-flex align-items-center gap-3" id="landing-nav-actions">
                    <a href="/login.php" class="btn-zc-outline d-none d-sm-inline-block" id="landing-nav-login">Login</a>
                    <a href="/register.php" class="btn-zc-primary" id="landing-nav-cta">Get Started</a>
                </div>
            </div>
        </div>
    </nav>

    <section class="zc-hero" id="landing-hero">
        <div class="container" id="landing-hero-container">
            <div class="row align-items-center" id="landing-hero-row">
                <div class="col-lg-6" id="landing-hero-content">
                    <p class="zc-section-label mb-2" id="landing-hero-tagline">Built for AI Voice Agents and their human friends.</p>
                    <h1 id="landing-hero-title">
                        Manage scheduling and reservations <span>like a pro</span>
                    </h1>
                    <p id="landing-hero-subtitle">
                        The all-in-one reservation platform that helps
                        reduce no-shows, and deliver exceptional guest experiences.
                    </p>
                    <div class="d-flex gap-3 mt-4 flex-wrap" id="landing-hero-actions">
                        <a href="/register.php" class="btn-zc-primary btn-zc-primary-lg" id="landing-hero-cta">
                            Get Started Free
                        </a>
                        <a href="#features" class="btn-zc-outline" style="padding:14px 32px;font-size:16px;" id="landing-hero-learn">
                            Learn More
                        </a>
                    </div>
                </div>

                <div class="col-lg-6 zc-hero-visual" id="landing-hero-visual">
                    <div class="zc-hero-card" id="landing-hero-card">
                        <div class="zc-hero-card-header" id="landing-hero-card-header">
                            <div class="zc-hero-card-dot" style="background:#10b981;"></div>
                            <div class="zc-hero-card-dot" style="background:#f59e0b;"></div>
                            <div class="zc-hero-card-dot" style="background:#ef4444;"></div>
                            <span style="margin-left:auto;font-size:13px;color:#999;">ZozoCal Products</span>
                        </div>
                        <div id="landing-hero-card-rows">
                            <div class="zc-hero-card-row" id="landing-hero-row-1">
                                <span style="width:40px;text-align:center;"><i class="feather-mic" style="font-size:18px;color:#8b5cf6;"></i></span>
                                <span style="flex:1;font-weight:600;">AI Receptionist</span>
                                <span class="zc-status-badge" style="background:#ede9fe;color:#7c3aed;">Voice AI</span>
                            </div>
                            <div class="zc-hero-card-row" id="landing-hero-row-2">
                                <span style="width:40px;text-align:center;"><i class="feather-calendar" style="font-size:18px;color:#2563eb;"></i></span>
                                <span style="flex:1;font-weight:600;">Restaurant Reservations</span>
                                <span class="zc-status-badge" style="background:#dbeafe;color:#2563eb;">Booking</span>
                            </div>
                            <div class="zc-hero-card-row" id="landing-hero-row-3">
                                <span style="width:40px;text-align:center;"><i class="feather-users" style="font-size:18px;color:#10b981;"></i></span>
                                <span style="flex:1;font-weight:600;">Staff Scheduling</span>
                                <span class="zc-status-badge" style="background:#dcfce7;color:#16a34a;">Workforce</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="zc-stats" id="landing-stats">
        <div class="container" id="landing-stats-container">
            <h3 class="text-center mb-4" id="landing-stats-heading">Make you and your staff more productive with AI Agents that work 24/7 and never need a break</h3>
            <div class="row" id="landing-stats-row">
                <div class="col-6 col-md-3" id="landing-stat-1">
                    <div class="zc-stat-card" id="landing-stat-card-1">
                        <div class="zc-stat-icon" id="landing-stat-icon-1">
                            <i class="feather-cpu"></i>
                        </div>
                        <h3>AI</h3>
                        <p>Enabled</p>
                    </div>
                </div>
                <div class="col-6 col-md-3" id="landing-stat-2">
                    <div class="zc-stat-card" id="landing-stat-card-2">
                        <div class="zc-stat-icon" id="landing-stat-icon-2">
                            <i class="feather-mic"></i>
                        </div>
                        <h3>Voice</h3>
                        <p>Agents</p>
                    </div>
                </div>
                <div class="col-6 col-md-3" id="landing-stat-3">
                    <div class="zc-stat-card" id="landing-stat-card-3">
                        <div class="zc-stat-icon" id="landing-stat-icon-3">
                            <i class="feather-message-square"></i>
                        </div>
                        <h3>SMS</h3>
                        <p>Text</p>
                    </div>
                </div>
                <div class="col-6 col-md-3" id="landing-stat-4">
                    <div class="zc-stat-card" id="landing-stat-card-4">
                        <div class="zc-stat-icon" id="landing-stat-icon-4">
                            <i class="feather-mail"></i>
                        </div>
                        <h3>Email</h3>
                        <p>Notifications</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="zc-features" id="features">
        <div class="container" id="landing-features-container">
            <div class="text-center" id="landing-features-header">
                <div class="zc-section-label" id="landing-features-label">Features</div>
                <h2 class="zc-section-title" id="landing-features-title">Everything you need to manage your schedules</h2>
                <p class="zc-section-subtitle" id="landing-features-subtitle">
                    From taking reservations to managing your guest relationships, ZozoCal has you covered.
                </p>
            </div>

            <div class="row g-4" id="landing-features-grid">
                <div class="col-md-6 col-lg-4" id="landing-feature-1">
                    <div class="zc-feature-card" id="landing-feature-card-1">
                        <div class="zc-feature-icon" style="background:linear-gradient(135deg,#667eea,#764ba2);" id="landing-feature-icon-1">
                            <i class="feather-calendar"></i>
                        </div>
                        <h4>Online Reservations</h4>
                        <p>Accept bookings 24/7 through your own branded booking page. Guests can reserve, modify, or cancel anytime without calling.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4" id="landing-feature-2">
                    <div class="zc-feature-card" id="landing-feature-card-2">
                        <div class="zc-feature-icon" style="background:linear-gradient(135deg,#10b981,#059669);" id="landing-feature-icon-2">
                            <i class="feather-grid"></i>
                        </div>
                        <h4>AI Voice Receptionist</h4>
                        <p>Have your own AI Voice Receptionist who knows your business and manages your calendar.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4" id="landing-feature-3">
                    <div class="zc-feature-card" id="landing-feature-card-3">
                        <div class="zc-feature-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);" id="landing-feature-icon-3">
                            <i class="feather-users"></i>
                        </div>
                        <h4>Client Relationship Management (CRM)</h4>
                        <p>Build client profiles with preferences, purchase history, and notes. Deliver personalized service every time.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4" id="landing-feature-4">
                    <div class="zc-feature-card" id="landing-feature-card-4">
                        <div class="zc-feature-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626);" id="landing-feature-icon-4">
                            <i class="feather-list"></i>
                        </div>
                        <h4>Waitlist/Walkin Management</h4>
                        <p>Manage walk-ins with a digital waitlist. Automatically notify guests via SMS when their table is ready.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4" id="landing-feature-5">
                    <div class="zc-feature-card" id="landing-feature-card-5">
                        <div class="zc-feature-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);" id="landing-feature-icon-5">
                            <i class="feather-bell"></i>
                        </div>
                        <h4>Smart Notifications</h4>
                        <p>Automated confirmation emails, SMS reminders, and no-show follow-ups. Reduce no-shows and keep guests informed.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4" id="landing-feature-6">
                    <div class="zc-feature-card" id="landing-feature-card-6">
                        <div class="zc-feature-icon" style="background:linear-gradient(135deg,#06b6d4,#0891b2);" id="landing-feature-icon-6">
                            <i class="feather-bar-chart-2"></i>
                        </div>
                        <h4>Reports & Analytics</h4>
                        <p>Track covers, no-show rates, peak hours, and table utilization. Data-driven insights to optimize your operations.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="zc-how" id="how-it-works">
        <div class="container" id="landing-how-container">
            <div class="text-center" id="landing-how-header">
                <div class="zc-section-label" id="landing-how-label">How It Works</div>
                <h2 class="zc-section-title" id="landing-how-title">ZozoCal for Restaurants</h2>
                <p class="zc-section-subtitle" id="landing-how-subtitle">
                    Get your restaurant set up on ZozoCal in three simple steps.
                </p>
            </div>

            <div class="row" id="landing-how-steps">
                <div class="col-md-4" id="landing-step-1">
                    <div class="zc-step" id="landing-step-card-1">
                        <div class="zc-step-number" id="landing-step-num-1">1</div>
                        <div class="zc-step-connector" id="landing-step-conn-1"></div>
                        <h4>Set Up Your Restaurant</h4>
                        <p>Add your tables, sections, operating hours, and reservation rules. Configure your floor plan in minutes.</p>
                    </div>
                </div>
                <div class="col-md-4" id="landing-step-2">
                    <div class="zc-step" id="landing-step-card-2">
                        <div class="zc-step-number" id="landing-step-num-2">2</div>
                        <div class="zc-step-connector" id="landing-step-conn-2"></div>
                        <h4>Accept Reservations</h4>
                        <p>Share your booking page with guests. Accept online reservations, phone bookings, and walk-ins from one dashboard.</p>
                    </div>
                </div>
                <div class="col-md-4" id="landing-step-3">
                    <div class="zc-step" id="landing-step-card-3">
                        <div class="zc-step-number" id="landing-step-num-3">3</div>
                        <h4>Manage & Grow</h4>
                        <p>Track guest preferences, analyze performance, and optimize your seating to maximize covers every service.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="zc-benefits" id="landing-benefits">
        <div class="container" id="landing-benefits-container">
            <div class="zc-benefit-block" id="landing-benefit-1">
                <div class="row align-items-center" id="landing-benefit-row-1">
                    <div class="col-lg-6" id="landing-benefit-text-1">
                        <div class="zc-section-label">For Your Guests</div>
                        <h3>Effortless booking experience</h3>
                        <p>Give your guests a beautiful, mobile-friendly booking page where they can see real-time availability, choose their preferred time, and book in seconds.</p>
                        <ul class="zc-check-list" id="landing-benefit-checks-1">
                            <li><i class="feather-check-circle"></i> Real-time availability display</li>
                            <li><i class="feather-check-circle"></i> Self-service modify and cancel</li>
                            <li><i class="feather-check-circle"></i> Automatic confirmation emails</li>
                            <li><i class="feather-check-circle"></i> Mobile-optimized booking page</li>
                        </ul>
                    </div>
                    <div class="col-lg-6" id="landing-benefit-visual-1">
                        <div class="zc-benefit-visual" id="landing-benefit-card-1">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;" id="landing-benefit-card-header-1">
                                <div style="width:40px;height:40px;border-radius:10px;background:var(--zc-gradient);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;">
                                    <i class="feather-calendar"></i>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:15px;color:var(--zc-dark);">Book a Table</div>
                                    <div style="font-size:12px;color:#999;">Select your preferred time</div>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;" id="landing-benefit-slots-1">
                                <span style="padding:8px 16px;border-radius:8px;border:1px solid #e5e7eb;font-size:13px;font-weight:600;color:var(--zc-gray);">5:30 PM</span>
                                <span style="padding:8px 16px;border-radius:8px;border:1px solid #e5e7eb;font-size:13px;font-weight:600;color:var(--zc-gray);">6:00 PM</span>
                                <span style="padding:8px 16px;border-radius:8px;background:var(--zc-gradient);font-size:13px;font-weight:600;color:#fff;">6:30 PM</span>
                                <span style="padding:8px 16px;border-radius:8px;border:1px solid #e5e7eb;font-size:13px;font-weight:600;color:var(--zc-gray);">7:00 PM</span>
                                <span style="padding:8px 16px;border-radius:8px;border:1px solid #e5e7eb;font-size:13px;font-weight:600;color:var(--zc-gray);">7:30 PM</span>
                                <span style="padding:8px 16px;border-radius:8px;border:1px solid #e5e7eb;font-size:13px;font-weight:600;color:var(--zc-gray);">8:00 PM</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="zc-benefit-block" id="landing-benefit-2">
                <div class="row align-items-center flex-lg-row-reverse" id="landing-benefit-row-2">
                    <div class="col-lg-6" id="landing-benefit-text-2">
                        <div class="zc-section-label">For Your Team</div>
                        <h3>Streamline front-of-house operations</h3>
                        <p>Your host team gets a powerful dashboard to manage the entire service. Smart table assignments, real-time status updates, and a full guest database at their fingertips.</p>
                        <ul class="zc-check-list" id="landing-benefit-checks-2">
                            <li><i class="feather-check-circle"></i> Smart table auto-assignment</li>
                            <li><i class="feather-check-circle"></i> Guest preference tracking</li>
                            <li><i class="feather-check-circle"></i> Digital waitlist with SMS alerts</li>
                            <li><i class="feather-check-circle"></i> Role-based staff access</li>
                        </ul>
                    </div>
                    <div class="col-lg-6" id="landing-benefit-visual-2">
                        <div class="zc-benefit-visual" id="landing-benefit-card-2">
                            <div style="font-weight:700;font-size:15px;color:var(--zc-dark);margin-bottom:16px;" id="landing-benefit-card-title-2">Tonight's Dashboard</div>
                            <div class="row g-3" id="landing-benefit-mini-stats">
                                <div class="col-6" id="landing-mini-stat-1">
                                    <div style="background:var(--zc-light);border-radius:10px;padding:16px;text-align:center;">
                                        <div style="font-size:24px;font-weight:800;color:var(--zc-primary);">28</div>
                                        <div style="font-size:12px;color:#999;">Reservations</div>
                                    </div>
                                </div>
                                <div class="col-6" id="landing-mini-stat-2">
                                    <div style="background:var(--zc-light);border-radius:10px;padding:16px;text-align:center;">
                                        <div style="font-size:24px;font-weight:800;color:#10b981;">86</div>
                                        <div style="font-size:12px;color:#999;">Covers</div>
                                    </div>
                                </div>
                                <div class="col-6" id="landing-mini-stat-3">
                                    <div style="background:var(--zc-light);border-radius:10px;padding:16px;text-align:center;">
                                        <div style="font-size:24px;font-weight:800;color:#f59e0b;">5</div>
                                        <div style="font-size:12px;color:#999;">Waitlist</div>
                                    </div>
                                </div>
                                <div class="col-6" id="landing-mini-stat-4">
                                    <div style="background:var(--zc-light);border-radius:10px;padding:16px;text-align:center;">
                                        <div style="font-size:24px;font-weight:800;color:#8b5cf6;">92%</div>
                                        <div style="font-size:12px;color:#999;">Utilization</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="zc-cta" id="landing-cta">
        <div class="container position-relative" style="z-index:1;" id="landing-cta-container">
            <h2 id="landing-cta-title">Ready to fill more tables?</h2>
            <p id="landing-cta-subtitle">Join restaurants using ZozoCal to streamline reservations and delight guests.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap" id="landing-cta-actions">
                <a href="/register.php" class="btn-zc-white" id="landing-cta-start">Get Started Free</a>
                <a href="/login.php" class="btn-zc-primary btn-zc-primary-lg" style="background:rgba(255,255,255,0.15);border:2px solid #fff;" id="landing-cta-login">Login</a>
            </div>
        </div>
    </section>

    <footer class="zc-footer" id="landing-footer">
        <div class="container" id="landing-footer-container">
            <div class="row" id="landing-footer-row">
                <div class="col-lg-4 mb-4" id="landing-footer-brand-col">
                    <div class="zc-footer-brand" id="landing-footer-brand">ZozoCal</div>
                    <p id="landing-footer-desc">The modern restaurant reservation platform. Manage bookings, tables, and guest relationships — all in one place.</p>
                </div>
                <div class="col-6 col-lg-2 mb-4" id="landing-footer-product-col">
                    <h6 id="landing-footer-product-title">Product</h6>
                    <ul class="zc-footer-links" id="landing-footer-product-links">
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="/login.php">Login</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2 mb-4" id="landing-footer-features-col">
                    <h6 id="landing-footer-features-title">Features</h6>
                    <ul class="zc-footer-links" id="landing-footer-features-links">
                        <li><a href="#features">Reservations</a></li>
                        <li><a href="#features">Table Management</a></li>
                        <li><a href="#features">Client CRM</a></li>
                        <li><a href="#features">Analytics</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2 mb-4" id="landing-footer-legal-col">
                    <h6 id="landing-footer-legal-title">Legal</h6>
                    <ul class="zc-footer-links" id="landing-footer-legal-links">
                        <li><a href="/terms.php">Terms of Service</a></li>
                        <li><a href="/privacy.php">Privacy Policy</a></li>
                        <li><a href="mailto:support@zozocal.com">support@zozocal.com</a></li>
                    </ul>
                </div>
            </div>
            <div class="zc-footer-bottom text-center" id="landing-footer-bottom">
                &copy; <?php echo date('Y'); ?> Kinetic Seas Incorporated (OTCQB: KSEZ). All rights reserved.
                &nbsp;&middot;&nbsp; <a href="/terms.php" style="color:rgba(255,255,255,0.5);">Terms</a>
                &nbsp;&middot;&nbsp; <a href="/privacy.php" style="color:rgba(255,255,255,0.5);">Privacy</a>
            </div>
        </div>
    </footer>

    <script src="assets/vendor/jquery.min.js"></script>
    <script src="assets/vendor/bootstrap.min.js"></script>

    <script>
    window.addEventListener('scroll', function() {
        var navbar = document.getElementById('landing-navbar');
        if (window.scrollY > 20) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    </script>
</body>
</html>
