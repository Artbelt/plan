<?php
require_once('NP/cut.php');

// Подключение к базе данных
$pdo1 = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");
$pdo2 = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");


$order = $_GET['order'] ?? '';
$stmt = $pdo1->prepare("SELECT filter, count FROM orders WHERE order_number = ? AND (hide IS NULL OR hide != 1)");
$stmt->execute([$order]);
$filters = $stmt->fetchAll(PDO::FETCH_ASSOC);
$rolls_1000 = [];
$rolls_500 = [];
function getPaperInfo($pdo, $filter) {
    $stmt = $pdo->prepare("SELECT paper_package FROM panel_filter_structure WHERE filter = ?");
    $stmt->execute([$filter]);
    $paper = $stmt->fetchColumn();
    if (!$paper) return null;

    $stmt = $pdo->prepare("SELECT * FROM paper_package_panel WHERE p_p_name = ?");
    $stmt->execute([$paper]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function ceilToHalf($number) {
    return ceil($number * 2) / 2;
}
function shuffleGroupedByHeight(array $arr): array {
    $grouped = [];
    foreach ($arr as $item) {
        $grouped[$item[1]][] = $item; // группируем по высоте
    }
    $result = [];
    foreach ($grouped as $group) {
        shuffle($group);
        $result = array_merge($result, $group);
    }
    return $result;
}


// Генератор всех сочетаний элементов массива по n
function getCombinations($elements, $length) {
    if ($length == 0) return [[]];
    if (count($elements) == 0) return [];

    $result = [];
    $head = $elements[0];
    $tail = array_slice($elements, 1);

    foreach (getCombinations($tail, $length - 1) as $combination) {
        array_unshift($combination, $head);
        $result[] = $combination;
    }

    foreach (getCombinations($tail, $length) as $combination) {
        $result[] = $combination;
    }

    return $result;
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>План раскроя</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            margin: 10px;
            background: #fff;
        }

        table {
            border-collapse: collapse;
            margin: 10px auto;
            font-size: 11px;
            width: auto;
            max-width: 900px;
            min-width: 500px;
        }

        th, td {
            border: 1px solid #999;
            padding: 3px 6px;
            text-align: center;
        }

        th {
            background-color: #f0f0f0;
        }

        h2, h3 {
            text-align: center;
            margin: 20px 0 10px;
            font-size: 16px;
        }

        /* Modal styles */
        #manualModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10;
        }

        #manualModal .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            width: 95%;
            max-width: 1400px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .modal-row {
            display: flex;
            gap: 20px;
        }

        .modal-column {
            flex: 1;
            max-width: 50%;
        }

        .scroll-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ccc;
            min-height: 100px; /* Ensure minimum height for visibility */
        }
    </style>
</head>
<body>

<h2>Раскрой для заявки: <b><?= htmlspecialchars($order) ?></b></h2>

