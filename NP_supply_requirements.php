<?php
// NP_supply_by_order.php — потребность по конкретной заявке, пивот "дни × позиции"
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
]);

// ----- AJAX: отрисовать только таблицу -----
if (isset($_GET['ajax']) && $_GET['ajax']=='1') {
    $order = $_POST['order'] ?? '';
    $ctype = $_POST['ctype'] ?? ''; // wireframe | prefilter | box | g_box
    if ($order==='' || $ctype==='') {
        http_response_code(400);
        echo "<p>Не указана заявка или тип комплектующих.</p>";
        exit;
    }

    // Единый запрос по выбранной заявке
    $sql = "
    WITH bp AS (
      SELECT
        order_number,
        TRIM(SUBSTRING_INDEX(filter_label,' [',1)) AS base_filter,
        filter_label,
        assign_date,
        `count`
      FROM build_plan
      WHERE order_number = :ord
    ),
    p AS (
      SELECT b.order_number, b.base_filter, b.filter_label, b.assign_date, b.`count`,
             pfs.wireframe, pfs.prefilter, pfs.box, pfs.g_box
      FROM bp b
      JOIN panel_filter_structure pfs ON pfs.filter = b.base_filter
    ),
    o AS (
      SELECT order_number, COALESCE(packaging_rate,1) AS packaging_rate
      FROM orders WHERE order_number = :ord
    )
    SELECT
      :ctype AS component_type,
      CASE 
        WHEN :ctype = 'wireframe' THEN p.wireframe
        WHEN :ctype = 'prefilter' THEN p.prefilter
        WHEN :ctype = 'box'       THEN p.box
        WHEN :ctype = 'g_box'     THEN p.g_box
      END AS component_name,
      p.assign_date AS need_by_date,
      p.filter_label,
      p.base_filter,
      CASE 
        WHEN :ctype = 'g_box' 
             THEN CEIL(p.`count` / NULLIF((SELECT packaging_rate FROM o LIMIT 1),0))
        ELSE p.`count`
      END AS qty
    FROM p
    WHERE
      ( :ctype <> 'wireframe' OR (p.wireframe IS NOT NULL AND p.wireframe <> '') )
      AND ( :ctype <> 'prefilter' OR (p.prefilter IS NOT NULL AND p.prefilter <> '') )
      AND ( :ctype <> 'box'       OR (p.box IS NOT NULL AND p.box <> '') )
      AND ( :ctype <> 'g_box'     OR (p.g_box IS NOT NULL AND p.g_box <> '') )
    ORDER BY need_by_date, component_name, base_filter
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ord'=>$order, ':ctype'=>$ctype]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "<p>По заявке <b>".htmlspecialchars($order)."</b> для типа «".htmlspecialchars($ctype)."» данных нет.</p>";
        exit;
    }

    // Собираем пивот: колонки — даты; строки — component_name
    $dates = [];       // упорядоченный список дат
    $items = [];       // список компонент (имена строк)
    $matrix = [];      // matrix[item][date] = qty
    foreach ($rows as $r) {
        $d = $r['need_by_date'];
        $name = $r['component_name'];
        if ($name === null || $name === '') continue;

        $dates[$d] = true;
        $items[$name] = true;

        if (!isset($matrix[$name])) $matrix[$name] = [];
        if (!isset($matrix[$name][$d])) $matrix[$name][$d] = 0;
        $matrix[$name][$d] += (float)$r['qty']; // схлопываем на случай дублей в build_plan
    }
    $dates = array_keys($dates);
    sort($dates);
    $items = array_keys($items);
    sort($items, SORT_NATURAL|SORT_FLAG_CASE);

    // Формат
    $titleMap = ['wireframe'=>'каркас','prefilter'=>'предфильтр','box'=>'индивидуальная коробка','g_box'=>'групповая коробка'];
    $title = $titleMap[$ctype] ?? $ctype;

    // Рендер таблицы
    function fmt($x){ return rtrim(rtrim(number_format((float)$x,3,'.',''), '0'), '.'); }

    echo "<h3 class=\"subtitle\">Заявка ".htmlspecialchars($order).": потребность — ".htmlspecialchars($title)."</h3>";
    echo '<div class="table-wrap"><table class="pivot">';
    echo '<thead><tr><th class="left">Позиция</th>';
    foreach ($dates as $d) {
        echo '<th class="nowrap vertical-date">' . date('d-m-y', strtotime($d)) . '</th>';
    }
    echo '<th class="nowrap">Итого</th></tr></thead><tbody>';

    foreach ($items as $name) {
        $rowTotal = 0;
        echo '<tr><td class="left">'.htmlspecialchars($name).'</td>';
        foreach ($dates as $d) {
            $v = $matrix[$name][$d] ?? 0;
            $rowTotal += $v;
            echo '<td>'.($v ? fmt($v) : '').'</td>';
        }
        echo '<td class="total">'.fmt($rowTotal).'</td></tr>';
    }

    // Итог по датам
    echo '<tr class="foot"><td class="left nowrap">Итого по дням</td>';
    $grand = 0;
    foreach ($dates as $d) {
        $col = 0;
        foreach ($items as $name) $col += $matrix[$name][$d] ?? 0;
        $grand += $col;
        echo '<td class="total">'.($col?fmt($col):'').'</td>';
    }
    echo '<td class="grand">'.fmt($grand).'</td></tr>';

    echo '</tbody></table></div>';
    exit;
}

