<?php
// ============================================================
// صفارة لايف - صفحة تفاصيل مباراة
// ============================================================
date_default_timezone_set('Asia/Riyadh');
require_once 'config.php';

// ══════════════════════════════════════════════════════════
// 1. تنظيف $slug — يقبل حروف وأرقام وشرطات فقط
// ══════════════════════════════════════════════════════════
$slug = trim($_GET['slug'] ?? '');

if (empty($slug)) {
    header('Location: index.php');
    exit;
}

// ══════════════════════════════════════════════════════════
// 2. cURL بدل file_get_contents + Cache
// ══════════════════════════════════════════════════════════
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

    if ($response === false || $curl_err)      return ['success' => false, 'reason' => 'connection_failed'];
    if ($http_code !== 200)                    return ['success' => false, 'reason' => 'http_' . $http_code];
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['success' => false, 'reason' => 'invalid_json'];
    if (empty($data['success']) || !isset($data['matches']) || !is_array($data['matches']))
                                               return ['success' => false, 'reason' => 'unexpected_structure'];
    return ['success' => true, 'matches' => $data['matches']];
}

function read_cache(): ?array {
    if (!file_exists(CACHE_FILE)) return null;
    $raw = file_get_contents(CACHE_FILE);
    if (!$raw) return null;
    $cached = json_decode($raw, true);
    return is_array($cached) ? $cached : null;
}

function write_cache(array $matches): void {
    $dir = dirname(CACHE_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(CACHE_FILE, json_encode(['matches' => $matches]), LOCK_EX);
}

// جلب البيانات — Cache أولاً ثم API
$matches = [];
$cached  = read_cache();
$cache_age = $cached ? (time() - filemtime(CACHE_FILE)) : PHP_INT_MAX;

if ($cached && $cache_age < CACHE_DURATION) {
    $matches = $cached['matches'];
} else {
    $result = fetch_api(PANEL_API_URL);
    if ($result['success']) {
        $matches = $result['matches'];
        write_cache($matches);
    } elseif ($cached) {
        $matches = $cached['matches']; // Cache قديم كاحتياط
    }
}

// البحث عن المباراة المطلوبة
$match = null;
foreach ($matches as $m) {
    if ((string)$m['id'] === (string)$slug) {
        $match = $m;
        break;
    }
}
if (!$match) {
    header('Location: index.php');
    exit;
}

// ══════════════════════════════════════════════════════════
// 3. التحقق من $match['id'] — رقم فقط
// ══════════════════════════════════════════════════════════
$match_id = filter_var($match['id'], FILTER_VALIDATE_INT);
if ($match_id === false || $match_id <= 0) {
    header('Location: index.php');
    exit;
}

// جلب التقرير من قاعدة البيانات
$report_from_db = '';
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $db->prepare("SELECT report FROM matches WHERE id = ?");
    $stmt->execute([$match_id]); // $match_id مضمون رقم الآن
    $report_from_db = $stmt->fetchColumn() ?: '';
} catch (PDOException $e) {
    // تجاهل الخطأ — التقرير اختياري
}

// حساب حالة المباراة
$now        = new DateTime('now', new DateTimeZone('UTC'));
$match_time = new DateTime($match['match_datetime'], new DateTimeZone('UTC'));
$diff = $now->getTimestamp() - $match_time->getTimestamp();

