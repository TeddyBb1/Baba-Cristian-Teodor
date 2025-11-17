<?php
session_start();

// ștergem toate datele din sesiune
$_SESSION = [];
session_unset();
session_destroy();

// opțional: ștergi și un cookie de "remember me" dacă vei avea
// setcookie('remember_token', '', time() - 3600, '/');

header('Location: index.php');
exit;
