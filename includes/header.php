<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'TaskMate | Get Things Done';

$isLoggedIn = isset($_SESSION['user_id']);
$userId     = (int)($_SESSION['user_id'] ?? 0);
$userName   = $_SESSION['full_name'] ?? 'User';
$userRole   = $_SESSION['role'] ?? 'customer';

$dashboardUrls = [
    'customer' => '/dashboard/customer.php',
    'helper'   => '/dashboard/helper.php',
    'admin'    => '/dashboard/admin.php',
];

$dashboardUrl = $dashboardUrls[$userRole] ?? null;
$unreadMessageCount = 0;

/* Get the unread message count for the currently logged-in customer/helper. */
if ($isLoggedIn && in_array($userRole, ['customer', 'helper'], true)) {
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            require_once __DIR__ . '/../config/database.php';
        }

        if (isset($pdo) && $pdo instanceof PDO) {
            $unreadStmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM messages m
                 INNER JOIN conversations c ON c.id = m.conversation_id
                 WHERE m.is_read = 0
                   AND m.sender_id <> :user_id
                   AND ((c.customer_id = :customer_id AND :role_customer = 'customer')
                     OR (c.helper_id = :helper_id AND :role_helper = 'helper'))"
            );
            $unreadStmt->execute([
                'user_id'      => $userId,
                'customer_id'  => $userId,
                'role_customer'=> $userRole,
                'helper_id'    => $userId,
                'role_helper'  => $userRole,
            ]);
            $unreadMessageCount = (int)$unreadStmt->fetchColumn();
        }
    } catch (Throwable $e) {
        // Keep the site navigation available even if the message count cannot load.
        error_log('TaskMate unread message count error: ' . $e->getMessage());
        $unreadMessageCount = 0;
    }
}

$navItems = [
    'guest' => [
        ['label' => 'Home', 'url' => '/index.php'],
        ['label' => 'Browse Tasks', 'url' => '/pages/browse.php'],
    ],

    'customer' => [
        ['label' => 'Home', 'url' => '/index.php'],
        ['label' => 'Post a Task', 'url' => '/pages/post-task.php'],
        ['label' => 'Browse Tasks', 'url' => '/pages/browse.php'],
        ['label' => 'My Tasks', 'url' => '/pages/my-tasks.php'],
        ['label' => 'Messages', 'url' => '/pages/messages.php', 'messages' => true],
        ['label' => 'My Profile', 'url' => '/pages/profile.php'],
    ],

    'helper' => [
        ['label' => 'Home', 'url' => '/index.php'],
        ['label' => 'Browse Tasks', 'url' => '/pages/browse.php'],
        ['label' => 'Assigned Tasks', 'url' => '/pages/assigned-tasks.php'],
        ['label' => 'Messages', 'url' => '/pages/messages.php', 'messages' => true],
        ['label' => 'My Profile', 'url' => '/pages/profile.php'],
    ],

    'admin' => [
        ['label' => 'Home', 'url' => '/index.php'],
        ['label' => 'Browse Tasks', 'url' => '/pages/browse.php'],
        ['label' => 'My Profile', 'url' => '/pages/profile.php'],
    ],
];

$currentRole = ($isLoggedIn && isset($navItems[$userRole]))
    ? $userRole
    : 'guest';

$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';

