
<?php
session_start();

require_once __DIR__ . '/config/database.php';

$email = strtolower(trim($_SESSION['pending_email'] ?? ''));
$message = '';

// Show any message returned from send_otp.php.
if (isset($_SESSION['otp_notice'])) {
    $message = $_SESSION['otp_notice'];
    unset($_SESSION['otp_notice']);
}

// User must have a pending registration email.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php');
    exit;
}

// Find the account.
$stmt = $pdo->prepare(
    'SELECT id, email_verified_at
     FROM users
     WHERE email = ?
     LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['pending_email']);
    header('Location: register.php');
    exit;
}

// Already verified? Go to login.
if ($user['email_verified_at'] !== null) {
    unset($_SESSION['pending_email']);
    header('Location: login.php?verified=1');
    exit;
}

// Process submitted OTP.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['verify_otp'])
) {
    $otp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        $message = 'Please enter a valid 6-digit OTP.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, otp_hash, expires_at, attempts
             FROM email_otps
             WHERE email = ?
               AND purpose = 'signup'
               AND consumed_at IS NULL
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $record = $stmt->fetch();

        if (!$record) {
            $message = 'No active OTP found. Click Send / Resend OTP.';
        } elseif ((int) $record['attempts'] >= 5) {
            $message = 'Too many attempts. Request a new OTP.';
        } elseif (strtotime($record['expires_at']) < time()) {
            $message = 'Your OTP has expired. Request a new one.';
        } else {
            // Count this verification attempt.
            $update = $pdo->prepare(
                'UPDATE email_otps
                 SET attempts = attempts + 1
                 WHERE id = ?'
            );
            $update->execute([$record['id']]);

            if (password_verify($otp, $record['otp_hash'])) {
                try {
                    $pdo->beginTransaction();

                    $verify = $pdo->prepare(
                        'UPDATE users
                         SET email_verified_at = NOW()
                         WHERE id = ?
                           AND email_verified_at IS NULL'
                    );
                    $verify->execute([$user['id']]);

                    $consume = $pdo->prepare(
                        'UPDATE email_otps
                         SET consumed_at = NOW()
                         WHERE id = ?
                           AND consumed_at IS NULL'
                    );
                    $consume->execute([$record['id']]);

                    $pdo->commit();

                    unset($_SESSION['pending_email']);

                    header('Location: login.php?verified=1');
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log(
                        'TaskMate OTP verification error: ' . $e->getMessage()
                    );

                    $message = 'Verification failed. Please try again.';
                }
            } else {
                $message = 'Incorrect OTP. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verify Email | TaskMate</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: #f4f8f3;
    color: #202820;
}

.card {
    width: 100%;
    max-width: 690px;
    padding: 48px;
    background: white;
    border: 1px solid #e3e9e1;
    border-radius: 30px;
    box-shadow: 0 16px 45px #244b2410;
}

.brand {
    color: #147b43;
    font-size: 42px;
    font-weight: 800;
    margin-bottom: 48px;
    letter-spacing: -1.5px;
}

h1 {
    font-size: 42px;
    letter-spacing: -1.5px;
    margin: 0 0 22px;
}

.description {
    color: #737b74;
    line-height: 1.8;
    font-size: 20px;
    margin-bottom: 38px;
    overflow-wrap: anywhere;
}

.email {
    color: #26352b;
    font-weight: 700;
}

.message {
    padding: 15px;
    margin-bottom: 22px;
    background: #fff1ef;
    color: #a52b1e;
    border-radius: 12px;
    font-size: 15px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

label {
    display: block;
    font-weight: 700;
    font-size: 17px;
    margin-bottom: 12px;
}

input {
    width: 100%;
    padding: 18px;
    border: 1px solid #d9ded9;
    border-radius: 15px;
    font-size: 28px;
    letter-spacing: 10px;
    text-align: center;
    outline: none;
}

input:focus {
    border-color: #229957;
    box-shadow: 0 0 0 4px #22995719;
}

button {
    width: 100%;
    padding: 20px;
    border: 0;
    border-radius: 14px;
    background: #1e2921;
    color: white;
    font-weight: 750;
    font-size: 18px;
    cursor: pointer;
    margin-top: 20px;
}

button:hover {
    background: #147b43;
}

.secondary {
    background: white;
    color: #147b43;
    border: 1px solid #d9ded9;
}

.secondary:hover {
    background: #f5faf5;
    color: #147b43;
}

.back {
    display: block;
    text-align: center;
    margin-top: 35px;
    color: #147b43;
    text-decoration: none;
    font-size: 17px;
}

@media (max-width: 600px) {
    body {
        padding: 14px;
    }

    .card {
        padding: 28px 22px;
        border-radius: 22px;
    }

    .brand {
        font-size: 32px;
        margin-bottom: 34px;
    }

    h1 {
        font-size: 31px;
    }

    .description {
        font-size: 16px;
    }

    input {
        font-size: 23px;
        letter-spacing: 7px;
    }
}
</style>
</head>

<body>
<main class="card">
    <div class="brand">TaskMate.</div>

    <h1>Verify your email</h1>

    <p class="description">
        Enter the 6-digit verification code sent to
        <span class="email"><?= htmlspecialchars(
            $email,
            ENT_QUOTES,
            'UTF-8'
        ) ?></span>
        to activate your account.
    </p>

    <?php if ($message !== ''): ?>
        <div class="message" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="verify_otp.php">
        <label for="otp">6-digit verification code</label>

        <input
            id="otp"
            name="otp"
            type="text"
            inputmode="numeric"
            pattern="[0-9]{6}"
            minlength="6"
            maxlength="6"
            autocomplete="one-time-code"
            placeholder="000000"
            required
        >

        <button type="submit" name="verify_otp" value="1">
            Verify email →
        </button>
    </form>

    <!-- Send OTP through send_otp.php using POST -->
    <form method="POST" action="send_otp.php">
        <button
            class="secondary"
            type="submit"
            name="send_otp"
            value="1"
        >
            Send / Resend OTP
        </button>
    </form>

    <a class="back" href="login.php">Back to login</a>
</main>
</body>
</html>