<table>
    <tr>
        <th>Фильтр</th>
        <th>Требуется, шт</th>
        <th>Бумага</th>
        <th>Ширина, мм</th>
        <th>Высота ребра, мм</th>
        <th>Рёбер</th>
        <th>Длина на фильтр, мм</th>
        <th>Итого м</th>
        <th>Рулонов (1000/500)</th>
    </tr>
    <?php
    foreach ($filters as $f) {
        $filter = $f['filter'];
        $count = (int)$f['count'];
        $paper = getPaperInfo($pdo2, $filter);
        if (!$paper) continue;

        $pleats = (int)$paper['p_p_pleats_count'];
        $height = (float)$paper['p_p_height'];
        $width = (float)$paper['p_p_width'];
        $length_per_filter = $pleats * 2 * $height;
        $total_length_m = ($length_per_filter * $count) / 1000;
        $reels = ceilToHalf($total_length_m / 1000);

        // Распределяем по рулонам
        $full = floor($reels);
        $half = ($reels - $full) >= 0.49 ? 1 : 0;

        for ($i = 0; $i < $full; $i++) {
            $rolls_1000[] = [
                'filter' => $filter,
                'paper' => $paper['p_p_name'],
                'width' => $width,
                'height' => $height,
                'length' => 1000,
                'len_per_filter' => $length_per_filter
            ];
        }

        if ($half) {
            $rolls_500[] = [
                'filter' => $filter,
                'paper' => $paper['p_p_name'],
                'width' => $width,
                'height' => $height,
                'length' => 500,
                'len_per_filter' => $length_per_filter
            ];
        }

        echo "<tr>
        <td>" . htmlspecialchars($filter) . "</td>
        <td>$count</td>
        <td>" . htmlspecialchars($paper['p_p_name']) . "</td>
        <td>$width</td>
        <td>$height</td>
        <td>$pleats</td>
        <td>$length_per_filter</td>
        <td>" . number_format($total_length_m, 2, ',', ' ') . "</td>
        <td>$full ×1000 " . ($half ? '+ 1×500' : '') . "</td>
    </tr>";
    }

    // Отдельный раскрой для 1000 м рулонов
    //list($bales_1000, $left_1000) = packRollsByGroupedHeight($rolls_1000, 1200);
    list($bales_1000, $left_1000) = cut_execute($rolls_1000, 1200, 35, 5);

    // Отдельный раскрой для 500 м рулонов
    //list($bales_500, $left_500) = packRollsByGroupedHeight($rolls_500, 1200);
    list($bales_500, $left_500) = cut_execute($rolls_500, 1200, 35,5);

    // Объединяем результаты
    $bales = array_merge($bales_1000, $bales_500);

    // Сохраняем раскроенные рулоны в базу данных -
    $bale_id_counter = 1;

    foreach ($bales as $bale) {
        foreach ($bale as $roll) {
            $stmt = $pdo1->prepare("INSERT INTO cut_plans (order_number, manual, filter, paper, width, height, length, waste, bale_id)
            VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $order,
                $roll['filter'],
                $roll['paper'],
                $roll['width'],
                $roll['height'],
                $roll['length'],
                $roll['waste'] ?? null,
                $bale_id_counter
            ]);
        }
        $bale_id_counter++;
    }

    // 🆕 Обновляем orders
    $pdo1->prepare("UPDATE orders SET cut_ready = 1 WHERE order_number = ?")->execute([$order]);

    // Оставшиеся рулоны, которые не вошли в раскрой
    $remaining_rolls = array_merge($left_1000, $left_500);

    // Проверка количества полос
    $total_initial = count($rolls_1000) + count($rolls_500);
    $total_used = 0;
    foreach ($bales as $bale) {
        $total_used += count($bale);
    }
    $total_left = count($remaining_rolls);
    $check = ($total_used + $total_left === $total_initial);
    echo "<h3>Проверка количества полос:</h3>";
    echo "<p>Всего в заявке: <b>$total_initial</b><br>";
    echo "Упаковано в бухты: <b>$total_used</b><br>";
    echo "Осталось неиспользованных: <b>$total_left</b><br>";
    echo "Сумма совпадает: <b style='color:" . ($check ? "green" : "red") . "'>" . ($check ? "ДА ✅" : "НЕТ ❌") . "</b></p>";

    ?>
</table>

<h3>Рулоны 1000 м</h3>
<table>
    <tr><th>Фильтр</th><th>Бумага</th><th>Ширина</th><th>Высота</th><th>Длина</th></tr>
    <?php foreach ($rolls_1000 as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['filter']) ?></td>
            <td><?= htmlspecialchars($r['paper']) ?></td>
            <td><?= $r['width'] ?></td>
            <td><?= $r['height'] ?></td>
            <td><?= $r['length'] ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h3>Рулоны 500 м</h3>
<table>
    <tr><th>Фильтр</th><th>Бумага</th><th>Ширина</th><th>Высота</th><th>Длина</th></tr>
    <?php foreach ($rolls_500 as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['filter']) ?></td>
            <td><?= htmlspecialchars($r['paper']) ?></td>
            <td><?= $r['width'] ?></td>
            <td><?= $r['height'] ?></td>
            <td><?= $r['length'] ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<h3>Упакованные бухты</h3>
<table>
    <tr>
        <th>Бухта №</th>
        <th>Фильтр</th>
        <th>Ширина</th>
        <th>Высота</th>
        <th>Длина</th>
        <th>Отход</th>
    </tr>
    <?php foreach ($bales as $i => $bale): ?>
        <?php foreach ($bale as $roll): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($roll['filter']) ?></td>
                <td><?= $roll['width'] ?></td>
                <td><?= $roll['height'] ?></td>
                <td><?= $roll['length'] ?></td>
                <td><?= $roll['waste'] ?? '' ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
</table>
<h3>Не вошедшие в раскрой рулоны</h3>
<?php if (count($remaining_rolls) === 0): ?>
    <p style="text-align:center; color: red;">Нет рулонов, не вошедших в раскрой</p>
