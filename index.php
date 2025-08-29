<?php
session_start();

// простая флеш-ошибка из сессии
$err = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// CSRF-токен
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Вход • U2</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #0f172a;            /* slate-900 */
            --card: #111827;          /* gray-900 */
            --text: #e5e7eb;          /* gray-200 */
            --muted: #9ca3af;         /* gray-400 */
            --primary: #3b82f6;       /* blue-500 */
            --primary-hover: #2563eb; /* blue-600 */
            --danger: #ef4444;        /* red-500 */
            --border: #1f2937;        /* gray-800 */
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: var(--text); }

        .auth-body {
            min-height: 100%;
            background: radial-gradient(1200px 600px at 10% -10%, rgba(59,130,246,.15), transparent),
            radial-gradient(1200px 600px at 110% 110%, rgba(16,185,129,.12), transparent),
            var(--bg);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .auth-card {
            width: 100%;
            max-width: 420px;
            background: linear-gradient(180deg, rgba(255,255,255,.02), rgba(255,255,255,.01));
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 20px 60px rgba(0,0,0,.45);
            backdrop-filter: blur(6px);
        }

        .auth-title { margin: 0 0 6px; font-size: 24px; font-weight: 700; }
        .auth-subtitle { margin: 0 0 18px; color: var(--muted); }

        .alert {
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.35);
            color: #fecaca;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .auth-form { display: grid; gap: 14px; }

        .field { display: grid; gap: 6px; }
        .field span { font-size: 14px; color: var(--muted); }
        .field input, .field select, .field textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #0b1220;
            color: var(--text);
            outline: none;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,.25);
        }

        .btn-primary {
            margin-top: 6px;
            width: 100%;
            padding: 12px 14px;
            border: none;
            border-radius: 12px;
            background: var(--primary);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: transform .04s ease, background .15s ease;
        }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-primary:active { transform: translateY(1px); }

        .auth-footer {
            margin-top: 18px;
            font-size: 12px;
            color: var(--muted);
            text-align: center;
        }

    </style>
</head>
<body class="auth-body">
<div class="auth-card">
    <h1 class="auth-title">Система U2</h1>
    <p class="auth-subtitle">Авторизация пользователя</p>

    <?php if ($err): ?>
        <div class="alert"><?= htmlspecialchars($err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="enter.php" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <label class="field">
            <span>Имя пользователя</span>
            <input name="user_name" type="text" required autofocus placeholder="Введите имя">
        </label>

        <label class="field">
            <span>Пароль</span>
            <input name="user_pass" type="password" required placeholder="Введите пароль">
        </label>

        <label class="field">
            <span>Подразделение</span>
            <select name="workshop" required>
                <option value="" selected disabled>Выберите подразделение</option>

                <option value="U2">U2</option>

            </select>
        </label>

        <button type="submit" class="btn-primary">Войти</button>
    </form>

    <div class="auth-footer">© <?php echo date('Y'); ?> Производство U2</div>
</div>
</body>
</html>
