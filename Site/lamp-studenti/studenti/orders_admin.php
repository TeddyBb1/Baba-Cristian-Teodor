<?php
session_start();
require __DIR__ . '/config.php';

// 1) Verificăm că userul e logat
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

// 2) Citim rolul din DB (ca să fim siguri că e admin)
$stmtUser = $mysqli->prepare("SELECT full_name, email, role FROM users WHERE id = ? LIMIT 1");
$stmtUser->bind_param('i', $currentUserId);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
$currentUser = $resUser ? $resUser->fetch_assoc() : null;
$stmtUser->close();

if (!$currentUser || $currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo 'Această pagină este disponibilă doar pentru admin.';
    exit;
}

$userName   = $currentUser['full_name'];
$userInitial = $userName !== '' ? mb_substr($userName, 0, 1, 'UTF-8') : '?';

// 3) Mesaj mic de feedback (după update)
$flashMessage = '';
if (isset($_GET['ok'])) {
    $flashMessage = 'Statusul comenzii a fost actualizat.';
} elseif (isset($_GET['err'])) {
    $flashMessage = 'Nu am putut actualiza statusul. Încearcă din nou.';
}

// 4) Citim toate comenzile cu info despre user
$sql = "
    SELECT 
      o.id,
      o.order_number,
      o.total_amount,
      o.status,
      o.created_at,
      u.full_name AS customer_name,
      u.email     AS customer_email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
";
$result = $mysqli->query($sql);
$orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

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
  <title>Administrare comenzi — LayerLab 3D</title>
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
  <a href="index.php#contact">Contact</a>

  <?php if (isset($_SESSION['user_id'])): ?>
    <?php
      $userName   = $_SESSION['user_name'] ?? '';
      $userInitial = $userName !== '' ? mb_substr($userName, 0, 1, 'UTF-8') : '?';
      $userRole   = $_SESSION['user_role'] ?? 'user';
    ?>
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
          <!-- 🛠️ Meniu special pentru admin -->
          <a href="orders_admin.php">Admin comenzi</a>
        <?php else: ?>
          <!-- 👤 Meniu normal pentru user obișnuit -->
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
        <div class="account-header">
          <div>
            <h1>Administrare comenzi</h1>
            <p class="account-subtitle">
              Doar tu (admin) vezi această pagină. Poți schimba statusul comenzilor.
            </p>
          </div>
        </div>

        <?php if ($flashMessage): ?>
          <div class="form-alert form-alert-success" style="margin-bottom:1rem;">
            <?php echo htmlspecialchars($flashMessage); ?>
          </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
          <div class="account-empty-state">
            <h2>Nu există încă nicio comandă</h2>
            <p>
              De îndată ce utilizatorii trimit comenzi, le vei vedea listate aici.
            </p>
          </div>
        <?php else: ?>
          <div class="orders-list">
            <?php foreach ($orders as $order): ?>
              <?php
                $statusLabel = formatOrderStatus($order['status']);
                $statusClass = 'status-' . $order['status'];
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
                    <div style="font-size:0.85rem; color:#6b7280;">
                      Client: <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                      &lt;<?php echo htmlspecialchars($order['customer_email']); ?>&gt;
                    </div>
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
                    În această pagină poți doar să actualizezi statusul comenzii. Detaliile
                    de produse le vom adăuga ulterior într-o pagină dedicată.
                  </p>
                </div>

                <div class="order-card-footer">
                  <form method="post" action="admin_orders_action.php" class="auth-form" style="flex-direction:row; gap:0.5rem; align-items:center;">
                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>" />
                    <label for="status-<?php echo (int)$order['id']; ?>" style="font-size:0.85rem;">
                      Status:
                    </label>
                    <select id="status-<?php echo (int)$order['id']; ?>" name="status">
                      <option value="in_proces" <?php echo $order['status']==='in_proces' ? 'selected' : ''; ?>>
                        În proces
                      </option>
                      <option value="livrat" <?php echo $order['status']==='livrat' ? 'selected' : ''; ?>>
                        Livrat
                      </option>
                      <option value="anulat" <?php echo $order['status']==='anulat' ? 'selected' : ''; ?>>
                        Anulat
                      </option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                      Salvează
                    </button>
                  </form>
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
