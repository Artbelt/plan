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

// Получение позиций из раскроя с полями glueing, prefilter и form_factor
$stmt = $pdo->prepare("
    SELECT rp.plan_date, c.filter, c.height, c.width, c.length, p.paper_package, 
           pp.p_p_height, pp.p_p_pleats_count, p.glueing, p.prefilter, f.name AS form_factor
    FROM cut_plans c
    JOIN roll_plan rp ON c.bale_id = rp.bale_id AND rp.order_number = c.order_number
    JOIN panel_filter_structure p ON p.filter = c.filter
    JOIN paper_package_panel pp ON pp.p_p_name = p.paper_package
    LEFT JOIN form_factors f ON p.form_factor_id = f.id
    WHERE c.order_number = ?
");
$stmt->execute([$order]);
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$by_date = [];
foreach ($positions as $p) {
    $icons = '';
    if (!empty($p['glueing'])) $icons .= ' <span class="icon" title="Проливка">●</span>';
    if (!empty($p['prefilter'])) $icons .= ' <span class="icon" title="Предфильтр">◩</span>';
    if ($p['form_factor'] === 'трапеция') {
        $icons .= ' <span class="icon" title="Трапеция">⏃</span>';
    } elseif ($p['form_factor'] === 'трапеция с обечайкой') {
        $icons .= ' <span class="icon" title="Трапеция с обечайкой">⏃◯</span>';
    }

    $label = "{$p['filter']} [{$p['height']}] {$p['width']} $icons";
    $by_date[$p['plan_date']][] = [
        'label' => $label,
        'cut_date' => $p['plan_date'],
        'filter' => $p['filter'],
        'length' => $p['length'],
        'pleats' => $p['p_p_pleats_count'],
        'pleat_height' => $p['p_p_height']
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Планирование гофрирования</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f0f0f0; font-size: 13px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 5px; vertical-align: top; white-space: normal; }
        .position-cell {
            cursor: pointer;
            padding: 3px;
            border-bottom: 1px dotted #ccc;
            display: block;
            margin-bottom: 2px;
        }
        .used {
            background-color: #8996d7;
            color: #333;
            border-radius: 4px;
            padding: 2px 4px;
            display: inline-block;
            margin-bottom: 2px;
            font-size: 13px;
        }
        .assigned-item { background: #d2f5a3; margin-bottom: 2px; padding: 2px 4px; cursor: pointer; border-radius: 4px; }
        .drop-target { min-height: 50px; }
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.4); justify-content: center; align-items: center;
        }
        .modal-content {
            background: white; padding: 20px; border-radius: 5px; width: 400px;
        }
        .modal h3 { margin-top: 0; }
        .modal button { margin-top: 10px; }
        .summary { font-weight: bold; padding-top: 5px; }
        .icon { font-size: 14px; margin-left: 4px; }
        .legend { margin-bottom: 10px; font-size: 13px; }
    </style>
</head>
<body>
<h2>Планирование гофрирования для заявки <?= htmlspecialchars($order) ?></h2>
<form method="get" style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
    Дата начала: <input type="date" name="start" value="<?= htmlspecialchars($_GET['start'] ?? date('Y-m-d')) ?>">
    Дней: <input type="number" name="days" value="<?= $days ?>" min="1" max="90">
    <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
    <button type="submit">Построить таблицу</button>
    <button type="button" onclick="addDay()">Добавить день</button>
</form>



<h3>Доступные позиции из раскроя</h3>
<div class="legend">
    Проливка ● | Предфильтр ◩ | Трапеция ⏃ | Трапеция с обечайкой ⏃◯
</div>
<table id="top-table">
    <tr>
        <?php foreach ($dates as $d): ?>
            <th><?= $d ?></th>
        <?php endforeach; ?>
    </tr>
    <tr>
        <?php foreach ($dates as $d): ?>
            <td>
                <?php foreach ($by_date[$d] ?? [] as $item): ?>
                    <?php $uniqueId = uniqid('pos_'); ?>
                    <div class="position-cell"
                         data-id="<?= $uniqueId ?>"
                         data-filter="<?= htmlspecialchars(strip_tags($item['label'])) ?>"
                         data-cut-date="<?= $item['cut_date'] ?>"
                         data-length="<?= $item['length'] ?>"
                         data-pleats="<?= $item['pleats'] ?>"
                         data-pleat-height="<?= $item['pleat_height'] ?>">
                        <?= $item['label'] ?>
                    </div>
                <?php endforeach; ?>
            </td>
        <?php endforeach; ?>
    </tr>
</table>

<h3>Планирование гофрирования</h3>
<form method="post" action="NP/save_corrugation_plan.php">
    <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
    <table id="bottom-table">
        <tr>
            <?php foreach ($dates as $d): ?>
                <th><?= $d ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($dates as $d): ?>
                <td class="drop-target" data-date="<?= $d ?>"></td>
            <?php endforeach; ?>
        </tr>
        <tr class="summary-row">
            <?php foreach ($dates as $d): ?>
                <td class="summary" id="summary-<?= $d ?>">0 шт</td>
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
    let selectedData = {};

    function closeModal() {
        document.getElementById("modal").style.display = "none";
        selectedData = {};
    }

    function updateSummary(date) {
        const td = document.querySelector('.drop-target[data-date="' + date + '"]');
        let total = 0;
        td.querySelectorAll('div').forEach(div => {
            const qty = parseInt(div.getAttribute('data-qty') || '0');
            total += qty;
        });
        document.getElementById("summary-" + date).innerText = total + " шт";
    }

    function attachRemoveHandlers() {
        document.querySelectorAll('.assigned-item').forEach(div => {
            div.onclick = () => {
                const posId = div.getAttribute('data-id');
                const upperCell = document.querySelector('.position-cell.used[data-id="' + posId + '"]');
                if (upperCell) {
                    upperCell.classList.remove('used');
                }
                const parentDate = div.closest('.drop-target').dataset.date;
                div.remove();
                updateSummary(parentDate);
            };
        });
    }

    document.querySelectorAll('.position-cell').forEach(cell => {
        cell.addEventListener('click', () => {
            if (cell.classList.contains('used')) return;

            selectedData = {
                id: cell.dataset.id,
                label: cell.dataset.filter,
                cutDate: cell.dataset.cutDate,
                length: parseFloat(cell.dataset.length),
                pleats: parseInt(cell.dataset.pleats),
                height: parseFloat(cell.dataset.pleatHeight)
            };

            document.getElementById("modal").style.display = "flex";
            const modalDates = document.getElementById("modal-dates");
            modalDates.innerHTML = '';

            document.querySelectorAll('.drop-target').forEach(td => {
                const date = td.getAttribute('data-date');
                if (date >= selectedData.cutDate) {
                    const btn = document.createElement('button');
                    btn.textContent = date;
                    btn.onclick = () => {
                        const rollLengthMm = selectedData.length * 1000;
                        const blankLength = selectedData.pleats * selectedData.height * 2;
                        const qty = Math.floor(rollLengthMm / blankLength);

                        const div = document.createElement('div');
                        div.innerText = selectedData.label + " (" + qty + " шт)";
                        div.classList.add('assigned-item');
                        div.setAttribute("data-qty", qty);
                        div.setAttribute("data-label", selectedData.label);
                        div.setAttribute("data-id", selectedData.id);
                        td.appendChild(div);

                        cell.classList.add('used');
                        updateSummary(date);
                        attachRemoveHandlers();
                        closeModal();
                    };
                    modalDates.appendChild(btn);
                }
            });
        });
    });

    function addDay() {
        const topTable = document.getElementById('top-table');
        const bottomTable = document.getElementById('bottom-table');

        const lastDateCell = topTable.querySelector('tr th:last-child');
        const lastDate = new Date(lastDateCell.innerText);
        lastDate.setDate(lastDate.getDate() + 1);
        const newDateStr = lastDate.toISOString().slice(0, 10);

        const topHead = topTable.querySelector('tr');
        const newTopTh = document.createElement('th');
        newTopTh.innerText = newDateStr;
        topHead.appendChild(newTopTh);

        const topRow = topTable.querySelector('tr:nth-of-type(2)');
        const newTopTd = document.createElement('td');
        topRow.appendChild(newTopTd);

        const bottomHead = bottomTable.querySelector('tr');
        const newBottomTh = document.createElement('th');
        newBottomTh.innerText = newDateStr;
        bottomHead.appendChild(newBottomTh);

        const bottomRow = bottomTable.querySelector('tr:nth-of-type(2)');
        const newBottomTd = document.createElement('td');
        newBottomTd.classList.add('drop-target');
        newBottomTd.setAttribute('data-date', newDateStr);
        bottomRow.appendChild(newBottomTd);

        const summaryRow = bottomTable.querySelector('.summary-row');
        const newSummaryTd = document.createElement('td');
        newSummaryTd.classList.add('summary');
        newSummaryTd.id = "summary-" + newDateStr;
        newSummaryTd.innerText = "0 шт";
        summaryRow.appendChild(newSummaryTd);
    }

    function preparePlan() {
        const data = {};
        document.querySelectorAll('.drop-target').forEach(td => {
            const date = td.getAttribute('data-date');
            const items = Array.from(td.querySelectorAll('div')).map(d => d.innerText);
            if (items.length > 0) data[date] = items;
        });
        document.getElementById('plan_data').value = JSON.stringify(data);
    }
</script>
</body>
</html>