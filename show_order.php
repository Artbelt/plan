<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Заявка</title>
    <style>
        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: max-content;
            max-width: 400px;
            background-color: #333;
            color: #fff;
            text-align: left;
            padding: 5px 10px;
            border-radius: 6px;
            position: absolute;
            z-index: 10;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            white-space: pre-line;
        }
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Индикатор загрузки */
        #loading {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            color: #333;
            font-weight: bold;
        }
        .spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            width: 80px;
            height: 80px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-text {
            font-size: 20px;
            color: #333;
        }

        /* Таблица */
        table {
            border: 1px solid black;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid black;
            padding: 5px 10px;
            text-align: center;
        }
    </style>
</head>

<body>

<div id="loading">
    <div class="spinner"></div>
    <div class="loading-text">Загрузка...</div>
</div>

<?php
require('tools/tools.php');
require('settings.php');
require('style/table.txt');

/**
 * Рендер ячейки с тултипом по датам.
 * $dateList — массив вида [дата1, кол-во1, дата2, кол-во2, ...]
 * $totalQty — итоговое число, которое показываем в самой ячейке
 */
function renderTooltipCell($dateList, $totalQty) {
    if (empty($dateList)) {
        return "<td>$totalQty</td>";
    }
    $tooltip = '';
    for ($i = 0; $i < count($dateList); $i += 2) {
        $tooltip .= $dateList[$i] . ' — ' . $dateList[$i + 1] . " шт\n";
    }
    return "<td><div class='tooltip'>$totalQty<span class='tooltiptext'>".htmlspecialchars(trim($tooltip))."</span></div></td>";
}

/**
 * Грузим FАCT гофропакетов из corrugation_plan:
 * - по заявке и фильтру
 * - суммируем fact_count
 * - для тултипа возвращаем разбивку по plan_date (по каждой строке плана, где fact_count>0)
 *
 * Возвращает [ $dateList, $totalFact ] как в renderTooltipCell
 */
function normalize_filter_label($label) {
    $pos = mb_strpos($label, ' [');
    if ($pos !== false) {
        return trim(mb_substr($label, 0, $pos));
    }
    return trim($label);
}

function get_corr_fact_for_filter(PDO $pdo, string $orderNumber, string $filterLabel): array {
    $filterLabel = normalize_filter_label($filterLabel);

    $stmt = $pdo->prepare("
        SELECT plan_date, COALESCE(fact_count,0) AS fact_count
        FROM corrugation_plan
        WHERE order_number = ?
          AND TRIM(SUBSTRING_INDEX(filter_label, ' [', 1)) = ?
          AND COALESCE(fact_count,0) > 0
        ORDER BY plan_date
    ");
    $stmt->execute([$orderNumber, $filterLabel]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dateList = [];
    $total = 0;
    foreach ($rows as $r) {
        $dateList[] = $r['plan_date'];
        $dateList[] = (int)$r['fact_count'];
        $total += (int)$r['fact_count'];
    }
    return [$dateList, $total];
}

// Получаем номер заявки
$order_number = $_POST['order_number'] ?? '';

// Подключим отдельный PDO для выборок из corrugation_plan (факт гофропакетов)
$pdo_corr = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");
$pdo_corr->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Загружаем заявку (как и раньше)
$result = show_order($order_number);

// Инициализация счётчиков
$filter_count_in_order = 0;   // всего фильтров по заявке (план)
$filter_count_produced = 0;   // Всего изготовлено готовых фильтров (факт) — из select_produced_filters_by_order
$count = 0;                   // номер п/п
$corr_fact_summ = 0;          // суммарно изготовлено гофропакетов по всей заявке (из corrugation_plan)

// Отрисовка таблицы
echo "<h3>Заявка: ".htmlspecialchars($order_number)."</h3>";
echo "<table id='order_table'>";
echo "<tr>
        <th>№п/п</th>
        <th>Фильтр</th>
        <th>Количество, шт</th>
        <th>Маркировка</th>
        <th>Упаковка инд.</th>
        <th>Этикетка инд.</th>
        <th>Упаковка групп.</th>
        <th>Норма упаковки</th>
        <th>Этикетка групп.</th>
        <th>Примечание</th>
        <th>Изготовлено, шт</th>
        <th>Остаток, шт</th>
        <th>Изготовленные гофропакеты, шт</th>
      </tr>";

while ($row = $result->fetch_assoc()) {
    $count++;

    // Готовые фильтры по заявке/фильтру (как было)
    $prod_info = select_produced_filters_by_order($row['filter'], $order_number);
    $date_list_filters = $prod_info[0]; // массив дат/кол-в
    $total_qty_filters = $prod_info[1]; // итог изготовлено фильтров

    $filter_count_in_order += (int)$row['count'];
    $filter_count_produced += $total_qty_filters;

    $difference = (int)$row['count'] - $total_qty_filters;

    // Гофропакеты: теперь из corrugation_plan.fact_count
    list($corr_date_list, $corr_total) = get_corr_fact_for_filter($pdo_corr, $order_number, $row['filter']);
    $corr_fact_summ += (int)$corr_total;

    echo "<tr>
        <td>$count</td>
        <td>".htmlspecialchars($row['filter'])."</td>
        <td>".(int)$row['count']."</td>
        <td>".htmlspecialchars($row['marking'])."</td>
        <td>".htmlspecialchars($row['personal_packaging'])."</td>
        <td>".htmlspecialchars($row['personal_label'])."</td>
        <td>".htmlspecialchars($row['group_packaging'])."</td>
        <td>".htmlspecialchars($row['packaging_rate'])."</td>
        <td>".htmlspecialchars($row['group_label'])."</td>
        <td>".htmlspecialchars($row['remark'])."</td>";

    // Колонка «Изготовлено, шт» — готовые фильтры с тултипом по датам (как было)
    echo renderTooltipCell($date_list_filters, $total_qty_filters);

    // Остаток по фильтрам
    echo "<td>".(int)$difference."</td>";

    // Новая логика «Изготовленные гофропакеты, шт» — из corrugation_plan.fact_count (+ тултип по plan_date)
    echo renderTooltipCell($corr_date_list, (int)$corr_total);

    echo "</tr>";
}

// Итоговая строка
$summ_difference = $filter_count_in_order - $filter_count_produced;

echo "<tr>
        <td>Итого:</td>
        <td></td>
        <td>".(int)$filter_count_in_order."</td>
        <td colspan='7'></td>
        <td>".(int)$filter_count_produced."</td>
        <td>".(int)$summ_difference."*</td>
        <td>".(int)$corr_fact_summ."*</td>
      </tr>";

echo "</table>";
echo "<p>* - без учета перевыполнения</p>";
?>

<br>
<form action='order_planning_U2.php' method='post'>
    <input type='hidden' name='order_number' value='<?= htmlspecialchars($order_number) ?>'>
    <input type='submit' value='Режим простого планирования'>
</form>

<form action='hiding_order.php' method='post' style="margin-top: 10px;">
    <input type='hidden' name='order_number' value='<?= htmlspecialchars($order_number) ?>'>
    <input type='submit' value='Отправить заявку в архив'>
</form>

<script>
    window.addEventListener('load', function () {
        document.getElementById('loading').style.display = 'none';
    });
</script>

</body>
</html>
