<?php
// ============================================================
// صفارة لايف 2.0 - الصفحة الرئيسية
// ============================================================

date_default_timezone_set('UTC');

define('API_URL',        'https://djelalda.online/backend/get_today_matches.php');
define('CACHE_FILE',     __DIR__ . '/cache/matches.json');
define('CACHE_DURATION', 60);
define('API_TIMEOUT',    3);
define('API_MAX_TIME',   8);

function fetch_api(string $url): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url . '?t=' . time(),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => API_TIMEOUT,
        CURLOPT_TIMEOUT        => API_MAX_TIME,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);
    if ($response === false || $curl_err) return ['success' => false, 'reason' => 'connection_failed'];
    if ($http_code !== 200)               return ['success' => false, 'reason' => 'http_' . $http_code];
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['success' => false, 'reason' => 'invalid_json'];
    if (empty($data['success']) || !isset($data['matches']) || !is_array($data['matches']))
        return ['success' => false, 'reason' => 'unexpected_structure'];
    return ['success' => true, 'matches' => $data['matches']];
}

function read_cache(): ?array {
    if (!file_exists(CACHE_FILE)) return null;
    $age = time() - filemtime(CACHE_FILE);
    $raw = file_get_contents(CACHE_FILE);
    if (!$raw) return null;
    $cached = json_decode($raw, true);
    if (!is_array($cached)) return null;
    $cached['cache_age'] = $age;
    return $cached;
}

