
<?php
session_start();
require_once __DIR__ . '/config/database.php';

$message = '';
$email = '';

if (isset($_GET['registered'])) {
    $message = 'Account created! Please verify your email before logging in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $message = 'Please enter a valid email and password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, full_name, email, password, role,
                    email_verified_at
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (
            $user &&
            $user['password'] !== null &&
            password_verify($password, $user['password'])
        ) {
            if ($user['email_verified_at'] === null) {
                $_SESSION['pending_email'] = $user['email'];
            
                header('Location: verify_otp.php');
                exit;
            }else {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                header('Location: index.php');
                exit;
            }
        } else {
            $message = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | TaskMate</title>

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
    background: #fff;
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

.hero-content > p {
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
    color: #405442;
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
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 14px;
    background: #fff;
    border: 1px solid #d9ded9;
    border-radius: 12px;
    color: #29312a;
    font-size: 15px;
    font-weight: 650;
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

.message {
    background: #fff1ef;
    border: 1px solid #ffd3cc;
    color: #a52b1e;
    padding: 12px;
    border-radius: 9px;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 18px;
    overflow-wrap: anywhere;
}

.field {
    margin-bottom: 19px;
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
    background: #fff;
    font-size: 15px;
    outline: none;
    transition: border .2s, box-shadow .2s;
}

.field input:focus {
    border-color: #229957;
    box-shadow: 0 0 0 3px #22995719;
}

.forgot {
    display: block;
    text-align: right;
    margin-top: -8px;
    margin-bottom: 20px;
    color: #147b43;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
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
    cursor: pointer;
    transition: background .2s, transform .2s;
}

.submit-btn:hover {
    background: #147b43;
    transform: translateY(-1px);
}

.bottom-text {
    text-align: center;
    margin-top: 27px;
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
        margin: 35px 0 10px;
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
            <h1>Welcome<br>back to<br><span>better days.</span></h1>

            <p>
                Your next task is just a few clicks away.
                Sign in to connect with trusted people
                and keep your day moving.
            </p>

            <div class="art">
                <div class="orbit"></div>
                <div class="art-card">
                    <div class="art-icon">🤝</div>
                    <strong>Good to have you back!</strong>
                    <p>Your tasks and trusted connections are waiting.</p>
                </div>
            </div>
        </div>

        <div class="hero-footer">
            Your everyday tasks, made easier with TaskMate.
        </div>
    </section>

    <main class="form-side">
        <div class="form-wrap">

            <h2>Welcome back</h2>
            <p class="subtitle">
                Log in to your TaskMate account to continue.
            </p>

            <a class="google-btn" href="#"
               onclick="alert('Google login will be connected in the OAuth step.'); return false;">
                <span class="google-g">G</span>
                Continue with Google
            </a>

            <div class="separator">OR CONTINUE WITH EMAIL</div>

            <?php if ($message !== ''): ?>
                <div class="message" role="alert">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label for="email">Email address</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        placeholder="you@example.com"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <a class="forgot" href="forgot_password.php">
                    Forgot password?
                </a>

                <button class="submit-btn" type="submit">
                    Log in to TaskMate →
                </button>
            </form>

            <p class="terms">
                By logging in, you agree to TaskMate's
                Terms of Service and Privacy Policy.
            </p>

            <p class="bottom-text">
                Don't have an account?
                <a href="register.php">Create account</a>
            </p>

        </div>
    </main>

</div>
</body>
</html>
