<?php
session_start();
require __DIR__ . '/config.php';

$errorMessage = '';
$successMessage = '';
$full_name = '';
$email = '';

// dacă ești deja logat, nu are sens să mai vezi pagina de register
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($full_name === '' || $email === '' || $password === '' || $password_confirm === '') {
        $errorMessage = 'Te rog completează toate câmpurile.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Adresa de e-mail nu pare validă.';
    } elseif (strlen($password) < 6) {
        $errorMessage = 'Parola trebuie să aibă minim 6 caractere.';
    } elseif ($password !== $password_confirm) {
        $errorMessage = 'Parolele nu se potrivesc.';
    } else {
        // verificăm dacă mai există acest email
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $existing = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if ($existing) {
                $errorMessage = 'Există deja un cont cu acest e-mail.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmtInsert = $mysqli->prepare("
                    INSERT INTO users (full_name, email, password_hash, role)
                    VALUES (?, ?, ?, 'user')
                ");

                if ($stmtInsert) {
                    $stmtInsert->bind_param('sss', $full_name, $email, $hash);
                    if ($stmtInsert->execute()) {
                        $newUserId = $stmtInsert->insert_id;
                        $stmtInsert->close();

                        // logăm automat user-ul
                        $_SESSION['user_id']   = $newUserId;
                        $_SESSION['user_name'] = $full_name;
                        $_SESSION['user_role'] = 'user';

                        header('Location: index.php');
                        exit;
                    } else {
                        $errorMessage = 'Eroare la salvarea contului. Încearcă din nou.';
                        $stmtInsert->close();
                    }
                } else {
                    $errorMessage = 'Eroare internă. Încearcă mai târziu.';
                }
            }
        } else {
            $errorMessage = 'Eroare internă. Încearcă mai târziu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Crează cont — LayerLab 3D</title>
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
    <section class="section auth-section">
      <div class="container auth-inner">
        <div class="auth-intro">
          <span class="badge auth-badge">Cont nou LayerLab 3D</span>
          <h1>Crează-ți un cont.</h1>
          <p>
            Cu un cont poți urmări comenzile, salva adrese și (mai încolo) lăsa review-uri la produse.
          </p>
        </div>

        <div class="auth-card">
          <h2 class="auth-title">Înregistrare</h2>
          <p class="auth-subtitle">Completează câteva detalii pentru a începe.</p>

          <?php if ($errorMessage): ?>
            <div class="form-alert form-alert-error" style="margin-bottom:0.8rem;">
              <?php echo htmlspecialchars($errorMessage); ?>
            </div>
          <?php endif; ?>

          <form class="auth-form" action="" method="post">
            <div class="form-field">
              <label for="full_name">Nume complet</label>
              <input
                type="text"
                id="full_name"
                name="full_name"
                placeholder="Ex: Baba Cristian-Teodor"
                required
                value="<?php echo htmlspecialchars($full_name); ?>"
              />
            </div>

            <div class="form-field">
              <label for="email">E-mail</label>
              <input
                type="email"
                id="email"
                name="email"
                placeholder="exemplu@mail.com"
                required
                value="<?php echo htmlspecialchars($email); ?>"
              />
            </div>

            <div class="form-field">
              <label for="password">Parolă</label>
              <div class="auth-password-wrapper">
                <input
                  type="password"
                  id="password"
                  name="password"
                  placeholder="Minim 6 caractere"
                  required
                />
                <button
                  type="button"
                  class="auth-toggle-password"
                  aria-label="Arată/ascunde parola"
                >
                  👁
                </button>
              </div>
            </div>

            <div class="form-field">
              <label for="password_confirm">Confirmă parola</label>
              <input
                type="password"
                id="password_confirm"
                name="password_confirm"
                placeholder="Rescrie parola"
                required
              />
            </div>

            <button type="submit" class="btn btn-primary btn-full auth-btn-main">
              Crează cont
            </button>

            <div class="auth-divider">
              <span></span>
              <p>ai deja cont?</p>
              <span></span>
            </div>

            <a href="login.php" class="btn btn-ghost btn-full">
              Mergi la login
            </a>
          </form>
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

    // Show / hide password
    const togglePasswordBtn = document.querySelector('.auth-toggle-password');
    const passwordInput = document.getElementById('password');

    if (togglePasswordBtn && passwordInput) {
      togglePasswordBtn.addEventListener('click', () => {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
      });
    }
  </script>
</body>
</html>
