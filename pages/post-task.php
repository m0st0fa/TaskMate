
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Only logged-in customers can post tasks
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'customer') {
    http_response_code(403);
    exit('Only customers can post tasks. <a href="index.php">Go Home</a>');
}

// CSRF protection
if (empty($_SESSION['post_task_token'])) {
    $_SESSION['post_task_token'] = bin2hex(random_bytes(32));
}

function post_task_e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Load categories from the database
$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();

$title = '';
$description = '';
$location = '';
$budget = '';
$category_id = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $budget = trim($_POST['budget'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $token = $_POST['csrf_token'] ?? '';

    // Validate request token
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['post_task_token'], $token)
    ) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    }

    // Validate title
    if ($title === '') {
        $errors[] = 'Please enter a task title.';
    } elseif (strlen($title) > 150) {
        $errors[] = 'Task title cannot exceed 150 characters.';
    }

    // Validate description
    if ($description === '') {
        $errors[] = 'Please describe the task.';
    }

    // Validate location
    if ($location === '') {
        $errors[] = 'Please enter the task location.';
    } elseif (strlen($location) > 255) {
        $errors[] = 'Location cannot exceed 255 characters.';
    }

    // Validate category
    $categoryExists = false;

    if (
        $category_id !== '' &&
        ctype_digit($category_id) &&
        (int) $category_id > 0
    ) {
        foreach ($categories as $category) {
            if ((int) $category['id'] === (int) $category_id) {
                $categoryExists = true;
                break;
            }
        }
    }

    if (!$categoryExists) {
        $errors[] = 'Please select a valid category.';
    }

    // Budget is optional
    $budgetValue = null;

    if ($budget !== '') {
        if (
            !is_numeric($budget) ||
            !is_finite((float) $budget) ||
            (float) $budget < 0 ||
            (float) $budget > 99999999.99
        ) {
            $errors[] = 'Please enter a valid budget.';
        } else {
            $budgetValue = $budget;
        }
    }

    // Save task to MySQL
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO tasks
                (customer_id, category_id, title, description, location, budget)
                VALUES
                (:customer_id, :category_id, :title, :description, :location, :budget)'
            );

            $stmt->execute([
                ':customer_id' => $_SESSION['user_id'],
                ':category_id' => (int) $category_id,
                ':title' => $title,
                ':description' => $description,
                ':location' => $location,
                ':budget' => $budgetValue,
            ]);

            $taskId = (int) $pdo->lastInsertId();

            // Prevent accidental duplicate submission
            $_SESSION['post_task_token'] = bin2hex(random_bytes(32));

            header('Location: post-task.php?success=1&id=' . $taskId);
            exit;
        } catch (PDOException $e) {
            error_log('TaskMate task creation failed: ' . $e->getMessage());
            $errors[] = 'Could not post your task right now. Please try again.';
        }
    }
}

