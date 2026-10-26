
<?php
$pageTitle = 'Assigned Tasks | TaskMate';

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'helper') {
    http_response_code(403);
    exit('Only helpers can access this page. <a href="/index.php">Go Home</a>');
}

$helperId = (int) $_SESSION['user_id'];

$sql = "
    SELECT
        t.id,
        t.title,
        t.description,
        t.location,
        t.budget,
        t.status AS task_status,
        t.created_at,
        c.name AS category_name,
        u.full_name AS customer_name,
        o.amount AS agreed_amount,
        o.status AS offer_status
    FROM offers o
    INNER JOIN tasks t ON t.id = o.task_id
    INNER JOIN users u ON u.id = t.customer_id
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE o.helper_id = :helper_id
      AND o.status = 'accepted'
      AND t.status IN ('assigned', 'in_progress', 'completed')
    ORDER BY
        CASE t.status
            WHEN 'in_progress' THEN 1
            WHEN 'assigned' THEN 2
            WHEN 'completed' THEN 3
            ELSE 4
        END,
        t.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['helper_id' => $helperId]);
$tasks = $stmt->fetchAll();

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function taskStatusLabel(string $status): string
{
    return match ($status) {
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

$assignedCount = 0;
$progressCount = 0;
$completedCount = 0;

foreach ($tasks as $task) {
    if ($task['task_status'] === 'assigned') {
        $assignedCount++;
    } elseif ($task['task_status'] === 'in_progress') {
        $progressCount++;
    } elseif ($task['task_status'] === 'completed') {
        $completedCount++;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .assigned-page {
        max-width: 1180px;
        margin: 0 auto;
        padding: 42px 22px 70px;
        color: #193327;
    }

    .assigned-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 18px;
        margin-bottom: 28px;
    }

    .assigned-heading h1 {
        margin: 0 0 8px;
        font-size: clamp(28px, 4vw, 36px);
        font-weight: 800;
    }

    .assigned-heading p {
        margin: 0;
        color: #708075;
    }

    .assigned-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 30px;
    }

    .assigned-stat {
        background: #fff;
        border: 1px solid #e0eae3;
        border-radius: 16px;
        padding: 23px;
    }

    .assigned-stat span {
        display: block;
        color: #708075;
        font-size: 14px;
        margin-bottom: 10px;
    }

    .assigned-stat strong {
        font-size: 30px;
        color: #16834a;
    }

    .assigned-list {
        display: grid;
        gap: 18px;
    }

    .assigned-card {
        background: #fff;
        border: 1px solid #e0eae3;
        border-radius: 17px;
        padding: 25px;
        box-shadow: 0 5px 18px rgba(20, 50, 35, .035);
    }

    .assigned-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .assigned-category {
        display: inline-block;
        background: #eaf6ee;
        color: #16834a;
        border-radius: 20px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 12px;
    }

    .assigned-card h2 {
        font-size: 23px;
        line-height: 1.35;
        margin: 0;
        overflow-wrap: anywhere;
    }

    .assigned-status {
        display: inline-block;
        border-radius: 20px;
        padding: 8px 13px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    .status-assigned {
        background: #e8efff;
        color: #2857b8;
    }

    .status-progress {
        background: #fff0d8;
        color: #9b6100;
    }

    .status-completed {
        background: #e4f7e9;
        color: #16834a;
    }

    .assigned-description {
        color: #637468;
        line-height: 1.7;
        margin: 15px 0 20px;
        overflow-wrap: anywhere;
    }

    .assigned-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 15px;
        border-top: 1px solid #edf1ee;
        padding-top: 20px;
    }

    .assigned-detail span {
        display: block;
        font-size: 13px;
        color: #78877d;
        margin-bottom: 5px;
    }

    .assigned-detail strong {
        font-size: 15px;
        overflow-wrap: anywhere;
    }

    .assigned-price {
        color: #16834a;
        font-size: 21px !important;
    }

    .assigned-card-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 23px;
    }

    .assigned-date {
        color: #7b887f;
        font-size: 13px;
    }

    .assigned-view-btn {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        background: #16834a;
        color: #fff;
        text-decoration: none;
        padding: 12px 19px;
        border-radius: 9px;
        font-weight: 700;
        font-size: 14px;
        transition: background .2s ease;
    }

    .assigned-view-btn:hover {
        background: #116b3b;
        color: #fff;
    }

    .assigned-empty {
        text-align: center;
        background: #fff;
        border: 1px dashed #cbd9ce;
        border-radius: 17px;
        padding: 55px 24px;
    }

    .assigned-empty h2 {
        margin: 0 0 10px;
    }

    .assigned-empty p {
        color: #708075;
        margin: 0 0 22px;
        line-height: 1.7;
    }

    @media (max-width: 650px) {
        .assigned-page {
            padding: 28px 15px 50px;
        }

        .assigned-stats {
            gap: 10px;
        }

        .assigned-stat {
            padding: 15px;
        }

        .assigned-stat strong {
            font-size: 24px;
        }

        .assigned-stat span {
            font-size: 12px;
        }

        .assigned-card {
            padding: 19px;
        }

        .assigned-card h2 {
            font-size: 20px;
        }

        .assigned-details {
            grid-template-columns: 1fr;
        }

        .assigned-card-bottom,
        .assigned-view-btn {
            width: 100%;
        }
    }
</style>

<main class="assigned-page">

    <div class="assigned-heading">
        <div>
            <h1>My Assigned Tasks</h1>
            <p>Manage your accepted jobs and track your progress.</p>
        </div>

        <a href="/pages/browse.php" class="assigned-view-btn">
            Browse More Tasks
        </a>
    </div>

    <section class="assigned-stats">
        <div class="assigned-stat">
            <span>Assigned</span>
            <strong><?= $assignedCount ?></strong>
        </div>

        <div class="assigned-stat">
            <span>In Progress</span>
            <strong><?= $progressCount ?></strong>
        </div>

        <div class="assigned-stat">
            <span>Completed</span>
            <strong><?= $completedCount ?></strong>
        </div>
    </section>

    <?php if (empty($tasks)): ?>

        <section class="assigned-empty">
            <h2>No assigned tasks yet</h2>
            <p>
                When a customer accepts one of your offers,
                that task will appear here.
            </p>

            <a href="/pages/browse.php" class="assigned-view-btn">
                Browse Available Tasks
            </a>
        </section>

    <?php else: ?>

        <section class="assigned-list">

            <?php foreach ($tasks as $task): ?>

                <?php
                    $statusClass = match ($task['task_status']) {
                        'assigned' => 'status-assigned',
                        'in_progress' => 'status-progress',
                        'completed' => 'status-completed',
                        default => 'status-assigned',
                    };
                ?>

                <article class="assigned-card">

                    <div class="assigned-card-top">
                        <div>
                            <span class="assigned-category">
                                <?= e($task['category_name'] ?: 'General Task') ?>
                            </span>

                            <h2><?= e($task['title']) ?></h2>
                        </div>

                        <span class="assigned-status <?= $statusClass ?>">
                            <?= e(taskStatusLabel($task['task_status'])) ?>
                        </span>
                    </div>

                    <p class="assigned-description">
                        <?= nl2br(e($task['description'])) ?>
                    </p>

                    <div class="assigned-details">

                        <div class="assigned-detail">
                            <span>Customer</span>
                            <strong><?= e($task['customer_name']) ?></strong>
                        </div>

                        <div class="assigned-detail">
                            <span>Location</span>
                            <strong><?= e($task['location']) ?></strong>
                        </div>

                        <div class="assigned-detail">
                            <span>Agreed Price</span>
                            <strong class="assigned-price">
                                ৳<?= number_format((float)$task['agreed_amount'], 2) ?>
                            </strong>
                        </div>

                        <div class="assigned-detail">
                            <span>Task Budget</span>
                            <strong>
                                <?= $task['budget'] !== null
                                    ? '৳' . number_format((float)$task['budget'], 2)
                                    : 'Not specified' ?>
                            </strong>
                        </div>

                    </div>

                    <div class="assigned-card-bottom">
                        <span class="assigned-date">
                            Posted on <?= date('d M Y', strtotime($task['created_at'])) ?>
                        </span>

                        <a
                            href="/pages/task-details.php?id=<?= (int)$task['id'] ?>"
                            class="assigned-view-btn"
                        >
                            View Task &amp; Update Progress →
                        </a>
                    </div>

                </article>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
