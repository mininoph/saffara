<?php
// ============================================================
// header.php - الهيدر المشترك لجميع صفحات صفارة لايف
// ============================================================
// المتطلبات قبل الـ include:
//   $matches  : مصفوفة المباريات (لكل عنصر مفاتيح: status, team1_name, team2_name,
//               competition, id, ...) — إن لم تكن متوفرة في الصفحة اتركها []
//
// يقوم هذا الملف تلقائياً بحساب:
//   $live_matches : المباريات الجارية الآن (لشارة LIVE وشريط العاجل)
//   $grouped      : تجميع المباريات حسب البطولة (لاقتراحات البحث)
// ------------------------------------------------------------

if (!isset($matches) || !is_array($matches)) {
    $matches = [];
}

$live_matches   = array_filter($matches, fn($m) => ($m['status'] ?? null) === 'live');
$upcoming_first = array_slice(array_filter($matches, fn($m) => ($m['status'] ?? null) === 'upcoming'), 0, 4);
$ticker_items   = array_merge(array_values($live_matches), array_values($upcoming_first));

$grouped = [];
foreach ($matches as $match) {
    $comp = $match['competition'] ?? '';
    if ($comp === '') continue;
    if (!isset($grouped[$comp])) $grouped[$comp] = [];
    $grouped[$comp][] = $match;
}
?>
<style>
/* ══ متغيرات الألوان (مشتركة لكل الموقع) ══ */
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
@keyframes blink{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(.7)}}

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

@media(max-width:900px){nav{display:none;}}
@media(max-width:600px){
    .top-ticker{display:none;}
    .logo-ar{font-size:15px;}
}
</style>

<!-- ══ شريط الأخبار العاجلة ══ -->
<div class="top-ticker">
    <div class="ticker-label">⚡ عاجل</div>
    <div class="ticker-scroll">
        <div class="ticker-track" id="tickerTrack">
            <?php
            if (empty($ticker_items)) {
                echo '<div class="ticker-item">⚽ مرحباً بك في صفارة لايف — منصة البث الرياضي الأولى</div>';
                echo '<div class="ticker-item">📺 تابع جميع المباريات بجودة HD وتعليق عربي</div>';
                echo '<div class="ticker-item">🏆 نغطي أهم البطولات العالمية والمحلية</div>';
            } else {
                for ($r = 0; $r < 2; $r++) {
                    foreach ($ticker_items as $tm) {
                        $dot   = $tm['status'] === 'live' ? '<span class="tdot"></span>' : '⏰';
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
            <a href="/" class="nav-link<?php echo ($current_page ?? '') === 'home' ? ' active' : ''; ?>">الرئيسية</a>
            <a href="/" class="nav-link<?php echo ($current_page ?? '') === 'matches' ? ' active' : ''; ?>">
                المباريات
                <?php if (!empty($live_matches)): ?>
                    <span class="live-badge">LIVE</span>
                <?php endif; ?>
            </a>
            <a href="/leagues/" class="nav-link<?php echo ($current_page ?? '') === 'leagues' ? ' active' : ''; ?>">الترتيب</a>
            <a href="/news.php" class="nav-link<?php echo ($current_page ?? '') === 'news' ? ' active' : ''; ?>">الأخبار</a>
            <a href="/videos/" class="nav-link<?php echo ($current_page ?? '') === 'videos' ? ' active' : ''; ?>">الفيديوهات</a>
            <a href="/transfers/" class="nav-link<?php echo ($current_page ?? '') === 'transfers' ? ' active' : ''; ?>">الانتقالات</a>
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

<!-- ══ بيانات JS للبحث ══ -->
<script>
var MATCHES_DATA = <?php echo json_encode(array_values($matches)); ?>;
</script>

<script>
(function(){
    /* ── ثيم (يُهيَّأ هنا لأن الهيدر يظهر أولاً في كل صفحة) ── */
    var html  = document.documentElement;
    var saved = localStorage.getItem('saffara-theme') || 'dark';
    html.setAttribute('data-theme', saved);

    document.addEventListener('DOMContentLoaded', function(){
        var thBtn = document.getElementById('themeToggle');
        thBtn.textContent = saved === 'dark' ? '🌙' : '☀️';
        thBtn.addEventListener('click', function(){
            var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            thBtn.textContent = next === 'dark' ? '🌙' : '☀️';
            localStorage.setItem('saffara-theme', next);
        });

        /* ── بحث ── */
        var searchInp  = document.getElementById('searchInp');
        var searchList = document.getElementById('searchList');

        searchInp.addEventListener('input', function(){
            var q = this.value.trim().toLowerCase();
            searchList.innerHTML = '';
            if (q.length < 2) return;
            var found = MATCHES_DATA.filter(function(m){
                return m.team1_name.toLowerCase().includes(q) ||
                       m.team2_name.toLowerCase().includes(q) ||
                       m.competition.toLowerCase().includes(q);
            }).slice(0, 6);
            found.forEach(function(m){
                var d = document.createElement('div');
                d.className = 'sr-item';
                d.innerHTML = m.team1_name + ' VS ' + m.team2_name + '<span>' + m.competition + '</span>';
                d.addEventListener('click', function(){
                    var card = document.querySelector('.match-card[data-match-id="' + m.id + '"]');
                    if (card) {
                        card.scrollIntoView({behavior:'smooth', block:'center'});
                        card.style.outline = '2px solid var(--accent)';
                        setTimeout(function(){ card.style.outline = ''; }, 2500);
                    }
                    document.getElementById('searchDrop').classList.remove('open');
                    searchInp.value = '';
                });
                searchList.appendChild(d);
            });
            if (found.length === 0) searchList.innerHTML = '<div class="sr-item" style="color:var(--muted)">لا توجد نتائج</div>';
        });

        document.addEventListener('click', function(e){
            if (!document.querySelector('.search-wrap').contains(e.target))
                document.getElementById('searchDrop').classList.remove('open');
        });
    });
})();

function toggleSearch(){
    document.getElementById('searchDrop').classList.toggle('open');
    if (document.getElementById('searchDrop').classList.contains('open'))
        setTimeout(function(){ document.getElementById('searchInp').focus(); }, 50);
}

function searchFor(q){
    document.getElementById('searchInp').value = q;
    document.getElementById('searchInp').dispatchEvent(new Event('input'));
}
</script>