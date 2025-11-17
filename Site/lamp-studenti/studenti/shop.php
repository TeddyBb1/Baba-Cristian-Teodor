<?php
session_start();
require __DIR__ . '/config.php';

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']); // doar cantitățile, cum ai acum
}


// Helper mic pentru afişarea stelelor
function render_stars(int $rating): string {
    $rating = max(1, min(5, $rating));
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= ($i <= $rating) ? '★' : '☆';
    }
    return $out;
}

/**
 * CATEGORII DISPONIBILE
 * cheie din URL => valoare din DB
 */
$validCategories = [
    'all'          => null,
    'birou-setup'  => 'Birou & setup',
    'decor-casa'   => 'Decor & casă',
    'piese-tehnice'=> 'Piese tehnice',
];

/**
 * SORTĂRI DISPONIBILE
 * cheie din URL => expresie ORDER BY
 */
$validSort = [
    'nou'        => 'created_at DESC',
    'pret-cresc' => 'price ASC',
    'pret-desc'  => 'price DESC',
];

// citim categoria din URL: ?category=decor-casa
$currentCategoryKey = $_GET['category'] ?? 'all';
if (!array_key_exists($currentCategoryKey, $validCategories)) {
    $currentCategoryKey = 'all';
}
$currentCategoryValue = $validCategories[$currentCategoryKey];

// citim termenul de căutare din URL: ?q=...
$searchTerm = trim($_GET['q'] ?? '');

// citim sortarea din URL: ?sort=pret-cresc
$sortKey = $_GET['sort'] ?? 'nou';
if (!array_key_exists($sortKey, $validSort)) {
    $sortKey = 'nou';
}
$orderBySql = $validSort[$sortKey];

// -------------------------
// Construim WHERE-ul dinamic (categorie + search)
// -------------------------
$whereParts = ['1=1'];

if ($currentCategoryValue !== null) {
    $catEsc = $mysqli->real_escape_string($currentCategoryValue);
    $whereParts[] = "category = '{$catEsc}'";
}

if ($searchTerm !== '') {
    $qEsc = $mysqli->real_escape_string($searchTerm);
    $whereParts[] = "(name LIKE '%{$qEsc}%' OR teaser LIKE '%{$qEsc}%' OR description LIKE '%{$qEsc}%')";
}

$whereSql = implode(' AND ', $whereParts);

// -------------------------
// Paginare
// -------------------------
$perPage = 8; // câte produse pe pagină

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// COUNT cu filtru (categorie + search)
$sqlCount = "SELECT COUNT(*) AS total FROM products WHERE {$whereSql}";
$resCount = $mysqli->query($sqlCount);
$rowCount = $resCount ? $resCount->fetch_assoc() : ['total' => 0];
$totalProducts = (int)$rowCount['total'];

$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

// SELECT cu filtru + paginare + sortare + rating
$sqlProducts = "
    SELECT p.*,
           pr.avg_rating,
           pr.review_count
    FROM products p
    LEFT JOIN (
        SELECT product_id,
               AVG(rating) AS avg_rating,
               COUNT(*)    AS review_count
        FROM product_reviews
        GROUP BY product_id
    ) pr ON pr.product_id = p.id
    WHERE {$whereSql}
    ORDER BY {$orderBySql}
    LIMIT {$perPage} OFFSET {$offset}
";
$result = $mysqli->query($sqlProducts);
$products = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$startItem = $totalProducts > 0 ? $offset + 1 : 0;
$endItem   = $totalProducts > 0 ? min($offset + $perPage, $totalProducts) : 0;

// helper pentru linkurile de paginare (păstrăm categoria + search + sort)
function buildPageUrl(int $page, string $categoryKey, string $searchTerm, string $sortKey): string {
    $params = ['page' => $page];

    if ($categoryKey !== 'all') {
        $params['category'] = $categoryKey;
    }
    if ($searchTerm !== '') {
        $params['q'] = $searchTerm;
    }
    if ($sortKey !== 'nou') {
        $params['sort'] = $sortKey;
    }

    return 'shop.php?' . http_build_query($params);
}

