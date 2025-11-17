<?php
session_start();
require __DIR__ . '/config.php';

$slug    = trim($_GET['slug'] ?? '');
$product = null;

if ($slug !== '') {
    $stmt = $mysqli->prepare("SELECT * FROM products WHERE slug = ? LIMIT 1");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result  = $stmt->get_result();
    $product = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$product) {
    http_response_code(404);
}

// număr produse în coș (pentru badge)
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
}

// -----------------------------
// Helper stele (★/☆)
// -----------------------------
function render_stars(int $rating): string {
    $rating = max(1, min(5, $rating));
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= ($i <= $rating) ? '★' : '☆';
    }
    return $out;
}

$reviewError   = '';
$reviewSuccess = '';
$hasPurchased  = false; // dacă userul chiar a cumpărat produsul

// ---------------------------------
// Verificăm dacă userul a cumpărat produsul
// ---------------------------------
if ($product && isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];

    $stmtP = $mysqli->prepare("
        SELECT COUNT(*) AS cnt
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.user_id = ?
          AND oi.product_id = ?
          AND o.status <> 'anulat'
    ");
    if ($stmtP) {
        $stmtP->bind_param('ii', $uid, $product['id']);
        $stmtP->execute();
        $resP = $stmtP->get_result();
        if ($resP && ($rowP = $resP->fetch_assoc())) {
            $hasPurchased = ((int)$rowP['cnt'] > 0);
        }
        $stmtP->close();
    }
}

