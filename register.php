
<?php
session_start();
require_once __DIR__ . '/config/database.php';

$message = '';
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid name and email.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);

        if ($check->fetch()) {
            $message = 'This email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([$fullName, $email, $hash]);

            session_regenerate_id(true);
$_SESSION['pending_email'] = $email;

header('Location: verify_otp.php');
exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account | TaskMate</title>
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI",
                 Roboto, sans-serif;
    min-height: 100vh;
    background: #f7f9f7;
    color: #202820;
}

.page {
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: 100vh;
    background: white;
}

.hero {
    background: #c8ffad;
    padding: 44px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
}

.brand {
    font-size: 29px;
    font-weight: 800;
    letter-spacing: -1px;
    color: #147b43;
}

.brand span {
    color: #26352b;
}

.hero-content {
    max-width: 500px;
    margin: 55px auto;
}

.hero h1 {
    font-size: clamp(35px, 4vw, 56px);
    line-height: 1.1;
    letter-spacing: -2px;
    margin-bottom: 22px;
}

.hero h1 span {
    color: #16834a;
}

.hero p {
    font-size: 17px;
    line-height: 1.8;
    color: #405442;
}

.art {
    position: relative;
    height: 230px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.orbit {
    width: 210px;
    height: 210px;
    border: 2px solid #76c96c;
    border-radius: 50%;
    position: absolute;
}

.art-card {
    position: relative;
    z-index: 1;
    width: min(290px, 100%);
    padding: 28px;
    background: #fffef7;
    border: 1px solid #e2ebdc;
    border-radius: 22px;
    box-shadow: 0 15px 35px #316c301c;
    transform: rotate(-3deg);
}

.art-icon {
    font-size: 38px;
    margin-bottom: 14px;
}

.art-card strong {
    display: block;
    font-size: 19px;
    margin-bottom: 8px;
}

.art-card p {
    font-size: 14px;
    line-height: 1.6;
}

.hero-footer {
    font-size: 13px;
    color: #48654c;
}

.form-side {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 28px;
}

.form-wrap {
    width: 100%;
    max-width: 440px;
}

.form-wrap h2 {
    font-size: 34px;
    letter-spacing: -1px;
    margin-bottom: 10px;
}

.subtitle {
    color: #737b74;
    margin-bottom: 30px;
    line-height: 1.6;
}

.google-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    padding: 14px;
    background: white;
    border: 1px solid #d9ded9;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 650;
    color: #29312a;
    text-decoration: none;
    cursor: pointer;
    transition: background .2s;
}

.google-btn:hover {
    background: #f7faf7;
}

.google-g {
    font-size: 21px;
    font-weight: 800;
    color: #4285f4;
}

.separator {
    display: flex;
    align-items: center;
    gap: 15px;
    color: #8b928b;
    font-size: 13px;
    margin: 25px 0;
}

.separator::before,
.separator::after {
    content: "";
    flex: 1;
    height: 1px;
    background: #e3e7e3;
}

.field {
    margin-bottom: 18px;
}

.field label {
    display: block;
    font-size: 14px;
    font-weight: 650;
    margin-bottom: 8px;
}

.field input {
    width: 100%;
    height: 49px;
    padding: 0 14px;
    border: 1px solid #d9ded9;
    border-radius: 10px;
    background: white;
    font-size: 15px;
    outline: none;
    transition: border .2s, box-shadow .2s;
}

.field input:focus {
    border-color: #229957;
    box-shadow: 0 0 0 3px #22995719;
}

.submit-btn {
    width: 100%;
    border: none;
    border-radius: 10px;
    background: #1e2921;
    color: white;
    font-size: 15px;
    font-weight: 700;
    padding: 15px;
    margin-top: 5px;
    cursor: pointer;
    transition: background .2s, transform .2s;
}

.submit-btn:hover {
    background: #147b43;
    transform: translateY(-1px);
}

.message {
    background: #fff1ef;
    border: 1px solid #ffd3cc;
    color: #a52b1e;
    padding: 12px;
    border-radius: 9px;
    font-size: 14px;
    margin-bottom: 18px;
}

.bottom-text {
    text-align: center;
    margin-top: 26px;
    color: #737b74;
    font-size: 14px;
}

.bottom-text a {
    color: #147b43;
    font-weight: 700;
    text-decoration: none;
}

.terms {
    color: #8a918a;
    font-size: 12px;
    text-align: center;
    line-height: 1.7;
    margin-top: 20px;
}

@media (max-width: 760px) {
    .page {
        grid-template-columns: 1fr;
    }

    .hero {
        padding: 26px 24px;
    }

    .hero-content {
        margin: 42px 0 15px;
    }

    .hero h1 {
        font-size: 38px;
    }

    .art {
        height: 170px;
    }

    .orbit {
        width: 155px;
        height: 155px;
    }

    .art-card {
        width: 245px;
        padding: 18px;
    }

    .hero-footer {
        margin-top: 20px;
    }

    .form-side {
        padding: 42px 24px;
    }

    .form-wrap h2 {
        font-size: 30px;
    }
}
</style>
</head>
<body>

<div class="page">
    <section class="hero">
        <div class="brand">Task<span>Mate</span>.</div>

        <div class="hero-content">
            <h1>Good help.<br>Better days.<br><span>Made simple.</span></h1>
            <p>
                Find trusted people for everyday tasks.
                From small errands to bigger projects,
                getting help has never been easier.
            </p>

            <div class="art">
                <div class="orbit"></div>
                <div class="art-card">
                    <div class="art-icon">🤝</div>
                    <strong>Help is closer than you think.</strong>
                    <p>Connect with people who can get things done.</p>
                </div>
            </div>
        </div>

        <div class="hero-footer">
            Your everyday tasks, made easier with TaskMate.
        </div>
    </section>

    <main class="form-side">
        <div class="form-wrap">
            <h2>Create your account</h2>
            <p class="subtitle">
                Join TaskMate and get things done, the easier way.
            </p>

            <a class="google-btn" href="#"
               onclick="alert('Google login will be connected in the OAuth step.'); return false;">
                <span class="google-g">G</span>
                Sign up with Google
            </a>

            <div class="separator">OR SIGN UP WITH EMAIL</div>

            <?php if ($message !== ''): ?>
                <div class="message">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label for="full_name">Full name</label>
                    <input id="full_name" type="text"
                           name="full_name" maxlength="100"
                           placeholder="Enter your full name"
                           value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="name" required>
                </div>

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email"
                           name="email" maxlength="150"
                           placeholder="you@example.com"
                           value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="email" required>
                </div>

                <div class="field">
                    <label for="password">Create password</label>
                    <input id="password" type="password"
                           name="password" minlength="8"
                           placeholder="At least 8 characters"
                           autocomplete="new-password" required>
                </div>

                <button class="submit-btn" type="submit">
                    Create your account →
                </button>
            </form>

            <p class="terms">
                By creating an account, you agree to our
                Terms of Service and Privacy Policy.
            </p>

            <p class="bottom-text">
                Already have an account?
                <a href="login.php">Log in</a>
            </p>
        </div>
    </main>
</div>

</body>
</html>
