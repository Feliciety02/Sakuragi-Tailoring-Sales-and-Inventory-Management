<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sakuragi Tailoring Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        :root {
            --primary: #0B5CF9;
            --dark: #111827;
            --light: #f8fafc;
        }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--light);
        }
        .hero {
            background: linear-gradient(135deg, var(--dark) 0%, #1e3a5f 100%);
            color: white;
            padding: 100px 0 80px;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(11,92,249,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero h1 { font-size: 3rem; font-weight: 800; }
        .hero p { font-size: 1.15rem; opacity: 0.9; }
        .hero .btn-primary {
            background: var(--primary);
            border: none;
            padding: 14px 36px;
            font-weight: 600;
            border-radius: 8px;
        }
        .hero .btn-outline-light {
            padding: 14px 36px;
            font-weight: 600;
            border-radius: 8px;
        }
        .service-card {
            background: white;
            border: none;
            border-radius: 16px;
            padding: 32px 20px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.08);
        }
        .service-card .icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 16px;
        }
        .service-card h5 { font-weight: 700; }
        .stats-section {
            background: white;
            padding: 60px 0;
        }
        .stat-number { font-size: 2.2rem; font-weight: 800; color: var(--primary); }
        .stat-label { color: #6c757d; font-size: 0.95rem; }
        .cta-section {
            background: linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        .cta-section h2 { font-weight: 800; font-size: 2rem; }
        .cta-section .btn-light {
            padding: 14px 40px;
            font-weight: 600;
            border-radius: 8px;
        }
        .footer {
            background: var(--dark);
            color: rgba(255,255,255,0.7);
            padding: 30px 0;
            text-align: center;
        }
        .navbar {
            background: white !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .navbar-brand {
            font-weight: 800;
            color: var(--dark) !important;
        }
        .navbar-brand i { color: var(--primary); margin-right: 8px; }
        .nav-pills .nav-link {
            color: var(--dark);
            font-weight: 500;
        }
        section { scroll-margin-top: 70px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-scissors"></i>Sakuragi Tailoring</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-2">
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item"><a class="btn btn-outline-primary btn-sm px-4 ms-2" href="/auth/login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-primary btn-sm px-4" href="/auth/register.php">Register</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="mb-3">Custom Tailoring,<br />Crafted with Precision</h1>
                    <p class="mb-4">From uniforms to event shirts, embroidery to sublimation — we bring your vision to life with quality and care.</p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="/auth/register.php" class="btn btn-primary btn-lg"><i class="fas fa-plus-circle me-2"></i>Get Started</a>
                        <a href="/auth/login.php" class="btn btn-outline-light btn-lg"><i class="fas fa-sign-in-alt me-2"></i>Sign In</a>
                    </div>
                </div>
                <div class="col-lg-5 text-center d-none d-lg-block">
                    <i class="fas fa-tshirt" style="font-size: 12rem; opacity: 0.15;"></i>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5" id="services">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Our Services</h2>
                <p class="text-muted">Everything you need for custom apparel, all in one place</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4"><div class="service-card"><div class="icon"><i class="fas fa-paint-brush"></i></div><h5>Embroidery</h5><p class="text-muted mb-0">Custom name stitches, logos, and intricate embroidery designs.</p></div></div>
                <div class="col-md-4"><div class="service-card"><div class="icon"><i class="fas fa-print"></i></div><h5>Sublimation</h5><p class="text-muted mb-0">Full-color prints that last — perfect for jerseys and events.</p></div></div>
                <div class="col-md-4"><div class="service-card"><div class="icon"><i class="fas fa-layer-group"></i></div><h5>Screen Printing</h5><p class="text-muted mb-0">Bulk orders made affordable with quality screen-printed designs.</p></div></div>
                <div class="col-md-4"><div class="service-card"><div class="icon"><i class="fas fa-cut"></i></div><h5>Alterations</h5><p class="text-muted mb-0">Professional resizing, repairs, and garment adjustments.</p></div></div>
                <div class="col-md-4"><div class="service-card"><div class="icon"><i class="fas fa-shield-alt"></i></div><h5>Patches</h5><p class="text-muted mb-0">Custom embroidered patches for uniforms, clubs, and teams.</p></div></div>
                <div class="col-md-4"><div class="service-card"><div class="icon"><i class="fas fa-palette"></i></div><h5>Custom Design</h5><p class="text-muted mb-0">Bring your own design or collaborate with our team.</p></div></div>
            </div>
        </div>
    </section>

    <section class="stats-section" id="about">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Why Choose Us?</h2>
                <p class="text-muted">Trusted by clients across the region</p>
            </div>
            <div class="row text-center g-4">
                <div class="col-md-3"><div class="stat-number">500+</div><div class="stat-label">Orders Fulfilled</div></div>
                <div class="col-md-3"><div class="stat-number">5+</div><div class="stat-label">Years in Business</div></div>
                <div class="col-md-3"><div class="stat-number">200+</div><div class="stat-label">Happy Clients</div></div>
                <div class="col-md-3"><div class="stat-number">100%</div><div class="stat-label">Satisfaction</div></div>
            </div>
        </div>
    </section>

    <section class="cta-section" id="contact">
        <div class="container">
            <h2 class="mb-3">Ready to Start Your Order?</h2>
            <p class="mb-4 opacity-75" style="font-size: 1.1rem;">Create an account and place your order in just a few clicks.</p>
            <a href="/auth/register.php" class="btn btn-light btn-lg"><i class="fas fa-user-plus me-2"></i>Create Account</a>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p class="mb-1">&copy; <?= date('Y') ?> Sakuragi Tailoring Shop. All rights reserved.</p>
            <p class="mb-0 small">Crafted with <i class="fas fa-heart text-danger"></i> in Davao</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
