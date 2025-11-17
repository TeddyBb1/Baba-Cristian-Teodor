<?php
session_start();
require __DIR__ . '/config.php';

$errorMessage = '';
$email = '';

// dacă ești deja logat, nu are sens să mai vezi pagina de login
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errorMessage = 'Te rog completează atât e-mailul, cât și parola.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Adresa de e-mail nu pare validă.';
    } else {
        $stmt = $mysqli->prepare("
            SELECT id, full_name, email, password_hash, role
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];

                $upd = $mysqli->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                if ($upd) {
                    $upd->bind_param('i', $user['id']);
                    $upd->execute();
                    $upd->close();
                }

                header('Location: index.php');
                exit;
            } else {
                $errorMessage = 'Email sau parolă incorecte.';
            }
        } else {
            $errorMessage = 'Eroare internă. Încearcă din nou mai târziu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login — LayerLab 3D</title>
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
          <span class="badge auth-badge">Cont client LayerLab 3D</span>
          <h1>Intră în contul tău.</h1>
          <p>
            Autentifică-te pentru a gestiona comenzile și, mai încolo, review-urile la produse.
          </p>
        </div>

        <div class="auth-card">
          <h2 class="auth-title">Login</h2>
          <p class="auth-subtitle">Autentifică-te pentru a continua.</p>

          <?php if ($errorMessage): ?>
            <div class="form-alert form-alert-error" style="margin-bottom:0.8rem;">
              <?php echo htmlspecialchars($errorMessage); ?>
            </div>
          <?php endif; ?>

          <form class="auth-form" action="" method="post">
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

            <div class="form-field auth-password-field">
              <label for="password">Parolă</label>
              <div class="auth-password-wrapper">
                <input
                  type="password"
                  id="password"
                  name="password"
                  placeholder="••••••••"
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
              <div class="auth-extra-links">
                <a href="#!" class="auth-link">Ai uitat parola? (în curând)</a>
              </div>
            </div>

            <div class="auth-options">
              <label class="auth-remember">
                <input type="checkbox" name="remember" />
                <span>Ține-mă minte pe acest device</span>
              </label>
            </div>

            <button type="submit" class="btn btn-primary btn-full auth-btn-main">
              Login
            </button>

            <div class="auth-divider">
              <span></span>
              <p>sau</p>
              <span></span>
            </div>

            <a href="register.php" class="btn btn-ghost btn-full">
              Crează un cont nou
            </a>
          </form>

          <p class="auth-footer-text">
            Nu ai încă un cont?
            <a href="register.php" class="auth-link">Înregistrează-te</a>
          </p>
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
