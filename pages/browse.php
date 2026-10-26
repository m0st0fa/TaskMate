
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

function browse_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Read search filters
$search = trim($_GET['q'] ?? '');
$categoryId = trim($_GET['category'] ?? '');

if (strlen($search) > 150) {
    $search = substr($search, 0, 150);
}

$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();

$sql = "
    SELECT
        t.id,
        t.title,
        t.description,
        t.location,
        t.budget,
        t.created_at,
        c.name AS category_name,
        u.id AS customer_id,
        u.full_name AS customer_name
    FROM tasks t
    LEFT JOIN categories c ON c.id = t.category_id
    INNER JOIN users u ON u.id = t.customer_id
    WHERE t.status = 'posted'
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            t.title LIKE :title_search
            OR t.description LIKE :description_search
            OR t.location LIKE :location_search
        )
    ";

    $term = '%' . $search . '%';

    $params[':title_search'] = $term;
    $params[':description_search'] = $term;
    $params[':location_search'] = $term;
}

if ($categoryId !== '' && ctype_digit($categoryId)) {
    $sql .= ' AND t.category_id = :category_id';
    $params[':category_id'] = (int)$categoryId;
}

$sql .= ' ORDER BY t.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$selectedCategoryName = 'All Categories';

foreach ($categories as $category) {
    if (
        $categoryId !== '' &&
        (string)$category['id'] === $categoryId
    ) {
        $selectedCategoryName = $category['name'];
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Browse Tasks | TaskMate</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
    background: #f5f8f5;
    color: #193125;
}

a { text-decoration: none; }

.navbar {
    background: white;
    border-bottom: 1px solid #e0e8e1;
}

.nav-inner {
    max-width: 1160px;
    margin: auto;
    padding: 17px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.logo {
    color: #16834a;
    font-size: 25px;
    font-weight: 800;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 23px;
    font-size: 14px;
    font-weight: 600;
    color: #53665a;
}

.nav-links a:hover,
.nav-links .active {
    color: #16834a;
}

.nav-action {
    background: #16834a;
    color: white;
    padding: 11px 15px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.hero {
    background: linear-gradient(135deg, #e5f6eb, #f8fbf8);
    padding: 48px 20px 45px;
    text-align: center;
}

.eyebrow {
    display: inline-block;
    background: #d4efde;
    color: #116a39;
    border-radius: 30px;
    padding: 6px 13px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
}

.hero h1 {
    font-size: clamp(30px, 5vw, 42px);
    letter-spacing: -1.2px;
    margin: 14px 0 9px;
}

.hero p {
    color: #66766b;
    margin: 0;
    font-size: 15px;
}

.container {
    max-width: 1160px;
    width: calc(100% - 32px);
    margin: 32px auto 60px;
}

.filters {
    background: white;
    padding: 20px;
    border: 1px solid #e0e8e1;
    border-radius: 13px;
    margin-bottom: 25px;
}

.filters form {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) minmax(170px, 240px) auto auto;
    gap: 12px;
}

input, select {
    width: 100%;
    min-width: 0;
    border: 1px solid #d8e2da;
    border-radius: 8px;
    background: white;
    padding: 12px;
    font: inherit;
    font-size: 14px;
    color: #193125;
    outline: none;
}

input:focus, select:focus {
    border-color: #16834a;
    box-shadow: 0 0 0 3px #16834a18;
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 12px 17px;
    border-radius: 8px;
    font: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    border: 0;
    white-space: nowrap;
}

.btn-primary {
    background: #16834a;
    color: white;
}

.btn-primary:hover { background: #10683a; }

.btn-light {
    background: #edf3ee;
    color: #405447;
}

.results-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}

.results-heading h2 {
    font-size: 22px;
    margin: 0;
}

.result-count {
    color: #718075;
    font-size: 13px;
}

.task-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.task-card {
    background: white;
    border: 1px solid #e0e8e1;
    border-radius: 14px;
    padding: 23px;
    box-shadow: 0 5px 20px #1a492008;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.category {
    color: #16834a;
    background: #e8f6ed;
    border-radius: 30px;
    display: inline-block;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 700;
    align-self: flex-start;
    margin-bottom: 13px;
}

.task-card h3 {
    margin: 0 0 10px;
    font-size: 19px;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.task-card h3 a { color: #193125; }
.task-card h3 a:hover { color: #16834a; }

.description {
    color: #647368;
    font-size: 14px;
    line-height: 1.7;
    margin: 0 0 18px;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

.task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 17px;
    color: #66766b;
    font-size: 13px;
    padding-top: 15px;
    border-top: 1px solid #edf1ed;
    margin-top: auto;
}

.task-meta strong { color: #263d2e; }

.profile-link {
    color: #16834a;
    font-weight: 700;
}
.profile-link:hover { text-decoration: underline; }

.card-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 20px;
}

.budget {
    font-size: 18px;
    font-weight: 800;
    color: #16834a;
}

.budget small {
    font-size: 11px;
    font-weight: 500;
    color: #718075;
}

.details-btn {
    color: #16834a;
    border: 1px solid #16834a;
    border-radius: 8px;
    padding: 10px 13px;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.details-btn:hover {
    background: #e8f6ed;
}

.empty {
    background: white;
    border: 1px solid #e0e8e1;
    border-radius: 14px;
    text-align: center;
    padding: 55px 20px;
}

.empty-icon { font-size: 42px; }
.empty h2 { margin: 12px 0 8px; }
.empty p { color: #718075; margin: 0 0 20px; }

footer {
    text-align: center;
    color: #718075;
    font-size: 12px;
    padding: 20px;
}

@media(max-width: 800px) {
    .nav-links { display: none; }
    .filters form { grid-template-columns: 1fr 1fr; }
    .task-grid { grid-template-columns: 1fr; }
}

@media(max-width: 480px) {
    .nav-inner { padding: 15px 16px; }
    .nav-action { padding: 10px; }
    .filters form { grid-template-columns: 1fr; }
    .container { margin-top: 22px; }
    .task-card { padding: 18px; }
    .results-heading { align-items: flex-start; flex-direction: column; }
}
</style>
</head>

<body>

<header class="navbar">
    <div class="nav-inner">
        <a href="../index.php" class="logo">TaskMate.</a>

        <nav class="nav-links">
            <a href="../index.php">Home</a>
            <a href="browse.php" class="active">Browse Tasks</a>
            <a href="categories.php">Categories</a>
        </nav>

        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if (($_SESSION['role'] ?? '') === 'customer'): ?>
                <a href="post-task.php" class="nav-action">+ Post a Task</a>
            <?php else: ?>
                <a href="../dashboard/helper.php" class="nav-action">My Dashboard</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="../login.php" class="nav-action">Login</a>
        <?php endif; ?>
    </div>
</header>

<section class="hero">
    <span class="eyebrow">FIND YOUR NEXT TASK</span>
    <h1>Explore Tasks</h1>
    <p>Discover tasks posted by customers and find work that suits your skills.</p>
</section>

<main class="container">

    <section class="filters">
        <form method="GET" action="browse.php">
            <input
                type="search"
                name="q"
                placeholder="Search title, description, location..."
                value="<?= browse_e($search) ?>"
                aria-label="Search tasks"
            >

            <select name="category" aria-label="Filter by category">
                <option value="">All Categories</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int)$category['id'] ?>"
                        <?= $categoryId !== ''
                            && ctype_digit($categoryId)
                            && (int)$categoryId === (int)$category['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= browse_e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-primary" type="submit">Search Tasks</button>
            <a class="btn btn-light" href="browse.php">Reset</a>
        </form>
    </section>

    <div class="results-heading">
        <h2>
            <?php if ($search !== '' || $categoryId !== ''): ?>
                Search Results
            <?php else: ?>
                Latest Tasks
            <?php endif; ?>
        </h2>

        <span class="result-count">
            <?= count($tasks) ?> task<?= count($tasks) === 1 ? '' : 's' ?> found
        </span>
    </div>

    <?php if (empty($tasks)): ?>

        <section class="empty">
            <div class="empty-icon">🔎</div>
            <h2>No tasks found</h2>
            <p>Try a different keyword or select another category.</p>
            <a href="browse.php" class="btn btn-primary">View All Tasks</a>
        </section>

    <?php else: ?>

        <section class="task-grid">
            <?php foreach ($tasks as $task): ?>
                <article class="task-card">

                    <span class="category">
                        <?= browse_e($task['category_name'] ?? 'Other') ?>
                    </span>

                    <h3>
                        <a href="task-details.php?id=<?= (int)$task['id'] ?>">
                            <?= browse_e($task['title']) ?>
                        </a>
                    </h3>

                    <p class="description">
                        <?php
                        $description = (string)$task['description'];
                        echo browse_e(
                            strlen($description) > 180
                                ? substr($description, 0, 180) . '...'
                                : $description
                        );
                        ?>
                    </p>

                    <div class="task-meta">
                        <span>📍 <strong>Location:</strong>
                            <?= browse_e($task['location']) ?>
                        </span>

                        <span>👤 <strong>Customer:</strong>
                            <a class="profile-link" href="user-profile.php?id=<?= (int)$task['customer_id'] ?>">
                                <?= browse_e($task['customer_name']) ?> · View Profile
                            </a>
                        </span>

                        <span>📅
                            <?= browse_e(date(
                                'd M Y',
                                strtotime($task['created_at'])
                            )) ?>
                        </span>
                    </div>

                    <div class="card-bottom">
                        <div class="budget">
                            <?php if ($task['budget'] !== null): ?>
                                ৳<?= number_format((float)$task['budget'], 2) ?>
                            <?php else: ?>
                                Negotiable
                            <?php endif; ?>
                            <small>BDT</small>
                        </div>

                        <a
                            href="task-details.php?id=<?= (int)$task['id'] ?>"
                            class="details-btn"
                        >View Details →</a>
                    </div>

                </article>
            <?php endforeach; ?>
        </section>

    <?php endif; ?>

</main>

<footer>© <?= date('Y') ?> TaskMate. Connecting people with the right help.</footer>

</body>
</html>
