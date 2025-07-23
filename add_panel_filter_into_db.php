<?php
require_once('tools/tools.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <title>Добавление нового панельного фильтра в БД</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h3 {
            text-align: center;
            color: #444;
        }
        form {
            max-width: 900px;
            margin: 20px auto;
            background: #fff;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        label {
            display: inline-block;
            margin: 10px 0;
            font-weight: bold;
        }
        input[type="text"], select {
            padding: 5px 8px;
            margin-left: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #ddd;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        input[type="submit"] {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
        .field-group {
            margin-bottom: 10px;
        }
        .field-group label {
            font-weight: normal;
            margin-right: 10px;
        }
        #change-log {
            margin-top: 20px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            max-height: 150px;
            overflow-y: auto;
        }
        #change-log div {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>

<h3><b>Добавление / редактирование фильтра в БД</b></h3>

<?php
if (isset($_POST['filter_name'])){
    $filter_name = $_POST['filter_name'];
} else {
    $filter_name = '';
}

if (isset($_POST['analog_filter']) AND ($_POST['analog_filter'] != '')){
    $analog_filter = $_POST['analog_filter'];
    echo "<p style='text-align: center'>ANALOG_FILTER = " . $analog_filter . "</p>";
    $analog_data = get_filter_data($analog_filter);
} else {
    $analog_filter = '';
    echo "<p style='text-align: center';>Аналог не определен</p>";
    $analog_data = array(
        'paper_package_length' => '',
        'paper_package_width' => '',
        'paper_package_height' => '',
        'paper_package_pleats_count' => '',
        'paper_package_amplifier' => '',
        'paper_package_remark' => '',
        'paper_package_supplier' => '',
        'wireframe_length' => '',
        'wireframe_width' => '',
        'wireframe_material' => '',
        'wireframe_supplier' => '',
        'prefilter_length' => '',
        'prefilter_width' => '',
        'prefilter_material' => '',
        'prefilter_supplier' => '',
        'prefilter_remark' => '',
        'glueing' => '',
        'glueing_remark' => '',
        'box' => '',
        'g_box' => '',
        'comment' => ''
    );
}
?>

