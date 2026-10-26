
<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

function returnToVerify(string $message): never
{
    $_SESSION['otp_notice'] = $message;
    header('Location: verify_otp.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Please use the Send / Resend OTP button.');
}

$email = strtolower(trim($_SESSION['pending_email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    returnToVerify('Session expired. Please register or log in again.');
}

try {
    // Find the account.
    $stmt = $pdo->prepare(
        'SELECT id, email_verified_at
         FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        returnToVerify('Account not found. Please register again.');
    }

    if ($user['email_verified_at'] !== null) {
        unset($_SESSION['pending_email']);
        header('Location: login.php?verified=1');
        exit;
    }

    // Enforce a 60-second resend delay.
    $recent = $pdo->prepare(
        "SELECT id FROM email_otps
         WHERE email = ? AND purpose = 'signup'
           AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
         LIMIT 1"
    );
    $recent->execute([$email]);

    if ($recent->fetch()) {
        returnToVerify(
            'Please wait 60 seconds before requesting another OTP.'
        );
    }

    // Generate a secure six-digit code.
    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    // Replace any older signup code.
    $delete = $pdo->prepare(
        "DELETE FROM email_otps
         WHERE email = ? AND purpose = 'signup'"
    );
    $delete->execute([$email]);

    $insert = $pdo->prepare(
        "INSERT INTO email_otps
            (email, otp_hash, purpose, expires_at)
         VALUES (?, ?, 'signup', ?)"
    );
    $insert->execute([$email, $otpHash, $expiresAt]);

    $config = require __DIR__ . '/config/mail.php';

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) $config['port'];
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 20;

    $mail->setFrom(
        $config['from_email'],
        $config['from_name']
    );
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Your TaskMate verification code';

    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

    $mail->Body = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:auto">
            <h2 style="color:#147b43">Verify your TaskMate email</h2>
            <p>Your verification code is:</p>
            <div style="font-size:32px;font-weight:bold;letter-spacing:8px">'
                . $safeOtp .
            '</div>
            <p>This code expires in 5 minutes. Do not share it.</p>
        </div>';

    $mail->AltBody =
        "Your TaskMate verification code is $otp. It expires in 5 minutes.";

    $mail->send();

    returnToVerify(
        'OTP sent successfully! Check this email inbox and Spam folder.'
    );

} catch (\Throwable $e) {
    // Keep detailed errors in the local PHP server log only.
    error_log('TaskMate OTP error: ' . $e->getMessage());

    returnToVerify(
        'OTP could not be sent. Check the PHP terminal for the error.'
    );
}
