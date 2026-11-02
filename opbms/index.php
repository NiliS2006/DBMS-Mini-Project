<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
if (isLoggedIn()) {
    redirect(getDashboardUrl());
}

$features = [
    ['fa-sitemap', 'Organizational budgeting', 'Plan budgets at organization and department level with clear ownership.'],
    ['fa-diagram-project', 'Project tracking', 'Connect project spend to the right team, allocation, and outcomes.'],
    ['fa-circle-check', 'Expense approvals', 'Keep submissions and decisions moving through a transparent review flow.'],
    ['fa-arrow-trend-up', 'Income tracking', 'Bring organizational income into the same financial picture.'],
    ['fa-chart-pie', 'Useful analytics', 'Turn budgets, expenses, and trends into practical reporting.'],
    ['fa-wallet', 'Personal budgeting', 'Track personal income, expenses, and category limits in one place.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OPBMS | Budget clarity for every level</title>
<meta name="description" content="Plan organizational and personal budgets, track spending, and manage approvals with OPBMS.">
<meta name="theme-color" content="#f6f8fa">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="landing-page">
<header class="site-header">
  <div class="site-nav-wrap">
    <a class="site-brand" href="<?= BASE_URL ?>/" aria-label="OPBMS home">
      <span class="brand-mark"><i class="fa-solid fa-chart-pie"></i></span>
      <span>opbms<span class="brand-period">.</span></span>
    </a>
    <button class="site-menu-toggle" id="siteMenuToggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="siteNavigation">
      <i class="fa-solid fa-bars"></i>
    </button>
    <nav class="site-navigation" id="siteNavigation" aria-label="Main navigation">
      <a href="#home">Home</a>
      <a href="#features">Features</a>
      <a href="#how-it-works">How it works</a>
      <a href="#about">About</a>
      <a href="#contact">Contact</a>
      <div class="site-nav-actions">
        <a class="site-login-link" href="<?= BASE_URL ?>/auth/login.php">Log in</a>
        <a class="btn btn-primary site-start-btn" href="<?= BASE_URL ?>/auth/register.php">Get started <i class="fa-solid fa-arrow-right"></i></a>
      </div>
    </nav>
  </div>
</header>

<main>
  <?= flashMessage() ?>
  <section class="hero-section" id="home">
    <div class="hero-inner">
      <div class="hero-copy">
        <div class="eyebrow"><span class="eyebrow-dot"></span> Budgets with a better point of view</div>
        <h1>Take Control of Every Budget. <span>Make Every Decision Count.</span></h1>
        <p>One clear view of organizational spending and personal finances. Plan with confidence, keep teams aligned, and make every rupee work harder.</p>
        <div class="hero-actions">
          <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>/auth/register.php">Create your account <i class="fa-solid fa-arrow-right"></i></a>
          <a class="hero-secondary-link" href="#how-it-works"><span class="play-icon"><i class="fa-solid fa-play"></i></span> See how it works</a>
        </div>
        <div class="hero-proof"><span class="proof-check"><i class="fa-solid fa-check"></i></span> Built for teams, projects, and people</div>
      </div>
      <div class="hero-visual" aria-label="Illustrative OPBMS dashboard preview">
        <div class="preview-orbit preview-orbit-one"></div>
        <div class="preview-orbit preview-orbit-two"></div>
        <div class="dashboard-preview">
          <div class="preview-topbar">
            <div class="preview-window-dots"><i></i><i></i><i></i></div>
            <span>Workspace overview</span>
            <div class="preview-user-dot">A</div>
          </div>
          <div class="preview-content">
            <div class="preview-heading">
              <div><span class="preview-kicker">MONDAY, OCTOBER 26</span><strong>Good morning, Alex</strong></div>
              <span class="preview-period"><i class="fa-regular fa-calendar"></i> This year <i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="preview-stats">
              <div class="preview-stat"><span>Total budget</span><strong>₹24.8L</strong><small class="positive"><i class="fa-solid fa-arrow-trend-up"></i> 8.2% <em>vs last year</em></small></div>
              <div class="preview-stat"><span>Spent to date</span><strong>₹16.2L</strong><small class="neutral">65.3% of total budget</small></div>
              <div class="preview-stat"><span>Awaiting review</span><strong>12</strong><small class="neutral">Across 4 departments</small></div>
            </div>
            <div class="preview-panels">
              <div class="preview-chart-panel">
                <div class="preview-panel-heading"><span>Spend overview</span><span class="preview-chart-legend"><i></i> Actual <i></i> Budget</span></div>
                <div class="preview-chart">
                  <div class="chart-y-labels"><span>₹8L</span><span>₹6L</span><span>₹4L</span><span>₹2L</span></div>
                  <div class="chart-grid">
                    <i></i><i></i><i></i><i></i>
                    <div class="chart-bars">
                      <span style="--bar-a:48%;--bar-b:61%"><i></i><b></b></span>
                      <span style="--bar-a:66%;--bar-b:78%"><i></i><b></b></span>
                      <span style="--bar-a:54%;--bar-b:69%"><i></i><b></b></span>
                      <span style="--bar-a:76%;--bar-b:88%"><i></i><b></b></span>
                      <span style="--bar-a:64%;--bar-b:76%"><i></i><b></b></span>
                      <span style="--bar-a:83%;--bar-b:94%"><i></i><b></b></span>
                    </div>
                    <div class="chart-x-labels"><span>May</span><span>Jun</span><span>Jul</span><span>Aug</span><span>Sep</span><span>Oct</span></div>
                  </div>
                </div>
              </div>
              <div class="preview-util-panel">
                <div class="preview-panel-heading"><span>Budget health</span><i class="fa-solid fa-ellipsis"></i></div>
                <div class="utilization-ring"><div><strong>65%</strong><span>utilized</span></div></div>
                <div class="utilization-note"><span>₹8.6L remaining</span><small>within planned limits</small></div>
              </div>
            </div>
            <div class="preview-activity">
              <div class="preview-panel-heading"><span>Recent activity</span><a href="<?= BASE_URL ?>/auth/login.php">View all <i class="fa-solid fa-arrow-right"></i></a></div>
              <div class="preview-activity-row"><span class="activity-symbol activity-blue"><i class="fa-solid fa-laptop"></i></span><span class="activity-name"><strong>Team equipment</strong><small>Product · Today</small></span><strong class="activity-amount">− ₹48,500</strong><span class="activity-status">Approved</span></div>
              <div class="preview-activity-row"><span class="activity-symbol activity-green"><i class="fa-solid fa-plane"></i></span><span class="activity-name"><strong>Client travel</strong><small>Sales · Yesterday</small></span><strong class="activity-amount">− ₹26,200</strong><span class="activity-status status-pending">In review</span></div>
            </div>
          </div>
        </div>
        <div class="floating-note"><span class="floating-note-icon"><i class="fa-solid fa-arrow-trend-up"></i></span><span><strong>On track</strong><small>Budget utilization</small></span><b>+12.4%</b></div>
      </div>
    </div>
    <div class="hero-bottom-line"><span>Clarity from the big picture to the small details.</span><i class="fa-solid fa-arrow-down"></i></div>
  </section>

  <section class="trust-strip" aria-label="OPBMS capabilities">
    <span>ONE CONNECTED FINANCIAL WORKSPACE</span>
    <div><i class="fa-solid fa-building"></i> Organizations</div>
    <div><i class="fa-solid fa-diagram-project"></i> Projects</div>
    <div><i class="fa-solid fa-receipt"></i> Expenses</div>
    <div><i class="fa-solid fa-chart-line"></i> Insights</div>
  </section>

  <section class="section-block features-section" id="features">
    <div class="section-heading">
      <span class="section-eyebrow">ONE SYSTEM. EVERY PERSPECTIVE.</span>
      <h2>Financial clarity, built into the way you work.</h2>
      <p>From the first allocation to the final report, OPBMS keeps every part of your financial workflow connected.</p>
    </div>
    <div class="feature-grid">
      <?php foreach ($features as $index => [$icon, $title, $description]): ?>
      <article class="feature-card">
        <span class="feature-icon feature-icon-<?= $index % 4 ?>"><i class="fa-solid <?= e($icon) ?>"></i></span>
        <h3><?= e($title) ?></h3>
        <p><?= e($description) ?></p>
        <span class="feature-arrow"><i class="fa-solid fa-arrow-right"></i></span>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="workflow-section" id="how-it-works">
    <div class="section-block workflow-inner">
      <div class="section-heading">
        <span class="section-eyebrow">A CLEAR LINE FROM PLAN TO SPEND</span>
        <h2>Every rupee has a place.</h2>
        <p>Follow the full financial picture through one connected hierarchy. Spend stays accountable to the plan.</p>
      </div>
      <div class="workflow-track">
        <article class="workflow-step"><span class="workflow-number">01</span><span class="workflow-icon"><i class="fa-solid fa-building"></i></span><h3>Organization</h3><p>Set the overall direction and allocate resources.</p></article>
        <span class="workflow-connector"><i class="fa-solid fa-arrow-right"></i></span>
        <article class="workflow-step"><span class="workflow-number">02</span><span class="workflow-icon"><i class="fa-solid fa-sitemap"></i></span><h3>Department</h3><p>Give each team a clear, owned allocation.</p></article>
        <span class="workflow-connector"><i class="fa-solid fa-arrow-right"></i></span>
        <article class="workflow-step"><span class="workflow-number">03</span><span class="workflow-icon"><i class="fa-solid fa-diagram-project"></i></span><h3>Project</h3><p>Connect delivery work to its budget.</p></article>
        <span class="workflow-connector"><i class="fa-solid fa-arrow-right"></i></span>
        <article class="workflow-step"><span class="workflow-number">04</span><span class="workflow-icon"><i class="fa-solid fa-wallet"></i></span><h3>Budget</h3><p>Plan the limits and track utilization.</p></article>
        <span class="workflow-connector"><i class="fa-solid fa-arrow-right"></i></span>
        <article class="workflow-step"><span class="workflow-number">05</span><span class="workflow-icon"><i class="fa-solid fa-receipt"></i></span><h3>Expense</h3><p>Submit, review, and keep a clear record.</p></article>
      </div>
      <div class="workflow-caption"><i class="fa-solid fa-shield-halved"></i> Connected records. Clear ownership. Confident decisions.</div>
    </div>
  </section>

  <section class="about-section section-block" id="about">
    <div class="about-art" aria-hidden="true">
      <div class="about-art-card about-art-card-main"><span class="about-art-label">YOUR FINANCIAL PICTURE</span><strong>One view.<br>More clarity.</strong><div class="about-art-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><div class="about-art-legend"><span><i></i> Budget</span><span><i></i> Actual</span></div></div>
      <span class="about-art-badge"><i class="fa-solid fa-check"></i> Accountable by design</span>
      <span class="about-art-decoration"></span>
    </div>
    <div class="about-copy">
      <span class="section-eyebrow">MEET OPBMS</span>
      <h2>Better budget management starts with a clearer view.</h2>
      <p>OPBMS brings organizational budgeting and personal finance into one practical system. Teams can see how budgets move from organizations to projects and expenses, while individuals keep track of their own money in a private, user-scoped space.</p>
      <p>Built around connected records and role-based workflows, it helps make financial activity easier to follow, review, and understand.</p>
      <a class="text-link" href="<?= BASE_URL ?>/auth/register.php">Get started with OPBMS <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </section>

  <section class="contact-section" id="contact">
    <div class="section-block contact-inner">
      <div class="contact-copy">
        <span class="section-eyebrow">LET'S TALK</span>
        <h2>Questions about getting started?</h2>
        <p>Tell us what you’re looking for. Our team will be glad to help you find the right place to begin.</p>
        <div class="contact-promise"><i class="fa-regular fa-message"></i><span><strong>A real conversation</strong><small>Share a little context and we’ll take it from there.</small></span></div>
      </div>
      <form class="contact-form" method="post" action="<?= BASE_URL ?>/contact.php">
        <?= csrfField() ?>
        <div class="contact-form-row">
          <div class="form-group"><label for="contact-name" class="required">Your name</label><input id="contact-name" name="name" type="text" maxlength="120" autocomplete="name" placeholder="Jane Smith" required></div>
          <div class="form-group"><label for="contact-email" class="required">Work email</label><input id="contact-email" name="email" type="email" maxlength="180" autocomplete="email" placeholder="jane@company.com" required></div>
        </div>
        <div class="form-group"><label for="contact-message" class="required">How can we help?</label><textarea id="contact-message" name="message" rows="4" maxlength="5000" placeholder="Tell us a little about what you need..." required></textarea></div>
        <button class="btn btn-primary btn-lg" type="submit">Send message <i class="fa-solid fa-arrow-right"></i></button>
        <p class="contact-form-note"><i class="fa-solid fa-lock"></i> Your details are used only to respond to your message.</p>
      </form>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="site-footer-main">
    <div class="footer-brand-column">
      <a class="site-brand" href="<?= BASE_URL ?>/"><span class="brand-mark"><i class="fa-solid fa-chart-pie"></i></span><span>opbms<span class="brand-period">.</span></span></a>
      <p>Budget clarity for organizations, teams, projects, and people.</p>
    </div>
    <div class="footer-links-column"><strong>Explore</strong><a href="#features">Features</a><a href="#how-it-works">How it works</a><a href="#about">About OPBMS</a></div>
    <div class="footer-links-column"><strong>Get started</strong><a href="<?= BASE_URL ?>/auth/login.php">Log in</a><a href="<?= BASE_URL ?>/auth/register.php">Create an account</a><a href="#contact">Contact</a></div>
    <div class="footer-note"><span class="footer-note-icon"><i class="fa-solid fa-shield-halved"></i></span><p><strong>Built around accountability.</strong><br>Clear roles and connected financial records keep the workflow in view.</p></div>
  </div>
  <div class="site-footer-bottom"><span>© <?= date('Y') ?> OPBMS. All rights reserved.</span><span>Made for clearer financial decisions.</span></div>
</footer>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