if ($diff > 7200) {
    $status = 'finished'; $status_text = '✓ انتهت';
} elseif ($diff >= -1800) {
    $status = 'live';     $status_text = '🔴 مباشر الآن';
} else {
    $status = 'upcoming'; $status_text = '⏰ قادمة';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($match['team1_name'] . ' vs ' . $match['team2_name']); ?> - صفارة لايف</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root, [data-theme="dark"] {
            --bg-deep: #050810; --bg-page: #070c18; --bg-card: #0c1120; --bg-card2: #111827;
            --bg-header: rgba(5,8,16,0.88); --accent: #00e5a0; --accent2: #00b87a;
            --accent-glow: rgba(0,229,160,0.18); --red: #ff3b5c; --gold: #f5c842;
            --text: #e8eaf0; --text-sub: #9ca3af; --muted: #6b7280;
            --border: rgba(255,255,255,0.08); --border-hover: rgba(0,229,160,0.22);
            --shadow-card: 0 14px 36px rgba(0,0,0,0.55);
        }
        [data-theme="light"] {
            --bg-deep: #f0f4f8; --bg-page: #f0f4f8; --bg-card: #ffffff; --bg-card2: #f8fafc;
            --bg-header: rgba(255,255,255,0.92); --accent: #00a372; --accent2: #007f59;
            --accent-glow: rgba(0,163,114,0.15); --red: #e02040; --gold: #c9930a;
            --text: #111827; --text-sub: #4b5563; --muted: #6b7280;
            --border: rgba(0,0,0,0.09); --border-hover: rgba(0,163,114,0.3);
            --shadow-card: 0 4px 24px rgba(0,0,0,0.10);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo','Tahoma',Arial,sans-serif; background: var(--bg-page); color: var(--text); min-height: 100vh; transition: background 0.3s, color 0.3s; }
        .container { max-width: 820px; margin: 0 auto; padding: 20px; }
        .back-link { display: inline-block; color: var(--accent); text-decoration: none; font-weight: 600; margin-bottom: 25px; transition: opacity 0.2s; }
        .back-link:hover { opacity: 0.7; }
        .match-header-hero { background: var(--bg-card); border-radius: 24px; padding: 40px 30px 30px; text-align: center; border: 1px solid var(--border); box-shadow: var(--shadow-card); margin-bottom: 30px; }
        .hero-teams { display: flex; align-items: center; justify-content: center; gap: 30px; margin-bottom: 15px; }
        .hero-team { display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .hero-team .team-logo { width: 100px; height: 100px; object-fit: contain; }
        .hero-team .team-name { font-weight: 900; font-size: 24px; color: var(--text); }
        .hero-vs { font-size: 40px; font-weight: 900; color: var(--gold); }
        .hero-meta { display: flex; justify-content: center; gap: 25px; flex-wrap: wrap; margin-top: 10px; color: var(--muted); font-size: 15px; }
        .hero-meta span { background: rgba(255,255,255,0.04); padding: 4px 16px; border-radius: 20px; border: 1px solid var(--border); }
        [data-theme="light"] .hero-meta span { background: rgba(0,0,0,0.04); }
        .report-section { background: var(--bg-card); border-radius: 24px; padding: 35px 30px; border: 1px solid var(--border); box-shadow: var(--shadow-card); margin-bottom: 30px; }
        .report-section h2 { font-size: 22px; font-weight: 700; color: var(--text); margin-bottom: 20px; border-right: 4px solid var(--accent); padding-right: 14px; }
        .report-section p { color: var(--text-sub); line-height: 1.9; font-size: 16px; margin-bottom: 16px; }
        .match-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 24px; padding: 30px; box-shadow: var(--shadow-card); }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px 30px; }
        .info-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .info-item .label { color: var(--muted); font-weight: 600; }
        .info-item .value { color: var(--text); font-weight: 700; }
        .match-status { display: inline-block; padding: 4px 18px; border-radius: 30px; font-weight: 700; font-size: 14px; margin-bottom: 20px; }
        .match-status.upcoming { background: rgba(245,200,66,0.10); color: var(--gold); border: 1px solid rgba(245,200,66,0.22); }
        .match-status.live { background: rgba(255,59,92,0.12); color: var(--red); border: 1px solid rgba(255,59,92,0.28); animation: blink 1.5s infinite; }
        .match-status.finished { background: rgba(107,114,128,0.10); color: var(--muted); border: 1px solid rgba(107,114,128,0.18); }
        @keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
        @media (max-width: 600px) {
            .hero-teams { gap: 15px; flex-wrap: wrap; }
            .hero-team .team-logo { width: 60px; height: 60px; }
            .hero-team .team-name { font-size: 18px; }
            .hero-vs { font-size: 28px; }
            .info-grid { grid-template-columns: 1fr; }
            .match-card { padding: 20px 16px; }
            .report-section { padding: 20px 16px; }
        }
    </style>
</head>
<body>
<div class="container">

    <a href="https://souikadz.com/" class="back-link">← العودة إلى المباريات</a>

    <div class="match-header-hero">
        <div class="hero-teams">
            <div class="hero-team">
                <img src="<?php echo htmlspecialchars($match['team1_logo']); ?>" class="team-logo" alt="<?php echo htmlspecialchars($match['team1_name']); ?>">
                <span class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
            </div>
            <div class="hero-vs">VS</div>
            <div class="hero-team">
                <img src="<?php echo htmlspecialchars($match['team2_logo']); ?>" class="team-logo" alt="<?php echo htmlspecialchars($match['team2_name']); ?>">
                <span class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
            </div>
        </div>
        <div class="hero-meta">
            <span>🏁 <?php echo htmlspecialchars($match['competition']); ?></span>
            <span>⏰ <span class="match-time-local" data-time="<?php echo date('Y-m-d H:i:s', strtotime($match['match_datetime'])); ?>"><?php echo date('H:i', strtotime($match['match_datetime'])); ?></span></span>
            
            <span>📅 <?php echo date('d/m/Y', strtotime($match['match_datetime'])); ?></span>
        
        </div>
    </div>

    <div class="report-section">
        <h2>📝 تقرير المباراة</h2>
        <?php if (!empty($report_from_db)): ?>
            <div style="background: var(--bg-card2); padding: 20px; border-radius: 16px; border: 1px solid var(--border);">
                <?php echo nl2br(htmlspecialchars($report_from_db)); ?>
            </div>
        <?php else: ?>
            <p style="color: var(--muted);">📭 لا يوجد تقرير متاح لهذه المباراة.</p>
        <?php endif; ?>
    </div>

    <div class="match-card">
        <span class="match-status <?php echo $status; ?>"><?php echo $status_text; ?></span>
        <div class="info-grid">
            <div class="info-item"><span class="label">🏆 البطولة</span><span class="value"><?php echo htmlspecialchars($match['competition']); ?></span></div>
            <div class="info-item"><span class="label">📅 التاريخ</span><span class="value"><?php echo date('d/m/Y', strtotime($match['match_datetime'])); ?></span></div>
           <div class="info-item"><span class="label">⏰ الوقت</span><span class="value"><span class="match-time-local" data-time="<?php echo date('Y-m-d H:i:s', strtotime($match['match_datetime'])); ?>"><?php echo date('H:i', strtotime($match['match_datetime'])); ?></span></span></div>
            <div class="info-item"><span class="label">📺 القناة</span><span class="value"><?php echo htmlspecialchars($match['channel_name']); ?></span></div>
            <div class="info-item"><span class="label">🎙️ المعلق</span><span class="value"><?php echo htmlspecialchars($match['commentator']); ?></span></div>
            <div class="info-item"><span class="label">🆔 رقم المباراة</span><span class="value">#<?php echo $match_id; ?></span></div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.match-time-local').forEach(function (el) {
       var d = new Date(el.getAttribute('data-time') + 'Z');
        el.textContent = String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    });
});
</script>
</body>
</html>