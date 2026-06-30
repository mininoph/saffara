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

// تجهيز المباريات
$now_ts        = time();
$next_match_ts = null;
$next_match_name = '';

foreach ($matches as $key => $match) {
    $matches[$key]['match_datetime_original'] = $match['match_datetime'];
    $dt = new DateTime($match['match_datetime'], new DateTimeZone('UTC'));
    $matches[$key]['match_datetime_utc'] = $dt->format('Y-m-d H:i:s');
    $matches[$key]['match_ts'] = $dt->getTimestamp();
    $diff = $now_ts - $dt->getTimestamp();
    if ($diff < -900) {
        $matches[$key]['status'] = 'upcoming';
        if ($next_match_ts === null || $dt->getTimestamp() < $next_match_ts) {
            $next_match_ts   = $dt->getTimestamp();
            $next_match_name = $match['team1_name'] . ' × ' . $match['team2_name'];
        }
    } elseif ($diff <= 7200) {
        $matches[$key]['status'] = 'live';
    } else {
        $matches[$key]['status'] = 'finished';
    }
}

// تجميع حسب البطولة
$grouped = [];
foreach ($matches as $match) {
    $comp = $match['competition'];
    if (!isset($grouped[$comp])) $grouped[$comp] = [];
    $grouped[$comp][] = $match;
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
/* ══ متغيرات ══ */
:root,[data-theme="dark"]{
    --bg-deep:#050810;--bg-page:#070c18;--bg-card:#0c1120;--bg-card2:#111827;
    --bg-header:rgba(5,8,16,0.96);--accent:#00e5a0;--accent2:#00b87a;
    --accent-glow:rgba(0,229,160,0.18);--red:#ff3b5c;--gold:#f5c842;
    --text:#e8eaf0;--text-sub:#9ca3af;--muted:#6b7280;
    --border:rgba(255,255,255,0.08);--border-h:rgba(0,229,160,0.22);
    --shadow:0 14px 36px rgba(0,0,0,0.55);
}
[data-theme="light"]{
    --bg-deep:#eef2f7;--bg-page:#f0f4f8;--bg-card:#fff;--bg-card2:#f8fafc;
    --bg-header:rgba(248,250,252,0.96);--accent:#00a372;--accent2:#007f59;
    --accent-glow:rgba(0,163,114,0.15);--red:#e02040;--gold:#c9930a;
    --text:#111827;--text-sub:#4b5563;--muted:#9ca3af;
    --border:rgba(0,0,0,0.09);--border-h:rgba(0,163,114,0.3);
    --shadow:0 4px 24px rgba(0,0,0,0.10);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Cairo',Tahoma,Arial,sans-serif;
    background:var(--bg-page);color:var(--text);
    min-height:100vh;overflow-x:hidden;transition:background .3s,color .3s;
}
[data-theme="dark"] body::before{
    content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
    background:
        radial-gradient(ellipse 70% 40% at 20% 10%,rgba(0,229,160,.06) 0%,transparent 70%),
        radial-gradient(ellipse 50% 30% at 80% 80%,rgba(0,100,255,.05) 0%,transparent 70%);
}

/* ══ شريط الأخبار العاجلة ══ */
.top-ticker{
    background:linear-gradient(90deg,#0a0f1a,#0d1520,#0a0f1a);
    border-bottom:1px solid var(--border);
    overflow:hidden;height:32px;display:flex;align-items:center;
}
[data-theme="light"] .top-ticker{background:linear-gradient(90deg,#e8edf5,#f0f4f8,#e8edf5);}
.ticker-label{
    flex-shrink:0;background:var(--accent);color:#07090f;
    font-size:11px;font-weight:900;padding:0 16px;height:100%;
    display:flex;align-items:center;gap:5px;letter-spacing:.5px;z-index:2;
    clip-path:polygon(0 0,100% 0,88% 100%,0 100%);padding-left:14px;padding-right:22px;
}
.ticker-scroll{overflow:hidden;flex:1;position:relative;}
.ticker-track{
    display:flex;gap:0;white-space:nowrap;
    animation:ticker 30s linear infinite;
}
.ticker-track:hover{animation-play-state:paused;}
.ticker-item{
    display:inline-flex;align-items:center;gap:8px;
    padding:0 22px;font-size:12px;font-weight:600;color:var(--text-sub);
    border-right:1px solid var(--border);flex-shrink:0;
}
.ticker-item .tscore{color:var(--gold);font-weight:900;}
.ticker-item .tdot{width:5px;height:5px;background:var(--red);border-radius:50%;animation:blink 1.2s infinite;flex-shrink:0;}
@keyframes ticker{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
@keyframes blink{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(.7)}}

/* ══ هيدر رئيسي ══ */
header{
    position:sticky;top:0;z-index:500;
    background:var(--bg-header);
    backdrop-filter:blur(22px) saturate(1.5);
    -webkit-backdrop-filter:blur(22px) saturate(1.5);
    border-bottom:1px solid var(--border);
    transition:background .3s;
}
.header-main{
    max-width:1100px;margin:0 auto;padding:0 20px;
    display:flex;align-items:center;gap:14px;height:58px;
}

/* لوغو */
.logo{display:flex;align-items:center;gap:10px;text-decoration:none;flex-shrink:0;}
.logo:hover{opacity:.85;}
.logo-mark{
    width:40px;height:40px;flex-shrink:0;
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    border-radius:11px;display:flex;align-items:center;justify-content:center;
    font-size:20px;box-shadow:0 0 14px var(--accent-glow);
}
.logo-ar{font-size:18px;font-weight:900;color:var(--text);display:block;line-height:1;}
.logo-en{font-size:10px;color:var(--accent);font-weight:700;letter-spacing:2px;text-transform:uppercase;display:block;margin-top:1px;}

/* ناف */
nav{display:flex;align-items:center;gap:2px;flex:1;justify-content:center;}
.nav-link{
    position:relative;padding:7px 12px;
    font-size:13px;font-weight:700;color:var(--text-sub);
    text-decoration:none;border-radius:8px;
    transition:color .2s,background .2s;white-space:nowrap;
    display:flex;align-items:center;gap:5px;
}
.nav-link:hover{color:var(--text);background:rgba(255,255,255,.05);}
[data-theme="light"] .nav-link:hover{background:rgba(0,0,0,.05);}
.nav-link.active{color:var(--accent);}
.nav-link.active::after{
    content:'';position:absolute;bottom:3px;left:50%;transform:translateX(-50%);
    width:16px;height:2px;background:var(--accent);border-radius:2px;
}
.live-badge{
    font-size:9px;font-weight:900;background:var(--red);color:#fff;
    padding:1px 6px;border-radius:10px;animation:blink 1.5s infinite;
}

/* بحث */
.search-wrap{position:relative;flex-shrink:0;}
.search-btn{
    width:36px;height:36px;border-radius:10px;
    background:var(--bg-card2);border:1px solid var(--border);
    color:var(--text-sub);cursor:pointer;font-size:15px;
    display:flex;align-items:center;justify-content:center;
    transition:border-color .2s,color .2s;
}
.search-btn:hover{border-color:var(--accent);color:var(--accent);}
.search-dropdown{
    display:none;position:absolute;top:calc(100% + 8px);
    left:50%;transform:translateX(-50%);
    width:320px;background:var(--bg-card);border:1px solid var(--border-h);
    border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,.5);overflow:hidden;z-index:600;
}
.search-dropdown.open{display:block;animation:fadeDown .15s ease;}
@keyframes fadeDown{from{opacity:0;transform:translateX(-50%) translateY(-6px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
.search-inp-row{display:flex;align-items:center;gap:10px;padding:12px 14px;border-bottom:1px solid var(--border);}
.search-inp-row span{color:var(--muted);font-size:14px;}
.search-inp-row input{
    flex:1;background:none;border:none;outline:none;
    color:var(--text);font-family:inherit;font-size:13px;font-weight:600;
}
.search-inp-row input::placeholder{color:var(--muted);}
.search-tags{padding:10px 12px;display:flex;gap:6px;flex-wrap:wrap;}
.stag{
    font-size:11px;font-weight:700;color:var(--text-sub);
    background:var(--bg-card2);border:1px solid var(--border);
    padding:3px 10px;border-radius:20px;cursor:pointer;transition:all .15s;
}
.stag:hover{border-color:var(--accent);color:var(--accent);}
.search-results-list{max-height:200px;overflow-y:auto;}
.sr-item{
    padding:10px 14px;font-size:13px;font-weight:600;color:var(--text);
    cursor:pointer;border-bottom:1px solid var(--border);transition:background .15s;
    display:flex;align-items:center;justify-content:space-between;
}
.sr-item:last-child{border-bottom:none;}
.sr-item:hover{background:var(--bg-card2);}
.sr-item span{font-size:11px;color:var(--muted);}

/* أدوات */
.header-tools{display:flex;align-items:center;gap:7px;flex-shrink:0;}
.tool-btn{
    width:36px;height:36px;border-radius:10px;
    background:var(--bg-card2);border:1px solid var(--border);
    color:var(--text-sub);cursor:pointer;font-size:15px;
    display:flex;align-items:center;justify-content:center;
    transition:border-color .2s,color .2s;
}
.tool-btn:hover{border-color:var(--accent);color:var(--accent);}
.btn-login{
    padding:7px 16px;background:transparent;
    border:1.5px solid var(--accent);color:var(--accent);
    border-radius:10px;font-family:inherit;font-size:13px;font-weight:800;
    cursor:pointer;transition:all .2s;text-decoration:none;
    display:flex;align-items:center;gap:5px;white-space:nowrap;
}
.btn-login:hover{background:var(--accent);color:#07090f;}

/* ══ شريط البطولات ══ */
.leagues-bar{
    background:var(--bg-card2);border-bottom:1px solid var(--border);
    position:sticky;top:58px;z-index:400;
}
.leagues-inner{
    max-width:1100px;margin:0 auto;padding:0 20px;
    display:flex;align-items:center;gap:4px;height:40px;
    overflow-x:auto;scrollbar-width:none;
}
.leagues-inner::-webkit-scrollbar{display:none;}
.lpill{
    display:flex;align-items:center;gap:6px;padding:4px 13px;
    border-radius:30px;font-size:12px;font-weight:700;
    color:var(--text-sub);cursor:pointer;white-space:nowrap;
    transition:all .2s;border:1px solid transparent;
    flex-shrink:0;text-decoration:none;
}
.lpill:hover{background:rgba(255,255,255,.05);color:var(--text);}
[data-theme="light"] .lpill:hover{background:rgba(0,0,0,.05);}
.lpill.active{background:rgba(0,229,160,.1);border-color:rgba(0,229,160,.25);color:var(--accent);}

/* ══ شريط العداد التنازلي ══ */
#countdown-bar{
    display:none;position:relative;z-index:5;
    background:linear-gradient(135deg,rgba(0,229,160,.1),rgba(0,100,255,.06));
    border-bottom:1px solid rgba(0,229,160,.15);padding:9px 20px;
}
.cd-inner{
    max-width:1100px;margin:0 auto;
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;
}
.cd-label{font-size:13px;font-weight:700;color:var(--accent);}
.cd-label strong{color:var(--text);}
.cd-timer{display:flex;gap:6px;}
.cd-unit{
    display:flex;flex-direction:column;align-items:center;
    background:var(--bg-card);border:1px solid var(--border-h);
    border-radius:8px;padding:3px 10px;min-width:48px;
}
.cd-num{font-size:20px;font-weight:900;color:var(--accent);line-height:1;}
.cd-lbl{font-size:10px;color:var(--muted);font-weight:700;margin-top:1px;}
.cd-sep{font-size:18px;color:var(--accent);font-weight:900;align-self:center;margin-top:-4px;}

/* ══ شريط اليوم + فلاتر ══ */
.top-bar{
    max-width:1100px;margin:16px auto 0;padding:0 20px;
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;
}
.date-nav{display:flex;align-items:center;gap:8px;}
.day-btn{
    padding:6px 16px;border-radius:30px;font-size:13px;font-weight:700;
    text-decoration:none;border:1px solid var(--border);
    background:transparent;color:var(--text-sub);cursor:pointer;
    transition:all .2s;font-family:inherit;
}
.day-btn.active,.day-btn:hover{
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#050810;border-color:transparent;
}
.right-info{display:flex;align-items:center;gap:10px;}
.date-val{font-size:13px;color:var(--accent);font-weight:700;}
.clock-val{font-size:12px;color:var(--muted);font-weight:600;}

.filter-bar{
    max-width:1100px;margin:12px auto 0;padding:0 20px;
    display:flex;align-items:center;gap:7px;flex-wrap:wrap;
}
.filter-btn{
    padding:6px 15px;border-radius:30px;font-size:12px;font-weight:700;
    border:1px solid var(--border);background:var(--bg-card2);
    color:var(--text-sub);cursor:pointer;font-family:inherit;
    transition:all .2s;display:flex;align-items:center;gap:5px;
}
.filter-btn.active{background:var(--accent);color:#050810;border-color:transparent;}
.filter-btn:hover:not(.active){border-color:var(--accent);color:var(--accent);}
.fcnt{
    background:rgba(255,255,255,.15);color:inherit;
    font-size:10px;padding:1px 6px;border-radius:20px;font-weight:900;
}
.filter-btn.active .fcnt{background:rgba(0,0,0,.2);}

.divider{height:1px;max-width:1100px;margin:12px auto 0;background:linear-gradient(90deg,transparent,var(--border),transparent);}

/* ══ محتوى ══ */
main{position:relative;z-index:5;max-width:1100px;margin:20px auto 50px;padding:0 20px;}

/* ══ مجموعة بطولة ══ */
.league-group{margin-bottom:18px;}
.league-header{
    display:flex;align-items:center;justify-content:space-between;
    background:var(--bg-card2);border:1px solid var(--border);
    border-radius:14px 14px 0 0;padding:9px 16px;
    cursor:pointer;transition:border-color .2s;user-select:none;
}
.league-header:hover{border-color:var(--border-h);}
.lh-left{display:flex;align-items:center;gap:10px;}
.lh-title{font-size:14px;font-weight:800;color:var(--text);}
.lh-count{font-size:11px;font-weight:700;color:var(--muted);background:rgba(255,255,255,.06);padding:2px 8px;border-radius:20px;}
.lh-live{font-size:11px;font-weight:800;color:var(--red);background:rgba(255,59,92,.1);padding:2px 9px;border-radius:20px;display:flex;align-items:center;gap:4px;}
.lh-dot{width:5px;height:5px;background:var(--red);border-radius:50%;animation:blink 1.2s infinite;display:inline-block;}
.lh-toggle{font-size:13px;color:var(--muted);transition:transform .3s;}
.league-group.collapsed .lh-toggle{transform:rotate(-90deg);}
.league-body{border:1px solid var(--border);border-top:none;border-radius:0 0 14px 14px;overflow:hidden;}
.league-group.collapsed .league-body{display:none;}

/* ══ بطاقة مباراة ══ */
.match-card{
    background:var(--bg-card);border-bottom:1px solid var(--border);
    transition:background .2s;position:relative;
}
.match-card:last-child{border-bottom:none;}
.match-card:hover{background:var(--bg-card2);}
.match-card.hidden-filter{display:none;}
.league-group.all-hidden{display:none;}

.card-top{
    padding:6px 16px;
    display:flex;align-items:center;justify-content:flex-end;
    border-bottom:1px solid var(--border);
}
.match-status{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 11px;border-radius:20px;}
.match-status.upcoming{background:rgba(245,200,66,.10);color:var(--gold);border:1px solid rgba(245,200,66,.25);}
.match-status.live{background:rgba(255,59,92,.12);color:var(--red);border:1px solid rgba(255,59,92,.28);}
.match-status.finished{background:rgba(107,114,128,.10);color:var(--muted);border:1px solid rgba(107,114,128,.18);}
.status-dot{width:6px;height:6px;background:var(--red);border-radius:50%;animation:blink 1.4s infinite;display:inline-block;}

.teams-row{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 12px;gap:10px;}
.team{display:flex;flex-direction:column;align-items:center;gap:8px;flex:1;min-width:0;}
.team-logo-wrap{
    width:66px;height:66px;border-radius:50%;
    background:var(--bg-deep);border:2px solid var(--border);
    display:flex;align-items:center;justify-content:center;overflow:hidden;
    transition:border-color .3s;
}
.match-card:hover .team-logo-wrap{border-color:var(--border-h);}
.team-logo{width:52px;height:52px;object-fit:contain;}
.team-name{font-size:14px;font-weight:800;color:var(--text);text-align:center;line-height:1.3;max-width:120px;}

.match-center{display:flex;flex-direction:column;align-items:center;gap:4px;flex-shrink:0;padding:0 10px;}
.vs-badge{
    width:30px;height:30px;border-radius:50%;
    background:rgba(0,229,160,.08);border:1px solid var(--border-h);
    display:flex;align-items:center;justify-content:center;
    font-size:9px;font-weight:900;color:var(--accent);
}
.match-time-display{font-size:20px;font-weight:900;color:var(--gold);letter-spacing:1px;}
.match-time-display.hidden{display:none;}
.match-result{font-size:22px;font-weight:900;color:var(--gold);letter-spacing:2px;background:rgba(0,0,0,.2);padding:3px 14px;border-radius:10px;}

.card-info{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;padding:10px 16px;border-top:1px solid var(--border);}
.info-tag{display:flex;align-items:center;gap:4px;background:rgba(255,255,255,.04);border:1px solid var(--border);color:var(--text-sub);font-size:12px;padding:4px 12px;border-radius:20px;font-weight:600;}
[data-theme="light"] .info-tag{background:rgba(0,0,0,.04);}

.card-footer{padding:10px 16px 14px;}
.watch-btn{
    display:flex;align-items:center;justify-content:center;gap:8px;
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#050810;text-decoration:none;padding:11px;border-radius:12px;
    font-weight:900;font-size:14px;transition:all .22s;
    cursor:pointer;border:none;width:100%;box-shadow:0 4px 16px var(--accent-glow);
}
.watch-btn:hover{filter:brightness(1.08);transform:translateY(-1px);box-shadow:0 6px 22px var(--accent-glow);}
.play-circle{width:20px;height:20px;background:rgba(5,8,16,.22);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;padding-right:1px;}
.finished-label{display:flex;align-items:center;justify-content:center;gap:5px;background:rgba(107,114,128,.18);border:1px solid rgba(107,114,128,.35);color:#a0aec0;padding:11px;border-radius:12px;font-size:13px;font-weight:700;width:100%;cursor:not-allowed;}

/* ══ لا مباريات ══ */
.no-matches{text-align:center;padding:70px 20px;}
.no-matches .big-icon{font-size:52px;display:block;margin-bottom:12px;opacity:.3;}
.no-matches p{font-size:16px;color:var(--muted);font-weight:600;}

/* ══ إشعار ══ */
.notice-bar{max-width:1100px;margin:14px auto 0;padding:0 20px;position:relative;z-index:10;}
.notice-bar-inner{display:flex;align-items:center;gap:8px;padding:9px 15px;border-radius:12px;font-size:12px;font-weight:600;}
.notice-bar-inner.warning{background:rgba(245,200,66,.08);border:1px solid rgba(245,200,66,.22);color:var(--gold);}

/* ══ فوتر ══ */
.main-footer{max-width:1100px;margin:30px auto 0;padding:0 20px 20px;}
.footer-inner{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:28px;background:var(--bg-card);border:1px solid var(--border);border-radius:18px;padding:30px 26px;}
.footer-logo{display:flex;align-items:center;gap:12px;margin-bottom:10px;}
.footer-logo-icon{width:42px;height:42px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 0 14px var(--accent-glow);}
.footer-logo h3{font-size:17px;font-weight:900;line-height:1;}
.footer-logo small{font-size:11px;color:var(--accent);font-weight:600;}
.footer-about{font-size:13px;color:var(--text-sub);line-height:1.6;margin-bottom:10px;}
.footer-col-title{font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;}
.footer-links{list-style:none;}
.footer-links li{margin-bottom:9px;}
.footer-links a{color:var(--text-sub);text-decoration:none;font-size:13px;transition:color .2s,padding-right .2s;display:inline-block;}
.footer-links a:hover{color:var(--accent);padding-right:5px;}
.footer-bottom{margin-top:14px;padding:14px 0 0;border-top:1px solid var(--border);text-align:center;font-size:13px;color:var(--muted);}
.footer-bottom a{color:var(--accent);font-weight:700;text-decoration:none;}

/* ══ ريسبونسف ══ */
@media(max-width:900px){nav{display:none;}}
@media(max-width:600px){
    .top-ticker{display:none;}
    .logo-ar{font-size:15px;}
    .footer-inner{grid-template-columns:1fr 1fr;gap:20px;padding:20px 16px;}
}
@media(max-width:420px){.footer-inner{grid-template-columns:1fr;}}
</style>
</head>
<body>

<!-- ══ شريط الأخبار العاجلة ══ -->
<div class="top-ticker">
    <div class="ticker-label">⚡ عاجل</div>
    <div class="ticker-scroll">
        <div class="ticker-track" id="tickerTrack">
            <?php
            // نأخذ المباريات الجارية للشريط
            $live_matches   = array_filter($matches, fn($m) => $m['status'] === 'live');
            $upcoming_first = array_slice(array_filter($matches, fn($m) => $m['status'] === 'upcoming'), 0, 4);
            $ticker_items   = array_merge(array_values($live_matches), array_values($upcoming_first));
            if (empty($ticker_items)) {
                echo '<div class="ticker-item">⚽ مرحباً بك في صفارة لايف — منصة البث الرياضي الأولى</div>';
                echo '<div class="ticker-item">📺 تابع جميع المباريات بجودة HD وتعليق عربي</div>';
                echo '<div class="ticker-item">🏆 نغطي أهم البطولات العالمية والمحلية</div>';
            } else {
                // عرض مرتين للتمرير اللانهائي
                for ($r = 0; $r < 2; $r++) {
                    foreach ($ticker_items as $tm) {
                        $dot = $tm['status'] === 'live' ? '<span class="tdot"></span>' : '⏰';
                        $score = $tm['status'] === 'live' ? ' <span class="tscore">جارية</span>' : '';
                        echo '<div class="ticker-item">' . $dot . ' ' . htmlspecialchars($tm['team1_name']) . ' × ' . htmlspecialchars($tm['team2_name']) . $score . ' · ' . htmlspecialchars($tm['competition']) . '</div>';
                    }
                }
            }
            ?>
        </div>
    </div>
</div>

<!-- ══ هيدر رئيسي ══ -->
<header>
    <div class="header-main">

        <!-- لوغو -->
        <a href="https://souikadz.com" class="logo">
            <div class="logo-mark">🏆</div>
            <div>
                <span class="logo-ar">صفارة لايف</span>
                <span class="logo-en">Saffara Live</span>
            </div>
        </a>

        <!-- ناف -->
        <nav>
            <a href="/" class="nav-link active">الرئيسية</a>
            <a href="/today/" class="nav-link">
                المباريات
                <?php if (!empty($live_matches)): ?>
                    <span class="live-badge">LIVE</span>
                <?php endif; ?>
            </a>
            <a href="/leagues/" class="nav-link">الترتيب</a>
            <a href="/news/" class="nav-link">الأخبار</a>
            <a href="/videos/" class="nav-link">الفيديوهات</a>
            <a href="/transfers/" class="nav-link">الانتقالات</a>
        </nav>

        <!-- بحث -->
        <div class="search-wrap">
            <button class="search-btn" id="searchBtn" onclick="toggleSearch()">🔍</button>
            <div class="search-dropdown" id="searchDrop">
                <div class="search-inp-row">
                    <span>🔍</span>
                    <input type="text" id="searchInp" placeholder="ابحث عن فريق أو بطولة...">
                </div>
                <div class="search-tags">
                    <?php
                    $leagues_list = array_keys($grouped);
                    foreach (array_slice($leagues_list, 0, 5) as $lg) {
                        echo '<span class="stag" onclick="searchFor(\'' . htmlspecialchars($lg, ENT_QUOTES) . '\')">' . htmlspecialchars($lg) . '</span>';
                    }
                    ?>
                </div>
                <div class="search-results-list" id="searchList"></div>
            </div>
        </div>

        <!-- أدوات -->
        <div class="header-tools">
            <button class="tool-btn" id="themeToggle" aria-label="الوضع الليلي">🌙</button>
            <button class="tool-btn" title="الإشعارات">🔔</button>
            <a href="/login/" class="btn-login">👤 دخول</a>
        </div>

    </div>
</header>

<!-- ══ شريط البطولات ══ -->
<div class="leagues-bar">
    <div class="leagues-inner">
        <a class="lpill active" href="#" data-league-filter="all">🌍 الكل</a>
        <?php foreach (array_keys($grouped) as $lg): ?>
            <a class="lpill" href="#" data-league-filter="<?php echo htmlspecialchars($lg, ENT_QUOTES); ?>">
                🏁 <?php echo htmlspecialchars($lg); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══ عداد تنازلي ══ -->
<div id="countdown-bar">
    <div class="cd-inner">
        <div class="cd-label">⚽ أقرب مباراة: <strong id="cd-name"><?php echo htmlspecialchars($next_match_name); ?></strong></div>
        <div class="cd-timer">
            <div class="cd-unit"><span class="cd-num" id="cd-h">00</span><span class="cd-lbl">ساعة</span></div>
            <span class="cd-sep">:</span>
            <div class="cd-unit"><span class="cd-num" id="cd-m">00</span><span class="cd-lbl">دقيقة</span></div>
            <span class="cd-sep">:</span>
            <div class="cd-unit"><span class="cd-num" id="cd-s">00</span><span class="cd-lbl">ثانية</span></div>
        </div>
    </div>
</div>

<!-- ══ شريط التاريخ والفلاتر ══ -->
<div class="top-bar">
    <div class="date-nav">
        <a href="/" class="day-btn active">اليوم</a>
        <a href="/tomorrow/" class="day-btn">الغد</a>
    </div>
    <div class="right-info">
        <span class="date-val" id="today-date"></span>
        <span class="clock-val" id="local-time"></span>
    </div>
</div>

<div class="filter-bar">
    <button class="filter-btn active" data-filter="all">الكل <span class="fcnt" id="cnt-all">0</span></button>
    <button class="filter-btn" data-filter="live">🔴 جارية <span class="fcnt" id="cnt-live">0</span></button>
    <button class="filter-btn" data-filter="upcoming">⏰ قادمة <span class="fcnt" id="cnt-upcoming">0</span></button>
    <button class="filter-btn" data-filter="finished">✓ انتهت <span class="fcnt" id="cnt-finished">0</span></button>
</div>
<div class="divider"></div>

<?php if ($api_error && $using_cache && $cache_age !== null): ?>
<div class="notice-bar">
    <div class="notice-bar-inner warning">⚠️ تعذر الاتصال — آخر تحديث منذ <?php echo round($cache_age/60); ?> دقيقة</div>
</div>
<?php endif; ?>

<!-- ══ المباريات ══ -->
<main>
<?php if ($api_error && !$using_cache): ?>
    <div class="no-matches"><span class="big-icon">⚠️</span><p>تعذر تحميل المباريات</p></div>
<?php elseif (count($matches) === 0): ?>
    <div class="no-matches"><span class="big-icon">⚽</span><p>لا توجد مباريات مجدولة اليوم</p></div>
<?php else: ?>
    <?php foreach ($grouped as $league_name => $league_matches): ?>
        <?php $live_cnt = count(array_filter($league_matches, fn($m) => $m['status'] === 'live')); ?>
        <div class="league-group" data-league="<?php echo htmlspecialchars($league_name, ENT_QUOTES); ?>">
            <div class="league-header" onclick="toggleLeague(this)">
                <div class="lh-left">
                    <span>🏁</span>
                    <span class="lh-title"><?php echo htmlspecialchars($league_name); ?></span>
                    <span class="lh-count"><?php echo count($league_matches); ?></span>
                    <?php if ($live_cnt > 0): ?>
                        <span class="lh-live"><span class="lh-dot"></span><?php echo $live_cnt; ?> مباشر</span>
                    <?php endif; ?>
                </div>
                <span class="lh-toggle">▼</span>
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
                         data-team1="<?php echo htmlspecialchars($match['team1_name'], ENT_QUOTES); ?>"
                         data-team2="<?php echo htmlspecialchars($match['team2_name'], ENT_QUOTES); ?>">

                        <div class="card-top">
                            <span class="match-status <?php echo $status; ?>">
                                <?php if ($status === 'live'): ?><span class="status-dot"></span><?php endif; ?>
                                <?php echo $status_text; ?>
                            </span>
                        </div>

                        <div class="teams-row">
                            <div class="team">
                                <div class="team-logo-wrap">
                                    <img src="<?php echo htmlspecialchars($match['team1_logo']); ?>" alt="<?php echo htmlspecialchars($match['team1_name']); ?>" class="team-logo" loading="lazy">
                                </div>
                                <span class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                            </div>
                            <div class="match-center">
                                <div class="vs-badge">VS</div>
                                <?php if ($status === 'finished'): ?>
                                    <div class="match-result"><?php echo $team1_score; ?> - <?php echo $team2_score; ?></div>
                                <?php else: ?>
                                    <div class="match-time-display match-time-local<?php echo $status === 'live' ? ' hidden' : ''; ?>"
                                         data-time="<?php echo $match['match_datetime_utc']; ?>">
                                        <?php echo date('H:i', strtotime($match['match_datetime_utc'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="team">
                                <div class="team-logo-wrap">
                                    <img src="<?php echo htmlspecialchars($match['team2_logo']); ?>" alt="<?php echo htmlspecialchars($match['team2_name']); ?>" class="team-logo" loading="lazy">
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
                                    <span class="play-circle">▶</span><?php echo $btn_label; ?>
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
                <li><a href="/hes-goals/">Hes Goals</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4 class="footer-col-title">⚽ المباريات</h4>
            <ul class="footer-links">
                <li><a href="/today/">مباريات اليوم</a></li>
                <li><a href="/tomorrow/">مباريات الغد</a></li>
                <li><a href="/results/">نتائج المباريات</a></li>
                <li><a href="/leagues/">جدول الدوريات</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4 class="footer-col-title">📱 تابعنا</h4>
            <ul class="footer-links">
                <li><a href="#">فيسبوك</a></li>
                <li><a href="#">تويتر / X</a></li>
                <li><a href="#">يوتيوب</a></li>
                <li><a href="#">تيليجرام</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>© <?php echo date('Y'); ?> <a href="https://souikadz.com">صفارة لايف</a> — جميع الحقوق محفوظة</p>
    </div>
</footer>

<!-- ══ بيانات PHP ══ -->
<script>
var MATCHES_DATA   = <?php echo json_encode(array_values($matches)); ?>;
var NEXT_MATCH_TS  = <?php echo $next_match_ts ? $next_match_ts * 1000 : 'null'; ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){

    function pad(n){ return String(n).padStart(2,'0'); }

    /* ── ثيم ── */
    var html  = document.documentElement;
    var thBtn = document.getElementById('themeToggle');
    var saved = localStorage.getItem('saffara-theme') || 'dark';
    html.setAttribute('data-theme', saved);
    thBtn.textContent = saved === 'dark' ? '🌙' : '☀️';
    thBtn.addEventListener('click', function(){
        var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        thBtn.textContent = next === 'dark' ? '🌙' : '☀️';
        localStorage.setItem('saffara-theme', next);
    });

    /* ── توقيت محلي للمباريات ── */
    document.querySelectorAll('.match-time-local').forEach(function(el){
        if (el.classList.contains('hidden')) return;
        var d = new Date(el.getAttribute('data-time').replace(' ','T')+'Z');
        el.textContent = pad(d.getHours())+':'+pad(d.getMinutes());
    });

    /* ── التاريخ والساعة ── */
    var days   = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
    var months = ['جانفي','فيفري','مارس','أفريل','ماي','جوان','جويلية','أوت','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    var now    = new Date();
    var dateEl = document.getElementById('today-date');
    if (dateEl) dateEl.textContent = days[now.getDay()]+' '+now.getDate()+' '+months[now.getMonth()]+' '+now.getFullYear();
    function updateClock(){
        var t=new Date(), el=document.getElementById('local-time');
        if(el) el.textContent='🕐 '+pad(t.getHours())+':'+pad(t.getMinutes())+':'+pad(t.getSeconds());
    }
    updateClock(); setInterval(updateClock,1000);

    /* ── عداد تنازلي ── */
    if(NEXT_MATCH_TS){
        var bar = document.getElementById('countdown-bar');
        bar.style.display = 'block';
        function tickCD(){
            var diff = Math.floor((NEXT_MATCH_TS - Date.now())/1000);
            if(diff<=0){bar.style.display='none';return;}
            document.getElementById('cd-h').textContent = pad(Math.floor(diff/3600));
            document.getElementById('cd-m').textContent = pad(Math.floor((diff%3600)/60));
            document.getElementById('cd-s').textContent = pad(diff%60);
        }
        tickCD(); setInterval(tickCD,1000);
    }

    /* ── عدادات الفلاتر ── */
    function updateCounts(){
        var cards = document.querySelectorAll('.match-card');
        var live=0,up=0,fin=0;
        cards.forEach(function(c){
            var s=c.getAttribute('data-status');
            if(s==='live')live++;
            else if(s==='upcoming')up++;
            else fin++;
        });
        document.getElementById('cnt-all').textContent  = cards.length;
        document.getElementById('cnt-live').textContent = live;
        document.getElementById('cnt-upcoming').textContent = up;
        document.getElementById('cnt-finished').textContent = fin;
    }
    updateCounts();

    /* ── فلترة ── */
    document.querySelectorAll('.filter-btn').forEach(function(fb){
        fb.addEventListener('click', function(){
            document.querySelectorAll('.filter-btn').forEach(function(b){b.classList.remove('active');});
            fb.classList.add('active');
            var f = fb.getAttribute('data-filter');
            document.querySelectorAll('.match-card').forEach(function(c){
                c.classList.toggle('hidden-filter', f!=='all' && c.getAttribute('data-status')!==f);
            });
            document.querySelectorAll('.league-group').forEach(function(g){
                var v = g.querySelectorAll('.match-card:not(.hidden-filter)').length;
                g.classList.toggle('all-hidden', v===0);
            });
        });
    });

    /* ── فلترة البطولات (شريط الأسفل) ── */
    document.querySelectorAll('.lpill').forEach(function(pill){
        pill.addEventListener('click', function(e){
            e.preventDefault();
            document.querySelectorAll('.lpill').forEach(function(p){p.classList.remove('active');});
            pill.classList.add('active');
            var f = pill.getAttribute('data-league-filter');
            document.querySelectorAll('.league-group').forEach(function(g){
                g.classList.toggle('all-hidden', f!=='all' && g.getAttribute('data-league')!==f);
            });
        });
    });

    /* ── بحث ── */
    var searchInp  = document.getElementById('searchInp');
    var searchList = document.getElementById('searchList');

    searchInp.addEventListener('input', function(){
        var q = this.value.trim().toLowerCase();
        searchList.innerHTML = '';
        if(q.length<2) return;
        var found = MATCHES_DATA.filter(function(m){
            return m.team1_name.toLowerCase().includes(q) ||
                   m.team2_name.toLowerCase().includes(q) ||
                   m.competition.toLowerCase().includes(q);
        }).slice(0,6);
        found.forEach(function(m){
            var d=document.createElement('div');
            d.className='sr-item';
            d.innerHTML = m.team1_name+' VS '+m.team2_name+'<span>'+m.competition+'</span>';
            d.addEventListener('click', function(){
                var card=document.querySelector('.match-card[data-match-id="'+m.id+'"]');
                if(card){
                    card.scrollIntoView({behavior:'smooth',block:'center'});
                    card.style.outline='2px solid var(--accent)';
                    setTimeout(function(){card.style.outline='';},2500);
                }
                document.getElementById('searchDrop').classList.remove('open');
                searchInp.value='';
            });
            searchList.appendChild(d);
        });
        if(found.length===0) searchList.innerHTML='<div class="sr-item" style="color:var(--muted)">لا توجد نتائج</div>';
    });

    document.addEventListener('click', function(e){
        if(!document.querySelector('.search-wrap').contains(e.target))
            document.getElementById('searchDrop').classList.remove('open');
    });

    /* ── تحديث حالة المباريات ── */
    function updateMatchStatus(){
        var now=new Date();
        document.querySelectorAll('.match-card').forEach(function(card){
            var ts  = card.getAttribute('data-match-time');
            var mid = card.getAttribute('data-match-id');
            if(!ts) return;
            var mt   = new Date(ts.replace(' ','T')+'Z');
            var diff = (now-mt)/1000;
            var stEl = card.querySelector('.match-status');
            var tEl  = card.querySelector('.match-time-local');
            var fEl  = card.querySelector('.card-footer');
            if(diff < -900){
                card.setAttribute('data-status','upcoming');
                stEl.className='match-status upcoming';
                stEl.innerHTML='⏰ لم تبدأ بعد';
                if(tEl) tEl.classList.remove('hidden');
                fEl.innerHTML='<a href="/match.php?slug='+mid+'" class="watch-btn"><span class="play-circle">▶</span>شاهد المباراة</a>';
            } else if(diff<=7200){
                card.setAttribute('data-status','live');
                stEl.className='match-status live';
                stEl.innerHTML='<span class="status-dot"></span> جارية الآن';
                if(tEl) tEl.classList.add('hidden');
                fEl.innerHTML='<a href="/streams/generate.php?match_id='+mid+'" class="watch-btn"><span class="play-circle">▶</span>شاهد الآن</a>';
            } else {
                card.setAttribute('data-status','finished');
                stEl.className='match-status finished';
                stEl.innerHTML='✓ انتهت';
                if(tEl) tEl.classList.add('hidden');
                fEl.innerHTML='<span class="finished-label">✓ انتهت المباراة</span>';
            }
        });
        updateCounts();
    }
    setInterval(updateMatchStatus,30000);

});

/* ── طي البطولة ── */
function toggleLeague(h){h.closest('.league-group').classList.toggle('collapsed');}

/* ── فتح/إغلاق البحث ── */
function toggleSearch(){document.getElementById('searchDrop').classList.toggle('open');if(document.getElementById('searchDrop').classList.contains('open'))setTimeout(function(){document.getElementById('searchInp').focus();},50);}

/* ── بحث من الاقتراحات ── */
function searchFor(q){
    document.getElementById('searchInp').value=q;
    document.getElementById('searchInp').dispatchEvent(new Event('input'));
}
</script>
</body>
</html>
