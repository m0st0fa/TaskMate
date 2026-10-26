
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['customer', 'helper'], true)) {
    header('Location: /dashboard/admin.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        c.id AS conversation_id,
        c.task_id,
        c.updated_at,
        t.title AS task_title,
        CASE
            WHEN c.customer_id = ? THEN helper.full_name
            ELSE customer.full_name
        END AS other_name,
        CASE
            WHEN c.customer_id = ? THEN helper.id
            ELSE customer.id
        END AS other_user_id,
        (
            SELECT m.message
            FROM messages m
            WHERE m.conversation_id = c.id
            ORDER BY m.id DESC
            LIMIT 1
        ) AS last_message,
        (
            SELECT COUNT(*)
            FROM messages unread
            WHERE unread.conversation_id = c.id
              AND unread.sender_id != ?
              AND unread.is_read = 0
        ) AS unread_count
    FROM conversations c
    INNER JOIN tasks t ON t.id = c.task_id
    INNER JOIN users customer ON customer.id = c.customer_id
    INNER JOIN users helper ON helper.id = c.helper_id
    WHERE c.customer_id = ? OR c.helper_id = ?
    ORDER BY c.updated_at DESC"
);

$stmt->execute([$userId, $userId, $userId, $userId, $userId]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Messages | TaskMate';
require_once __DIR__ . '/../includes/header.php';

function messagesEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<style>
.messages-page {
    max-width: 1050px;
    margin: 0 auto;
    padding: 35px 20px 60px;
    color: #24352b;
}
.messages-heading { margin-bottom: 25px; }
.messages-heading h1 { margin: 0 0 8px; font-size: 30px; }
.messages-heading p { margin: 0; color: #64756a; }
.messages-list {
    background: white;
    border: 1px solid #e3ebe5;
    border-radius: 18px;
    overflow: hidden;
}
.message-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    color: inherit;
    text-decoration: none;
    border-bottom: 1px solid #edf1ee;
}
.message-item:last-child { border-bottom: 0; }
.message-item:hover { background: #f7fbf8; }
.message-avatar {
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #e5f5eb;
    color: #16834a;
    font-size: 20px;
    font-weight: 800;
}
.message-details { min-width: 0; flex: 1; }
.message-details strong { display: block; margin-bottom: 5px; }
.message-task { font-size: 12px; color: #16834a; margin-bottom: 5px; }
.message-preview {
    color: #64756a;
    font-size: 14px;
    overflow-wrap: anywhere;
}
.message-unread {
    min-width: 24px;
    height: 24px;
    padding: 0 7px;
    border-radius: 20px;
    background: #16834a;
    color: white;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 700;
}
.messages-empty {
    text-align: center;
    padding: 55px 20px;
}
.messages-empty h2 { margin: 0 0 10px; }
.messages-empty p { color: #64756a; line-height: 1.7; }
.messages-browse {
    display: inline-block;
    margin-top: 10px;
    padding: 11px 17px;
    border-radius: 9px;
    background: #16834a;
    color: white;
    text-decoration: none;
    font-weight: 700;
}
@media(max-width: 500px) {
    .messages-page { padding: 25px 13px 40px; }
    .message-item { padding: 16px 12px; gap: 11px; }
    .messages-heading h1 { font-size: 25px; }
}
</style>

<main class="messages-page">
    <header class="messages-heading">
        <h1>Messages</h1>
        <p>Chat with customers and helpers about your tasks.</p>
    </header>

    <section class="messages-list">
        <?php if ($conversations): ?>
            <?php foreach ($conversations as $conversation): ?>
                <a
                    class="message-item"
                    href="/pages/chat.php?id=<?= (int) $conversation['conversation_id'] ?>"
                >
                    <div class="message-avatar">
                        <?= messagesEscape(strtoupper(substr($conversation['other_name'] ?: 'U', 0, 1))) ?>
                    </div>

                    <div class="message-details">
                        <strong><?= messagesEscape($conversation['other_name']) ?></strong>

                        <div class="message-task">
                            Task: <?= messagesEscape($conversation['task_title']) ?>
                        </div>

                        <div class="message-preview">
                            <?= $conversation['last_message'] !== null
                                ? messagesEscape($conversation['last_message'])
                                : 'Start your conversation...' ?>
                        </div>
                    </div>

                    <?php if ((int) $conversation['unread_count'] > 0): ?>
                        <span class="message-unread">
                            <?= (int) $conversation['unread_count'] ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="messages-empty">
                <h2>No conversations yet</h2>
                <p>Your conversations with customers or helpers will appear here.</p>
                <a class="messages-browse" href="/pages/browse.php">Browse Tasks</a>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
