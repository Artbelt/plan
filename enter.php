<?php
session_start();
require_once('settings.php');
require_once('tools/tools.php');

$user = $_SESSION['user'] ?? $_GET['user_name'] ?? '';
$workshop = $_SESSION['workshop'] ?? $_GET['workshop'] ?? '';
$advertisement = 'Информация';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>U2</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
            color: #333;
        }
        header {
            background: #4a90e2;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header .title {
            font-size: 20px;
            font-weight: bold;
        }
        header a {
            color: white;
            text-decoration: none;
            margin-left: 10px;
        }
        main {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            padding: 20px;
        }

        @media (max-width: 1200px) {
            main {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        .section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section h2 {
            margin-top: 0;
            font-size: 18px;
            color: #4a90e2;
        }
        .section button, .section input[type=submit] {
            display: block;
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .section button:hover, .section input[type=submit]:hover {
            background: #388E3C;
        }
        .ads-form textarea, .ads-form input[type=text], .ads-form input[type=date] {
            width: 100%;
            padding: 8px;
            margin: 5px 0 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }
        footer {
            background: #4a90e2;
            color: white;
            text-align: center;
            padding: 10px 0;
            margin-top: 20px;
        }


    </style>
</head>
<body>

<header>
    <div class="title">Подразделение: <?= htmlspecialchars($workshop) ?></div>
    <div>
        Пользователь: <?= htmlspecialchars($user) ?>
        <a href="logout.php">Выход</a>
    </div>
</header>

<main>
    <!-- Блок операций -->
    <div class="section">
        <h2>Операции</h2>
        <a href="test.php" target="_blank">
            <button>Выпуск продукции</button>
        </a>

        <form action="product_output_view.php" method="post">
            <input type="submit" value="Обзор выпуска продукции">
        </form>
        <form action="parts_output_view.php" method="post">
            <input type="submit" value="Обзор изготовленных гофропакетов">
        </form>
    </div>

    <!-- Блок приложений -->
    <div class="section">
        <h2>Приложения</h2>
        <form action="add_filter_properties_into_db.php" method="post" target="_blank">
            <input type="hidden" name="workshop" value="<?= htmlspecialchars($workshop) ?>">
            <input type="submit" value="Добавить / изменить фильтр">
        </form>
        <form action="manufactured_production_editor.php" method="post" target="_blank">
            <input type="hidden" name="workshop" value="<?= htmlspecialchars($workshop) ?>">
            <input type="submit" value="Редактор внесенной продукции">
        </form>
        <form action="gofra_table.php" method="post" target="_blank">
            <input type="hidden" name="workshop" value="<?= htmlspecialchars($workshop) ?>">
            <input type="submit" value="Журнал для гофропакетчиков">
        </form>
        <form action="gofra_packages_table.php" method="post" target="_blank">
            <input type="hidden" name="workshop" value="<?= htmlspecialchars($workshop) ?>">
            <input type="submit" value="Количество гофропакетов из рулона">
        </form>
        <hr>
        <form action="NP_monitor.php" method="post" target="_blank">
            <input type="hidden" name="workshop" value="<?= htmlspecialchars($workshop) ?>">
            <input type="submit" value="Мониторинг">
        </form>
        <form action="worker_modules/tasks_corrugation.php" method="post" target="_blank">
            <input type="hidden" name="workshop" value="<?= htmlspecialchars($workshop) ?>">
            <input type="submit" value="Модуль оператора ГМ">
        </form>
        <form action="worker_modules/tasks_cut.php" method="post" target="_blank">
            <input type="hidden" name="workshop" value="<?= htmlspecialchars($workshop) ?>">
            <input type="submit" value="Модуль оператора бумагорезки">
        </form>
    </div>

    <!-- Блок заявок -->
    <div class="section">
        <h2>Заявки</h2>
        <?php
        global $mysql_host, $mysql_user, $mysql_user_pass, $mysql_database, $workshop;
        $mysqli = new mysqli( $mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
        $sql = "SELECT DISTINCT order_number, workshop, hide FROM orders;";
        if ($result = $mysqli->query($sql)) {
            echo '<form action="show_order.php" method="post" target="_blank">';
            while ($orders_data = $result->fetch_assoc()) {
                if (($workshop == $orders_data['workshop']) && ($orders_data['hide'] != 1)) {
                    echo "<input type='submit' class='btn-order' name='order_number' value='".$orders_data['order_number']."'>";
                }
            }
            echo '</form>';
        }
        ?>
        <form action="archived_orders.php" target="_blank">
            <input type="submit" class="btn-order" value="Архив заявок">
        </form>

        <form action="NP_cut_index.php" method="post" target="_blank">
            <input type="submit" class="btn-order" value="Менеджер планирования [НОВАЯ ВЕРСИЯ]">
        </form>
        <form action="NP_supply_requirements.php" method="post" target="_blank">
            <input type="submit" class="btn-order" value="Потребность в комплектации">
        <form enctype="multipart/form-data" action="load_file.php" method="POST">
            <input type="file" name="userfile">
            <input type="submit" class="btn-order" value="Загрузить заявку">
        </form>
    </div>


    <!-- Блок объявлений -->
    <div class="section">
        <h2>Объявления</h2>
        <?php show_ads(); ?>
        <form class="ads-form" action="create_ad.php" method="post">
            <input type="text" name="title" placeholder="Название объявления" required>
            <textarea name="content" placeholder="Введите текст" required></textarea>
            <input type="date" name="expires_at" required>
            <button type="submit">Создать объявление</button>
        </form>
    </div>
</main>


<footer>
    <?= $advertisement ?>
</footer>

</body>
</html>
