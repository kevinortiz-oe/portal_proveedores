<?php
require 'vendor/autoload.php';
$app = require_once 'app/Config/Paths.php';
require_once 'system/Test/bootstrap.php';

$db = \Config\Database::connect();
$query = $db->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'facturas' AND table_schema = 'portal_prov'");
$results = $query->getResultArray();

foreach ($results as $row) {
    echo $row['column_name'] . " (" . $row['data_type'] . ") - Nullable: " . $row['is_nullable'] . "\n";
}
