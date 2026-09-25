<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Arrive Michigan Avenue - Chicago, IL | Apartments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --brand-green: #00a84f;
            --brand-dark: #1a1a2e;
            --brand-gray: #6c757d;
            --brand-light: #f8f9fa;
        }

        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; color: #333; }

        /* Top Nav */
        .demo-topnav {
            background: var(--brand-dark);
            padding: 0.75rem 0;
        }
        .demo-topnav .brand {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
        }
        .demo-topnav .brand span { color: var(--brand-green); }

        /* Hero Gallery */
        .hero-gallery {
            position: relative;
            background: #000;
            max-height: 480px;
            overflow: hidden;
        }
        .hero-gallery img {
            width: 100%;
            height: 480px;
            object-fit: cover;
            opacity: 0.9;
        }
        .hero-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            padding: 2rem;
            color: #fff;
        }
        .hero-overlay h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.25rem; }
        .hero-overlay .address { font-size: 1.1rem; opacity: 0.9; }
        .gallery-badge {
            position: absolute;
            bottom: 1rem;
            right: 1rem;
            background: rgba(0,0,0,0.6);
            color: #fff;
            padding: 0.4rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        /* Pricing Bar */
        .pricing-bar {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .pricing-bar .price-range {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--brand-dark);
        }
        .pricing-bar .beds-info {
            color: var(--brand-gray);
            font-size: 0.95rem;
        }

        /* CTA Button */
        .btn-cta-call {
            background: var(--brand-green);
            color: #fff;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1.05rem;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-cta-call:hover {
            background: #008f43;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,168,79,0.3);
        }
        .btn-cta-call:active { transform: translateY(0); }

        /* Section Tabs */
        .section-tabs {
            border-bottom: 2px solid #dee2e6;
            background: #fff;
        }
        .section-tabs .nav-link {
            color: var(--brand-gray);
            font-weight: 600;
            padding: 0.75rem 1.25rem;
            border: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        .section-tabs .nav-link.active {
            color: var(--brand-green);
            border-bottom-color: var(--brand-green);
            background: transparent;
        }

        /* Content Sections */
        .content-section { padding: 2rem 0; }
        .content-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--brand-dark);
            margin-bottom: 1.25rem;
        }

        /* Amenity Grid */
        .amenity-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            font-size: 0.95rem;
        }
        .amenity-item i { color: var(--brand-green); font-size: 1.1rem; }

        /* Floor Plan Cards */
        .floorplan-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.25rem;
            transition: box-shadow 0.2s;
        }
        .floorplan-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .floorplan-card .fp-type { font-weight: 700; font-size: 1.1rem; color: var(--brand-dark); }
        .floorplan-card .fp-price { font-weight: 700; color: var(--brand-green); font-size: 1.15rem; }
        .floorplan-card .fp-details { color: var(--brand-gray); font-size: 0.9rem; }

        /* Contact Sidebar */
        .contact-sidebar {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 1.5rem;
            position: sticky;
            top: 80px;
        }
        .contact-sidebar h5 { font-weight: 700; color: var(--brand-dark); }

        /* Map placeholder */
        .map-placeholder {
            background: #e9ecef;
            border-radius: 8px;
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brand-gray);
        }

        /* Retell Overlay (same as main app) */
        .retell-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .retell-overlay-content {
            background: #fff; border-radius: 1rem; padding: 2.5rem 2rem;
            text-align: center; max-width: 420px; width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .retell-pulse-ring {
            width: 90px; height: 90px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.75rem; color: #fff; background: var(--brand-green);
        }
        .retell-pulse-ring.connecting { animation: retell-pulse 1.5s ease-in-out infinite; }
        .retell-pulse-ring.active { background: #22c55e; animation: retell-pulse 2s ease-in-out infinite; }
        .retell-pulse-ring.agent_talking { background: #3b82f6; animation: retell-pulse 0.8s ease-in-out infinite; }
        .retell-pulse-ring.ended { background: #6b7280; animation: none; }
        .retell-pulse-ring.error { background: #ef4444; animation: none; }
        @keyframes retell-pulse {
            0% { box-shadow: 0 0 0 0 rgba(0,168,79,0.4); }
            70% { box-shadow: 0 0 0 25px rgba(0,168,79,0); }
            100% { box-shadow: 0 0 0 0 rgba(0,168,79,0); }
        }
        .retell-overlay-status { margin: 1rem 0 0.5rem; font-size: 1.05rem; font-weight: 500; color: #374151; }
        .retell-overlay-timer { margin-bottom: 1rem; font-size: 2.25rem; font-weight: 600; color: #111827; }
        .retell-context-line { font-size: 0.85rem; color: #6b7280; padding: 0.15rem 0; }
        .retell-overlay-context {
            background: #f9fafb; border-radius: 0.5rem;
            padding: 0.75rem 1rem; margin-bottom: 1.5rem; text-align: left;
        }

        /* Review stars */
        .star-filled { color: #ffc107; }
    </style>
</head>
<body>

    <!-- Top Navigation -->
    <nav class="demo-topnav" id="demo-topnav">
        <div class="container" id="demo-topnav-container">
            <a href="#" class="brand" id="demo-brand">apartments<span>.com</span></a>
        </div>
    </nav>

    <!-- Hero Gallery -->
    <div class="hero-gallery" id="demo-hero">
        <img src="https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=1400&h=480&fit=crop"
             alt="Arrive Michigan Avenue" id="demo-hero-img">
        <div class="hero-overlay" id="demo-hero-overlay">
            <h1 id="demo-hero-title">Arrive Michigan Avenue</h1>
            <div class="address" id="demo-hero-address">
                <i class="bi bi-geo-alt me-1"></i> 200 E Illinois St, Chicago, IL 60611
            </div>
        </div>
        <div class="gallery-badge" id="demo-gallery-badge">
            <i class="bi bi-images me-1"></i> 42 Photos
        </div>
    </div>

    <!-- Pricing Bar -->
    <div class="pricing-bar" id="demo-pricing-bar">
        <div class="container" id="demo-pricing-container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3" id="demo-pricing-row">
                <div id="demo-pricing-info">
                    <div class="price-range" id="demo-price-range">$2,265 - $7,508</div>
                    <div class="beds-info" id="demo-beds-info">Studio - 3 Beds | 456 - 1,684 Sq Ft</div>
                </div>
                <div class="d-flex gap-2 align-items-center" id="demo-pricing-actions">
                    <span class="text-muted me-2 d-none d-md-inline" id="demo-pricing-label">
                        <i class="bi bi-telephone-fill me-1"></i> Talk to a leasing agent
                    </span>
                    <button class="btn-cta-call" id="demo-call-btn"
                            onclick="startRetellCall()">
                        <i class="bi bi-telephone-fill me-2"></i> Speak with AI Agent
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Tabs -->
    <div class="section-tabs" id="demo-tabs">
        <div class="container" id="demo-tabs-container">
            <ul class="nav" id="demo-tabs-nav">
                <li class="nav-item" id="demo-tab-overview"><a class="nav-link active" href="#overview">Overview</a></li>
                <li class="nav-item" id="demo-tab-floorplans"><a class="nav-link" href="#floorplans">Floor Plans</a></li>
                <li class="nav-item" id="demo-tab-amenities"><a class="nav-link" href="#amenities">Amenities</a></li>
                <li class="nav-item" id="demo-tab-location"><a class="nav-link" href="#location">Location</a></li>
                <li class="nav-item" id="demo-tab-reviews"><a class="nav-link" href="#reviews">Reviews</a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container py-4" id="demo-content">
        <div class="row" id="demo-content-row">

            <!-- Left Content -->
            <div class="col-lg-8" id="demo-content-left">

                <!-- Overview -->
                <section class="content-section" id="overview">
                    <h2 id="demo-overview-title">About Arrive Michigan Avenue</h2>
                    <p id="demo-overview-text">
                        Arrive Michigan Avenue offers luxury apartment living in the heart of Chicago's Streeterville neighborhood.
                        Located steps from the Magnificent Mile, Navy Pier, and Lake Michigan, residents enjoy an unparalleled urban lifestyle.
                        Our pet-friendly community features thoughtfully designed studio, one, two, and three-bedroom apartments
                        with high-end finishes, floor-to-ceiling windows, and breathtaking city views.
                    </p>
                    <div class="row g-3 mb-3" id="demo-highlights-row">
                        <div class="col-sm-6 col-md-3" id="demo-highlight-1">
                            <div class="border rounded p-3 text-center" id="demo-highlight-card-1">
                                <i class="bi bi-building fs-3 text-muted" id="demo-highlight-icon-1"></i>
                                <div class="fw-bold mt-1" id="demo-highlight-val-1">44 Floors</div>
                                <small class="text-muted" id="demo-highlight-lbl-1">High-Rise</small>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3" id="demo-highlight-2">
                            <div class="border rounded p-3 text-center" id="demo-highlight-card-2">
                                <i class="bi bi-calendar-check fs-3 text-muted" id="demo-highlight-icon-2"></i>
                                <div class="fw-bold mt-1" id="demo-highlight-val-2">Built 2020</div>
                                <small class="text-muted" id="demo-highlight-lbl-2">Year Built</small>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3" id="demo-highlight-3">
                            <div class="border rounded p-3 text-center" id="demo-highlight-card-3">
                                <i class="bi bi-door-open fs-3 text-muted" id="demo-highlight-icon-3"></i>
                                <div class="fw-bold mt-1" id="demo-highlight-val-3">698 Units</div>
                                <small class="text-muted" id="demo-highlight-lbl-3">Apartments</small>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3" id="demo-highlight-4">
                            <div class="border rounded p-3 text-center" id="demo-highlight-card-4">
                                <i class="bi bi-shield-check fs-3 text-muted" id="demo-highlight-icon-4"></i>
                                <div class="fw-bold mt-1" id="demo-highlight-val-4">Pet Friendly</div>
                                <small class="text-muted" id="demo-highlight-lbl-4">Dogs & Cats</small>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Floor Plans -->
                <section class="content-section border-top" id="floorplans">
                    <h2 id="demo-fp-title">Floor Plans & Pricing</h2>
                    <div class="row g-3" id="demo-fp-row">
                        <div class="col-md-6" id="demo-fp-col-1">
                            <div class="floorplan-card" id="demo-fp-card-1">
                                <div class="d-flex justify-content-between align-items-start" id="demo-fp-header-1">
                                    <div id="demo-fp-info-1">
                                        <div class="fp-type" id="demo-fp-type-1">Studio</div>
                                        <div class="fp-details" id="demo-fp-details-1">1 Bath | 456 - 583 Sq Ft</div>
                                    </div>
                                    <div class="fp-price" id="demo-fp-price-1">$2,265+</div>
                                </div>
                                <div class="mt-2" id="demo-fp-avail-1">
                                    <span class="badge bg-success">3 Available</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6" id="demo-fp-col-2">
                            <div class="floorplan-card" id="demo-fp-card-2">
                                <div class="d-flex justify-content-between align-items-start" id="demo-fp-header-2">
                                    <div id="demo-fp-info-2">
                                        <div class="fp-type" id="demo-fp-type-2">1 Bedroom</div>
                                        <div class="fp-details" id="demo-fp-details-2">1 Bath | 618 - 918 Sq Ft</div>
                                    </div>
                                    <div class="fp-price" id="demo-fp-price-2">$2,750+</div>
                                </div>
                                <div class="mt-2" id="demo-fp-avail-2">
                                    <span class="badge bg-success">7 Available</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6" id="demo-fp-col-3">
                            <div class="floorplan-card" id="demo-fp-card-3">
                                <div class="d-flex justify-content-between align-items-start" id="demo-fp-header-3">
                                    <div id="demo-fp-info-3">
                                        <div class="fp-type" id="demo-fp-type-3">2 Bedrooms</div>
                                        <div class="fp-details" id="demo-fp-details-3">2 Bath | 1,095 - 1,300 Sq Ft</div>
                                    </div>
                                    <div class="fp-price" id="demo-fp-price-3">$4,380+</div>
                                </div>
                                <div class="mt-2" id="demo-fp-avail-3">
                                    <span class="badge bg-success">5 Available</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6" id="demo-fp-col-4">
                            <div class="floorplan-card" id="demo-fp-card-4">
                                <div class="d-flex justify-content-between align-items-start" id="demo-fp-header-4">
                                    <div id="demo-fp-info-4">
                                        <div class="fp-type" id="demo-fp-type-4">3 Bedrooms</div>
                                        <div class="fp-details" id="demo-fp-details-4">2 Bath | 1,450 - 1,684 Sq Ft</div>
                                    </div>
                                    <div class="fp-price" id="demo-fp-price-4">$7,508+</div>
                                </div>
                                <div class="mt-2" id="demo-fp-avail-4">
                                    <span class="badge bg-warning text-dark">1 Available</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Amenities -->
                <section class="content-section border-top" id="amenities">
                    <h2 id="demo-amenities-title">Amenities</h2>
                    <div class="row" id="demo-amenities-row">
                        <div class="col-md-4" id="demo-amenities-col-1">
                            <h6 class="fw-bold text-muted mb-2" id="demo-amenities-cat-1">Community</h6>
                            <div class="amenity-item" id="demo-amenity-1"><i class="bi bi-water"></i> Rooftop Pool & Hot Tub</div>
                            <div class="amenity-item" id="demo-amenity-2"><i class="bi bi-fire"></i> Outdoor Fire Pits</div>
                            <div class="amenity-item" id="demo-amenity-3"><i class="bi bi-cup-hot"></i> Resident Lounge & Bar</div>
                            <div class="amenity-item" id="demo-amenity-4"><i class="bi bi-people"></i> Co-Working Space</div>
                            <div class="amenity-item" id="demo-amenity-5"><i class="bi bi-box-seam"></i> Package Lockers</div>
                            <div class="amenity-item" id="demo-amenity-6"><i class="bi bi-bicycle"></i> Bike Storage</div>
                        </div>
                        <div class="col-md-4" id="demo-amenities-col-2">
                            <h6 class="fw-bold text-muted mb-2" id="demo-amenities-cat-2">Fitness & Wellness</h6>
                            <div class="amenity-item" id="demo-amenity-7"><i class="bi bi-heart-pulse"></i> State-of-the-Art Gym</div>
                            <div class="amenity-item" id="demo-amenity-8"><i class="bi bi-person-arms-up"></i> Yoga Studio</div>
                            <div class="amenity-item" id="demo-amenity-9"><i class="bi bi-dribbble"></i> Sports Simulator</div>
                            <div class="amenity-item" id="demo-amenity-10"><i class="bi bi-flower1"></i> Spa & Sauna</div>
                        </div>
                        <div class="col-md-4" id="demo-amenities-col-3">
                            <h6 class="fw-bold text-muted mb-2" id="demo-amenities-cat-3">Apartment Features</h6>
                            <div class="amenity-item" id="demo-amenity-11"><i class="bi bi-aspect-ratio"></i> Floor-to-Ceiling Windows</div>
                            <div class="amenity-item" id="demo-amenity-12"><i class="bi bi-snow"></i> In-Unit Washer/Dryer</div>
                            <div class="amenity-item" id="demo-amenity-13"><i class="bi bi-ev-front"></i> Quartz Countertops</div>
                            <div class="amenity-item" id="demo-amenity-14"><i class="bi bi-lamp"></i> Smart Home Ready</div>
                            <div class="amenity-item" id="demo-amenity-15"><i class="bi bi-columns-gap"></i> Walk-In Closets</div>
                            <div class="amenity-item" id="demo-amenity-16"><i class="bi bi-wind"></i> Central AC & Heat</div>
                        </div>
                    </div>
                </section>

                <!-- Location -->
                <section class="content-section border-top" id="location">
                    <h2 id="demo-location-title">Location</h2>
                    <div class="map-placeholder mb-3" id="demo-map">
                        <div class="text-center" id="demo-map-inner">
                            <i class="bi bi-geo-alt fs-1"></i>
                            <p class="mt-2 mb-0">200 E Illinois St, Chicago, IL 60611</p>
                            <small>Streeterville | Near North Side</small>
                        </div>
                    </div>
                    <div class="row g-3" id="demo-nearby-row">
                        <div class="col-md-4" id="demo-nearby-col-1">
                            <h6 class="fw-bold" id="demo-nearby-cat-1"><i class="bi bi-train-front me-1"></i> Transit</h6>
                            <div class="small text-muted" id="demo-nearby-1">Grand Red Line - 0.4 mi</div>
                            <div class="small text-muted" id="demo-nearby-2">Chicago Brown/Purple - 0.5 mi</div>
                        </div>
                        <div class="col-md-4" id="demo-nearby-col-2">
                            <h6 class="fw-bold" id="demo-nearby-cat-2"><i class="bi bi-bag me-1"></i> Shopping</h6>
                            <div class="small text-muted" id="demo-nearby-3">Magnificent Mile - 0.2 mi</div>
                            <div class="small text-muted" id="demo-nearby-4">Water Tower Place - 0.5 mi</div>
                        </div>
                        <div class="col-md-4" id="demo-nearby-col-3">
                            <h6 class="fw-bold" id="demo-nearby-cat-3"><i class="bi bi-tree me-1"></i> Parks</h6>
                            <div class="small text-muted" id="demo-nearby-5">Lake Shore Trail - 0.3 mi</div>
                            <div class="small text-muted" id="demo-nearby-6">Navy Pier - 0.6 mi</div>
                        </div>
                    </div>
                </section>

                <!-- Reviews -->
                <section class="content-section border-top" id="reviews">
                    <h2 id="demo-reviews-title">Resident Reviews</h2>
                    <div class="d-flex align-items-center mb-3" id="demo-reviews-summary">
                        <span class="fs-3 fw-bold me-2" id="demo-reviews-score">4.3</span>
                        <span id="demo-reviews-stars">
                            <i class="bi bi-star-fill star-filled"></i>
                            <i class="bi bi-star-fill star-filled"></i>
                            <i class="bi bi-star-fill star-filled"></i>
                            <i class="bi bi-star-fill star-filled"></i>
                            <i class="bi bi-star-half star-filled"></i>
                        </span>
                        <span class="text-muted ms-2" id="demo-reviews-count">(128 Reviews)</span>
                    </div>
                    <div class="border rounded p-3 mb-3" id="demo-review-1">
                        <div class="d-flex justify-content-between" id="demo-review-1-header">
                            <strong id="demo-review-1-author">Sarah M.</strong>
                            <span class="text-muted small" id="demo-review-1-date">2 weeks ago</span>
                        </div>
                        <div class="my-1" id="demo-review-1-stars">
                            <i class="bi bi-star-fill star-filled"></i><i class="bi bi-star-fill star-filled"></i><i class="bi bi-star-fill star-filled"></i><i class="bi bi-star-fill star-filled"></i><i class="bi bi-star-fill star-filled"></i>
                        </div>
                        <p class="mb-0 small" id="demo-review-1-text">Amazing views and great amenities. The rooftop pool is incredible in the summer. Staff is always helpful and responsive to maintenance requests.</p>
                    </div>
                    <div class="border rounded p-3" id="demo-review-2">
                        <div class="d-flex justify-content-between" id="demo-review-2-header">
                            <strong id="demo-review-2-author">James K.</strong>
                            <span class="text-muted small" id="demo-review-2-date">1 month ago</span>
                        </div>
                        <div class="my-1" id="demo-review-2-stars">
                            <i class="bi bi-star-fill star-filled"></i><i class="bi bi-star-fill star-filled"></i><i class="bi bi-star-fill star-filled"></i><i class="bi bi-star-fill star-filled"></i><i class="bi bi-star star-filled"></i>
                        </div>
                        <p class="mb-0 small" id="demo-review-2-text">Love the location — walking distance to everything. The gym is top-notch. Only downside is parking can be pricey.</p>
                    </div>
                </section>
            </div>

            <!-- Right Sidebar -->
            <div class="col-lg-4" id="demo-content-right">
                <div class="contact-sidebar" id="demo-contact-sidebar">
                    <h5 class="mb-3" id="demo-contact-title">Contact This Property</h5>

                    <div class="text-center mb-3" id="demo-contact-cta">
                        <button class="btn-cta-call w-100 mb-2" id="demo-sidebar-call-btn"
                                onclick="startRetellCall()">
                            <i class="bi bi-telephone-fill me-2"></i> Speak with AI Agent
                        </button>
                        <small class="text-muted" id="demo-contact-hours">Available 24/7 via AI</small>
                    </div>

                    <hr>

                    <div class="mb-3" id="demo-contact-form">
                        <h6 class="fw-bold mb-2" id="demo-contact-form-title">Or send a message</h6>
                        <div class="mb-2" id="demo-contact-name-group">
                            <input type="text" class="form-control form-control-sm" placeholder="Full Name" id="demo-contact-name">
                        </div>
                        <div class="mb-2" id="demo-contact-email-group">
                            <input type="email" class="form-control form-control-sm" placeholder="Email Address" id="demo-contact-email">
                        </div>
                        <div class="mb-2" id="demo-contact-phone-group">
                            <input type="tel" class="form-control form-control-sm" placeholder="Phone Number" id="demo-contact-phone">
                        </div>
                        <div class="mb-2" id="demo-contact-message-group">
                            <textarea class="form-control form-control-sm" rows="3" placeholder="I'm interested in scheduling a tour..." id="demo-contact-message"></textarea>
                        </div>
                        <button class="btn btn-outline-dark w-100 btn-sm" id="demo-contact-send-btn">Send Message</button>
                    </div>

                    <hr>

                    <div id="demo-contact-details">
                        <div class="small mb-2" id="demo-contact-address-line">
                            <i class="bi bi-geo-alt me-2 text-muted"></i> 200 E Illinois St, Chicago, IL 60611
                        </div>
                        <div class="small mb-2" id="demo-contact-phone-line">
                            <i class="bi bi-telephone me-2 text-muted"></i> (312) 555-0199
                        </div>
                        <div class="small" id="demo-contact-hours-line">
                            <i class="bi bi-clock me-2 text-muted"></i> Mon-Sat 9am-6pm, Sun 12-5pm
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-4" id="demo-footer">
        <div class="container text-center" id="demo-footer-container">
            <p class="mb-0 small" id="demo-footer-text">Retell Voice Agent Demo &mdash; This is a demonstration page.</p>
        </div>
    </footer>

    <!-- Retell Call Overlay (hidden) -->
    <div id="retell-call-overlay" style="display:none;" class="retell-overlay">
        <div class="retell-overlay-content" id="retell-overlay-content">
            <div id="retell-overlay-header">
                <div class="retell-pulse-ring connecting" id="retell-pulse-ring">
                    <i class="bi bi-telephone-fill"></i>
                </div>
                <h5 class="mt-3 mb-1" id="retell-call-title">Arrive Michigan Avenue</h5>
                <p class="text-muted mb-0" id="retell-call-subtitle">AI Leasing Agent</p>
            </div>
            <div class="retell-overlay-status" id="retell-overlay-status">
                <span id="retell-status-text">Connecting...</span>
            </div>
            <div class="retell-overlay-timer" id="retell-overlay-timer" style="display:none;">
                <span id="retell-call-timer" class="font-monospace">00:00</span>
            </div>
            <div class="retell-overlay-context" id="retell-overlay-context">
                <div class="retell-context-line">Property: Arrive Michigan Avenue</div>
                <div class="retell-context-line">Location: 200 E Illinois St, Chicago, IL</div>
                <div class="retell-context-line">Units: Studio - 3 Bed | $2,265 - $7,508</div>
            </div>
            <div id="retell-overlay-actions">
                <button class="btn btn-danger btn-lg rounded-pill px-5" id="retell-end-call-btn" onclick="endRetellCall()">
                    <i class="bi bi-telephone-x-fill me-2"></i> End Call
                </button>
            </div>
        </div>
    </div>

    <!-- Retell Web SDK -->
    <script src="assets/js/retell-sdk-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    (function() {
        'use strict';

        var AGENT_ID = 'agent_34e6ac5fc8792f0d02ccea1e9a';
        var retellClient = null;
        var callStartTime = null;
        var callTimerInterval = null;

        // Initialize SDK
        function init() {
            if (typeof retellClientJsSdk === 'undefined' || !retellClientJsSdk.RetellWebClient) {
                console.error('Retell SDK not loaded');
                return;
            }
            retellClient = new retellClientJsSdk.RetellWebClient();

            retellClient.on('call_started', function() {
                updateStatus('active', 'Call Connected');
                startTimer();
            });

            retellClient.on('call_ended', function() {
                updateStatus('ended', 'Call Ended');
                stopTimer();
                setTimeout(hideOverlay, 1500);
            });

            retellClient.on('agent_start_talking', function() {
                updateStatus('agent_talking', 'Agent Speaking...');
            });

            retellClient.on('agent_stop_talking', function() {
                updateStatus('active', 'Listening...');
            });

            retellClient.on('error', function(error) {
                console.error('Retell error:', error);
                updateStatus('error', 'Call error occurred');
                stopTimer();
                setTimeout(hideOverlay, 2500);
            });
        }

        // Start call
        window.startRetellCall = async function() {
            if (!retellClient) {
                alert('Voice agent is loading, please try again in a moment.');
                return;
            }

            showOverlay();

            try {
                await navigator.mediaDevices.getUserMedia({ audio: true });

                var response = await fetch('/api/retell/create-demo-call.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        agent_id: AGENT_ID,
                        metadata: {
                            customer_name: 'Edward',
                            property_name: 'Arrive Michigan Avenue',
                            property_address: '200 E Illinois St, Chicago, IL 60611',
                            price_range: '$2,265 - $7,508',
                            unit_types: 'Studio, 1 Bed, 2 Bed, 3 Bed'
                        }
                    }),
                });

                if (!response.ok) {
                    var errData = await response.json();
                    throw new Error(errData.error || 'Failed to create call');
                }

                var data = await response.json();
                if (!data.access_token) throw new Error('No access token received');

                await retellClient.startCall({ accessToken: data.access_token });

            } catch (error) {
                console.error('Call failed:', error);
                if (error.name === 'NotAllowedError') {
                    updateStatus('error', 'Microphone access denied');
                } else {
                    updateStatus('error', error.message || 'Failed to start call');
                }
                stopTimer();
                setTimeout(hideOverlay, 2500);
            }
        };

        // End call
        window.endRetellCall = function() {
            if (retellClient) retellClient.stopCall();
            stopTimer();
            updateStatus('ended', 'Call Ended');
            setTimeout(hideOverlay, 1000);
        };

        function showOverlay() {
            document.getElementById('retell-status-text').textContent = 'Connecting...';
            document.getElementById('retell-call-timer').textContent = '00:00';
            document.getElementById('retell-overlay-timer').style.display = 'none';
            document.getElementById('retell-pulse-ring').className = 'retell-pulse-ring connecting';
            document.getElementById('retell-call-overlay').style.display = 'flex';
        }

        function hideOverlay() {
            document.getElementById('retell-call-overlay').style.display = 'none';
        }

        function updateStatus(state, message) {
            document.getElementById('retell-status-text').textContent = message;
            document.getElementById('retell-pulse-ring').className = 'retell-pulse-ring ' + state;
            if (state === 'active' || state === 'agent_talking') {
                document.getElementById('retell-overlay-timer').style.display = 'block';
            }
        }

        function startTimer() {
            callStartTime = Date.now();
            callTimerInterval = setInterval(function() {
                var elapsed = Math.floor((Date.now() - callStartTime) / 1000);
                var m = String(Math.floor(elapsed / 60)).padStart(2, '0');
                var s = String(elapsed % 60).padStart(2, '0');
                document.getElementById('retell-call-timer').textContent = m + ':' + s;
            }, 1000);
        }

        function stopTimer() {
            if (callTimerInterval) { clearInterval(callTimerInterval); callTimerInterval = null; }
        }

        // Init on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>

</body>
</html>