if (!function_exists('taskMateNavActive')) {
    function taskMateNavActive(string $url, string $currentScript): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return $path === $currentScript ? ' active' : '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="/assets/css/style.css">

    <style>
        .tm-navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #fff;
            border-bottom: 1px solid #e4ebe6;
            box-shadow: 0 2px 10px rgba(20, 50, 35, .04);
        }

        .tm-nav-container {
            max-width: 1440px;
            min-height: 78px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .tm-logo {
            color: #16834a;
            font-size: 29px;
            font-weight: 800;
            text-decoration: none;
            letter-spacing: -.8px;
            white-space: nowrap;
        }

        .tm-logo span { color: #1d9a5b; }

        .tm-nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 7px;
        }

        .tm-nav-links a {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 13px;
            color: #52645a;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 9px;
            transition: .2s ease;
        }

        .tm-nav-links a:hover,
        .tm-nav-links a.active {
            color: #16834a;
            background: #eaf6ee;
        }

        .tm-message-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 19px;
            height: 19px;
            padding: 0 5px;
            box-sizing: border-box;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 11px;
            line-height: 1;
            font-weight: 800;
        }

        .tm-nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
            white-space: nowrap;
        }

        .tm-user-name {
            color: #34483b;
            font-size: 14px;
            font-weight: 600;
        }

        .tm-nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 17px;
            border-radius: 9px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition: .2s ease;
        }

        .tm-btn-login {
            color: #16834a;
            border: 1px solid #16834a;
            background: #fff;
        }

        .tm-btn-register,
        .tm-btn-logout {
            color: #fff;
            background: #16834a;
            border: 1px solid #16834a;
        }

        .tm-btn-login:hover { background: #eaf6ee; }
        .tm-btn-register:hover,
        .tm-btn-logout:hover { background: #116b3b; color: #fff; }

        .tm-menu-toggle {
            display: none;
            border: 1px solid #dce7df;
            background: #fff;
            border-radius: 8px;
            padding: 8px 11px;
            font-size: 22px;
            cursor: pointer;
            color: #16834a;
        }

        @media (max-width: 1050px) {
            .tm-nav-container { flex-wrap: wrap; gap: 12px; }
            .tm-menu-toggle { display: block; margin-left: auto; }
            .tm-nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                order: 3;
            }
            .tm-nav-links.open { display: flex; }
            .tm-nav-links a { padding: 12px; }
            .tm-nav-right { order: 2; }
        }

        @media (max-width: 560px) {
            .tm-nav-container { padding: 12px 15px; }
            .tm-logo { font-size: 25px; }
            .tm-user-name { display: none; }
            .tm-nav-right { gap: 7px; }
            .tm-nav-btn { padding: 9px 11px; font-size: 12px; }
        }
    </style>
</head>

<body>
<header class="tm-navbar">
    <div class="tm-nav-container">
        <a class="tm-logo" href="/index.php">TaskMate<span>.</span></a>

        <button
            class="tm-menu-toggle"
            id="tmMenuToggle"
            type="button"
            aria-label="Toggle navigation"
            aria-expanded="false"
            aria-controls="tmNavLinks"
        >☰</button>

        <nav class="tm-nav-links" id="tmNavLinks">
            <?php foreach ($navItems[$currentRole] as $item): ?>
                <a
                    href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                    class="<?= taskMateNavActive($item['url'], $currentScript) ?>"
                    <?php if (!empty($item['messages'])): ?>aria-label="Messages<?= $unreadMessageCount > 0 ? ', ' . $unreadMessageCount . ' unread' : '' ?>"<?php endif; ?>
                >
                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($item['messages']) && $unreadMessageCount > 0): ?>
                        <span class="tm-message-badge"><?= $unreadMessageCount > 99 ? '99+' : $unreadMessageCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <?php if ($isLoggedIn && $dashboardUrl !== null): ?>
                <a
                    href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="<?= taskMateNavActive($dashboardUrl, $currentScript) ?>"
                >Dashboard</a>
            <?php endif; ?>
        </nav>

        <div class="tm-nav-right">
            <?php if ($isLoggedIn): ?>
                <span class="tm-user-name">
                    Hi, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <a class="tm-nav-btn tm-btn-logout" href="/logout.php">Logout</a>
            <?php else: ?>
                <a class="tm-nav-btn tm-btn-login" href="/login.php">Login</a>
                <a class="tm-nav-btn tm-btn-register" href="/register.php">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('tmMenuToggle');
    const nav = document.getElementById('tmNavLinks');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            const isOpen = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.textContent = isOpen ? '✕' : '☰';
        });
    }
});
</script>
</body>
</html>
