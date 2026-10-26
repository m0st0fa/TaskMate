
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
$role = $_SESSION['role'] ?? '';
$taskId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$taskId) {
    http_response_code(400);
    exit('Invalid task ID.');
}

if (empty($_SESSION['review_csrf'])) {
    $_SESSION['review_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['review_csrf'];

function reviewEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function reviewRedirect(int $taskId, string $message): never
{
    $_SESSION['review_flash'] = $message;
    header('Location: /pages/review-task.php?id=' . $taskId);
    exit;
}

/* Load task and the accepted helper */
$stmt = $pdo->prepare(
    "SELECT t.*, c.full_name AS customer_name,
            o.helper_id, h.full_name AS helper_name,
            o.amount AS agreed_amount
     FROM tasks t
     INNER JOIN users c ON c.id = t.customer_id
     INNER JOIN offers o
        ON o.task_id = t.id AND o.status = 'accepted'
     INNER JOIN users h ON h.id = o.helper_id
     WHERE t.id = ?
     LIMIT 1"
);
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    http_response_code(404);
    exit('Task or assigned helper not found.');
}

$customerId = (int) $task['customer_id'];
$helperId = (int) $task['helper_id'];

$isCustomer = $userId === $customerId && $role === 'customer';
$isHelper = $userId === $helperId && $role === 'helper';

if (!$isCustomer && !$isHelper) {
    http_response_code(403);
    exit('Only this task’s customer and assigned helper can review each other.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) ||
        !hash_equals($csrf, $submittedToken)) {
        http_response_code(403);
        exit('Invalid security token. Refresh and try again.');
    }

    if ($task['status'] !== 'completed') {
        reviewRedirect($taskId, 'Reviews are available only after the customer confirms completion.');
    }

    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $reviewText = trim((string)($_POST['review_text'] ?? ''));

    if ($rating === false || $rating === null || $rating < 1 || $rating > 5) {
        reviewRedirect($taskId, 'Please select a rating from 1 to 5 stars.');
    }

    if (mb_strlen($reviewText) > 2000) {
        reviewRedirect($taskId, 'Review must be 2000 characters or fewer.');
    }

    $revieweeId = $isCustomer ? $helperId : $customerId;

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO task_reviews
                (task_id, reviewer_id, reviewee_id, rating, review_text)
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $taskId,
            $userId,
            $revieweeId,
            $rating,
            $reviewText !== '' ? $reviewText : null
        ]);

        reviewRedirect($taskId, 'Your review has been submitted successfully.');

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            reviewRedirect($taskId, 'You have already reviewed this task.');
        }

        error_log('TaskMate review error: ' . $e->getMessage());
        reviewRedirect($taskId, 'Could not save your review. Please try again.');
    }
}

/* Find the current user's review and reviews for this task */
$stmt = $pdo->prepare(
    "SELECT * FROM task_reviews
     WHERE task_id = ? AND reviewer_id = ?
     LIMIT 1"
);
$stmt->execute([$taskId, $userId]);
$myReview = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT r.rating, r.review_text, r.created_at,
            reviewer.full_name AS reviewer_name,
            reviewee.full_name AS reviewee_name
     FROM task_reviews r
     INNER JOIN users reviewer ON reviewer.id = r.reviewer_id
     INNER JOIN users reviewee ON reviewee.id = r.reviewee_id
     WHERE r.task_id = ?
     ORDER BY r.created_at DESC"
);
$stmt->execute([$taskId]);
$reviews = $stmt->fetchAll();

$revieweeName = $isCustomer
    ? $task['helper_name']
    : $task['customer_name'];

$pageTitle = 'Review Task | TaskMate';
require_once __DIR__ . '/../includes/header.php';

$flash = $_SESSION['review_flash'] ?? '';
unset($_SESSION['review_flash']);
?>

