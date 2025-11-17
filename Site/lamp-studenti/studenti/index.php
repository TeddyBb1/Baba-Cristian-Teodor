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

// 4 produse random pentru secțiunea "Produse populare" + rating
$popularProducts = [];

$sqlPopular = "
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
    ORDER BY RAND()
    LIMIT 4
";

if ($result = $mysqli->query($sqlPopular)) {
    $popularProducts = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>LayerLab 3D — Magazin printuri 3D</title>
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
    <!-- HERO -->
    <section class="hero">
      <div class="container hero-inner">
        <div class="hero-text">
          <span class="badge">Print 3D premium • Made in Ro</span>
          <h1>Transformăm ideile tale în obiecte reale.</h1>
          <p>
            Produse printate 3D, finisate manual, pregătite să arate bine pe birou, în casă
            sau în setup-ul tău de gaming. Design modern, calitate de studio.
          </p>

          <div class="hero-actions">
            <a href="shop.php" class="btn btn-primary">🛒 Vezi produsele</a>
            <a href="#contact" class="btn btn-ghost">📎 Trimite un model 3D</a>
          </div>

          <div class="hero-meta">
            <span>⚙️ PLA+/PETG de calitate</span>
            <span>🎨 Culori custom la cerere</span>
            <span>📦 Livrare rapidă în toată țara</span>
          </div>
        </div>

        <div class="hero-visual" aria-hidden="true">
          <div class="hero-card">
            <div class="hero-shape hero-shape-main"></div>
            <div class="hero-shape hero-shape-secondary"></div>
            <div class="hero-tag">Print 3D în lucru…</div>
            <div class="hero-product-mock">
              <img src="images/hero-print3d.jpg" alt="Print 3D în lucru" class="hero-image" />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- PRODUSE POPULARE (din DB, random) -->
    <section id="produse" class="section">
      <div class="container">
        <div class="section-header">
          <h2>Produse populare</h2>
          <p>Alege din colecția noastră de obiecte printate 3D gata de livrare.</p>
        </div>

        <div class="product-grid">
          <?php if (empty($popularProducts)): ?>
            <p>Produsele populare vor apărea aici imediat ce le adăugăm în shop.</p>
          <?php else: ?>
            <?php foreach ($popularProducts as $product): ?>
              <article class="product-card">
                <div class="product-image">
                  <?php if (!empty($product['badge_label'])): ?>
                    <?php
                      $badgeClass = 'product-tag';
                      if (!empty($product['badge_variant']) && $product['badge_variant'] === 'alt') {
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
                      <?php echo number_format((int)$product['price'], 0, ',', ' '); ?> lei
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
      </div>
    </section>

    <!-- CUM FUNCȚIONEAZĂ -->
    <section id="cum-functioneaza" class="section section-alt">
      <div class="container">
        <div class="section-header">
          <h2>Cum funcționează</h2>
          <p>Proces simplu, rezultat de studio.</p>
        </div>

        <div class="steps-grid">
          <div class="step">
            <div class="step-icon">1</div>
            <h3>Alegi produsul sau ideea</h3>
            <p>
              Selectezi un produs din shop sau ne scrii ce vrei să printăm (ex: suport, piesă, obiect pentru cadou).
            </p>
          </div>
          <div class="step">
            <div class="step-icon">2</div>
            <h3>Stabilim detaliile</h3>
            <p>
              Discutăm dimensiuni, culoare, material și dacă e nevoie ajustăm modelul 3D pentru un print curat.
            </p>
          </div>
          <div class="step">
            <div class="step-icon">3</div>
            <h3>Print & livrare</h3>
            <p>
              Facem printul, îl verificăm, îl împachetăm safe și îl trimitem spre tine, gata de folosit.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- COMENZI CUSTOM -->
    <section id="custom" class="section">
      <div class="container custom-inner">
        <div class="custom-text">
          <h2>Ai un model 3D sau o idee în cap?</h2>
          <p>
            Lucrăm și pe comenzi custom. Poți să ne trimiți un fișier STL/STEP sau doar o descriere și câteva poze,
            iar noi te ajutăm să îl transformi într-un obiect real.
          </p>
          <ul class="custom-list">
            <li>🔩 Piese tehnice, adaptoare, prinderi</li>
            <li>🎁 Cadouri personalizate cu nume / text</li>
            <li>🎮 Accesorii pentru PC, console și setup</li>
          </ul>
          <a href="#contact" class="btn btn-primary">Scrie-ne pentru o comandă custom</a>
        </div>

        <div class="custom-card">
          <h3>Info rapid comenzi custom</h3>
          <ul>
            <li>Fișiere acceptate: STL, 3MF, STEP</li>
            <li>Materiale: PLA+, PETG (altele la cerere)</li>
            <li>Termen standard: 2–5 zile lucrătoare</li>
            <li>Discount la comenzi de volum</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- CONTACT -->
    <section id="contact" class="section section-alt">
      <div class="container contact-inner">
        <div class="contact-text">
          <h2>Hai să facem ceva tare.</h2>
          <p>
            Scrie-ne câteva detalii despre ce ai vrea să printăm pentru tine. Răspundem de obicei în aceeași zi.
          </p>
        </div>

        <form class="contact-form" method="post" action="contact_submit.php" enctype="multipart/form-data">
          <!-- container pentru mesaje -->
          <div id="contactAlert" class="form-alert" style="display:none;"></div>

          <div class="form-row">
            <div class="form-field">
              <label for="name">Nume complet</label>
              <input
                type="text"
                id="name"
                name="name"
                placeholder="Ex: Baba Cristian-Teodor"
                required
              >
            </div>
            <div class="form-field">
              <label for="email">E-mail</label>
              <input
                type="email"
                id="email"
                name="email"
                placeholder="exemplu@mail.com"
                required
              >
            </div>
          </div>

          <div class="form-row">
            <div class="form-field">
              <label for="type">Tip comandă</label>
              <select id="type" name="type" required>
                <option value="">Alege...</option>
                <option value="produs-shop">
                  Produs din shop
                </option>
                <option value="custom-model">
                  Comandă custom (am model 3D)
                </option>
                <option value="custom-idee">
                  Comandă custom (am doar idee)
                </option>
              </select>
            </div>
            <div class="form-field">
              <label for="budget">Buget estimativ (lei)</label>
              <input
                type="number"
                id="budget"
                name="budget"
                min="0"
                step="10"
                placeholder="Ex: 150"
              >
            </div>
          </div>

          <div class="form-row">
            <div class="form-field">
              <label for="model_file">Fișier 3D (STL, 3MF, STEP)</label>
              <input
                type="file"
                id="model_file"
                name="model_file"
                accept=".stl,.3mf,.step,.stp"
              >
              <small style="font-size: 0.8rem; color: #6b7280;">
                Opțional. Max ~20MB, formate acceptate: STL, 3MF, STEP.
              </small>
            </div>
          </div>

          <div class="form-field">
            <label for="message">Detalii comandă</label>
            <textarea
              id="message"
              name="message"
              rows="4"
              placeholder="Ex: vreau un suport pentru controller Xbox, negru mat, cu logo mic în față..."
              required
            ></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-full">Trimite mesajul</button>
        </form>
      </div>
    </section>
  </main>

  <footer class="site-footer">
  <div class="footer-top">
    <div class="container footer-grid">

      <!-- Coloana 1 -->
      <div class="footer-col footer-brand">
        <span class="logo-text footer-logo">LayerLab 3D</span>
        <p class="footer-desc">
          Magazin de obiecte printate 3D, realizate la comandă în România.  
          Fiecare print este unic, gândit cu pasiune și precizie.
        </p>

        <div class="footer-socials">
          <a href="#" aria-label="Instagram">📸</a>
          <a href="#" aria-label="TikTok">🎵</a>
          <a href="#" aria-label="Facebook">💬</a>
        </div>
      </div>

      <!-- Coloana 2 -->
      <div class="footer-col">
        <h4>Informații utile</h4>
        <ul>
          <li><button data-footer-modal="faq">Întrebări frecvente</button></li>
          <li><button data-footer-modal="termeni">Termeni și condiții</button></li>
          <li><button data-footer-modal="cookies">Politică cookies</button></li>
          <li><button data-footer-modal="gdpr">Politica de confidențialitate</button></li>
          <li><button data-footer-modal="sal">Soluționarea litigiilor (SAL)</button></li>
          <li><button data-footer-modal="sol">Soluționarea online (SOL)</button></li>
        </ul>
      </div>

      <!-- Coloana 3 -->
      <div class="footer-col">
        <h4>Comenzi și livrare</h4>
        <ul>
          <li><button data-footer-modal="cum-comand">Cum comand online</button></li>
          <li><button data-footer-modal="plata">Modalități de plată</button></li>
          <li><button data-footer-modal="livrare">Livrarea comenzilor</button></li>
          <li><button data-footer-modal="retur">Returul comenzilor</button></li>
        </ul>
      </div>

      <!-- Coloana 4 -->
      <div class="footer-col footer-map">
        <h4>Unde ne găsești</h4>
        <div class="map-container">
          <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d712.0142909265422!2d26.073826269636456!3d44.45249905551489!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x40b201f9c0d61197%3A0xd00f9c6a81d6bf05!2s%C8%98oseaua%20Nicolae%20Titulescu%20119-117%2C%20Bucure%C8%99ti!5e0!3m2!1sen!2sro!4v1762771709167!5m2!1sen!2sro"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
          ></iframe>
        </div>

        <div class="footer-legal-logos">
          <a href="https://anpc.ro/ce-este-sal" target="_blank">
            <img src="images/sal.png" alt="SAL ANPC" />
          </a>
          <a href="https://ec.europa.eu/consumers/odr" target="_blank">
            <img src="images/sol.png" alt="SOL UE" />
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <p>&copy; <span id="year"></span> LayerLab 3D. Toate drepturile rezervate.</p>
  </div>

  <!-- MODAL pentru paginile legale -->
  <div id="footerModal" class="footer-modal">
    <div class="footer-modal-backdrop"></div>
    <div class="footer-modal-dialog">
      <button class="footer-modal-close" aria-label="Închide">✕</button>
      <h3 id="footerModalTitle"></h3>
      <div id="footerModalContent" class="footer-modal-content"></div>
    </div>
  </div>
</footer>


  <!-- Buton "Înapoi sus" -->
  <button id="backToTop" class="back-to-top" aria-label="Înapoi sus">↑</button>

  <script>
    // Toggle meniu mobil
    const navToggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('.nav');
    navToggle.addEventListener('click', () => {
      nav.classList.toggle('nav-open');
      navToggle.classList.toggle('nav-open');
    });

      document.getElementById('year').textContent = new Date().getFullYear();

  // Conținut pentru modale (placeholder — vom pune textele legale reale)
  const footerInfoContent = {
    faq: { title: 'Întrebări frecvente', html: '<p>Răspunsuri rapide la întrebările despre livrare, plată și produse.</p>' },
    termeni: { title: 'Termeni și condiții', html: '<p>Termenii de utilizare ai site-ului LayerLab 3D. În curând text complet.</p>' },
    cookies: { title: 'Politică cookies', html: '<p>Folosim cookies pentru analiză și funcționare corectă a site-ului.</p>' },
    gdpr: { title: 'Politica de confidențialitate', html: '<p>LayerLab 3D respectă confidențialitatea datelor tale personale.</p>' },
    sal: { title: 'Soluționarea alternativă a litigiilor', html: '<p>Poți apela la ANPC pentru soluționarea amiabilă a disputelor.</p>' },
    sol: { title: 'Soluționarea online a litigiilor', html: '<p>Platforma oficială a UE: <a href="https://ec.europa.eu/consumers/odr" target="_blank">ec.europa.eu/consumers/odr</a></p>' },
    'cum-comand': { title: 'Cum comand online', html: '<p>Adaugă produsele în coș, completează datele și confirmă comanda.</p>' },
    plata: { title: 'Modalități de plată', html: '<p>Plată ramburs sau prin transfer bancar.</p>' },
    livrare: { title: 'Livrarea comenzilor', html: '<p>Livrare prin curier rapid în 1–3 zile lucrătoare.</p>' },
    retur: { title: 'Returul comenzilor', html: '<p>Acceptăm retur în termen de 14 zile pentru produsele standard.</p>' },
  };

  const modal = document.getElementById('footerModal');
  const title = document.getElementById('footerModalTitle');
  const content = document.getElementById('footerModalContent');

  document.querySelectorAll('[data-footer-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const key = btn.getAttribute('data-footer-modal');
      const data = footerInfoContent[key];
      if (data) {
        title.textContent = data.title;
        content.innerHTML = data.html;
        modal.classList.add('show');
      }
    });
  });

  modal.querySelector('.footer-modal-close').addEventListener('click', () => modal.classList.remove('show'));
  modal.querySelector('.footer-modal-backdrop').addEventListener('click', () => modal.classList.remove('show'));

    // Buton "Înapoi sus"
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

    // AJAX pentru formularul de contact
    const contactForm = document.querySelector('.contact-form');
    const contactAlert = document.getElementById('contactAlert');

    if (contactForm && contactAlert) {
      contactForm.addEventListener('submit', async (e) => {
        e.preventDefault(); // oprim submitul clasic (fără reload)

        contactAlert.style.display = 'block';
        contactAlert.classList.remove('form-alert-success', 'form-alert-error');
        contactAlert.textContent = 'Se trimite mesajul...';

        const formData = new FormData(contactForm);

        try {
          const response = await fetch('contact_submit.php', {
            method: 'POST',
            body: formData
          });

          const data = await response.json();

          contactAlert.classList.remove('form-alert-success', 'form-alert-error');

          if (data.success) {
            contactAlert.classList.add('form-alert-success');
            contactAlert.textContent = data.message || 'Mesaj trimis cu succes.';
            contactForm.reset();
          } else {
            contactAlert.classList.add('form-alert-error');
            contactAlert.textContent = data.message || 'A apărut o eroare. Încearcă din nou.';
          }
        } catch (err) {
          contactAlert.classList.remove('form-alert-success');
          contactAlert.classList.add('form-alert-error');
          contactAlert.textContent = 'A apărut o eroare de rețea. Încearcă din nou.';
        }
      });
    }

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
