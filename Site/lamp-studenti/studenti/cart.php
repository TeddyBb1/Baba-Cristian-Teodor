<?php
session_start();
require __DIR__ . '/config.php';

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']); // doar cantitățile, cum ai acum
}

// helper: construim o listă de item-uri de coș cu datele produselor
function getCartItems(mysqli $mysqli, array $cart): array {
    $items = [];

    if (empty($cart)) {
        return $items;
    }

    $ids = array_keys($cart);
    $ids = array_filter(array_map('intval', $ids), fn($v) => $v > 0);

    if (empty($ids)) {
        return $items;
    }

    $inList = implode(',', $ids);
    $sql    = "SELECT * FROM products WHERE id IN ($inList)";
    $res    = $mysqli->query($sql);

    if (!$res) {
        return $items;
    }

    $products = [];
    while ($row = $res->fetch_assoc()) {
        $products[(int)$row['id']] = $row;
    }

    foreach ($cart as $pid => $qty) {
        $pid = (int)$pid;
        if (!isset($products[$pid])) {
            continue; // produs șters din DB
        }

        $qty = (int)$qty;
        if ($qty < 1) {
            continue;
        }

        $product    = $products[$pid];
        $unitPrice  = (float)$product['price'];
        $lineTotal  = $unitPrice * $qty;

        $items[] = [
            'product'    => $product,
            'qty'        => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ];
    }

    return $items;
}

$infoMessage  = '';
$errorMessage = '';

// citim coșul din sesiune
$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

