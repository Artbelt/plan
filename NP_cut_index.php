<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");

// Загружаем статусы всех заявок
$orders = $pdo->query("
    SELECT DISTINCT order_number, cut_ready, cut_confirmed, plan_ready, corr_ready, build_ready
    FROM orders
    WHERE hide IS NULL OR hide != 1
")->fetchAll(PDO::FETCH_ASSOC);

// Загружаем заявки, по которым уже есть гофроплан
$stmt = $pdo->query("SELECT DISTINCT order_number FROM corrugation_plan");
$corr_done = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Этапы планирования</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f8f8f8;
            margin: 20px;
        }
        h2 { text-align: center; }
        table {
            border-collapse: collapse;
            width: 95%;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px 15px;
            text-align: center;
        }
        th {
            background: #f0f0f0;
            font-weight: bold;
        }
        a.btn {
            padding: 6px 12px;
            display: inline-block;
            background: #1976d2;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        a.btn:hover {
            background: #1565c0;
        }
        .done {
            color: green;
            font-weight: bold;
        }
        .disabled {
            color: #aaa;
        }
    </style>
</head>
<body>
<h2>Планирование заявок</h2>
<table>
    <tr>
        <th>Заявка</th>
        <th>Раскрой</th>
        <th>Утверждение</th>
        <th>План раскроя</th>
        <th>План гофрирования</th>
        <th>План сборки</th>
    </tr>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td><?= htmlspecialchars($o['order_number']) ?></td>

            <td>
                <?php if ($o['cut_ready']): ?>
                    <span class="done">✅</span>
                <?php else: ?>
                    <a class="btn" target="_blank" href="NP_cut_plan.php?order=<?= urlencode($o['order_number']) ?>">Сделать раскрой</a>
                <?php endif; ?>
            </td>

            <td>
                <?php if (!$o['cut_ready']): ?>
                    <span class="disabled">Не готово</span>
                <?php elseif ($o['cut_confirmed']): ?>
                    <span class="done">✅</span>
                <?php else: ?>
                    <a class="btn" href="NP/confirm_cut.php?order=<?= urlencode($o['order_number']) ?>">Утвердить</a>
                <?php endif; ?>
            </td>

            <td>
                <?php if (!$o['cut_confirmed']): ?>
                    <span class="disabled">Не утверждено</span>
                <?php elseif ($o['plan_ready']): ?>
                    <span class="done">✅</span>
                <?php else: ?>
                    <a class="btn" href="NP_roll_plan.php?order=<?= urlencode($o['order_number']) ?>">Планировать раскрой</a>
                <?php endif; ?>
            </td>

            <td>
                <?php if (!$o['plan_ready']): ?>
                    <span class="disabled">Не готов план раскроя</span>
                <?php elseif (isset($corr_done[$o['order_number']])): ?>
                    <span class="done">✅</span>
                <?php else: ?>
                    <a class="btn" href="NP_corrugation_plan.php?order=<?= urlencode($o['order_number']) ?>">Планировать гофрирование</a>
                <?php endif; ?>
            </td>

            <td>
                <?php if (!isset($corr_done[$o['order_number']])): ?>
                    <span class="disabled">Нет гофроплана</span>
                <?php elseif ($o['build_ready']): ?>
                    <span class="done">✅</span>
                <?php else: ?>
                    <a class="btn" href="NP_build_plan.php?order=<?= urlencode($o['order_number']) ?>">Планировать сборку</a>
                <?php endif; ?>
            </td>

        </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
