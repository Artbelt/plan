<?php
/* cut_roll_plan.php — планирование раскроя (страница + API)
   - Левый столбец фиксирован (sticky)
   - Верхняя строка дат и нижняя строка «Загрузка (ч)» фиксированы по вертикали
   - Нижний горизонтальный бегунок синхронизирован с таблицей
   - Ширина колонок дат регулируется CSS-переменной --dayW
   - API: ?action=load_assignments / ?action=save_assignments (таблица roll_plan)
*/

$dsn  = "mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4";
$user = "root";
$pass = "";

$action = $_GET['action'] ?? '';

/* ============================ API ===================================== */
if (in_array($action, ['load_assignments','save_assignments'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Таблица roll_plan (как в схеме)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS roll_plan (
              id INT(11) NOT NULL AUTO_INCREMENT,
              order_number VARCHAR(50) DEFAULT NULL,
              bale_id VARCHAR(50) DEFAULT NULL,
              plan_date DATE DEFAULT NULL,
              done TINYINT(1) DEFAULT 0 COMMENT 'Выполнено: 0 или 1',
              PRIMARY KEY (id),
              UNIQUE KEY order_number (order_number, bale_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if ($action === 'load_assignments') {
            $order = $_GET['order'] ?? '';
            if ($order === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no order']); exit; }

            $st = $pdo->prepare("SELECT plan_date, bale_id
                                 FROM roll_plan
                                 WHERE order_number=?
                                 ORDER BY plan_date, bale_id");
            $st->execute([$order]);
            $plan = [];
            foreach ($st as $r) {
                $d = $r['plan_date'];
                $b = (string)$r['bale_id'];
                if ($d === null) continue; // без даты не включаем
                $plan[$d][] = $b;
            }
            echo json_encode(['ok'=>true,'plan'=>$plan]); exit;
        }

        if ($action === 'save_assignments') {
            $raw = file_get_contents('php://input');
            $payload = $raw ? json_decode($raw, true) : [];
            $order = (string)($payload['order'] ?? '');
            $plan  = $payload['plan'] ?? [];

            if ($order === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no order']); exit; }
            if (!is_array($plan)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad plan']); exit; }

            $pdo->beginTransaction();
            // простой способ: очистить все строки этого заказа и записать заново
            $pdo->prepare("DELETE FROM roll_plan WHERE order_number=?")->execute([$order]);
            $ins = $pdo->prepare("INSERT INTO roll_plan(order_number, plan_date, bale_id) VALUES(?,?,?)");

            foreach ($plan as $date => $bales) {
                $dd = DateTime::createFromFormat('Y-m-d', $date);
                if (!$dd || !is_array($bales)) continue;
                foreach ($bales as $bid) {
                    $b = trim((string)$bid); if ($b==='') continue;
                    $ins->execute([$order, $dd->format('Y-m-d'), $b]);
                }
            }
            $pdo->commit();
            echo json_encode(['ok'=>true]); exit;
        }

        echo json_encode(['ok'=>false,'error'=>'unknown action']); exit;

    } catch(Throwable $e) {
        if (!empty($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
        exit;
    }
}

/* ============================ PAGE ==================================== */

try{
    $pdo = new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
}catch(Throwable $e){
    http_response_code(500);
    exit('DB error: '.$e->getMessage());
}

$order = $_GET['order'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM cut_plans WHERE order_number = ?");
$stmt->execute([$order]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bales = [];
foreach ($rows as $r) {
    $bales[$r['bale_id']][] = $r;   // одна строка = один «рулон» в бухте
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Планирование раскроя: <?= htmlspecialchars($order) ?></title>
    <style>
        :root{ --dayW: 88px; } /* ширина колонок дат */

        *{ box-sizing: border-box; }
        body{ font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,Helvetica,sans-serif; padding:20px; background:#f7f9fc; color:#333; }
        .container{ max-width:1200px; margin:0 auto; }

        h2{ color:#2c3e50; font-size:22px; margin:0 0 4px; }
        p{ margin:0 0 16px; font-size:13px; color:#666; }

        form{
            background:#fff; padding:12px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.06);
            display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-top:10px;
        }
        label{ font-size:14px; color:#444; }
        input[type="date"], input[type="number"]{
            padding:6px 10px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; background:#fff; outline:none;
        }
        .btn{
            background:#1a73e8; color:#fff; border:1px solid #1a73e8; border-radius:10px; padding:8px 14px;
            font-size:14px; cursor:pointer; transition:.15s ease; font-weight:600;
        }
        .btn:hover{ background:#1557b0; border-color:#1557b0; }

        #planArea{
            position:relative; overflow-x:auto; overflow-y:auto; margin-top:14px;
            border:1px solid #e5e7eb; border-radius:10px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.05); max-height:70vh; padding:0;
        }

        table{ border-collapse:separate; border-spacing:0; width:max-content; background:#fff; }
        th,td{ border:1px solid #e5e7eb; padding:6px 8px; font-size:12px; text-align:center; white-space:nowrap; height:24px; background:#fff; }

        /* липкая шапка */
        thead th{ position:sticky; top:0; z-index:6; background:#f1f5f9; }
        thead th:first-child{
            left:0; z-index:8; text-align:left; background:#e5ecf7;
            min-width:160px; max-width:360px; white-space:normal;
        }

        /* липкий левый столбец */
        tbody td:first-child{
            position:sticky; left:0; z-index:4; background:#fff; text-align:left;
            min-width:160px; max-width:360px; white-space:normal; box-shadow:2px 0 0 rgba(0,0,0,.06);
        }

        /* липкий низ (итоги) */
        tfoot td{ position:sticky; bottom:0; z-index:5; background:#f8fafc; font-weight:700; border-top:2px solid #e5e7eb; }
        tfoot td:first-child{
            left:0; z-index:7; text-align:left; background:#eef2ff;
            min-width:160px; max-width:360px; white-space:normal; box-shadow:2px 0 0 rgba(0,0,0,.06);
        }

        /* ширина колонок дат */
        thead th:not(:first-child), tbody td:not(:first-child), tfoot td:not(:first-child){
            width:var(--dayW); min-width:var(--dayW); max-width:var(--dayW);
        }

        .bale-label{ display:block; font-size:11px; color:#6b7280; margin-top:3px; line-height:1.2; white-space:normal; }
        .highlight{ background:#d1ecf1 !important; border-color:#0bb !important; }
        .overload{ background:#fde2e2 !important; }

        .hscroll{ margin-top:10px; height:18px; border:1px solid #e5e7eb; background:#fff; border-radius:8px; overflow-x:auto; overflow-y:hidden; box-shadow:0 1px 4px rgba(0,0,0,.04); }
        .hscroll-inner{ height:1px; }

        @media (max-width:768px){
            form{ flex-direction:column; align-items:flex-start; }
            thead th:first-child, tbody td:first-child, tfoot td:first-child{ min-width:140px; }
            .btn{ width:100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Планирование раскроя для заявки <?= htmlspecialchars($order) ?></h2>
    <p><b>Норматив:</b> 1 бухта = <b>40 минут</b> = 0.67 ч</p>

    <form onsubmit="event.preventDefault(); drawTable();">
        <label>Дата начала: <input type="date" id="startDate" required></label>
        <label>Дней: <input type="number" id="daysCount" min="1" value="10" required></label>
        <button type="submit" class="btn">Построить</button>
        <button type="button" class="btn" id="btnLoad">Загрузить сохранённый</button>
        <button type="button" class="btn" id="btnSave">Сохранить план</button>
    </form>

    <div id="planArea"></div>

    <!-- Нижний бегунок -->
    <div id="hScroll" class="hscroll" aria-label="Горизонтальная прокрутка">
        <div class="hscroll-inner"></div>
    </div>
</div>

<script>
    const ORDER  = <?= json_encode($order) ?>;
    const BALES  = <?= json_encode($bales, JSON_UNESCAPED_UNICODE) ?>;

    let selected = {}; // { "YYYY-MM-DD": ["baleId1","baleId2", ...] }

    const cssEsc = (s)=> (window.CSS && CSS.escape) ? CSS.escape(s) : String(s).replace(/"/g,'\\"');

    const daysBetween = (isoA, isoB) => {
        const a = new Date(isoA), b = new Date(isoB);
        a.setHours(12); b.setHours(12);
        return Math.round((b - a) / 86400000);
    };

    async function drawTable() {
        const startVal = document.getElementById('startDate').value;
        const days = parseInt(document.getElementById('daysCount').value);
        if (!startVal || isNaN(days)) return;

        const start = new Date(startVal);
        const container = document.getElementById('planArea');
        container.innerHTML = '';

        const table  = document.createElement('table');

        /* --- THEAD --- */
        const thead  = document.createElement('thead');
        const headTr = document.createElement('tr');
        headTr.innerHTML = '<th>Бухта</th>';
        const dates = [];
        for (let d = 0; d < days; d++) {
            const date = new Date(start);
            date.setDate(start.getDate() + d);
            const iso = date.toISOString().split('T')[0];
            dates.push(iso);
            headTr.innerHTML += `<th>${iso}</th>`;
        }
        thead.appendChild(headTr);
        table.appendChild(thead);

        /* --- TBODY --- */
        const tbody = document.createElement('tbody');

        Object.entries(BALES).forEach(([baleId, rolls])=>{
            const tr = document.createElement('tr');

            const heights = rolls.map(r => `[${r.height}]`).join(' ');
            const tooltip = rolls.map(r => `${r.filter} [${r.height}] ${r.width}`).join('\n');

            const td0 = document.createElement('td');
            td0.innerHTML = `<strong>Бухта ${baleId}</strong><div class="bale-label">${heights}</div>`;
            td0.title = tooltip;
            tr.appendChild(td0);

            dates.forEach(iso=>{
                const td = document.createElement('td');
                td.dataset.date   = iso;
                td.dataset.baleId = baleId;

                td.onclick = ()=>{
                    const sid = td.dataset.date;
                    const bid = td.dataset.baleId;

                    // снимаем выделение со всех ячеек этой бухты (в строке)
                    document.querySelectorAll(`td[data-bale-id="${cssEsc(bid)}"]`).forEach(c=>{
                        c.classList.remove('highlight');
                        const d0 = c.dataset.date;
                        if (selected[d0]) {
                            const idx = selected[d0].indexOf(bid);
                            if (idx>=0) selected[d0].splice(idx,1);
                            if (selected[d0].length===0) delete selected[d0];
                        }
                    });

                    // выделяем текущую
                    if (!selected[sid]) selected[sid] = [];
                    if (!selected[sid].includes(bid)) {
                        selected[sid].push(bid);
                        td.classList.add('highlight');
                    }
                    updateTotals();
                };

                tr.appendChild(td);
            });

            tbody.appendChild(tr);
        });

        table.appendChild(tbody);

        /* --- TFOOT (липкий низ) --- */
        const tfoot = document.createElement('tfoot');
        const totalRow = document.createElement('tr');
        totalRow.innerHTML = '<td><b>Загрузка (ч)</b></td>';
        dates.forEach(iso=>{
            const t = document.createElement('td');
            t.id = 'load-' + iso;
            totalRow.appendChild(t);
        });
        tfoot.appendChild(totalRow);
        table.appendChild(tfoot);

        container.appendChild(table);

        // Нижний бегунок
        setupBottomScrollbar(container, table);

        // Автоподгрузка сохранённого плана для текущих параметров
        try{
            const plan = await loadSavedPlan();
            applyPlan(plan);
        }catch(e){
            console.warn('План не загружен:', e);
        }
    }

    function setupBottomScrollbar(container, table) {
        const bar   = document.getElementById('hScroll');
        const inner = bar.querySelector('.hscroll-inner');

        const syncWidth = ()=> { inner.style.width = table.scrollWidth + 'px'; };
        syncWidth();

        if (window.ResizeObserver) {
            const ro = new ResizeObserver(syncWidth);
            ro.observe(table);
        } else {
            window.addEventListener('resize', syncWidth);
        }

        let lock = false;
        bar.addEventListener('scroll', ()=>{ if(lock) return; lock = true; container.scrollLeft = bar.scrollLeft; lock = false; });
        container.addEventListener('scroll', ()=>{ if(lock) return; lock = true; bar.scrollLeft = container.scrollLeft; lock = false; });
    }

    function updateTotals() {
        const minsPerBale = 40;
        const all = document.querySelectorAll('td.highlight');
        const cnt = {};
        all.forEach(td=>{
            const d = td.dataset.date;
            cnt[d] = (cnt[d]||0) + 1;
        });

        document.querySelectorAll('[id^="load-"]').forEach(td=>{
            const date = td.id.replace('load-','');
            const hours = ((cnt[date]||0) * minsPerBale) / 60;
            td.textContent = (hours>0) ? hours.toFixed(2) : '';
            td.className = (hours > 7) ? 'overload' : '';
        });
    }

    async function savePlan(){
        try{
            const res = await fetch(location.pathname + '?action=save_assignments', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ order: ORDER, plan: selected })
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'save failed');
            alert('План сохранён');
        }catch(e){
            alert('Ошибка сохранения: ' + e.message);
        }
    }

    async function loadSavedPlan(){
        const res = await fetch(location.pathname + '?action=load_assignments&order=' + encodeURIComponent(ORDER));
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'load failed');
        return data.plan || {};
    }

    function applyPlan(plan){
        const chosen = new Map(); // bale_id -> date (берём первое попадание)
        Object.entries(plan).forEach(([date, list])=>{
            if (!Array.isArray(list)) return;
            list.forEach(bid=>{
                const b = String(bid);
                if (!chosen.has(b)) chosen.set(b, date);
            });
        });

        document.querySelectorAll('td.highlight').forEach(el => el.classList.remove('highlight'));
        selected = {};

        for (const [bid, date] of chosen.entries()){
            document.querySelectorAll(`td[data-bale-id="${cssEsc(bid)}"]`).forEach(c=>c.classList.remove('highlight'));
            const td = document.querySelector(`td[data-bale-id="${cssEsc(bid)}"][data-date="${cssEsc(date)}"]`);
            if (!td) continue;

            if (!selected[date]) selected[date] = [];
            if (!selected[date].includes(bid)) selected[date].push(bid);

            td.classList.add('highlight');
        }
        updateTotals();
    }

    // Кнопки в форме
    document.getElementById('btnSave').addEventListener('click', savePlan);

    // «Загрузить сохранённый»:
    // 1) тянем план из БД
    // 2) определяем min/max даты
    // 3) подставляем их в инпуты (startDate — min, days — разница + 1)
    // 4) строим таблицу и применяем план (drawTable сам снова загрузит и применит)
    document.getElementById('btnLoad').addEventListener('click', async ()=>{
        try{
            const plan = await loadSavedPlan();
            const dates = Object.keys(plan).filter(Boolean).sort();
            if (!dates.length) { alert('Сохранённый план не найден.'); return; }

            const startISO = dates[0];
            const endISO   = dates[dates.length - 1];
            const days     = daysBetween(startISO, endISO) + 1;

            document.getElementById('startDate').value  = startISO;
            document.getElementById('daysCount').value  = Math.max(1, days);

            await drawTable(); // он сам подгрузит и применит plan
        }catch(e){
            alert('Не удалось загрузить план: ' + e.message);
        }
    });

    // стартовая дата = сегодня
    (function setToday(){
        const el = document.getElementById('startDate');
        const today = new Date(); today.setHours(12);
        el.value = today.toISOString().slice(0,10);
    })();
</script>
</body>
</html>
