<?php
// ============================================================
// صفارة لايف - نتائج المباريات
// ============================================================

date_default_timezone_set('Africa/Algiers');

// ── إعدادات ──────────────────────────────────────────────
define('API_URL', 'https://djelalda.online/backend/get_results.php');
define('CACHE_FILE', __DIR__ . '/cache/results.json');
define('CACHE_DURATION', 3600); // ساعة واحدة
define('API_TIMEOUT', 3);
define('API_MAX_TIME', 8);

function fetch_api(string $url): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?t=' . time(),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => API_TIMEOUT,
        CURLOPT_TIMEOUT => API_MAX_TIME,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curl_err) {
        return ['success' => false, 'reason' => 'connection_failed'];
    }
    if ($http_code !== 200) {
        return ['success' => false, 'reason' => 'http_' . $http_code];
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'reason' => 'invalid_json'];
    }
    if (empty($data['success']) || !isset($data['matches']) || !is_array($data['matches'])) {
        return ['success' => false, 'reason' => 'unexpected_structure'];
    }
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

$using_cache = false;
$cache_age = null;
$api_error = false;

$cached = read_cache();
$cache_valid = $cached && isset($cached['cache_age']) && $cached['cache_age'] < CACHE_DURATION;

if ($cache_valid) {
    $matches = $cached['matches'];
    $using_cache = true;
    $cache_age = $cached['cache_age'];
} else {
    $result = fetch_api(API_URL);
    if ($result['success']) {
        $matches = $result['matches'];
        write_cache($matches);
    } else {
        $api_error = true;
        if ($cached && isset($cached['matches'])) {
            $matches = $cached['matches'];
            $using_cache = true;
            $cache_age = $cached['cache_age'] ?? null;
        } else {
            $matches = [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتائج المباريات - صفارة لايف</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        /* ══ استيراد نفس الـ CSS من الصفحة الرئيسية ══ */
        :root,[data-theme="dark"] { --bg-deep: #050810; --bg-page: #070c18; --bg-card: #0c1120; --bg-card2: #111827; --bg-header: rgba(5,8,16,0.88); --accent: #00e5a0; --accent2: #00b87a; --accent-glow: rgba(0,229,160,0.18); --red: #ff3b5c; --gold: #f5c842; --text: #e8eaf0; --text-sub: #9ca3af; --muted: #6b7280; --border: rgba(255,255,255,0.08); --border-hover: rgba(0,229,160,0.22); --shadow-card: 0 14px 36px rgba(0,0,0,0.55); }
        [data-theme="light"] { --bg-deep: #f0f4f8; --bg-page: #f0f4f8; --bg-card: #ffffff; --bg-card2: #f8fafc; --bg-header: rgba(255,255,255,0.92); --accent: #00a372; --accent2: #007f59; --accent-glow: rgba(0,163,114,0.15); --red: #e02040; --gold: #c9930a; --text: #111827; --text-sub: #4b5563; --muted: #6b7280; --border: rgba(0,0,0,0.09); --border-hover: rgba(0,163,114,0.3); --shadow-card: 0 4px 24px rgba(0,0,0,0.10); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', 'Tahoma', Arial, sans-serif; background: var(--bg-page); color: var(--text); min-height: 100vh; overflow-x: hidden; transition: background 0.3s, color 0.3s; }
        [data-theme="dark"] body::before { content: ''; position: fixed; inset: 0; background: radial-gradient(ellipse 70% 40% at 20% 10%, rgba(0,229,160,0.06) 0%, transparent 70%), radial-gradient(ellipse 50% 30% at 80% 80%, rgba(0,100,255,0.05) 0%, transparent 70%); pointer-events: none; z-index: 0; }
        header { position: sticky; top: 0; z-index: 100; padding: 0 20px; background: var(--bg-header); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); border-bottom: 1px solid var(--border); transition: background 0.3s; }
        .header-inner { max-width: 820px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; padding: 15px 0; }
        .logo-block { display: flex; align-items: center; gap: 11px; text-decoration: none; transition: opacity 0.2s; }
        .logo-block:hover { opacity: 0.82; }
        .logo-icon { width: 44px; height: 44px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 21px; box-shadow: 0 0 16px var(--accent-glow); flex-shrink: 0; }
        .logo-text h1 { font-size: 20px; font-weight: 900; color: var(--text); line-height: 1; }
        .logo-text small { font-size: 11px; color: var(--accent); font-weight: 600; letter-spacing: 0.8px; }
        .header-right { display: flex; align-items: center; gap: 10px; }
        .theme-toggle { width: 38px; height: 38px; border-radius: 50%; border: 1px solid var(--border); background: var(--bg-card2); color: var(--text); font-size: 17px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s, border-color 0.2s, transform 0.2s; flex-shrink: 0; }
        .theme-toggle:hover { border-color: var(--accent); transform: rotate(20deg); }
        .live-pill { display: flex; align-items: center; gap: 6px; background: rgba(255,59,92,0.10); border: 1px solid rgba(255,59,92,0.28); color: var(--red); padding: 6px 13px; border-radius: 30px; font-size: 12px; font-weight: 700; }
        .live-dot { width: 7px; height: 7px; background: var(--red); border-radius: 50%; animation: blink 1.4s infinite; }
        @keyframes blink { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.3; transform: scale(0.7); } }
        .date-strip { position: relative; z-index: 5; max-width: 820px; margin: 28px auto 0; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; }
        .date-strip h2 { font-size: 14px; font-weight: 700; color: var(--muted); letter-spacing: 0.8px; text-transform: uppercase; }
        .date-val { font-size: 14px; color: var(--accent); font-weight: 700; }
        .divider { height: 1px; background: linear-gradient(90deg, transparent, var(--border), transparent); max-width: 820px; margin: 12px auto 0; }
        main { position: relative; z-index: 5; max-width: 820px; margin: 22px auto 50px; padding: 0 20px; }
        .matches-list { display: flex; flex-direction: column; gap: 14px; }
        .match-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 18px; overflow: hidden; transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s; position: relative; width: 100%; }
        .match-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-card), 0 0 0 1px var(--border-hover); border-color: var(--border-hover); }
        .match-card::after { content: ''; position: absolute; bottom: 0; right: 0; left: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--accent), transparent); opacity: 0; transition: opacity 0.3s; }
        .match-card:hover::after { opacity: 1; }
        .card-top { padding: 8px 16px; background: var(--bg-card2); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .league-name { font-size: 12px; font-weight: 700; color: var(--muted); }
        .match-status.finished { background: rgba(107,114,128,0.10); color: var(--muted); border: 1px solid rgba(107,114,128,0.18); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .teams-row { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px 14px; gap: 10px; }
        .team { display: flex; flex-direction: column; align-items: center; gap: 10px; flex: 1; min-width: 0; }
        .team-logo-wrap { width: 72px; height: 72px; border-radius: 50%; background: var(--bg-deep); border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; transition: border-color 0.3s; }
        .match-card:hover .team-logo-wrap { border-color: var(--border-hover); }
        .team-logo { width: 56px; height: 56px; object-fit: contain; }
        .team-name { font-size: 15px; font-weight: 800; color: var(--text); text-align: center; line-height: 1.3; max-width: 130px; }
        .match-center { display: flex; flex-direction: column; align-items: center; gap: 5px; flex-shrink: 0; padding: 0 12px; }
        .vs-badge { width: 34px; height: 34px; border-radius: 50%; background: rgba(0,229,160,0.08); border: 1px solid var(--border-hover); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 900; color: var(--accent); }
        .match-result { font-size: 20px; font-weight: 900; color: var(--gold); letter-spacing: 1px; }
        .card-info { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; padding: 12px 16px 14px; border-top: 1px solid var(--border); }
        .info-tag { display: flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.04); border: 1px solid var(--border); color: var(--text-sub); font-size: 12px; padding: 5px 13px; border-radius: 20px; font-weight: 600; }
        [data-theme="light"] .info-tag { background: rgba(0,0,0,0.04); }
        .no-matches { text-align: center; padding: 80px 20px; }
        .no-matches .big-icon { font-size: 54px; display: block; margin-bottom: 14px; opacity: 0.35; }
        .no-matches p { font-size: 17px; color: var(--muted); font-weight: 600; }
        .notice-bar { position: relative; z-index: 10; max-width: 820px; margin: 16px auto 0; padding: 0 20px; }
        .notice-bar-inner { display: flex; align-items: center; gap: 9px; padding: 10px 16px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .notice-bar-inner.warning { background: rgba(245,200,66,0.08); border: 1px solid rgba(245,200,66,0.22); color: var(--gold); }
        .notice-bar-inner.info { background: rgba(0,229,160,0.06); border: 1px solid rgba(0,229,160,0.15); color: var(--accent); }
        .footer { max-width: 820px; margin: 30px auto 0; padding: 0 20px 20px; text-align: center; border-top: 1px solid var(--border); padding-top: 20px; font-size: 13px; color: var(--muted); }
        .footer a { color: var(--accent); font-weight: 700; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
        @media (max-width: 600px) { .header-inner { padding: 12px 0; } .logo-text h1 { font-size: 17px; } .live-pill span:last-child { display: none; } .team-logo-wrap { width: 58px; height: 58px; } .team-logo { width: 44px; height: 44px; } .team-name { font-size: 13px; max-width: 90px; } .match-result { font-size: 17px; } .vs-badge { width: 28px; height: 28px; font-size: 9px; } .teams-row { padding: 14px 12px 12px; } .date-strip h2, .date-val { font-size: 12px; } }
    </style>
</head>
<body>

<header>
    <div class="header-inner">
        <a href="https://souikadz.com" class="logo-block">
            <div class="logo-icon">🏆</div>
            <div class="logo-text">
                <h1>صفارة لايف</h1>
                <small>saffara Live</small>
            </div>
        </a>
        <div class="header-right">
            <button class="theme-toggle" id="themeToggle" aria-label="تبديل الوضع">🌙</button>
            <div class="live-pill">
                <span class="live-dot"></span>
                <span>بث مباشر</span>
            </div>
        </div>
    </div>
</header>

<div class="date-strip">
    <h2>📊 نتائج المباريات</h2>
    <span class="date-val" id="today-date"></span>
</div>
<div class="divider"></div>

<?php if ($api_error && $using_cache && $cache_age !== null): ?>
    <div class="notice-bar">
        <div class="notice-bar-inner warning">
            ⚠️ تعذر الاتصال بالخادم — يتم عرض آخر نتائج متاحة
        </div>
    </div>
<?php elseif ($api_error && !$using_cache): ?>
    <div class="notice-bar">
        <div class="notice-bar-inner warning">
            ❌ تعذر تحميل النتائج — يرجى المحاولة لاحقاً
        </div>
    </div>
<?php endif; ?>

<main>
    <div class="matches-list">
        <?php if ($api_error): ?>
            <div class="no-matches">
                <span class="big-icon">⚠️</span>
                <p>لا يمكن الاتصال بالبانل — تأكد من الاتصال</p>
            </div>
        <?php elseif (count($matches) > 0): ?>
            <?php foreach ($matches as $match): ?>
                <div class="match-card">
                    <div class="card-top">
                        <span class="league-name">🏁 <?php echo htmlspecialchars($match['competition']); ?></span>
                        <span class="match-status finished">✓ انتهت</span>
                    </div>
                    <div class="teams-row">
                        <div class="team">
                            <div class="team-logo-wrap">
                                <img src="<?php echo htmlspecialchars($match['team1_logo']); ?>" alt="<?php echo htmlspecialchars($match['team1_name']); ?>" class="team-logo">
                            </div>
                            <span class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                        </div>
                        <div class="match-center">
                            <div class="vs-badge">VS</div>
                            <div class="match-result">
                                <?php 
                                $score1 = isset($match['team1_score']) ? $match['team1_score'] : '?';
                                $score2 = isset($match['team2_score']) ? $match['team2_score'] : '?';
                                echo $score1 . ' - ' . $score2; 
                                ?>
                            </div>
                        </div>
                        <div class="team">
                            <div class="team-logo-wrap">
                                <img src="<?php echo htmlspecialchars($match['team2_logo']); ?>" alt="<?php echo htmlspecialchars($match['team2_name']); ?>" class="team-logo">
                            </div>
                            <span class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                        </div>
                    </div>
                    <div class="card-info">
                        <span class="info-tag">📅 <?php echo date('Y-m-d H:i', strtotime($match['match_datetime'])); ?></span>
                        <?php if (!empty($match['channel_name'])): ?>
                            <span class="info-tag">📺 <?php echo htmlspecialchars($match['channel_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-matches">
                <span class="big-icon">📊</span>
                <p>لا توجد نتائج مباريات متاحة</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<footer class="footer">
    <p>© <?php echo date('Y'); ?> <a href="https://souikadz.com">صفارة لايف</a> — جميع الحقوق محفوظة</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var daysFr = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
    var monthsFr = ['جانفي','فيفري','مارس','أفريل','ماي','جوان','جويلية','أوت','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    var now = new Date();
    var dateEl = document.getElementById('today-date');
    if (dateEl) dateEl.textContent = daysFr[now.getDay()] + ' ' + now.getDate() + ' ' + monthsFr[now.getMonth()] + ' ' + now.getFullYear();

    var html = document.documentElement;
    var btn = document.getElementById('themeToggle');
    var saved = localStorage.getItem('saffara-theme') || 'dark';
    html.setAttribute('data-theme', saved);
    btn.textContent = saved === 'dark' ? '🌙' : '☀️';

    btn.addEventListener('click', function() {
        var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        btn.textContent = next === 'dark' ? '🌙' : '☀️';
        localStorage.setItem('saffara-theme', next);
    });
});
</script>
</body>
</html>