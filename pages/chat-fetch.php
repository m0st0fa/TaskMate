
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function chatFetchResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    chatFetchResponse(405, [
        'success' => false,
        'message' => 'GET method required.'
    ]);
}

if (empty($_SESSION['user_id'])) {
    chatFetchResponse(401, [
        'success' => false,
        'message' => 'Please log in again.'
    ]);
}

if (!in_array($_SESSION['role'] ?? '', ['customer', 'helper'], true)) {
    chatFetchResponse(403, [
        'success' => false,
        'message' => 'Access denied.'
    ]);
}

$conversationId = filter_var(
    $_GET['conversation_id'] ?? null,
    FILTER_VALIDATE_INT
);

$afterId = filter_var(
    $_GET['after_id'] ?? 0,
    FILTER_VALIDATE_INT
);

if (!$conversationId || $conversationId < 1 || $afterId === false || $afterId < 0) {
    chatFetchResponse(422, [
        'success' => false,
        'message' => 'Invalid request.'
    ]);
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT id
         FROM conversations
         WHERE id = ?
           AND (customer_id = ? OR helper_id = ?)
         LIMIT 1"
    );
    $stmt->execute([$conversationId, $userId, $userId]);

    if (!$stmt->fetchColumn()) {
        chatFetchResponse(403, [
            'success' => false,
            'message' => 'You cannot access this conversation.'
        ]);
    }

    // Load new messages only.
    $stmt = $pdo->prepare(
        "SELECT id, sender_id, message, is_read, created_at
         FROM messages
         WHERE conversation_id = ?
           AND id > ?
         ORDER BY id ASC
         LIMIT 100"
    );
    $stmt->execute([$conversationId, $afterId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $messages = [];

    foreach ($rows as $row) {
        $isMine = (int) $row['sender_id'] === $userId;

        // Mark messages received by this user as read.
        if (!$isMine && (int) $row['is_read'] === 0) {
            $readStmt = $pdo->prepare(
                "UPDATE messages
                 SET is_read = 1
                 WHERE id = ?
                   AND conversation_id = ?
                   AND sender_id <> ?"
            );
            $readStmt->execute([
                (int) $row['id'],
                $conversationId,
                $userId
            ]);
        }

        $messages[] = [
            'id' => (int) $row['id'],
            'message' => $row['message'],
            'created_at' => date(
                'd M, h:i A',
                strtotime($row['created_at'])
            ),
            'is_mine' => $isMine,
            'is_read' => !$isMine ? true : (bool) $row['is_read']
        ];
    }

    chatFetchResponse(200, [
        'success' => true,
        'messages' => $messages
    ]);

} catch (Throwable $e) {
    error_log('TaskMate chat-fetch error: ' . $e->getMessage());

    chatFetchResponse(500, [
        'success' => false,
        'message' => 'Could not load messages.'
    ]);
}
