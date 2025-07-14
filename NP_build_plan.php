
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
        body {     font-family: sans-serif;
            font-size: 12px; /* 👈 уменьшен шрифт */
            padding: 20px;
            background: #f0f0f0; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td {    font-size: 11px; /* 👈 уменьшен размер шрифта в таблице */
            border: 1px solid #ccc;
            padding: 3px;
            vertical-align: top; }
        .position-cell {     cursor: pointer;
            padding: 2px;
            font-size: 11px; /* 👈 уменьшен шрифт */
            border-bottom: 1px dotted #ccc; }
        .used {
            background-color: #8996d7;
            color: #333;
            border-radius: 4px;
            padding: 2px 4px;
            display: inline-block;
            margin-bottom: 2px;
            font-size: 11px;
        }
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
        .summary { font-size: 11px;
            font-weight: bold;
            padding-top: 4px; }
    </style>
</head>
<body>
<h2>Планирование сборки для заявки <?= htmlspecialchars($order) ?></h2>
<form method="get">
    Дата начала: <input type="date" name="start" value="<?= htmlspecialchars($_GET['start'] ?? date('Y-m-d')) ?>">
    Дней: <input type="number" name="days" value="<?= $days ?>" min="1" max="30">
    <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
    <button type="submit">Построить таблицу</button>
</form>

<label>Заливок в смену: <input type="number" id="fills_per_day" value="50" min="1" style="width:60px;"></label>

<h3>Доступные позиции из гофроплана</h3>
<table>
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
                    $short = preg_replace('/\[\d+]\s+\d+(\.\d+)?$/', '', $label); // убираем [48] 199 в конце
                    ?>
                    <div class="position-cell" title="<?= htmlspecialchars($tooltip) ?>" data-label="<?= htmlspecialchars($label) ?>" data-cut-date="<?= $d ?>">
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
    <table>
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
        <tr>
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

    function closeModal() {
        document.getElementById("modal").style.display = "none";
        selectedLabel = '';
        selectedCutDate = '';
    }

    function updateSummary(date) {
        const td = document.querySelector('.drop-target[data-date="' + date + '"]');
        const items = Array.from(td.querySelectorAll('div'));

        const totalFilters = items.reduce((sum, div) => {
            const count = parseFloat(div.getAttribute('data-filters') || "0");
            return sum + count;
        }, 0);

        document.getElementById("summary-" + date).innerText =
            items.length + " позиций, " + totalFilters + " фильтров";
    }


    document.querySelectorAll('.position-cell').forEach(cell => {
        cell.addEventListener('click', () => {
            if (cell.classList.contains('used')) return;
            selectedLabel = cell.dataset.label;
            selectedCutDate = cell.dataset.cutDate;

            // Отобразим модальное окно
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

        const targets = Array.from(document.querySelectorAll('.drop-target')).filter(td =>
            td.dataset.date >= startDate
        );
        let i = 0;
        while (total > 0 && i < targets.length) {
            const batch = Math.min(total, fillsPerDay);
            const div = document.createElement('div');

            const filterName = selectedLabel.split('[')[0].trim();
            div.innerText = `${filterName}`;
            div.setAttribute('data-filters', batch);  // 👈 Сохраняем кол-во фильтров

            targets[i].appendChild(div);
            updateSummary(targets[i].dataset.date);
            total -= batch;
            i++;
        }
    }



    function preparePlan() {
        const data = {};
        document.querySelectorAll('.drop-target').forEach(td => {
            const date = td.getAttribute('data-date');
            const items = Array.from(td.querySelectorAll('div')).map(d => ({
                label: d.innerText,
                count: parseFloat(d.getAttribute('data-filters') || "0")
            }));
            if (items.length > 0) data[date] = items;
        });
        document.getElementById('plan_data').value = JSON.stringify(data);
    }
</script>
</body>
</html>