// ---------------------------------
// Procesăm POST – doar pentru review
// ---------------------------------
if ($product && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_review') {
        if (!isset($_SESSION['user_id'])) {
            $reviewError = 'Trebuie să fii logat ca să poți lăsa un review.';
        } elseif (!$hasPurchased) {
            $reviewError = 'Poți lăsa review doar pentru produsele pe care le-ai cumpărat.';
        } else {
            $userId  = (int)$_SESSION['user_id'];
            $rating  = (int)($_POST['rating'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');

            if ($rating < 1 || $rating > 5) {
                $reviewError = 'Te rog alege un rating între 1 și 5 stele.';
            } elseif ($comment === '') {
                $reviewError = 'Te rog scrie câteva cuvinte despre produs.';
            } else {
                // verificăm dacă userul are deja review la acest produs
                $check = $mysqli->prepare("
                    SELECT id
                    FROM product_reviews
                    WHERE product_id = ? AND user_id = ?
                    LIMIT 1
                ");
                $check->bind_param('ii', $product['id'], $userId);
                $check->execute();
                $resExisting = $check->get_result();
                $existing    = $resExisting ? $resExisting->fetch_assoc() : null;
                $check->close();

                if ($existing) {
                    // UPDATE review existent
                    $revId = (int)$existing['id'];
                    $upd = $mysqli->prepare("
                        UPDATE product_reviews
                        SET rating = ?, comment = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    if ($upd) {
                        $upd->bind_param('isi', $rating, $comment, $revId);
                        if ($upd->execute()) {
                            $reviewSuccess = 'Review-ul tău a fost actualizat.';
                        } else {
                            $reviewError = 'Nu am putut salva modificările. Încearcă din nou.';
                        }
                        $upd->close();
                    } else {
                        $reviewError = 'Eroare internă la actualizarea review-ului.';
                    }
                } else {
                    // INSERT review nou
                    $ins = $mysqli->prepare("
                        INSERT INTO product_reviews (product_id, user_id, rating, comment)
                        VALUES (?, ?, ?, ?)
                    ");
                    if ($ins) {
                        $ins->bind_param('iiis', $product['id'], $userId, $rating, $comment);
                        if ($ins->execute()) {
                            $reviewSuccess = 'Mulțumim! Review-ul tău a fost adăugat.';
                        } else {
                            $reviewError = 'Nu am putut salva review-ul. Încearcă din nou.';
                        }
                        $ins->close();
                    } else {
                        $reviewError = 'Eroare internă la salvarea review-ului.';
                    }
                }

                // dacă totul a mers, redirect ca să evităm resubmit la refresh
                if ($reviewSuccess !== '') {
                    header('Location: product.php?slug=' . urlencode($slug) . '#reviews');
                    exit;
                }
            }
        }
    }
}

// ---------------------------------
// Citim review-urile + media de rating
// ---------------------------------
$reviews      = [];
$reviewCount  = 0;
$avgRating    = null;
$avgFormatted = null;
$userReview   = null;

if ($product) {
    // media + număr
    $stmtAvg = $mysqli->prepare("
        SELECT COUNT(*) AS cnt, AVG(rating) AS avg_rating
        FROM product_reviews
        WHERE product_id = ?
    ");
    $stmtAvg->bind_param('i', $product['id']);
    $stmtAvg->execute();
    $resAvg = $stmtAvg->get_result();
    if ($resAvg && ($rowAvg = $resAvg->fetch_assoc())) {
        $reviewCount = (int)$rowAvg['cnt'];
        $avgRating   = $rowAvg['avg_rating'] !== null ? (float)$rowAvg['avg_rating'] : null;
        if ($avgRating !== null) {
            $avgFormatted = number_format($avgRating, 1);
        }
    }
    $stmtAvg->close();

    // lista review-urilor (cu numele userilor)
    $stmtRev = $mysqli->prepare("
        SELECT pr.*, u.full_name
        FROM product_reviews pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.product_id = ?
        ORDER BY pr.created_at DESC
    ");
    $stmtRev->bind_param('i', $product['id']);
    $stmtRev->execute();
    $resRev  = $stmtRev->get_result();
    $reviews = $resRev ? $resRev->fetch_all(MYSQLI_ASSOC) : [];
    $stmtRev->close();

    // review-ul userului logat (pentru precompletare)
    if (isset($_SESSION['user_id'])) {
        $uid   = (int)$_SESSION['user_id'];
        $stmtU = $mysqli->prepare("
            SELECT *
            FROM product_reviews
            WHERE product_id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmtU->bind_param('ii', $product['id'], $uid);
        $stmtU->execute();
        $resU = $stmtU->get_result();
        $userReview = $resU ? $resU->fetch_assoc() : null;
        $stmtU->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>
    <?php echo $product ? htmlspecialchars($product['name']) . ' — LayerLab 3D' : 'Produs negăsit — LayerLab 3D'; ?>
  </title>
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

        <!-- Coș cu badge -->
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
    <?php if (!$product): ?>
      <!-- 404 PRODUS -->
      <section class="section section-alt">
        <div class="container">
          <div class="product-not-found">
            <h1>Produsul nu a fost găsit</h1>
            <p>
              Se pare că acest produs nu mai există sau linkul nu este corect.
            </p>
            <a href="shop.php" class="btn btn-primary">↩ Înapoi la toate produsele</a>
          </div>
        </div>
      </section>
    <?php else: ?>
      <!-- PAGINA DE PRODUS: HERO -->
      <section class="section section-alt product-hero">
        <div class="container">
          <a href="shop.php" class="product-back-link">← Înapoi la toate produsele</a>

          <div class="product-layout">
            <!-- GALERIE / IMAGINE PRODUS -->
            <div class="product-gallery">
              <?php if (!empty($product['badge_label'])): ?>
                <?php
                  $badgeClass = 'product-tag product-tag-floating';
                  if (!empty($product['badge_variant']) && $product['badge_variant'] === 'alt') {
                    $badgeClass .= ' product-tag-alt';
                  }
                ?>
                <span class="<?php echo $badgeClass; ?>">
                  <?php echo htmlspecialchars($product['badge_label']); ?>
                </span>
              <?php endif; ?>

              <div class="product-main-image">
                <?php if (!empty($product['image_url'])): ?>
                  <img
                    src="<?php echo htmlspecialchars($product['image_url']); ?>"
                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                    class="product-image-real product-image-large"
                  />
                <?php else: ?>
                  <div class="image-placeholder product-image-placeholder-large">
                    <?php echo htmlspecialchars($product['name']); ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- INFO PRODUS (sus) -->
            <div class="product-info">
              <?php if (!empty($product['category'])): ?>
                <div class="product-category-pill">
                  <?php echo htmlspecialchars($product['category']); ?>
                </div>
              <?php endif; ?>

              <h1 class="product-title">
                <?php echo htmlspecialchars($product['name']); ?>
              </h1>

              <?php if (!empty($product['teaser'])): ?>
                <p class="product-teaser">
                  <?php echo htmlspecialchars($product['teaser']); ?>
                </p>
              <?php endif; ?>

              <div class="product-price-block">
                <span class="product-price-main">
                  <?php echo number_format((int)$product['price'], 0, ',', ' '); ?> lei
                </span>
                <span class="product-price-sub">
                  Preț orientativ, în funcție de opțiuni (culoare, dimensiune etc.).
                </span>
              </div>

              <div class="product-meta-list">
                <?php if (!empty($product['meta1'])): ?>
                  <span class="product-meta-pill">
                    <?php echo htmlspecialchars($product['meta1']); ?>
                  </span>
                <?php endif; ?>
                <?php if (!empty($product['meta2'])): ?>
                  <span class="product-meta-pill">
                    <?php echo htmlspecialchars($product['meta2']); ?>
                  </span>
                <?php endif; ?>
              </div>

              <!-- 🛒 Adaugă în coș (AJAX) -->
              <form method="post" action="cart_add.php" class="add-to-cart-ajax" style="margin-top:0.8rem; margin-bottom:0.6rem;">
                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>" />
                <div class="form-field" style="max-width:130px; margin-bottom:0.5rem;">
                  <label for="qty">Cantitate</label>
                  <input
                    type="number"
                    id="qty"
                    name="qty"
                    min="1"
                    max="10"
                    value="1"
                  />
                </div>
                <button type="submit" class="btn btn-primary">
                  🛒 Adaugă în coș
                </button>
              </form>

              <div class="product-cta">
                <a href="cart.php" class="btn btn-ghost">
                  🧾 Vezi coșul
                </a>
              </div>

              <div class="product-small-print">
                <p>
                  Fiecare print este realizat la comandă. Poți vedea comanda în secțiunea
                  <strong> Comenzile mele</strong> după ce finalizezi checkout-ul. 🙂
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- DETALII + REVIEWS -->
      <section class="section product-details-section" id="reviews">
        <div class="container product-details-layout">
          <div class="product-details-main">
            <h2>Descriere detaliată</h2>

            <?php if (!empty($product['description'])): ?>
              <div class="product-description">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
              </div>
            <?php else: ?>
              <p>Descriere completă de completat ulterior.</p>
            <?php endif; ?>
          </div>

          <div class="product-details-side">
            <h3>Recenzii clienți</h3>

            <?php if ($reviewCount > 0 && $avgFormatted !== null): ?>
              <p class="product-reviews-intro">
                Scor mediu: <strong><?php echo $avgFormatted; ?></strong> din 5
                (<?php echo $reviewCount; ?> review-uri)
                <br>
                <span style="font-size:1rem;"><?php echo render_stars((int)round($avgRating)); ?></span>
              </p>
            <?php else: ?>
              <p class="product-reviews-intro">
                Acest produs nu are încă review-uri. Fii primul care lasă un feedback. ✨
              </p>
            <?php endif; ?>

            <!-- mesaje eroare / succes review -->
            <?php if ($reviewError): ?>
              <div class="form-alert form-alert-error" style="margin-bottom:0.8rem;">
                <?php echo htmlspecialchars($reviewError); ?>
              </div>
            <?php endif; ?>
            <?php if ($reviewSuccess): ?>
              <div class="form-alert form-alert-success" style="margin-bottom:0.8rem;">
                <?php echo htmlspecialchars($reviewSuccess); ?>
              </div>
            <?php endif; ?>

            <!-- Lista review-uri existente -->
            <?php if (!empty($reviews)): ?>
              <?php foreach ($reviews as $rev): ?>
                <div class="product-review-card">
                  <div class="product-review-header">
                    <span class="product-review-name">
                      <?php echo htmlspecialchars($rev['full_name']); ?>
                    </span>
                    <span class="product-review-stars">
                      <?php echo render_stars((int)$rev['rating']); ?>
                    </span>
                  </div>
                  <p class="product-review-text">
                    <?php echo nl2br(htmlspecialchars($rev['comment'])); ?>
                  </p>
                  <p style="margin:0.25rem 0 0; font-size:0.75rem; color:#9ca3af;">
                    <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($rev['created_at']))); ?>
                  </p>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <!-- Formular review (doar pentru cumpărători) -->
            <?php if (!isset($_SESSION['user_id'])): ?>
              <button class="btn btn-outline product-review-btn" type="button"
                onclick="window.location.href='login.php?redirect=<?php echo urlencode('product.php?slug=' . $slug . '#reviews'); ?>'">
                Loghează-te pentru a lăsa un review
              </button>
            <?php elseif (!$hasPurchased): ?>
              <div class="form-alert form-alert-error" style="margin-top:0.8rem;">
                Poți lăsa review doar pentru produsele pe care le-ai cumpărat.
              </div>
            <?php else: ?>
              <form method="post" class="auth-form" style="margin-top:1rem;">
                <input type="hidden" name="action" value="add_review" />

                <div class="form-field">
                  <label for="rating">Rating</label>
                  <select id="rating" name="rating" required>
                    <?php
                      $currentRating = $userReview ? (int)$userReview['rating'] : 5;
                      for ($i = 5; $i >= 1; $i--):
                    ?>
                      <option value="<?php echo $i; ?>" <?php echo $i === $currentRating ? 'selected' : ''; ?>>
                        <?php echo $i; ?> stele
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>

                <div class="form-field">
                  <label for="comment">
                    <?php echo $userReview ? 'Editează review-ul tău' : 'Scrie un review'; ?>
                  </label>
                  <textarea
                    id="comment"
                    name="comment"
                    rows="3"
                    required
                    placeholder="Cum ți s-a părut acest produs?"
                  ><?php echo $userReview ? htmlspecialchars($userReview['comment']) : ''; ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-full product-review-btn">
                  <?php echo $userReview ? 'Actualizează review-ul' : 'Trimite review'; ?>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <?php
      // produse din aceeași categorie (dacă avem categorie setată)
      $related = [];
      if (!empty($product['category'])) {
          $stmtRel = $mysqli->prepare("
              SELECT *
              FROM products
              WHERE category = ? AND slug <> ?
              ORDER BY created_at DESC
              LIMIT 4
          ");
          $stmtRel->bind_param('ss', $product['category'], $product['slug']);
          $stmtRel->execute();
          $resRel = $stmtRel->get_result();
          $related = $resRel ? $resRel->fetch_all(MYSQLI_ASSOC) : [];
          $stmtRel->close();
      }
      ?>

      <?php if (!empty($related)): ?>
        <section class="section">
          <div class="container">
            <div class="section-header">
              <h2>Alte produse din aceeași categorie</h2>
              <p>Îți pot plăcea și aceste modele printate 3D.</p>
            </div>

            <div class="product-grid">
              <?php foreach ($related as $rel): ?>
                <article class="product-card">
                  <div class="product-image">
                    <?php if (!empty($rel['badge_label'])): ?>
                      <?php
                        $badgeClass = 'product-tag';
                        if (!empty($rel['badge_variant']) && $rel['badge_variant'] === 'alt') {
                          $badgeClass .= ' product-tag-alt';
                        }
                      ?>
                      <span class="<?php echo $badgeClass; ?>">
                        <?php echo htmlspecialchars($rel['badge_label']); ?>
                      </span>
                    <?php endif; ?>

                    <?php if (!empty($rel['image_url'])): ?>
                      <img
                        src="<?php echo htmlspecialchars($rel['image_url']); ?>"
                        alt="<?php echo htmlspecialchars($rel['name']); ?>"
                        class="product-image-real"
                      />
                    <?php else: ?>
                      <div class="image-placeholder">
                        <?php echo htmlspecialchars($rel['name']); ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="product-body">
                    <h3><?php echo htmlspecialchars($rel['name']); ?></h3>
                    <?php if (!empty($rel['teaser'])): ?>
                      <p><?php echo htmlspecialchars($rel['teaser']); ?></p>
                    <?php endif; ?>

                    <div class="product-meta">
                      <?php if (!empty($rel['meta1'])): ?>
                        <span><?php echo htmlspecialchars($rel['meta1']); ?></span>
                      <?php endif; ?>
                      <?php if (!empty($rel['meta2'])): ?>
                        <span><?php echo htmlspecialchars($rel['meta2']); ?></span>
                      <?php endif; ?>
                    </div>

                    <div class="product-footer">
                      <span class="product-price">
                        <?php echo number_format((int)$rel['price'], 0, ',', ' '); ?> lei
                      </span>
                      <a
                        href="product.php?slug=<?php echo urlencode($rel['slug']); ?>"
                        class="btn btn-outline"
                      >
                        Detalii
                      </a>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>
    <?php endif; ?>
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

  <!-- Toast notificare -->
  <div id="toast" class="toast" aria-live="polite"></div>

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

    // ---------- Toast helper ----------
    function showToast(message) {
      const toast = document.getElementById('toast');
      if (!toast) return;

      toast.textContent = message;
      toast.classList.add('show');

      clearTimeout(window.__toastTimeout);
      window.__toastTimeout = setTimeout(() => {
        toast.classList.remove('show');
      }, 2800);
    }

    // ---------- AJAX Add to cart ----------
    document.querySelectorAll('form.add-to-cart-ajax').forEach(form => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        // marcăm că vrem răspuns JSON
        formData.append('ajax', '1');

        try {
          const response = await fetch(form.action || 'cart_add.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });

          const data = await response.json();

          if (data.success) {
            const badge = document.querySelector('.cart-badge');
            if (badge) {
              badge.textContent = data.cart_count;
              if (data.cart_count > 0) {
                badge.classList.add('is-visible');
              }
            }
            showToast(data.message || 'Produs adăugat în coș.');
          } else {
            showToast(data.message || 'Nu am putut adăuga produsul în coș.');
          }
        } catch (err) {
          console.error(err);
          showToast('A apărut o eroare de rețea. Încearcă din nou.');
        }
      });
    });
  </script>
</body>
</html>
