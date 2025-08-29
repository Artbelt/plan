<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");
$order = $_GET['order'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM cut_plans WHERE order_number = ?");
$stmt->execute([$order]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bales = [];
foreach ($rows as $r) {
    $bales[$r['bale_id']][] = $r;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Планирование раскроя: <?= htmlspecialchars($order) ?></title>
    <style>
        :root{
            --dayW: 72px; /* ★ ширина колонок с датой: подправляй при необходимости */
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
            background: #f7f9fc;
            color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; }



        h2 { color: #2c3e50; font-size: 24px; margin-bottom: 5px; }
        p { margin-bottom: 20px; font-size: 13px; color: #666; }

        form {
            background: #ffffff; padding: 15px; border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
        }
        label { font-size: 14px; color: #444; }
        input[type="date"], input[type="number"]{
            padding: 6px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;
        }
        .btn { background-color: #1a73e8; color: #fff; border: none; border-radius: 5px; padding: 8px 16px; font-size: 14px; cursor: pointer; transition: background .3s; }
        .btn:hover { background-color: #1557b0; }

        #planArea{
            position: relative;
            overflow-x: auto;
            margin-top: 30px;
        }

        table{
            border-collapse: separate;
            border-spacing: 0;
            width: max-content;           /* ширина = сумме колонок */
            background: #fff;
            border-radius: 8px;
            /* ВАЖНО: убрать overflow:hidden — иначе sticky не работает */
            /* overflow: hidden;  <-- удалить */
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }

        th, td{
            border: 1px solid #e0e0e0;
            padding: 4px 6px;
            font-size: 11px;
            text-align: center;
            white-space: nowrap;
            box-sizing: border-box;
            height: 20px;
        }
        th{ background:#f0f3f8; font-weight:600; }
        /* фиксируем левый столбец */
        #planArea { position: relative; }            /* чтобы sticky знал к чему привязываться */
        #planArea table { border-collapse: separate; } /* sticky стабильнее с separate */

        :root{ --dayW: 72px; }
        th:not(:first-child), td:not(:first-child){
            width: var(--dayW);
            min-width: var(--dayW);
            max-width: var(--dayW);
        }

        /* ЛИПКИЙ левый столбец */
        th:first-child{
            position: sticky;
            left: 0;
            z-index: 4;
            min-width: 120px;
            max-width: 320px;
            text-align: left;
            white-space: normal;
            background: #dce3f0;              /* фон для заголовка колонки */
        }
        td:first-child{
            position: sticky;
            left: 0;
            z-index: 3;
            min-width: 120px;
            max-width: 320px;
            text-align: left;
            white-space: normal;
            background: #fff;                  /* непрозрачный фон строк */
            box-shadow: 2px 0 0 rgba(0,0,0,.06); /* тонкая вертикальная линия справа */
        }
        /* ★ фиксируем ширину только для колонок с датами */
        th:not(:first-child), td:not(:first-child){
            width: var(--dayW);
            min-width: var(--dayW);
            max-width: var(--dayW);
        }

        td[colspan] { background: #f9f9f9; font-weight: bold; font-size: 12px; }

        .bale-label { display:block; font-size: 10px; color: #666; margin-top: 4px; line-height: 1.2; white-space: normal; }
        .highlight { background-color: #d1ecf1 !important; border: 1px solid #0bb !important; }
        .overload { background-color: #f8d7da !important; }

        @media (max-width: 768px){
            form { flex-direction: column; align-items: flex-start; }
            th:first-child, td:first-child { min-width: 100px; }
            .btn { width: 100%; }
        }

        /* нижняя полоса горизонтального скролла */
        .hscroll { margin-top: 8px; height: 18px; border: 1px solid #e0e0e0; background: #fff; border-radius: 6px; overflow-x: auto; overflow-y: hidden; }
        .hscroll-inner { height: 1px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Планирование раскроя для заявки <?= htmlspecialchars($order) ?></h2>
    <p><b>Норматив:</b> 1 бухта = <b>40 минут</b> = 0.67 ч</p>

    <form onsubmit="event.preventDefault(); drawTable();">
        <label>Дата начала: <input type="date" id="startDate" required></label>
        <label>Дней: <input type="number" id="daysCount" min="1" value="10" required></label>
        <button type="submit" class="btn">Построить таблицу</button>
    </form>

    <div id="planArea" style="margin-top: 20px;"></div>

    <!-- Нижний бегунок -->
    <div id="hScroll" class="hscroll" aria-label="Горизонтальная прокрутка">
        <div class="hscroll-inner"></div>
    </div>
</div>

<script>
    const bales = <?= json_encode($bales) ?>;
    let selected = {};

    function drawTable() {
        const start = new Date(document.getElementById('startDate').value);
        const days = parseInt(document.getElementById('daysCount').value);
        if (!start || isNaN(days)) return;

        const container = document.getElementById('planArea');
        container.innerHTML = '';

        const table = document.createElement('table');
        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        headRow.innerHTML = '<th>Бухта</th>';
        for (let d = 0; d < days; d++) {
            const date = new Date(start);
            date.setDate(start.getDate() + d);
            const dateStr = date.toISOString().split('T')[0]; // YYYY-MM-DD
            headRow.innerHTML += `<th>${dateStr}</th>`;
        }
        thead.appendChild(headRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        Object.entries(bales).forEach(([baleId, rolls]) => {
            const row = document.createElement('tr');

            const heights = rolls.map(r => `[${r.height}]`).join(' ');
            const tooltip = rolls.map(r => `${r.filter} [${r.height}] ${r.width}`).join('\n');

            const cell = document.createElement('td');
            cell.innerHTML = `<strong>Бухта ${baleId}</strong><div class="bale-label">${heights}</div>`;
            cell.title = tooltip;
            row.appendChild(cell);

            for (let d = 0; d < days; d++) {
                const date = new Date(start);
                date.setDate(start.getDate() + d);
                const td = document.createElement('td');
                td.dataset.date = date.toISOString().split('T')[0];
                td.dataset.baleId = baleId;

                td.onclick = () => {
                    const sid = td.dataset.date;
                    const bid = td.dataset.baleId;

                    const rowCells = document.querySelectorAll(`td[data-bale-id="${bid}"]`);
                    rowCells.forEach(cell => {
                        cell.classList.remove('highlight');
                        const cellDate = cell.dataset.date;
                        if (selected[cellDate] && selected[cellDate].includes(bid)) {
                            selected[cellDate].splice(selected[cellDate].indexOf(bid), 1);
                            if (selected[cellDate].length === 0) delete selected[cellDate];
                        }
                    });

                    if (!selected[sid]) selected[sid] = [];
                    if (!selected[sid].includes(bid)) {
                        selected[sid].push(bid);
                        td.classList.add('highlight');
                    }

                    updateTotals();
                };

                row.appendChild(td);
            }

            tbody.appendChild(row);
        });

        const totalRow = document.createElement('tr');
        totalRow.innerHTML = '<td><b>Загрузка (ч)</b></td>';
        for (let d = 0; d < days; d++) {
            const date = new Date(start);
            date.setDate(start.getDate() + d);
            const dateStr = date.toISOString().split('T')[0];
            const td = document.createElement('td');
            td.id = 'load-' + dateStr;
            totalRow.appendChild(td);
        }
        tbody.appendChild(totalRow);

        table.appendChild(tbody);
        container.appendChild(table);

        // ★ УБРАНО: блок, который принудительно ставил 12px всем th
        // Теперь ширина управляется CSS-переменной --dayW

        // Кнопка «Сохранить»
        const saveBtn = document.createElement('button');
        saveBtn.className = 'btn';
        saveBtn.style.marginTop = '10px';
        saveBtn.innerText = 'Сохранить план';
        saveBtn.onclick = savePlan;
        container.appendChild(saveBtn);

        // Нижний бегунок
        setupBottomScrollbar(container, table);
    }

    function setupBottomScrollbar(container, table) {
        const bar = document.getElementById('hScroll');
        if (!bar) return;
        const inner = bar.querySelector('.hscroll-inner');

        const updateWidth = () => { inner.style.width = table.scrollWidth + 'px'; };
        updateWidth();

        if (window.ResizeObserver) {
            const ro = new ResizeObserver(updateWidth);
            ro.observe(table);
        } else {
            window.addEventListener('resize', updateWidth);
        }

        let lock = false;
        bar.addEventListener('scroll', () => {
            if (lock) return; lock = true;
            container.scrollLeft = bar.scrollLeft;
            lock = false;
        });
        container.addEventListener('scroll', () => {
            if (lock) return; lock = true;
            bar.scrollLeft = container.scrollLeft;
            lock = false;
        });
    }

    function updateTotals() {
        const all = document.querySelectorAll('td.highlight');
        const counter = {};
        all.forEach(td => {
            const date = td.dataset.date;
            if (!counter[date]) counter[date] = 0;
            counter[date]++;
        });

        for (const date in counter) {
            const td = document.getElementById('load-' + date);
            const hours = counter[date] * 40 / 60;
            td.innerText = hours.toFixed(2);
            td.className = hours > 7 ? 'overload' : '';
        }

        document.querySelectorAll('[id^=load-]').forEach(td => {
            if (!td.innerText) {
                td.className = '';
                td.innerText = '';
            }
        });
    }

    function savePlan() {
        const payload = { order: <?= json_encode($order) ?>, plan: selected };

        fetch('NP/save_roll_plan.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
            .then(res => res.text())
            .then(msg => {
                if (msg.trim() === 'ok') {
                    window.location.href = 'NP_cut_index.php';
                } else {
                    alert("Ошибка сохранения: " + msg);
                }
            })
            .catch(err => alert("Ошибка: " + err));
    }
</script>
</body>
</html>
