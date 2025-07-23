<?php
require_once('tools/tools.php');

$filter_name = $_POST['filter_name'];
$category = $_POST['category'];

/** ГОФРОПАКЕТ */
$p_p_name = "гофропакет ".$filter_name;
$p_p_length = $_POST['p_p_length'];
$p_p_width = $_POST['p_p_width'];
$p_p_height = $_POST['p_p_height'];
$p_p_pleats_count = $_POST['p_p_pleats_count'];
$p_p_amplifier = $_POST['p_p_amplifier'];
$p_p_supplier = $_POST['p_p_supplier'];
$p_p_remark = $_POST['p_p_remark'];

/** КАРКАС */
$wf_length = $_POST['wf_length'];
$wf_width = $_POST['wf_width'];
$wf_material = $_POST['wf_material'];
$wf_supplier = $_POST['wf_supplier'];
if (($wf_length != '') and ($wf_width != '') and ($wf_material != '') and ($wf_supplier != '')) {
    $wf_name = "каркас ".$filter_name;
} else {
    $wf_name = "";
}

/** ПРЕДФИЛЬТР */
$pf_length = $_POST['pf_length'];
$pf_width = $_POST['pf_width'];
$pf_material = $_POST['pf_material'];
$pf_supplier = $_POST['pf_supplier'];
$pf_remark = $_POST['pf_remark'];
if (($pf_length != '') and ($pf_width != '') and ($pf_material != '') and ($pf_supplier != '')) {
    $pf_name = "предфильтр ".$filter_name;
} else {
    $pf_name = "";
}

/** УПАКОВКА ИНД */
$box = $_POST['box'];
/** УПАКОВКА ГР */
$g_box = $_POST['g_box'];
/** ПРИМЕЧАНИЕ */
$remark = $_POST['remark'];

/** ЛОГ ИЗМЕНЕНИЙ */
$changes_log = $_POST['changes_log'] ?? '';
$ip_address = $_SERVER['REMOTE_ADDR']; // IP пользователя
$user_name = $_SESSION['user_name'] ?? 'Гость'; // Если будет авторизация

$a = check_filter($_POST['filter_name']);

/** Если фильтр уже есть в БД -> выход */
if ($a > 0) {
    echo "Фильтр {$filter_name} уже есть в БД";
    exit();
}

/** Если фильтра в БД такого нет -> начинаем запись */

/** Запись информации о фильтре в БД */
$sql = "INSERT INTO panel_filter_structure(filter, category, paper_package, wireframe, prefilter, box, g_box, comment) 
        VALUES ('$filter_name','$category','$p_p_name','$wf_name','$pf_name','$box','$g_box','$remark');";
$result = mysql_execute($sql);

/** Запись информации о гофропакете в БД */
$sql = "INSERT INTO paper_package_panel(p_p_name, p_p_length, p_p_height, p_p_width, p_p_pleats_count, p_p_amplifier, supplier, p_p_remark) 
        VALUES('$p_p_name','$p_p_length','$p_p_height','$p_p_width','$p_p_pleats_count','$p_p_amplifier','$p_p_supplier','$p_p_remark');";
$result = mysql_execute($sql);

/** Запись информации о каркасе в БД если каркас указан */
if ($wf_name != '') {
    $sql = "INSERT INTO wireframe_panel(w_name, w_length, w_width, w_material, w_supplier) 
        VALUES('$wf_name','$wf_length','$wf_width','$wf_material','$wf_supplier');";
    $result = mysql_execute($sql);
}

/** Запись информации о предфильтре в БД если предфильтр указан*/
if ($pf_name != '') {
    $sql = "INSERT INTO prefilter_panel(p_name, p_length, p_width, p_material, p_supplier, p_remark)
        VALUES('$pf_name','$pf_length','$pf_width','$pf_material','$pf_supplier','$pf_remark')";
    $result = mysql_execute($sql);
}

/** Запись лога изменений в БД */
if (!empty(trim($changes_log))) {
    $changes_log = addslashes($changes_log); // экранирование спецсимволов
    $sql = "INSERT INTO changes_log (filter_name, user_name, changes, ip_address) 
            VALUES ('$filter_name', '$user_name', '$changes_log', '$ip_address')";
    mysql_execute($sql);
}

echo "Фильтр {$filter_name} успешно добавлен в БД";
?>
