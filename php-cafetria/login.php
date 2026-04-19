<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/admin-dashboard.php' : 'api_login_and_UserPages/user-home.php'));
    exit;
}

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->
prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]); $user = $stmt->fetch(); if ($user &&
password_verify($password, $user['password'])) { $_SESSION['user_id'] =
$user['id']; $_SESSION['name'] = $user['name']; $_SESSION['role'] =
$user['role']; $_SESSION['room'] = $user['room']; header('Location: ' .
($user['role'] === 'admin' ? 'admin/admin-dashboard.php' :
'api_login_and_UserPages/user-home.php')); exit; } else { $error = 'Invalid email or password.'; }
} } ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cafetria — Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;1,400&family=Jost:wght@200;300;400&display=swap"
        rel="stylesheet" />
    <style>
    *,
    *::before,
    *::after {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --gold: #c9a14a;
        --cream: #f0e6d0;
        --dark: #0e0a06;
        --brown: #1c1108;
        --mid: #2a1a09;
        --error: #e05c5c;
    }

    body {
        background: var(--dark);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Jost', sans-serif;
        font-weight: 300;
        overflow: hidden;
    }

    canvas {
        position: fixed;
        inset: 0;
        z-index: 0;
    }

    /* ── Card ── */
    .login-card {
        position: relative;
        z-index: 1;
        width: min(420px, calc(100vw - 2rem));
        background: rgba(28, 17, 8, 0.85);
        border: 1px solid rgba(201, 161, 74, 0.18);
        backdrop-filter: blur(12px);
        padding: 2.8rem 2.5rem 2.5rem;
        opacity: 0;
        animation: fadeUp 0.9s 0.15s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* ── Logo ── */
    .logo {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 2rem;
    }

    .logo-ring {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--mid);
        border: 1px solid rgba(201, 161, 74, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        box-shadow: 0 0 32px rgba(201, 161, 74, 0.1);
    }

    .logo-ring svg {
        width: 26px;
        height: 26px;
        color: var(--gold);
    }

    .logo h1 {
        font-family: 'Cormorant Garamond', serif;
        font-weight: 300;
        font-size: 2.2rem;
        color: var(--cream);
        letter-spacing: -0.01em;
        line-height: 1;
    }

    .logo h1 em {
        font-style: italic;
        color: var(--gold);
    }

    .logo p {
        font-size: 0.62rem;
        letter-spacing: 0.4em;
        text-transform: uppercase;
        color: rgba(201, 161, 74, 0.45);
        margin-top: 0.3rem;
    }

    /* ── Divider ── */
    .divider {
        height: 1px;
        background: linear-gradient(to right,
                transparent,
                rgba(201, 161, 74, 0.25),
                transparent);
        margin-bottom: 1.8rem;
    }

    /* ── Form ── */
    .field {
        margin-bottom: 1.2rem;
    }

    label {
        display: block;
        font-size: 0.65rem;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: rgba(201, 161, 74, 0.7);
        margin-bottom: 0.5rem;
    }

    .input-wrap {
        position: relative;
    }

    .input-wrap>svg {
        position: absolute;
        left: 0.9rem;
        top: 50%;
        transform: translateY(-50%);
        width: 15px;
        height: 15px;
        color: rgba(201, 161, 74, 0.4);
        pointer-events: none;
        transition: color 0.2s;
    }

    input[type='email'],
    input[type='password'],
    input[type='text'] {
        width: 100%;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(201, 161, 74, 0.15);
        color: var(--cream);
        font-family: 'Jost', sans-serif;
        font-weight: 300;
        font-size: 0.9rem;
        padding: 0.78rem 0.9rem 0.78rem 2.5rem;
        outline: none;
        transition:
            border-color 0.2s,
            background 0.2s;
        -webkit-appearance: none;
    }

    input::placeholder {
        color: rgba(240, 230, 208, 0.2);
    }

    input:focus {
        border-color: rgba(201, 161, 74, 0.55);
        background: rgba(255, 255, 255, 0.07);
    }

    input:focus+svg,
    .input-wrap:focus-within svg {
        color: var(--gold);
    }

    /* Eye toggle */
    .eye-btn {
        position: absolute;
        right: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: rgba(201, 161, 74, 0.35);
        display: flex;
        align-items: center;
        transition: color 0.2s;
        padding: 0;
    }

    .eye-btn:hover {
        color: var(--gold);
    }

    .eye-btn svg {
        width: 16px;
        height: 16px;
    }

    /* ── Error ── */
    .error-msg {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(224, 92, 92, 0.08);
        border: 1px solid rgba(224, 92, 92, 0.25);
        color: #e08080;
        font-size: 0.78rem;
        padding: 0.65rem 0.9rem;
        margin-bottom: 1.2rem;
        animation: shake 0.4s ease;
    }

    .error-msg svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
        fill: currentColor;
    }

    /* ── Field error ── */
    .field-error {
        font-size: 0.68rem;
        color: #e08080;
        margin-top: 0.35rem;
        display: none;
    }

    .field.invalid input {
        border-color: rgba(224, 92, 92, 0.45);
    }

    .field.invalid .field-error {
        display: block;
    }

    /* ── Submit ── */
    button[type='submit'] {
        width: 100%;
        padding: 0.9rem;
        background: var(--gold);
        color: var(--dark);
        font-family: 'Jost', sans-serif;
        font-weight: 400;
        font-size: 0.78rem;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        border: none;
        cursor: pointer;
        margin-top: 0.4rem;
        transition:
            background 0.25s,
            transform 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    button[type='submit']:hover {
        background: var(--cream);
        transform: translateY(-1px);
    }

    button[type='submit']:active {
        transform: translateY(0);
    }

    button[type='submit'] svg {
        width: 14px;
        height: 14px;
        fill: currentColor;
    }

    /* ── Back ── */
    .back-link {
        position: fixed;
        top: 1.5rem;
        left: 1.8rem;
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.65rem;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: rgba(201, 161, 74, 0.4);
        text-decoration: none;
        transition: color 0.2s;
    }

    .back-link:hover {
        color: var(--gold);
    }

    .back-link svg {
        width: 14px;
        height: 14px;
        fill: currentColor;
    }

    /* ── Animations ── */
    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(16px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes shake {

        0%,
        100% {
            transform: translateX(0);
        }

        20% {
            transform: translateX(-6px);
        }

        40% {
            transform: translateX(6px);
        }

        60% {
            transform: translateX(-4px);
        }

        80% {
            transform: translateX(4px);
        }
    }
    </style>
</head>

<body>
    <canvas id="c"></canvas>

    <a href="index.php" class="back-link">
        <svg viewBox="0 0 24 24">
            <path d="M15 19l-7-7 7-7" />
        </svg>
        Back
    </a>

    <div class="login-card">
        <!-- Logo -->
        <div class="logo">
            <div class="logo-ring">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                    <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                    <line x1="6" y1="1" x2="6" y2="4" />
                    <line x1="10" y1="1" x2="10" y2="4" />
                    <line x1="14" y1="1" x2="14" y2="4" />
                </svg>
            </div>
            <h1>
                Cafe
                <em>tria</em>
            </h1>
            <p>Sign in to continue</p>
        </div>

        <div class="divider"></div>

        <!-- Server-side error -->
        <?php if ($error): ?>
        <div class="error-msg">
            <svg viewBox="0 0 24 24">
                <path
                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form id="loginForm" method="POST" action="" novalidate>
            <div class="field" id="f-email">
                <label for="email">Email Address</label>
                <div class="input-wrap">
                    <input type="email" id="email" name="email" placeholder="you@company.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" />
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                </div>
                <span class="field-error" id="err-email">
                    Please enter a valid email address.
                </span>
            </div>

            <div class="field" id="f-password">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password" placeholder="••••••••"
                        autocomplete="current-password" />
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                        stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <button type="button" class="eye-btn" id="eyeBtn" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                </div>
                <span class="field-error" id="err-password">
                    Password is required.
                </span>
            </div>

            <button type="submit">
                <svg viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5v10z" />
                </svg>
                Sign In
            </button>
        </form>
    </div>

    <script>
    /* ── Canvas background ── */
    const c = document.getElementById('c'),
        ctx = c.getContext('2d');
    let W, H;
    const frames = [];
    const totalFrames = 238;
    let currentFrame = 1;
    let loadedCount = 0;
    let isLoaded = false;

    function resize() {
        W = c.width = innerWidth;
        H = c.height = innerHeight;
    }

    // Preload frames
    function preload() {
        for (let i = 1; i <= totalFrames; i++) {
            const img = new Image();
            const frameNum = i.toString().padStart(3, '0');
            img.src = `frames/ezgif-frame-${frameNum}.jpg`;
            img.onload = () => {
                loadedCount++;
                if (loadedCount > 30) isLoaded = true;
            };
            frames[i] = img;
        }
    }

    function draw() {
        if (isLoaded) {
            const img = frames[currentFrame];
            if (img && img.complete) {
                const imgRatio = img.width / img.height;
                const canvasRatio = W / H;
                let drawW, drawH, drawX, drawY;

                if (canvasRatio > imgRatio) {
                    drawW = W;
                    drawH = W / imgRatio;
                    drawX = 0;
                    drawY = (H - drawH) / 2;
                } else {
                    drawW = H * imgRatio;
                    drawH = H;
                    drawX = (W - drawW) / 2;
                    drawY = 0;
                }
                
                ctx.drawImage(img, drawX, drawY, drawW, drawH);
                
                ctx.fillStyle = 'rgba(10, 7, 3, 0.65)';
                ctx.fillRect(0, 0, W, H);

                if (currentFrame < totalFrames) {
                    currentFrame++;
                }
            }
        } else {
            const g = ctx.createRadialGradient(W * 0.5, H * 0.44, 0, W * 0.5, H * 0.44, W * 0.72);
            g.addColorStop(0, 'rgba(46,31,13,.9)');
            g.addColorStop(1, 'rgba(10,7,3,1)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, W, H);
        }
        requestAnimationFrame(draw);
    }

    resize();
    preload();
    draw();
    window.addEventListener('resize', resize);

    /* ── Password toggle ── */
    const pwInput = document.getElementById('password');
    const eyeBtn = document.getElementById('eyeBtn');
    const eyeIcon = document.getElementById('eyeIcon');
    eyeBtn.addEventListener('click', () => {
        const show = pwInput.type === 'password';
        pwInput.type = show ? 'text' : 'password';
        eyeIcon.innerHTML = show ?
            `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>` :
            `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    });

    /* ── Client-side validation ── */
    const form = document.getElementById('loginForm');
    const fEmail = document.getElementById('f-email');
    const fPass = document.getElementById('f-password');
    const iEmail = document.getElementById('email');
    const iPass = document.getElementById('password');

    function validateEmail(v) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    }

    function setValid(field, valid) {
        field.classList.toggle('invalid', !valid);
    }

    iEmail.addEventListener('input', () => {
        if (fEmail.classList.contains('invalid'))
            setValid(fEmail, validateEmail(iEmail.value.trim()));
    });
    iPass.addEventListener('input', () => {
        if (fPass.classList.contains('invalid'))
            setValid(fPass, iPass.value.trim() !== '');
    });

    form.addEventListener('submit', (e) => {
        let ok = true;
        if (!validateEmail(iEmail.value.trim())) {
            setValid(fEmail, false);
            ok = false;
        }
        if (iPass.value.trim() === '') {
            setValid(fPass, false);
            ok = false;
        }
        if (!ok) e.preventDefault();
    });
    </script>
</body>

</html>