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
        body {
            font-family: sans-serif;
            font-size: 12px;
            padding: 20px;
            background: #f0f0f0;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            font-size: 11px;
            border: 1px solid #ccc;
            padding: 3px;
            vertical-align: top;
            white-space: normal !important;
            text-align: center;
        }
        .position-cell {
            display: block;
            margin-bottom: 2px;
            cursor: pointer;
            padding: 2px;
            font-size: 11px;
            border-bottom: 1px dotted #ccc;
        }
        .used {
            background-color: #8996d7;
            color: #333;
            border-radius: 4px;
            padding: 2px 4px;
            display: inline-block;
            margin-bottom: 2px;
            font-size: 11px;
        }
        .assigned-item {
            background: #d2f5a3;
            margin-bottom: 2px;
            padding: 2px 4px;
            cursor: pointer;
            border-radius: 4px;
            display: block;
        }
        .drop-target { min-height: 20px; height: 25px; }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 5px;
            width: 400px;
        }
        .modal h3 { margin-top: 0; }
        .modal button { margin-top: 10px; }
        .summary {
            font-size: 11px;
            font-weight: bold;
            padding-top: 4px;
        }
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
        <tr>
            <?php foreach ($dates as $d): ?>
                <th><?= $d ?></th>
            <?php endforeach; ?>
        </tr>
        <!-- Генерируем 17 строк для мест на сборочном столе -->
        <?php for ($i = 0; $i < 17; $i++): ?>
            <tr>
                <?php foreach ($dates as $d): ?>
                    <td class="drop-target" data-date="<?= $d ?>" data-row="<?= $i ?>"></td>
                <?php endforeach; ?>
            </tr>
        <?php endfor; ?>
        <tr class="summary-row">
            <?php foreach ($dates as $d): ?>
                <td class="summary" id="summary-<?= $d ?>">0 позиций, 0 фильтров</td>
            <?php endforeach; ?>
        </tr>
    </table>
    <input type="hidden" name="plan_data" id="plan_data">
    <button type="submit" onclick="preparePlan()">Сохранить план</button>
</form>

<div class="modal" id="modal">
    <div class="modal-content">
        <h3>Выберите дату</h3>
        <div id="modal-dates"></div>
        <button onclick="closeModal()">Отмена</button>
    </div>
</div>

<script>
    let selectedLabel = '';
    let selectedCutDate = '';
    let selectedId = '';

    function closeModal() {
        document.getElementById("modal").style.display = "none";
        selectedLabel = '';
        selectedCutDate = '';
        selectedId = '';
    }

    function updateSummary(date) {
        let totalPositions = 0;
        let totalFilters = 0;
        document.querySelectorAll(`.drop-target[data-date="${date}"]`).forEach(td => {
            const div = td.querySelector('div');
            if (div) {
                totalPositions++;
                totalFilters += parseFloat(div.getAttribute('data-filters') || "0");
            }
        });
        document.getElementById("summary-" + date).innerText =
            `${totalPositions} позиций, ${totalFilters} фильтров`;
    }

    function attachRemoveHandlers() {
        document.querySelectorAll('.assigned-item').forEach(div => {
            div.onclick = () => {
                const posId = div.getAttribute('data-id');
                const upperCell = document.querySelector('.position-cell.used[data-id="' + posId + '"]');
                if (upperCell) {
                    upperCell.classList.remove('used');
                }
                document.querySelectorAll('.assigned-item[data-id="' + posId + '"]').forEach(item => {
                    const parentDate = item.closest('.drop-target').dataset.date;
                    item.remove();
                    updateSummary(parentDate);
                });
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

            document.querySelectorAll('.drop-target').forEach(td => {
                const date = td.dataset.date;
                if (date >= selectedCutDate) {
                    const btn = document.createElement("button");
                    btn.innerText = date;
                    btn.style.display = "block";
                    btn.onclick = () => {
                        distributeToBuildPlan(date);
                        closeModal();
                        cell.classList.add('used');
                    };
                    modalDates.appendChild(btn);
                }
            });

            document.getElementById("modal").style.display = "flex";
        });
    });

    function distributeToBuildPlan(startDate) {
        const match = selectedLabel.match(/\[(\d+)]\s+(\d+(\.\d+)?)/);
        if (!match) return;
        let total = parseFloat(match[2]);
        const fillsPerDay = parseInt(document.getElementById("fills_per_day").value || "50");

        const dayColumns = Array.from(document.querySelectorAll(`.drop-target[data-date]`))
            .reduce((acc, td) => {
                const date = td.dataset.date;
                if (!acc[date]) acc[date] = [];
                acc[date].push(td);
                return acc;
            }, {});

        const availableDates = Object.keys(dayColumns).filter(date => date >= startDate);
        for (let d of availableDates) {
            let cells = dayColumns[d];
            for (let cell of cells) {
                if (!cell.innerHTML && total > 0) {
                    const batch = Math.min(total, fillsPerDay);
                    const div = document.createElement('div');
                    const filterName = selectedLabel.split('[')[0].trim();
                    div.innerText = `${filterName} (${batch})`;
                    div.setAttribute('data-filters', batch);
                    div.setAttribute('data-id', selectedId);
                    div.classList.add('assigned-item');

                    cell.appendChild(div);
                    updateSummary(d);
                    attachRemoveHandlers();
                    total -= batch;
                    if (total <= 0) return;
                }
            }
        }
    }

    function addDay() {
        const topTable = document.getElementById('top-table');
        const bottomTable = document.getElementById('bottom-table');

        const lastDateCell = topTable.querySelector('tr th:last-child');
        const lastDate = new Date(lastDateCell.innerText);
        lastDate.setDate(lastDate.getDate() + 1);
        const newDateStr = lastDate.toISOString().slice(0, 10);

        // Верхняя таблица
        const topHead = topTable.querySelector('tr');
        const newTopTh = document.createElement('th');
        newTopTh.innerText = newDateStr;
        topHead.appendChild(newTopTh);

        const topRow = topTable.querySelector('tr:nth-of-type(2)');
        const newTopTd = document.createElement('td');
        topRow.appendChild(newTopTd);

        // Нижняя таблица
        const bottomHead = bottomTable.querySelector('tr:first-child');
        const newBottomTh = document.createElement('th');
        newBottomTh.innerText = newDateStr;
        bottomHead.appendChild(newBottomTh);

        const allRows = bottomTable.querySelectorAll('tr');
        for (let i = 1; i <= 17; i++) {
            const newTd = document.createElement('td');
            newTd.classList.add('drop-target');
            newTd.setAttribute('data-date', newDateStr);
            newTd.setAttribute('data-row', i - 1);
            allRows[i].appendChild(newTd);
        }

        const summaryRow = bottomTable.querySelector('.summary-row');
        const newSummaryTd = document.createElement('td');
        newSummaryTd.classList.add('summary');
        newSummaryTd.id = "summary-" + newDateStr;
        newSummaryTd.innerText = "0 позиций, 0 фильтров";
        summaryRow.appendChild(newSummaryTd);
    }

    function preparePlan() {
        const data = {};
        document.querySelectorAll('.drop-target').forEach(td => {
            const date = td.getAttribute('data-date');
            const div = td.querySelector('div');
            if (div) {
                if (!data[date]) data[date] = [];
                data[date].push({
                    label: div.innerText,
                    count: parseFloat(div.getAttribute('data-filters') || "0")
                });
            }
        });
        document.getElementById('plan_data').value = JSON.stringify(data);
    }
</script>
</body>
</html>
