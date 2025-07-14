<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");
$order = $_POST['order'] ?? '';
$data = json_decode($_POST['plan_data'] ?? '[]', true);

if (!$order || !is_array($data)) {
    exit('Ошибка данных');
}

// Удалим предыдущие записи
$stmt = $pdo->prepare("DELETE FROM build_plan WHERE order_number = ?");
$stmt->execute([$order]);

// Вставим новые
$insert = $pdo->prepare("INSERT INTO build_plan (order_number, assign_date, filter_label, count) VALUES (?, ?, ?, ?)");

foreach ($data as $date => $items) {
    foreach ($items as $item) {
        $label = $item['label'] ?? '';
        $count = $item['count'] ?? 0;

        if ($label && $count > 0) {
            $insert->execute([$order, $date, $label, $count]);
        }
    }
}

// Отметим как готовое
$pdo->prepare("UPDATE orders SET build_ready = 1 WHERE order_number = ?")->execute([$order]);

// Вернёмся на главную
header("Location: ../NP_cut_index.php");
exit;
