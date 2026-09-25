<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Coming Soon</title>
    <meta name="description" content="This site is coming soon." />

    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon-vt.png" />
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/vendor/feather.min.css" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --coming-bg: #f3efe6;
            --coming-panel: rgba(255, 255, 255, 0.78);
            --coming-text: #1e293b;
            --coming-muted: #64748b;
            --coming-accent: #b45309;
            --coming-accent-dark: #92400e;
            --coming-border: rgba(180, 83, 9, 0.14);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--coming-text);
            background:
                radial-gradient(circle at top left, rgba(245, 158, 11, 0.18), transparent 28%),
                radial-gradient(circle at bottom right, rgba(251, 191, 36, 0.16), transparent 32%),
                linear-gradient(135deg, #f8f5ee 0%, var(--coming-bg) 100%);
        }

        .coming-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
        }

        .coming-panel {
            width: 100%;
            max-width: 720px;
            padding: 48px 40px;
            border-radius: 28px;
            background: var(--coming-panel);
            border: 1px solid var(--coming-border);
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.10);
            backdrop-filter: blur(14px);
            text-align: center;
        }

        .coming-kicker {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(180, 83, 9, 0.10);
            color: var(--coming-accent-dark);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .coming-title {
            margin: 0 0 16px;
            font-size: clamp(40px, 7vw, 72px);
            line-height: 0.95;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .coming-subtitle {
            max-width: 560px;
            margin: 0 auto 28px;
            font-size: 18px;
            line-height: 1.7;
            color: var(--coming-muted);
        }

        .coming-host {
            margin: 0 auto 34px;
            display: inline-block;
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(148, 163, 184, 0.18);
            color: var(--coming-text);
            font-size: 14px;
            font-weight: 600;
        }

        .coming-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .coming-btn-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 170px;
            padding: 14px 24px;
            border-radius: 14px;
            border: none;
            background: linear-gradient(135deg, var(--coming-accent) 0%, #f59e0b 100%);
            color: #fff;
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .coming-btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(180, 83, 9, 0.24);
            color: #fff;
        }

        .coming-note {
            margin-top: 24px;
            font-size: 13px;
            color: var(--coming-muted);
        }

        @media (max-width: 640px) {
            .coming-panel {
                padding: 38px 24px;
                border-radius: 22px;
            }

            .coming-subtitle {
                font-size: 16px;
            }
        }
    </style>
</head>
<body id="coming-body">
    <main class="coming-shell" id="coming-shell">
        <div class="coming-panel" id="coming-panel">
            <div class="coming-kicker" id="coming-kicker">
                <i class="feather-clock" aria-hidden="true"></i>
                <span id="coming-kicker-text">Launching Soon</span>
            </div>

            <h1 class="coming-title" id="coming-title">Coming Soon</h1>

            <p class="coming-subtitle" id="coming-subtitle">
                This landing page has not been configured yet. The application is available, and you can still sign in below.
            </p>

            <div class="coming-host" id="coming-host">
                <?php echo htmlspecialchars(currentLandingRequestHost() ?: 'Unmapped Host'); ?>
            </div>

            <div class="coming-actions" id="coming-actions">
                <a href="/sms-signup.php" class="coming-btn-login" id="coming-btn-reservation" style="background:linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                    <i class="feather-calendar" aria-hidden="true"></i>
                    <span id="coming-btn-reservation-text">Make a Reservation</span>
                </a>
                <a href="/login.php" class="coming-btn-login" id="coming-btn-login">
                    <i class="feather-log-in" aria-hidden="true"></i>
                    <span id="coming-btn-login-text">Login</span>
                </a>
            </div>

            <div class="coming-note" id="coming-note">
                A domain-specific landing page can be added later through the `landing_page_routes` table.
            </div>
        </div>
    </main>
</body>
</html>
