<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Outbound requests') · LaraClient</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --paper: #fcfcfd;
            --panel: #ffffff;
            --ink: #12161f;
            --muted: #6a7482;
            --rule: #e4e7ec;
            --rule-soft: #f0f2f5;
            --ok: #1b7f5b;
            --warn: #a8700d;
            --fail: #c0304a;
            --accent: #2a3fa0;
            --display: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif;
            --mono: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: var(--display);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-radius: 2px;
        }

        .shell { max-width: 1240px; margin: 0 auto; padding: 32px 24px 96px; }

        /* ---- masthead ---- */
        .masthead {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--rule);
        }

        .wordmark {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .wordmark span { color: var(--muted); font-weight: 400; }

        .window-label {
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        /* ---- trace strip: the last 120 calls, oldest to newest ---- */
        .trace {
            margin: 24px 0 8px;
            padding: 18px 20px 14px;
            background: var(--panel);
            border: 1px solid var(--rule);
            border-radius: 4px;
        }

        .trace-head {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 14px;
        }

        .trace-title {
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .trace-bars {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            height: 72px;
            border-bottom: 1px solid var(--rule);
        }

        .bar {
            flex: 1 1 0;
            min-width: 2px;
            min-height: 2px;
            background: var(--ok);
            border-radius: 1px 1px 0 0;
            opacity: 0.75;
            transition: opacity 120ms ease;
        }

        .bar:hover, .bar:focus-visible { opacity: 1; }
        .bar.warn { background: var(--warn); }
        .bar.error { background: var(--fail); opacity: 1; }

        .trace-axis {
            display: flex;
            justify-content: space-between;
            margin-top: 6px;
            font-family: var(--mono);
            font-size: 10px;
            color: var(--muted);
        }

        .trace-empty {
            font-family: var(--mono);
            font-size: 12px;
            color: var(--muted);
            padding: 24px 0;
            text-align: center;
        }

        /* ---- stats ---- */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1px;
            background: var(--rule);
            border: 1px solid var(--rule);
            border-radius: 4px;
            margin-bottom: 28px;
            overflow: hidden;
        }

        .stat { background: var(--panel); padding: 14px 16px; }

        .stat dt {
            font-family: var(--mono);
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin: 0 0 4px;
        }

        .stat dd {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }

        .stat dd small { font-size: 12px; color: var(--muted); font-weight: 400; }
        .stat.is-bad dd { color: var(--fail); }

        /* ---- filters ---- */
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 18px;
        }

        .chip {
            font-family: var(--mono);
            font-size: 12px;
            padding: 5px 11px;
            border: 1px solid var(--rule);
            border-radius: 999px;
            background: var(--panel);
            color: var(--muted);
        }

        .chip:hover { border-color: var(--accent); color: var(--accent); text-decoration: none; }
        .chip[aria-current="true"] { background: var(--ink); border-color: var(--ink); color: var(--paper); }

        .search {
            flex: 1 1 220px;
            display: flex;
            gap: 8px;
        }

        .search input {
            flex: 1;
            font-family: var(--mono);
            font-size: 12px;
            padding: 6px 11px;
            border: 1px solid var(--rule);
            border-radius: 3px;
            background: var(--panel);
            color: var(--ink);
        }

        .search button {
            font-family: var(--mono);
            font-size: 12px;
            padding: 6px 14px;
            border: 1px solid var(--ink);
            border-radius: 3px;
            background: var(--ink);
            color: var(--paper);
            cursor: pointer;
        }

        /* ---- table ---- */
        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-family: var(--mono);
            font-size: 12.5px;
        }

        .log-table thead th {
            text-align: left;
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 500;
            padding: 0 12px 8px;
            border-bottom: 1px solid var(--rule);
            white-space: nowrap;
        }

        .log-table tbody tr { border-bottom: 1px solid var(--rule-soft); }
        .log-table tbody tr:hover { background: var(--rule-soft); }
        .log-table td { padding: 9px 12px; vertical-align: top; }

        .col-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .endpoint { max-width: 520px; overflow-wrap: anywhere; }
        .endpoint .host { color: var(--muted); }

        .verb { font-weight: 700; font-size: 11px; letter-spacing: 0.04em; }

        .status { font-weight: 700; }
        .status.ok { color: var(--ok); }
        .status.warn { color: var(--warn); }
        .status.error { color: var(--fail); }

        .flag {
            display: inline-block;
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 2px;
            border: 1px solid var(--rule);
            color: var(--muted);
            margin-left: 6px;
        }

        .empty {
            padding: 64px 24px;
            text-align: center;
            border: 1px dashed var(--rule);
            border-radius: 4px;
            color: var(--muted);
        }

        .empty strong { display: block; color: var(--ink); font-size: 15px; margin-bottom: 6px; }

        .pagination { margin-top: 24px; font-family: var(--mono); font-size: 12px; }
        .pagination svg { width: 14px; height: 14px; }

        /* ---- detail ---- */
        .back { font-family: var(--mono); font-size: 12px; }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1px;
            background: var(--rule);
            border: 1px solid var(--rule);
            border-radius: 4px;
            margin: 20px 0 28px;
            overflow: hidden;
        }

        .detail-grid div { background: var(--panel); padding: 12px 16px; }
        .detail-grid dt { font-family: var(--mono); font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }
        .detail-grid dd { margin: 4px 0 0; font-family: var(--mono); font-size: 13px; overflow-wrap: anywhere; }

        .payload { margin-bottom: 24px; }

        .payload-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
        }

        .payload h2 {
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 500;
            margin: 0;
        }

        .copy-btn {
            flex-shrink: 0;
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 0.04em;
            padding: 4px 10px;
            border: 1px solid var(--rule);
            border-radius: 3px;
            background: var(--panel);
            color: var(--muted);
            cursor: pointer;
            transition: border-color 120ms ease, color 120ms ease, background 120ms ease;
        }

        .copy-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .copy-btn.is-copied {
            border-color: var(--ok);
            color: var(--ok);
        }

        .payload pre {
            margin: 0;
            padding: 16px;
            background: var(--panel);
            border: 1px solid var(--rule);
            border-radius: 4px;
            font-family: var(--mono);
            font-size: 12px;
            line-height: 1.6;
            overflow-x: auto;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .note {
            font-size: 12px;
            color: var(--muted);
            padding: 10px 14px;
            border-left: 2px solid var(--rule);
            margin-bottom: 24px;
        }

        @media (max-width: 720px) {
            .shell { padding: 20px 14px 64px; }
            .hide-sm { display: none; }
            .trace-bars { height: 52px; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>
    <div class="shell">
        @yield('content')
    </div>

    @stack('scripts')
</body>
</html>
