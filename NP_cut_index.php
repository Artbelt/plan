<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Статусы заявок
$orders = $pdo->query("
    SELECT DISTINCT order_number, cut_ready, cut_confirmed, plan_ready, corr_ready, build_ready
    FROM orders
    WHERE hide IS NULL OR hide != 1
    ORDER BY order_number
")->fetchAll(PDO::FETCH_ASSOC);

// Заявки, по которым уже есть гофроплан
$stmt = $pdo->query("SELECT DISTINCT order_number FROM corrugation_plan");
$corr_done = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Этапы планирования</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --bg:#f6f7fb; --card:#ffffff; --text:#111827; --muted:#6b7280;
            --line:#e5e7eb; --ok:#16a34a; --accent:#2563eb; --accent2:#0ea5e9;
        }
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);margin:20px;color:var(--text)}
        h2{text-align:center;margin:6px 0 16px}
        .wrap{max-width:1200px;margin:0 auto}
        table{border-collapse:collapse;width:100%;background:#fff;border:1px solid var(--line);box-shadow:0 2px 10px rgba(0,0,0,.05)}
        th,td{border:1px solid var(--line);padding:10px 12px;text-align:center;vertical-align:middle}
        th{background:#f3f4f6;font-weight:600}
        td:first-child{font-weight:600}
        .btn{padding:6px 10px;border-radius:8px;background:var(--accent);color:#fff;text-decoration:none;border:1px solid var(--accent)}
        .btn:hover{filter:brightness(.95)}
        .btn-secondary{background:#eef6ff;color:var(--accent);border-color:#cfe0ff}
        .btn-print{background:#ecfeff;color:var(--accent2);border-color:#bae6fd}
        .done{color:var(--ok);font-weight:700}
        .disabled{color:#9ca3af}
        .stack{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
        .sub{display:block;font-size:12px;color:var(--muted);margin-top:4px}
        @media (max-width:900px){
            table{font-size:13px}
            .stack{gap:6px}
            .btn,.btn-secondary,.btn-print{padding:6px 8px}
        }
        @media print{
            body{background:#fff;margin:0}
            .wrap{max-width:none}
        }
    </style>
</head>
<body>
<div class="wrap">
    <h2>Планирование заявок</h2>

    <table>
        <tr>
            <th>Заявка</th>
            <th>Раскрой (подготовка)</th>
            <th>Утверждение</th>
            <th>План раскроя рулона</th>
            <th>План гофрирования</th>
            <th>План сборки</th>
        </tr>

        <?php foreach ($orders as $o): $ord = $o['order_number']; ?>
            <tr>
                <td><?= htmlspecialchars($ord) ?></td>

                <!-- Раскрой (подготовка) -->
                <td>
                    <?php if ($o['cut_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">

                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" target="_blank" href="NP_cut_plan.php?order=<?= urlencode($ord) ?>">Сделать раскрой</a>
                        </div>
                        <span class="sub">нет данных для просмотра</span>
                    <?php endif; ?>
                </td>

                <!-- Утверждение -->
                <td>
                    <?php if (!$o['cut_ready']): ?>
                        <span class="disabled">Не готово</span>
                    <?php elseif ($o['cut_confirmed']): ?>
                        <div class="done">✅ Утверждено</div>
                        <div class="stack">

                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP/confirm_cut.php?order=<?= urlencode($ord) ?>">Утвердить</a>
                        </div>
                        <span class="sub">утвердите, затем появится печать</span>
                    <?php endif; ?>
                </td>

                <!-- План раскроя рулона -->
                <td>
                    <?php if (!$o['cut_confirmed']): ?>
                        <span class="disabled">Не утверждено</span>
                    <?php elseif ($o['plan_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="NP_view_roll_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>

                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP_roll_plan.php?order=<?= urlencode($ord) ?>">Планировать раскрой</a>
                        </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                    <?php endif; ?>
                </td>

                <!-- План гофрирования -->
                <td>
                    <?php if (!$o['plan_ready']): ?>
                        <span class="disabled">Не готов план раскроя</span>
                    <?php elseif (isset($corr_done[$ord])): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="NP_view_corrugation_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>

                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP_corrugation_plan.php?order=<?= urlencode($ord) ?>">Планировать гофрирование</a>
                        </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                    <?php endif; ?>
                </td>

                <!-- План сборки -->
                <td>
                    <?php if (!isset($corr_done[$ord])): ?>
                        <span class="disabled">Нет гофроплана</span>
                    <?php elseif ($o['build_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <!-- у нас уже есть удобный просмотр/печать -->
                            <a class="btn-secondary" target="_blank" href="view_production_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>

                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP_build_plan.php?order=<?= urlencode($ord) ?>">Планировать сборку</a>
                        </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
