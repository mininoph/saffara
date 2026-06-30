<?php
date_default_timezone_set('Africa/Algiers');

define('API_BASE', 'https://djelalda.online/backend/get_leagues.php');
define('API_TIMEOUT', 3);
define('API_MAX_TIME', 8);

$leagues_list = [
    'world_cup'        => ['name_ar' => 'كأس العالم 2026',    'season' => '2026',      'flag' => '🌍', 'has_groups' => true],
    'premier_league'   => ['name_ar' => 'الدوري الإنجليزي',          'season' => '2026/2027', 'flag' => '🏴󠁿', 'has_groups' => false],
    'la_liga'          => ['name_ar' => 'الدوري الإسباني',           'season' => '2024/2025', 'flag' => '🇪🇸', 'has_groups' => false],
    'serie_a'          => ['name_ar' => 'الدوري الإيطالي',           'season' => '2026/2027', 'flag' => '🇮🇹', 'has_groups' => false],
    'bundesliga'       => ['name_ar' => 'الدوري الألماني',           'season' => '2025/2026', 'flag' => '🇩🇪', 'has_groups' => false],
    'ligue_1'          => ['name_ar' => 'الدوري الفرنسي',            'season' => '2024/2025', 'flag' => '🇫🇷', 'has_groups' => false],
    'champions_league' => ['name_ar' => 'دوري أبطال أوروبا',       'season' => '2025/2026', 'flag' => '⭐', 'has_groups' => false],
];

function fetch_league(string $league): array {
    $url = API_BASE . '?league=' . urlencode($league);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => API_TIMEOUT,
        CURLOPT_TIMEOUT        => API_MAX_TIME,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if (!$response || $err) return ['success' => false];
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($data['success'])) return ['success' => false];
    return $data;
}

$active = $_GET['league'] ?? 'world_cup';
if (!isset($leagues_list[$active])) $active = 'world_cup';
$data = fetch_league($active);
$activeCfg = $leagues_list[$active];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ترتيب الدوريات - صفارة لايف</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
:root,[data-theme="dark"]{
    --bg-page:#070c18;--bg-card:#0c1120;--bg-card2:#111827;
    --bg-header:rgba(5,8,16,0.92);--accent:#00e5a0;--accent2:#00b87a;
    --accent-glow:rgba(0,229,160,0.18);--red:#ff3b5c;--gold:#f5c842;
    --text:#e8eaf0;--text-sub:#9ca3af;--muted:#6b7280;
    --border:rgba(255,255,255,0.08);
}
[data-theme="light"]{
    --bg-page:#f0f4f8;--bg-card:#ffffff;--bg-card2:#f8fafc;
    --bg-header:rgba(255,255,255,0.95);--accent:#00a372;--accent2:#007f59;
    --accent-glow:rgba(0,163,114,0.15);--red:#e02040;--gold:#c9930a;
    --text:#111827;--text-sub:#4b5563;--muted:#6b7280;
    --border:rgba(0,0,0,0.09);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Cairo',sans-serif;background:var(--bg-page);color:var(--text);min-height:100vh;overflow-x:hidden;transition:background .3s,color .3s;}

/* HEADER */
header{position:sticky;top:0;z-index:200;padding:0 20px;background:var(--bg-header);backdrop-filter:blur(18px);border-bottom:1px solid var(--border);}
.header-inner{max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:14px 0;}
.logo-block{display:flex;align-items:center;gap:11px;text-decoration:none;}
.logo-icon{width:42px;height:42px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 0 16px var(--accent-glow);}
.logo-text h1{font-size:19px;font-weight:900;color:var(--text);line-height:1;}
.logo-text small{font-size:11px;color:var(--accent);font-weight:600;letter-spacing:.8px;}
.header-right{display:flex;align-items:center;gap:10px;}
.theme-toggle{width:36px;height:36px;border-radius:50%;border:1px solid var(--border);background:var(--bg-card2);color:var(--text);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;}
.theme-toggle:hover{border-color:var(--accent);}
.live-pill{display:flex;align-items:center;gap:6px;background:rgba(255,59,92,.10);border:1px solid rgba(255,59,92,.28);color:var(--red);padding:5px 12px;border-radius:30px;font-size:12px;font-weight:700;}
.live-dot{width:7px;height:7px;background:var(--red);border-radius:50%;animation:blink 1.4s infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.3;}}

