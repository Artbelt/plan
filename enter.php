<?php
/** Запуск сессии */
session_start();

/** подключение фалйа настроек */
require_once('settings.php') ;
require_once('tools/tools.php') ;

echo "<link rel=\"stylesheet\" href=\"sheets.css\">";
/** ---------------------------------------------------------------------------------------------------------------- */
/**                                                  Блок авторизации                                                */
/** ---------------------------------------------------------------------------------------------------------------- */

/**  Проверка ввода имени и пароля */
if ((isset($_POST['user_name']))&&(!$_SESSION)) {
    if (!$_POST['user_name']) {
        echo '<div class="alert">'
            . 'вы не ввели имя'
            . '</div><p><div class="center">'
            . '<a href="index.php">назад</a></div>';
        exit;
    }
}

if ((isset($_POST['user_pass']))&&(!$_SESSION)) {
    if (!$_POST['user_pass']) {
        echo '<div class="alert">'
            . 'вы не ввели пароль'
            . '</div><p><div class="center">'
            . '<a href="index.php">назад</a></div>';
        exit;
    }
}

if ((isset($_SESSION['user'])&&(isset($_SESSION['workshop'])))){

    $user = $_SESSION['user'];
    $workshop = $_SESSION['workshop'];
    $advertisement = '~~~~~';

}else{

    $user = $_GET['user_name'];
    $password = $_GET['user_pass'];
    $workshop = $_GET['workshop'];
    $advertisement = 'SOME INFORMATION';

    /** Подключаемся к БД */
    $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
    if ($mysqli->connect_errno) {
        /** Если не получилось подключиться */
        echo 'Возникла проблема на сайте'
            . "Номер ошибки: " . $mysqli->connect_errno . "\n"
            . "Ошибка: " . $mysqli->connect_error . "\n";
        exit;
    }
    /** Выполняем запрос SQL */
    $sql = "SELECT * FROM users WHERE user = '$user';";
    if (!$result = $mysqli->query($sql)) {
        echo "Ошибка: Наш запрос не удался и вот почему: \n"
            . "Запрос: " . $sql . "\n"
            . "Номер ошибки: " . $mysqli->errno . "\n"
            . "Ошибка: " . $mysqli->error . "\n";
        exit;
    }
    /** Разбираем результат запроса */
    if ($result->num_rows === 0) {
        // Упс! в запросе нет ни одной строки!
        echo '<div class="alert">'
            . 'Нет такого пользователя'
            . '</div><p><div class="center">'
            . '<a href="index.php">назад</a></div>';
        exit;
    }

    /** Разбор массива значений  */
    $user_data = $result->fetch_assoc();

    /** Проверка пароля */
    if ($password != $user_data['pass']) {
        echo '<div class="alert">'
            . 'Ошибка доступа'
            . '</div><p><div class="center">'
            . '<a href="index.php">назад</a></div>';
        exit;
    }

    /** Проверка соответствия уровня доступа выбранному значению участка */
    $access = false;//маркер доступа
    switch ($_GET['workshop']) {
            case 'ZU':
                if ($user_data['ZU'] > 0) $access = true;
                break;
            case 'U1':
                if ($user_data['U1'] > 0) $access = true;
                break;
            case 'U2':
                if ($user_data['U2'] > 0) $access = true;
                break;
            case 'U3':
                if ($user_data['U3'] > 0) $access = true;
                break;
            case 'U4':
                if ($user_data['U4'] > 0) $access = true;
                break;
            case 'U5':
                if ($user_data['U5'] > 0) $access = true;
                break;
            case 'U6':
                if ($user_data['U6'] > 0) $access = true;
                break;
    }

    /** Если выбран участок к которому нет доступа */
    if (!$access) {
        echo '<div class="alert">'
            . 'Доступ к данному подразделению закрыт'
            . '</div><p><div class="center">'
            . '<a href="index.php">назад</a></div>';
        exit;
    }
    /** Регистрация имени пользователя в сессии */
    $_SESSION['user'] = $user;
    $_SESSION['workshop'] = $workshop;
}
echo '<title>U2</title>';
echo '<head>';
//echo '<script> setInterval(() => window.location.reload(), 15000);</script>';//автообновление страницы каждые 15 сек
echo '</head>';

