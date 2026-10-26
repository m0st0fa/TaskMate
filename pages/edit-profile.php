
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

function tm_edit_escape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Get account and existing profile information
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.role,
        p.bio,
        p.phone,
        p.location,
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
    header('Location: /logout.php');
    exit;
}

// CSRF protection
if (empty($_SESSION['edit_profile_token'])) {
    $_SESSION['edit_profile_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';

$fullName = $user['full_name'] ?? '';
$bio = $user['bio'] ?? '';
$phone = $user['phone'] ?? '';
$location = $user['location'] ?? '';
$skills = $user['skills'] ?? '';
$serviceArea = $user['service_area'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['edit_profile_token'], $token)
    ) {
        $errors[] = 'Your session token is invalid. Please refresh the page and try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $serviceArea = trim($_POST['service_area'] ?? '');

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($fullName) > 100) {
            $errors[] = 'Full name cannot exceed 100 characters.';
        }

        if (mb_strlen($bio) > 2000) {
            $errors[] = 'Bio cannot exceed 2000 characters.';
        }

        if (mb_strlen($phone) > 25) {
            $errors[] = 'Phone number cannot exceed 25 characters.';
        }

        if (mb_strlen($location) > 255) {
            $errors[] = 'Location cannot exceed 255 characters.';
        }

        if (mb_strlen($skills) > 2000) {
            $errors[] = 'Skills cannot exceed 2000 characters.';
        }

        if (mb_strlen($serviceArea) > 255) {
            $errors[] = 'Service area cannot exceed 255 characters.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                // Update name in the existing users table.
                $updateUser = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?
                    WHERE id = ?
                ");
                $updateUser->execute([$fullName, $userId]);

                // Create or update the user's profile.
                $upsertProfile = $pdo->prepare("
                    INSERT INTO user_profiles
                        (user_id, bio, phone, location, skills, service_area)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        bio = VALUES(bio),
                        phone = VALUES(phone),
                        location = VALUES(location),
                        skills = VALUES(skills),
                        service_area = VALUES(service_area)
                ");

                $upsertProfile->execute([
                    $userId,
                    $bio !== '' ? $bio : null,
                    $phone !== '' ? $phone : null,
                    $location !== '' ? $location : null,
                    $skills !== '' ? $skills : null,
                    $serviceArea !== '' ? $serviceArea : null
                ]);

                $pdo->commit();

                // Update the name displayed in the navbar.
                $_SESSION['full_name'] = $fullName;

                header('Location: /pages/profile.php?updated=1');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('TaskMate profile update failed: ' . $e->getMessage());
                $errors[] = 'Unable to save your profile right now. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.edit-profile-page {
    max-width: 850px;
    margin: 35px auto;
    padding: 0 18px 45px;
    color: #1f2937;
}

.edit-profile-heading {
    margin-bottom: 24px;
}

.edit-profile-heading h1 {
    margin: 0 0 8px;
    font-size: 30px;
}

.edit-profile-heading p {
    margin: 0;
    color: #6b7280;
}

.edit-profile-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, .04);
}

.edit-profile-section {
    margin-bottom: 25px;
}

.edit-profile-section h2 {
    font-size: 19px;
    margin: 0 0 18px;
}

.edit-profile-field {
    margin-bottom: 19px;
}

.edit-profile-field label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
}

.edit-profile-field input,
.edit-profile-field textarea {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    background: #fff;
    color: #1f2937;
    font: inherit;
    outline: none;
}

.edit-profile-field input:focus,
.edit-profile-field textarea:focus {
    border-color: #0f766e;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, .12);
}

.edit-profile-field textarea {
    min-height: 110px;
    resize: vertical;
}

.edit-profile-field small {
    display: block;
    color: #6b7280;
    font-size: 12px;
    margin-top: 6px;
}

.edit-profile-field input[readonly] {
    background: #f3f4f6;
    color: #6b7280;
    cursor: not-allowed;
}

.edit-profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 18px;
}

