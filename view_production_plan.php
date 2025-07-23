<?php
// Подключение к БД
$dsn = 'mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Получаем номер заявки
$order = $_GET['order'] ?? '';
if (!$order) {
    die("Не указан номер заявки.");
}

// Загружаем план
$stmt = $pdo->prepare("
    SELECT assign_date, filter_label, count
    FROM build_plan
    WHERE order_number = ?
    ORDER BY assign_date ASC
");
$stmt->execute([$order]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Группируем по датам
$calendar = [];
foreach ($rows as $row) {
    $date = $row['assign_date'];
    if (!isset($calendar[$date])) {
        $calendar[$date] = [];
    }
    $calendar[$date][] = $row;
}

$dates = array_keys($calendar);
$start = new DateTime(min($dates));
$end = new DateTime(max($dates));
$end->modify('+1 day');

$period = new DatePeriod($start, new DateInterval('P1D'), $end);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>План производства фильтров по заявке № <?= htmlspecialchars($order) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
            font-weight: normal;
        }
        .search-box {
            text-align: center;
            margin-bottom: 20px;
        }
        .search-box input {
            padding: 10px;
            width: 250px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        .day {
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .day strong {
            display: block;
            margin-bottom: 8px;
            color: #4CAF50;
        }
        .day ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .day ul li {
            font-size: 14px;
            padding: 2px 0;
            transition: background 0.2s;
        }
        .highlight {
            background: #ffeb3b;
            border-radius: 3px;
            padding: 2px;
        }
        .btn-back {
            display: block;
            width: 200px;
            text-align: center;
            margin: 30px auto;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .btn-back:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>

<h1>План производства фильтров по заявке № <?= htmlspecialchars($order) ?></h1>

<div class="search-box">
    <input type="text" id="searchInput" placeholder="Поиск фильтра...">
</div>

<div class="calendar" id="calendar">
    <?php foreach ($period as $date):
        $d = $date->format('Y-m-d');
        $items = $calendar[$d] ?? [];
        ?>
        <div class="day">
            <strong><?= $date->format('d.m.Y') ?></strong>
            <?php if ($items): ?>
                <ul>
                    <?php foreach ($items as $item): ?>
                        <li><?= htmlspecialchars($item['filter_label']) ?> — <?= (int)$item['count'] ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <em>Нет задач</em>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<a class="btn-back" href="plans.php">← Назад к списку планов</a>

<script>
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.day li').forEach(li => {
            const text = li.textContent.toLowerCase();
            if (query && text.includes(query)) {
                li.classList.add('highlight');
            } else {
                li.classList.remove('highlight');
            }
        });
    });
</script>

</body>
</html>
