<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");
$order = $_GET['order'] ?? '';
$days = intval($_GET['days'] ?? 9);
$start = $_GET['start'] ?? date('Y-m-d');
$fills_per_day = intval($_GET['fills_per_day'] ?? 50);

$start_date = new DateTime($start);
$dates = [];
for ($i = 0; $i < $days; $i++) {
    $dates[] = $start_date->format('Y-m-d');
    $start_date->modify('+1 day');
}

// Получение позиций из гофроплана (включаем count)
$stmt = $pdo->prepare("SELECT plan_date, filter_label, count FROM corrugation_plan WHERE order_number = ?");
$stmt->execute([$order]);
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$by_date = [];
foreach ($positions as $p) {
    $tooltip = "{$p['filter_label']} | Кол-во гофропакетов: {$p['count']}";
    $by_date[$p['plan_date']][] = [
        'label'   => $p['filter_label'],
        'tooltip' => $tooltip,
        'count'   => $p['count']
    ];
}

// Загрузка существующего плана сборки
$stmt = $pdo->prepare("SELECT assign_date, place, filter_label, count FROM build_plan WHERE order_number = ? ORDER BY assign_date, place");
$stmt->execute([$order]);
$existing_plan = $stmt->fetchAll(PDO::FETCH_ASSOC);
$plan_data = [];
foreach ($existing_plan as $row) {
    if (!isset($plan_data[$row['assign_date']])) {
        $plan_data[$row['assign_date']] = [];
    }
    if (!isset($plan_data[$row['assign_date']][$row['place']])) {
        $plan_data[$row['assign_date']][$row['place']] = [];
    }
    $plan_data[$row['assign_date']][$row['place']][] = [
        'filter' => $row['filter_label'],
        'count' => $row['count']
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Планирование сборки</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; padding: 20px; background: #f0f0f0; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { font-size: 11px; border: 1px solid #ccc; padding: 3px; vertical-align: top; white-space: normal; background: #fff; }
        th { background: #fafafa; }
        .position-cell { display: block; margin-bottom: 2px; cursor: pointer; padding: 2px; font-size: 11px; border-bottom: 1px dotted #ccc; }
        .used { background-color: #ccc; color: #666; cursor: not-allowed; }
        .assigned-item {
            background: #d2f5a3;
            margin-bottom: 2px;
            padding: 2px 4px;
            cursor: pointer;
            border-radius: 4px;
            display: block;
            box-sizing: border-box;
            width: 100%;
        }
        .half-width { width: 50%; float: left; box-sizing: border-box; }
        .drop-target { min-height: 20px; min-width: 90px; position: relative; }
        .date-col { min-width: 90px; } /* удобная ширина для дат */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 20px; border-radius: 5px; width: 400px; }
        .modal h3 { margin-top: 0; }
        .modal button { margin-top: 10px; }
        .date-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; max-height: 300px; overflow-y: auto; }
        .places { margin-top: 10px; }
        .places-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 10px; }
        .places-grid button { padding: 5px; font-size: 12px; cursor: pointer; }
        .summary { font-size: 11px; font-weight: bold; padding-top: 4px; }
        .hover-highlight { background-color: #ffe780 !important; transition: background-color 0.2s; }
        .highlight-col { background-color: #ffe6b3 !important; }
        .highlight-row { background-color: #fff3cd !important; }

        /* --- Sticky колонки для нижней таблицы --- */
        .table-wrap { position: relative; overflow: auto; border: 1px solid #ddd; background: #fff; }
        #bottom-table { width: max(100%, 1200px); } /* чтобы был горизонтальный скролл при множестве дней */
        .sticky-left, .sticky-right {
            position: sticky;
            z-index: 3;
            background: #fff; /* не прозрачно над содержимым */
        }
        th.sticky-left, td.sticky-left {
            left: 0;
            z-index: 4; /* поверх обычных ячеек */
            box-shadow: 2px 0 0 rgba(0,0,0,0.06);
            min-width: 60px;
            text-align: center;
        }
        th.sticky-right, td.sticky-right {
            right: 0;
            z-index: 4;
            box-shadow: -2px 0 0 rgba(0,0,0,0.06);
            min-width: 60px;
            text-align: center;
        }
    </style>
</head>
<body>
<h2>Планирование сборки для заявки <?= htmlspecialchars($order) ?></h2>
<form method="get" style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
    Дата начала: <input type="date" name="start" value="<?= htmlspecialchars($_GET['start'] ?? date('Y-m-d')) ?>">
    Дней: <input type="number" name="days" value="<?= $days ?>" min="1" max="90">
    Заливок в смену: <input type="number" name="fills_per_day" id="fills_per_day" value="<?= $fills_per_day ?>" min="1" style="width:60px;">
    <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
    <button type="submit">Построить таблицу</button>
    <button type="button" onclick="addDay()">Добавить день</button>
    <button type="button" onclick="removeDay()">Убрать день</button>
    <button type="button" onclick="reloadPlan()" style="background:#16a34a; color:#fff; padding:5px 10px; border:1px solid #16a34a; border-radius:4px; cursor:pointer;">Загрузить план</button>
    <button type="button" onclick="savePlan()" style="background:#2563eb; color:#fff; padding:5px 10px; border:1px solid #2563eb; border-radius:4px; cursor:pointer;">Сохранить план</button>
</form>

<h3>Доступные позиции из гофроплана</h3>
<table id="top-table">
    <tr>
        <?php foreach ($dates as $d): ?>
            <th class="date-col"><?= $d ?></th>
        <?php endforeach; ?>
    </tr>
    <tr>
        <?php foreach ($dates as $d): ?>
            <td>
                <?php foreach ($by_date[$d] ?? [] as $item): ?>
                    <?php
                    $short = preg_replace('/\[\d+]\s+\d+(\.\d+)?$/', '', $item['label']);
                    $uniqueId = uniqid('pos_');
                    ?>
                    <div class="position-cell"
                         data-id="<?= $uniqueId ?>"
                         data-label="<?= htmlspecialchars($item['label']) ?>"
                         data-count="<?= $item['count'] ?>"
                         title="<?= htmlspecialchars($item['tooltip']) ?>"
                         data-cut-date="<?= $d ?>">
                        <?= htmlspecialchars($short) ?>
                    </div>
                <?php endforeach; ?>
            </td>
        <?php endforeach; ?>
    </tr>
</table>

<h3>Планирование сборки</h3>
<form method="post" action="NP/save_build_plan.php" id="save-form">
    <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
    <input type="hidden" name="plan_data" id="plan_data">
</form>

<div class="table-wrap">
    <table id="bottom-table">
        <thead>
        <tr>
            <th class="sticky-left">Место</th>
            <?php foreach ($dates as $d): ?>
                <th class="date-col"><?= $d ?></th>
            <?php endforeach; ?>
            <th class="sticky-right" id="right-sticky-header">Место</th>
        </tr>
        </thead>
        <tbody>
        <?php for ($place = 1; $place <= 17; $place++): ?>
            <tr>
                <td class="sticky-left"><?= $place ?></td>
                <?php foreach ($dates as $d): ?>
                    <td class="drop-target date-col" data-date="<?= $d ?>" data-place="<?= $place ?>"></td>
                <?php endforeach; ?>
                <td class="sticky-right"><?= $place ?></td>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>
</div>

<div class="modal" id="modal">
    <div class="modal-content">
        <h3>Выберите дату</h3>
        <div id="modal-dates" class="date-grid"></div>
        <div class="places">
            <h4>Выберите место:</h4>
            <div id="modal-places" class="places-grid"></div>
        </div>
        <button onclick="closeModal()">Отмена</button>
    </div>
</div>

<script>
    let selectedLabel = '';
    let selectedCutDate = '';
    let selectedId = '';
    let selectedDate = '';

    function closeModal() {
        document.getElementById("modal").style.display = "none";
        selectedLabel = '';
        selectedCutDate = '';
        selectedId = '';
        selectedDate = '';
        document.getElementById("modal-places").innerHTML = "";
    }

    function attachRemoveHandlers() {
        document.querySelectorAll('.assigned-item').forEach(div => {
            div.onmouseenter = () => highlightByLabel(div.dataset.label);
            div.onmouseleave = removeHoverHighlight;
            div.onclick = () => {
                const posId = div.getAttribute('data-id');
                const upperCell = document.querySelector('.position-cell.used[data-id="' + posId + '"]');
                if (upperCell) upperCell.classList.remove('used');
                document.querySelectorAll(`.assigned-item[data-id='${posId}']`).forEach(item => item.remove());
            };
        });
    }

    document.querySelectorAll('.position-cell').forEach(cell => {
        cell.onmouseenter = () => highlightByLabel(cell.dataset.label);
        cell.onmouseleave = removeHoverHighlight;
        cell.addEventListener('click', () => {
            if (cell.classList.contains('used')) return;
            selectedLabel = cell.dataset.label;
            selectedCutDate = cell.dataset.cutDate;
            selectedId = cell.dataset.id;
            const modalDates = document.getElementById("modal-dates");
            modalDates.innerHTML = "";
            // собираем список дат из заголовков нижней таблицы, исключая левый и правый sticky
            const ths = Array.from(document.querySelectorAll('#bottom-table thead th'));
            ths.forEach((th, i) => {
                if (i > 0 && i < ths.length - 1) {
                    const dateStr = th.innerText.trim();
                    if (dateStr >= selectedCutDate) {
                        const btn = document.createElement("button");
                        btn.innerText = dateStr;
                        btn.onclick = () => {
                            selectedDate = dateStr;
                            renderPlacesForDate(selectedDate);
                        };
                        modalDates.appendChild(btn);
                    }
                }
            });
            document.getElementById("modal").style.display = "flex";
        });
    });

    function highlightByLabel(label) {
        const match = label.match(/\d{4}/);
        if (!match) return;
        const digits = match[0];
        document.querySelectorAll('.position-cell, .assigned-item').forEach(el => {
            const elMatch = el.dataset.label.match(/\d{4}/);
            if (elMatch && elMatch[0] === digits) el.classList.add('hover-highlight');
        });
    }

    function removeHoverHighlight() {
        document.querySelectorAll('.hover-highlight').forEach(el => el.classList.remove('hover-highlight'));
    }

    function renderPlacesForDate(date) {
        const modalPlaces = document.getElementById("modal-places");
        modalPlaces.innerHTML = "";
        for (let i = 1; i <= 17; i++) {
            const btn = document.createElement("button");
            btn.innerText = "Место " + i;
            const td = document.querySelector(`.drop-target[data-date='${date}'][data-place='${i}']`);
            const items = td.querySelectorAll('.assigned-item');
            const isFull = items.length >= 2;
            if (isFull) {
                btn.disabled = true;
                btn.style.opacity = "0.5";
            } else {
                btn.onclick = () => {
                    distributeToBuildPlan(date, i);
                    const cell = document.querySelector('.position-cell[data-id="' + selectedId + '"]');
                    if (cell) cell.classList.add('used');
                    closeModal();
                };
            }
            modalPlaces.appendChild(btn);
        }
    }

    function distributeToBuildPlan(startDate, place) {
        let total = parseInt(document.querySelector(`.position-cell[data-id="${selectedId}"]`).dataset.count);
        const fillsPerDay = parseInt(document.getElementById("fills_per_day").value || "50");
        const dateHeaders = Array.from(document.querySelectorAll('#bottom-table thead th'));
        const dateList = dateHeaders.slice(1, dateHeaders.length - 1).map(th => th.innerText).filter(d => d >= startDate);

        let dateIndex = 0;
        while (total > 0 && dateIndex < dateList.length) {
            const td = document.querySelector(`.drop-target[data-date='${dateList[dateIndex]}'][data-place='${place}']`);
            if (td) {
                let alreadyInCell = 0;
                td.querySelectorAll('.assigned-item').forEach(item => {
                    const countMatch = item.title.match(/\((\d+)\)/);
                    if (countMatch) alreadyInCell += parseInt(countMatch[1]);
                });
                // Вычисляем свободное место: fillsPerDay минус уже занятое
                let freeSpace = fillsPerDay - alreadyInCell;
                if (freeSpace <= 0) { 
                    dateIndex++; 
                    continue; 
                }
                // Добавляем столько, сколько помещается в свободное место
                const batch = Math.min(total, freeSpace);
                const div = document.createElement('div');
                const filterName = selectedLabel.split('[')[0].trim();
                div.innerText = filterName;
                div.title = `${filterName} (${batch})`;
                div.classList.add('assigned-item');
                div.setAttribute('data-label', selectedLabel);
                if (td.querySelector('.assigned-item')) {
                    div.classList.add('half-width');
                    td.querySelector('.assigned-item').classList.add('half-width');
                }
                div.setAttribute('data-id', selectedId);
                td.appendChild(div);
                total -= batch;
            }
            dateIndex++;
        }
        attachRemoveHandlers();
    }

    function preparePlan() {
        const data = {};
        document.querySelectorAll('.drop-target').forEach(td => {
            const date = td.getAttribute('data-date');
            const place = td.getAttribute('data-place');
            const items = Array.from(td.querySelectorAll('div')).map(d => ({
                label: d.dataset.label,
                count: d.title.match(/\((\d+)\)/) ? parseInt(d.title.match(/\((\d+)\)/)[1]) : 0
            }));
            if (items.length > 0) {
                if (!data[date]) data[date] = {};
                data[date][place] = items;
            }
        });
        document.getElementById('plan_data').value = JSON.stringify(data);
    }

    function addDay() {
        const topTable = document.getElementById('top-table');
        const bottomTable = document.getElementById('bottom-table');

        // последняя дата = предпоследний th (перед правым sticky)
        const ths = bottomTable.querySelectorAll('thead th');
        const lastDateTh = ths[ths.length - 2];
        const lastDate = new Date(lastDateTh.innerText);
        lastDate.setDate(lastDate.getDate() + 1);
        const newDateStr = lastDate.toISOString().split('T')[0];

        // Верхняя таблица
        const topHeaderRow = topTable.querySelector('tr:first-child');
        const newTopTh = document.createElement('th');
        newTopTh.className = 'date-col';
        newTopTh.innerText = newDateStr;
        topHeaderRow.appendChild(newTopTh);

        const topSecondRow = topTable.querySelector('tr:nth-child(2)');
        const newTopTd = document.createElement('td');
        topSecondRow.appendChild(newTopTd);

        // Нижняя таблица: вставляем ПЕРЕД правым sticky
        const bottomHeaderRow = bottomTable.querySelector('thead tr');
        const rightStickyHeader = document.getElementById('right-sticky-header');
        const newBottomTh = document.createElement('th');
        newBottomTh.className = 'date-col';
        newBottomTh.innerText = newDateStr;
        bottomHeaderRow.insertBefore(newBottomTh, rightStickyHeader);

        const bottomRows = bottomTable.querySelectorAll('tbody tr');
        bottomRows.forEach(row => {
            const place = row.querySelector('td.sticky-left').innerText;
            const newTd = document.createElement('td');
            newTd.classList.add('drop-target', 'date-col');
            newTd.setAttribute('data-date', newDateStr);
            newTd.setAttribute('data-place', place);
            const rightSticky = row.querySelector('td.sticky-right');
            row.insertBefore(newTd, rightSticky);
        });

        addTableHoverEffect();
    }

    function removeDay() {
        const topTable = document.getElementById('top-table');
        const bottomTable = document.getElementById('bottom-table');

        // Удаляем последнюю дату ПЕРЕД правым sticky
        const ths = bottomTable.querySelectorAll('thead th');
        if (ths.length <= 3) return; // минимум: левый, один день, правый
        const lastDateTh = ths[ths.length - 2];
        const dateStr = lastDateTh.innerText;
        lastDateTh.remove();

        // Верх: убираем последний столбец
        const topHeaders = topTable.querySelectorAll('tr:first-child th');
        if (topHeaders.length > 0) topHeaders[topHeaders.length - 1].remove();
        const topRows = topTable.querySelectorAll('tr:nth-child(2) td');
        if (topRows.length > 0) topRows[topRows.length - 1].remove();

        // Низ: убрать ячейки с этой датой
        const bottomRows = bottomTable.querySelectorAll('tbody tr');
        bottomRows.forEach(row => {
            const cells = Array.from(row.querySelectorAll('td.drop-target'));
            const cellToRemove = cells.find(td => td.getAttribute('data-date') === dateStr);
            if (cellToRemove) cellToRemove.remove();
        });
    }

    function addTableHoverEffect() {
        const bottomTable = document.getElementById('bottom-table');
        const rows = bottomTable.querySelectorAll('tbody tr');
        const ths = bottomTable.querySelectorAll('thead th');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td.drop-target');
            cells.forEach((cell) => {
                cell.addEventListener('mouseenter', () => {
                    // Подсветим всю строку
                    row.querySelectorAll('td').forEach(td => td.classList.add('highlight-row'));
                    // Подсветим соответствующий столбец: находим индекс th по дате
                    const date = cell.getAttribute('data-date');
                    let colIndex = -1;
                    ths.forEach((th, idx) => { if (th.innerText.trim() === date) colIndex = idx; });
                    if (colIndex > -1) {
                        // Подсветить этот th
                        ths[colIndex].classList.add('highlight-col');
                        // И каждую ячейку в этом столбце (пробегаем строки)
                        bottomTable.querySelectorAll('tbody tr').forEach(r => {
                            const tds = r.querySelectorAll('td');
                            if (tds[colIndex]) tds[colIndex].classList.add('highlight-col');
                        });
                    }
                });
                cell.addEventListener('mouseleave', () => {
                    bottomTable.querySelectorAll('td, th').forEach(c => {
                        c.classList.remove('highlight-col');
                        c.classList.remove('highlight-row');
                    });
                });
            });
        });
    }

    addTableHoverEffect();
    window.addDay = addDay;
    window.removeDay = removeDay;

    // Загрузка существующего плана
    function loadExistingPlan() {
        const planData = <?= json_encode($plan_data) ?>;
        const corrPlanData = <?= json_encode($by_date) ?>;
        
        // Создаем маппинг filter -> full label из corrugation_plan
        const filterToLabel = {};
        Object.values(corrPlanData).forEach(dateItems => {
            dateItems.forEach(item => {
                const filterName = item.label.split('[')[0].trim();
                filterToLabel[filterName] = item.label;
            });
        });
        
        // Проходим по всему плану и размещаем элементы
        Object.keys(planData).forEach(date => {
            Object.keys(planData[date]).forEach(place => {
                const td = document.querySelector(`.drop-target[data-date='${date}'][data-place='${place}']`);
                if (!td) return;
                
                planData[date][place].forEach(item => {
                    const filterName = item.filter;
                    const count = item.count;
                    const fullLabel = filterToLabel[filterName] || filterName;
                    
                    const div = document.createElement('div');
                    div.innerText = filterName;
                    div.title = `${filterName} (${count})`;
                    div.classList.add('assigned-item');
                    div.setAttribute('data-label', fullLabel);
                    div.setAttribute('data-id', 'loaded_' + Math.random().toString(36).substr(2, 9));
                    
                    if (td.querySelector('.assigned-item')) {
                        div.classList.add('half-width');
                        td.querySelector('.assigned-item').classList.add('half-width');
                    }
                    
                    td.appendChild(div);
                });
            });
        });
        
        // Помечаем использованные позиции в верхней таблице
        document.querySelectorAll('.assigned-item').forEach(assignedItem => {
            const label = assignedItem.dataset.label;
            const posCell = Array.from(document.querySelectorAll('.position-cell')).find(cell => 
                cell.dataset.label === label && !cell.classList.contains('used')
            );
            if (posCell) {
                posCell.classList.add('used');
            }
        });
        
        attachRemoveHandlers();
    }
    
    // Загружаем план при загрузке страницы
    if (Object.keys(<?= json_encode($plan_data) ?>).length > 0) {
        loadExistingPlan();
    }
    
    // Функция для перезагрузки плана (очистить и загрузить заново)
    function reloadPlan() {
        // Очищаем все назначенные элементы
        document.querySelectorAll('.assigned-item').forEach(item => item.remove());
        
        // Убираем пометки "used" с верхней таблицы
        document.querySelectorAll('.position-cell.used').forEach(cell => cell.classList.remove('used'));
        
        // Загружаем план заново
        if (Object.keys(<?= json_encode($plan_data) ?>).length > 0) {
            loadExistingPlan();
            alert('План загружен из базы данных');
        } else {
            alert('Сохраненный план не найден');
        }
    }
    
    window.reloadPlan = reloadPlan;
    
    // Функция для сохранения плана
    function savePlan() {
        preparePlan();
        document.getElementById('save-form').submit();
    }
    
    window.savePlan = savePlan;
</script>
</body>
</html>
