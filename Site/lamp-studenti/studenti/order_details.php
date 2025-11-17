<?php
session_start();
require __DIR__ . '/config.php';

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']); // doar cantitățile, cum ai acum
}

date_default_timezone_set('Europe/Bucharest');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId      = (int)$_SESSION['user_id'];
$userName    = $_SESSION['user_name'] ?? '';
$userInitial = $userName !== '' ? mb_substr($userName, 0, 1, 'UTF-8') : '?';
$userRole    = $_SESSION['user_role'] ?? 'user';

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order   = null;
$items   = [];
$userRow = null;
$reviewError = '';
$reviewSuccess = '';

function formatOrderStatus(string $status): string {
    switch ($status) {
        case 'livrat': return 'Livrat';
        case 'anulat': return 'Anulat';
        default:       return 'În proces';
    }
}

function renderStars(int $rating): string {
    $rating = max(1, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

if ($orderId <= 0) {
    header('Location: orders.php');
    exit;
}

// ─ Utilizator (adresă + plată) ─
$stmtUser = $mysqli->prepare("
    SELECT full_name, phone, email,
           address_line1, address_line2, city, county, postal_code,
           preferred_payment_method, payment_notes
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmtUser->bind_param('i', $userId);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
$userRow = $resUser ? $resUser->fetch_assoc() : null;
$stmtUser->close();

// ─ Comanda (aparține userului?) ─
$stmt = $mysqli->prepare("
    SELECT id, order_number, total_amount, status, created_at
    FROM orders
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$res = $stmt->get_result();
$order = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit('Comanda nu există sau nu îți aparține.');
}

// doar comenzile livrate pot primi review
$canReview = ($order['status'] === 'livrat');

// ─ POST: Adăugare / editare review ─
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'add_review'
) {
    if (!$canReview) {
        $reviewError = 'Poți lăsa review doar pentru comenzi livrate.';
    } else {
        $productId = (int)($_POST['product_id'] ?? 0);
        $rating    = (int)($_POST['rating'] ?? 0);
        $comment   = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $reviewError = 'Alege un rating între 1 și 5 stele.';
        } elseif ($comment === '') {
            $reviewError = 'Scrie un mic comentariu.';
        } else {
            // verificăm că produsul chiar e în această comandă
            $stmtCheck = $mysqli->prepare("
                SELECT oi.product_id
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.id = ? AND o.user_id = ? AND oi.product_id = ?
                LIMIT 1
            ");
            $stmtCheck->bind_param('iii', $orderId, $userId, $productId);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            $valid = $resCheck && $resCheck->fetch_assoc();
            $stmtCheck->close();

            if (!$valid) {
                $reviewError = 'Nu poți lăsa review la un produs pe care nu l-ai cumpărat.';
            } else {
                // există deja review?
                $stmtExists = $mysqli->prepare("
                    SELECT id
                    FROM product_reviews
                    WHERE product_id = ? AND user_id = ?
                    LIMIT 1
                ");
                $stmtExists->bind_param('ii', $productId, $userId);
                $stmtExists->execute();
                $resExists = $stmtExists->get_result();
                $exists = $resExists && $resExists->fetch_assoc();
                $stmtExists->close();

                if ($exists) {
                    $stmtUpd = $mysqli->prepare("
                        UPDATE product_reviews
                        SET rating = ?, comment = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmtUpd->bind_param('isi', $rating, $comment, $exists['id']);
                    $stmtUpd->execute();
                    $stmtUpd->close();
                    $reviewSuccess = 'Review actualizat cu succes!';
                } else {
                    $stmtIns = $mysqli->prepare("
                        INSERT INTO product_reviews (product_id, user_id, rating, comment)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmtIns->bind_param('iiis', $productId, $userId, $rating, $comment);
                    $stmtIns->execute();
                    $stmtIns->close();
                    $reviewSuccess = 'Mulțumim pentru review!';
                }

                header('Location: order_details.php?id=' . $orderId);
                exit;
            }
        }
    }
}

// ─ Produsele din comandă ─
$stmtItems = $mysqli->prepare("
    SELECT oi.*, (oi.quantity * oi.unit_price) AS line_total,
           p.name AS product_name, p.slug, p.image_url
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmtItems->bind_param('i', $orderId);
$stmtItems->execute();
$resItems = $stmtItems->get_result();
$items = $resItems ? $resItems->fetch_all(MYSQLI_ASSOC) : [];
$stmtItems->close();

// review-urile userului pentru produsele din comandă
$userReviewsByProduct = [];
if (!empty($items)) {
    $ids = implode(',', array_map('intval', array_column($items, 'product_id')));
    $resRev = $mysqli->query("
        SELECT *
        FROM product_reviews
        WHERE user_id = {$userId}
          AND product_id IN ($ids)
    ");
    while ($row = $resRev->fetch_assoc()) {
        $userReviewsByProduct[(int)$row['product_id']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Comanda #<?php echo htmlspecialchars($order['order_number']); ?> — LayerLab 3D</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
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
      <a href="orders.php" class="product-back-link">← Înapoi la comenzile mele</a>

      <div class="order-details-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1.5rem;">
        <div>
          <h1>Comanda #<?php echo htmlspecialchars($order['order_number']); ?></h1>
          <p class="account-subtitle">
            Plasată la <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
          </p>
        </div>
        <?php
          $statusClass = 'status-' . $order['status'];
          $statusLabel = formatOrderStatus($order['status']);
        ?>
        <span class="order-status <?php echo htmlspecialchars($statusClass); ?>">
          <?php echo htmlspecialchars($statusLabel); ?>
        </span>
      </div>

      <?php if ($reviewError): ?>
        <div class="form-alert form-alert-error" style="margin-bottom:1rem;">
          <?php echo htmlspecialchars($reviewError); ?>
        </div>
      <?php elseif ($reviewSuccess): ?>
        <div class="form-alert form-alert-success" style="margin-bottom:1rem;">
          <?php echo htmlspecialchars($reviewSuccess); ?>
        </div>
      <?php endif; ?>

      <?php if (!$canReview): ?>
        <div class="account-info-banner" style="margin-bottom:1.2rem;">
          Poți lăsa review la produsele din această comandă
          <strong>doar după ce statusul este „Livrat”.</strong>
        </div>
      <?php endif; ?>

      <div class="order-details-layout" style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1.1fr);gap:2rem;align-items:flex-start;">
        <!-- STÂNGA: produse + review-uri -->
        <div>
          <h2 style="margin-bottom:1rem;">Produse în această comandă</h2>

          <?php if (empty($items)): ?>
            <p>Nu am găsit produse asociate acestei comenzi.</p>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
              <?php
                $quantity  = (int)$item['quantity'];
                $unitPrice = (float)$item['unit_price'];
                $lineTotal = (float)$item['line_total'];
                $pid       = (int)$item['product_id'];
                $myReview  = $userReviewsByProduct[$pid] ?? null;
              ?>
              <article
                class="order-item-card"
                style="
                  display:flex;
                  gap:1rem;
                  align-items:flex-start;
                  margin-bottom:1rem;
                  padding:1rem 1.1rem;
                  border-radius:1rem;
                  background:#ffffff;
                  box-shadow:0 14px 30px rgba(15,23,42,0.06);
                  border:1px solid rgba(148,163,184,0.25);
                "
              >
                <div style="width:120px;flex-shrink:0;">
                  <?php if (!empty($item['image_url'])): ?>
                    <img
                      src="<?php echo htmlspecialchars($item['image_url']); ?>"
                      alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                      style="width:120px;height:120px;object-fit:cover;border-radius:0.9rem;"
                    />
                  <?php else: ?>
                    <div class="image-placeholder" style="width:120px;height:120px;border-radius:0.9rem;">
                      <?php echo htmlspecialchars($item['product_name']); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div style="flex:1;display:flex;flex-direction:column;gap:0.5rem;">
                  <div>
                    <a
                      href="product.php?slug=<?php echo urlencode($item['slug']); ?>"
                      style="font-weight:600;font-size:0.95rem;text-decoration:none;color:#0f172a;"
                    >
                      <?php echo htmlspecialchars($item['product_name']); ?>
                    </a>
                    <p style="margin:0.2rem 0 0.1rem;font-size:0.85rem;color:#6b7280;">
                      Cantitate: <?php echo $quantity; ?> ×
                      <?php echo number_format($unitPrice, 2, ',', ' '); ?> lei
                    </p>
                    <p style="margin:0;font-size:0.9rem;font-weight:600;">
                      Total linie: <?php echo number_format($lineTotal, 2, ',', ' '); ?> lei
                    </p>
                  </div>

                  <!-- zona review -->
                  <div style="margin-top:0.2rem;">
                    <?php if ($myReview): ?>
                      <div class="product-review-card" style="margin-bottom:0.4rem;">
                        <div class="product-review-header">
                          <span class="product-review-name">Review-ul tău</span>
                          <span class="product-review-stars">
                            <?php echo renderStars((int)$myReview['rating']); ?>
                          </span>
                        </div>
                        <p class="product-review-text">
                          <?php echo nl2br(htmlspecialchars($myReview['comment'])); ?>
                        </p>
                      </div>
                    <?php endif; ?>

                    <?php if ($canReview): ?>
                      <form method="post" class="auth-form" style="margin-top:0.3rem;padding:0.6rem 0 0;border-top:1px dashed rgba(148,163,184,0.7);">
                        <input type="hidden" name="action" value="add_review" />
                        <input type="hidden" name="product_id" value="<?php echo $pid; ?>" />

                        <div class="form-field" style="margin-bottom:0.4rem;">
                          <label for="rating_<?php echo $pid; ?>">Rating</label>
                          <select id="rating_<?php echo $pid; ?>" name="rating" required>
                            <?php
                              $current = $myReview ? (int)$myReview['rating'] : 5;
                              for ($i = 5; $i >= 1; $i--):
                            ?>
                              <option value="<?php echo $i; ?>" <?php echo $i === $current ? 'selected' : ''; ?>>
                                <?php echo $i; ?> stele
                              </option>
                            <?php endfor; ?>
                          </select>
                        </div>

                        <div class="form-field" style="margin-bottom:0.5rem;">
                          <label for="comment_<?php echo $pid; ?>">
                            <?php echo $myReview ? 'Editează review-ul' : 'Scrie un review pentru acest produs'; ?>
                          </label>
                          <textarea
                            id="comment_<?php echo $pid; ?>"
                            name="comment"
                            rows="2"
                            required
                            placeholder="Cum ți s-a părut acest produs?"
                          ><?php echo $myReview ? htmlspecialchars($myReview['comment']) : ''; ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                          <?php echo $myReview ? 'Actualizează review-ul' : 'Trimite review'; ?>
                        </button>
                      </form>
                    <?php elseif (!$myReview): ?>
                      <p style="margin:0.3rem 0 0;font-size:0.8rem;color:#6b7280;">
                        Review-urile pot fi lăsate doar după ce comanda este livrată.
                      </p>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- DREAPTA: adresă + plată + total -->
        <aside
          class="order-summary-card"
          style="
            background:#ffffff;
            border-radius:1rem;
            padding:1.2rem 1.3rem;
            box-shadow:0 14px 30px rgba(15,23,42,0.08);
            border:1px solid rgba(148,163,184,0.35);
            font-size:0.9rem;
          "
        >
          <h2 style="margin-top:0;margin-bottom:0.6rem;font-size:1.1rem;">Adresă de livrare</h2>

          <?php if ($userRow && $userRow['address_line1']): ?>
            <p style="margin:0 0 0.2rem;"><strong><?php echo htmlspecialchars($userRow['full_name']); ?></strong></p>
            <p style="margin:0 0 0.1rem;"><?php echo htmlspecialchars($userRow['address_line1']); ?></p>
            <?php if ($userRow['address_line2']): ?>
              <p style="margin:0 0 0.1rem;"><?php echo htmlspecialchars($userRow['address_line2']); ?></p>
            <?php endif; ?>
            <p style="margin:0 0 0.1rem;">
              <?php echo htmlspecialchars($userRow['city']); ?>,
              <?php echo htmlspecialchars($userRow['county']); ?>
              <?php echo htmlspecialchars($userRow['postal_code']); ?>
            </p>
            <?php if ($userRow['phone']): ?>
              <p style="margin:0.2rem 0 0;">Tel: <?php echo htmlspecialchars($userRow['phone']); ?></p>
            <?php endif; ?>
          <?php else: ?>
            <p class="order-note">
              Nu ai setat o adresă de livrare. Poți adăuga una în
              <a href="addresses.php">Adrese &amp; plăți</a>.
            </p>
          <?php endif; ?>

          <hr style="margin:0.9rem 0;border:none;border-top:1px solid rgba(148,163,184,0.4);" />

          <h2 style="margin:0 0 0.4rem;font-size:1.05rem;">Metodă de plată</h2>
          <p style="margin:0 0 0.2rem;">
            <?php
              $pm = $userRow['preferred_payment_method'] ?? 'ramburs';
              $pmLabels = [
                'ramburs'         => 'Plată ramburs la curier',
                'transfer_bancar' => 'Transfer bancar',
                'card_online'     => 'Card online',
                'altul'           => 'Altă metodă'
              ];
              echo htmlspecialchars($pmLabels[$pm] ?? $pm);
            ?>
          </p>
          <?php if (!empty($userRow['payment_notes'])): ?>
            <p class="order-note" style="margin-top:0.2rem;">
              Notă plată: <?php echo htmlspecialchars($userRow['payment_notes']); ?>
            </p>
          <?php endif; ?>

          <hr style="margin:0.9rem 0;border:none;border-top:1px solid rgba(148,163,184,0.4);" />

          <h2 style="margin:0 0 0.4rem;font-size:1.05rem;">Total comandă</h2>
          <p class="order-total-big" style="margin:0 0 0.2rem;font-size:1.4rem;font-weight:700;">
            <?php echo number_format((float)$order['total_amount'], 2, ',', ' '); ?> lei
          </p>

          <p class="order-note" style="margin-top:0.4rem;">
            Transportul și detaliile de livrare se stabilesc în discuția ulterioară
            sau la confirmarea comenzii.
          </p>
        </aside>
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

<button id="backToTop" class="back-to-top" aria-label="Înapoi sus">↑</button>

<script>
  const navToggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.nav');
  if (navToggle) {
    navToggle.addEventListener('click', () => {
      nav.classList.toggle('nav-open');
      navToggle.classList.toggle('nav-open');
    });
  }

  document.getElementById('year').textContent = new Date().getFullYear();

  const backToTop = document.getElementById('backToTop');
  if (backToTop) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 300) backToTop.classList.add('visible');
      else backToTop.classList.remove('visible');
    });
    backToTop.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

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
