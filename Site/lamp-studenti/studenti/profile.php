<?php
session_start();
require __DIR__ . '/config.php';

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']); // doar cantitățile, cum ai acum
}

// dacă nu e logat, îl trimitem la login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// luăm user-ul din DB
$stmt = $mysqli->prepare("
    SELECT id, full_name, email, role, created_at, last_login_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result ? $result->fetch_assoc() : null;
$stmt->close();

// dacă nu mai există user-ul (șters din DB) => scoatem sesiunea
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// formatare date (dacă sunt disponibile)
$createdAt = !empty($user['created_at'])
    ? date('d.m.Y', strtotime($user['created_at']))
    : null;

$lastLogin = !empty($user['last_login_at'])
    ? date('d.m.Y H:i', strtotime($user['last_login_at']))
    : null;

// traducem rolul un pic mai prietenos
$roleLabel = $user['role'] === 'admin' ? 'Administrator' : 'Client';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Profilul meu — LayerLab 3D</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <!-- HEADER -->
  <header class="site-header">
    <div class="container header-inner">
      <a href="index.php#top" class="logo">
        <span class="logo-mark">LL</span>
        <span class="logo-text">LayerLab 3D</span>
      </a>

      <nav class="nav">
        <a href="index.php#top">Acasă</a>
        <a href="shop.php">Produse</a>
        <a href="cart.php" class="nav-cart-link">
  🛒 <span>Coș</span>
  <span class="cart-badge <?php echo $cartCount > 0 ? 'is-visible' : ''; ?>">
    <?php echo $cartCount; ?>
  </span>
</a>
        <a href="index.php#contact">Contact</a>

        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="profile.php">Profil</a>
          <span class="nav-user">
            👋 <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
          </span>
          <a href="logout.php">Logout</a>
        <?php else: ?>
          <a href="login.php">Login</a>
          <a href="register.php">Register</a>
        <?php endif; ?>
      </nav>

      <button class="nav-toggle" aria-label="Deschide meniul">
        <span></span>
        <span></span>
      </button>
    </div>
  </header>

  <main id="top">
    <section class="section section-alt auth-section">
      <div class="container auth-inner">
        <!-- STÂNGA: intro -->
        <div class="auth-intro">
          <span class="badge auth-badge">Profil utilizator</span>
          <h1>Salut, <?php echo htmlspecialchars($user['full_name']); ?> 👋</h1>
          <p>
            Aici poți vedea detaliile contului tău LayerLab 3D.
            Mai târziu vom adăuga și comenzi, adrese de livrare și review-uri la produse.
          </p>
        </div>

        <!-- DREAPTA: card cu info cont -->
        <div class="auth-card">
          <h2 class="auth-title">Detalii cont</h2>
          <p class="auth-subtitle">Informații despre profilul tău.</p>

          <div class="account-info-block">
            <p><strong>Nume complet:</strong><br><?php echo htmlspecialchars($user['full_name']); ?></p>
            <p><strong>E-mail:</strong><br><?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Rol:</strong><br><?php echo htmlspecialchars($roleLabel); ?></p>

            <?php if ($createdAt): ?>
              <p><strong>Cont creat la:</strong><br><?php echo $createdAt; ?></p>
            <?php endif; ?>

            <?php if ($lastLogin): ?>
              <p><strong>Ultima autentificare:</strong><br><?php echo $lastLogin; ?></p>
            <?php endif; ?>
          </div>

          <hr style="border:none;border-top:1px solid rgba(148,163,184,0.4);margin:1rem 0;">

          <div class="account-actions">
            <p style="font-size:0.9rem;color:#6b7280;margin-top:0;">
              În versiuni viitoare vei putea:
            </p>
            <ul style="font-size:0.9rem;color:#4b5563;padding-left:1.1rem;margin-top:0.2rem;">
              <li>să îți editezi numele și e-mailul</li>
              <li>să vezi istoricul comenzilor</li>
              <li>să gestionezi adresele de livrare</li>
              <li>să vezi și să editezi review-urile lăsate la produse</li>
            </ul>

            <a href="shop.php" class="btn btn-primary btn-full" style="margin-top:0.8rem;">
              🛒 Mergi înapoi în shop
            </a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <div class="footer-left">
        <span class="logo-text footer-logo">LayerLab 3D</span>
        <p>Magazin de obiecte printate 3D, realizate la comandă în România.</p>
      </div>
      <div class="footer-right">
        <span>Instagram, TikTok &amp; TikTok Shop (în curând)</span>
        <span>&copy; <span id="year"></span> LayerLab 3D. Toate drepturile rezervate.</span>
      </div>
    </div>
  </footer>

  <script>
    // Toggle meniu mobil
    const navToggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('.nav');
    if (navToggle) {
      navToggle.addEventListener('click', () => {
        nav.classList.toggle('nav-open');
        navToggle.classList.toggle('nav-open');
      });
    }

    // An curent în footer
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
</body>
</html>
