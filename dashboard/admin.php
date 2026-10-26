
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    $role = $_SESSION['role'] ?? '';

    if (in_array($role, ['customer', 'helper'], true)) {
        header('Location: /dashboard/' . $role . '.php');
    } else {
        header('Location: /login.php');
    }
    exit;
}

require_once __DIR__ . '/../config/database.php';

function adminDashboardCount(PDO $pdo, string $sql): int
{
    return (int) $pdo->query($sql)->fetchColumn();
}

$totalUsers = adminDashboardCount($pdo, 'SELECT COUNT(*) FROM users');
$totalCustomers = adminDashboardCount($pdo, "SELECT COUNT(*) FROM users WHERE role = 'customer'");
$totalHelpers = adminDashboardCount($pdo, "SELECT COUNT(*) FROM users WHERE role = 'helper'");
$totalTasks = adminDashboardCount($pdo, 'SELECT COUNT(*) FROM tasks');
$totalOffers = adminDashboardCount($pdo, 'SELECT COUNT(*) FROM offers');

$attentionTasks = adminDashboardCount(
    $pdo,
    "SELECT COUNT(*) FROM tasks WHERE status IN ('disputed', 'completion_requested')"
);

$stmt = $pdo->query(
    "SELECT t.id, t.title, t.status, t.budget, t.created_at,
            u.full_name AS customer_name,
            c.name AS category_name,
            (SELECT COUNT(*) FROM offers o WHERE o.task_id = t.id) AS offer_count
     FROM tasks t
     LEFT JOIN users u ON u.id = t.customer_id
     LEFT JOIN categories c ON c.id = t.category_id
     ORDER BY t.created_at DESC
     LIMIT 10"
);

$recentTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Admin Dashboard | TaskMate';
require_once __DIR__ . '/../includes/header.php';

function adminDashboardEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function adminDashboardStatus(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}
?>

<style>
.admin-dashboard {
    max-width: 1250px;
    margin: 0 auto;
    padding: 35px 22px 60px;
    color: #24352b;
}
.admin-hero {
    padding: 34px;
    border-radius: 22px;
    background: linear-gradient(125deg, #173c2a, #16834a);
    color: white;
    margin-bottom: 28px;
}
.admin-hero h1 { margin: 0 0 10px; font-size: 30px; }
.admin-hero p { margin: 0; line-height: 1.7; opacity: .92; }
.admin-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 30px;
}
.admin-stat, .admin-panel, .admin-action {
    background: white;
    border: 1px solid #e5ece7;
    border-radius: 16px;
    padding: 23px;
    box-shadow: 0 5px 18px rgba(20, 50, 35, .04);
}
.admin-stat span { color: #64756a; font-size: 14px; }
.admin-stat strong { display: block; font-size: 30px; margin-top: 10px; }
.admin-stat.warning strong { color: #c0392b; }
.admin-section-title { margin: 0 0 18px; font-size: 21px; }
.admin-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 30px;
}
.admin-action { text-decoration: none; color: inherit; transition: .2s; }
.admin-action:hover { transform: translateY(-3px); border-color: #16834a; }
.admin-action strong { display: block; margin-bottom: 8px; color: #16834a; }
.admin-action span { font-size: 14px; color: #64756a; line-height: 1.6; }
.admin-table-wrap { overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; min-width: 750px; }
.admin-table th, .admin-table td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid #edf1ee;
    font-size: 14px;
}
.admin-table th { color: #64756a; font-weight: 600; }
.admin-table a { color: #16834a; font-weight: 700; text-decoration: none; }
.admin-status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #edf5ef;
    font-size: 12px;
}
.admin-empty { padding: 25px 0; color: #64756a; }
@media (max-width: 850px) {
    .admin-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .admin-actions { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .admin-dashboard { padding: 24px 14px 40px; }
    .admin-hero { padding: 25px 20px; }
    .admin-hero h1 { font-size: 25px; }
}
</style>

<main class="admin-dashboard">

    <section class="admin-hero">
        <h1>Admin Dashboard</h1>
        <p>Welcome, <?= adminDashboardEscape($_SESSION['full_name'] ?? 'Administrator') ?>. Monitor users, tasks, offers, and platform activity.</p>
    </section>

    <section class="admin-stats">
        <div class="admin-stat">
            <span>Total Users</span>
            <strong><?= $totalUsers ?></strong>
        </div>
        <div class="admin-stat">
            <span>Total Customers</span>
            <strong><?= $totalCustomers ?></strong>
        </div>
        <div class="admin-stat">
            <span>Total Helpers</span>
            <strong><?= $totalHelpers ?></strong>
        </div>
        <div class="admin-stat">
            <span>Total Tasks</span>
            <strong><?= $totalTasks ?></strong>
        </div>
        <div class="admin-stat">
            <span>Total Offers</span>
            <strong><?= $totalOffers ?></strong>
        </div>
        <div class="admin-stat warning">
            <span>Tasks Needing Attention</span>
            <strong><?= $attentionTasks ?></strong>
        </div>
    </section>

    <h2 class="admin-section-title">Quick Actions</h2>

    <section class="admin-actions">
        <a class="admin-action" href="/pages/browse.php">
            <strong>Browse Tasks</strong>
            <span>Review tasks currently listed on the platform.</span>
        </a>

        <a class="admin-action" href="/pages/profile.php">
            <strong>My Profile</strong>
            <span>View your administrator account information.</span>
        </a>
    </section>

    <section class="admin-panel">
        <h2 class="admin-section-title">Recent Tasks</h2>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Customer</th>
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
                            <td><?= adminDashboardEscape($task['title']) ?></td>
                            <td><?= adminDashboardEscape($task['customer_name'] ?: 'Unknown') ?></td>
                            <td><?= adminDashboardEscape($task['category_name'] ?: 'General') ?></td>
                            <td>৳<?= number_format((float) $task['budget'], 2) ?></td>
                            <td><?= (int) $task['offer_count'] ?></td>
                            <td>
                                <span class="admin-status">
                                    <?= adminDashboardEscape(adminDashboardStatus($task['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a href="/pages/task-details.php?id=<?= (int) $task['id'] ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="admin-empty">No tasks found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
