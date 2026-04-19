<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/admin-dashboard.php' : 'api_login_and_UserPages/user-home.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,600;1,400&family=Jost:wght@200;300;400&display=swap"
        rel="stylesheet">
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
    }

    body {
        background: var(--dark);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-family: 'Jost', sans-serif;
        overflow: hidden;
    }

    canvas {
        position: fixed;
        inset: 0;
        z-index: 0;
    }

    .card {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        opacity: 0;
        animation: fadeUp 1s .2s cubic-bezier(.16, 1, .3, 1) forwards;
    }

    .logo-ring {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: var(--brown);
        border: 1px solid rgba(201, 161, 74, .35);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.8rem;
        box-shadow: 0 0 48px rgba(201, 161, 74, .12);
    }

    .logo-ring svg {
        width: 34px;
        height: 34px;
        color: var(--gold);
    }

    h1 {
        font-family: 'Cormorant Garamond', serif;
        font-weight: 300;
        font-size: clamp(4rem, 12vw, 5.5rem);
        line-height: 1;
        color: var(--cream);
        letter-spacing: -.02em;
        margin-bottom: .35rem;
    }

    h1 em {
        font-style: italic;
        color: var(--gold);
    }

    .sub {
        font-weight: 200;
        font-size: .68rem;
        letter-spacing: .45em;
        text-transform: uppercase;
        color: rgba(201, 161, 74, .5);
        margin-bottom: 2.8rem;
    }

    .divider {
        width: 1px;
        height: 44px;
        background: linear-gradient(to bottom, transparent, var(--gold), transparent);
        margin-bottom: 2.8rem;
    }

    a.btn {
        display: inline-flex;
        align-items: center;
        gap: .6rem;
        padding: .88rem 2.8rem;
        background: var(--gold);
        color: var(--dark);
        font-family: 'Jost', sans-serif;
        font-weight: 400;
        font-size: .76rem;
        letter-spacing: .2em;
        text-transform: uppercase;
        text-decoration: none;
        transition: background .25s, transform .25s;
    }

    a.btn:hover {
        background: var(--cream);
        transform: translateY(-2px);
    }

    a.btn svg {
        width: 13px;
        height: 13px;
        fill: currentColor;
    }

    .footer-note {
        position: fixed;
        bottom: 1.5rem;
        font-size: .58rem;
        letter-spacing: .3em;
        text-transform: uppercase;
        color: rgba(240, 230, 208, .18);
        z-index: 1;
    }

    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(18px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
</head>

<body>
    <canvas id="c"></canvas>

    <div class="card">
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
        <h1>Cafe<em>tria</em></h1>
        <p class="sub">Workplace Cafeteria</p>
        <div class="divider"></div>
        <a href="login.php" class="btn">
            <svg viewBox="0 0 24 24">
                <path d="M10 17l5-5-5-5v10z" />
            </svg>
            Get Started
        </a>
    </div>

    <p class="footer-note">&copy; <?= date('Y') ?> Cafetria &mdash; Internal Use Only</p>

    <script>
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
                if (loadedCount > 30) isLoaded = true; // Start early for smooth feel
            };
            frames[i] = img;
        }
    }

    function draw() {
        if (isLoaded) {
            const img = frames[currentFrame];
            if (img && img.complete) {
                // Draw with "cover" behavior
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
                
                // Add a subtle dark overlay to keep text readable
                ctx.fillStyle = 'rgba(10, 7, 3, 0.6)';
                ctx.fillRect(0, 0, W, H);

                if (currentFrame < totalFrames) {
                    currentFrame++;
                }
            }
        } else {
            // Loading state: just a gradient
            const g = ctx.createRadialGradient(W * .5, H * .44, 0, W * .5, H * .44, W * .72);
            g.addColorStop(0, 'rgba(46,31,13,.88)');
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
    </script>
</body>

</html>