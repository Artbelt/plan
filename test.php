<?php

require_once('tools/tools.php');
require_once('settings.php');
require_once('Planned_order.php');

global $main_roll_length;
global $max_gap;
global $min_gap;
global $width_of_main_roll;

if (isset($_POST)){

    print_r_my($_POST);

    /** Читаем данные переданные в пост_запросе в массив */

    /** @var  $separated_order массив позиций, которые надо кроить */
    $separated_order = array();
    /** @var  $ignored_positions массив позиций, которые не надо кроить*/
    $ignored_positions = array();

/* Образец данных, переданых в POST
    [filter_1] => SX1211
    [count_1] => 1000
    [width_1] => 123.5
    [chck_box_1] => */
    $y=1;
    //$a=0;
    //$b=0;

    //echo "count(POST)=";
    //echo count($_POST);
    //echo "<br>";
    for ($x = 0; $x < (count($_POST))-1; $x = $x + 4){

        //echo "x=".$x;
        /**  временный массив для сбора позиций */
        $temp_array = array();
        array_push($temp_array,$_POST['filter_'.($y)]);
        array_push($temp_array,$_POST['count_'.($y)]);
        array_push($temp_array,$_POST['width_'.($y)]);
        /** Если нажато галочка игнорировать: */
        if ($_POST['chck_box_'.($y)] == 'checked'){
            /** Заносим в массив с игнорируемымыи позициями */
            array_push($ignored_positions, $temp_array);
            //$a = $a+1;
            //echo "a=".$a."<br>";
        } else {
            /** Заносим в массив для раскроя  */
            array_push($separated_order, $temp_array);
            //$b=$b+1;
            //echo "b=".$b."<br>";
        }
        $y = $y+1; // переменная счета
    }
    $order_number = $_POST['order_number'];
    echo $order_number;
    echo "<p>Позиции для раскроя<p>";
    print_r_my($separated_order);
    echo "<p>Игнорируемые позиции<p>";
    print_r_my($ignored_positions);
}

/** Создаем объект планирования заявки */
$initial_order = new Planned_order;

/** Задаем ему имя */
$initial_order->set_name($order_number);

/** Задаем ему заявку */
$initial_order->set_order(get_order($order_number));

/** Проверяем на наличие фильтров в БД */
$initial_order->check_for_new_filters();

/** получаем данные для расчета раскроя (параметры гофропакетов) */
$initial_order->get_data_for_cutting_separately($main_roll_length);

/** инициализируем массив для формирования раскроев */
$initial_order->cut_array_and_half_cut_array_init();

$initial_order->cu





?>

