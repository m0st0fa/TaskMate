
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'helper') {
    $role = $_SESSION['role'] ?? '';

    if (in_array($role, ['customer', 'admin'], true)) {
        header('Location: /dashboard/' . $role . '.php');
    } else {
        header('Location: /login.php');
    }
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? 'Helper';

function helperDashboardCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

$pendingOffers = helperDashboardCount(
    $pdo,
    "SELECT COUNT(*) FROM offers WHERE helper_id = ? AND status = 'pending'",
    [$userId]
);

$acceptedOffers = helperDashboardCount(
    $pdo,
    "SELECT COUNT(*) FROM offers WHERE helper_id = ? AND status = 'accepted'",
    [$userId]
);

$completedTasks = helperDashboardCount(
    $pdo,
    "SELECT COUNT(DISTINCT t.id)
     FROM tasks t
     INNER JOIN offers o ON o.task_id = t.id
     WHERE o.helper_id = ? AND o.status = 'accepted'
     AND t.status = 'completed'",
    [$userId]
);

$totalOffers = helperDashboardCount(
    $pdo,
    "SELECT COUNT(*) FROM offers WHERE helper_id = ?",
    [$userId]
);

$stmt = $pdo->prepare(
    "SELECT o.id AS offer_id, o.amount, o.status AS offer_status,
            o.created_at AS offer_created_at,
            t.id AS task_id, t.title, t.location, t.budget,
            t.status AS task_status,
            c.name AS category_name
     FROM offers o
     INNER JOIN tasks t ON t.id = o.task_id
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE o.helper_id = ?
     ORDER BY o.created_at DESC
     LIMIT 8"
);
$stmt->execute([$userId]);
$myOffers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Helper Dashboard | TaskMate';
require_once __DIR__ . '/../includes/header.php';

function helperDashboardEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function helperDashboardStatus(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}
?>

<style>
.helper-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 35px 22px 60px;
    color: #24352b;
}
.helper-hero {
    padding: 34px;
    border-radius: 22px;
    background: linear-gradient(125deg, #116b3b, #20a15c);
    color: white;
    margin-bottom: 28px;
}
.helper-hero h1 { margin: 0 0 10px; font-size: 30px; }
.helper-hero p { margin: 0; line-height: 1.7; opacity: .92; }
.helper-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 30px;
}
.helper-stat, .helper-panel, .helper-action {
    background: white;
    border: 1px solid #e5ece7;
    border-radius: 16px;
    padding: 23px;
    box-shadow: 0 5px 18px rgba(20, 50, 35, .04);
}
.helper-stat span { color: #64756a; font-size: 14px; }
.helper-stat strong { display: block; font-size: 30px; margin-top: 10px; }
.helper-section-title { margin: 0 0 18px; font-size: 21px; }
.helper-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 30px;
}
.helper-action { text-decoration: none; color: inherit; transition: .2s; }
.helper-action:hover { transform: translateY(-3px); border-color: #1b9655; }
.helper-action strong { display: block; margin-bottom: 8px; color: #16834a; }
.helper-action span { font-size: 14px; color: #64756a; line-height: 1.6; }
.helper-table-wrap { overflow-x: auto; }
.helper-table { width: 100%; border-collapse: collapse; min-width: 680px; }
.helper-table th, .helper-table td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid #edf1ee;
    font-size: 14px;
}
.helper-table th { color: #64756a; font-weight: 600; }
.helper-table a { color: #16834a; font-weight: 700; text-decoration: none; }
.helper-status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #edf5ef;
    font-size: 12px;
}
.helper-empty { padding: 25px 0; color: #64756a; }
@media (max-width: 850px) {
    .helper-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .helper-actions { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .helper-dashboard { padding: 24px 14px 40px; }
    .helper-hero { padding: 25px 20px; }
    .helper-hero h1 { font-size: 25px; }
}
</style>

<main class="helper-dashboard">

    <section class="helper-hero">
        <h1>Welcome back, <?= helperDashboardEscape($userName) ?>!</h1>
        <p>Discover tasks, manage your offers, and keep track of your work in one place.</p>
    </section>

    <section class="helper-stats">
        <div class="helper-stat">
            <span>Total Offers</span>
            <strong><?= $totalOffers ?></strong>
        </div>
        <div class="helper-stat">
            <span>Pending Offers</span>
            <strong><?= $pendingOffers ?></strong>
        </div>
        <div class="helper-stat">
            <span>Accepted Offers</span>
            <strong><?= $acceptedOffers ?></strong>
        </div>
        <div class="helper-stat">
            <span>Completed Tasks</span>
            <strong><?= $completedTasks ?></strong>
        </div>
    </section>

    <h2 class="helper-section-title">Quick Actions</h2>

    <section class="helper-actions">
        <a class="helper-action" href="/pages/browse.php">
            <strong>Browse Available Tasks</strong>
            <span>Find tasks that match your skills and submit an offer.</span>
        </a>

        <a class="helper-action" href="/pages/assigned-tasks.php">
            <strong>My Assigned Tasks</strong>
            <span>View and manage tasks assigned to you.</span>
        </a>
    </section>

    <section class="helper-panel">
        <h2 class="helper-section-title">My Recent Offers</h2>

        <div class="helper-table-wrap">
            <table class="helper-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Category</th>
                        <th>My Offer</th>
                        <th>Offer Status</th>
                        <th>Task Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($myOffers): ?>
                    <?php foreach ($myOffers as $offer): ?>
                        <tr>
                            <td><?= helperDashboardEscape($offer['title']) ?></td>
                            <td><?= helperDashboardEscape($offer['category_name'] ?: 'General') ?></td>
                            <td>৳<?= number_format((float) $offer['amount'], 2) ?></td>
                            <td>
                                <span class="helper-status">
                                    <?= helperDashboardEscape(helperDashboardStatus($offer['offer_status'])) ?>
                                </span>
                            </td>
                            <td><?= helperDashboardEscape(helperDashboardStatus($offer['task_status'])) ?></td>
                            <td>
                                <a href="/pages/task-details.php?id=<?= (int) $offer['task_id'] ?>">View Task</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="helper-empty">
                            You haven't submitted any offers yet.
                            <a href="/pages/browse.php">Browse tasks</a> to get started.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
