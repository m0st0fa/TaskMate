
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['customer', 'helper'], true)) {
    header('Location: /dashboard/admin.php');
    exit;
}

function chatEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function chatRedirectToMessages(string $message): never
{
    $_SESSION['chat_flash'] = $message;
    header('Location: /pages/messages.php');
    exit;
}

if (empty($_SESSION['chat_csrf_token'])) {
    $_SESSION['chat_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['chat_csrf_token'];

$conversationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$taskId = filter_input(INPUT_GET, 'task_id', FILTER_VALIDATE_INT);
$requestedHelperId = filter_input(INPUT_GET, 'helper_id', FILTER_VALIDATE_INT);

try {
    /*
     * Create/open a conversation from a task.
     *
     * Customer:
     * /pages/chat.php?task_id=TASK_ID&helper_id=HELPER_ID
     *
     * Helper:
     * /pages/chat.php?task_id=TASK_ID
     */
    if ($taskId) {
        $stmt = $pdo->prepare(
            "SELECT id, customer_id, title
             FROM tasks
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            chatRedirectToMessages('Task not found.');
        }

        $customerId = (int) $task['customer_id'];

        if ($role === 'customer') {
            if ($customerId !== $userId || !$requestedHelperId) {
                chatRedirectToMessages('Invalid customer or helper.');
            }

            $helperId = (int) $requestedHelperId;
        } else {
            $helperId = $userId;
        }

        if ($helperId === $customerId) {
            chatRedirectToMessages('You cannot chat with yourself.');
        }

        // A helper must have submitted an offer for this task.
        $offerStmt = $pdo->prepare(
            "SELECT id
             FROM offers
             WHERE task_id = ?
               AND helper_id = ?
             LIMIT 1"
        );
        $offerStmt->execute([$taskId, $helperId]);

        if (!$offerStmt->fetchColumn()) {
            chatRedirectToMessages(
                'A conversation can only be opened with a helper who has offered on this task.'
            );
        }

        $helperStmt = $pdo->prepare(
            "SELECT id FROM users WHERE id = ? AND role = 'helper' LIMIT 1"
        );
        $helperStmt->execute([$helperId]);

        if (!$helperStmt->fetchColumn()) {
            chatRedirectToMessages('The selected helper account was not found.');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO conversations (task_id, customer_id, helper_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$taskId, $customerId, $helperId]);

        $stmt = $pdo->prepare(
            "SELECT id
             FROM conversations
             WHERE task_id = ?
               AND customer_id = ?
               AND helper_id = ?
             LIMIT 1"
        );
        $stmt->execute([$taskId, $customerId, $helperId]);

        $conversationId = (int) $stmt->fetchColumn();

        header('Location: /pages/chat.php?id=' . $conversationId);
        exit;
    }

    if (!$conversationId || $conversationId < 1) {
        chatRedirectToMessages('Choose a conversation to open.');
    }

    $stmt = $pdo->prepare(
        "SELECT
            c.id,
            c.task_id,
            c.customer_id,
            c.helper_id,
            c.created_at,
            t.title AS task_title,
            customer.full_name AS customer_name,
            helper.full_name AS helper_name
         FROM conversations c
         INNER JOIN tasks t ON t.id = c.task_id
         INNER JOIN users customer ON customer.id = c.customer_id
         INNER JOIN users helper ON helper.id = c.helper_id
         WHERE c.id = ?
           AND (c.customer_id = ? OR c.helper_id = ?)
         LIMIT 1"
    );
    $stmt->execute([$conversationId, $userId, $userId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conversation) {
        http_response_code(403);
        exit('You do not have permission to access this conversation.');
    }

    $otherUserId = (int) (
        (int) $conversation['customer_id'] === $userId
            ? $conversation['helper_id']
            : $conversation['customer_id']
    );

    $otherName = (int) $conversation['customer_id'] === $userId
        ? $conversation['helper_name']
        : $conversation['customer_name'];

    // Mark messages sent by the other participant as read.
    $readStmt = $pdo->prepare(
        "UPDATE messages
         SET is_read = 1
         WHERE conversation_id = ?
           AND sender_id <> ?
           AND is_read = 0"
    );
    $readStmt->execute([$conversationId, $userId]);

    $stmt = $pdo->prepare(
        "SELECT id, sender_id, message, is_read, created_at
         FROM messages
         WHERE conversation_id = ?
         ORDER BY id ASC
         LIMIT 200"
    );
    $stmt->execute([$conversationId]);
    $chatMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('TaskMate chat error: ' . $e->getMessage());
    http_response_code(500);
    exit('Could not load this conversation. Please try again.');
}

$pageTitle = 'Chat | TaskMate';
require_once __DIR__ . '/../includes/header.php';

$chatFlash = $_SESSION['chat_flash'] ?? '';
unset($_SESSION['chat_flash']);
?>

<style>
.chat-page {
    max-width: 1050px;
    margin: 0 auto;
    padding: 30px 20px 55px;
    color: #24352b;
}

.chat-shell {
    height: min(690px, calc(100vh - 190px));
    min-height: 480px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e1eae3;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(20, 50, 35, .06);
}

.chat-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px 24px;
    border-bottom: 1px solid #e8eee9;
}

.chat-back {
    color: #16834a;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
}

.chat-avatar {
    width: 45px;
    height: 45px;
    flex: 0 0 45px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #e5f5eb;
    color: #16834a;
    font-size: 19px;
    font-weight: 800;
}

.chat-person {
    min-width: 0;
    flex: 1;
}

.chat-person strong {
    display: block;
    overflow-wrap: anywhere;
}

.chat-person span {
    display: block;
    margin-top: 4px;
    color: #718076;
    font-size: 13px;
}

.chat-task-link {
    color: #16834a;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.chat-alert {
    margin: 14px 20px 0;
    padding: 11px 14px;
    border-radius: 9px;
    background: #fff7e8;
    color: #79540b;
    font-size: 14px;
}

.chat-messages {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 13px;
    overflow-y: auto;
    padding: 24px;
    background: #f8fbf9;
}

.chat-empty {
    margin: auto;
    max-width: 320px;
    text-align: center;
    color: #718076;
    line-height: 1.7;
}

.chat-row {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    max-width: 78%;
}

.chat-row.mine {
    align-self: flex-end;
    align-items: flex-end;
}

.chat-bubble {
    padding: 12px 15px;
    border: 1px solid #e0eae3;
    border-radius: 15px 15px 15px 4px;
    background: white;
    line-height: 1.6;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.chat-row.mine .chat-bubble {
    color: white;
    background: #16834a;
    border-color: #16834a;
    border-radius: 15px 15px 4px 15px;
}

.chat-meta {
    margin-top: 5px;
    color: #87948b;
    font-size: 11px;
}

.chat-composer {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    padding: 18px 20px;
    border-top: 1px solid #e8eee9;
    background: white;
}

.chat-composer textarea {
    flex: 1;
    min-width: 0;
    min-height: 46px;
    max-height: 120px;
    resize: vertical;
    padding: 12px 14px;
    border: 1px solid #dce6df;
    border-radius: 12px;
    font: inherit;
    outline: none;
}

.chat-composer textarea:focus {
    border-color: #16834a;
}

.chat-send {
    min-height: 46px;
    padding: 0 20px;
    border: 0;
    border-radius: 10px;
    background: #16834a;
    color: white;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.chat-send:disabled {
    opacity: .55;
    cursor: wait;
}

@media (max-width: 600px) {
    .chat-page { padding: 18px 10px 35px; }
    .chat-shell { height: calc(100dvh - 150px); min-height: 430px; }
    .chat-header { padding: 15px 12px; gap: 9px; }
    .chat-messages { padding: 16px 12px; }
    .chat-composer { padding: 12px; gap: 8px; }
    .chat-composer textarea { font-size: 14px; }
    .chat-send { padding: 0 13px; }
    .chat-row { max-width: 90%; }
}
</style>

<main class="chat-page">
    <section class="chat-shell">

        <header class="chat-header">
            <a class="chat-back" href="/pages/messages.php" aria-label="Back to messages">←</a>

            <div class="chat-avatar">
                <?= chatEscape(mb_strtoupper(mb_substr($otherName, 0, 1))) ?>
            </div>

            <div class="chat-person">
                <strong><?= chatEscape($otherName) ?></strong>
                <span><?= chatEscape($conversation['task_title']) ?></span>
            </div>

            <a class="chat-task-link"
               href="/pages/task-details.php?id=<?= (int) $conversation['task_id'] ?>">
                View Task
            </a>
        </header>

        <?php if ($chatFlash !== ''): ?>
            <div class="chat-alert"><?= chatEscape($chatFlash) ?></div>
        <?php endif; ?>

        <div
            class="chat-messages"
            id="chatMessages"
            data-conversation-id="<?= (int) $conversation['id'] ?>"
        >
            <?php if (!$chatMessages): ?>
                <div class="chat-empty" id="chatEmpty">
                    <strong>Start the conversation</strong>
                    <br>
                    Send a message about this task. Your messages will appear here.
                </div>
            <?php endif; ?>

            <?php foreach ($chatMessages as $msg): ?>
                <div class="chat-row <?= (int) $msg['sender_id'] === $userId ? 'mine' : '' ?>"
                     data-message-id="<?= (int) $msg['id'] ?>">
                    <div class="chat-bubble"><?= chatEscape($msg['message']) ?></div>
                    <div class="chat-meta">
                        <?= date('d M, h:i A', strtotime($msg['created_at'])) ?>
                        <?php if ((int) $msg['sender_id'] === $userId && (int) $msg['is_read'] === 1): ?>
                            · Read
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <form class="chat-composer" id="chatForm">
            <input type="hidden" name="csrf_token" value="<?= chatEscape($csrfToken) ?>">
            <input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>">

            <textarea
                name="message"
                id="chatInput"
                maxlength="2000"
                rows="1"
                placeholder="Write a message..."
                aria-label="Message"
                required
            ></textarea>

            <button class="chat-send" id="chatSend" type="submit">Send</button>
        </form>

    </section>
</main>

<script>
(function () {
    const messagesBox = document.getElementById('chatMessages');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const sendButton = document.getElementById('chatSend');
    const conversationId = messagesBox.dataset.conversationId;

    let newestMessageId = 0;
    let loading = false;

    messagesBox.querySelectorAll('[data-message-id]').forEach(function (item) {
        newestMessageId = Math.max(
            newestMessageId,
            Number(item.dataset.messageId || 0)
        );
    });

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function appendMessage(message) {
        const empty = document.getElementById('chatEmpty');
        if (empty) empty.remove();

        const row = document.createElement('div');
        row.className = message.is_mine ? 'chat-row mine' : 'chat-row';
        row.dataset.messageId = message.id;

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.textContent = message.message;

        const meta = document.createElement('div');
        meta.className = 'chat-meta';
        meta.textContent = message.created_at + (
            message.is_mine && message.is_read ? ' · Read' : ''
        );

        row.appendChild(bubble);
        row.appendChild(meta);
        messagesBox.appendChild(row);

        newestMessageId = Math.max(newestMessageId, Number(message.id));
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    async function fetchMessages() {
        if (loading) return;
        loading = true;

        try {
            const response = await fetch(
                '/pages/chat-fetch.php?conversation_id=' +
                encodeURIComponent(conversationId) +
                '&after_id=' + encodeURIComponent(newestMessageId),
                {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                }
            );

            if (!response.ok) return;

            const data = await response.json();
            if (!data.success || !Array.isArray(data.messages)) return;

            data.messages.forEach(appendMessage);
        } catch (error) {
            // Keep the chat usable if a refresh fails temporarily.
        } finally {
            loading = false;
        }
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const message = input.value.trim();
        if (!message || sendButton.disabled) return;

        sendButton.disabled = true;

        try {
            const response = await fetch('/pages/chat-send.php', {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                alert(data.message || 'Could not send the message.');
                return;
            }

            input.value = '';
            appendMessage(data.message);
            await fetchMessages();
        } catch (error) {
            alert('Network error. Please try again.');
        } finally {
            sendButton.disabled = false;
            input.focus();
        }
    });

    messagesBox.scrollTop = messagesBox.scrollHeight;

    // The fetch endpoint will be added next.
    window.setInterval(fetchMessages, 3000);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