// helper mic pentru linkuri de categorie care păstrează q + sort
function categoryUrl(string $key, string $searchTerm, string $sortKey): string {
    $params = [];
    if ($key !== 'all') {
        $params['category'] = $key;
    }
    if ($searchTerm !== '') {
        $params['q'] = $searchTerm;
    }
    if ($sortKey !== 'nou') {
        $params['sort'] = $sortKey;
    }
    if (empty($params)) {
        return 'shop.php';
    }
    return 'shop.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Toate produsele — LayerLab 3D</title>
  <link rel="stylesheet" href="style.css" />
</head>

<!-- Buton "Înapoi sus" -->
<button id="backToTop" class="back-to-top" aria-label="Înapoi sus">↑</button>

<script>
  // Apare doar când derulezi în jos
  const backToTop = document.getElementById('backToTop');
  window.addEventListener('scroll', () => {
    if (window.scrollY > 300) {
      backToTop.classList.add('visible');
    } else {
      backToTop.classList.remove('visible');
    }
  });

  // Scroll lin către începutul paginii
  backToTop.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
</script>

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
    <!-- HERO SHOP -->
    <section class="section section-alt shop-hero">
      <div class="container shop-hero-inner">
        <div>
          <h1>Toate produsele LayerLab 3D</h1>
          <p>
            Aici găsești toate obiectele printate 3D disponibile în shop:
            suporturi, organizatoare, decorațiuni și accesorii pentru setup.
          </p>
          <div class="shop-hero-meta">
            <span>📦 Produse realizate la comandă</span>
            <span>🇷🇴 Printate în România</span>
          </div>
        </div>
      </div>
    </section>

    <!-- TOOLBAR FILTRE / SEARCH -->
    <section class="section shop-toolbar-section">
      <div class="container">
        <div class="shop-toolbar">
          <!-- SEARCH: trimite q + păstrează categoria & sortarea curentă -->
          <form class="shop-search" method="get" action="shop.php">
            <?php if ($currentCategoryKey !== 'all'): ?>
              <input type="hidden" name="category" value="<?php echo htmlspecialchars($currentCategoryKey); ?>">
            <?php endif; ?>
            <?php if ($sortKey !== 'nou'): ?>
              <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
            <?php endif; ?>

            <label for="search" class="shop-search-label">Caută produs</label>
            <input
              type="text"
              id="search"
              name="q"
              class="shop-search-input"
              placeholder="Ex: suport căști, vază, organizer..."
              value="<?php echo htmlspecialchars($searchTerm); ?>"
            />
          </form>

          <div class="shop-filters">
            <div class="shop-chips">
              <a
                class="chip <?php echo $currentCategoryKey === 'all' ? 'chip-active' : ''; ?>"
                href="<?php echo htmlspecialchars(categoryUrl('all', $searchTerm, $sortKey)); ?>"
              >
                Toate
              </a>
              <a
                class="chip <?php echo $currentCategoryKey === 'birou-setup' ? 'chip-active' : ''; ?>"
                href="<?php echo htmlspecialchars(categoryUrl('birou-setup', $searchTerm, $sortKey)); ?>"
              >
                Birou &amp; setup
              </a>
              <a
                class="chip <?php echo $currentCategoryKey === 'decor-casa' ? 'chip-active' : ''; ?>"
                href="<?php echo htmlspecialchars(categoryUrl('decor-casa', $searchTerm, $sortKey)); ?>"
              >
                Decor &amp; casă
              </a>
              <a
                class="chip <?php echo $currentCategoryKey === 'piese-tehnice' ? 'chip-active' : ''; ?>"
                href="<?php echo htmlspecialchars(categoryUrl('piese-tehnice', $searchTerm, $sortKey)); ?>"
              >
                Piese tehnice
              </a>
            </div>

            <!-- SORTARE: form separat, cu auto-submit la change -->
            <form class="shop-sort" method="get" action="shop.php">
              <?php if ($currentCategoryKey !== 'all'): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($currentCategoryKey); ?>">
              <?php endif; ?>
              <?php if ($searchTerm !== ''): ?>
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>">
              <?php endif; ?>

              <label for="sort">Sortează după</label>
              <select id="sort" name="sort" onchange="this.form.submit()">
                <option value="nou" <?php if ($sortKey === 'nou') echo 'selected'; ?>>Cele mai noi</option>
                <option value="pret-cresc" <?php if ($sortKey === 'pret-cresc') echo 'selected'; ?>>
                  Preț: mic &rarr; mare
                </option>
                <option value="pret-desc" <?php if ($sortKey === 'pret-desc') echo 'selected'; ?>>
                  Preț: mare &rarr; mic
                </option>
              </select>
            </form>
          </div>
        </div>

        <div class="shop-grid-info">
          <?php if ($totalProducts > 0): ?>
            <span>
              Produse <?php echo $startItem; ?>–<?php echo $endItem; ?>
              din <?php echo $totalProducts; ?>
              <?php if ($currentCategoryValue !== null): ?>
                (filtru: <?php echo htmlspecialchars($currentCategoryValue); ?>)
              <?php endif; ?>
              <?php if ($searchTerm !== ''): ?>
                &mdash; rezultate pentru „<?php echo htmlspecialchars($searchTerm); ?>”
              <?php endif; ?>
            </span>
          <?php else: ?>
            <span>
              0 produse
              <?php if ($searchTerm !== ''): ?>
                pentru „<?php echo htmlspecialchars($searchTerm); ?>”
              <?php endif; ?>
            </span>
          <?php endif; ?>

          <span class="shop-grid-hint">
            Pentru ceva custom, poți oricând folosi secțiunea de
            <a href="index.php#custom">Comenzi custom</a>.
          </span>
        </div>

        <!-- GRID PRODUSE DIN BAZA DE DATE -->
        <div class="product-grid">
          <?php if (empty($products)): ?>
            <p>Nu am găsit produse pentru criteriile alese. Încearcă altă căutare sau altă categorie. 🙂</p>
          <?php else: ?>
            <?php foreach ($products as $product): ?>
              <article class="product-card">
                <div class="product-image">
                  <?php if (!empty($product['badge_label'])): ?>
                    <?php
                      $badgeClass = 'product-tag';
                      if ($product['badge_variant'] === 'alt') {
                        $badgeClass .= ' product-tag-alt';
                      }
                    ?>
                    <span class="<?php echo $badgeClass; ?>">
                      <?php echo htmlspecialchars($product['badge_label']); ?>
                    </span>
                  <?php endif; ?>

                  <?php if (!empty($product['image_url'])): ?>
                    <img
                      src="<?php echo htmlspecialchars($product['image_url']); ?>"
                      alt="<?php echo htmlspecialchars($product['name']); ?>"
                      class="product-image-real"
                    />
                  <?php else: ?>
                    <div class="image-placeholder">
                      <?php echo htmlspecialchars($product['name']); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="product-body">
                  <h3><?php echo htmlspecialchars($product['name']); ?></h3>

                  <?php if (!empty($product['teaser'])): ?>
                    <p><?php echo htmlspecialchars($product['teaser']); ?></p>
                  <?php endif; ?>

                  <?php
                    $avgRating   = isset($product['avg_rating']) ? (float)$product['avg_rating'] : null;
                    $reviewCount = isset($product['review_count']) ? (int)$product['review_count'] : 0;

                    if ($avgRating !== null && $reviewCount > 0):
                  ?>
                    <div class="product-rating">
                      <span class="product-rating-stars">
                        <?php echo render_stars((int)round($avgRating)); ?>
                      </span>
                      <span class="product-rating-count">
                        <?php echo $reviewCount; ?> review-uri
                      </span>
                    </div>
                  <?php endif; ?>

                  <div class="product-meta">
                    <?php if (!empty($product['meta1'])): ?>
                      <span><?php echo htmlspecialchars($product['meta1']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($product['meta2'])): ?>
                      <span><?php echo htmlspecialchars($product['meta2']); ?></span>
                    <?php endif; ?>
                  </div>

                  <div class="product-footer">
                    <span class="product-price">
                      <?php echo number_format($product['price'], 0, ',', ' '); ?> lei
                    </span>
                    <a
                      href="product.php?slug=<?php echo urlencode($product['slug']); ?>"
                      class="btn btn-outline"
                    >
                      Detalii / Comandă
                    </a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- PAGINARE DINAMICĂ -->
        <?php if ($totalPages > 1): ?>
          <div class="shop-pagination">
            <?php if ($page > 1): ?>
              <a
                class="btn btn-ghost"
                href="<?php echo htmlspecialchars(buildPageUrl($page - 1, $currentCategoryKey, $searchTerm, $sortKey)); ?>"
              >
                ◀ Pagina anterioară
              </a>
            <?php else: ?>
              <button class="btn btn-ghost" type="button" disabled>
                ◀ Pagina anterioară
              </button>
            <?php endif; ?>

            <span class="shop-page-indicator">
              Pagina <?php echo $page; ?> din <?php echo $totalPages; ?>
            </span>

            <?php if ($page < $totalPages): ?>
              <a
                class="btn btn-ghost"
                href="<?php echo htmlspecialchars(buildPageUrl($page + 1, $currentCategoryKey, $searchTerm, $sortKey)); ?>"
              >
                Pagina următoare ▶
              </a>
            <?php else: ?>
              <button class="btn btn-ghost" type="button" disabled>
                Pagina următoare ▶
              </button>
            <?php endif; ?>
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

  <script>
    // Toggle meniu mobil
    const navToggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('.nav');
    navToggle.addEventListener('click', () => {
      nav.classList.toggle('nav-open');
      navToggle.classList.toggle('nav-open');
    });

    // An curent în footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Dropdown user (meniul din nume)
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