<style>
.review-page{max-width:850px;margin:0 auto;padding:40px 20px 70px;color:#193327}
.review-card{background:#fff;border:1px solid #e0eae3;border-radius:17px;padding:27px;margin-bottom:22px}
.review-card h1,.review-card h2{margin-top:0}
.review-muted{color:#708075;line-height:1.7}
.review-alert{padding:13px 16px;border-radius:10px;background:#edf8f0;margin-bottom:18px}
.review-stars{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 20px}
.review-stars label{cursor:pointer}
.review-stars input{position:absolute;opacity:0;width:1px;height:1px}
.review-stars span{display:block;border:1px solid #dce6df;border-radius:9px;padding:11px 14px;font-size:20px}
.review-stars input:checked+span{background:#fff5d6;border-color:#e6b943}
.review-form label{font-weight:700;display:block;margin:14px 0 8px}
.review-form textarea{width:100%;box-sizing:border-box;min-height:110px;border:1px solid #dce6df;border-radius:9px;padding:12px;font:inherit}
.review-button{display:inline-block;border:0;border-radius:9px;background:#16834a;color:white;padding:12px 18px;font-weight:700;cursor:pointer}
.review-item{padding:17px 0;border-top:1px solid #edf1ee}
.review-item:first-of-type{border-top:0}
.review-score{color:#a87400;font-weight:800}
@media(max-width:520px){.review-page{padding:24px 13px}.review-card{padding:20px}}
</style>

<main class="review-page">

    <?php if ($flash !== ''): ?>
        <div class="review-alert"><?= reviewEscape($flash) ?></div>
    <?php endif; ?>

    <section class="review-card">
        <h1>Rate Your Experience</h1>
        <p class="review-muted">
            Task: <strong><?= reviewEscape($task['title']) ?></strong><br>
            Customer: <?= reviewEscape($task['customer_name']) ?><br>
            Helper: <?= reviewEscape($task['helper_name']) ?><br>
            Agreed price: ৳<?= number_format((float)$task['agreed_amount'], 2) ?>
        </p>

        <?php if ($task['status'] !== 'completed'): ?>
            <div class="review-alert">
                The customer must confirm task completion before either person can submit a review.
            </div>

        <?php elseif ($myReview): ?>
            <h2>Your Review</h2>
            <p class="review-score">
                <?= str_repeat('★', (int)$myReview['rating']) ?>
                <?= str_repeat('☆', 5 - (int)$myReview['rating']) ?>
                (<?= (int)$myReview['rating'] ?>/5)
            </p>
            <p><?= nl2br(reviewEscape($myReview['review_text'] ?: 'No written comment.')) ?></p>
            <p class="review-muted">You have already reviewed this task.</p>

        <?php else: ?>
            <h2>
                <?= $isCustomer ? 'Rate Your Helper' : 'Rate Your Customer' ?>
            </h2>

            <p class="review-muted">
                You are reviewing <strong><?= reviewEscape($revieweeName) ?></strong>.
                Choose a star rating and optionally write a review.
            </p>

            <form method="post" class="review-form">
                <input type="hidden" name="csrf_token" value="<?= reviewEscape($csrf) ?>">

                <label>Rating</label>
                <div class="review-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label>
                            <input
                                type="radio"
                                name="rating"
                                value="<?= $i ?>"
                                required
                            >
                            <span><?= str_repeat('★', $i) ?> <?= $i ?></span>
                        </label>
                    <?php endfor; ?>
                </div>

                <label for="review_text">Your review (optional)</label>
                <textarea
                    id="review_text"
                    name="review_text"
                    maxlength="2000"
                    placeholder="Share your experience..."
                ></textarea>

                <button type="submit" class="review-button">
                    Submit Review
                </button>
            </form>
        <?php endif; ?>
    </section>

    <section class="review-card">
        <h2>Reviews for This Task</h2>

        <?php if (empty($reviews)): ?>
            <p class="review-muted">No reviews have been submitted yet.</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <strong><?= reviewEscape($review['reviewer_name']) ?></strong>
                    <span class="review-muted">
                        → <?= reviewEscape($review['reviewee_name']) ?>
                    </span>

                    <p class="review-score">
                        <?= str_repeat('★', (int)$review['rating']) ?>
                        <?= str_repeat('☆', 5 - (int)$review['rating']) ?>
                    </p>

                    <?php if (!empty($review['review_text'])): ?>
                        <p><?= nl2br(reviewEscape($review['review_text'])) ?></p>
                    <?php endif; ?>

                    <small class="review-muted">
                        <?= date('d M Y', strtotime($review['created_at'])) ?>
                    </small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <a href="/pages/task-details.php?id=<?= (int)$taskId ?>">
        ← Back to Task Details
    </a>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
