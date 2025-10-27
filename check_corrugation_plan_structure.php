<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4', 'root', '');

echo "=== СТРУКТУРА ТАБЛИЦЫ corrugation_plan ===\n\n";
$stmt = $pdo->query("DESCRIBE corrugation_plan");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "{$col['Field']}: {$col['Type']} {$col['Key']} {$col['Extra']}\n";
}

echo "\n=== ПРИМЕР ДАННЫХ ===\n";
$stmt = $pdo->query("SELECT * FROM corrugation_plan WHERE order_number = '43-50-25' LIMIT 3");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