// ----- обычная загрузка страницы -----

// Список заявок (из build_plan и/или orders)
$orders = $pdo->query("SELECT DISTINCT order_number FROM build_plan ORDER BY order_number")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Потребность по заявке</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --bg:#f6f7fb; --card:#ffffff; --text:#111827; --muted:#6b7280;
            --line:#e5e7eb; --accent:#2563eb; --accent-soft:#eaf1ff;
        }
        *{box-sizing:border-box}
        body{
            font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
            background:var(--bg); color:var(--text);
            margin:0; padding:10px; font-size:13px;      /* поменьше шрифт */
        }
        h2{margin:6px 0 12px;text-align:center}
        .panel{
            max-width:1100px;margin:0 auto 12px;background:#fff;border-radius:10px;
            padding:12px;box-shadow:0 1px 4px rgba(0,0,0,.08);display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:center
        }
        .vertical-date {
            writing-mode: vertical-rl; /* Поворот текста вертикально */
            transform: rotate(180deg); /* Чтобы шло снизу вверх или сверху вниз */
            white-space: nowrap;
            padding: 4px;
        }
        label{white-space:nowrap}
        select,button{padding:7px 10px;font-size:13px;border:1px solid var(--line);border-radius:8px;background:#fff}
        button{cursor:pointer;font-weight:600}
        .btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}
        .btn-soft{background:var(--accent-soft);color:var(--accent);border-color:#cfe0ff}
        #result{max-width:1100px;margin:0 auto}

        .subtitle{margin:6px 0 8px}

        .table-wrap{overflow-x:auto;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08);padding:10px}
        table.pivot{border-collapse:collapse;width:100%;min-width:640px;font-size:12.5px}
        table.pivot th, table.pivot td{border:1px solid #ddd;padding:5px 7px;text-align:center;vertical-align:middle}
        table.pivot thead th{background:#f0f0f0;font-weight:600}
        .left{text-align:left;white-space:normal}
        .nowrap{white-space:nowrap}         /* даты не переносятся */
        table.pivot td.total{background:#f9fafb;font-weight:bold}
        table.pivot tr.foot td{background:#eef6ff;font-weight:bold}
        table.pivot td.grand{background:#e6ffe6;font-weight:bold}
        tbody tr:nth-child(even){background:#fafafa}
        @media(max-width:700px){ select,button{width:100%} }

        /* Печать */
        @media print{
            @page { size: landscape; margin: 10mm; }
            body{background:#fff}
            .panel{display:none}
            .table-wrap{box-shadow:none;border-radius:0;padding:0}
            table.pivot{font-size:11px}
            table.pivot thead th, table.pivot td{ -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<h2>Потребность комплектующих по заявке</h2>

<div class="panel">
    <label>Заявка:</label>
    <select id="order">
        <option value="">— выберите —</option>
        <?php foreach ($orders as $o): ?>
            <option value="<?= htmlspecialchars($o) ?>"><?= htmlspecialchars($o) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Тип комплектующих:</label>
    <select id="ctype">
        <option value="">— выберите —</option>
        <option value="prefilter">Предфильтр</option>
        <option value="wireframe">Каркас</option>
        <option value="box">Коробка индивидуальная</option>
        <option value="g_box">Коробка групповая</option>
    </select>

    <button class="btn-primary" onclick="loadPivot()">Показать потребность</button>
    <button class="btn-soft" onclick="window.print()">Печать</button>
</div>

<div id="result"></div>

<script>
    function loadPivot(){
        const order = document.getElementById('order').value;
        const ctype = document.getElementById('ctype').value;
        if(!order){ alert('Выберите заявку'); return; }
        if(!ctype){ alert('Выберите тип комплектующих'); return; }

        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange=function(){
            if(this.readyState===4){
                if(this.status===200) document.getElementById('result').innerHTML = this.responseText;
                else alert('Ошибка загрузки: '+this.status);
            }
        };
        xhr.open('POST','?ajax=1',true);
        xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        xhr.send('order='+encodeURIComponent(order)+'&ctype='+encodeURIComponent(ctype));
    }
</script>
</body>
</html>
