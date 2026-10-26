
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$profileId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$profileId || $profileId < 1) {
    http_response_code(404);
    exit('User profile not found.');
}

// Fetch public profile information
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.full_name,
        u.role,
        u.created_at,
        p.bio,
        p.location,
        p.profile_photo,
        p.skills,
        p.service_area
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$profileId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    exit('User profile not found.');
}

// Rating summary
$ratingStmt = $pdo->prepare("
    SELECT
        COALESCE(AVG(rating), 0) AS average_rating,
        COUNT(*) AS total_reviews
    FROM task_reviews
    WHERE reviewee_id = ?
");
$ratingStmt->execute([$profileId]);
$rating = $ratingStmt->fetch();

$averageRating = (float) ($rating['average_rating'] ?? 0);
$totalReviews = (int) ($rating['total_reviews'] ?? 0);

// Completed task count
if ($user['role'] === 'helper') {
    $completedStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT t.id)
        FROM tasks t
        INNER JOIN offers o ON o.task_id = t.id
        WHERE o.helper_id = ?
          AND o.status = 'accepted'
          AND t.status = 'completed'
    ");
    $completedStmt->execute([$profileId]);
} else {
    $completedStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM tasks
        WHERE customer_id = ?
          AND status = 'completed'
    ");
    $completedStmt->execute([$profileId]);
}

$completedTasks = (int) $completedStmt->fetchColumn();

// Recent reviews received by this user
$reviewsStmt = $pdo->prepare("
    SELECT
        r.rating,
        r.review_text,
        r.created_at,
        reviewer.full_name AS reviewer_name,
        t.title AS task_title
    FROM task_reviews r
    INNER JOIN users reviewer ON reviewer.id = r.reviewer_id
    INNER JOIN tasks t ON t.id = r.task_id
    WHERE r.reviewee_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
");
$reviewsStmt->execute([$profileId]);
$reviews = $reviewsStmt->fetchAll();

function tm_public_profile_escape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$fullName = $user['full_name'] ?? 'User';
$initial = strtoupper(substr(trim($fullName), 0, 1));
$roleLabel = ucfirst($user['role'] ?? 'customer');

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.public-profile-page {
    max-width: 1000px;
    margin: 35px auto;
    padding: 0 18px 45px;
    color: #1f2937;
}

.public-profile-heading {
    margin-bottom: 22px;
}

.public-profile-heading h1 {
    margin: 0 0 7px;
    font-size: 30px;
}

.public-profile-heading p {
    color: #6b7280;
    margin: 0;
}

.public-profile-card,
.public-reviews-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, .04);
}

.public-profile-cover {
    height: 125px;
    background: linear-gradient(120deg, #0f766e, #14b8a6);
}

.public-profile-main {
    padding: 0 28px 28px;
}

.public-profile-top {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
    margin-top: -42px;
}

.public-profile-avatar {
    width: 92px;
    height: 92px;
    border-radius: 50%;
    border: 5px solid #fff;
    background: #ccfbf1;
    color: #115e59;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: 700;
    overflow: hidden;
    flex-shrink: 0;
}

.public-profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.public-profile-identity {
    flex: 1;
    min-width: 180px;
    padding-top: 40px;
}

.public-profile-identity h2 {
    margin: 0 0 7px;
    font-size: 24px;
    overflow-wrap: anywhere;
}

.public-profile-role {
    display: inline-block;
    margin-top: 8px;
    padding: 5px 12px;
    border-radius: 20px;
    background: #ccfbf1;
    color: #115e59;
    font-size: 13px;
    font-weight: 600;
}

.public-profile-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin: 28px 0;
}

.public-profile-stat {
    padding: 19px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    text-align: center;
    background: #f9fafb;
}

.public-profile-stat strong {
    display: block;
    font-size: 25px;
    margin-bottom: 5px;
}

.public-profile-stat span {
    font-size: 13px;
    color: #6b7280;
}

.public-profile-section {
    border-top: 1px solid #e5e7eb;
    padding: 23px 0;
}

.public-profile-section h3,
.public-reviews-header h3 {
    margin: 0 0 15px;
    font-size: 19px;
}

.public-profile-section p {
    color: #4b5563;
    line-height: 1.7;
    margin: 0;
    overflow-wrap: anywhere;
}

.public-profile-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.public-profile-detail-label {
    display: block;
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 6px;
}

.public-profile-detail-value {
    font-weight: 500;
    overflow-wrap: anywhere;
    line-height: 1.6;
}

.public-profile-muted {
    color: #9ca3af !important;
    font-style: italic;
}

.public-reviews-card {
    margin-top: 25px;
    padding: 26px 28px;
}

.public-reviews-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 8px;
}

.public-reviews-header h3 {
    margin-bottom: 0;
}

.public-review-count {
    color: #6b7280;
    font-size: 14px;
}

.public-review {
    padding: 21px 0;
    border-bottom: 1px solid #e5e7eb;
}

.public-review:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.public-review-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.public-reviewer {
    font-weight: 700;
    margin-bottom: 5px;
}

.public-review-task {
    color: #6b7280;
    font-size: 13px;
}

.public-review-stars {
    color: #d97706;
    letter-spacing: 2px;
    font-size: 17px;
    white-space: nowrap;
}

.public-review-date {
    color: #9ca3af;
    font-size: 12px;
    margin-top: 5px;
}

.public-review-text {
    color: #4b5563;
    line-height: 1.7;
    margin: 12px 0 0;
    overflow-wrap: anywhere;
}

