<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --cream:     #F2F0EB;
            --teal:      #0097A7;
            --teal-dim:  rgba(0, 151, 167, 0.18);
            --slate:     #6B7B8D;
            --ink:       #1A1C1E;
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            min-height: 100%;
        }

        body {
            background-color: var(--cream);
            font-family: 'Jost', sans-serif;
            color: var(--ink);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100dvh;
            padding: 3rem 2rem 4rem;
            position: relative;
        }

        /* Teal-tinted paper grain */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch' result='noise'/%3E%3CfeColorMatrix type='luminanceToAlpha' in='noise' result='luma'/%3E%3CfeFlood flood-color='%230097A7' result='color'/%3E%3CfeComposite in='color' in2='luma' operator='in'/%3E%3C/filter%3E%3Crect width='200' height='200' filter='url(%23n)'/%3E%3C/svg%3E");
            opacity: 0.08;
            pointer-events: none;
            z-index: 999;
        }

        /* ── Layout ── */
        .page {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 640px;
        }

        /* ── Logo ── */
        .logo-wrap {
            opacity: 0;
            animation: rise 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.1s forwards;
            margin-bottom: 2rem;
        }

        .logo-wrap img {
            height: 52px;
            width: auto;
            display: block;
        }

        /* ── Content block: badge → headline → rule → tagline ── */
        .content {
            text-align: center;
            opacity: 0;
            animation: rise 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.28s forwards;
            padding: 0 0 2rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1.5px solid var(--teal);
            color: var(--teal);
            font-family: 'Jost', sans-serif;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            padding: 0.35rem 1rem;
            border-radius: 999px;
            margin-bottom: 1.6rem;
        }

        .badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--teal);
            animation: pulse 2s ease-in-out infinite;
        }

        h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.2rem, 6vw, 3.5rem);
            font-weight: 500;
            line-height: 1.18;
            letter-spacing: -0.01em;
            color: var(--ink);
            margin-bottom: 1.25rem;
        }

        h1 em {
            font-style: italic;
            color: var(--teal);
        }

        .rule {
            width: 36px;
            height: 1.5px;
            background: var(--teal);
            margin: 0 auto 1.25rem;
            opacity: 0.5;
        }

        .tagline {
            font-size: 0.875rem;
            font-weight: 300;
            color: var(--slate);
            letter-spacing: 0.02em;
            line-height: 1.75;
        }

        /* ── Illustration ── */
        .illustration-wrap {
            width: 100%;
            position: relative;
            opacity: 0;
            animation: rise 1s cubic-bezier(0.22, 1, 0.36, 1) 0.55s forwards;
        }

        /* Diagonal hatch — echoes the Mac SVG's own hatching language */
        .illustration-wrap::before {
            content: '';
            position: absolute;
            inset: -20% -10%;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12'%3E%3Cline x1='0' y1='12' x2='12' y2='0' stroke='%230097A7' stroke-opacity='0.07' stroke-width='1'/%3E%3C/svg%3E");
            mask-image: radial-gradient(ellipse 80% 75% at 50% 55%, black 15%, transparent 68%);
            -webkit-mask-image: radial-gradient(ellipse 80% 75% at 50% 55%, black 15%, transparent 68%);
            pointer-events: none;
            z-index: 0;
        }

        /* Float animation starts after entrance (0.55s delay + 1s duration = 1.55s) */
        .illustration-img {
            height: clamp(320px, 55vh, 520px);
            width: auto;
            max-width: 100%;
            display: block;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            animation: bob 4s ease-in-out 1.6s infinite;
        }

        /* ── Footer ── */
        .footer {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.7rem;
            font-weight: 400;
            letter-spacing: 0.08em;
            color: var(--slate);
            opacity: 0;
            animation: rise 0.8s ease 1.1s forwards;
            white-space: nowrap;
        }

        /* ── Animations ── */
        @keyframes rise {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.5; transform: scale(0.75); }
        }

        @keyframes bob {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-10px); }
        }
    </style>
</head>
<body>
    <div class="page">

        <div class="logo-wrap">
            <img src="/images/dimak-logo.png" alt="{{ config('app.name') }}">
        </div>

        <div class="content">
            <div class="badge">Próximamente</div>
            <h1>Expertos en lo que<br><em>más importa.</em></h1>
            <div class="rule"></div>
            <p class="tagline">Estamos trabajando en algo para ti.<br>Vuelve pronto.</p>
        </div>

        <div class="illustration-wrap">
            <img class="illustration-img" src="/images/macintosh.svg" alt="Dimak — reparación de dispositivos">
        </div>

    </div>

    <footer class="footer">© {{ date('Y') }} {{ config('app.name') }}</footer>
</body>
</html>
