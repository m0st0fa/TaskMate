
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.role,
        u.created_at,
        p.bio,
        p.phone,
        p.location,
        p.profile_photo,
        p.skills,
        p.service_area
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

// Average rating and number of reviews received
$ratingStmt = $pdo->prepare("
    SELECT
        COALESCE(AVG(rating), 0) AS average_rating,
        COUNT(*) AS total_reviews
    FROM task_reviews
    WHERE reviewee_id = ?
");
$ratingStmt->execute([$userId]);
$rating = $ratingStmt->fetch();

$averageRating = (float) ($rating['average_rating'] ?? 0);
$totalReviews = (int) ($rating['total_reviews'] ?? 0);

// Count completed tasks according to the user's role
if ($user['role'] === 'helper') {
    $completedStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT t.id)
        FROM tasks t
        INNER JOIN offers o ON o.task_id = t.id
        WHERE o.helper_id = ?
          AND o.status = 'accepted'
          AND t.status = 'completed'
    ");
    $completedStmt->execute([$userId]);
} else {
    $completedStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM tasks
        WHERE customer_id = ?
          AND status = 'completed'
    ");
    $completedStmt->execute([$userId]);
}

$completedTasks = (int) $completedStmt->fetchColumn();

$fullName = $user['full_name'] ?? 'User';
$initial = strtoupper(substr(trim($fullName), 0, 1));
$roleLabel = ucfirst($user['role'] ?? 'customer');

