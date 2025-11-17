<?php
session_start();
require __DIR__ . '/config.php';

// Detectăm dacă cererea este AJAX (fetch / XHR)
$isAjax =
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
     strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_POST['ajax']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Metodă invalidă.'
        ]);
        exit;
    }

    header('Location: shop.php');
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
// acceptăm atât `qty` cât și `quantity`
$qty = (int)($_POST['qty'] ?? ($_POST['quantity'] ?? 1));

// fallback – după adăugare trimitem către coș (pentru non-AJAX)
$redirectAfter = 'cart.php';

if ($productId <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Produs invalid.'
        ]);
        exit;
    }

    header('Location: ' . $redirectAfter);
    exit;
}

// normalizăm cantitatea (1–10)
if ($qty < 1)  $qty = 1;
if ($qty > 10) $qty = 10;

// verificăm că produsul există
$stmt = $mysqli->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $productId);
$stmt->execute();
$res     = $stmt->get_result();
$product = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$product) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Produsul nu a fost găsit.'
        ]);
        exit;
    }

    header('Location: ' . $redirectAfter);
    exit;
}

// inițializăm coșul în sesiune dacă nu există
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// dacă produsul există în coș, creștem cantitatea
if (isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId] += $qty;
} else {
    $_SESSION['cart'][$productId] = $qty;
}

// mic limit ca să nu explodeze cantitatea
if ($_SESSION['cart'][$productId] > 50) {
    $_SESSION['cart'][$productId] = 50;
}

// număr total de produse în coș
$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
}

// Răspuns pentru AJAX
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'    => true,
        'message'    => 'Produsul a fost adăugat în coș.',
        'cart_count' => $cartCount,
    ]);
    exit;
}

// Varianta veche: redirect direct în coș
header('Location: cart.php?added=1');
exit;
