@php($t = fn (string $key, array $replace = []) => lang('igniter.voxpilot::branding.landing.'.$key, $replace))
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $t('meta_title') }}</title>
    <meta name="description" content="{{ $t('meta_description') }}">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#05070d">
    <meta property="og:title" content="{{ $t('meta_title') }}">
    <meta property="og:description" content="{{ $t('meta_description') }}">
    <link rel="icon" href="{{ asset('voxpilot/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('voxpilot/favicon-32x32.png') }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('voxpilot/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #05070d;
            --fg: #e9edf7;
            --muted: #8a96b4;
            --card: rgba(11, 15, 26, 0.72);
            --border: rgba(255, 255, 255, 0.08);
            --border-strong: rgba(255, 255, 255, 0.14);
            --primary: #7c6cff;
            --primary-2: #5b5cff;
            --accent: #22d3ee;
            --brand-cyan: #01b3fd;
            --success: #38e8c5;
            --radius: 14px;
            --font-sans: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            --font-display: 'Space Grotesk', Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--fg);
            font-family: var(--font-sans);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }
        img, svg { display: block; }

        /* Backdrop: mesh, masked grid and slow aurora, as on voxpilothq.io */
        .backdrop { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .mesh {
            position: absolute; inset: 0;
            background:
                radial-gradient(40rem 40rem at 12% -10%, rgba(124, 108, 255, 0.16), transparent 60%),
                radial-gradient(38rem 38rem at 95% 0%, rgba(34, 211, 238, 0.10), transparent 60%),
                radial-gradient(45rem 45rem at 50% 120%, rgba(80, 124, 255, 0.10), transparent 60%);
        }
        .grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.045) 1px, transparent 1px);
            background-size: 56px 56px;
            -webkit-mask-image: radial-gradient(ellipse at 50% 35%, #000 25%, transparent 72%);
            mask-image: radial-gradient(ellipse at 50% 35%, #000 25%, transparent 72%);
        }
        .aurora { position: absolute; border-radius: 50%; filter: blur(64px); animation: aurora 18s ease-in-out infinite alternate; }
        .aurora-1 { width: 42rem; height: 42rem; left: -14rem; top: -8rem; background: radial-gradient(circle, rgba(124, 108, 255, 0.22), transparent 70%); }
        .aurora-2 { width: 36rem; height: 36rem; right: -12rem; top: 4rem; background: radial-gradient(circle, rgba(34, 211, 238, 0.14), transparent 70%); animation-delay: -6s; }
        @keyframes aurora {
            0% { transform: translate3d(0, 0, 0) scale(1); }
            100% { transform: translate3d(4rem, 3rem, 0) scale(1.12); }
        }

        .page { position: relative; z-index: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 24px; }

        /* Header */
        .header { padding: 22px 0; }
        .header .container { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { display: inline-flex; align-items: center; gap: 12px; }
        .brand img { height: 26px; width: auto; }
        .brand-pill {
            padding: 3px 10px; border: 1px solid var(--border-strong); border-radius: 999px;
            font-size: 11px; font-weight: 700; letter-spacing: 0.14em; color: var(--accent);
            background: rgba(34, 211, 238, 0.06);
        }

        .glass {
            background: var(--card);
            border: 1px solid var(--border);
            -webkit-backdrop-filter: blur(18px) saturate(140%);
            backdrop-filter: blur(18px) saturate(140%);
        }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            height: 48px; padding: 0 26px; border-radius: 12px;
            font-family: var(--font-sans); font-size: 15px; font-weight: 600; white-space: nowrap;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease, background-color 0.2s ease;
        }
        .btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
        .btn svg { width: 18px; height: 18px; flex: none; }
        .btn-primary {
            color: #fff;
            background: linear-gradient(90deg, var(--primary), var(--primary-2));
            box-shadow: 0 8px 32px -8px rgba(124, 108, 255, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.18);
        }
        .btn-primary:hover { filter: brightness(1.1); box-shadow: 0 10px 44px -6px rgba(124, 108, 255, 0.95), inset 0 1px 0 rgba(255, 255, 255, 0.18); transform: translateY(-1px); }
        .btn-primary:hover .arrow { transform: translateX(3px); }
        .arrow { transition: transform 0.2s ease; }
        .btn-ghost { color: var(--fg); }
        .btn-ghost:hover { background: rgba(255, 255, 255, 0.07); }
        .btn-sm { height: 40px; padding: 0 18px; font-size: 14px; border-radius: 10px; }

        /* Hero */
        .hero { flex: 1; display: flex; align-items: center; padding: 40px 0 56px; }
        .hero .container { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr); gap: 56px; align-items: center; }

        .badge {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 7px 16px 7px 12px; border-radius: 999px;
            font-size: 13.5px; color: rgba(233, 237, 247, 0.85);
        }
        .ping { position: relative; width: 8px; height: 8px; flex: none; }
        .ping::before, .ping::after { content: ''; position: absolute; inset: 0; border-radius: 50%; background: var(--accent); }
        .ping::before { animation: ping 1.6s cubic-bezier(0, 0, 0.2, 1) infinite; opacity: 0.7; }
        @keyframes ping { 75%, 100% { transform: scale(2.4); opacity: 0; } }

        h1 {
            margin: 22px 0 0;
            font-family: var(--font-display);
            font-size: clamp(40px, 5.6vw, 68px);
            line-height: 1.04;
            letter-spacing: -0.025em;
            font-weight: 700;
        }
        .text-gradient {
            display: block;
            background-image: linear-gradient(120deg, #ffffff 0%, #c7c2ff 38%, #6ee7ff 100%);
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent; color: transparent;
        }
        .lead { margin: 22px 0 0; max-width: 34rem; font-size: clamp(16px, 1.6vw, 19px); line-height: 1.65; color: var(--muted); }
        .actions { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-top: 34px; }
        .access-note { display: flex; align-items: center; gap: 8px; margin-top: 18px; font-size: 13.5px; color: rgba(138, 150, 180, 0.85); }
        .access-note svg { width: 14px; height: 14px; color: var(--accent); }

        /* Live board mock */
        .stage { position: relative; }
        .stage::before {
            content: ''; position: absolute; inset: 8% 4%; border-radius: 40px; z-index: -1;
            background: radial-gradient(closest-side, rgba(124, 108, 255, 0.35), rgba(34, 211, 238, 0.12) 60%, transparent);
            filter: blur(40px);
        }
        .board {
            position: relative; border-radius: 22px; padding: 20px;
            box-shadow: 0 30px 80px -30px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }
        .board::after {
            content: ''; position: absolute; inset: 0; border-radius: inherit; padding: 1px; pointer-events: none;
            background: linear-gradient(135deg, rgba(124, 108, 255, 0.6), rgba(34, 211, 238, 0.15) 50%, transparent 80%);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude;
        }
        .board-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .board-title { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 14px; }
        .dots { display: flex; gap: 6px; }
        .dots span { width: 10px; height: 10px; border-radius: 50%; background: rgba(255, 255, 255, 0.12); }
        .live {
            display: inline-flex; align-items: center; gap: 7px; padding: 4px 10px; border-radius: 999px;
            font-size: 12px; font-weight: 600; color: var(--success);
            background: rgba(56, 232, 197, 0.1); border: 1px solid rgba(56, 232, 197, 0.25);
        }
        .live .ping::before, .live .ping::after { background: var(--success); }
        .live .ping { width: 7px; height: 7px; }

        .call {
            display: flex; align-items: center; gap: 14px; margin-top: 16px; padding: 14px 16px;
            border-radius: var(--radius); background: rgba(255, 255, 255, 0.035); border: 1px solid var(--border);
        }
        .call-icon {
            display: grid; place-items: center; width: 38px; height: 38px; border-radius: 12px; flex: none;
            background: linear-gradient(135deg, rgba(124, 108, 255, 0.35), rgba(34, 211, 238, 0.25));
        }
        .call-icon svg { width: 18px; height: 18px; color: #fff; }
        .call-label { font-size: 13px; color: var(--muted); }
        .wave { display: flex; align-items: center; gap: 4px; height: 28px; margin-left: auto; }
        .wave span { width: 4px; border-radius: 4px; background: var(--brand-cyan); animation: wave 1.2s ease-in-out infinite; }
        .wave span:nth-child(odd) { background: #fff; }
        .wave span:nth-child(1) { height: 30%; animation-delay: -0.9s; }
        .wave span:nth-child(2) { height: 70%; animation-delay: -0.6s; }
        .wave span:nth-child(3) { height: 100%; animation-delay: -0.3s; }
        .wave span:nth-child(4) { height: 60%; animation-delay: -0.75s; }
        .wave span:nth-child(5) { height: 90%; animation-delay: -0.45s; }
        .wave span:nth-child(6) { height: 45%; animation-delay: -0.15s; }
        .wave span:nth-child(7) { height: 75%; animation-delay: -1.05s; }
        @keyframes wave { 0%, 100% { transform: scaleY(0.45); } 50% { transform: scaleY(1); } }

        .ticket {
            position: relative; margin-top: 14px; padding: 18px; border-radius: var(--radius);
            background: linear-gradient(180deg, rgba(124, 108, 255, 0.10), rgba(255, 255, 255, 0.03));
            border: 1px solid rgba(124, 108, 255, 0.35);
            animation: ticket-in 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) 0.4s both;
        }
        @keyframes ticket-in { from { opacity: 0; transform: translateY(18px) scale(0.98); } to { opacity: 1; transform: none; } }
        .ticket-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .ticket-title { font-family: var(--font-display); font-size: 18px; font-weight: 600; }
        .ticket-source { display: flex; align-items: center; gap: 6px; margin-top: 4px; font-size: 12.5px; color: var(--accent); }
        .ticket-source svg { width: 13px; height: 13px; }
        .tag {
            flex: none; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600;
            color: #c7c2ff; background: rgba(124, 108, 255, 0.16);
        }
        .items { margin: 14px 0 0; padding: 0; list-style: none; }
        .items li {
            display: flex; justify-content: space-between; gap: 12px; padding: 9px 0;
            font-size: 14px; border-top: 1px dashed rgba(255, 255, 255, 0.08);
        }
        .items li span:last-child { color: var(--muted); font-variant-numeric: tabular-nums; }
        .ticket-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; padding-top: 14px; border-top: 1px solid var(--border); }
        .total { font-size: 13px; color: var(--muted); }
        .total strong { display: block; font-family: var(--font-display); font-size: 22px; color: var(--fg); font-variant-numeric: tabular-nums; }
        .accept {
            display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 16px; border-radius: 10px;
            font-size: 13.5px; font-weight: 600; color: #04121a; background: var(--success);
            box-shadow: 0 6px 24px -8px rgba(56, 232, 197, 0.8);
        }
        .accept svg { width: 15px; height: 15px; }
        .ghost-ticket {
            margin: -6px 18px 0; height: 14px; border-radius: 0 0 14px 14px;
            background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border); border-top: 0;
        }

        /* Features */
        .features { padding: 0 0 56px; }
        .features .container { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .feature { padding: 22px; border-radius: 18px; transition: border-color 0.2s ease, transform 0.2s ease; }
        .feature:hover { border-color: var(--border-strong); transform: translateY(-2px); }
        .feature-icon {
            display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px;
            background: rgba(124, 108, 255, 0.14); color: #c7c2ff;
        }
        .feature:nth-child(2) .feature-icon { background: rgba(34, 211, 238, 0.12); color: var(--accent); }
        .feature:nth-child(3) .feature-icon { background: rgba(56, 232, 197, 0.12); color: var(--success); }
        .feature-icon svg { width: 20px; height: 20px; }
        .feature h2 { margin: 16px 0 6px; font-family: var(--font-display); font-size: 17px; font-weight: 600; letter-spacing: -0.01em; }
        .feature p { margin: 0; font-size: 14.5px; line-height: 1.6; color: var(--muted); }

        /* Footer */
        .footer { border-top: 1px solid var(--border); padding: 22px 0 28px; font-size: 13px; color: var(--muted); }
        .footer .container { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; }
        .footer a:hover { color: var(--fg); }
        .langs { display: flex; flex-wrap: wrap; gap: 4px; }
        .langs a { padding: 4px 8px; border-radius: 8px; font-size: 12px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; }
        .langs a[aria-current="true"] { color: var(--fg); background: rgba(255, 255, 255, 0.08); }

        .reveal { animation: reveal 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) both; }
        .reveal-2 { animation-delay: 0.08s; }
        .reveal-3 { animation-delay: 0.16s; }
        .reveal-4 { animation-delay: 0.24s; }
        @keyframes reveal { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

        @media (max-width: 960px) {
            .hero { padding: 24px 0 48px; }
            .hero .container { grid-template-columns: minmax(0, 1fr); gap: 44px; }
            .copy { text-align: center; }
            .copy .lead { margin-left: auto; margin-right: auto; }
            .actions, .access-note { justify-content: center; }
            .stage { max-width: 520px; width: 100%; margin: 0 auto; }
            .features .container { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 560px) {
            .container { padding: 0 16px; }
            .header { padding: 16px 0; }
            .brand img { height: 22px; }
            .header .btn-sm span { display: none; }
            .header .btn-sm { padding: 0 12px; }
            .actions .btn { width: 100%; }
            .board { padding: 14px; border-radius: 18px; }
            .wave { display: none; }
            .footer .container { justify-content: center; text-align: center; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>
<div class="backdrop" aria-hidden="true">
    <div class="mesh"></div>
    <div class="grid"></div>
    <div class="aurora aurora-1"></div>
    <div class="aurora aurora-2"></div>
</div>

<div class="page">
    <header class="header">
        <div class="container">
            <a class="brand" href="{{ url('/') }}" aria-label="{{ $brandName }}">
                <img src="{{ asset('voxpilot/logo-on-dark.svg') }}" alt="VoxPilot" width="203" height="26">
                <span class="brand-pill">POS</span>
            </a>
            <a class="btn btn-ghost btn-sm glass" href="{{ $loginUrl }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>
                <span>{{ $t('cta') }}</span>
            </a>
        </div>
    </header>

    <main class="hero">
        <div class="container">
            <div class="copy">
                <span class="badge glass reveal"><span class="ping" aria-hidden="true"></span>{{ $t('badge') }}</span>
                <h1 class="reveal reveal-2">{{ $t('headline') }}<span class="text-gradient">{{ $t('headline_accent') }}</span></h1>
                <p class="lead reveal reveal-3">{{ $t('subheadline') }}</p>
                <div class="actions reveal reveal-4">
                    <a class="btn btn-primary" href="{{ $loginUrl }}">
                        {{ $t('cta') }}
                        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                    <a class="btn btn-ghost glass" href="{{ $marketingUrl }}" target="_blank" rel="noopener">
                        {{ $t('secondary_cta') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
                    </a>
                </div>
                <p class="access-note reveal reveal-4">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    {{ $t('access_note') }}
                </p>
            </div>

            <div class="stage reveal reveal-3" aria-hidden="true">
                <div class="board glass">
                    <div class="board-head">
                        <div class="board-title">
                            <div class="dots"><span></span><span></span><span></span></div>
                            {{ $brandName }}
                        </div>
                        <span class="live"><span class="ping"></span>{{ $t('ticket_live') }}</span>
                    </div>

                    <div class="call">
                        <div class="call-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </div>
                        <div class="call-label">{{ $t('ticket_call') }}</div>
                        <div class="wave"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
                    </div>

                    <div class="ticket">
                        <div class="ticket-head">
                            <div>
                                <div class="ticket-title">{{ $t('ticket_new') }} · #1042</div>
                                <div class="ticket-source">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    {{ $t('ticket_source') }}
                                </div>
                            </div>
                            <span class="tag">{{ $t('ticket_delivery') }}</span>
                        </div>
                        <ul class="items">
                            <li><span>2× Pizza Margherita</span><span>€19.00</span></li>
                            <li><span>1× Tiramisù</span><span>€6.50</span></li>
                            <li><span>2× San Pellegrino</span><span>€5.90</span></li>
                        </ul>
                        <div class="ticket-foot">
                            <div class="total">{{ $t('ticket_total') }}<strong>€31.40</strong></div>
                            <span class="accept">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                {{ $t('ticket_accept') }}
                            </span>
                        </div>
                    </div>
                    <div class="ghost-ticket"></div>
                </div>
            </div>
        </div>
    </main>

    <section class="features">
        <div class="container">
            <article class="feature glass">
                <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
                <h2>{{ $t('feature_realtime_title') }}</h2>
                <p>{{ $t('feature_realtime_text') }}</p>
            </article>
            <article class="feature glass">
                <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
                <h2>{{ $t('feature_sync_title') }}</h2>
                <p>{{ $t('feature_sync_text') }}</p>
            </article>
            <article class="feature glass">
                <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></div>
                <h2>{{ $t('feature_secure_title') }}</h2>
                <p>{{ $t('feature_secure_text') }}</p>
            </article>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <span>&copy; {{ date('Y') }} VoxPilot. {{ $t('footer_rights') }} · <a href="{{ $marketingUrl }}" target="_blank" rel="noopener">voxpilothq.io</a></span>
            <nav class="langs" aria-label="{{ lang('igniter.voxpilot::branding.landing.language') }}">
                @foreach(config('voxpilot.locales', ['en']) as $code)
                    <a href="{{ url('/') }}?lang={{ $code }}" hreflang="{{ $code }}" lang="{{ $code }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                @endforeach
            </nav>
        </div>
    </footer>
</div>
</body>
</html>