// ---------------------------
// POST: update coș / checkout
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1) actualizare cantități
    if ($action === 'update') {
        if (isset($_POST['qty']) && is_array($_POST['qty'])) {
            foreach ($_POST['qty'] as $pid => $q) {
                $pid = (int)$pid;
                $q   = (int)$q;

                if ($pid <= 0) {
                    continue;
                }

                if ($q <= 0) {
                    unset($cart[$pid]);
                } else {
                    if ($q > 50) $q = 50;
                    $cart[$pid] = $q;
                }
            }
        }

        $_SESSION['cart'] = $cart;
        header('Location: cart.php?updated=1');
        exit;
    }

    // 2) checkout – creare comandă
    if ($action === 'checkout') {
        if (empty($cart)) {
            $errorMessage = 'Coșul este gol. Adaugă produse înainte de a plasa comanda.';
        } else {
            if (!isset($_SESSION['user_id'])) {
                // cere login, apoi întoarce-te la coș
                header('Location: login.php?redirect=' . urlencode('cart.php'));
                exit;
            }

            $userId = (int)$_SESSION['user_id'];

            // reconstruim items cu prețuri actuale din DB
            $items = getCartItems($mysqli, $cart);

            if (empty($items)) {
                $errorMessage = 'Nu am putut încărca produsele din coș. Încearcă din nou.';
            } else {
                $total = 0.0;
                foreach ($items as $it) {
                    $total += $it['line_total'];
                }

                // order_number
                try {
                    $orderNumber = 'LL-' . date('YmdHis') . '-' . random_int(100, 999);
                } catch (Exception $e) {
                    $orderNumber = 'LL-' . date('YmdHis');
                }

                // creăm comanda
                $stmtOrder = $mysqli->prepare("
                    INSERT INTO orders (user_id, order_number, total_amount, status)
                    VALUES (?, ?, ?, 'in_proces')
                ");

                if ($stmtOrder) {
                    $stmtOrder->bind_param('isd', $userId, $orderNumber, $total);
                    if ($stmtOrder->execute()) {
                        $orderId = $stmtOrder->insert_id;
                        $stmtOrder->close();

                        // inserăm item-urile
                        $stmtItem = $mysqli->prepare("
                            INSERT INTO order_items (order_id, product_id, quantity, unit_price)
                            VALUES (?, ?, ?, ?)
                        ");

                        if ($stmtItem) {
                            foreach ($items as $it) {
                                $pid       = (int)$it['product']['id'];
                                $qty       = (int)$it['qty'];
                                $unitPrice = (float)$it['unit_price'];

                                $stmtItem->bind_param('iiid', $orderId, $pid, $qty, $unitPrice);
                                $stmtItem->execute();
                            }
                            $stmtItem->close();

                            // golim coșul
                            $_SESSION['cart'] = [];
                            header('Location: orders.php?created=1');
                            exit;
                        } else {
                            $errorMessage = 'Eroare internă la salvarea produselor în comandă.';
                        }
                    } else {
                        $errorMessage = 'Nu am putut crea comanda. Încearcă din nou.';
                        $stmtOrder->close();
                    }
                } else {
                    $errorMessage = 'Eroare internă la crearea comenzii.';
                }
            }
        }
    }
}

// după eventuale modificări, recitim coșul actual
$cart  = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
$items = getCartItems($mysqli, $cart);

$totalAmount = 0.0;
foreach ($items as $it) {
    $totalAmount += $it['line_total'];
}

// mesaje info din query string
if (isset($_GET['added'])) {
    $infoMessage = 'Produsul a fost adăugat în coș.';
} elseif (isset($_GET['updated'])) {
    $infoMessage = 'Coș actualizat.';
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Coșul meu — LayerLab 3D</title>
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

        <?php if (isset($_SESSION['user_id'])): ?>
          <?php
            $userName   = $_SESSION['user_name'] ?? '';
            $userInitial = $userName !== '' ? mb_substr($userName, 0, 1, 'UTF-8') : '?';
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
              <a href="orders.php">Comenzi</a>
              <a href="addresses.php">Adrese &amp; plăți</a>
              <a href="account.php">Setări cont</a>
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
        <h1>Coșul meu</h1>

        <?php if ($infoMessage): ?>
          <div class="form-alert form-alert-success" style="margin-top:0.8rem;">
            <?php echo htmlspecialchars($infoMessage); ?>
          </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
          <div class="form-alert form-alert-error" style="margin-top:0.8rem;">
            <?php echo htmlspecialchars($errorMessage); ?>
          </div>
        <?php endif; ?>

        <?php if (empty($items)): ?>
          <p style="margin-top:1.2rem;">
            Coșul tău este gol momentan.
          </p>
          <a href="shop.php" class="btn btn-primary" style="margin-top:0.6rem;">
            🛒 Mergi în shop
          </a>
        <?php else: ?>
          <form method="post" style="margin-top:1rem;">
            <input type="hidden" name="action" value="update" />

            <div class="contact-form" style="padding:1.2rem 1.3rem;">
              <?php foreach ($items as $item): ?>
                <?php $p = $item['product']; ?>
                <div style="display:flex; gap:1rem; align-items:center; margin-bottom:0.8rem;">
                  <div style="width:72px; height:72px; border-radius:0.75rem; overflow:hidden; background:#f3f4f6; flex-shrink:0;">
                    <?php if (!empty($p['image_url'])): ?>
                      <img src="<?php echo htmlspecialchars($p['image_url']); ?>"
                           alt="<?php echo htmlspecialchars($p['name']); ?>"
                           style="width:100%; height:100%; object-fit:cover;">
                    <?php endif; ?>
                  </div>

                  <div style="flex:1;">
                    <div style="font-weight:600;">
                      <?php echo htmlspecialchars($p['name']); ?>
                    </div>
                    <div style="font-size:0.85rem; color:#6b7280; margin-top:0.15rem;">
                      <?php echo number_format($item['unit_price'], 0, ',', ' '); ?> lei / buc
                    </div>
                  </div>

                  <div style="width:80px;">
                    <input
                      type="number"
                      name="qty[<?php echo (int)$p['id']; ?>]"
                      min="0"
                      max="50"
                      value="<?php echo (int)$item['qty']; ?>"
                      style="width:100%; border-radius:0.5rem; border:1px solid rgba(148,163,184,0.7); padding:0.3rem 0.4rem; text-align:center;"
                    />
                  </div>

                  <div style="width:110px; text-align:right; font-weight:600;">
                    <?php echo number_format($item['line_total'], 0, ',', ' '); ?> lei
                  </div>
                </div>
              <?php endforeach; ?>

              <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem; border-top:1px solid #e5e7eb; padding-top:0.8rem;">
                <div>
                  <button type="submit" class="btn btn-ghost">
                    Actualizează coșul
                  </button>
                </div>
                <div style="font-size:1rem;">
                  Total: <strong><?php echo number_format($totalAmount, 0, ',', ' '); ?> lei</strong>
                </div>
              </div>
            </div>
          </form>

          <form method="post" style="margin-top:0.9rem;">
            <input type="hidden" name="action" value="checkout" />
            <button type="submit" class="btn btn-primary">
              ✅ Plasează comanda
            </button>
            <a href="shop.php" class="btn btn-ghost" style="margin-left:0.4rem;">
              ↩ Continuă cumpărăturile
            </a>
          </form>
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
