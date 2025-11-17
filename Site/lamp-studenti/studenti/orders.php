<?php
session_start();
require __DIR__ . '/config.php';

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']); // doar cantitățile, cum ai acum
}

// dacă nu e logat, trimitem la login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$userName   = $_SESSION['user_name'] ?? '';
$userInitial = $userName !== '' ? mb_substr($userName, 0, 1, 'UTF-8') : '?';
$userRole   = $_SESSION['user_role'] ?? 'user';

// citim comenzile din DB
$stmt = $mysqli->prepare("
    SELECT id, order_number, total_amount, status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// mapăm statusul din DB la text frumos
function formatOrderStatus(string $status): string {
    switch ($status) {
        case 'livrat':      return 'Livrat';
        case 'anulat':      return 'Anulat';
        case 'in_proces':
        default:            return 'În proces';
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Comenzile mele — LayerLab 3D</title>
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
          <div class="nav-user-wrapper">
            <button type="button" class="nav-user-trigger">
              <span class="nav-user-initials">
                <?php echo htmlspecialchars(mb_strtoupper($userInitial, 'UTF-8')); ?>
              </span>
              <span class="nav-user-name">
                <?php echo htmlspecialchars($userName); ?>
              </span>
              <span class="nav-user-caret">▾</span>
            </button>

            <div class="nav-user-dropdown">
              <?php if ($userRole === 'admin'): ?>
                <a href="orders_admin.php">Admin comenzi</a>
              <?php else: ?>
                <a href="orders.php">Comenzi</a>
                <a href="addresses.php">Adrese &amp; plăți</a>
                <a href="account.php">Setări cont</a>
              <?php endif; ?>
              <a href="logout.php">Logout</a>
            </div>
          </div>
        <?php else: ?>
          <a href="login.php">Login</a>
        <?php endif; ?>
      </nav>

      <button class="nav-toggle" aria-label="Deschide meniul">
        <span></span>
        <span></span>
      </button>
    </div>
  </header>

  <main id="top">
    <section class="section section-alt">
      <div class="container">
        <!-- header simplu de pagină -->
        <div class="account-header">
          <div>
            <h1>Comenzile mele</h1>
            <p class="account-subtitle">
              Aici vezi istoricul comenzilor plasate cu acest cont.
            </p>
          </div>
        </div>

        <?php if (empty($orders)): ?>
          <div class="account-empty-state">
            <h2>Nu ai încă nicio comandă</h2>
            <p>
              Începe prin a explora produsele noastre printate 3D și plasează prima ta comandă.
            </p>
            <a href="shop.php" class="btn btn-primary">🛒 Mergi în shop</a>
          </div>
        <?php else: ?>
          <div class="orders-list">
            <?php foreach ($orders as $order): ?>
              <?php
                $statusClass = 'status-' . $order['status']; // ex: status-in_proces
                $statusLabel = formatOrderStatus($order['status']);
              ?>
              <article class="order-card">
                <div class="order-card-header">
                  <div>
                    <span class="order-id">
                      Comanda #<?php echo htmlspecialchars($order['order_number']); ?>
                    </span>
                    <span class="order-date">
                      plasată la <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                    </span>
                  </div>
                  <span class="order-status <?php echo htmlspecialchars($statusClass); ?>">
                    <?php echo htmlspecialchars($statusLabel); ?>
                  </span>
                </div>

                <div class="order-card-body">
                  <p class="order-total">
                    Total: <strong><?php echo number_format($order['total_amount'], 2, ',', ' '); ?> lei</strong>
                  </p>
                  <p class="order-note">
                    Apasă pe „Vezi detalii” pentru a vedea produsele incluse în această comandă.
                  </p>
                </div>

                <div class="order-card-footer">
                  <a
                    class="btn btn-ghost"
                    href="order_details.php?id=<?php echo (int)$order['id']; ?>"
                  >
                    Vezi detalii
                  </a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
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

  <!-- Buton "Înapoi sus" -->
  <button id="backToTop" class="back-to-top" aria-label="Înapoi sus">↑</button>

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

    // Buton "Înapoi sus"
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
      window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
          backToTop.classList.add('visible');
        } else {
          backToTop.classList.remove('visible');
        }
      });

      backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }

    // Dropdown user
    const userWrapper = document.querySelector('.nav-user-wrapper');
    const userTrigger = document.querySelector('.nav-user-trigger');

    if (userWrapper && userTrigger) {
      userTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        userWrapper.classList.toggle('open');
      });

      document.addEventListener('click', (e) => {
        if (!userWrapper.contains(e.target)) {
          userWrapper.classList.remove('open');
        }
      });
    }
  </script>
</body>
</html>