/** ---------------------------------------------------------------------------------------------------------------- */
/**                                                 конец авторизации                                                */
/** ---------------------------------------------------------------------------------------------------------------- */

/** ---------------------------------------------------------------------------------------------------------------- */
/**                                              Шапка главного окна                                                 */
/** ---------------------------------------------------------------------------------------------------------------- */
echo "<table  width=100% height=100% style='background-color: #6495ed' >"
    ."<tr height='10%' align='center' style='background-color: #dedede'><td width='20%' >Подразделение: $workshop";

if (is_admin($user)){
    edit_access_button_draw();
}

if (is_edit_access_granted()){
    echo '<div id = "alert_div_1" style="width: 220; height: 5; background-color: lightgreen;"></div><p>';
}else{
    echo '<div id = "alert_div_2" style="width: 220; height: 5; background-color: gray;"></div><p>';
}

echo "</td><td width='80%'><!--#application_name=--><br>$application_name<br></td>"
    ."<td >Пользователь: $user<br><a href='logout.php'>выход из системы</a></td></tr>"
    ."<tr height='10%' align='center' ><td colspan='3'>#attention block<br>";

/** Раздел объявлений */
echo $advertisement."</td></tr>";


/** ---------------------------------------------------------------------------------------------------------------- */
/**                                                 Раздел ОПЕРАЦИИ                                                  */
/** ---------------------------------------------------------------------------------------------------------------- */
echo "<tr align='center'><td>"
    ."<table  height='100%' width='100%' bgcolor='white' style='border-collapse: collapse'>"
    ."<tr height='80%'><td>Операции: <p>";
?>
<a href="test.php" target="_blank" rel="noopener noreferrer">
    <button style="height: 20px; width: 220px">Выпуск продукции</button>
</a>

<?php
        echo "<form action='parts_output_for_workers.php' method='post'><input type='submit' value='Внесение изготовленных гофропакетов'></form>"
        ."Обзор операций:<p>"
        ."<form action='product_output_view.php' method='post'><input type='submit' value='Обзор выпуска продукции'  style=\"height: 20px; width: 220px\"></form>"
        ."<form action='parts_output_view.php' method='post'><input type='submit' value='Обзор выпуска комплектующих'  style=\"height: 20px; width: 220px\"></form>"
    ."</td></tr>"
    ."<tr bgcolor='#6495ed'><td>"

/** ---------------------------------------------------------------------------------------------------------------- */
/**                                                 Раздел ПРИЛОЖЕНИЯ                                                */
/** ---------------------------------------------------------------------------------------------------------------- */
    ."Управление данными <p>"
    /**
        ."<form action='add_filter_into_db.php' method='post'>"
        ."<input type='hidden' name='workshop' value='$workshop'>"
        ."<input type='submit'  value='добавить фильтр в БД-----ххх'  style=\"height: 20px; width: 220px\">"
        ."</form>"
    */
        /** Добавление полной информации по фильтру  */
        . "<form action='add_panel_filter_into_db.php' method='post'>"
        ."<input type='hidden' name='workshop' value='$workshop'>"
        ."<input type='submit'  value='добавить фильтр в БД(full)'  style=\"height: 20px; width: 220px\">"
        ."</form>"

        ."<form action='add_filter_properties_into_db.php' method='post'>"
        ."<input type='hidden' name='workshop' value='$workshop'>"
        ."<input type='submit'  value='изменить параметры фильтра'  style=\"height: 20px; width: 220px\">"
        ."</form>"

    ."<form action='manufactured_production_editor.php' method='post' target='_blank'>"
    ."<input type='hidden' name='workshop' value='U2'>"
    ."<input type='submit'  value='Редактор внесенной продукции'  style=\"height: 20px; width: 220px\">"
    ."</form>";

        ?>




        <form action="create_ad.php" method="post" style="display: flex; flex-direction: column; max-width: 400px; gap: 10px;">
            <label style="display: flex; flex-direction: column; font-weight: bold;">
                <span>Название объявления</span>
                <input type="text" name="title" placeholder="Введите название" required
                       style="padding: 8px; font-size: 16px; border: 1px solid #ccc; border-radius: 5px;">
            </label>

            <label style="display: flex; flex-direction: column; font-weight: bold;">
                <span>Текст объявления</span>
                <textarea name="content" placeholder="Введите текст" required
                          style="padding: 8px; font-size: 16px; border: 1px solid #ccc; border-radius: 5px; resize: vertical; min-height: 100px;"></textarea>
            </label>

            <label style="display: flex; flex-direction: column; font-weight: bold;">
                <span>Дата окончания</span>
                <input type="date" name="expires_at" required
                       style="padding: 8px; font-size: 16px; border: 1px solid #ccc; border-radius: 5px;">
            </label>

            <button type="submit"
                    style="padding: 10px; font-size: 16px; background-color: #007bff; color: white; cursor: pointer; border: none; border-radius: 5px;"
                    onmouseover="this.style.backgroundColor='#0056b3'"
                    onmouseout="this.style.backgroundColor='#007bff'">
                Создать объявление
            </button>
        </form>