<?php else: ?>
    <table>
        <tr>
            <th>Фильтр</th>
            <th>Бумага</th>
            <th>Ширина</th>
            <th>Высота</th>
            <th>Длина</th>
        </tr>
        <?php foreach ($remaining_rolls as $roll): ?>
            <tr>
                <td><?= htmlspecialchars($roll['filter']) ?></td>
                <td><?= htmlspecialchars($roll['paper']) ?></td>
                <td><?= $roll['width'] ?></td>
                <td><?= $roll['height'] ?></td>
                <td><?= $roll['length'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<!-- МОДАЛЬНОЕ ОКНО -->

<div style="text-align: center;">
    <button onclick="openManualPacking()">Упаковать остатки</button>

</div>

<div id="manualModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:10;">
    <div class="modal-content">
        <!-- Top row: Остатки and Собираемая бухта -->
        <div class="modal-row">
            <div class="modal-column">
                <h4>Не використані рулони</h4>
                <table id="leftoverTable" border="1" style="width:100%; font-size:11px;"></table>
            </div>
            <div class="modal-column">
                <h4>Собираемая бухта</h4>
                <table id="baleTable" border="1" style="width:100%; font-size:11px;"></table>
                <div style="text-align:right; margin-top:10px;">
                    <span>Суммарная ширина: <b><span id="totalWidth">0</span> мм</b></span><br>
                    <span>Остаток: <b><span id="remainingWidth">1200</span> мм</b></span>
                </div>
                <form method="POST" action="NP/manual_pack.php">
                    <input type="hidden" name="bale_data" id="baleDataInput">
                    <button type="submit" onclick="return saveManualBale()">Сохранить бухту</button>
                    <button type="button" id="closeAfterSaveBtn" onclick="closeManualPacking()" style="display:none; margin-left:10px;">Закрыть окно</button>
                </form>
            </div>
        </div>

        <!-- Bottom row: Всі фільтри and Сформированные вручную бухты -->
        <div class="modal-row">
            <div class="modal-column">
                <h4>Всі фільтри</h4>
                <div class="scroll-container">
                    <table id="catalogTable" border="1" style="width:100%; font-size:11px; border-collapse:collapse;"></table>
                </div>
            </div>
            <div class="modal-column">
                <h4>Сформированные вручную бухты</h4>
                <table id="manualBalesTable" border="1" style="width:100%; font-size:11px;">
                    <thead>
                    <tr><th>№</th><th>Фильтр</th><th>Ширина</th><th>Высота</th><th>Длина</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <button id="saveAllBalesBtn" onclick="saveAllManualBales()" disabled>Сохранить раскрои</button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" name="order_number" value="<?= htmlspecialchars($order) ?>">

<script>
    const remainingRolls = <?= json_encode($remaining_rolls) ?>;
    let allFilters = []; // загрузим через fetch ниже
    let bale = [];

    function openManualPacking() {
        fetch('NP/get_all_filters.php')
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(data => {
                console.log('Fetched filters:', data); // Для отладки
                if (Array.isArray(data) && data.length > 0) {
                    allFilters = data;
                } else {
                    allFilters = []; // Пустой массив, если данных нет
                    console.warn('No filter data received');
                }
                drawCatalogTable();
                drawInteractiveTables();
                updateTotalWidth();
            })
            .catch(error => {
                console.error('Error fetching filters:', error);
                allFilters = []; // Устанавливаем пустой массив при ошибке
                drawCatalogTable();
                drawInteractiveTables();
                updateTotalWidth();
            });
        document.getElementById('manualModal').style.display = 'block';
    }

    function drawCatalogTable() {
        const table = document.getElementById('catalogTable');
        table.innerHTML = '<tr><th>Фильтр</th><th>Ширина</th><th>Высота</th><th>Длина</th></tr>';
        if (allFilters.length > 0) {
            allFilters.forEach((r) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${r.filter || 'N/A'}</td><td>${r.width || 'N/A'}</td><td>${r.height || 'N/A'}</td><td>${r.length || 'N/A'}</td>`;
                tr.style.cursor = 'pointer';
                tr.onclick = () => {
                    const cloned = { ...r, source: 'catalog' };
                    bale.push(cloned);
                    drawInteractiveTables();
                    updateTotalWidth();
                };
                table.appendChild(tr);
            });
        } else {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="4">Нет данных</td>';
            table.appendChild(tr);
        }
    }

    function closeManualPacking() {
        document.getElementById('manualModal').style.display = 'none';
        bale = [];
        drawInteractiveTables();
    }

    function splitRoll(index) {
        const roll = remainingRolls[index];
        if (!roll || roll.length !== 1000) return;

        remainingRolls.splice(index, 1);
        const roll500a = { ...roll, length: 500 };
        const roll500b = { ...roll, length: 500 };
        remainingRolls.push(roll500a, roll500b);

        drawInteractiveTables();
        updateTotalWidth();
    }

    function drawInteractiveTables() {
        const leftTable = document.getElementById('leftoverTable');
        leftTable.innerHTML = '<tr><th>Фильтр</th><th>Ширина</th><th>Высота</th><th>Длина</th></tr>';
        remainingRolls.forEach((r, i) => {
            const tr = document.createElement('tr');
            let lengthCell = `${r.length}`;
            if (r.length === 1000) {
                lengthCell += ` <button onclick="splitRoll(${i})" title="Разделить на 2×500">✂️</button>`;
            }
            tr.innerHTML = `<td>${r.filter}</td><td>${r.width}</td><td>${r.height}</td><td>${lengthCell}</td>`;
            tr.style.cursor = 'pointer';
            tr.onclick = (e) => {
                if (e.target.tagName === 'BUTTON') return;
                bale.push(r);
                remainingRolls.splice(i, 1);
                drawInteractiveTables();
                updateTotalWidth();
            };
            leftTable.appendChild(tr);
        });

        const baleTable = document.getElementById('baleTable');
        baleTable.innerHTML = '<tr><th>Фильтр</th><th>Ширина</th><th>Высота</th><th>Длина</th></tr>';
        bale.forEach((r, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${r.filter}</td><td>${r.width}</td><td>${r.height}</td><td>${r.length}</td>`;
            if (r.source === 'catalog') {
                tr.style.backgroundColor = '#fffacc';
            }
            tr.style.cursor = 'pointer';
            tr.onclick = () => {
                if (r.source !== 'catalog') {
                    remainingRolls.push(r);
                }
                bale.splice(i, 1);
                drawInteractiveTables();
                updateTotalWidth();
            };
            baleTable.appendChild(tr);
        });
    }

    function updateTotalWidth() {
        const maxWidth = 1200;
        const total = bale.reduce((sum, r) => sum + parseFloat(r.width), 0);
        const remaining = Math.max(0, maxWidth - total);
        document.getElementById('totalWidth').innerText = total.toFixed(1);
        document.getElementById('remainingWidth').innerText = remaining.toFixed(1);
    }

    function saveManualBale() {
        if (bale.length === 0) return false;
        savedManualBales.push([...bale]);
        bale = [];
        drawInteractiveTables();
        updateTotalWidth();
        drawSavedManualBales();

        // Показываем кнопку "Закрыть окно"
        document.getElementById('closeAfterSaveBtn').style.display = 'inline-block';

        return false; // предотвращаем отправку формы
    }

    let savedManualBales = [];

    function saveManualBale() {
        if (bale.length === 0) return false;
        savedManualBales.push([...bale]);
        bale = [];
        drawInteractiveTables();
        updateTotalWidth();
        drawSavedManualBales();
        return false;
    }

    function drawSavedManualBales() {
        const tbody = document.querySelector("#manualBalesTable tbody");
        tbody.innerHTML = '';
        savedManualBales.forEach((baleGroup, index) => {
            baleGroup.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${index + 1}</td><td>${r.filter}</td><td>${r.width}</td><td>${r.height}</td><td>${r.length}</td>`;
                tbody.appendChild(tr);
            });
        });
        document.getElementById('saveAllBalesBtn').disabled = savedManualBales.length === 0;
    }

    function saveAllManualBales() {
        if (savedManualBales.length === 0 && bales.length === 0) return;

        const order = <?= json_encode($order) ?>;
        const payload = {
            order: order,
            auto_bales: <?= json_encode($bales) ?>,
            manual_bales: savedManualBales
        };

        fetch('NP/save_combined_bales.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
            .then(res => res.text())
            .then(res => {
                alert("Все бухты сохранены!");
                savedManualBales = [];
                drawSavedManualBales();
            })
            .catch(err => {
                console.error(err);
                alert("Ошибка при сохранении.");
            });
    }


</script>
</body>
</html>