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

$userId = (int)($_SESSION['user_id'] ?? 0);

// citim datele utilizatorului din DB
$stmt = $mysqli->prepare("
    SELECT id, full_name, email, password_hash
    FROM users
    WHERE id = ?
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();
} else {
    $user = null;
}

// dacă nu mai există userul (cont șters etc.) – îl scoatem din sesiune
if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// mesaje pentru cele 2 formulare
$profileError   = '';
$profileSuccess = '';
$passError      = '';
$passSuccess    = '';

// procesează formularele
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if ($full_name === '' || $email === '') {
            $profileError = 'Te rog completează numele și e-mailul.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileError = 'Adresa de e-mail nu pare validă.';
        } else {
            // verificăm dacă emailul e folosit de alt cont
            $stmtCheck = $mysqli->prepare("
                SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1
            ");
            if ($stmtCheck) {
                $stmtCheck->bind_param('si', $email, $userId);
                $stmtCheck->execute();
                $resCheck = $stmtCheck->get_result();
                $existing = $resCheck ? $resCheck->fetch_assoc() : null;
                $stmtCheck->close();

                if ($existing) {
                    $profileError = 'Există deja un alt cont cu acest e-mail.';
                } else {
                    // update efectiv
                    $stmtUpd = $mysqli->prepare("
                        UPDATE users
                        SET full_name = ?, email = ?
                        WHERE id = ?
                        LIMIT 1
                    ");
                    if ($stmtUpd) {
                        $stmtUpd->bind_param('ssi', $full_name, $email, $userId);
                        if ($stmtUpd->execute()) {
                            $profileSuccess      = 'Setările de profil au fost actualizate.';
                            $user['full_name']   = $full_name;
                            $user['email']       = $email;

                            // update și în sesiune
                            $_SESSION['user_name'] = $full_name;
                        } else {
                            $profileError = 'A apărut o eroare la salvare. Încearcă din nou.';
                        }
                        $stmtUpd->close();
                    } else {
                        $profileError = 'Eroare internă. Încearcă mai târziu.';
                    }
                }
            } else {
                $profileError = 'Eroare internă. Încearcă mai târziu.';
            }
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $passError = 'Te rog completează toate câmpurile pentru parolă.';
        } elseif (strlen($new) < 6) {
            $passError = 'Noua parolă trebuie să aibă minim 6 caractere.';
        } elseif ($new !== $confirm) {
            $passError = 'Cele două câmpuri pentru noua parolă nu se potrivesc.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $passError = 'Parola curentă este incorectă.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);

            $stmtPass = $mysqli->prepare("
                UPDATE users
                SET password_hash = ?
                WHERE id = ?
                LIMIT 1
            ");
            if ($stmtPass) {
                $stmtPass->bind_param('si', $newHash, $userId);
                if ($stmtPass->execute()) {
                    $passSuccess              = 'Parola a fost schimbată cu succes.';
                    $user['password_hash']    = $newHash;
                } else {
                    $passError = 'Nu am putut schimba parola. Încearcă din nou.';
                }
                $stmtPass->close();
            } else {
                $passError = 'Eroare internă. Încearcă mai târziu.';
            }
        }
    }
}

// pentru header (nume + inițială)
$userName    = $_SESSION['user_name'] ?? $user['full_name'] ?? '';
$userInitial = $userName !== '' ? mb_substr($userName, 0, 1, 'UTF-8') : '?';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Setări cont — LayerLab 3D</title>
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
            $userName    = $_SESSION['user_name'] ?? '';
            $userInitial = $userName !== '' ? mb_substr($userName, 0, 1, 'UTF-8') : '?';
            $userRole    = $_SESSION['user_role'] ?? 'user';
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
            <h1>Setări cont</h1>
            <p class="account-subtitle">
              Actualizează informațiile tale de bază și parola pentru contul LayerLab 3D.
            </p>
          </div>
        </div>

        <div class="account-grid-2">
          <!-- CARD PROFIL -->
          <div class="account-card">
            <div class="account-card-header">
              <h2>Informații profil</h2>
            </div>
            <p class="account-card-text">
              Numele va fi folosit pe pagină și în comunicarea legată de comenzi.
            </p>

            <?php if ($profileError): ?>
              <div class="form-alert form-alert-error">
                <?php echo htmlspecialchars($profileError); ?>
              </div>
            <?php elseif ($profileSuccess): ?>
              <div class="form-alert form-alert-success">
                <?php echo htmlspecialchars($profileSuccess); ?>
              </div>
            <?php endif; ?>

            <form method="post" class="auth-form" action="">
              <input type="hidden" name="action" value="update_profile">

              <div class="form-field">
                <label for="full_name">Nume complet</label>
                <input
                  type="text"
                  id="full_name"
                  name="full_name"
                  required
                  value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                >
              </div>

              <div class="form-field">
                <label for="email">E-mail</label>
                <input
                  type="email"
                  id="email"
                  name="email"
                  required
                  value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                >
              </div>

              <button type="submit" class="btn btn-primary">
                Salvează modificările
              </button>
            </form>
          </div>

          <!-- CARD PAROLĂ -->
          <div class="account-card">
            <div class="account-card-header">
              <h2>Schimbă parola</h2>
            </div>
            <p class="account-card-text">
              Îți recomandăm să folosești o parolă unică, pe care nu o reutilizezi și pe alte site-uri.
            </p>

            <?php if ($passError): ?>
              <div class="form-alert form-alert-error">
                <?php echo htmlspecialchars($passError); ?>
              </div>
            <?php elseif ($passSuccess): ?>
              <div class="form-alert form-alert-success">
                <?php echo htmlspecialchars($passSuccess); ?>
              </div>
            <?php endif; ?>

            <form method="post" class="auth-form" action="">
              <input type="hidden" name="action" value="change_password">

              <div class="form-field">
                <label for="current_password">Parola curentă</label>
                <input
                  type="password"
                  id="current_password"
                  name="current_password"
                  required
                >
              </div>

              <div class="form-field">
                <label for="new_password">Parolă nouă</label>
                <input
                  type="password"
                  id="new_password"
                  name="new_password"
                  placeholder="Minim 6 caractere"
                  required
                >
              </div>

              <div class="form-field">
                <label for="new_password_confirm">Confirmă parola nouă</label>
                <input
                  type="password"
                  id="new_password_confirm"
                  name="new_password_confirm"
                  required
                >
              </div>

              <button type="submit" class="btn btn-primary">
                Schimbă parola
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
    const nav       = document.querySelector('.nav');
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
