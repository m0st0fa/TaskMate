
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'customer') {
    http_response_code(403);
    exit('Only customers can access this page. <a href="../index.php">Go Home</a>');
}

function my_tasks_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['my_tasks_token'])) {
    $_SESSION['my_tasks_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $taskId = filter_var(
        $_POST['task_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['my_tasks_token'], $token)
    ) {
        $error = 'Invalid request. Please refresh the page.';
    } elseif (!$taskId || $taskId < 1) {
        $error = 'Invalid task ID.';
    } else {
        try {
            // Only cancel this customer's tasks that are still posted.
            $stmt = $pdo->prepare(
                "UPDATE tasks
                 SET status = 'cancelled'
                 WHERE id = :task_id
                   AND customer_id = :customer_id
                   AND status = 'posted'"
            );

            $stmt->execute([
                ':task_id' => $taskId,
                ':customer_id' => $_SESSION['user_id']
            ]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['my_tasks_token'] = bin2hex(random_bytes(32));

                header('Location: my-tasks.php?cancelled=1');
                exit;
            }

            $error = 'Task could not be cancelled. It may not belong to you, or its status may have changed.';
        } catch (PDOException $e) {
            error_log('Task cancellation failed: ' . $e->getMessage());
            $error = 'Unable to cancel the task right now. Please try again.';
        }
    }
}

if (isset($_GET['cancelled']) && $_GET['cancelled'] === '1') {
    $message = 'Your task has been cancelled successfully.';
}

// Fetch only tasks belonging to the logged-in customer.
$stmt = $pdo->prepare(
    'SELECT
        t.id,
        t.title,
        t.description,
        t.location,
        t.budget,
        t.status,
        t.created_at,
        c.name AS category_name
     FROM tasks t
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.customer_id = :customer_id
     ORDER BY t.created_at DESC'
);

$stmt->execute([
    ':customer_id' => $_SESSION['user_id']
]);

$tasks = $stmt->fetchAll();

$statusLabels = [
    'posted' => 'Posted',
    'assigned' => 'Assigned',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Tasks | TaskMate</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
    background: #f5f8f5;
    color: #193125;
}

a { text-decoration: none; }

.navbar {
    background: white;
    border-bottom: 1px solid #e0e8e1;
}

.nav-inner {
    max-width: 1100px;
    margin: auto;
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.logo {
    font-size: 25px;
    font-weight: 800;
    color: #16834a;
}

.back {
    color: #53665a;
    font-size: 14px;
    font-weight: 600;
}

.container {
    max-width: 1100px;
    width: calc(100% - 32px);
    margin: 38px auto 60px;
}

.heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.heading h1 {
    font-size: clamp(27px, 4vw, 35px);
    margin: 0 0 6px;
    letter-spacing: -1px;
}

.heading p {
    color: #718075;
    margin: 0;
    font-size: 14px;
}

.primary-btn {
    background: #16834a;
    color: white;
    border-radius: 9px;
    padding: 12px 17px;
    font-weight: 700;
    font-size: 14px;
    white-space: nowrap;
}

.primary-btn:hover { background: #10683a; }

.notice {
    padding: 13px 16px;
    margin-bottom: 18px;
    border-radius: 9px;
    font-size: 14px;
}

.success {
    color: #176637;
    background: #e8f7ed;
    border: 1px solid #c9e8d2;
}

.error {
    color: #982929;
    background: #fff0f0;
    border: 1px solid #f2cccc;
}

.task-list {
    display: grid;
    gap: 17px;
}

.task-card {
    background: white;
    border: 1px solid #e0e8e1;
    border-radius: 14px;
    padding: 23px;
    box-shadow: 0 5px 20px #1a492008;
}

.task-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.task-title {
    font-size: 19px;
    font-weight: 750;
    margin: 0 0 7px;
    overflow-wrap: anywhere;
}

.task-title a { color: #193125; }
.task-title a:hover { color: #16834a; }

.category {
    font-size: 13px;
    color: #718075;
}

.status {
    display: inline-block;
    border-radius: 30px;
    padding: 6px 11px;
    font-size: 12px;
    font-weight: 750;
    white-space: nowrap;
}

.status-posted { background: #e5f5e9; color: #18733e; }
.status-assigned { background: #e8efff; color: #3057a4; }
.status-in_progress { background: #fff0d7; color: #96620a; }
.status-completed { background: #e5f3f7; color: #24677b; }
.status-cancelled { background: #f0eeee; color: #777; }

.description {
    margin: 16px 0;
    color: #58685d;
    font-size: 14px;
    line-height: 1.7;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

.meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 22px;
    color: #617166;
    font-size: 13px;
    padding-top: 14px;
    border-top: 1px solid #edf1ed;
}

.meta strong { color: #193125; }

.actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 18px;
}

.details-btn {
    display: inline-block;
    color: #16834a;
    border: 1px solid #16834a;
    border-radius: 8px;
    padding: 9px 14px;
    font-size: 13px;
    font-weight: 700;
}

.cancel-form { margin: 0; }

.cancel-btn {
    border: 0;
    background: #fff0f0;
    color: #b12e2e;
    border-radius: 8px;
    padding: 10px 13px;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.cancel-btn:hover { background: #ffdddd; }

.empty {
    background: white;
    border: 1px solid #e0e8e1;
    border-radius: 15px;
    text-align: center;
    padding: 55px 20px;
}

.empty-icon { font-size: 42px; }

.empty h2 { margin: 12px 0 8px; }

.empty p {
    color: #718075;
    margin: 0 0 22px;
}

footer {
    text-align: center;
    color: #718075;
    padding: 20px;
    font-size: 12px;
}

@media(max-width: 600px) {
    .nav-inner { padding: 15px 17px; }
    .container { margin-top: 27px; }
    .heading { align-items: flex-start; flex-direction: column; }
    .task-card { padding: 18px; }
    .task-top { flex-direction: column; }
}
</style>
</head>

<body>

<header class="navbar">
    <div class="nav-inner">
        <a href="../index.php" class="logo">TaskMate.</a>
        <a href="../index.php" class="back">← Back to Home</a>
    </div>
</header>

<main class="container">

    <div class="heading">
        <div>
            <h1>My Tasks</h1>
            <p>Manage and track the tasks you have posted.</p>
        </div>

        <a href="post-task.php" class="primary-btn">+ Post a Task</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="notice success">
            <?= my_tasks_e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error">
            <?= my_tasks_e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($tasks)): ?>

        <section class="empty">
            <div class="empty-icon">📋</div>
            <h2>No tasks yet</h2>
            <p>Your posted tasks will appear here.</p>
            <a href="post-task.php" class="primary-btn">Post Your First Task</a>
        </section>

    <?php else: ?>

        <div class="task-list">

            <?php foreach ($tasks as $task): ?>
                <?php
                    $status = $task['status'];
                    $statusClass = isset($statusLabels[$status])
                        ? $status
                        : 'cancelled';
                    $statusLabel = $statusLabels[$status] ?? 'Unknown';
                ?>

                <article class="task-card">

                    <div class="task-top">
                        <div>
                            <h2 class="task-title">
                                <a href="task-details.php?id=<?= (int)$task['id'] ?>">
                                    <?= my_tasks_e($task['title']) ?>
                                </a>
                            </h2>

                            <div class="category">
                                <?= my_tasks_e($task['category_name'] ?? 'Uncategorized') ?>
                                · Task #<?= (int)$task['id'] ?>
                            </div>
                        </div>

                        <span class="status status-<?= my_tasks_e($statusClass) ?>">
                            <?= my_tasks_e($statusLabel) ?>
                        </span>
                    </div>

                    <p class="description"><?=
                        my_tasks_e($task['description'])
                    ?></p>

                    <div class="meta">
                        <span>📍 <strong>Location:</strong>
                            <?= my_tasks_e($task['location']) ?>
                        </span>

                        <span>💰 <strong>Budget:</strong>
                            <?php if ($task['budget'] !== null): ?>
                                ৳<?= number_format((float)$task['budget'], 2) ?>
                            <?php else: ?>
                                Not specified
                            <?php endif; ?>
                        </span>

                        <span>📅 <strong>Posted:</strong>
                            <?= my_tasks_e(date(
                                'd M Y',
                                strtotime($task['created_at'])
                            )) ?>
                        </span>
                    </div>

                    <div class="actions">
                        <a
                            href="task-details.php?id=<?= (int)$task['id'] ?>"
                            class="details-btn"
                        >View Details →</a>

                        <?php if ($status === 'posted'): ?>
                            <form
                                method="POST"
                                action="my-tasks.php"
                                class="cancel-form"
                                onsubmit="return confirm('Are you sure you want to cancel this task?');"
                            >
                                <input type="hidden" name="csrf_token"
                                    value="<?= my_tasks_e($_SESSION['my_tasks_token']) ?>">

                                <input type="hidden" name="task_id"
                                    value="<?= (int)$task['id'] ?>">

                                <button type="submit" class="cancel-btn">
                                    Cancel Task
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                </article>
            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<footer>© <?= date('Y') ?> TaskMate. Connecting people with the right help.</footer>

</body>
</html>