function tm_profile_escape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.profile-page {
    max-width: 1000px;
    margin: 35px auto;
    padding: 0 18px 45px;
    color: #1f2937;
}
.profile-heading {
    margin-bottom: 22px;
}
.profile-heading h1 {
    font-size: 30px;
    margin: 0 0 7px;
}
.profile-heading p {
    color: #6b7280;
    margin: 0;
}
.profile-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,.04);
}
.profile-cover {
    height: 125px;
    background: linear-gradient(120deg, #0f766e, #14b8a6);
}
.profile-main {
    padding: 0 28px 28px;
}
.profile-top {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
    margin-top: -42px;
}
.profile-avatar {
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
.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.profile-identity {
    flex: 1;
    min-width: 180px;
    padding-top: 40px;
}
.profile-identity h2 {
    margin: 0 0 6px;
    font-size: 24px;
    overflow-wrap: anywhere;
}
.profile-email {
    color: #6b7280;
    overflow-wrap: anywhere;
}
.profile-role {
    display: inline-block;
    margin-top: 9px;
    padding: 5px 12px;
    background: #ccfbf1;
    color: #115e59;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
.profile-edit-btn {
    display: inline-block;
    text-decoration: none;
    padding: 11px 18px;
    border-radius: 9px;
    background: #0f766e;
    color: white !important;
    font-weight: 600;
    margin-top: 40px;
}
.profile-edit-btn:hover {
    background: #115e59;
}
.profile-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin: 28px 0;
}
.profile-stat {
    padding: 19px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    text-align: center;
    background: #f9fafb;
}
.profile-stat strong {
    display: block;
    font-size: 25px;
    margin-bottom: 5px;
}
.profile-stat span {
    font-size: 13px;
    color: #6b7280;
}
.profile-section {
    border-top: 1px solid #e5e7eb;
    padding: 23px 0;
}
.profile-section h3 {
    margin: 0 0 15px;
    font-size: 18px;
}
.profile-section p {
    color: #4b5563;
    line-height: 1.7;
    margin: 0;
    overflow-wrap: anywhere;
}
.profile-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}
.profile-detail-label {
    display: block;
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 6px;
}
.profile-detail-value {
    font-weight: 500;
    overflow-wrap: anywhere;
}
.profile-empty {
    color: #9ca3af !important;
    font-style: italic;
}
@media (max-width: 600px) {
    .profile-page {
        margin-top: 22px;
        padding-left: 12px;
        padding-right: 12px;
    }
    .profile-main {
        padding: 0 18px 20px;
    }
    .profile-top {
        gap: 10px;
    }
    .profile-identity {
        padding-top: 43px;
    }
    .profile-identity h2 {
        font-size: 20px;
    }
    .profile-edit-btn {
        margin-top: 12px;
    }
    .profile-stats {
        gap: 8px;
    }
    .profile-stat {
        padding: 14px 5px;
    }
    .profile-stat strong {
        font-size: 21px;
    }
    .profile-stat span {
        font-size: 11px;
    }
    .profile-details {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="profile-page">
    <div class="profile-heading">
        <h1>My Profile</h1>
        <p>Manage your TaskMate account and view your activity.</p>
    </div>

    <section class="profile-card">
        <div class="profile-cover"></div>

        <div class="profile-main">
            <div class="profile-top">
                <div class="profile-avatar">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img
                            src="<?= tm_profile_escape($user['profile_photo']) ?>"
                            alt="Profile photo"
                        >
                    <?php else: ?>
                        <?= tm_profile_escape($initial) ?>
                    <?php endif; ?>
                </div>

                <div class="profile-identity">
                    <h2><?= tm_profile_escape($fullName) ?></h2>
                    <div class="profile-email">
                        <?= tm_profile_escape($user['email']) ?>
                    </div>
                    <span class="profile-role">
                        <?= tm_profile_escape($roleLabel) ?>
                    </span>
                </div>

                <a class="profile-edit-btn" href="/pages/edit-profile.php">
                    Edit Profile
                </a>
            </div>

            <div class="profile-stats">
                <div class="profile-stat">
                    <strong>
                        <?= $totalReviews > 0
                            ? number_format($averageRating, 1) . ' ★'
                            : '—' ?>
                    </strong>
                    <span>Average Rating</span>
                </div>

                <div class="profile-stat">
                    <strong><?= $totalReviews ?></strong>
                    <span>Reviews Received</span>
                </div>

                <div class="profile-stat">
                    <strong><?= $completedTasks ?></strong>
                    <span>Completed Tasks</span>
                </div>
            </div>

            <div class="profile-section">
                <h3>About Me</h3>
                <p class="<?= empty($user['bio']) ? 'profile-empty' : '' ?>">
                    <?= !empty($user['bio'])
                        ? nl2br(tm_profile_escape($user['bio']))
                        : 'You have not added a bio yet. Click Edit Profile to introduce yourself.' ?>
                </p>
            </div>

            <div class="profile-section">
                <h3>Profile Details</h3>

                <div class="profile-details">
                    <div>
                        <span class="profile-detail-label">Phone</span>
                        <div class="profile-detail-value">
                            <?= !empty($user['phone'])
                                ? tm_profile_escape($user['phone'])
                                : 'Not added yet' ?>
                        </div>
                    </div>

                    <div>
                        <span class="profile-detail-label">Location</span>
                        <div class="profile-detail-value">
                            <?= !empty($user['location'])
                                ? tm_profile_escape($user['location'])
                                : 'Not added yet' ?>
                        </div>
                    </div>

                    <div>
                        <span class="profile-detail-label">Account Type</span>
                        <div class="profile-detail-value">
                            <?= tm_profile_escape($roleLabel) ?>
                        </div>
                    </div>

                    <div>
                        <span class="profile-detail-label">Member Since</span>
                        <div class="profile-detail-value">
                            <?= !empty($user['created_at'])
                                ? date('M Y', strtotime($user['created_at']))
                                : 'N/A' ?>
                        </div>
                    </div>

                    <?php if ($user['role'] === 'helper'): ?>
                        <div>
                            <span class="profile-detail-label">Skills</span>
                            <div class="profile-detail-value">
                                <?= !empty($user['skills'])
                                    ? nl2br(tm_profile_escape($user['skills']))
                                    : 'Not added yet' ?>
                            </div>
                        </div>

                        <div>
                            <span class="profile-detail-label">Service Area</span>
                            <div class="profile-detail-value">
                                <?= !empty($user['service_area'])
                                    ? tm_profile_escape($user['service_area'])
                                    : 'Not added yet' ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
