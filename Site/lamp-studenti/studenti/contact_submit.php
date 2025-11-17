<?php
header('Content-Type: application/json');

require __DIR__ . '/config.php';

$response = [
    'success' => false,
    'message' => 'Eroare necunoscută.'
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodă invalidă.';
    echo json_encode($response);
    exit;
}

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$type    = trim($_POST['type'] ?? '');
$budget  = trim($_POST['budget'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '' || $type === '') {
    $response['message'] = 'Te rog completează numele, e-mailul, tipul comenzii și mesajul.';
    echo json_encode($response);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Adresa de e-mail nu pare validă.';
    echo json_encode($response);
    exit;
}

// upload fișier (opțional)
$filePathRelative = null;
$originalName     = null;

if (!empty($_FILES['model_file']['name'])) {
    $allowedExt = ['stl', '3mf', 'step', 'stp'];
    $ext = strtolower(pathinfo($_FILES['model_file']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt, true)) {
        $response['message'] = 'Fișierul trebuie să fie STL, 3MF sau STEP.';
        echo json_encode($response);
        exit;
    }

    if ($_FILES['model_file']['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'A apărut o eroare la încărcarea fișierului.';
        echo json_encode($response);
        exit;
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $basename = bin2hex(random_bytes(8)) . '.' . $ext;
    $filePath = $uploadDir . '/' . $basename;

    if (!move_uploaded_file($_FILES['model_file']['tmp_name'], $filePath)) {
        $response['message'] = 'Nu am reușit să salvăm fișierul pe server.';
        echo json_encode($response);
        exit;
    }

    $filePathRelative = 'uploads/' . $basename;
    $originalName     = $_FILES['model_file']['name'];
}

// inserare în DB
$stmt = $mysqli->prepare("
    INSERT INTO contact_messages
        (full_name, email, type, budget, message, file_path, original_filename)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    $response['message'] = 'Eroare internă: nu s-a putut pregăti interogarea.';
    echo json_encode($response);
    exit;
}

$stmt->bind_param(
    'sssssss',
    $name,
    $email,
    $type,
    $budget,
    $message,
    $filePathRelative,
    $originalName
);

if (!$stmt->execute()) {
    $response['message'] = 'Eroare la salvarea în baza de date. Încearcă din nou.';
    $stmt->close();
    echo json_encode($response);
    exit;
}

$stmt->close();

$response['success'] = true;
$response['message'] = 'Mulțumim! Ți-am primit mesajul și îți răspundem cât de repede putem. 😊';
echo json_encode($response);
