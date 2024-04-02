
<html>
<head>
    <title>
        Plan system
    </title>
    <link rel="stylesheet" href="sheets.css">
</head>
<body>
<div class="center">
<?php
echo '<form action="enter.php" method="get">';
echo '<input  type="text" name="user_name" width="140" placeholder="user_name"/>';
echo '<input  type="password" name="user_pass" width="140" placeholder="user_pass"/>';
echo '<select name="workshop">';
echo '<option value="U2">Сборочный участок №2</option>';
echo '</select>';
echo '<input type="submit" value="           Вход в систему        "/>';
echo '</form>';
?>
    <p>

    <form method="post"  action="share_orders.php" name="workshop_too" value="U2">
        <button type="submit">.... Просмотр заявок У2 .... </button>
    </form>

</div>



</body>
</html>


