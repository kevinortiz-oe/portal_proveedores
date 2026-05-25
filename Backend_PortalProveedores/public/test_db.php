<?php
$host = 'localhost';
$user = 'postgres';
$pass = 'postgres';

echo "Testing connection to postgres first...\n";
try {
    $pdo = new PDO("pgsql:host=$host;dbname=postgres", $user, $pass);
    echo "Connection to postgres SUCCESS!\n";
    $pdo = null;
} catch (PDOException $e) {
    echo "Connection to postgres FAILED: " . $e->getMessage() . "\n";
}

echo "Testing connection to portal_prov next...\n";
try {
    $pdo = new PDO("pgsql:host=$host;dbname=portal_prov", $user, $pass);
    echo "Connection to portal_prov SUCCESS!\n";
} catch (PDOException $e) {
    echo "Connection to portal_prov FAILED: " . $e->getMessage() . "\n";
}
