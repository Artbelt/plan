<?php
// view_production_plan.php — план vs факт по сборке для выбранной заявки

// Подключение к БД
$dsn = 'mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Получаем номер заявки
$order = $_GET['order'] ?? '';
if (!$order) {
    die("Не указан номер заявки.");
}

/* -----------------------
   1) ПЛАН (build_plan)
------------------------*/
$stmt = $pdo->prepare("
    SELECT assign_date, filter_label, `count`
    FROM build_plan
    WHERE order_number = ?
    ORDER BY assign_date ASC, filter_label
");
$stmt->execute([$order]);
$planRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Группируем план по датам и нормализуем названия
$planByDate = [];           // [$date][] = ['base'=>..., 'label'=>..., 'count'=>int]
$allPlanDates = [];

foreach ($planRows as $row) {
    $date = $row['assign_date'];
    $label = preg_replace('/\[.*$/', '', $row['filter_label']); // убираем всё после [
    $label = preg_replace('/[●◩⏃]/u', '', $label);              // убираем значки
    $label = trim($label);

    if (!isset($planByDate[$date])) $planByDate[$date] = [];
    $planByDate[$date][] = [
        'base'  => $label,
        'label' => $row['filter_label'],
        'count' => (int)$row['count'],
    ];
    $allPlanDates[$date] = true;
}

/* -----------------------
   2) ФАКТ (manufactured_production)
------------------------*/
$stmt = $pdo->prepare("
    SELECT date_of_production AS prod_date,
           TRIM(SUBSTRING_INDEX(name_of_filter,' [',1)) AS base_filter,
           SUM(count_of_filters) AS fact_count
    FROM manufactured_production
    WHERE name_of_order = ?
    GROUP BY prod_date, base_filter
    ORDER BY prod_date ASC, base_filter
");
$stmt->execute([$order]);
$factRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Группируем факт по датам и базовым фильтрам
$factByDate = [];          // [$date][$base_filter] = int fact
$allFactDates = [];

foreach ($factRows as $r) {
    $d = $r['prod_date'];
    $b = $r['base_filter'];
    $c = (int)$r['fact_count'];
    if (!$b) continue;

    if (!isset($factByDate[$d])) $factByDate[$d] = [];
    if (!isset($factByDate[$d][$b])) $factByDate[$d][$b] = 0;
    $factByDate[$d][$b] += $c;

    $allFactDates[$d] = true;
}

/* -----------------------
   3) Временной диапазон
------------------------*/
if ($planRows) {
    $dates = array_keys($allPlanDates + $allFactDates); // объединяем дни, где был план или факт
    sort($dates);
    $start = new DateTime(reset($dates));
    $end   = new DateTime(end($dates));
    $end->modify('+1 day');
} else {
    // если плана нет — покажем только даты факта
    if ($allFactDates) {
        $dates = array_keys($allFactDates);
        sort($dates);
        $start = new DateTime(reset($dates));
        $end   = new DateTime(end($dates));
        $end->modify('+1 day');
    } else {
        // ни плана, ни факта — пусто
        $dates = [];
        $start = new DateTime();
        $end   = new DateTime();
    }
}

$period = new DatePeriod($start, new DateInterval('P1D'), $end);

// Для прогресса по дню
function sumPlanForDay($items) {
    $s = 0; foreach ($items as $it) $s += (int)$it['count']; return $s;
}
function sumFactForDay($factMap) {
    $s = 0; foreach ($factMap as $v) $s += (int)$v; return $s;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>План и факт сборки по заявке № <?= htmlspecialchars($order) ?></title>
    <style>
        :root{
            --bg:#f6f7fb; --card:#fff; --text:#111827; --muted:#6b7280; --line:#e5e7eb;
            --ok:#16a34a; --warn:#d97706; --bad:#dc2626; --accent:#2563eb;
        }
        *{box-sizing:border-box}
        body{
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg); color: var(--text);
            margin: 0; padding: 16px; font-size: 14px;
        }
        h1{ text-align:center; margin: 6px 0 12px; font-weight: 700; }
        .toolbar{
            display:flex; gap:8px; flex-wrap:wrap; justify-content:center; align-items:center;
            margin-bottom: 12px;
        }
        .toolbar input[type="text"]{
            padding:8px 10px; border:1px solid var(--line); border-radius:8px; width:280px;
        }
        .btn, .btn-print{
            padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:#fff; cursor:pointer;
        }
        .btn-print{ background: #eaf1ff; color: var(--accent); border-color:#cfe0ff; font-weight:600; }

        .calendar{
            display:grid; grid-template-columns: repeat(7, 1fr);
            gap: 10px; margin-top: 10px;
        }
        .day{
            background: var(--card); border:1px solid var(--line); border-radius:10px;
            padding:10px; min-height:140px; display:flex; flex-direction:column; gap:6px;
            box-shadow:0 1px 4px rgba(0,0,0,.06);
        }
        .date{
            font-weight:600; color:#4CAF50; white-space: nowrap;
        }
        .muted{ color: var(--muted); }

        ul{ list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:4px; }
        li{ padding:2px 4px; border-radius:6px; background:#fafafa; border:1px solid var(--line); }
        li .line{
            display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;
        }
        .tag{ font-size:12px; padding:1px 6px; border-radius:999px; border:1px solid var(--line); background:#fff; }
        .ok{ color: var(--ok); border-color:#c9f2d9; background:#f1f9f4; }
        .warn{ color: var(--warn); border-color:#fde7c3; background:#fff9ed; }
        .bad{ color: var(--bad); border-color:#ffc9c9; background:#fff1f1; }

        .totals{ font-size:12px; color:#374151; display:flex; justify-content:space-between; gap:8px; }
        .bar{
            height:6px; background:#eef2ff; border-radius:999px; overflow:hidden; border:1px solid #dfe3ff;
        }
        .bar > span{
            display:block; height:100%; background:#60a5fa; /* факт */
        }

        .legend{
            display:flex; gap:10px; justify-content:center; align-items:center; font-size:12px; color:var(--muted);
            margin-top:8px;
        }
        .chip{ padding:2px 8px; border-radius:999px; border:1px solid var(--line); }
        .chip.ok{ color:var(--ok); background:#f1f9f4; border-color:#c9f2d9;}
        .chip.warn{ color:var(--warn); background:#fff9ed; border-color:#fde7c3;}
        .chip.bad{ color:var(--bad); background:#fff1f1; border-color:#ffc9c9;}

        @media(max-width:900px){ .calendar{ grid-template-columns: repeat(3, 1fr); } }
        @media(max-width:600px){ .calendar{ grid-template-columns: repeat(2, 1fr); } }
        @media print{
            @page { size: landscape; margin: 10mm; }
            body{ background:#fff; }
            .toolbar{ display:none; }
            .day{ break-inside: avoid; box-shadow:none; }
        }
    </style>
</head>
<body>

<h1>План и факт сборки — заявка № <?= htmlspecialchars($order) ?></h1>

<div class="toolbar">
    <input type="text" id="searchInput" placeholder="Поиск фильтра...">
    <button class="btn-print" onclick="window.print()">Печать</button>
</div>

<div class="calendar" id="calendar">
    <?php
    foreach ($period as $dt):
        $d = $dt->format('Y-m-d');
        $planItems = $planByDate[$d] ?? [];
        $factMap   = $factByDate[$d] ?? []; // [$base_filter] => fact_count

        // Итоги по дню
        $sumPlan = sumPlanForDay($planItems);
        $sumFact = sumFactForDay($factMap);
        $pct = $sumPlan > 0 ? min(100, round($sumFact / $sumPlan * 100)) : ($sumFact > 0 ? 100 : 0);
        ?>
        <div class="day">
            <div class="date"><?= $dt->format('d.m.Y') ?></div>

            <?php if ($planItems || $factMap): ?>
                <div class="totals">
                    <span>Итого: план <?= (int)$sumPlan ?> / факт <?= (int)$sumFact ?></span>
                    <span class="muted"><?= $pct ?>%</span>
                </div>
                <div class="bar" title="Факт/План">
                    <span style="width: <?= $pct ?>%"></span>
                </div>

                <ul>
                    <?php
                    // ключ: базовое название фильтра — чтобы можно было показать план и факт рядом
                    $allKeys = [];
                    foreach ($planItems as $it) $allKeys[$it['base']] = true;
                    foreach (array_keys($factMap) as $bf) $allKeys[$bf] = true;
                    ksort($allKeys, SORT_NATURAL | SORT_FLAG_CASE);

                    foreach (array_keys($allKeys) as $base) {
                        // суммируем план по одной базе (вдруг в этот день несколько строк по одному фильтру)
                        $planSum = 0;
                        foreach ($planItems as $it) if ($it['base'] === $base) $planSum += (int)$it['count'];
                        $factSum = (int)($factMap[$base] ?? 0);

                        $cls = $factSum >= $planSum ? 'ok' : ($factSum > 0 ? 'warn' : 'bad');
                        if ($planSum === 0 && $factSum > 0) $cls = 'ok'; // факт без плана — не считаем ошибкой, просто отмечаем
                        ?>
                        <li data-filter="<?= htmlspecialchars(mb_strtolower($base)) ?>">
                            <div class="line">
                                <span><strong><?= htmlspecialchars($base) ?></strong></span>
                                <span class="tag <?= $cls ?>">План: <?= (int)$planSum ?> • Факт: <?= (int)$factSum ?></span>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
            <?php else: ?>
                <em class="muted">Нет задач и факта</em>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="legend">
    <span class="chip ok">факт ≥ план</span>
    <span class="chip warn">факт &lt; план (частично)</span>
    <span class="chip bad">пока нет факта</span>
</div>

<script>
    // Поиск по названию фильтра
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.day li').forEach(li => {
            const key = li.getAttribute('data-filter') || '';
            li.style.display = (!q || key.includes(q)) ? '' : 'none';
        });
    });
</script>

</body>
</html>
