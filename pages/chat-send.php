
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function chatSendResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chatSendResponse(405, [
        'success' => false,
        'message' => 'POST method required.'
    ]);
}

if (empty($_SESSION['user_id'])) {
    chatSendResponse(401, [
        'success' => false,
        'message' => 'Please log in again.'
    ]);
}

if (!in_array($_SESSION['role'] ?? '', ['customer', 'helper'], true)) {
    chatSendResponse(403, [
        'success' => false,
        'message' => 'Access denied.'
    ]);
}

$token = $_POST['csrf_token'] ?? '';

if (
    !is_string($token) ||
    empty($_SESSION['chat_csrf_token']) ||
    !hash_equals($_SESSION['chat_csrf_token'], $token)
) {
    chatSendResponse(403, [
        'success' => false,
        'message' => 'Security token expired. Refresh the page.'
    ]);
}

$conversationId = filter_var(
    $_POST['conversation_id'] ?? null,
    FILTER_VALIDATE_INT
);

$message = trim((string) ($_POST['message'] ?? ''));

if (!$conversationId || $conversationId < 1) {
    chatSendResponse(422, [
        'success' => false,
        'message' => 'Invalid conversation.'
    ]);
}

if ($message === '' || mb_strlen($message) > 2000) {
    chatSendResponse(422, [
        'success' => false,
        'message' => 'Message must contain 1–2000 characters.'
    ]);
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id
         FROM conversations
         WHERE id = ?
           AND (customer_id = ? OR helper_id = ?)
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$conversationId, $userId, $userId]);

    if (!$stmt->fetchColumn()) {
        $pdo->rollBack();

        chatSendResponse(403, [
            'success' => false,
            'message' => 'You cannot send messages in this conversation.'
        ]);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO messages (conversation_id, sender_id, message)
         VALUES (?, ?, ?)"
    );
    $stmt->execute([$conversationId, $userId, $message]);

    $messageId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "UPDATE conversations
         SET updated_at = CURRENT_TIMESTAMP
         WHERE id = ?"
    );
    $stmt->execute([$conversationId]);

    $stmt = $pdo->prepare(
        "SELECT id, message, created_at
         FROM messages
         WHERE id = ?"
    );
    $stmt->execute([$messageId]);
    $savedMessage = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    chatSendResponse(200, [
        'success' => true,
        'message' => [
            'id' => (int) $savedMessage['id'],
            'message' => $savedMessage['message'],
            'created_at' => date(
                'd M, h:i A',
                strtotime($savedMessage['created_at'])
            ),
            'is_mine' => true,
            'is_read' => false
        ]
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('TaskMate chat-send error: ' . $e->getMessage());

    chatSendResponse(500, [
        'success' => false,
        'message' => 'Could not send the message. Please try again.'
    ]);
}