<?php
    echo "</td></tr>"
    ."</table>";

/** ---------------------------------------------------------------------------------------------------------------- */
/**                                                 Раздел ЗАДАЧИ                                                    */
/** ---------------------------------------------------------------------------------------------------------------- */
echo "</td><td>"
    ."<table height='100%' width='100%' bgcolor='white' style='border-collapse: collapse'>"
    //."<tr><td>1</td><td>2</td></tr>"
    ."<tr><td style='color: cornflowerblue'>";
show_ads();

echo "изготовленая продукция за последние 10 дней: <p>";



show_weekly_production();

echo "</td></tr><tr><td></td></tr>"
    ."</table>"
    ."</td><td>";

/** ---------------------------------------------------------------------------------------------------------------- */
/**                                                 Раздел ЗАЯВКИ                                                    */
/** ---------------------------------------------------------------------------------------------------------------- */

/** Форма загрузки файла с заявкой в БД */
echo '<table height="100%" ><tr><td bgcolor="white" style="border-collapse: collapse">Сохраненные заявки<br>';


/** Подключаемся к БД */
$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
if ($mysqli->connect_errno) {
    /** Если не получилось подключиться */
    echo 'Возникла проблема на сайте'
        . "Номер ошибки: " . $mysqli->connect_errno . "\n"
        . "Ошибка: " . $mysqli->connect_error . "\n";
    exit;
}

/** Выполняем запрос SQL для загрузки заявок*/
$sql = "SELECT DISTINCT order_number, workshop, hide FROM orders;";
if (!$result = $mysqli->query($sql)){
    echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"
        ."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n";
    exit;
}
/** Разбираем результат запроса */
if ($result->num_rows === 0) { echo "В базе нет ни одной заявки";}

/** Разбор массива значений  */
echo '<form action="show_order.php" method="post"  target="_blank">';
while ($orders_data = $result->fetch_assoc()){
    if (($workshop == $orders_data['workshop'])&( $orders_data['hide'] != 1)){
        echo "<input type='submit' name='order_number' value=".$orders_data['order_number']." style=\"height: 20px; width: 240px\">";
    }
}




echo '</form>';


?>
<form action="archived_orders.php" target="_blank">
    <input type="submit" value="Архив заявок" style="height: 20px; width: 215px">
</form>
<?php

/** Блок распланированных заявок  */
echo "Распланированные заявки";
echo "<form action='planning_manager.php' method='post'>"
    ."<input type='submit' value='Менеджер планирования' style='height: 20px; width: 220px'>"
    ."</form>";


/** Блок загрузки заявок */
echo "</td></tr><tr><td height='20%'>"
     .'<form enctype="multipart/form-data" action="load_file.php" method="POST">'
     .'<input type="hidden" name="MAX_FILE_SIZE" value="3000000" />'
     .'Добавить заявку: <input name="userfile" type="file" /><br>'
     .'<input type="submit" value="Загрузить файл"  style="height: 20px; width: 220px" />'
     .'</form>'
     .'</td></tr></table>';

/** конец формы загрузки */
echo "</td></tr></table>";
echo "</td></tr></table>";
$result->close();
$mysqli->close();


?>
