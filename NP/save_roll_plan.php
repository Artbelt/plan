<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");
$data = json_decode(file_get_contents("php://input"), true);

$order = $data['order'] ?? '';
$plan = $data['plan'] ?? [];

if (!$order || !is_array($plan)) {
    http_response_code(400);
    echo "Некорректные данные";
    exit;
}

// Удалим все старые записи для этого order
$stmt = $pdo->prepare("DELETE FROM roll_plan WHERE order_number = ?");
$stmt->execute([$order]);

// Сохраняем новые
$insert = $pdo->prepare("INSERT INTO roll_plan (order_number, bale_id, plan_date) VALUES (?, ?, ?)");
foreach ($plan as $date => $baleIds) {
    foreach ($baleIds as $baleId) {
        $insert->execute([$order, $baleId, $date]);
    }
}

// Обновляем orders: отмечаем, что план раскроя завершён
$pdo->prepare("UPDATE orders SET plan_ready = 1 WHERE order_number = ?")->execute([$order]);

// Возвращаем текст для JS
echo "ok";
