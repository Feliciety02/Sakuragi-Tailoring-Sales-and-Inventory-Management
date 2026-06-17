<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sakuragi Tailoring Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #84C5FF, rgb(94, 144, 245), #0B5CF9);
            background-size: 400% 400%;
            animation: gradientShift 10s ease infinite;
            overflow-x: hidden;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .particles {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .particles span {
            position: absolute;
            bottom: -20px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            animation: floatParticles linear infinite;
        }
        .particles span:nth-child(1) { left: 5%; width: 6px; height: 6px; animation-duration: 8s; animation-delay: 0s; }
        .particles span:nth-child(2) { left: 10%; width: 8px; height: 8px; animation-duration: 10s; animation-delay: 2s; }
        .particles span:nth-child(3) { left: 15%; width: 5px; height: 5px; animation-duration: 12s; animation-delay: 4s; }
        .particles span:nth-child(4) { left: 20%; width: 10px; height: 10px; animation-duration: 9s; animation-delay: 1s; }
        .particles span:nth-child(5) { left: 25%; width: 7px; height: 7px; animation-duration: 11s; animation-delay: 3s; }
        .particles span:nth-child(6) { left: 30%; width: 9px; height: 9px; animation-duration: 8s; animation-delay: 5s; }
        .particles span:nth-child(7) { left: 35%; width: 12px; height: 12px; animation-duration: 14s; animation-delay: 2s; }
        .particles span:nth-child(8) { left: 40%; width: 5px; height: 5px; animation-duration: 9s; animation-delay: 4s; }
        .particles span:nth-child(9) { left: 45%; width: 8px; height: 8px; animation-duration: 13s; animation-delay: 6s; }
        .particles span:nth-child(10) { left: 50%; width: 6px; height: 6px; animation-duration: 7s; animation-delay: 2s; }
        .particles span:nth-child(11) { left: 55%; width: 11px; height: 11px; animation-duration: 11s; animation-delay: 5s; }
        .particles span:nth-child(12) { left: 60%; width: 7px; height: 7px; animation-duration: 10s; animation-delay: 3s; }
        .particles span:nth-child(13) { left: 65%; width: 9px; height: 9px; animation-duration: 8s; animation-delay: 1s; }
        .particles span:nth-child(14) { left: 70%; width: 5px; height: 5px; animation-duration: 12s; animation-delay: 6s; }
        .particles span:nth-child(15) { left: 75%; width: 10px; height: 10px; animation-duration: 9s; animation-delay: 3s; }
        .particles span:nth-child(16) { left: 80%; width: 7px; height: 7px; animation-duration: 13s; animation-delay: 2s; }
        .particles span:nth-child(17) { left: 85%; width: 6px; height: 6px; animation-duration: 11s; animation-delay: 4s; }
        .particles span:nth-child(18) { left: 90%; width: 8px; height: 8px; animation-duration: 10s; animation-delay: 1s; }
        .particles span:nth-child(19) { left: 95%; width: 5px; height: 5px; animation-duration: 8s; animation-delay: 5s; }
        .particles span:nth-child(20) { left: 98%; width: 6px; height: 6px; animation-duration: 9s; animation-delay: 2s; }

        @keyframes floatParticles {
            0% { transform: translateY(0) translateX(0) scale(1); opacity: 0.7; }
            50% { opacity: 1; }
            100% { transform: translateY(-120vh) translateX(20px) scale(1.5); opacity: 0; }
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 18px 0;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand {
            font-size: 1.3rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar-brand i { font-size: 1.5rem; }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
            list-style: none;
        }
        .nav-links a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: 0.3s;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .nav-links .btn-nav {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: 0.3s;
        }
        .nav-links .btn-nav:hover { background: rgba(255,255,255,0.25); }
        .nav-links .btn-primary-nav {
            background: #fff;
            color: #0B5CF9;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: 0.3s;
        }
        .nav-links .btn-primary-nav:hover { background: #f0f4ff; transform: translateY(-2px); }

        /* Dropdown */
        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 6px;
            min-width: 180px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(8px);
            transition: 0.3s;
        }
        .dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: 0.2s;
        }
        .dropdown-menu a:hover { background: #f0f4ff; color: #0B5CF9; }

        /* Hero */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 80px;
            position: relative;
        }
        .hero-content {
            display: flex;
            align-items: center;
            gap: 60px;
        }
        .hero-text { flex: 1; }
        .hero-text h1 {
            font-size: 3.2rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
            margin-bottom: 20px;
            animation: fadeInUp 0.8s ease forwards;
        }
        .hero-text h1 span {
            background: rgba(255,255,255,0.15);
            padding: 0 8px;
            border-radius: 4px;
        }
        .hero-text p {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.8);
            line-height: 1.7;
            margin-bottom: 32px;
            animation: fadeInUp 1s ease forwards;
        }
        .hero-buttons {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            animation: fadeInUp 1.2s ease forwards;
        }
        .hero-buttons .btn {
            padding: 14px 32px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .hero-buttons .btn-primary {
            background: #fff;
            color: #0B5CF9;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        .hero-buttons .btn-primary:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.2); }
        .hero-buttons .btn-outline {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff;
        }
        .hero-buttons .btn-outline:hover { background: rgba(255,255,255,0.2); transform: translateY(-4px); }
        .hero-image {
            flex: 1;
            text-align: center;
            animation: fadeInRight 1.5s ease forwards;
        }
        .hero-image i { font-size: 14rem; color: rgba(255,255,255,0.12); }

        /* White card sections */
        .section-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 60px 48px;
            margin-bottom: 60px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.08);
            animation: fadeInUp 0.8s ease forwards;
        }
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: #0B5CF9;
            margin-bottom: 8px;
            position: relative;
        }
        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: #0B5CF9;
            border-radius: 2px;
            margin-top: 10px;
        }
        .section-sub {
            color: #666;
            font-size: 1rem;
            margin-bottom: 40px;
            font-weight: 300;
        }

        /* Service cards */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px;
        }
        .service-card {
            background: #fff;
            border: 1px solid #eef2f7;
            border-radius: 16px;
            padding: 28px 20px;
            text-align: center;
            transition: 0.3s;
            cursor: default;
        }
        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(11,92,249,0.1);
            border-color: #0B5CF9;
        }
        .service-card .icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #e8f0ff, #d0e2ff);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.4rem;
            color: #0B5CF9;
        }
        .service-card h5 { font-weight: 700; color: #333; margin-bottom: 6px; font-size: 1rem; }
        .service-card p { font-size: 0.85rem; color: #888; margin: 0; }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 20px;
        }
        .stat-card {
            text-align: center;
            padding: 24px 12px;
            background: #f8faff;
            border-radius: 16px;
            border: 1px solid #eef2f7;
        }
        .stat-card .number { font-size: 2.2rem; font-weight: 800; color: #0B5CF9; }
        .stat-card .label { font-size: 0.85rem; color: #888; font-weight: 500; margin-top: 4px; }

        /* Team */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 24px;
        }
        .team-card {
            background: #f8faff;
            border-radius: 16px;
            padding: 28px 16px;
            text-align: center;
            border: 1px solid #eef2f7;
            transition: 0.3s;
        }
        .team-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .team-card .avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0B5CF9, #84C5FF);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
        }
        .team-card h5 { font-weight: 700; color: #333; margin-bottom: 2px; font-size: 0.95rem; }
        .team-card p { font-size: 0.8rem; color: #888; margin: 0; }

        /* CTA */
        .cta-section {
            text-align: center;
            padding: 40px 20px;
        }
        .cta-section h2 { font-size: 2rem; font-weight: 800; color: #fff; margin-bottom: 12px; }
        .cta-section p { color: rgba(255,255,255,0.8); margin-bottom: 28px; font-size: 1.05rem; }
        .cta-section .btn {
            padding: 16px 40px;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            color: #0B5CF9;
            transition: 0.3s;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        .cta-section .btn:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.2); }

        /* Footer */
        .footer {
            text-align: center;
            padding: 30px 0;
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
        }
        .footer i { color: #ff6b6b; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @media (max-width: 768px) {
            .navbar .container { flex-wrap: wrap; gap: 12px; }
            .nav-links { flex-wrap: wrap; }
            .hero-content { flex-direction: column; gap: 30px; }
            .hero-text h1 { font-size: 2rem; }
            .hero-image i { font-size: 6rem; }
            .section-card { padding: 32px 20px; }
            .section-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="particles">
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
    </div>

    <nav class="navbar">
        <div class="container">
            <a href="#" class="navbar-brand"><i class="fas fa-scissors"></i>Sakuragi</a>
            <ul class="nav-links">
                <li><a href="#services">Services</a></li>
                <li><a href="#about">About</a></li>
                <li class="dropdown">
                    <a href="#" class="btn-nav"><i class="fas fa-user me-1"></i>Demo <i class="fas fa-chevron-down ms-1" style="font-size:0.7rem;"></i></a>
                    <div class="dropdown-menu">
                        <a href="/auth/login.php?demo=admin"><i class="fas fa-user-shield" style="color:#dc3545;"></i>Admin</a>
                        <a href="/auth/login.php?demo=employee"><i class="fas fa-user-tie" style="color:#fd7e14;"></i>Employee</a>
                        <a href="/auth/login.php?demo=customer"><i class="fas fa-user" style="color:#0d6efd;"></i>Customer</a>
                    </div>
                </li>
                <li><a href="/auth/login.php" class="btn-nav">Sign In</a></li>
                <li><a href="/auth/register.php" class="btn-primary-nav"><i class="fas fa-plus-circle me-1"></i>Get Started</a></li>
            </ul>
        </div>
    </nav>

    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>Custom Tailoring,<br><span>Crafted with Precision</span></h1>
                    <p>From uniforms to event shirts, embroidery to sublimation — we bring your vision to life with quality and care. Trusted by <strong>200+ clients</strong> across the region.</p>
                    <div class="hero-buttons">
                        <a href="/auth/register.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i>Get Started</a>
                        <a href="/auth/login.php" class="btn btn-outline"><i class="fas fa-sign-in-alt"></i>Sign In</a>
                    </div>
                </div>
                <div class="hero-image">
                    <i class="fas fa-tshirt"></i>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="section-card" id="services">
            <h2 class="section-title">Our Services</h2>
            <p class="section-sub">Everything you need for custom apparel, all in one place</p>
            <div class="services-grid">
                <div class="service-card"><div class="icon"><i class="fas fa-paint-brush"></i></div><h5>Embroidery</h5><p>Custom name stitches, logos, and intricate embroidery designs</p></div>
                <div class="service-card"><div class="icon"><i class="fas fa-print"></i></div><h5>Sublimation</h5><p>Full-color prints that last — perfect for jerseys and events</p></div>
                <div class="service-card"><div class="icon"><i class="fas fa-layer-group"></i></div><h5>Screen Printing</h5><p>Bulk orders made affordable with quality screen-printed designs</p></div>
                <div class="service-card"><div class="icon"><i class="fas fa-cut"></i></div><h5>Alterations</h5><p>Professional resizing, repairs, and garment adjustments</p></div>
                <div class="service-card"><div class="icon"><i class="fas fa-shield-alt"></i></div><h5>Patches</h5><p>Custom embroidered patches for uniforms, clubs, and teams</p></div>
                <div class="service-card"><div class="icon"><i class="fas fa-palette"></i></div><h5>Custom Design</h5><p>Bring your own design or collaborate with our team</p></div>
            </div>
        </div>

        <div class="section-card" id="about">
            <h2 class="section-title">Why Choose Us?</h2>
            <p class="section-sub">Trusted by clients across the region for quality and reliability</p>
            <div class="stats-grid">
                <div class="stat-card"><div class="number">500+</div><div class="label">Orders Fulfilled</div></div>
                <div class="stat-card"><div class="number">5+</div><div class="label">Years in Business</div></div>
                <div class="stat-card"><div class="number">200+</div><div class="label">Happy Clients</div></div>
                <div class="stat-card"><div class="number">100%</div><div class="label">Satisfaction</div></div>
            </div>
        </div>

        <div class="section-card">
            <h2 class="section-title">Developer Team</h2>
            <p class="section-sub">Crafting the digital experience that powers Sakuragi Tailoring Shop</p>
            <div class="team-grid">
                <?php
                $team = [
                    ['name' => 'Albert Peculados', 'role' => 'Developer'],
                    ['name' => 'Cjay Lao', 'role' => 'Backend Developer'],
                    ['name' => 'Fe Malasarte', 'role' => 'Frontend Developer'],
                    ['name' => 'Joevan Capote', 'role' => 'Developer'],
                ];
                foreach ($team as $m):
                    $parts = explode(' ', $m['name']);
                    $initials = count($parts) >= 2 ? strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1)) : strtoupper(substr($m['name'], 0, 2));
                ?>
                <div class="team-card">
                    <div class="avatar"><?= $initials ?></div>
                    <h5><?= htmlspecialchars($m['name']) ?></h5>
                    <p><?= htmlspecialchars($m['role']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <section class="cta-section">
        <div class="container">
            <h2>Ready to Start Your Order?</h2>
            <p>Create an account and place your order in just a few clicks.</p>
            <a href="/auth/register.php" class="btn"><i class="fas fa-user-plus"></i>Create Account</a>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Sakuragi Tailoring Shop. All rights reserved. Crafted with <i class="fas fa-heart"></i> in Davao</p>
        </div>
    </footer>
</body>
</html>
