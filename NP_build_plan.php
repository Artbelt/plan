<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");
$order = $_GET['order'] ?? '';
$days = intval($_GET['days'] ?? 9);
$start = $_GET['start'] ?? date('Y-m-d');

$start_date = new DateTime($start);
$dates = [];
for ($i = 0; $i < $days; $i++) {
    $dates[] = $start_date->format('Y-m-d');
    $start_date->modify('+1 day');
}

// Получение позиций из гофроплана
$stmt = $pdo->prepare("SELECT plan_date, filter_label FROM corrugation_plan WHERE order_number = ?");
$stmt->execute([$order]);
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$by_date = [];
foreach ($positions as $p) {
    $by_date[$p['plan_date']][] = $p['filter_label'];
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
        th, td { font-size: 11px; border: 1px solid #ccc; padding: 3px; vertical-align: top; white-space: normal; }
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
        .half-width {
            width: 50%;
            float: left;
            box-sizing: border-box;
        }
        .drop-target { min-height: 20px; min-width: 80px; position: relative; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 20px; border-radius: 5px; width: 400px; }
        .modal h3 { margin-top: 0; }
        .modal button { margin-top: 10px; }
        .date-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; max-height: 300px; overflow-y: auto; }
        .places { margin-top: 10px; }
        .places-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 10px; }
        .places-grid button { padding: 5px; font-size: 12px; cursor: pointer; }
        .summary { font-size: 11px; font-weight: bold; padding-top: 4px; }
    </style>
</head>
<body>
<h2>Планирование сборки для заявки <?= htmlspecialchars($order) ?></h2>
<form method="get" style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
    Дата начала: <input type="date" name="start" value="<?= htmlspecialchars($_GET['start'] ?? date('Y-m-d')) ?>">
    Дней: <input type="number" name="days" value="<?= $days ?>" min="1" max="30">
    <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
    <button type="submit">Построить таблицу</button>
    <button type="button" onclick="addDay()">Добавить день</button>
</form>

<label>Заливок в смену: <input type="number" id="fills_per_day" value="50" min="1" style="width:60px;"></label>

<h3>Доступные позиции из гофроплана</h3>
<table id="top-table">
    <tr>
        <?php foreach ($dates as $d): ?>
            <th><?= $d ?></th>
        <?php endforeach; ?>
    </tr>
    <tr>
        <?php foreach ($dates as $d): ?>
            <td>
                <?php foreach ($by_date[$d] ?? [] as $label): ?>
                    <?php
                    $tooltip = $label;
                    $short = preg_replace('/\[\d+]\s+\d+(\.\d+)?$/', '', $label);
                    $uniqueId = uniqid('pos_');
                    ?>
                    <div class="position-cell"
                         data-id="<?= $uniqueId ?>"
                         title="<?= htmlspecialchars($tooltip) ?>"
                         data-label="<?= htmlspecialchars($label) ?>"
                         data-cut-date="<?= $d ?>">
                        <?= htmlspecialchars($short) ?>
                    </div>
                <?php endforeach; ?>
            </td>
        <?php endforeach; ?>
    </tr>
</table>

<h3>Планирование сборки</h3>
<form method="post" action="NP/save_build_plan.php">
    <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
    <table id="bottom-table">
        <thead>
        <tr>
            <th>Место</th>
            <?php foreach ($dates as $d): ?>
                <th><?= $d ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php for ($place = 1; $place <= 17; $place++): ?>
            <tr>
                <td><?= $place ?></td>
                <?php foreach ($dates as $d): ?>
                    <td class="drop-target" data-date="<?= $d ?>" data-place="<?= $place ?>"></td>
                <?php endforeach; ?>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>
    <input type="hidden" name="plan_data" id="plan_data">
    <button type="submit" onclick="preparePlan()">Сохранить план</button>