.edit-profile-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.edit-profile-save,
.edit-profile-cancel {
    display: inline-block;
    padding: 12px 20px;
    border-radius: 9px;
    text-decoration: none;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.edit-profile-save {
    background: #0f766e;
    color: #fff;
    border: 1px solid #0f766e;
}

.edit-profile-save:hover {
    background: #115e59;
}

.edit-profile-cancel {
    background: #fff;
    color: #374151;
    border: 1px solid #d1d5db;
}

.edit-profile-cancel:hover {
    background: #f9fafb;
}

.edit-profile-alert {
    border-radius: 9px;
    padding: 14px 16px;
    margin-bottom: 20px;
}

.edit-profile-error {
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

@media (max-width: 600px) {
    .edit-profile-page {
        margin-top: 22px;
        padding: 0 12px 30px;
    }

    .edit-profile-card {
        padding: 20px 16px;
    }

    .edit-profile-grid {
        grid-template-columns: 1fr;
    }

    .edit-profile-heading h1 {
        font-size: 25px;
    }
}
</style>

<main class="edit-profile-page">
    <div class="edit-profile-heading">
        <h1>Edit Profile</h1>
        <p>Update your information and make your TaskMate profile better.</p>
    </div>

    <section class="edit-profile-card">

        <?php if ($errors): ?>
            <div class="edit-profile-alert edit-profile-error" role="alert">
                <strong>Please fix the following:</strong>
                <ul style="margin-bottom: 0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= tm_edit_escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/pages/edit-profile.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= tm_edit_escape($_SESSION['edit_profile_token']) ?>"
            >

            <div class="edit-profile-section">
                <h2>Basic Information</h2>

                <div class="edit-profile-field">
                    <label for="full_name">Full Name *</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        maxlength="100"
                        value="<?= tm_edit_escape($fullName) ?>"
                        required
                    >
                </div>

                <div class="edit-profile-field">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        value="<?= tm_edit_escape($user['email']) ?>"
                        readonly
                    >
                    <small>Email address cannot be changed from this page.</small>
                </div>

                <div class="edit-profile-grid">
                    <div class="edit-profile-field">
                        <label for="phone">Phone Number</label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            maxlength="25"
                            placeholder="e.g. 01XXXXXXXXX"
                            value="<?= tm_edit_escape($phone) ?>"
                        >
                    </div>

                    <div class="edit-profile-field">
                        <label for="location">Location</label>
                        <input
                            type="text"
                            id="location"
                            name="location"
                            maxlength="255"
                            placeholder="e.g. Uttara, Dhaka"
                            value="<?= tm_edit_escape($location) ?>"
                        >
                    </div>
                </div>

                <div class="edit-profile-field">
                    <label for="bio">About Me</label>
                    <textarea
                        id="bio"
                        name="bio"
                        maxlength="2000"
                        placeholder="Introduce yourself..."
                    ><?= tm_edit_escape($bio) ?></textarea>
                    <small>Tell people a little about yourself.</small>
                </div>
            </div>

            <?php if ($user['role'] === 'helper'): ?>
                <div class="edit-profile-section">
                    <h2>Helper Information</h2>

                    <div class="edit-profile-field">
                        <label for="skills">Skills and Services</label>
                        <textarea
                            id="skills"
                            name="skills"
                            maxlength="2000"
                            placeholder="e.g. Home cleaning, graphic design, web development..."
                        ><?= tm_edit_escape($skills) ?></textarea>
                        <small>List the skills and services you can provide.</small>
                    </div>

                    <div class="edit-profile-field">
                        <label for="service_area">Service Area</label>
                        <input
                            type="text"
                            id="service_area"
                            name="service_area"
                            maxlength="255"
                            placeholder="e.g. Uttara, Mirpur, Dhaka"
                            value="<?= tm_edit_escape($serviceArea) ?>"
                        >
                        <small>Where are you available to provide services?</small>
                    </div>
                </div>
            <?php endif; ?>

            <div class="edit-profile-actions">
                <button type="submit" class="edit-profile-save">
                    Save Changes
                </button>

                <a href="/pages/profile.php" class="edit-profile-cancel">
                    Cancel
                </a>
            </div>
        </form>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
