<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sakuragi — Tailoring Production Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/public/assets/css/landing-modern.css">
</head>
<body>

  <!-- ── Navigation ── -->
  <nav class="navbar">
    <div class="navbar-inner">
      <a href="/" class="navbar-brand">
        <svg viewBox="0 0 28 28" fill="none">
          <rect width="28" height="28" rx="6" fill="#1e3a5f"/>
          <path d="M7 10h14l-3 8H10L7 10z" fill="#fff" opacity=".9"/>
          <path d="M14 7v14M9 9l5 5M19 9l-5 5" stroke="#fff" stroke-width="1.5" stroke-linecap="round" opacity=".6"/>
        </svg>
        Sakuragi
      </a>
      <ul class="nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#metrics">Metrics</a></li>
        <li><a href="#cta">Contact</a></li>
        <a href="/auth/login.php" class="btn-nav">Sign In</a>
        <a href="/auth/register.php" class="btn-primary-nav">Get Started</a>
      </ul>
    </div>
  </nav>

  <!-- ── Hero ── -->
  <section class="hero">
    <div class="container">
      <div class="hero-grid">
        <div class="hero-text fade-up">
          <div class="hero-tag"><i class="fas fa-bolt"></i> Manufacturing Operations Platform</div>
          <h1>Manage Your Tailoring Production<br><span>From Order to Delivery</span></h1>
          <p>Track orders, manage employees, monitor production stages, and ensure quality control in one centralized platform designed for modern garment factories.</p>
          <div class="hero-buttons">
            <a href="/auth/register.php" class="btn btn-primary"><i class="fas fa-rocket"></i> Start Free Trial</a>
            <a href="#features" class="btn btn-outline"><i class="fas fa-play-circle"></i> Watch Demo</a>
          </div>
          <div class="trust-row">
            <div class="trust-item"><span class="value">10,000+</span><span class="label">Garments Processed</span></div>
            <div class="trust-item"><span class="value">98%</span><span class="label">On-Time Delivery</span></div>
            <div class="trust-item"><span class="value">500+</span><span class="label">Active Customers</span></div>
          </div>
        </div>
        <div class="hero-preview fade-up" style="animation-delay:.2s">
          <div class="browser-window">
            <div class="browser-bar">
              <div class="dots">
                <span class="dot"></span><span class="dot"></span><span class="dot"></span>
              </div>
              <div class="url"><i class="fas fa-lock" style="margin-right:6px;font-size:10px;color:#10b981"></i>app.sakuragi.com/dashboard</div>
            </div>
            <div class="browser-body">
              <div class="mini-dash">
                <div class="mini-dash-header">
                  <h4>Production Overview</h4>
                  <span>Last 7 days</span>
                </div>
                <div class="mini-kpi">
                  <div class="mini-kpi-item"><div class="val">48</div><div class="lbl">Total Orders</div></div>
                  <div class="mini-kpi-item"><div class="val">23</div><div class="lbl">In Production</div></div>
                  <div class="mini-kpi-item"><div class="val">8</div><div class="lbl">QC Queue</div></div>
                  <div class="mini-kpi-item"><div class="val">12</div><div class="lbl">Completed</div></div>
                </div>
                <div class="mini-kanban">
                  <div class="mini-col">
                    <h5>Cutting</h5>
                    <div class="mini-card"><strong>#ORD-1042</strong><span>J. Cruz · 3 items</span></div>
                    <div class="mini-card"><strong>#ORD-1039</strong><span>M. Santos · 2 items</span></div>
                  </div>
                  <div class="mini-col">
                    <h5>Sewing</h5>
                    <div class="mini-card"><strong>#ORD-1035</strong><span>A. Reyes · 5 items</span></div>
                    <div class="mini-card"><strong>#ORD-1031</strong><span>L. Tan · 1 item</span></div>
                  </div>
                  <div class="mini-col">
                    <h5>Quality Check</h5>
                    <div class="mini-card"><strong>#ORD-1028</strong><span>R. Lim · 4 items</span></div>
                    <div class="mini-card" style="border-color:#fee2e2"><strong>#ORD-1025</strong><span>D. Co · 2 items</span></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Features ── -->
  <section class="section" id="features">
    <div class="container">
      <div class="section-header">
        <h2>Everything you need to run production</h2>
        <p>A complete platform for managing orders, production stages, employees, and quality control.</p>
      </div>
      <div class="features-grid">
        <div class="feature-card fade-up">
          <div class="feature-icon" style="background:#eef2ff;color:#2563eb"><i class="fas fa-columns"></i></div>
          <h3>Kanban Production Board</h3>
          <p>Drag and drop orders across production stages with real-time updates. See exactly where every garment is in your pipeline.</p>
        </div>
        <div class="feature-card fade-up" style="animation-delay:.1s">
          <div class="feature-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-clipboard-check"></i></div>
          <h3>Quality Control Center</h3>
          <p>Dedicated QC workspace with pass/fail checklist, inspection history, and automated rework routing for failed items.</p>
        </div>
        <div class="feature-card fade-up" style="animation-delay:.2s">
          <div class="feature-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-users"></i></div>
          <h3>Employee Workspaces</h3>
          <p>Personalized views showing only relevant tasks per role. Tailors see their queue, QC sees inspections, managers see everything.</p>
        </div>
        <div class="feature-card fade-up" style="animation-delay:.3s">
          <div class="feature-icon" style="background:#fce7f3;color:#db2777"><i class="fas fa-chart-line"></i></div>
          <h3>Real-Time Analytics</h3>
          <p>Track completion rates, employee productivity, and production bottlenecks with live dashboards and exportable reports.</p>
        </div>
        <div class="feature-card fade-up" style="animation-delay:.4s">
          <div class="feature-icon" style="background:#e0f2fe;color:#0891b2"><i class="fas fa-bell"></i></div>
          <h3>Smart Notifications</h3>
          <p>Automatic alerts for stage changes, QC results, and overdue orders. Keep everyone in sync without manual updates.</p>
        </div>
        <div class="feature-card fade-up" style="animation-delay:.5s">
          <div class="feature-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-tshirt"></i></div>
          <h3>Garment-Level Tracking</h3>
          <p>Track every individual item within an order across all stages. Know exactly which sizes and quantities are where.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Metrics ── -->
  <section class="section" id="metrics" style="padding-top:0">
    <div class="container">
      <div class="section-header">
        <h2>Trusted by garment factories</h2>
        <p>Real results from production teams using Sakuragi to manage their workflow.</p>
      </div>
      <div class="metrics-grid">
        <div class="metric-card fade-up">
          <div class="number">48%</div>
          <div class="label">Faster Production Cycles</div>
        </div>
        <div class="metric-card fade-up" style="animation-delay:.1s">
          <div class="number">32%</div>
          <div class="label">Fewer QC Failures</div>
        </div>
        <div class="metric-card fade-up" style="animation-delay:.2s">
          <div class="number">99.9%</div>
          <div class="label">Uptime Guarantee</div>
        </div>
        <div class="metric-card fade-up" style="animation-delay:.3s">
          <div class="number">4.9/5</div>
          <div class="label">Customer Rating</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── CTA ── -->
  <section id="cta">
    <div class="container">
      <div class="cta-section fade-up">
        <h2>Ready to transform your production?</h2>
        <p>Start your free trial today. No credit card required.</p>
        <a href="/auth/register.php" class="btn"><i class="fas fa-arrow-right"></i> Get Started Free</a>
      </div>
    </div>
  </section>

  <!-- ── Footer ── -->
  <footer class="footer">
    <div class="container">
      <p>&copy; <?= date('Y') ?> Sakuragi Tailoring. Made with care for garment manufacturers.</p>
    </div>
  </footer>

</body>
</html>