.public-no-reviews {
    color: #6b7280;
    text-align: center;
    padding: 30px 10px;
}

@media (max-width: 600px) {
    .public-profile-page {
        margin-top: 22px;
        padding: 0 12px 30px;
    }

    .public-profile-main {
        padding: 0 18px 20px;
    }

    .public-profile-identity {
        padding-top: 43px;
    }

    .public-profile-identity h2 {
        font-size: 20px;
    }

    .public-profile-stats {
        gap: 8px;
    }

    .public-profile-stat {
        padding: 14px 5px;
    }

    .public-profile-stat strong {
        font-size: 21px;
    }

    .public-profile-stat span {
        font-size: 11px;
    }

    .public-profile-details {
        grid-template-columns: 1fr;
    }

    .public-reviews-card {
        padding: 20px 17px;
    }
}
</style>

<main class="public-profile-page">

    <div class="public-profile-heading">
        <h1>Public Profile</h1>
        <p>Learn more about this TaskMate member and their experience.</p>
    </div>

    <section class="public-profile-card">
        <div class="public-profile-cover"></div>

        <div class="public-profile-main">
            <div class="public-profile-top">
                <div class="public-profile-avatar">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img
                            src="<?= tm_public_profile_escape($user['profile_photo']) ?>"
                            alt="Profile photo"
                        >
                    <?php else: ?>
                        <?= tm_public_profile_escape($initial) ?>
                    <?php endif; ?>
                </div>

                <div class="public-profile-identity">
                    <h2><?= tm_public_profile_escape($fullName) ?></h2>

                    <span class="public-profile-role">
                        <?= tm_public_profile_escape($roleLabel) ?>
                    </span>
                </div>
            </div>

            <div class="public-profile-stats">
                <div class="public-profile-stat">
                    <strong>
                        <?= $totalReviews > 0
                            ? number_format($averageRating, 1) . ' ★'
                            : '—' ?>
                    </strong>
                    <span>Average Rating</span>
                </div>

                <div class="public-profile-stat">
                    <strong><?= $totalReviews ?></strong>
                    <span>Reviews Received</span>
                </div>

                <div class="public-profile-stat">
                    <strong><?= $completedTasks ?></strong>
                    <span>Completed Tasks</span>
                </div>
            </div>

            <div class="public-profile-section">
                <h3>About Me</h3>

                <?php if (!empty($user['bio'])): ?>
                    <p><?= nl2br(tm_public_profile_escape($user['bio'])) ?></p>
                <?php else: ?>
                    <p class="public-profile-muted">
                        This user has not added a bio yet.
                    </p>
                <?php endif; ?>
            </div>

            <div class="public-profile-section">
                <h3>Profile Details</h3>

                <div class="public-profile-details">
                    <div>
                        <span class="public-profile-detail-label">Location</span>
                        <div class="public-profile-detail-value">
                            <?= !empty($user['location'])
                                ? tm_public_profile_escape($user['location'])
                                : 'Not provided' ?>
                        </div>
                    </div>

                    <div>
                        <span class="public-profile-detail-label">Member Since</span>
                        <div class="public-profile-detail-value">
                            <?= !empty($user['created_at'])
                                ? date('M Y', strtotime($user['created_at']))
                                : 'N/A' ?>
                        </div>
                    </div>

                    <?php if ($user['role'] === 'helper'): ?>
                        <div>
                            <span class="public-profile-detail-label">Skills & Services</span>
                            <div class="public-profile-detail-value">
                                <?= !empty($user['skills'])
                                    ? nl2br(tm_public_profile_escape($user['skills']))
                                    : 'Not provided' ?>
                            </div>
                        </div>

                        <div>
                            <span class="public-profile-detail-label">Service Area</span>
                            <div class="public-profile-detail-value">
                                <?= !empty($user['service_area'])
                                    ? tm_public_profile_escape($user['service_area'])
                                    : 'Not provided' ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="public-reviews-card">
        <div class="public-reviews-header">
            <h3>Reviews & Feedback</h3>
            <span class="public-review-count">
                <?= $totalReviews ?> review<?= $totalReviews === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if (empty($reviews)): ?>
            <div class="public-no-reviews">
                <div style="font-size: 32px; margin-bottom: 8px;">★</div>
                <strong>No reviews yet</strong>
                <p>Reviews will appear here after other users rate this member.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <article class="public-review">
                    <div class="public-review-top">
                        <div>
                            <div class="public-reviewer">
                                <?= tm_public_profile_escape($review['reviewer_name']) ?>
                            </div>

                            <div class="public-review-task">
                                Task: <?= tm_public_profile_escape($review['task_title']) ?>
                            </div>
                        </div>

                        <div>
                            <div
                                class="public-review-stars"
                                aria-label="<?= (int) $review['rating'] ?> out of 5 stars"
                            ><?= str_repeat('★', (int) $review['rating']) ?><?= str_repeat('☆', 5 - (int) $review['rating']) ?></div>

                            <div class="public-review-date">
                                <?= !empty($review['created_at'])
                                    ? date('M j, Y', strtotime($review['created_at']))
                                    : '' ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($review['review_text'])): ?>
                        <p class="public-review-text">
                            <?= nl2br(tm_public_profile_escape($review['review_text'])) ?>
                        </p>
                    <?php else: ?>
                        <p class="public-review-text public-profile-muted">
                            No written feedback provided.
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
