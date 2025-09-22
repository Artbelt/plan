<?php
session_start();
require_once('settings.php');
require_once('tools/tools.php');

$user      = $_SESSION['user']      ?? ($_GET['user_name'] ?? '');
$workshop  = $_SESSION['workshop']  ?? ($_GET['workshop'] ?? '');
$advertisement = 'Информация';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>U2</title>

    <style>
        /* ===== Pro UI (neutral + single accent) ===== */
        :root{
            --bg:#f6f7f9;
            --panel:#ffffff;
            --ink:#1f2937;
            --muted:#6b7280;
            --border:#e5e7eb;
            --accent:#2457e6;
            --accent-ink:#ffffff;
            --radius:12px;
            --shadow:0 2px 12px rgba(2,8,20,.06);
            --shadow-soft:0 1px 8px rgba(2,8,20,.05);
        }
        html,body{height:100%}
        body{
            margin:0; background:var(--bg); color:var(--ink);
            font:14px/1.45 "Segoe UI", Roboto, Arial, sans-serif;
            -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
        }
        a{color:var(--accent); text-decoration:none}
        a:hover{text-decoration:underline}

        /* контейнер и сетка */
        .container{ max-width:1280px; margin:0 auto; padding:16px; }
        .layout{ width:100%; border-spacing:16px; border:0; background:transparent; }
        .header-row .header-cell{ padding:0; border:0; background:transparent; }
        .headerbar{ display:flex; align-items:center; gap:12px; padding:10px 4px; color:#374151; }
        .headerbar .spacer{ flex:1; }

        /* панели-колонки */
        .content-row > td{ vertical-align:top; }
        .panel{
            background:var(--panel);
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:14px;
        }
        .panel--main{ box-shadow:var(--shadow-soft); }
        .section-title{
            font-size:15px; font-weight:600; color:#111827;
            margin:0 0 10px; padding-bottom:6px; border-bottom:1px solid var(--border);
        }

        /* таблицы/карточки внутри панелей */
        .panel table{
            width:100%;
            border-collapse:collapse;
            background:#fff;
            border:1px solid var(--border);
            border-radius:10px;
            box-shadow:var(--shadow-soft);
            overflow:hidden;
        }
        .panel td,.panel th{padding:10px;border-bottom:1px solid var(--border);vertical-align:top}
        .panel tr:last-child td{border-bottom:0}

        /* вертикальные стеки */
        .stack{ display:flex; flex-direction:column; gap:8px; }
        .stack-lg{ gap:12px; }

        /* кнопки (единый стиль) */
        button, input[type="submit"]{
            appearance:none;
            border:1px solid transparent;
            cursor:pointer;
            background:var(--accent);
            color:var(--accent-ink);
            padding:7px 14px;
            border-radius:9px;
            font-weight:600;
            transition:background .2s, box-shadow .2s, transform .04s, border-color .2s;
            box-shadow: 0 3px 6px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
        }
        button:hover, input[type="submit"]:hover{ background:#1e47c5; box-shadow:0 2px 8px rgba(2,8,20,.10); transform:translateY(-1px); }
        button:active, input[type="submit"]:active{ transform:translateY(0); }
        button:disabled, input[type="submit"]:disabled{
            background:#e5e7eb; color:#9ca3af; border-color:#e5e7eb; box-shadow:none; cursor:not-allowed;
        }
        input[type="submit"][style*="background"], button[style*="background"]{
            background:var(--accent)!important; color:#fff!important;
        }

        /* поля ввода/селекты */
        input[type="text"], input[type="date"], input[type="number"], input[type="password"],
        textarea, select{
            min-width:180px; padding:7px 10px;
            border:1px solid var(--border); border-radius:9px;
            background:#fff; color:var(--ink); outline:none;
            transition:border-color .2s, box-shadow .2s;
        }
        input:focus, textarea:focus, select:focus{
            border-color:#c7d2fe; box-shadow:0 0 0 3px #e0e7ff;
        }
        textarea{min-height:92px; resize:vertical}

        /* инфо-блоки */
        .alert{
            background:#fffbe6; border:1px solid #f4e4a4; color:#634100;
            padding:10px; border-radius:9px; margin:12px 0; font-weight:600;
        }
        .muted{color:var(--muted)}

        /* чипы заявок справа */
        .saved-orders input[type="submit"]{
            display:inline-block; margin:4px 6px 0 0;
            border-radius:999px!important; padding:6px 10px!important;
            background:var(--accent)!important; color:#fff!important;
            border:none!important; box-shadow:0 1px 4px rgba(2,8,20,.06);
        }

        /* карточка поиска */
        .search-card{
            border:1px solid var(--border);
            border-radius:10px; background:#fff;
            box-shadow:var(--shadow-soft); padding:12px; margin-top:8px;
        }
        /* ===== Modal (ads) ===== */
        .modal-backdrop{
            position:fixed; inset:0; background:rgba(15,23,42,.45);
            opacity:0; pointer-events:none; transition:opacity .18s ease;
            z-index:60;
        }
        .modal{
            position:fixed; inset:0; display:flex; align-items:center; justify-content:center;
            pointer-events:none; z-index:61;
        }
        .modal__panel{
            width:min(720px, calc(100% - 24px)); background:#fff; border:1px solid var(--border);
            border-radius:16px; box-shadow:0 10px 30px rgba(2,8,20,.20); transform:translateY(10px) scale(.98);
            transition:transform .18s ease, opacity .18s ease; opacity:0;
        }
        .modal--open .modal__panel{ transform:translateY(0) scale(1); opacity:1; }
        .modal--open, .modal--open + .modal-backdrop{ pointer-events:auto; opacity:1; }

        .modal__head{ display:flex; align-items:center; justify-content:space-between;
            padding:14px 16px; border-bottom:1px solid var(--border); }
        .modal__title{ font-size:16px; font-weight:700; color:#111827; margin:0; }
        .modal__body{ padding:14px 16px; }
        .modal__foot{ display:flex; gap:8px; justify-content:flex-end; padding:14px 16px;
            border-top:1px solid var(--border); background:#fafafa; border-radius:0 0 16px 16px; }

        .modal__close{
            appearance:none; background:transparent; border:1px solid var(--border);
            color:#374151; border-radius:9px; padding:6px 10px; cursor:pointer;
        }
        .modal__close:hover{ background:#f3f4f6; }

        .modal .field{ display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
        .modal label{ font-weight:600; color:#374151; }


        /* адаптив */
        @media (max-width:1100px){
            .layout{ border-spacing:10px; }
            .content-row > td{ display:block; width:auto!important; }
        }

        /* футер */
        .footer{
            margin-top:12px; padding:10px 12px; background:#fff; border:1px solid var(--border);
            border-radius:10px; box-shadow:var(--shadow-soft); color:#374151;
        }
    </style>
</head>
<body>

<div class="container">
    <table class="layout">
        <!-- Шапка -->
        <tr class="header-row">
            <td class="header-cell" colspan="3">
                <div class="headerbar">
                    <div>Подразделение: <?php echo htmlspecialchars($workshop); ?></div>
                    <div class="spacer"></div>
                    <div>Пользователь: <?php echo htmlspecialchars($user); ?> · <a href="logout.php">выход из системы</a></div>
                </div>
                <?php if (function_exists('is_admin') && is_admin($user)) { if (function_exists('edit_access_button_draw')) { edit_access_button_draw(); } } ?>
            </td>
        </tr>

        <!-- Контент: 3 колонки (как в образце) -->
        <tr class="content-row">
            <!-- Левая панель: Операции + Приложения -->
            <td class="panel panel--left" style="width:22%;">
                <div class="section-title">Операции</div>
                <div class="stack">
                    <a href="test.php" target="_blank" rel="noopener" class="stack"><button>Выпуск продукции</button></a>
                    <form action="product_output_view.php" method="post" class="stack"><input type="submit" value="Обзор выпуска продукции"></form>
                    <form action="parts_output_view.php" method="post"><input type="submit" value="Обзор изготовленных гофропакетов"></form>
                </div>

                <div class="section-title" style="margin-top:14px;">Приложения</div>
                <div class="stack">
                    <form action="add_filter_properties_into_db.php" method="post" target="_blank" class="stack">
                        <input type="hidden" name="workshop" value="<?php echo htmlspecialchars($workshop); ?>" >
                        <input type="submit" value="Добавить / изменить фильтр">
                    </form>
                    <form action="manufactured_production_editor.php" method="post" target="_blank" class="stack">
                        <input type="hidden" name="workshop" value="<?php echo htmlspecialchars($workshop); ?>">
                        <input type="submit" value="Редактор внесенной продукции">
                    </form>
                    <form action="gofra_table.php" method="post" target="_blank" class="stack">
                        <input type="hidden" name="workshop" value="<?php echo htmlspecialchars($workshop); ?>">
                        <input type="submit" value="Журнал для гофропакетчиков">
                    </form>
                    <form action="gofra_packages_table.php" method="post" target="_blank" class="stack">
                        <input type="hidden" name="workshop" value="<?php echo htmlspecialchars($workshop); ?>">
                        <input type="submit" value="Кол-во гофропакетов из рулона">
                    </form>

                    <div style="border-top:1px solid var(--border); margin:6px 0;"></div>

                    <form action="NP_monitor.php" method="post" target="_blank" class="stack">
                        <input type="hidden" name="workshop" value="<?php echo htmlspecialchars($workshop); ?>">
                        <input type="submit" value="Мониторинг">
                    </form>
                    <form action="worker_modules/tasks_corrugation.php" method="post" target="_blank" class="stack">
                        <input type="hidden" name="workshop" value="<?php echo htmlspecialchars($workshop); ?>">
                        <input type="submit" value="Модуль оператора ГМ">
                    </form>
                    <form action="worker_modules/tasks_cut.php" method="post" target="_blank" class="stack">
                        <input type="hidden" name="workshop" value="<?php echo htmlspecialchars($workshop); ?>">
                        <input type="submit" value="Модуль оператора бумагорезки">
                    </form>
                    <form action="worker_modules/tasks_for_builders.php" method="post" target="_blank" class="stack">
                        <input type="hidden" name="workshop" value="<?php echo htmlspecialchars($workshop); ?>">
                        <input type="submit" value="План для сборщиц">
                    </form>
                    <form action="buffer_stock.php" method="post" target="_blank" class="stack">
                        <input type="hidden" name="workshop" value="<?php echo htmlspecialchars($workshop); ?>">
                        <input type="submit" value="Буфер гофропакетов">
                    </form>
                    <div class="search-card">
                        <h4 style="margin:0 0 8px;">Объявления</h4>
                        <div class="stack" >
                            <button type="button" id="openAdModal">Создать объявление</button>

                            <noscript>
                                <!-- Носкрипт-фолбэк: обычная форма, если JS выключен -->
                                <form class="stack" action="create_ad.php" method="post">
                                    <input type="text" name="title" placeholder="Название объявления" required>
                                    <textarea name="content" placeholder="Введите текст" required></textarea>
                                    <input type="date" name="expires_at" required>
                                    <button type="submit">Создать объявление</button>
                                </form>
                            </noscript>
                        </div>
                    </div>
                </div>
            </td>

            <!-- Центральная панель: Объявления + Поиск по фильтру -->
            <td class="panel panel--main">
                <div class="section-title">Объявления</div>
                <div class="stack-lg">
                    <?php if (function_exists('show_ads')) { show_ads(); } ?>




                    <div class="search-card">
                        <h4 style="margin:0 0 8px;">Поиск заявок по фильтру</h4>
                        <div class="stack">
                            <label for="filterSelect">Фильтр:</label>
                            <?php
                            if (function_exists('load_filters_into_select')) {
                                // тот же селект, что и в образце
                                load_filters_into_select('Выберите фильтр'); // <select name="analog_filter">
                            }
                            ?>
                        </div>
                        <div id="filterSearchResult" style="margin-top:10px;"></div>
                    </div>
                </div>

                <script>
                    (function(){
                        const resultBox = document.getElementById('filterSearchResult');
                        function getSelectEl(){ return document.querySelector('select[name="analog_filter"]'); }
                        async function runSearch(){
                            const sel = getSelectEl();
                            if(!sel){ resultBox.innerHTML = '<div class="alert">Не найден выпадающий список.</div>'; return; }
                            const val = sel.value.trim();
                            if(!val){ resultBox.innerHTML = '<div class="muted">Выберите фильтр…</div>'; return; }
                            resultBox.textContent = 'Загрузка…';
                            try{
                                const formData = new FormData(); formData.append('filter', val);
                                const resp = await fetch('search_filter_in_the_orders.php', { method:'POST', body:formData });
                                if(!resp.ok){ resultBox.innerHTML = `<div class="alert">Ошибка запроса: ${resp.status} ${resp.statusText}</div>`; return; }
                                resultBox.innerHTML = await resp.text();
                            }catch(e){ resultBox.innerHTML = `<div class="alert">Ошибка: ${e}</div>`; }
                        }
                        const sel = getSelectEl(); if(sel){ sel.id='filterSelect'; sel.addEventListener('change', runSearch); }
                    })();
                </script>
            </td>

            <!-- Правая панель: Заявки/архив/планирование/загрузка -->
            <td class="panel panel--right" style="width:24%;">
                <div class="section-title">Сохраненные заявки</div>
                <div class="saved-orders">
                    <?php
                    // Подгрузка заявок по текущему цеху, hide != 1
                    global $mysql_host, $mysql_user, $mysql_user_pass, $mysql_database;
                    $mysqli = @new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
                    if ($mysqli->connect_errno) {
                        echo '<div class="alert">Проблема подключения к БД</div>';
                    } else {
                        $sql = "SELECT DISTINCT order_number, workshop, hide FROM orders;";
                        if ($result = $mysqli->query($sql)) {
                            echo '<form action="show_order.php" method="post" target="_blank">';
                            if ($result->num_rows === 0) { echo "<div class='muted'>В базе нет ни одной заявки</div>"; }
                            while ($orders_data = $result->fetch_assoc()){
                                if (($workshop === $orders_data['workshop']) && ($orders_data['hide'] != 1)){
                                    $val = htmlspecialchars($orders_data['order_number']);
                                    echo "<input type='submit' name='order_number' value='{$val}'>";
                                }
                            }
                            echo '</form>';
                            $result->close();
                        } else {
                            echo '<div class="alert">Ошибка запроса заявок</div>';
                        }
                        $mysqli->close();
                    }
                    ?>
                </div>

                <div class="section-title" style="margin-top:14px;">Управление заявками</div>
                <section class="stack">
                    <form action="archived_orders.php" target="_blank"  class="stack"><input type="submit" value="Архив заявок"></form>
                    <form action="NP_cut_index.php" method="post" target="_blank"  class="stack"><input type="submit" value="Менеджер планирования"></form>
                    <form action="NP_supply_requirements.php" method="post" target="_blank"  class="stack"><input type="submit" value="Потребность в комплектации"></form>

                    <div class="search-card">
                        <form enctype="multipart/form-data" action="load_file.php" method="POST" class="stack">
                            <label class="muted">Добавить заявку коммерческого отдела:</label>
                            <input type="file" name="userfile" />
                            <input type="submit" value="Загрузить заявку" />
                        </form>
                    </div>
                </section>
            </td>
        </tr>

        <!-- Футер (как панель) -->
        <tr>
            <td colspan="3">
                <div class="footer"><?php echo $advertisement; ?></div>
            </td>
        </tr>
    </table>
</div>
<!-- Ads Modal -->
<div id="adModal" class="modal" aria-hidden="true" aria-labelledby="adModalTitle" role="dialog">
    <div class="modal__panel" role="document">
        <div class="modal__head">
            <h3 class="modal__title" id="adModalTitle">Создать объявление</h3>
            <button type="button" class="modal__close" id="adModalCloseTop" aria-label="Закрыть">Закрыть</button>
        </div>
        <form id="adForm" action="create_ad.php" method="post">
            <div class="modal__body">
                <div class="field">
                    <label for="ad_title">Название</label>
                    <input id="ad_title" name="title" type="text" placeholder="Название объявления" required>
                </div>
                <div class="field">
                    <label for="ad_content">Текст</label>
                    <textarea id="ad_content" name="content" placeholder="Введите текст" required></textarea>
                </div>
                <div class="field">
                    <label for="ad_expires">Действительно до</label>
                    <input id="ad_expires" name="expires_at" type="date" required>
                </div>
            </div>
            <div class="modal__foot">
                <button type="button" class="modal__close" id="adModalCancel">Отмена</button>
                <button type="submit">Создать объявление</button>
            </div>
        </form>
    </div>
</div>
<div id="adModalBackdrop" class="modal-backdrop"></div>
<script>
    (function(){
        const modal = document.getElementById('adModal');
        const backdrop = document.getElementById('adModalBackdrop');
        const openBtn = document.getElementById('openAdModal');
        const closeTop = document.getElementById('adModalCloseTop');
        const cancelBtn = document.getElementById('adModalCancel');
        const form = document.getElementById('adForm');
        const firstInput = document.getElementById('ad_title');

        function open(){
            modal.classList.add('modal--open');
            backdrop.classList.add('modal--open');
            modal.setAttribute('aria-hidden','false');
            // небольшая задержка, чтобы браузер вставил в поток
            setTimeout(()=> firstInput && firstInput.focus(), 30);
            document.addEventListener('keydown', onKey);
        }
        function close(){
            modal.classList.remove('modal--open');
            backdrop.classList.remove('modal--open');
            modal.setAttribute('aria-hidden','true');
            document.removeEventListener('keydown', onKey);
            openBtn && openBtn.focus();
        }
        function onKey(e){
            if (e.key === 'Escape') close();
        }
        function onBackdropClick(e){
            if (e.target === backdrop) close();
        }

        openBtn && openBtn.addEventListener('click', open);
        closeTop && closeTop.addEventListener('click', close);
        cancelBtn && cancelBtn.addEventListener('click', close);
        backdrop && backdrop.addEventListener('click', onBackdropClick);

        // опционально: дизейбл кнопки на время отправки
        form && form.addEventListener('submit', function(){
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn){
                submitBtn.disabled = true; submitBtn.textContent = 'Отправка...';
            }
        });
    })();
</script>


</body>
</html>