function write_cache(array $matches): void {
    $dir = dirname(CACHE_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(CACHE_FILE, json_encode(['matches' => $matches]), LOCK_EX);
}

$using_cache = false; $cache_age = null; $api_error = false; $api_error_reason = '';
$cached      = read_cache();
$cache_valid = $cached && isset($cached['cache_age']) && $cached['cache_age'] < CACHE_DURATION;

if ($cache_valid) {
    $matches = $cached['matches']; $using_cache = true; $cache_age = $cached['cache_age'];
} else {
    $result = fetch_api(API_URL);
    if ($result['success']) {
        $matches = $result['matches']; write_cache($matches);
    } else {
        $api_error = true; $api_error_reason = $result['reason'];
        if ($cached && isset($cached['matches'])) {
            $matches = $cached['matches']; $using_cache = true; $cache_age = $cached['cache_age'] ?? null;
        } else { $matches = []; }
    }
}

// تجهيز المباريات + تجميع حسب البطولة
$grouped = [];
$now_ts  = time();
$next_match_ts = null; // أقرب مباراة قادمة للعداد التنازلي

foreach ($matches as $key => $match) {
    $matches[$key]['match_datetime_original'] = $match['match_datetime'];
    $dt = new DateTime($match['match_datetime'], new DateTimeZone('UTC'));
    $matches[$key]['match_datetime_utc'] = $dt->format('Y-m-d H:i:s');
    $matches[$key]['match_ts'] = $dt->getTimestamp();

    $diff = $now_ts - $dt->getTimestamp();
    if ($diff < -900) {
        $matches[$key]['status'] = 'upcoming';
        // أقرب مباراة قادمة
        if ($next_match_ts === null || $dt->getTimestamp() < $next_match_ts) {
            $next_match_ts = $dt->getTimestamp();
        }
    } elseif ($diff <= 7200) {
        $matches[$key]['status'] = 'live';
    } else {
        $matches[$key]['status'] = 'finished';
    }

    $comp = htmlspecialchars($match['competition']);
    if (!isset($grouped[$comp])) $grouped[$comp] = [];
    $grouped[$comp][] = $matches[$key];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صفارة لايف - مباريات اليوم</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        /* ── متغيرات ── */
        :root, [data-theme="dark"] {
            --bg-deep:     #050810;
            --bg-page:     #070c18;
            --bg-card:     #0c1120;
            --bg-card2:    #111827;
            --bg-header:   rgba(5,8,16,0.92);
            --accent:      #00e5a0;
            --accent2:     #00b87a;
            --accent-glow: rgba(0,229,160,0.18);
            --red:         #ff3b5c;
            --gold:        #f5c842;
            --text:        #e8eaf0;
            --text-sub:    #9ca3af;
            --muted:       #6b7280;
            --border:      rgba(255,255,255,0.08);
            --border-h:    rgba(0,229,160,0.22);
            --shadow:      0 14px 36px rgba(0,0,0,0.55);
        }
        [data-theme="light"] {
            --bg-deep:     #eef2f7;
            --bg-page:     #f0f4f8;
            --bg-card:     #ffffff;
            --bg-card2:    #f8fafc;
            --bg-header:   rgba(255,255,255,0.94);
            --accent:      #00a372;
            --accent2:     #007f59;
            --accent-glow: rgba(0,163,114,0.15);
            --red:         #e02040;
            --gold:        #c9930a;
            --text:        #111827;
            --text-sub:    #4b5563;
            --muted:       #9ca3af;
            --border:      rgba(0,0,0,0.09);
            --border-h:    rgba(0,163,114,0.3);
            --shadow:      0 4px 24px rgba(0,0,0,0.10);
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            background: var(--bg-page);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            transition: background .3s, color .3s;
        }
        [data-theme="dark"] body::before {
            content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
            background:
                radial-gradient(ellipse 70% 40% at 20% 10%, rgba(0,229,160,.06) 0%, transparent 70%),
                radial-gradient(ellipse 50% 30% at 80% 80%, rgba(0,100,255,.05) 0%, transparent 70%);
        }

        /* ── هيدر ── */
        header {
            position:sticky; top:0; z-index:200;
            background: var(--bg-header);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            transition: background .3s;
        }
        .header-inner {
            max-width: 900px; margin:0 auto;
            display:flex; align-items:center; justify-content:space-between;
            padding: 12px 20px;
        }
        .logo-block {
            display:flex; align-items:center; gap:10px;
            text-decoration:none; transition:opacity .2s;
        }
        .logo-block:hover { opacity:.82; }
        .logo-icon {
            width:42px; height:42px;
            background: linear-gradient(135deg,var(--accent),var(--accent2));
            border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            font-size:20px; box-shadow:0 0 16px var(--accent-glow);
        }
        .logo-text h1 { font-size:19px; font-weight:900; line-height:1; }
        .logo-text small { font-size:11px; color:var(--accent); font-weight:600; letter-spacing:.8px; }

        /* ── شريط بحث ── */
        .search-wrap {
            flex:1; max-width:320px; margin:0 16px;
            position:relative;
        }
        .search-wrap input {
            width:100%;
            background: var(--bg-card2);
            border: 1px solid var(--border);
            border-radius:30px;
            color: var(--text);
            font-family: inherit;
            font-size:13px; font-weight:600;
            padding: 9px 16px 9px 40px;
            outline:none;
            transition: border-color .2s;
        }
        .search-wrap input::placeholder { color:var(--muted); }
        .search-wrap input:focus { border-color:var(--accent); }
        .search-wrap .search-icon {
            position:absolute; left:14px; top:50%; transform:translateY(-50%);
            color:var(--muted); font-size:14px; pointer-events:none;
        }
        .search-results {
            display:none; position:absolute; top:calc(100% + 6px); right:0; left:0;
            background:var(--bg-card); border:1px solid var(--border);
            border-radius:14px; overflow:hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,.4);
            z-index:300;
        }
        .search-results.show { display:block; }
        .search-item {
            padding:11px 16px;
            font-size:13px; font-weight:600; color:var(--text);
            cursor:pointer; border-bottom:1px solid var(--border);
            transition: background .15s;
        }
        .search-item:last-child { border-bottom:none; }
        .search-item:hover { background:var(--bg-card2); }
        .search-item span { color:var(--muted); font-size:11px; margin-right:6px; }

        /* ── اليمين: أدوات ── */
        .header-right { display:flex; align-items:center; gap:8px; }
        .theme-toggle {
            width:36px; height:36px; border-radius:50%;
            border:1px solid var(--border); background:var(--bg-card2);
            color:var(--text); font-size:16px; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            transition: border-color .2s, transform .2s;
        }
        .theme-toggle:hover { border-color:var(--accent); transform:rotate(20deg); }
        .live-pill {
            display:flex; align-items:center; gap:5px;
            background:rgba(255,59,92,.10); border:1px solid rgba(255,59,92,.28);
            color:var(--red); padding:6px 12px; border-radius:30px;
            font-size:12px; font-weight:700;
        }
        .live-dot {
            width:7px; height:7px; background:var(--red); border-radius:50%;
            animation: blink 1.4s infinite;
        }
        @keyframes blink { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.3;transform:scale(.7)} }

        /* ── العداد التنازلي ── */
        #countdown-bar {
            position:relative; z-index:5;
            background: linear-gradient(135deg, rgba(0,229,160,.12), rgba(0,100,255,.08));
            border-bottom: 1px solid rgba(0,229,160,.18);
            padding: 10px 20px;
            display: none; /* يُفعَّل بالـ JS */
        }
        .countdown-inner {
            max-width:900px; margin:0 auto;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;
        }
        .countdown-label { font-size:13px; font-weight:700; color:var(--accent); }
        .countdown-label strong { color:var(--text); }
        .countdown-timer {
            display:flex; gap:8px;
        }
        .cd-unit {
            display:flex; flex-direction:column; align-items:center;
            background:var(--bg-card); border:1px solid var(--border-h);
            border-radius:10px; padding:4px 12px; min-width:52px;
        }
        .cd-num { font-size:22px; font-weight:900; color:var(--accent); line-height:1; }
        .cd-lbl { font-size:10px; color:var(--muted); font-weight:700; margin-top:2px; }
        .cd-sep { font-size:20px; color:var(--accent); font-weight:900; align-self:center; margin-top:-6px; }

        /* ── شريط التاريخ + فلترة ── */
        .top-bar {
            max-width:900px; margin:18px auto 0; padding:0 20px;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
        }
        .date-nav { display:flex; align-items:center; gap:8px; }
        .day-btn {
            padding:7px 18px; border-radius:30px; font-size:13px; font-weight:700;
            text-decoration:none; border:1px solid var(--border);
            background:transparent; color:var(--text-sub); cursor:pointer;
            transition: all .2s; font-family:inherit;
        }
        .day-btn.active, .day-btn:hover {
            background: linear-gradient(135deg,var(--accent),var(--accent2));
            color:#050810; border-color:transparent;
        }
        .right-bar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .date-val { font-size:13px; color:var(--accent); font-weight:700; }
        .clock-val { font-size:12px; color:var(--muted); font-weight:600; }

        /* ── فلاتر ── */
        .filter-bar {
            max-width:900px; margin:14px auto 0; padding:0 20px;
            display:flex; align-items:center; gap:8px; flex-wrap:wrap;
        }
        .filter-btn {
            padding:6px 16px; border-radius:30px; font-size:13px; font-weight:700;
            border:1px solid var(--border); background:var(--bg-card2);
            color:var(--text-sub); cursor:pointer; font-family:inherit;
            transition: all .2s; display:flex; align-items:center; gap:5px;
        }
        .filter-btn.active { background:var(--accent); color:#050810; border-color:transparent; }
        .filter-btn:hover:not(.active) { border-color:var(--accent); color:var(--accent); }
        .filter-count {
            background:rgba(255,255,255,.15); color:inherit;
            font-size:11px; padding:1px 7px; border-radius:20px; font-weight:900;
        }
        .filter-btn.active .filter-count { background:rgba(0,0,0,.2); }

        .divider {
            height:1px; max-width:900px; margin:14px auto 0;
            background: linear-gradient(90deg,transparent,var(--border),transparent);
        }

        /* ── المحتوى الرئيسي ── */
        main {
            position:relative; z-index:5;
            max-width:900px; margin:20px auto 50px; padding:0 20px;
        }

        /* ── مجموعة البطولة ── */
        .league-group { margin-bottom:20px; }
        .league-header {
            display:flex; align-items:center; justify-content:space-between;
            background:var(--bg-card2); border:1px solid var(--border);
            border-radius:14px 14px 0 0;
            padding:10px 16px; cursor:pointer;
            transition: border-color .2s;
            user-select:none;
        }
        .league-header:hover { border-color:var(--border-h); }
        .league-header-left { display:flex; align-items:center; gap:10px; }
        .league-emoji { font-size:18px; }
        .league-title { font-size:14px; font-weight:800; color:var(--text); }
        .league-count {
            font-size:11px; font-weight:700; color:var(--muted);
            background:rgba(255,255,255,.06); padding:2px 9px; border-radius:20px;
        }
        .league-header-right { display:flex; align-items:center; gap:10px; }
        .league-toggle { font-size:14px; color:var(--muted); transition:transform .3s; }
        .league-group.collapsed .league-toggle { transform:rotate(-90deg); }
        .league-body { border:1px solid var(--border); border-top:none; border-radius:0 0 14px 14px; overflow:hidden; }
        .league-group.collapsed .league-body { display:none; }

        /* ── بطاقة مباراة ── */
        .match-card {
            background:var(--bg-card);
            border-bottom:1px solid var(--border);
            transition: background .2s;
            position:relative;
        }
        .match-card:last-child { border-bottom:none; }
        .match-card:hover { background:var(--bg-card2); }

        .card-top {
            padding:7px 16px;
            display:flex; align-items:center; justify-content:flex-end;
            border-bottom:1px solid var(--border);
        }
        .match-status {
            display:inline-flex; align-items:center; gap:5px;
            font-size:11px; font-weight:700; padding:3px 11px; border-radius:20px;
        }
        .match-status.upcoming { background:rgba(245,200,66,.10); color:var(--gold); border:1px solid rgba(245,200,66,.25); }
        .match-status.live     { background:rgba(255,59,92,.12);  color:var(--red);  border:1px solid rgba(255,59,92,.28); }
        .match-status.finished { background:rgba(107,114,128,.10); color:var(--muted); border:1px solid rgba(107,114,128,.18); }
        .status-dot { width:6px; height:6px; background:var(--red); border-radius:50%; animation:blink 1.4s infinite; display:inline-block; }

        .teams-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:16px 20px 12px; gap:10px;
        }
        .team {
            display:flex; flex-direction:column; align-items:center;
            gap:8px; flex:1; min-width:0;
        }
        .team-logo-wrap {
            width:64px; height:64px; border-radius:50%;
            background:var(--bg-deep); border:2px solid var(--border);
            display:flex; align-items:center; justify-content:center; overflow:hidden;
            transition:border-color .3s;
        }
        .match-card:hover .team-logo-wrap { border-color:var(--border-h); }
        .team-logo { width:50px; height:50px; object-fit:contain; }
        .team-name { font-size:14px; font-weight:800; color:var(--text); text-align:center; line-height:1.3; max-width:120px; }

        .match-center {
            display:flex; flex-direction:column; align-items:center;
            gap:4px; flex-shrink:0; padding:0 10px;
        }
        .vs-badge {
            width:30px; height:30px; border-radius:50%;
            background:rgba(0,229,160,.08); border:1px solid var(--border-h);
            display:flex; align-items:center; justify-content:center;
            font-size:9px; font-weight:900; color:var(--accent);
        }
        .match-time-display { font-size:20px; font-weight:900; color:var(--gold); letter-spacing:1px; }
        .match-result       { font-size:22px; font-weight:900; color:var(--gold); letter-spacing:2px; background:rgba(0,0,0,.2); padding:3px 14px; border-radius:10px; }

        .card-info {
            display:flex; justify-content:center; gap:8px; flex-wrap:wrap;
            padding:10px 16px; border-top:1px solid var(--border);
        }
        .info-tag {
            display:flex; align-items:center; gap:4px;
            background:rgba(255,255,255,.04); border:1px solid var(--border);
            color:var(--text-sub); font-size:12px; padding:4px 12px; border-radius:20px; font-weight:600;
        }
        [data-theme="light"] .info-tag { background:rgba(0,0,0,.04); }

        .card-footer { padding:10px 16px 14px; }
        .watch-btn {
            display:flex; align-items:center; justify-content:center; gap:8px;
            background:linear-gradient(135deg,var(--accent),var(--accent2));
            color:#050810; text-decoration:none;
            padding:11px; border-radius:12px;
            font-weight:900; font-size:14px;
            transition:all .22s; cursor:pointer; border:none; width:100%;
            box-shadow:0 4px 16px var(--accent-glow);
        }
        .watch-btn:hover { filter:brightness(1.08); transform:translateY(-1px); box-shadow:0 6px 22px var(--accent-glow); }
        .play-circle {
            width:20px; height:20px; background:rgba(5,8,16,.22); border-radius:50%;
            display:flex; align-items:center; justify-content:center; font-size:9px; padding-right:1px;
        }
        .finished-label {
            display:flex; align-items:center; justify-content:center; gap:5px;
            background:rgba(107,114,128,.18); border:1px solid rgba(107,114,128,.35);
            color:#a0aec0; padding:11px; border-radius:12px;
            font-size:13px; font-weight:700; width:100%; cursor:not-allowed;
        }

        /* ── لا مباريات ── */
        .no-matches { text-align:center; padding:70px 20px; }
        .no-matches .big-icon { font-size:52px; display:block; margin-bottom:12px; opacity:.3; }
        .no-matches p { font-size:16px; color:var(--muted); font-weight:600; }

        /* ── إشعار ── */
        .notice-bar { max-width:900px; margin:14px auto 0; padding:0 20px; z-index:10; position:relative; }
        .notice-bar-inner {
            display:flex; align-items:center; gap:8px;
            padding:9px 15px; border-radius:12px; font-size:12px; font-weight:600;
        }
        .notice-bar-inner.warning { background:rgba(245,200,66,.08); border:1px solid rgba(245,200,66,.22); color:var(--gold); }

        /* ── فوتر ── */
        .main-footer { max-width:900px; margin:30px auto 0; padding:0 20px 20px; }
        .footer-inner {
            display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:28px;
            background:var(--bg-card); border:1px solid var(--border);
            border-radius:18px; padding:30px 26px;
        }
        .footer-logo { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
        .footer-logo-icon {
            width:42px; height:42px; background:linear-gradient(135deg,var(--accent),var(--accent2));
            border-radius:12px; display:flex; align-items:center; justify-content:center;
            font-size:20px; box-shadow:0 0 14px var(--accent-glow);
        }
        .footer-logo h3 { font-size:17px; font-weight:900; line-height:1; }
        .footer-logo small { font-size:11px; color:var(--accent); font-weight:600; }
        .footer-about { font-size:13px; color:var(--text-sub); line-height:1.6; margin-bottom:10px; }
        .footer-col-title { font-size:13px; font-weight:700; color:var(--text); margin-bottom:12px; letter-spacing:.5px; }
        .footer-links { list-style:none; }
        .footer-links li { margin-bottom:9px; }
        .footer-links a { color:var(--text-sub); text-decoration:none; font-size:13px; transition:color .2s, padding-right .2s; display:inline-block; }
        .footer-links a:hover { color:var(--accent); padding-right:5px; }
        .footer-bottom {
            margin-top:14px; padding:14px 0 0; border-top:1px solid var(--border);
            text-align:center; font-size:13px; color:var(--muted);
        }
        .footer-bottom a { color:var(--accent); font-weight:700; text-decoration:none; }

        /* ── إخفاء البطاقات بالفلتر ── */
        .match-card.hidden-filter { display:none; }
        .league-group.all-hidden  { display:none; }

        @media(max-width:700px){
            .footer-inner { grid-template-columns:1fr 1fr; gap:20px; padding:22px 16px; }
            .search-wrap  { max-width:160px; }
            .countdown-timer .cd-unit { min-width:44px; padding:4px 8px; }
        }
        @media(max-width:480px){
            .footer-inner { grid-template-columns:1fr; }
            .search-wrap  { display:none; }
            .header-inner { gap:8px; }
        }
    </style>
</head>
<body>

<!-- ══ هيدر ══ -->
<header>
    <div class="header-inner">
        <a href="https://souikadz.com" class="logo-block">
            <div class="logo-icon">🏆</div>
            <div class="logo-text">
                <h1>صفارة لايف</h1>
                <small>Saffara Live</small>
            </div>
        </a>

        <div class="search-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" placeholder="بحث عن فريق أو بطولة..." autocomplete="off">
            <div class="search-results" id="searchResults"></div>
        </div>

        <div class="header-right">
            <button class="theme-toggle" id="themeToggle" aria-label="تبديل الوضع">🌙</button>
            <div class="live-pill"><span class="live-dot"></span><span>بث مباشر</span></div>
        </div>
    </div>
</header>

<!-- ══ عداد تنازلي ══ -->
<div id="countdown-bar">
    <div class="countdown-inner">
        <div class="countdown-label">⚽ أقرب مباراة: <strong id="cd-match-name">—</strong></div>
        <div class="countdown-timer">
            <div class="cd-unit"><span class="cd-num" id="cd-h">00</span><span class="cd-lbl">ساعة</span></div>
            <span class="cd-sep">:</span>
            <div class="cd-unit"><span class="cd-num" id="cd-m">00</span><span class="cd-lbl">دقيقة</span></div>
            <span class="cd-sep">:</span>
            <div class="cd-unit"><span class="cd-num" id="cd-s">00</span><span class="cd-lbl">ثانية</span></div>
        </div>
    </div>
</div>

<!-- ══ التاريخ والفلاتر ══ -->
<div class="top-bar">
    <div class="date-nav">
        <a href="/" class="day-btn active">اليوم</a>
        <a href="/tomorrow/" class="day-btn">الغد</a>
    </div>
    <div class="right-bar">
        <span class="date-val" id="today-date"></span>
        <span class="clock-val" id="local-time"></span>
    </div>
</div>

<div class="filter-bar">
    <button class="filter-btn active" data-filter="all">الكل <span class="filter-count" id="cnt-all">0</span></button>
    <button class="filter-btn" data-filter="live">🔴 جارية <span class="filter-count" id="cnt-live">0</span></button>
    <button class="filter-btn" data-filter="upcoming">⏰ قادمة <span class="filter-count" id="cnt-upcoming">0</span></button>
    <button class="filter-btn" data-filter="finished">✓ انتهت <span class="filter-count" id="cnt-finished">0</span></button>
</div>
<div class="divider"></div>

<?php if ($api_error && $using_cache && $cache_age !== null): ?>
<div class="notice-bar">
    <div class="notice-bar-inner warning">
        ⚠️ تعذر الاتصال بالخادم — آخر تحديث منذ <?php echo round($cache_age/60); ?> دقيقة
    </div>
</div>
<?php endif; ?>

<!-- ══ المحتوى ══ -->
<main>

    <?php if ($api_error && !$using_cache): ?>
        <div class="no-matches"><span class="big-icon">⚠️</span><p>تعذر تحميل المباريات — حاول لاحقاً</p></div>

    <?php elseif (count($matches) === 0): ?>
        <div class="no-matches"><span class="big-icon">⚽</span><p>لا توجد مباريات مجدولة اليوم</p></div>

    <?php else: ?>
        <?php foreach ($grouped as $league_name => $league_matches): ?>
            <?php
            $live_cnt     = count(array_filter($league_matches, fn($m) => $m['status'] === 'live'));
            $upcoming_cnt = count(array_filter($league_matches, fn($m) => $m['status'] === 'upcoming'));
            ?>
            <div class="league-group" data-league="<?php echo htmlspecialchars($league_name); ?>">
                <div class="league-header" onclick="toggleLeague(this)">
                    <div class="league-header-left">
                        <span class="league-emoji">🏁</span>
                        <span class="league-title"><?php echo htmlspecialchars($league_name); ?></span>
                        <span class="league-count"><?php echo count($league_matches); ?> مباراة</span>
                        <?php if ($live_cnt > 0): ?>
                            <span style="font-size:11px;font-weight:700;color:var(--red);background:rgba(255,59,92,.1);padding:2px 9px;border-radius:20px;display:flex;align-items:center;gap:4px;">
                                <span style="width:5px;height:5px;background:var(--red);border-radius:50%;display:inline-block;animation:blink 1.4s infinite;"></span>
                                <?php echo $live_cnt; ?> مباشر
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="league-header-right">
                        <span class="league-toggle">▼</span>
                    </div>
                </div>
                <div class="league-body">
                    <?php foreach ($league_matches as $match): ?>
                        <?php
                        $status = $match['status'];
                        if ($status === 'upcoming') {
                            $status_text = '⏰ لم تبدأ بعد';
                            $watch_link  = '/match.php?slug=' . $match['id'];
                            $btn_label   = 'شاهد المباراة';
                        } elseif ($status === 'live') {
                            $status_text = 'جارية الآن';
                            $watch_link  = '/streams/generate.php?match_id=' . $match['id'];
                            $btn_label   = 'شاهد الآن';
                        } else {
                            $status_text = '✓ انتهت';
                            $watch_link  = null;
                            $btn_label   = null;
                            $team1_score = $match['team1_score'] ?? '?';
                            $team2_score = $match['team2_score'] ?? '?';
                        }
                        ?>
                        <div class="match-card"
                             data-status="<?php echo $status; ?>"
                             data-match-time="<?php echo $match['match_datetime_utc']; ?>"
                             data-match-id="<?php echo $match['id']; ?>"
                             data-team1="<?php echo htmlspecialchars($match['team1_name']); ?>"
                             data-team2="<?php echo htmlspecialchars($match['team2_name']); ?>"
                             data-league-name="<?php echo htmlspecialchars($league_name); ?>">

                            <div class="card-top">
                                <span class="match-status <?php echo $status; ?>">
                                    <?php if ($status === 'live'): ?><span class="status-dot"></span><?php endif; ?>
                                    <?php echo $status_text; ?>
                                </span>
                            </div>

                            <div class="teams-row">
                                <div class="team">
                                    <div class="team-logo-wrap">
                                        <img src="<?php echo htmlspecialchars($match['team1_logo']); ?>"
                                             alt="<?php echo htmlspecialchars($match['team1_name']); ?>"
                                             class="team-logo" loading="lazy">
                                    </div>
                                    <span class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                                </div>

                                <div class="match-center">
                                    <div class="vs-badge">VS</div>
                                    <?php if ($status === 'finished'): ?>
                                        <div class="match-result"><?php echo $team1_score; ?> - <?php echo $team2_score; ?></div>
                                    <?php else: ?>
                                        <div class="match-time-display match-time-local"
                                             data-time="<?php echo $match['match_datetime_utc']; ?>">
                                            <?php echo date('H:i', strtotime($match['match_datetime_utc'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="team">
                                    <div class="team-logo-wrap">
                                        <img src="<?php echo htmlspecialchars($match['team2_logo']); ?>"
                                             alt="<?php echo htmlspecialchars($match['team2_name']); ?>"
                                             class="team-logo" loading="lazy">
                                    </div>
                                    <span class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                                </div>
                            </div>

                            <div class="card-info">
                                <span class="info-tag">📺 <?php echo htmlspecialchars($match['channel_name']); ?></span>
                                <span class="info-tag">🎙️ <?php echo htmlspecialchars($match['commentator']); ?></span>
                            </div>

                            <div class="card-footer">
                                <?php if ($status === 'finished'): ?>
                                    <span class="finished-label">✓ انتهت المباراة</span>
                                <?php else: ?>
                                    <a href="<?php echo $watch_link; ?>" class="watch-btn">
                                        <span class="play-circle">▶</span>
                                        <?php echo $btn_label; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<!-- ══ فوتر ══ -->
<footer class="main-footer">
    <div class="footer-inner">
        <div class="footer-col">
            <div class="footer-logo">
                <span class="footer-logo-icon">🏆</span>
                <div><h3>صفارة لايف</h3><small>Saffara Live</small></div>
            </div>
            <p class="footer-about">منصتك الأولى لمشاهدة المباريات بث مباشر بجودة عالية وبدون تقطيع.</p>
            <div style="font-size:13px;color:var(--muted);">📧 info@saffara.com</div>
        </div>
        <div class="footer-col">
            <h4 class="footer-col-title">📺 البث المباشر</h4>
            <ul class="footer-links">
                <li><a href="/live-hd/">بث مباشر HD</a></li>
                <li><a href="/koora-live/">كورة لايف</a></li>
                <li><a href="/kora-online/">كورة أون لاين</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4 class="footer-col-title">⚽ المباريات</h4>
            <ul class="footer-links">
                <li><a href="/today/">مباريات اليوم</a></li>
                <li><a href="/tomorrow/">مباريات الغد</a></li>
                <li><a href="/leagues/">جدول الدوريات</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4 class="footer-col-title">📱 تابعنا</h4>
            <ul class="footer-links">
                <li><a href="#">فيسبوك</a></li>
                <li><a href="#">تيليجرام</a></li>
                <li><a href="#">يوتيوب</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>© <?php echo date('Y'); ?> <a href="https://souikadz.com">صفارة لايف</a> — جميع الحقوق محفوظة</p>
    </div>
</footer>

<!-- ══ بيانات PHP → JS ══ -->
<script>
// بيانات المباريات من PHP
var MATCHES_DATA = <?php echo json_encode(array_values($matches)); ?>;
var NEXT_MATCH_TS = <?php echo $next_match_ts ? $next_match_ts * 1000 : 'null'; ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ── الثيم ── */
    var html  = document.documentElement;
    var btn   = document.getElementById('themeToggle');
    var saved = localStorage.getItem('saffara-theme') || 'dark';
    html.setAttribute('data-theme', saved);
    btn.textContent = saved === 'dark' ? '🌙' : '☀️';
    btn.addEventListener('click', function () {
        var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        btn.textContent = next === 'dark' ? '🌙' : '☀️';
        localStorage.setItem('saffara-theme', next);
    });

    /* ── التوقيت المحلي ── */
    document.querySelectorAll('.match-time-local').forEach(function(el) {
        var d = new Date(el.getAttribute('data-time').replace(' ', 'T') + 'Z');
        el.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes());
    });

    /* ── التاريخ والساعة ── */
    var days   = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
    var months = ['جانفي','فيفري','مارس','أفريل','ماي','جوان','جويلية','أوت','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    var now    = new Date();
    var dateEl = document.getElementById('today-date');
    if (dateEl) dateEl.textContent = days[now.getDay()] + ' ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();

    function updateClock() {
        var t = new Date();
        var el = document.getElementById('local-time');
        if (el) el.textContent = '🕐 ' + pad(t.getHours()) + ':' + pad(t.getMinutes()) + ':' + pad(t.getSeconds());
    }
    updateClock();
    setInterval(updateClock, 1000);

    function pad(n) { return String(n).padStart(2,'0'); }

    /* ── عداد تنازلي ── */
    if (NEXT_MATCH_TS) {
        // إيجاد اسم المباراة القادمة
        var nextMatch = MATCHES_DATA.find(function(m) {
            return new Date(m.match_datetime_utc.replace(' ','T')+'Z').getTime() === NEXT_MATCH_TS;
        });
        if (nextMatch) {
            document.getElementById('cd-match-name').textContent =
                nextMatch.team1_name + ' × ' + nextMatch.team2_name;
        }
        var bar = document.getElementById('countdown-bar');
        bar.style.display = 'block';

        function tickCountdown() {
            var diff = Math.floor((NEXT_MATCH_TS - Date.now()) / 1000);
            if (diff <= 0) { bar.style.display = 'none'; return; }
            var h = Math.floor(diff / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;
            document.getElementById('cd-h').textContent = pad(h);
            document.getElementById('cd-m').textContent = pad(m);
            document.getElementById('cd-s').textContent = pad(s);
        }
        tickCountdown();
        setInterval(tickCountdown, 1000);
    }

    /* ── عداد الفلاتر ── */
    function updateCounts() {
        var all      = document.querySelectorAll('.match-card').length;
        var live     = document.querySelectorAll('.match-card[data-status="live"]').length;
        var upcoming = document.querySelectorAll('.match-card[data-status="upcoming"]').length;
        var finished = document.querySelectorAll('.match-card[data-status="finished"]').length;
        document.getElementById('cnt-all').textContent      = all;
        document.getElementById('cnt-live').textContent     = live;
        document.getElementById('cnt-upcoming').textContent = upcoming;
        document.getElementById('cnt-finished').textContent = finished;
    }
    updateCounts();

    /* ── فلترة ── */
    document.querySelectorAll('.filter-btn').forEach(function(fbtn) {
        fbtn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(function(b){ b.classList.remove('active'); });
            fbtn.classList.add('active');
            var filter = fbtn.getAttribute('data-filter');

            document.querySelectorAll('.match-card').forEach(function(card) {
                var status = card.getAttribute('data-status');
                if (filter === 'all' || status === filter) {
                    card.classList.remove('hidden-filter');
                } else {
                    card.classList.add('hidden-filter');
                }
            });

            // إخفاء مجموعة بطولة إذا كل بطاقاتها مخفية
            document.querySelectorAll('.league-group').forEach(function(grp) {
                var visible = grp.querySelectorAll('.match-card:not(.hidden-filter)').length;
                if (visible === 0) grp.classList.add('all-hidden');
                else               grp.classList.remove('all-hidden');
            });
        });
    });

    /* ── بحث ── */
    var searchInput   = document.getElementById('searchInput');
    var searchResults = document.getElementById('searchResults');

    searchInput.addEventListener('input', function() {
        var q = this.value.trim().toLowerCase();
        searchResults.innerHTML = '';
        if (q.length < 2) { searchResults.classList.remove('show'); return; }

        var found = [];
        MATCHES_DATA.forEach(function(m) {
            var t1 = m.team1_name.toLowerCase();
            var t2 = m.team2_name.toLowerCase();
            var lg = m.competition.toLowerCase();
            if (t1.includes(q) || t2.includes(q) || lg.includes(q)) {
                found.push(m);
            }
        });

        if (found.length === 0) {
            searchResults.innerHTML = '<div class="search-item" style="color:var(--muted)">لا توجد نتائج</div>';
        } else {
            found.slice(0,6).forEach(function(m) {
                var item = document.createElement('div');
                item.className = 'search-item';
                item.innerHTML = m.team1_name + ' <span>VS</span> ' + m.team2_name + ' <span>· ' + m.competition + '</span>';
                item.addEventListener('click', function() {
                    // تمييز البطاقة
                    document.querySelectorAll('.match-card').forEach(function(c){ c.style.outline = ''; });
                    var card = document.querySelector('.match-card[data-match-id="' + m.id + '"]');
                    if (card) {
                        card.scrollIntoView({behavior:'smooth', block:'center'});
                        card.style.outline = '2px solid var(--accent)';
                        setTimeout(function(){ card.style.outline = ''; }, 2500);
                    }
                    searchInput.value = '';
                    searchResults.classList.remove('show');
                });
                searchResults.appendChild(item);
            });
        }
        searchResults.classList.add('show');
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.remove('show');
        }
    });

    /* ── تحديث حالة المباريات كل 30 ثانية ── */
    function updateMatchStatus() {
        var now = new Date();
        document.querySelectorAll('.match-card').forEach(function(card) {
            var matchTimeStr = card.getAttribute('data-match-time');
            var matchId      = card.getAttribute('data-match-id');
            if (!matchTimeStr) return;

            var matchTime   = new Date(matchTimeStr.replace(' ','T') + 'Z');
            var diffSeconds = (now - matchTime) / 1000;
            var statusEl    = card.querySelector('.match-status');
            var timeEl      = card.querySelector('.match-time-local');
            var footerEl    = card.querySelector('.card-footer');

            if (diffSeconds < -900) {
                card.setAttribute('data-status', 'upcoming');
                statusEl.className = 'match-status upcoming';
                statusEl.innerHTML = '⏰ لم تبدأ بعد';
                if (timeEl) timeEl.style.display = '';
                footerEl.innerHTML = '<a href="/match.php?slug=' + matchId + '" class="watch-btn"><span class="play-circle">▶</span>شاهد المباراة</a>';
            } else if (diffSeconds <= 7200) {
                card.setAttribute('data-status', 'live');
                statusEl.className = 'match-status live';
                statusEl.innerHTML = '<span class="status-dot"></span> جارية الآن';
                if (timeEl) timeEl.style.display = 'none';
                footerEl.innerHTML = '<a href="/streams/generate.php?match_id=' + matchId + '" class="watch-btn"><span class="play-circle">▶</span>شاهد الآن</a>';
            } else {
                card.setAttribute('data-status', 'finished');
                statusEl.className = 'match-status finished';
                statusEl.innerHTML = '✓ انتهت';
                if (timeEl) timeEl.style.display = 'none';
                footerEl.innerHTML = '<span class="finished-label">✓ انتهت المباراة</span>';
            }
        });
        updateCounts();
    }
    setInterval(updateMatchStatus, 30000);

});

/* ── طي/فتح البطولة ── */
function toggleLeague(header) {
    var grp = header.closest('.league-group');
    grp.classList.toggle('collapsed');
}
</script>
</body>
</html>
