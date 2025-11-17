<?php
session_start();
require __DIR__ . '/config.php';

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']); // doar cantitățile, cum ai acum
}

// trebuie să fii logat
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId      = (int)$_SESSION['user_id'];
$userName    = $_SESSION['user_name'] ?? '';
$userInitial = $userName !== '' ? mb_substr($userName, 0, 1, 'UTF-8') : '?';
$userRole    = $_SESSION['user_role'] ?? 'user';

$addressError = '';
$addressSuccess = '';
$paymentError = '';
$paymentSuccess = '';

// citim userul (inclusiv adrese + plăți)
$stmt = $mysqli->prepare("
    SELECT
      full_name,
      email,
      phone,
      address_line1,
      address_line2,
      city,
      county,
      postal_code,
      preferred_payment_method,
      payment_notes
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo 'Contul nu a fost găsit.';
    exit;
}

// --------------------------------------------------
// Procesăm formularele (Adrese / Plăți)
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    // ====== FORMULAR ADRESĂ ======
    if ($formType === 'address') {
        $full_name   = trim($_POST['full_name'] ?? $user['full_name']);
        $phone       = trim($_POST['phone'] ?? '');
        $address1    = trim($_POST['address_line1'] ?? '');
        $address2    = trim($_POST['address_line2'] ?? '');
        $city        = trim($_POST['city'] ?? '');
        $county      = trim($_POST['county'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');

        if ($full_name === '') {
            $addressError = 'Te rog completează numele complet.';
        } elseif ($address1 === '' || $city === '' || $county === '' || $postal_code === '') {
            $addressError = 'Te rog completează câmpurile obligatorii de adresă (stradă, oraș, județ, cod poștal).';
        } else {
            $stmtUp = $mysqli->prepare("
                UPDATE users
                SET
                  full_name     = ?,
                  phone         = ?,
                  address_line1 = ?,
                  address_line2 = ?,
                  city          = ?,
                  county        = ?,
                  postal_code   = ?
                WHERE id = ?
            ");

            if ($stmtUp) {
                $stmtUp->bind_param(
                    'sssssssi',
                    $full_name,
                    $phone,
                    $address1,
                    $address2,
                    $city,
                    $county,
                    $postal_code,
                    $userId
                );

                if ($stmtUp->execute()) {
                    $addressSuccess = 'Am salvat adresa de livrare.';

                    // actualizăm datele în array-ul user + sesiune
                    $user['full_name']   = $full_name;
                    $user['phone']       = $phone;
                    $user['address_line1'] = $address1;
                    $user['address_line2'] = $address2;
                    $user['city']        = $city;
                    $user['county']      = $county;
                    $user['postal_code'] = $postal_code;
                    $_SESSION['user_name'] = $full_name;
                } else {
                    $addressError = 'Nu am putut salva adresa. Încearcă din nou.';
                }
                $stmtUp->close();
            } else {
                $addressError = 'Eroare internă la salvarea adresei.';
            }
        }
    }

    // ====== FORMULAR PLĂȚI ======
    if ($formType === 'payment') {
        $payment      = $_POST['payment_method'] ?? 'ramburs';
        $paymentNotes = trim($_POST['payment_notes'] ?? '');

        $validPayments = ['ramburs', 'transfer_bancar', 'card_online', 'altul'];
        if (!in_array($payment, $validPayments, true)) {
            $payment = 'ramburs';
        }

        $stmtPay = $mysqli->prepare("
            UPDATE users
            SET
              preferred_payment_method = ?,
              payment_notes            = ?
            WHERE id = ?
        ");

        if ($stmtPay) {
            $stmtPay->bind_param('ssi', $payment, $paymentNotes, $userId);

            if ($stmtPay->execute()) {
                $paymentSuccess = 'Am salvat preferințele de plată.';
                $user['preferred_payment_method'] = $payment;
                $user['payment_notes']            = $paymentNotes;
            } else {
                $paymentError = 'Nu am putut salva preferințele de plată. Încearcă din nou.';
            }
            $stmtPay->close();
        } else {
            $paymentError = 'Eroare internă la salvarea preferințelor de plată.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Adrese & plăți — LayerLab 3D</title>
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
        <div class="account-header" style="margin-bottom:1.4rem;">
          <div>
            <h1>Adrese &amp; plăți</h1>
            <p class="account-subtitle">
              În stânga salvezi adresa ta de livrare, în dreapta alegi cum preferi să plătești.
              Datele sunt folosite automat la următoarele comenzi.
            </p>
          </div>
        </div>

        <!-- două carduri: Adrese / Plăți -->
        <div class="account-two-col"
             style="display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1.1fr);gap:1.8rem;align-items:flex-start;">

          <!-- CARD ADRESE -->
          <div class="contact-form">
            <h2 style="margin-top:0;margin-bottom:0.6rem;">Adresa de livrare</h2>
            <p style="margin-top:0;margin-bottom:0.9rem;font-size:0.9rem;color:#4b5563;">
              Completează datele tale pentru livrare. Poți folosi această adresă pentru toate comenzile.
            </p>

            <?php if ($addressError): ?>
              <div class="form-alert form-alert-error">
                <?php echo htmlspecialchars($addressError); ?>
              </div>
            <?php endif; ?>

            <?php if ($addressSuccess): ?>
              <div class="form-alert form-alert-success">
                <?php echo htmlspecialchars($addressSuccess); ?>
              </div>
            <?php endif; ?>

            <form method="post">
              <input type="hidden" name="form_type" value="address">

              <div class="form-row">
                <div class="form-field">
                  <label for="full_name">Nume complet</label>
                  <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    required
                    value="<?php echo htmlspecialchars($user['full_name']); ?>"
                  >
                </div>
                <div class="form-field">
                  <label>E-mail</label>
                  <input
                    type="email"
                    value="<?php echo htmlspecialchars($user['email']); ?>"
                    disabled
                  >
                </div>
              </div>

              <div class="form-row">
                <div class="form-field">
                  <label for="phone">Telefon</label>
                  <input
                    type="text"
                    id="phone"
                    name="phone"
                    placeholder="Ex: 07xx xxx xxx"
                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                  >
                </div>
              </div>

              <div class="form-row">
                <div class="form-field">
                  <label for="address_line1">Adresă (stradă, număr) *</label>
                  <input
                    type="text"
                    id="address_line1"
                    name="address_line1"
                    required
                    value="<?php echo htmlspecialchars($user['address_line1'] ?? ''); ?>"
                  >
                </div>
              </div>

              <div class="form-row">
                <div class="form-field">
                  <label for="address_line2">Bloc, scară, etaj, apartament (opțional)</label>
                  <input
                    type="text"
                    id="address_line2"
                    name="address_line2"
                    value="<?php echo htmlspecialchars($user['address_line2'] ?? ''); ?>"
                  >
                </div>
              </div>

              <div class="form-row">
                <div class="form-field">
                  <label for="city">Oraș *</label>
                  <input
                    type="text"
                    id="city"
                    name="city"
                    required
                    value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"
                  >
                </div>
                <div class="form-field">
                  <label for="county">Județ *</label>
                  <input
                    type="text"
                    id="county"
                    name="county"
                    required
                    value="<?php echo htmlspecialchars($user['county'] ?? ''); ?>"
                  >
                </div>
                <div class="form-field">
                  <label for="postal_code">Cod poștal *</label>
                  <input
                    type="text"
                    id="postal_code"
                    name="postal_code"
                    required
                    value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>"
                  >
                </div>
              </div>

              <button type="submit" class="btn btn-primary btn-full">
                Salvează adresa
              </button>
            </form>
          </div>

          <!-- CARD PLĂȚI -->
          <div class="contact-form">
            <h2 style="margin-top:0;margin-bottom:0.6rem;">Plăți &amp; facturare</h2>
            <p style="margin-top:0;margin-bottom:0.9rem;font-size:0.9rem;color:#4b5563;">
              Alege cum preferi să plătești și lasă eventuale detalii pentru factură sau livrare.
            </p>

            <?php if ($paymentError): ?>
              <div class="form-alert form-alert-error">
                <?php echo htmlspecialchars($paymentError); ?>
              </div>
            <?php endif; ?>

            <?php if ($paymentSuccess): ?>
              <div class="form-alert form-alert-success">
                <?php echo htmlspecialchars($paymentSuccess); ?>
              </div>
            <?php endif; ?>

            <form method="post">
              <input type="hidden" name="form_type" value="payment">

              <div class="form-row">
                <div class="form-field">
                  <label for="payment_method">Preferință de plată</label>
                  <?php $currentPay = $user['preferred_payment_method'] ?? 'ramburs'; ?>
                  <select id="payment_method" name="payment_method">
                    <option value="ramburs" <?php echo $currentPay === 'ramburs' ? 'selected' : ''; ?>>
                      Ramburs la curier
                    </option>
                    <option value="transfer_bancar" <?php echo $currentPay === 'transfer_bancar' ? 'selected' : ''; ?>>
                      Transfer bancar
                    </option>
                    <option value="card_online" <?php echo $currentPay === 'card_online' ? 'selected' : ''; ?>>
                      Card online (în curând)
                    </option>
                    <option value="altul" <?php echo $currentPay === 'altul' ? 'selected' : ''; ?>>
                      Altceva / discutăm la telefon
                    </option>
                  </select>
                </div>
              </div>

              <div class="form-row">
                <div class="form-field">
                  <label for="payment_notes">Detalii facturare / plată (opțional)</label>
                  <textarea
                    id="payment_notes"
                    name="payment_notes"
                    rows="4"
                    placeholder="Ex: date firmă pentru factură, interval preferat livrare, alte mențiuni..."
                  ><?php echo htmlspecialchars($user['payment_notes'] ?? ''); ?></textarea>
                </div>
              </div>

              <button type="submit" class="btn btn-primary btn-full">
                Salvează preferințele de plată
              </button>
            </form>
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
