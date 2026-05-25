<?php
$host = '192.177.80.111';
$db   = 'datagl';
$user = 'postgres';
$pass = 'Guat3#2025$L!veX';
$port = '5432';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'detalles_factura' AND table_schema = 'portal_prov' ORDER BY ordinal_position");
    while ($row = $stmt->fetch()) {
        echo $row['column_name'] . " (" . $row['data_type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
