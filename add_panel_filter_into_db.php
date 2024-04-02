<?php
require_once('tools/tools.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <title>Добавление нового панельного фильтра в БД</title>
    <style>
        article, aside, details, figcaption, figure, footer,header,
        hgroup, menu, nav, section { display: block; }
    </style>
</head>
<body>


<?php

echo " <H3><b>Добавление нового фильтра в БД</b></H3>";

if (isset($_POST['filter_name'])){
    $filter_name = $_POST['filter_name'];
} else {
    $filter_name = '';
}

if (isset($_POST['analog_filter']) AND ($_POST['analog_filter'] != '')){
    $analog_filter = $_POST['analog_filter'];
    /** Если аналог установлен то загружаем всю информацию в поля о аналоге */
    echo "<p>ANALOG_FILTER=".$analog_filter;
    // массив для записи всех значений аналога
    $analog_data = get_filter_data($analog_filter);
    //var_dump(get_filter_data($analog_filter));

}else{
    echo "<p> Аналог не определен";
    $analog_data = array();
    $analog_data['paper_package_length'] ='';
    $analog_data['paper_package_width'] ='';
    $analog_data['paper_package_height'] ='';
    $analog_data['paper_package_pleats_count'] ='';
    $analog_data['paper_package_amplifier'] ='';
    $analog_data['paper_package_remark'] ='';
    $analog_data['paper_package_supplier'] ='';
    $analog_data['wireframe_length'] ='';
    $analog_data['wireframe_width'] ='';
    $analog_data['wireframe_material'] ='';
    $analog_data['wireframe_supplier'] ='';
    $analog_data['prefilter_length'] ='';
    $analog_data['prefilter_width'] ='';
    $analog_data['prefilter_material'] ='';
    $analog_data['prefilter_supplier'] ='';
    $analog_data['prefilter_remark'] ='';
    $analog_data['box'] ='';
    $analog_data['g_box'] ='';
    $analog_data['comment'] ='';
}

?>

<form action="processing_add_panel_filter_into_db.php" method="post" >
    <label><b>Наименование фильтра</b>
    <input type="text" name="filter_name" size="40" value="<?php echo $filter_name?>"><p>
    </label>
    <div id="mark"></div>
    <label>Категория
    <select name="category">
        <option>Панельный</option>
    </select>
    </label><br>
    <hr>
    <label><b>Гофропакет:</b></label><p>
        <label>Длина: <input type="text" size="5" name="p_p_length" value="<?php echo $analog_data['paper_package_length'] ?>"></label>
        <label>Ширина: <input type="text" size="5" name="p_p_width" value="<?php echo $analog_data['paper_package_width'] ?>"></label>
        <label>Высота:<input type="text" size="5" name="p_p_height" value="<?php echo $analog_data['paper_package_height'] ?>"> </label>
        <label>Кол-во ребер: <input type="text" size="5" name="p_p_pleats_count" value="<?php echo $analog_data['paper_package_pleats_count'] ?>"></label>
        <label>Усилитель: <input type="text" size="2" name="p_p_amplifier" value="<?php echo $analog_data['paper_package_amplifier'] ?>"></label>
        <label>Поставщик: <select name="p_p_supplier"  ><option></option>
                                                        <option  <?php if ($analog_data['paper_package_supplier'] == 'У2'){echo 'selected';} ?> >У2</option>
            </select></label><p>
        <label>Комментарий: <input type="text" size="50" name="p_p_remark" value="<?php echo $analog_data['paper_package_remark'] ?>"></label><br>

        <hr>
    <label><b>Каркас</b></label><p>
        <label>Длина: <input type="text" size="5" name="wf_length" value="<?php echo $analog_data['wireframe_length'] ?>"></label>
        <label>Ширина: <input type="text" size="5" name="wf_width" value="<?php echo $analog_data['wireframe_width'] ?>"></label>
        <label>Материал: <select name="wf_material"><option></option>
                                                    <option <?php if ($analog_data['wireframe_material'] == 'ОЦ 0,45'){echo 'selected';} ?>>ОЦ 0,45</option>
                                                    <option <?php if ($analog_data['wireframe_material'] == 'Жесть 0,22'){echo 'selected';} ?>>Жесть 0,22</option>
                        </select></label>
        <label>Поставщик: <select name="wf_supplier"><option></option>
                                                    <option <?php if ($analog_data['wireframe_supplier'] == 'ЗУ'){echo 'selected';} ?>>ЗУ</option>
                          </select><br></label>
        <hr>

    <label><b>Предфильтр</b></label><p>
        <label>Длина:<input type="text" size="5" name="pf_length" value="<?php echo $analog_data['prefilter_length'] ?>"> </label>
        <label>Ширина: <input type="text" size="5" name="pf_width"value="<?php echo $analog_data['prefilter_width'] ?>"></label>
        <label>Материал:<select name="pf_material"><option></option>
                                                    <option <?php if ($analog_data['prefilter_material'] == 'Н/т полотно'){echo 'selected';} ?>>Н/т полотно</option>
                    </select> </label>
        <label>Поставщик:<select name="pf_supplier"><option></option>
                                                    <option <?php if ($analog_data['prefilter_supplier'] == 'УУ'){echo 'selected';} ?>>УУ</option>
                        </select></label><p>
        <label>Комментарий:<input type="text" size="50" name="pf_remark" value="<?php echo $analog_data['prefilter_remark'] ?>"></label><br>
    <hr>
    <label><b>Индивидуальная упаковка</b></label><p>
        <label>Коробка №:   <select name="box"><?php select_boxes($analog_data['box']);?></select></label><br>
    <hr>
    <label><b>Групповая упаковка</b></label><p>
        <label>Ящик №: <select name="g_box"><?php select_g_boxes($analog_data['g_box']);?></select></label><br>
    <hr>
    <label><b>Примечание</b>
        <input type="text" size="100" name="remark" value="<?php echo $analog_data['comment'] ?>">
    </label><p>
    <hr>
    <input type="submit" value="Сохранить фильтр">

</form>

</body>
</html>