<form action="processing_add_panel_filter_into_db.php" method="post">
    <div class="field-group">
        <label>Наименование фильтра</label>
        <input type="text" name="filter_name" size="40" value="<?php echo $analog_filter?>">
    </div>
    <div class="field-group">
        <label>Категория</label>
        <select name="category">
            <option>Панельный</option>
        </select>
    </div>

    <hr>
    <div class="section-title">Гофропакет:</div>
    <div class="field-group">
        <label>Длина г/п:<input type="text" size="5" name="p_p_length" value="<?php echo $analog_data['paper_package_length'] ?>"></label>
        <label>Ширина г/п:<input type="text" size="5" name="p_p_width" value="<?php echo $analog_data['paper_package_width'] ?>"></label>
        <label>Высота г/п:<input type="text" size="5" name="p_p_height" value="<?php echo $analog_data['paper_package_height'] ?>"></label>
        <label>Кол-во ребер  г/п:<input type="text" size="5" name="p_p_pleats_count" value="<?php echo $analog_data['paper_package_pleats_count'] ?>"></label>
    </div>
    <div class="field-group">
        <label>Усилитель:<input type="text" size="2" name="p_p_amplifier" value="<?php echo $analog_data['paper_package_amplifier'] ?>"></label>
        <label>Поставщик  г/п:
            <select name="p_p_supplier">
                <option></option>
                <option <?php if ($analog_data['paper_package_supplier'] == 'У2'){echo 'selected';} ?> >У2</option>
            </select>
        </label>
    </div>
    <div class="field-group">
        <label>Комментарий  г/п:<input type="text" size="50" name="p_p_remark" value="<?php echo $analog_data['paper_package_remark'] ?>"></label>
    </div>

    <hr>
    <div class="section-title">Каркас:</div>
    <div class="field-group">
        <label>Длина каркаса:<input type="text" size="5" name="wf_length" value="<?php echo $analog_data['wireframe_length'] ?>"></label>
        <label>Ширина каркаса:<input type="text" size="5" name="wf_width" value="<?php echo $analog_data['wireframe_width'] ?>"></label>
        <label>Материал каркаса:
            <select name="wf_material">
                <option></option>
                <option <?php if ($analog_data['wireframe_material'] == 'ОЦ 0,45'){echo 'selected';} ?>>ОЦ 0,45</option>
                <option <?php if ($analog_data['wireframe_material'] == 'Жесть 0,22'){echo 'selected';} ?>>Жесть 0,22</option>
            </select>
        </label>
        <label>Поставщик каркаса:
            <select name="wf_supplier">
                <option></option>
                <option <?php if ($analog_data['wireframe_supplier'] == 'ЗУ'){echo 'selected';} ?>>ЗУ</option>
            </select>
        </label>
    </div>

    <hr>
    <div class="section-title">Предфильтр:</div>
    <div class="field-group">
        <label>Длина предфильтра:<input type="text" size="5" name="pf_length" value="<?php echo $analog_data['prefilter_length'] ?>"></label>
        <label>Ширина предфильтра:<input type="text" size="5" name="pf_width" value="<?php echo $analog_data['prefilter_width'] ?>"></label>
        <label>Материал предфильтра:
            <select name="pf_material">
                <option></option>
                <option <?php if ($analog_data['prefilter_material'] == 'Н/т полотно'){echo 'selected';} ?>>Н/т полотно</option>
            </select>
        </label>
        <label>Поставщик предфильтра:
            <select name="pf_supplier">
                <option></option>
                <option <?php if ($analog_data['prefilter_supplier'] == 'УУ'){echo 'selected';} ?>>УУ</option>
            </select>
        </label>
    </div>
    <div class="field-group">
        <label>Комментарий к предфильтру:<input type="text" size="50" name="pf_remark" value="<?php echo $analog_data['prefilter_remark'] ?>"></label>
    </div>
    <hr>
    <div class="section-title">Проливка:</div>
    <select name="glueing">
        <option></option>
        <option <?php if ($analog_data['glueing'] == '1'){echo 'selected';} ?>>1</option>
        <option <?php if ($analog_data['glueing'] == '2'){echo 'selected';} ?>>2</option>
        <option <?php if ($analog_data['glueing'] == '3'){echo 'selected';} ?>>3</option>
        <option <?php if ($analog_data['glueing'] == '4'){echo 'selected';} ?>>4</option>
    </select>
    <div class="field-group">
        <label>Комментарий к проливке:<input type="text" size="50" name="glueing_remark" value="<?php echo $analog_data['glueing_remark']; ?>"></label>
    </div>
    </div>

    <hr>
    <div class="section-title">Индивидуальная упаковка:</div>
    <div class="field-group">
        <label>Коробка №: <select name="box"><?php select_boxes($analog_data['box']);?></select></label>
    </div>

    <hr>
    <div class="section-title">Групповая упаковка:</div>
    <div class="field-group">
        <label>Ящик №: <select name="g_box"><?php select_g_boxes($analog_data['g_box']);?></select></label>
    </div>

    <hr>
    <div class="field-group">
        <label>Примечание:<input type="text" size="100" name="remark" value="<?php echo $analog_data['comment'] ?>"></label>
    </div>

    <hr>
    <div id="change-log"><b>Изменения:</b></div>
    <input type="hidden" name="changes_log" id="changes_log">

    <input type="submit" value="Сохранить фильтр">
</form>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const logDiv = document.getElementById("change-log");
        const logField = document.getElementById("changes_log");

        document.querySelectorAll("input[type=text], select").forEach(el => {
            el.dataset.oldValue = el.value;

            el.addEventListener("change", function() {
                let oldVal = this.dataset.oldValue || "пусто";
                let newVal = this.value || "пусто";
                if (oldVal !== newVal) {
                    let label = this.closest("label") ? this.closest("label").innerText.split(':')[0] : this.name;
                    let msg = `  ${label}  : [ ${oldVal} ] => [ ${newVal} ];`;
                    let p = document.createElement("div");
                    p.textContent = msg;
                    logDiv.appendChild(p);
                    this.dataset.oldValue = newVal;
                    logField.value += msg + "\n";
                }
            });
        });
    });
</script>

</body>
</html>
