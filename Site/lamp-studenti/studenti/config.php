<?php
date_default_timezone_set('Europe/Bucharest');
// restul config-ului tău...

$DB_HOST = 'mysql';      // numele serviciului din compose
$DB_USER = 'user';
$DB_PASS = 'password';
$DB_NAME = 'layerlab';   // baza unde ai tabela products

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$mysqli->query("SET time_zone = '+02:00'");


if ($mysqli->connect_error) {
    die('Eroare conexiune DB: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