$success = isset($_GET['success']) && $_GET['success'] === '1';
$taskId = isset($_GET['id']) && ctype_digit($_GET['id'])
    ? (int) $_GET['id']
    : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Post a Task | TaskMate</title>

    <style>
        :root {
            --green: #16834a;
            --green-dark: #10683a;
            --green-light: #eaf7ef;
            --text: #172b20;
            --muted: #718076;
            --border: #dfe8e1;
            --background: #f6f9f6;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI",
                Roboto, Arial, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .navbar {
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        .nav-inner {
            max-width: 1160px;
            margin: auto;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .logo {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -1px;
            color: var(--green);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
        }

        .back-link {
            color: #526358;
            font-weight: 600;
        }

        .hero {
            background: linear-gradient(135deg, #e8f7ed, #f7fbf8);
            padding: 44px 20px 65px;
            text-align: center;
        }

        .eyebrow {
            display: inline-block;
            color: var(--green-dark);
            background: #d8f1e1;
            border-radius: 30px;
            padding: 6px 13px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
        }

        .hero h1 {
            font-size: clamp(29px, 5vw, 43px);
            line-height: 1.2;
            letter-spacing: -1.5px;
            margin-bottom: 12px;
        }

        .hero p {
            color: #617267;
            max-width: 560px;
            margin: auto;
            font-size: 15px;
        }

        .main {
            width: min(100% - 32px, 760px);
            margin: -34px auto 60px;
            position: relative;
        }

        .form-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 14px 45px rgba(27, 67, 42, 0.07);
        }

        .form-heading {
            padding-bottom: 22px;
            margin-bottom: 25px;
            border-bottom: 1px solid #edf1ed;
        }

        .form-heading h2 {
            font-size: 22px;
            margin-bottom: 5px;
        }

        .form-heading p {
            font-size: 14px;
            color: var(--muted);
        }

        .field {
            margin-bottom: 22px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .required {
            color: #dc4545;
        }

        .optional {
            color: var(--muted);
            font-size: 12px;
            font-weight: 500;
        }

        input, select, textarea {
            display: block;
            width: 100%;
            border: 1px solid #d8e2da;
            background: #fff;
            border-radius: 9px;
            padding: 13px 14px;
            color: var(--text);
            font: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(22, 131, 74, .10);
        }

        textarea {
            min-height: 145px;
            resize: vertical;
        }

        .hint {
            color: var(--muted);
            font-size: 12px;
            margin-top: 6px;
        }

        .submit-btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            background: var(--green);
            color: white;
            padding: 15px 20px;
            font-size: 15px;
            font-weight: 750;
            cursor: pointer;
            transition: background .2s, transform .2s;
        }

        .submit-btn:hover {
            background: var(--green-dark);
            transform: translateY(-1px);
        }

        .notice {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 14px;
        }

        .error {
            background: #fff0f0;
            color: #9f2525;
            border: 1px solid #f3caca;
        }

        .success {
            background: #e9f8ee;
            color: #176637;
            border: 1px solid #c6e9d0;
        }

        .notice ul {
            padding-left: 20px;
        }

        .success strong {
            display: block;
            margin-bottom: 3px;
        }

        .tips {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 20px;
        }

        .tip {
            padding: 15px 12px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            text-align: center;
            font-size: 12px;
            color: #66756a;
        }

        .tip-icon {
            display: block;
            font-size: 22px;
            margin-bottom: 5px;
        }

        .tip strong {
            display: block;
            color: var(--text);
            font-size: 13px;
            margin-bottom: 3px;
        }

        footer {
            text-align: center;
            padding: 24px 16px;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 560px) {
            .nav-inner {
                padding: 15px 18px;
            }

            .nav-right {
                gap: 12px;
            }

            .form-card {
                padding: 22px 18px;
            }

            .hero {
                padding: 35px 18px 55px;
            }

            .tips {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<header class="navbar">
    <div class="nav-inner">
        <a class="logo" href="../index.php">TaskMate<span>.</span></a>

        <div class="nav-right">
            <a class="back-link" href="../index.php">← Back to Home</a>
        </div>
    </div>
</header>

<section class="hero">
    <span class="eyebrow">Your task, your way</span>
    <h1>What do you need help with?</h1>
    <p>
        Tell us what you need, set your budget, and find the right
        person to get the job done.
    </p>
</section>

<main class="main">
    <div class="form-card">

        <div class="form-heading">
            <h2>Post your task</h2>
            <p>Fill in the details below. Fields marked with * are required.</p>
        </div>

        <?php if ($success && $taskId > 0): ?>
            <div class="notice success">
                <strong>✓ Your task has been posted successfully!</strong>
                Your task ID is #<?= post_task_e($taskId) ?>.
                Your task has been saved in the TaskMate database.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="notice error">
                <strong>Please check the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= post_task_e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="post-task.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= post_task_e($_SESSION['post_task_token']) ?>"
            >

            <div class="field">
                <label for="title">
                    Task title <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="150"
                    placeholder="e.g. Need someone to clean my apartment"
                    value="<?= post_task_e($title) ?>"
                    required
                >

                <p class="hint">Write a short, clear title for your task.</p>
            </div>

            <div class="field">
                <label for="category_id">
                    Category <span class="required">*</span>
                </label>

                <select id="category_id" name="category_id" required>
                    <option value="">Choose a category</option>

                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= post_task_e($category['id']) ?>"
                            <?= (string) $category_id === (string) $category['id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= post_task_e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="description">
                    Task description <span class="required">*</span>
                </label>

                <textarea
                    id="description"
                    name="description"
                    placeholder="Explain what needs to be done, any requirements, and other important details..."
                    required
                ><?= post_task_e($description) ?></textarea>

                <p class="hint">
                    More details help helpers understand your requirements.
                </p>
            </div>

            <div class="field">
                <label for="location">
                    Task location <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="location"
                    name="location"
                    maxlength="255"
                    placeholder="e.g. Uttara, Dhaka"
                    value="<?= post_task_e($location) ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="budget">
                    Your budget (BDT)
                    <span class="optional">— Optional</span>
                </label>

                <input
                    type="number"
                    id="budget"
                    name="budget"
                    min="0"
                    max="99999999.99"
                    step="0.01"
                    placeholder="e.g. 1500"
                    value="<?= post_task_e($budget) ?>"
                >

                <p class="hint">
                    Enter your estimated budget in Bangladeshi Taka (৳).
                </p>
            </div>

            <button class="submit-btn" type="submit">
                Post My Task →
            </button>

        </form>
    </div>

    <div class="tips">
        <div class="tip">
            <span class="tip-icon">✍️</span>
            <strong>Be specific</strong>
            <span>Explain exactly what you need.</span>
        </div>

        <div class="tip">
            <span class="tip-icon">📍</span>
            <strong>Add your location</strong>
            <span>Help nearby helpers find your task.</span>
        </div>

        <div class="tip">
            <span class="tip-icon">৳</span>
            <strong>Set a budget</strong>
            <span>Share your estimated spending limit.</span>
        </div>
    </div>
</main>

<footer>
    © <?= date('Y') ?> TaskMate. Connecting people with the right help.
</footer>

</body>
</html>
