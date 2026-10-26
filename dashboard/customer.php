
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'customer') {
    $role = $_SESSION['role'] ?? '';

    if (in_array($role, ['helper', 'admin'], true)) {
        header('Location: /dashboard/' . $role . '.php');
    } else {
        header('Location: /login.php');
    }
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? 'Customer';

function customerDashboardCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

$totalTasks = customerDashboardCount(
    $pdo,
    'SELECT COUNT(*) FROM tasks WHERE customer_id = ?',
    [$userId]
);

$openTasks = customerDashboardCount(
    $pdo,
    "SELECT COUNT(*) FROM tasks WHERE customer_id = ? AND status = 'posted'",
    [$userId]
);

$activeTasks = customerDashboardCount(
    $pdo,
    "SELECT COUNT(*) FROM tasks WHERE customer_id = ? AND status IN ('assigned', 'in_progress', 'completion_requested')",
    [$userId]
);

$completedTasks = customerDashboardCount(
    $pdo,
    "SELECT COUNT(*) FROM tasks WHERE customer_id = ? AND status = 'completed'",
    [$userId]
);

$stmt = $pdo->prepare(
    "SELECT t.id, t.title, t.location, t.budget, t.status, t.created_at,
            c.name AS category_name,
            (SELECT COUNT(*) FROM offers o WHERE o.task_id = t.id) AS offer_count
     FROM tasks t
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.customer_id = ?
     ORDER BY t.created_at DESC
     LIMIT 6"
);
$stmt->execute([$userId]);
$recentTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Customer Dashboard | TaskMate';
require_once __DIR__ . '/../includes/header.php';

function customerDashboardEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function customerDashboardStatus(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}
?>

<style>
.customer-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 35px 22px 60px;
    color: #24352b;
}
.customer-hero {
    padding: 34px;
    border-radius: 22px;
    background: linear-gradient(125deg, #116b3b, #20a15c);
    color: white;
    margin-bottom: 28px;
}
.customer-hero h1 { margin: 0 0 10px; font-size: 30px; }
.customer-hero p { margin: 0; line-height: 1.7; opacity: .92; }
.customer-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 30px;
}
.customer-stat, .customer-panel, .customer-action {
    background: white;
    border: 1px solid #e5ece7;
    border-radius: 16px;
    padding: 23px;
    box-shadow: 0 5px 18px rgba(20, 50, 35, .04);
}
.customer-stat span { color: #64756a; font-size: 14px; }
.customer-stat strong { display: block; font-size: 30px; margin-top: 10px; }
.customer-section-title { margin: 0 0 18px; font-size: 21px; }
.customer-actions {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 30px;
}
.customer-action { text-decoration: none; color: inherit; transition: .2s; }
.customer-action:hover { transform: translateY(-3px); border-color: #1b9655; }
.customer-action strong { display: block; margin-bottom: 8px; color: #16834a; }
.customer-action span { font-size: 14px; color: #64756a; line-height: 1.6; }
.customer-table-wrap { overflow-x: auto; }
.customer-table { width: 100%; border-collapse: collapse; min-width: 650px; }
.customer-table th, .customer-table td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid #edf1ee;
    font-size: 14px;
}
.customer-table th { color: #64756a; font-weight: 600; }
.customer-table a { color: #16834a; font-weight: 700; text-decoration: none; }
.customer-status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #edf5ef;
    font-size: 12px;
}
.customer-empty { padding: 25px 0; color: #64756a; }
@media (max-width: 850px) {
    .customer-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .customer-actions { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .customer-dashboard { padding: 24px 14px 40px; }
    .customer-hero { padding: 25px 20px; }
    .customer-hero h1 { font-size: 25px; }
}
</style>

<main class="customer-dashboard">

    <section class="customer-hero">
        <h1>Welcome back, <?= customerDashboardEscape($userName) ?>!</h1>
        <p>Manage your tasks, review offers, and keep track of your services from one place.</p>
    </section>

    <section class="customer-stats">
        <div class="customer-stat">
            <span>Total Tasks</span>
            <strong><?= $totalTasks ?></strong>
        </div>
        <div class="customer-stat">
            <span>Open Tasks</span>
            <strong><?= $openTasks ?></strong>
        </div>
        <div class="customer-stat">
            <span>Active Tasks</span>
            <strong><?= $activeTasks ?></strong>
        </div>
        <div class="customer-stat">
            <span>Completed Tasks</span>
            <strong><?= $completedTasks ?></strong>
        </div>
    </section>

    <h2 class="customer-section-title">Quick Actions</h2>

    <section class="customer-actions">
        <a class="customer-action" href="/pages/post-task.php">
            <strong>+ Post a Task</strong>
            <span>Describe the service you need and receive offers from helpers.</span>
        </a>

        <a class="customer-action" href="/pages/my-tasks.php">
            <strong>My Tasks</strong>
            <span>View your tasks, offers, and current task progress.</span>
        </a>

        <a class="customer-action" href="/pages/profile.php">
            <strong>My Profile</strong>
            <span>View and manage your account information.</span>
        </a>
    </section>

    <section class="customer-panel">
        <h2 class="customer-section-title">Recent Tasks</h2>

        <div class="customer-table-wrap">
            <table class="customer-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Category</th>
                        <th>Budget</th>
                        <th>Offers</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recentTasks): ?>
                    <?php foreach ($recentTasks as $task): ?>
                        <tr>
                            <td><?= customerDashboardEscape($task['title']) ?></td>
                            <td><?= customerDashboardEscape($task['category_name'] ?: 'General') ?></td>
                            <td>৳<?= number_format((float) $task['budget'], 2) ?></td>
                            <td><?= (int) $task['offer_count'] ?></td>
                            <td>
                                <span class="customer-status">
                                    <?= customerDashboardEscape(customerDashboardStatus($task['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a href="/pages/task-details.php?id=<?= (int) $task['id'] ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="customer-empty">
                            You haven't posted any tasks yet.
                            <a href="/pages/post-task.php">Post your first task</a>.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
