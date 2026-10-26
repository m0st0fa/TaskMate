
<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'customer';
$taskId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$taskId) {
    http_response_code(400);
    exit('Invalid task ID. <a href="/pages/browse.php">Browse Tasks</a>');
}

if (empty($_SESSION['task_details_token'])) {
    $_SESSION['task_details_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['task_details_token'];

function td_e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function td_redirect(int $id, string $message): never
{
    $_SESSION['task_details_flash'] = $message;
    header('Location: /pages/task-details.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)) {
        http_response_code(403);
        exit('Security token invalid. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare(
            'SELECT * FROM tasks WHERE id = ? FOR UPDATE'
        );
        $lock->execute([$taskId]);
        $lockedTask = $lock->fetch();

        if (!$lockedTask) {
            throw new RuntimeException('Task not found.');
        }

        $isOwner = (int)$lockedTask['customer_id'] === $userId;

        $acceptedOfferStmt = $pdo->prepare(
            "SELECT * FROM offers
             WHERE task_id = ? AND status = 'accepted'
             LIMIT 1"
        );
        $acceptedOfferStmt->execute([$taskId]);
        $acceptedOffer = $acceptedOfferStmt->fetch();

        switch ($action) {
            case 'submit_completion':
                if ($role !== 'helper' ||
                    !$acceptedOffer ||
                    (int)$acceptedOffer['helper_id'] !== $userId) {
                    throw new RuntimeException(
                        'Only the assigned helper can submit this task for verification.'
                    );
                }

                if ($lockedTask['status'] !== 'in_progress') {
                    throw new RuntimeException(
                        'The task must be in progress before submitting completion.'
                    );
                }

                $note = trim((string)($_POST['completion_note'] ?? ''));

                if (mb_strlen($note) > 2000) {
                    throw new RuntimeException(
                        'Completion note must be 2000 characters or fewer.'
                    );
                }

                $stmt = $pdo->prepare(
                    "UPDATE tasks
                     SET status = 'completion_requested',
                         completion_note = ?,
                         completion_requested_at = NOW(),
                         dispute_reason = NULL,
                         disputed_at = NULL
                     WHERE id = ? AND status = 'in_progress'"
                );
                $stmt->execute([$note !== '' ? $note : null, $taskId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Task status changed. Refresh and try again.');
                }

                $flash = 'Completion submitted. Wait for the customer to verify the work.';
                break;

            case 'confirm_completion':
                if (!$isOwner || $role !== 'customer') {
                    throw new RuntimeException(
                        'Only the customer who posted this task can confirm completion.'
                    );
                }

                if ($lockedTask['status'] !== 'completion_requested') {
                    throw new RuntimeException(
                        'There is no completion request awaiting confirmation.'
                    );
                }

                if (!$acceptedOffer) {
                    throw new RuntimeException('No assigned helper was found.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE tasks
                     SET status = 'completed',
                         completed_at = NOW()
                     WHERE id = ? AND customer_id = ?
                       AND status = 'completion_requested'"
                );
                $stmt->execute([$taskId, $userId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Could not confirm completion. Refresh and try again.');
                }

                $flash = 'Completion confirmed. The task is now officially completed.';
                break;

            case 'report_problem':
                if (!$isOwner || $role !== 'customer') {
                    throw new RuntimeException(
                        'Only the task owner can report a problem.'
                    );
                }

                if ($lockedTask['status'] !== 'completion_requested') {
                    throw new RuntimeException(
                        'A problem can only be reported while completion is awaiting verification.'
                    );
                }

                $reason = trim((string)($_POST['dispute_reason'] ?? ''));

                if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
                    throw new RuntimeException(
                        'Please describe the problem in 5–2000 characters.'
                    );
                }

                $stmt = $pdo->prepare(
                    "UPDATE tasks
                     SET status = 'disputed',
                         dispute_reason = ?,
                         disputed_at = NOW()
                     WHERE id = ? AND customer_id = ?
                       AND status = 'completion_requested'"
                );
                $stmt->execute([$reason, $taskId, $userId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Could not report the problem. Refresh and try again.');
                }

                $flash = 'Problem reported. The task is now marked for review.';
                break;

            case 'start_task':
                if ($role !== 'helper' ||
                    !$acceptedOffer ||
                    (int)$acceptedOffer['helper_id'] !== $userId) {
                    throw new RuntimeException(
                        'Only the assigned helper can start this task.'
                    );
                }

                if ($lockedTask['status'] !== 'assigned') {
                    throw new RuntimeException('This task cannot be started in its current status.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE tasks SET status = 'in_progress'
                     WHERE id = ? AND status = 'assigned'"
                );
                $stmt->execute([$taskId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Could not start task. Refresh and try again.');
                }

                $flash = 'Task started. Remember to submit it for customer verification when finished.';
                break;

            case 'accept_offer':
            case 'reject_offer':
                if ($role !== 'customer' || !$isOwner) {
                    throw new RuntimeException(
                        'Only the customer who posted this task can manage offers.'
                    );
                }

                if ($lockedTask['status'] !== 'posted') {
                    throw new RuntimeException(
                        'Offers can only be managed while the task is posted.'
                    );
                }

                $offerId = filter_input(INPUT_POST, 'offer_id', FILTER_VALIDATE_INT);

                if (!$offerId) {
                    throw new RuntimeException('Invalid offer.');
                }

                $offerStmt = $pdo->prepare(
                    "SELECT * FROM offers
                     WHERE id = ? AND task_id = ? FOR UPDATE"
                );
                $offerStmt->execute([$offerId, $taskId]);
                $offer = $offerStmt->fetch();

                if (!$offer || $offer['status'] !== 'pending') {
                    throw new RuntimeException('This offer is no longer pending.');
                }

                if ($action === 'reject_offer') {
                    $stmt = $pdo->prepare(
                        "UPDATE offers SET status = 'rejected'
                         WHERE id = ? AND status = 'pending'"
                    );
                    $stmt->execute([$offerId]);

                    $flash = 'Offer rejected.';
                    break;
                }

                $stmt = $pdo->prepare(
                    "UPDATE offers SET status = 'accepted'
                     WHERE id = ? AND status = 'pending'"
                );
                $stmt->execute([$offerId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Could not accept this offer.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE offers SET status = 'rejected'
                     WHERE task_id = ? AND id <> ? AND status = 'pending'"
                );
                $stmt->execute([$taskId, $offerId]);

                $stmt = $pdo->prepare(
                    "UPDATE tasks SET status = 'assigned'
                     WHERE id = ? AND status = 'posted'"
                );
                $stmt->execute([$taskId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Could not assign the task.');
                }

                $flash = 'Offer accepted. The task is now assigned to the helper.';
                break;

            case 'submit_offer':
                if ($role !== 'helper') {
                    throw new RuntimeException('Only helpers can submit offers.');
                }

                if ($lockedTask['status'] !== 'posted') {
                    throw new RuntimeException('This task is no longer accepting offers.');
                }

                if ((int)$lockedTask['customer_id'] === $userId) {
                    throw new RuntimeException('You cannot offer on your own task.');
                }

                $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
                $message = trim((string)($_POST['message'] ?? ''));

                if ($amount === false || $amount === null ||
                    $amount <= 0 || $amount > 99999999) {
                    throw new RuntimeException('Enter a valid offer amount.');
                }

                if (mb_strlen($message) > 2000) {
                    throw new RuntimeException('Offer message is too long.');
                }

                $check = $pdo->prepare(
                    'SELECT id FROM offers WHERE task_id = ? AND helper_id = ? LIMIT 1'
                );
                $check->execute([$taskId, $userId]);

                if ($check->fetch()) {
                    throw new RuntimeException('You have already submitted an offer for this task.');
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO offers (task_id, helper_id, amount, message)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $taskId,
                    $userId,
                    $amount,
                    $message !== '' ? $message : null
                ]);

                $flash = 'Your offer has been submitted.';
                break;

            default:
                throw new RuntimeException('Unknown action.');
        }

        $pdo->commit();
        td_redirect($taskId, $flash);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException) {
            error_log('TaskMate task-details database error: ' . $e->getMessage());
            td_redirect($taskId, 'A database error occurred. Please try again.');
        }

        td_redirect($taskId, $e->getMessage());
    }
}

/* Fetch task details */
$stmt = $pdo->prepare(
    "SELECT t.*, c.name AS category_name, u.full_name AS customer_name
     FROM tasks t
     LEFT JOIN categories c ON c.id = t.category_id
     INNER JOIN users u ON u.id = t.customer_id
     WHERE t.id = ?"
);
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    http_response_code(404);
    exit('Task not found. <a href="/pages/browse.php">Browse Tasks</a>');
}

$isOwner = (int)$task['customer_id'] === $userId;

$stmt = $pdo->prepare(
    "SELECT o.*, u.full_name AS helper_name
     FROM offers o
     INNER JOIN users u ON u.id = o.helper_id
     WHERE o.task_id = ?
     ORDER BY o.created_at DESC"
);
$stmt->execute([$taskId]);
$allOffers = $stmt->fetchAll();

$myOffer = null;
$acceptedOffer = null;
$receivedOffers = [];

foreach ($allOffers as $offer) {
    if ((int)$offer['helper_id'] === $userId) {
        $myOffer = $offer;
    }

    if ($offer['status'] === 'accepted') {
        $acceptedOffer = $offer;
    }

    if ($isOwner) {
        $receivedOffers[] = $offer;
    }
}

$isAssignedHelper = $role === 'helper'
    && $acceptedOffer
    && (int)$acceptedOffer['helper_id'] === $userId;

$flash = $_SESSION['task_details_flash'] ?? '';
unset($_SESSION['task_details_flash']);

$pageTitle = $task['title'] . ' | TaskMate';
require_once __DIR__ . '/../includes/header.php';

$statusLabels = [
    'posted' => 'Open for Offers',
    'assigned' => 'Assigned',
    'in_progress' => 'In Progress',
    'completion_requested' => 'Awaiting Customer Verification',
    'completed' => 'Completed',
    'disputed' => 'Under Review',
    'cancelled' => 'Cancelled',
];

$taskStatus = $task['status'];
?>

<style>
.td-page{max-width:1250px;margin:0 auto;padding:42px 20px 70px;color:#193327}
.td-layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,.9fr);gap:26px;align-items:start}
.td-main{display:grid;gap:24px;min-width:0}
.td-card{background:#fff;border:1px solid #e0eae3;border-radius:18px;padding:30px;box-shadow:0 4px 16px #19332708}
.td-category{display:inline-block;background:#e8f6ee;color:#16834a;padding:8px 14px;border-radius:24px;font-size:14px;font-weight:700}
.td-card h1{font-size:clamp(27px,4vw,40px);line-height:1.2;margin:24px 0 16px;overflow-wrap:anywhere}
.td-card h2{font-size:24px;margin:0 0 20px}
.td-status{display:inline-block;border-radius:24px;padding:8px 14px;background:#e8efff;color:#2857b8;font-weight:700;font-size:14px}
.td-status.completed{background:#e4f7e9;color:#16834a}
.td-status.completion_requested{background:#fff0d8;color:#925d00}
.td-status.disputed{background:#ffe8e8;color:#ad3030}
.td-description{margin:25px 0;color:#586c60;line-height:1.8;overflow-wrap:anywhere}
.td-divider{border:0;border-top:1px solid #edf1ee;margin:24px 0}
.td-detail{display:grid;grid-template-columns:110px 1fr;gap:10px;margin:15px 0}
.td-detail strong{overflow-wrap:anywhere}
.td-muted{color:#708075;line-height:1.7}
.td-profile-link{display:inline-block;margin-left:8px;color:#16834a;font-size:13px;font-weight:700;text-decoration:none}
.td-profile-link:hover{text-decoration:underline}
.td-button{display:inline-flex;align-items:center;justify-content:center;padding:12px 17px;border-radius:9px;border:0;background:#16834a;color:white;text-decoration:none;font-weight:700;font-size:14px;cursor:pointer}
.td-button:hover{background:#116b3b;color:white}
.td-button.secondary{background:#f0f4f1;color:#34483b}
.td-button.danger{background:#fff0f0;color:#a12c2c;border:1px solid #f0d0d0}
.td-button:disabled{opacity:.5;cursor:not-allowed}
.td-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.td-form{display:grid;gap:12px;margin-top:16px}
.td-form label{font-size:14px;font-weight:700}
.td-form input,.td-form textarea{width:100%;box-sizing:border-box;border:1px solid #dce6df;border-radius:9px;padding:12px;font:inherit;background:#fff;color:#193327}
.td-form textarea{min-height:100px;resize:vertical}
.td-offer{border:1px solid #e0eae3;border-radius:14px;padding:20px;margin-top:14px}
.td-offer-top{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.td-offer-top strong{font-size:18px}
.td-price{color:#16834a;font-size:22px;font-weight:800}
.td-timeline{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center;margin:26px 0 20px}
.td-step{color:#89958d;font-size:14px;font-weight:600}
.td-step-number{width:46px;height:46px;border-radius:50%;background:#edf1ee;display:grid;place-items:center;margin:0 auto 12px;font-size:16px}
.td-step.active{color:#16834a}
.td-step.active .td-step-number{background:#16834a;color:#fff}
.td-alert{padding:14px 16px;background:#edf8f0;border:1px solid #d3ead9;border-radius:10px;margin-bottom:20px;line-height:1.6}
.td-alert.warning{background:#fff7e8;border-color:#f1dfb6}
.td-alert.danger{background:#fff0f0;border-color:#f0d0d0}
@media(max-width:850px){.td-layout{grid-template-columns:1fr}.td-card{padding:22px}}
@media(max-width:520px){.td-page{padding:24px 13px 50px}.td-detail{grid-template-columns:95px 1fr}.td-timeline{gap:3px}.td-step{font-size:12px}.td-step-number{width:38px;height:38px}}
</style>

<main class="td-page">

    <?php if ($flash !== ''): ?>
        <div class="td-alert"><?= td_e($flash) ?></div>
    <?php endif; ?>

    <div class="td-layout">
        <div class="td-main">

            <section class="td-card">
                <span class="td-category">
                    <?= td_e($task['category_name'] ?: 'General Task') ?>
                </span>

                <h1><?= td_e($task['title']) ?></h1>

                <span class="td-status <?= td_e($taskStatus) ?>">
                    <?= td_e($statusLabels[$taskStatus] ?? $taskStatus) ?>
                </span>

                <p class="td-description"><?= nl2br(td_e($task['description'])) ?></p>

                <hr class="td-divider">

                <div class="td-detail">
                    <strong>Location</strong>
                    <span><?= td_e($task['location']) ?></span>
                </div>

                <div class="td-detail">
                    <strong>Budget</strong>
                    <span>
                        <?= $task['budget'] !== null
                            ? '৳' . number_format((float)$task['budget'], 2) . ' BDT'
                            : 'Not specified' ?>
                    </span>
                </div>

                <div class="td-detail">
                    <strong>Posted by</strong>
                    <span>
                        <?= td_e($task['customer_name']) ?>
                        <a class="td-profile-link" href="/pages/user-profile.php?id=<?= (int)$task['customer_id'] ?>">View Profile →</a>
                    </span>
                </div>

                <div class="td-detail">
                    <strong>Posted on</strong>
                    <span><?= date('d M Y', strtotime($task['created_at'])) ?></span>
                </div>

                <?php if ($acceptedOffer): ?>
                    <?php if ($isOwner && $role === 'customer'): ?>
                        <div class="td-actions">
                            <a class="td-button" href="/pages/chat.php?task_id=<?= (int)$taskId ?>&amp;helper_id=<?= (int)$acceptedOffer['helper_id'] ?>">Message Assigned Helper</a>
                        </div>
                    <?php elseif ($isAssignedHelper): ?>
                        <div class="td-actions">
                            <a class="td-button" href="/pages/chat.php?task_id=<?= (int)$taskId ?>">Message Customer</a>
                        </div>
                    <?php endif; ?>
                    <div class="td-detail">
                        <strong>Assigned helper</strong>
                        <span>
                            <?= td_e($acceptedOffer['helper_name']) ?>
                            <a class="td-profile-link" href="/pages/user-profile.php?id=<?= (int)$acceptedOffer['helper_id'] ?>">View Profile →</a>
                        </span>
                    </div>
                    <div class="td-detail">
                        <strong>Agreed price</strong>
                        <strong style="color:#16834a">
                            ৳<?= number_format((float)$acceptedOffer['amount'], 2) ?>
                        </strong>
                    </div>
                <?php endif; ?>
            </section>

            <section class="td-card">
                <h2>Task Progress</h2>

                <?php
                $steps = [
                    'assigned' => 1,
                    'in_progress' => 2,
                    'completion_requested' => 2,
                    'completed' => 3,
                    'disputed' => 2,
                ];
                $activeStep = $steps[$taskStatus] ?? 0;
                ?>

                <div class="td-timeline">
                    <?php
                    $labels = ['Assigned', 'In Progress', 'Completed'];
                    for ($i = 1; $i <= 3; $i++):
                        $done = $activeStep >= $i && $taskStatus !== 'disputed';
                    ?>
                        <div class="td-step <?= $done ? 'active' : '' ?>">
                            <div class="td-step-number">
                                <?= $done && $i < $activeStep ? '✓' : $i ?>
                            </div>
                            <?= $labels[$i - 1] ?>
                        </div>
                    <?php endfor; ?>
                </div>

                <p class="td-muted">
                    Current status:
                    <strong><?= td_e($statusLabels[$taskStatus] ?? $taskStatus) ?></strong>
                </p>

<?php if ($taskStatus === 'completed'): ?>
    <div class="td-actions">
        <a class="td-button"
           href="/pages/review-task.php?id=<?= (int)$taskId ?>">
            ⭐ Rate Customer / Helper
        </a>
    </div>
<?php endif; ?>


                <?php if ($taskStatus === 'completion_requested'): ?>
                    <div class="td-alert warning">
                        The helper has submitted this task for verification.
                        It is not officially completed until you confirm it.
                    </div>
                <?php elseif ($taskStatus === 'disputed'): ?>
                    <div class="td-alert danger">
                        This task has a reported problem and needs review.
                        <?php if ($isOwner && !empty($task['dispute_reason'])): ?>
                            <p><strong>Customer's report:</strong><br>
                                <?= nl2br(td_e($task['dispute_reason'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($isAssignedHelper && $taskStatus === 'assigned'): ?>
                    <form method="post" class="td-actions">
                        <input type="hidden" name="csrf_token" value="<?= td_e($csrfToken) ?>">
                        <input type="hidden" name="action" value="start_task">
                        <button class="td-button" type="submit">Start Task</button>
                    </form>
                <?php endif; ?>

                <?php if ($isAssignedHelper && $taskStatus === 'in_progress'): ?>
                    <form method="post" class="td-form">
                        <input type="hidden" name="csrf_token" value="<?= td_e($csrfToken) ?>">
                        <input type="hidden" name="action" value="submit_completion">

                        <label for="completion_note">Describe the completed work</label>
                        <textarea
                            id="completion_note"
                            name="completion_note"
                            maxlength="2000"
                            placeholder="Explain what you completed. The customer will review your submission."
                        ></textarea>

                        <button class="td-button" type="submit">
                            Submit for Verification
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($isOwner && $taskStatus === 'completion_requested'): ?>
                    <?php if (!empty($task['completion_note'])): ?>
                        <div class="td-alert">
                            <strong>Helper's completion note</strong><br>
                            <?= nl2br(td_e($task['completion_note'])) ?>
                        </div>
                    <?php endif; ?>

                    <p class="td-muted">
                        Review the work before confirming. If the task is unfinished
                        or incorrect, report the problem instead.
                    </p>

                    <div class="td-actions">
                        <form method="post" onsubmit="return confirm('Have you checked the work and confirmed it is complete?');">
                            <input type="hidden" name="csrf_token" value="<?= td_e($csrfToken) ?>">
                            <input type="hidden" name="action" value="confirm_completion">
                            <button class="td-button" type="submit">Confirm Completion</button>
                        </form>
                    </div>

                    <form method="post" class="td-form">
                        <input type="hidden" name="csrf_token" value="<?= td_e($csrfToken) ?>">
                        <input type="hidden" name="action" value="report_problem">

                        <label for="dispute_reason">Report a problem</label>
                        <textarea
                            id="dispute_reason"
                            name="dispute_reason"
                            minlength="5"
                            maxlength="2000"
                            required
                            placeholder="Explain what is unfinished or wrong..."
                        ></textarea>

                        <button class="td-button danger" type="submit">
                            Report a Problem
                        </button>
                    </form>
                <?php endif; ?>
            </section>

            <?php if ($isOwner): ?>
                <section class="td-card">
                    <h2>Received Offers (<?= count($receivedOffers) ?>)</h2>

                    <?php if (empty($receivedOffers)): ?>
                        <p class="td-muted">No offers have been received yet.</p>
                    <?php else: ?>
                        <?php foreach ($receivedOffers as $offer): ?>
                            <div class="td-offer">
                                <div class="td-offer-top">
                                    <strong><?= td_e($offer['helper_name']) ?></strong>
                                    <a class="td-profile-link" href="/pages/user-profile.php?id=<?= (int)$offer['helper_id'] ?>">View Profile →</a>
                                    <span class="td-price">
                                        ৳<?= number_format((float)$offer['amount'], 2) ?>
                                    </span>
                                </div>

                                <p class="td-muted">
                                    Status: <?= td_e(ucfirst($offer['status'])) ?>
                                    · <?= date('d M Y', strtotime($offer['created_at'])) ?>
                                </p>

                                <?php if (!empty($offer['message'])): ?>
                                    <p><?= nl2br(td_e($offer['message'])) ?></p>
                                <?php endif; ?>

                                <div class="td-actions">
                                    <a class="td-button" href="/pages/chat.php?task_id=<?= (int)$taskId ?>&amp;helper_id=<?= (int)$offer['helper_id'] ?>">Message Helper</a>
                                </div>

                                <?php if ($taskStatus === 'posted' && $offer['status'] === 'pending'): ?>
                                    <div class="td-actions">
                                        <form method="post" onsubmit="return confirm('Accept this offer?');">
                                            <input type="hidden" name="csrf_token" value="<?= td_e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="accept_offer">
                                            <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                                            <button class="td-button" type="submit">Accept Offer</button>
                                        </form>

                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= td_e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="reject_offer">
                                            <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                                            <button class="td-button secondary" type="submit">Reject</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            <?php elseif ($role === 'helper' && !$isAssignedHelper && $taskStatus === 'posted'): ?>
                <section class="td-card">
                    <h2>Make an Offer</h2>

                    <?php if ($myOffer): ?>
                        <p class="td-muted">
                            You already submitted an offer.
                            Status: <strong><?= td_e(ucfirst($myOffer['status'])) ?></strong>
                        </p>
                        <div class="td-actions">
                            <a class="td-button" href="/pages/chat.php?task_id=<?= (int)$taskId ?>">Message Customer</a>
                        </div>
                    <?php else: ?>
                        <form method="post" class="td-form">
                            <input type="hidden" name="csrf_token" value="<?= td_e($csrfToken) ?>">
                            <input type="hidden" name="action" value="submit_offer">

                            <label for="amount">Your price (BDT)</label>
                            <input id="amount" type="number" name="amount" min="1" max="99999999" step="0.01" required>

                            <label for="message">Message to customer</label>
                            <textarea id="message" name="message" maxlength="2000" placeholder="Describe how you will complete the task"></textarea>

                            <button class="td-button" type="submit">Submit Offer</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

        </div>

        <aside class="td-card">
            <h2>Your Task</h2>
            <p class="td-muted">
                Review task details, manage offers and track progress.
            </p>

            <?php if ($role === 'customer'): ?>
                <a class="td-button" href="/pages/my-tasks.php">Manage My Tasks</a>
            <?php elseif ($role === 'helper'): ?>
                <a class="td-button" href="/pages/assigned-tasks.php">My Assigned Tasks</a>
            <?php else: ?>
                <a class="td-button" href="/pages/browse.php">Browse Tasks</a>
            <?php endif; ?>

            <div class="td-actions">
                <a class="td-button secondary" href="/pages/browse.php">← Browse Tasks</a>
            </div>
        </aside>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
