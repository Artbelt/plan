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

// Получаем номер заявки
$order_number = $_POST['order_number'] ?? '';

// Функция для рендеринга ячейки с тултипом
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

// Загружаем заявку
$result = show_order($order_number);

// Инициализация счётчиков
$filter_count_in_order = 0;
$filter_count_produced = 0;
$count = 0;
$produced_parts_summ = 0;

// Отрисовка таблицы
echo "<h3>Заявка: $order_number</h3>";
echo "<table id='order_table'>";
echo "<tr>
        <th>№п/п</th><th>Фильтр</th><th>Количество, шт</th><th>Маркировка</th><th>Упаковка инд.</th>
        <th>Этикетка инд.</th><th>Упаковка групп.</th><th>Норма упаковки</th><th>Этикетка групп.</th>
        <th>Примечание</th><th>Изготовлено, шт</th><th>Остаток, шт</th><th>Изготовленные гофропакеты, шт</th>
      </tr>";

while ($row = $result->fetch_assoc()) {
    $count++;

    $prod_info = select_produced_filters_by_order($row['filter'], $order_number);
    $date_list = $prod_info[0];
    $total_qty = $prod_info[1];

    $filter_count_in_order += (int)$row['count'];
    $filter_count_produced += $total_qty;

    $difference = (int)$row['count'] - $total_qty;
    $manufactured_parts = manufactured_part_count($row['filter'], $order_number);
    $manufactured_parts = (int)manufactured_part_count($row['filter'], $order_number);
    $produced_parts_summ += $manufactured_parts;

    echo "<tr>
        <td>$count</td>
        <td>{$row['filter']}</td>
        <td>{$row['count']}</td>
        <td>{$row['marking']}</td>
        <td>{$row['personal_packaging']}</td>
        <td>{$row['personal_label']}</td>
        <td>{$row['group_packaging']}</td>
        <td>{$row['packaging_rate']}</td>
        <td>{$row['group_label']}</td>
        <td>{$row['remark']}</td>";

    echo renderTooltipCell($date_list, $total_qty);
    echo "<td>$difference</td>";
    echo "<td>$manufactured_parts</td>";
    echo "</tr>";
}

// Итоговая строка
$summ_difference = $filter_count_in_order - $filter_count_produced;

echo "<tr>
        <td>Итого:</td>
        <td></td>
        <td>$filter_count_in_order</td>
        <td colspan='7'></td>
        <td>$filter_count_produced</td>
        <td>{$summ_difference}*</td>
        <td>{$produced_parts_summ}*</td>
      </tr>";

echo "</table>";
echo "<p>* - без учета перевыполнения</p>";

// Кнопки управления
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
