<?php
// Inițializare variabile
$nume = $email = $mesaj = "";
$erori = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Preluare și curățare date
    $nume  = trim($_POST["nume"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mesaj = trim($_POST["mesaj"] ?? "");

    // Validare Nume
    if (strlen($nume) < 3) {
        $erori["nume"] = "Numele trebuie să conțină minimum 3 caractere.";
    }

    // Validare Email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erori["email"] = "Introduceți o adresă de email validă.";
    }

    // Validare Mesaj
    if (strlen($mesaj) < 10) {
        $erori["mesaj"] = "Mesajul trebuie să conțină minimum 10 caractere.";
    }

    if (empty($erori)) {
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Formular de contact</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #eef2ff, #e0f7ff);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page {
            width: 100%;
            max-width: 550px;
            padding: 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 28px 30px 26px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(148, 163, 184, 0.35);
        }

        .card-header {
            margin-bottom: 18px;
        }

        .title {
            margin: 0;
            font-size: 1.7rem;
            font-weight: 700;
            color: #111827;
        }

        .subtitle {
            margin-top: 4px;
            font-size: 0.92rem;
            color: #6b7280;
        }

        form label {
            display: block;
            margin-top: 14px;
            font-size: 0.92rem;
            color: #374151;
            font-weight: 500;
        }

        .field {
            margin-top: 6px;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 9px 11px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
            background-color: #f9fafb;
        }

        input[type="text"]:focus,
        textarea:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
            background-color: #ffffff;
        }

        textarea {
            resize: vertical;
            min-height: 110px;
        }

        .error {
            margin-top: 4px;
            color: #b91c1c;
            font-size: 0.8rem;
        }

        .btn-row {
            margin-top: 22px;
            display: flex;
            justify-content: flex-end;
        }

        button[type="submit"] {
            padding: 9px 22px;
            border-radius: 999px;
            border: none;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.35);
            transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
        }

        button[type="submit"]:hover {
            transform: translateY(-1px);
            filter: brightness(1.03);
            box-shadow: 0 16px 30px rgba(37, 99, 235, 0.45);
        }

        button[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.3);
        }

        .success-banner {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #ecfdf3;
            border: 1px solid #22c55e33;
            color: #166534;
            font-size: 0.9rem;
        }

        .success-banner h3 {
            margin: 0 0 6px;
            font-size: 1rem;
        }

        .success-banner p {
            margin: 2px 0;
        }

        @media (max-width: 600px) {
            .card {
                padding: 22px 18px 20px;
            }

            .title {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>

<div class="page">
    <div class="card" id="card-container">
        <div class="card-header">
            <h1 class="title">Formular de contact</h1>
            <p class="subtitle">Completează câmpurile de mai jos și îți vom răspunde în cel mai scurt timp.</p>
        </div>

        <form method="post" id="contactForm">
            <label for="nume">Nume:</label>
            <div class="field">
                <input type="text" id="nume" name="nume"
                       value="<?= htmlspecialchars($nume) ?>"
                       placeholder="Ex.: Andrei Popescu">
                <?php if (isset($erori["nume"])): ?>
                    <div class="error"><?= $erori["nume"] ?></div>
                <?php endif; ?>
            </div>

            <label for="email">Email:</label>
            <div class="field">
                <input type="text" id="email" name="email"
                       value="<?= htmlspecialchars($email) ?>"
                       placeholder="exemplu@domeniu.ro">
                <?php if (isset($erori["email"])): ?>
                    <div class="error"><?= $erori["email"] ?></div>
                <?php endif; ?>
            </div>

            <label for="mesaj">Mesaj:</label>
            <div class="field">
                <textarea id="mesaj" name="mesaj"
                          placeholder="Scrie aici mesajul tău..."><?= htmlspecialchars($mesaj) ?></textarea>
                <?php if (isset($erori["mesaj"])): ?>
                    <div class="error"><?= $erori["mesaj"] ?></div>
                <?php endif; ?>
            </div>

            <div class="btn-row">
                <button type="submit">Trimite</button>
            </div>
        </form>
    </div>
</div>

<?php if ($success): ?>
<script>
// Ascundem formularul și afișăm mesajul de mulțumire în același card
const form = document.getElementById('contactForm');
const card = document.getElementById('card-container');

form.style.display = 'none';

const thankYouHtml = `
    <div class="success-banner">
        <h3>Mulțumim, <?= htmlspecialchars($nume) ?>!</h3>
        <p>Mesajul tău a fost trimis cu succes.</p>
        <p><strong>Mesaj:</strong></p>
        <p><?= nl2br(htmlspecialchars($mesaj)) ?></p>
    </div>
`;

card.insertAdjacentHTML('beforeend', thankYouHtml);
</script>
<?php endif; ?>

</body>
</html>