</form>

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
            div.onclick = () => {
                const posId = div.getAttribute('data-id');
                const upperCell = document.querySelector('.position-cell.used[data-id="' + posId + '"]');
                if (upperCell) upperCell.classList.remove('used');
                document.querySelectorAll(`.assigned-item[data-id='${posId}']`).forEach(item => item.remove());
            };
        });
    }

    document.querySelectorAll('.position-cell').forEach(cell => {
        cell.addEventListener('click', () => {
            if (cell.classList.contains('used')) return;
            selectedLabel = cell.dataset.label;
            selectedCutDate = cell.dataset.cutDate;
            selectedId = cell.dataset.id;

            const modalDates = document.getElementById("modal-dates");
            modalDates.innerHTML = "";
            document.querySelectorAll('#bottom-table thead th').forEach((th, i) => {
                if (i > 0 && th.innerText >= selectedCutDate) {
                    const btn = document.createElement("button");
                    btn.innerText = th.innerText;
                    btn.onclick = () => {
                        selectedDate = th.innerText;
                        renderPlacesForDate(selectedDate);
                    };
                    modalDates.appendChild(btn);
                }
            });

            document.getElementById("modal").style.display = "flex";
        });
    });

    function renderPlacesForDate(date) {
        const modalPlaces = document.getElementById("modal-places");
        modalPlaces.innerHTML = "";

        for (let i = 1; i <= 17; i++) {
            const btn = document.createElement("button");
            btn.innerText = "Место " + i;

            const td = document.querySelector(`.drop-target[data-date='${date}'][data-place='${i}']`);
            const items = td.querySelectorAll('.assigned-item');
            const isFull = items.length >= 2;  // 2 половинки максимум

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
        const match = selectedLabel.match(/\[(\d+)]\s+(\d+(\.\d+)?)/);
        if (!match) return;
        let total = parseFloat(match[2]);
        const fillsPerDay = parseInt(document.getElementById("fills_per_day").value || "50");

        const dates = Array.from(document.querySelectorAll('#bottom-table thead th'))
            .slice(1)
            .map(th => th.innerText)
            .filter(date => date >= startDate);

        let dateIndex = 0;
        while (total > 0 && dateIndex < dates.length) {
            const batch = Math.min(total, fillsPerDay);
            const td = document.querySelector(`.drop-target[data-date='${dates[dateIndex]}'][data-place='${place}']`);
            if (td) {
                const div = document.createElement('div');
                const filterName = selectedLabel.split('[')[0].trim();
                div.innerText = filterName;
                div.title = `${filterName} (${batch})`;
                div.classList.add('assigned-item');

                // Если уже есть элемент, делим на половины
                if (td.querySelector('.assigned-item')) {
                    div.classList.add('half-width');
                    td.querySelector('.assigned-item').classList.add('half-width');
                }

                div.setAttribute('data-id', selectedId);
                td.appendChild(div);
            }
            total -= batch;
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
                label: d.innerText,
                count: d.title.match(/\((\d+)\)/) ? parseInt(d.title.match(/\((\d+)\)/)[1]) : 0
            }));
            if (items.length > 0) {
                if (!data[date]) data[date] = {};
                data[date][place] = items;
            }
        });
        document.getElementById('plan_data').value = JSON.stringify(data);
    }

</script>
<script>
    function addDay() {
        const topTable = document.getElementById('top-table');
        const bottomTable = document.getElementById('bottom-table');

        // Получаем последнюю дату из нижней таблицы
        const lastDateCell = bottomTable.querySelector('thead th:last-child');
        const lastDate = new Date(lastDateCell.innerText);
        lastDate.setDate(lastDate.getDate() + 1);
        const newDateStr = lastDate.toISOString().split('T')[0];

        // === Добавляем колонку в верхнюю таблицу ===
        const topHeaderRow = topTable.querySelector('tr:first-child');
        const newTopTh = document.createElement('th');
        newTopTh.innerText = newDateStr;
        topHeaderRow.appendChild(newTopTh);

        const topSecondRow = topTable.querySelector('tr:nth-child(2)');
        const newTopTd = document.createElement('td');
        topSecondRow.appendChild(newTopTd);

        // === Добавляем колонку в нижнюю таблицу ===
        const bottomHeaderRow = bottomTable.querySelector('thead tr');
        const newBottomTh = document.createElement('th');
        newBottomTh.innerText = newDateStr;
        bottomHeaderRow.appendChild(newBottomTh);

        // Добавляем ячейку для каждого места (1–17)
        const bottomRows = bottomTable.querySelectorAll('tbody tr');
        bottomRows.forEach(row => {
            const place = row.querySelector('td:first-child').innerText;
            const newTd = document.createElement('td');
            newTd.classList.add('drop-target');
            newTd.setAttribute('data-date', newDateStr);
            newTd.setAttribute('data-place', place);
            row.appendChild(newTd);
        });
    }

    // Делаем функцию глобальной
    window.addDay = addDay;
</script>

</body>
</html>
