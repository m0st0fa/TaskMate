
<?php
require __DIR__ . '/../config/database.php';

$categoryId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$categoryId || $categoryId < 1) {
    http_response_code(404);
    exit('Category not found.');
}

// Get category details.
$stmt = $pdo->prepare(
    'SELECT id, name, description
     FROM categories
     WHERE id = ?'
);
$stmt->execute([$categoryId]);
$category = $stmt->fetch();

if (!$category) {
    http_response_code(404);
    exit('Category not found.');
}

// Get tasks in this category.
$stmt = $pdo->prepare(
    'SELECT id, title, description, location, budget, status, created_at
     FROM tasks
     WHERE category_id = ?
     ORDER BY created_at DESC, id DESC'
);
$stmt->execute([$categoryId]);
$tasks = $stmt->fetchAll();

$pageTitle = $category['name'] . ' | TaskMate';

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

require __DIR__ . '/../includes/header.php';
?>

<section class="container" style="padding-top:40px;padding-bottom:60px">

    <p>
        <a href="../index.php">Home</a>
        /
        <?= e($category['name']) ?>
    </p>

    <h1><?= e($category['name']) ?></h1>

    <p><?= e($category['description']) ?></p>

    <hr>

    <h2>Available Tasks</h2>

    <?php if (empty($tasks)): ?>

        <p>
            No tasks have been posted in this category yet.
            Please check back later.
        </p>

    <?php else: ?>

        <div class="tasks">

            <?php foreach ($tasks as $task): ?>

                <article class="task">
                    <div class="task-body">

                        <h3><?= e($task['title']) ?></h3>

                        <p><?= e($task['description']) ?></p>

                        <p>
                            <strong>Location:</strong>
                            <?= e($task['location']) ?>
                        </p>

                        <p>
                            <strong>Budget:</strong>
                            ৳<?= e($task['budget']) ?>
                        </p>

                        <p>
                            <strong>Status:</strong>
                            <?= e($task['status']) ?>
                        </p>

                    </div>
                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