/* TABS — الفكرة: صفين على الكمبيوتر إذا لزم، وسحب على الموبايل */
.tabs-wrapper{
    background:var(--bg-card);
    border-bottom:1px solid var(--border);
    position:sticky;top:71px;z-index:100;
}
.tabs-inner{
    max-width:1100px;margin:0 auto;padding:0 20px;
    display:flex;flex-wrap:wrap;gap:0;
}
.tab-btn{
    background:none;border:none;border-bottom:3px solid transparent;
    color:var(--muted);font-family:'Cairo',sans-serif;
    font-size:13px;font-weight:700;
    padding:13px 18px 10px;
    cursor:pointer;display:flex;align-items:center;gap:6px;
    white-space:nowrap;transition:color .2s,border-color .2s;
    flex-shrink:0;
}
.tab-btn:hover{color:var(--text);}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent);}

/* PAGE TITLE */
.page-title{max-width:1100px;margin:22px auto 0;padding:0 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.page-title h2{font-size:18px;font-weight:700;}
.season-badge{background:var(--bg-card);border:1px solid var(--border);border-radius:20px;padding:4px 14px;font-size:13px;color:var(--text-sub);font-weight:600;}
.divider{height:1px;background:linear-gradient(90deg,transparent,var(--border),transparent);max-width:1100px;margin:12px auto 0;}

/* MAIN */
main{max-width:1100px;margin:22px auto 50px;padding:0 24px;min-height:300px;}

/* LOADING */
.loading-box{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:80px 20px;color:var(--muted);}
.spinner{width:40px;height:40px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.no-data{text-align:center;padding:80px 20px;}
.no-data .icon{font-size:52px;display:block;margin-bottom:14px;opacity:.3;}
.no-data p{font-size:16px;color:var(--muted);font-weight:600;}

/* GROUPS GRID — 2 columns desktop, 1 mobile */
.groups-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;}
@media(max-width:720px){.groups-grid{grid-template-columns:1fr;}}

.group-card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
.group-header{
    padding:10px 16px;font-size:14px;font-weight:900;
    background:linear-gradient(90deg,rgba(0,229,160,.12),transparent);
    border-bottom:1px solid var(--border);color:var(--gold);
    display:flex;align-items:center;gap:8px;
}

/* TABLE */
.standings-table{width:100%;border-collapse:collapse;}
.standings-table thead{background:var(--bg-card2);}
.standings-table th{
    padding:10px 10px;text-align:center;
    font-size:11px;font-weight:700;color:var(--text-sub);
    letter-spacing:.5px;border-bottom:1px solid var(--border);
}
.standings-table th.col-team{text-align:right;}
.standings-table td{padding:10px 10px;border-bottom:1px solid var(--border);font-size:14px;text-align:center;vertical-align:middle;}
.standings-table td.col-team{text-align:right;}
.standings-table tbody tr:last-child td{border-bottom:none;}
.standings-table tbody tr:hover{background:rgba(0,229,160,.03);}

.team-cell{display:flex;align-items:center;gap:9px;}
.team-cell img{width:24px;height:24px;object-fit:contain;flex-shrink:0;}
.team-logo-ph{width:24px;height:24px;background:var(--border);border-radius:50%;flex-shrink:0;}
.team-name{font-weight:600;font-size:14px;}

.rank-num{
    width:30px;height:30px;border-radius:8px;
    display:inline-flex;align-items:center;justify-content:center;
    font-size:12px;font-weight:900;background:var(--bg-card2);color:var(--muted);
}
.rank-num.gold{background:rgba(245,200,66,.18);color:var(--gold);}
.rank-num.silver{background:rgba(0,229,160,.14);color:var(--accent);}
.rank-num.bronze{background:rgba(0,229,160,.08);color:var(--accent);}
.rank-num.cl{background:rgba(124,58,237,.15);color:#a78bfa;}
.rank-num.rel{background:rgba(255,59,92,.13);color:var(--red);}

.pts{font-weight:900;color:var(--gold);font-size:15px;}
.gd-pos{color:#10b981;}
.gd-neg{color:var(--red);}

/* FULL TABLE */
.full-table-card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;}

/* FOOTER */
.main-footer{max-width:1100px;margin:30px auto 0;padding:0 24px 24px;}
.footer-inner{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:28px;background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:30px 28px;}
.footer-logo{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
.footer-logo-icon{width:42px;height:42px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;}
.footer-logo h3{font-size:17px;font-weight:900;color:var(--text);line-height:1;}
.footer-logo small{font-size:11px;color:var(--accent);font-weight:600;}
.footer-about{font-size:13px;color:var(--text-sub);line-height:1.6;margin-bottom:10px;}
.footer-contact{font-size:13px;color:var(--muted);}
.footer-col-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:12px;}
.footer-links{list-style:none;}
.footer-links li{margin-bottom:9px;}
.footer-links a{color:var(--text-sub);text-decoration:none;font-size:13px;transition:color .2s;}
.footer-links a:hover{color:var(--accent);}
.footer-bottom{margin-top:14px;padding:14px 0 0;border-top:1px solid var(--border);text-align:center;font-size:13px;color:var(--muted);}
.footer-bottom a{color:var(--accent);font-weight:700;text-decoration:none;}
@media(max-width:700px){.footer-inner{grid-template-columns:1fr 1fr;gap:20px;padding:22px 18px;}}
@media(max-width:440px){.footer-inner{grid-template-columns:1fr;}.footer-logo{justify-content:center;}.footer-col{text-align:center;}}
</style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="header-inner">
        <a href="https://souikadz.com" class="logo-block">
            <div class="logo-icon">🏆</div>
            <div class="logo-text"><h1>صفارة لايف</h1><small>Saffara Live</small></div>
        </a>
        <div class="header-right">
            <button class="theme-toggle" id="themeToggle">🌙</button>
            <div class="live-pill"><span class="live-dot"></span><span>بث مباشر</span></div>
        </div>
    </div>
</header>

<!-- TABS -->
<div class="tabs-wrapper">
    <div class="tabs-inner" id="tabs">
        <?php foreach($leagues_list as $key => $cfg): ?>
        <button class="tab-btn <?php echo $key===$active?'active':''; ?>"
            data-league="<?php echo $key; ?>"
            data-name="<?php echo htmlspecialchars($cfg['name_ar']); ?>"
            data-season="<?php echo htmlspecialchars($cfg['season']); ?>">
            <?php echo $cfg['flag']; ?> <?php echo htmlspecialchars($cfg['name_ar']); ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- PAGE TITLE -->
<div class="page-title">
    <h2 id="page-title-text"><?php echo $activeCfg['flag'].' '.htmlspecialchars($activeCfg['name_ar']); ?></h2>
    <span class="season-badge" id="page-season">⚽ الموسم <?php echo htmlspecialchars($activeCfg['season']); ?></span>
</div>
<div class="divider"></div>

<!-- MAIN CONTENT -->
<main id="main-content">
<?php
function rankClass(int $pos, int $total): string {
    if($pos===1) return 'gold';
    if($pos===2) return 'silver';
    if($pos===3) return 'bronze';
    if($pos===4) return 'cl';
    if($pos>=$total-2 && $total>10) return 'rel';
    return '';
}
function gdStr(int $gd): string { return $gd>0?"+$gd":$gd; }
function gdClass(int $gd): string { return $gd>0?'gd-pos':($gd<0?'gd-neg':''); }
function logoHtml(string $logo, string $name): string {
    if($logo) return '<img src="'.htmlspecialchars($logo).'" alt="'.htmlspecialchars($name).'" onerror="this.style.display=\'none\'">';
    return '<div class="team-logo-ph"></div>';
}

if(!$data['success']):
?>
    <div class="no-data"><span class="icon">⚠️</span><p>تعذر تحميل البيانات — حاول مرة أخرى</p></div>
<?php elseif($data['type']==='groups'):
    // كأس العالم / مجموعات
?>
    <div class="groups-grid">
    <?php foreach($data['groups'] as $groupName => $teams):
        $total = count($teams);
    ?>
        <div class="group-card">
            <div class="group-header">🏅 <?php echo htmlspecialchars($groupName); ?></div>
            <table class="standings-table">
                <thead><tr>
                    <th style="width:36px">#</th>
                    <th class="col-team">الفريق</th>
                    <th>ل</th><th>ف</th><th>ت</th><th>خ</th><th>فا</th><th>ن</th>
                </tr></thead>
                <tbody>
                <?php $r=1; foreach($teams as $t):
                    $gd=intval($t['goal_difference']);
                ?>
                <tr>
                    <td><span class="rank-num <?php echo rankClass($r,$total); ?>"><?php echo $r++; ?></span></td>
                    <td class="col-team"><div class="team-cell"><?php echo logoHtml($t['team_logo']??'',$t['team_name']); ?><span class="team-name"><?php echo htmlspecialchars($t['team_name']); ?></span></div></td>
                    <td><?php echo $t['played']; ?></td>
                    <td><?php echo $t['won']; ?></td>
                    <td><?php echo $t['drawn']; ?></td>
                    <td><?php echo $t['lost']; ?></td>
                    <td class="<?php echo gdClass($gd); ?>"><?php echo gdStr($gd); ?></td>
                    <td class="pts"><?php echo $t['points']; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    </div>
<?php else:
    // جدول دوري عادي
    $standings = $data['standings'];
    $total = count($standings);
?>
    <div class="full-table-card">
        <table class="standings-table">
            <thead><tr>
                <th style="width:36px">#</th>
                <th class="col-team">الفريق</th>
                <th>ل</th><th>ف</th><th>ت</th><th>خ</th><th>له</th><th>عليه</th><th>فا</th><th>ن</th>
            </tr></thead>
            <tbody>
            <?php foreach($standings as $i => $t):
                $pos = isset($t['rank']) ? intval($t['rank']) : $i+1;
                $gd  = intval($t['goal_difference']);
            ?>
            <tr>
                <td><span class="rank-num <?php echo rankClass($pos,$total); ?>"><?php echo $pos; ?></span></td>
                <td class="col-team"><div class="team-cell"><?php echo logoHtml($t['team_logo']??'',$t['team_name']); ?><span class="team-name"><?php echo htmlspecialchars($t['team_name']); ?></span></div></td>
                <td><?php echo $t['played']; ?></td>
                <td><?php echo $t['won']; ?></td>
                <td><?php echo $t['drawn']; ?></td>
                <td><?php echo $t['lost']; ?></td>
                <td><?php echo $t['goals_for']; ?></td>
                <td><?php echo $t['goals_against']; ?></td>
                <td class="<?php echo gdClass($gd); ?>"><?php echo gdStr($gd); ?></td>
                <td class="pts"><?php echo $t['points']; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</main>

<!-- FOOTER -->
<footer class="main-footer">
    <div class="footer-inner">
        <div class="footer-col">
            <div class="footer-logo">
                <span class="footer-logo-icon">🏆</span>
                <div><h3>صفارة لايف</h3><small>Saffara Live</small></div>
            </div>
            <p class="footer-about">منصتك الأولى لمشاهدة المباريات بث مباشر بجودة عالية وبأفضل خدمة.</p>
            <div class="footer-contact">📧 info@saffara.com</div>
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
                <li><a href="/">مباريات اليوم</a></li>
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

<script>
// Theme
(function(){
    var h=document.documentElement,b=document.getElementById('themeToggle');
    var s=localStorage.getItem('saffara-theme')||'dark';
    h.setAttribute('data-theme',s);b.textContent=s==='dark'?'🌙':'☀️';
    b.addEventListener('click',function(){
        var n=h.getAttribute('data-theme')==='dark'?'light':'dark';
        h.setAttribute('data-theme',n);b.textContent=n==='dark'?'🌙':'☀️';
        localStorage.setItem('saffara-theme',n);
    });
})();

// Tabs
const cache={};

function rankClass(pos,total){
    if(pos===1)return'gold';if(pos===2)return'silver';if(pos===3)return'bronze';
    if(pos===4)return'cl';if(pos>=total-2&&total>10)return'rel';return'';
}
function gdFmt(gd){return gd>0?'+'+gd:gd;}
function gdCls(gd){return gd>0?'gd-pos':gd<0?'gd-neg':'';}
function logoHtml(logo,name){
    return logo?`<img src="${logo}" alt="${name}" onerror="this.style.display='none'">`:'<div class="team-logo-ph"></div>';
}

function renderGroups(groups){
    return '<div class="groups-grid">'+Object.entries(groups).map(([name,teams])=>{
        const total=teams.length;
        const rows=teams.map((t,i)=>{
            const pos=i+1,gd=parseInt(t.goal_difference);
            return `<tr>
                <td><span class="rank-num ${rankClass(pos,total)}">${pos}</span></td>
                <td class="col-team"><div class="team-cell">${logoHtml(t.team_logo,t.team_name)}<span class="team-name">${t.team_name}</span></div></td>
                <td>${t.played}</td><td>${t.won}</td><td>${t.drawn}</td><td>${t.lost}</td>
                <td class="${gdCls(gd)}">${gdFmt(gd)}</td>
                <td class="pts">${t.points}</td>
            </tr>`;
        }).join('');
        return `<div class="group-card">
            <div class="group-header">🏅 المجموعة ${name}</div>
            <table class="standings-table">
                <thead><tr><th>#</th><th class="col-team">الفريق</th><th>ل</th><th>ف</th><th>ت</th><th>خ</th><th>فا</th><th>ن</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
    }).join('')+'</div>';
}

function renderTable(standings){
    const total=standings.length;
    const rows=standings.map((t,i)=>{
        const pos=t.rank?parseInt(t.rank):i+1,gd=parseInt(t.goal_difference);
        return `<tr>
            <td><span class="rank-num ${rankClass(pos,total)}">${pos}</span></td>
            <td class="col-team"><div class="team-cell">${logoHtml(t.team_logo,t.team_name)}<span class="team-name">${t.team_name}</span></div></td>
            <td>${t.played}</td><td>${t.won}</td><td>${t.drawn}</td><td>${t.lost}</td>
            <td>${t.goals_for}</td><td>${t.goals_against}</td>
            <td class="${gdCls(gd)}">${gdFmt(gd)}</td>
            <td class="pts">${t.points}</td>
        </tr>`;
    }).join('');
    return `<div class="full-table-card"><table class="standings-table">
        <thead><tr><th>#</th><th class="col-team">الفريق</th><th>ل</th><th>ف</th><th>ت</th><th>خ</th><th>له</th><th>عليه</th><th>فا</th><th>ن</th></tr></thead>
        <tbody>${rows}</tbody>
    </table></div>`;
}

async function loadLeague(league,name,season){
    const main=document.getElementById('main-content');
    document.getElementById('page-title-text').textContent=name;
    document.getElementById('page-season').textContent='⚽ الموسم '+season;
    if(cache[league]){renderData(cache[league]);return;}
    main.innerHTML='<div class="loading-box"><div class="spinner"></div><p>جاري تحميل '+name+'...</p></div>';
    try{
        const res=await fetch('https://djelalda.online/backend/get_leagues.php?league='+league);
        const d=await res.json();
        if(!d.success)throw new Error();
        cache[league]=d;renderData(d);
    }catch(e){
        main.innerHTML='<div class="no-data"><span class="icon">⚠️</span><p>تعذر تحميل البيانات</p></div>';
    }
}

function renderData(d){
    const main=document.getElementById('main-content');
    main.innerHTML=d.type==='groups'?renderGroups(d.groups):renderTable(d.standings);
}

document.getElementById('tabs').addEventListener('click',function(e){
    const btn=e.target.closest('.tab-btn');
    if(!btn)return;
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    loadLeague(btn.dataset.league,btn.dataset.name,btn.dataset.season);
    history.pushState(null,'','?league='+btn.dataset.league);
});
</script>
</body>
</html>