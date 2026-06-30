<?php
require_once __DIR__ . '/config.php';
$page_title = 'آخر الأخبار';
include __DIR__ . '/header.php';
// دالة تنظيف النصوص
function cleanText($text) {
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $text);
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

// جلب الأخبار
$stmt = $db->query("SELECT * FROM news ORDER BY pub_date DESC LIMIT 30");
$all_news = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أخبار كرة القدم - صفارة لايف</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
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
            min-height:100vh;transition:background .3s,color .3s;
        }

        /* ══ هيدر ══ */
        header{
            position:sticky;top:0;z-index:500;
            background:var(--bg-header);
            backdrop-filter:blur(22px) saturate(1.5);
            -webkit-backdrop-filter:blur(22px) saturate(1.5);
            border-bottom:1px solid var(--border);
        }
        .header-main{
            max-width:1100px;margin:0 auto;padding:0 20px;
            display:flex;align-items:center;gap:14px;height:58px;
        }
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

        nav{display:flex;align-items:center;gap:2px;flex:1;justify-content:center;}
        .nav-link{
            position:relative;padding:7px 12px;
            font-size:13px;font-weight:700;color:var(--text-sub);
            text-decoration:none;border-radius:8px;
            transition:color .2s,background .2s;white-space:nowrap;
            display:flex;align-items:center;gap:5px;
        }
        .nav-link:hover{color:var(--text);background:rgba(255,255,255,.05);}
        .nav-link.active{color:var(--accent);}
        .nav-link.active::after{
            content:'';position:absolute;bottom:3px;left:50%;transform:translateX(-50%);
            width:16px;height:2px;background:var(--accent);border-radius:2px;
        }

        .search-wrap{position:relative;flex-shrink:0;}
        .search-btn{
            width:36px;height:36px;border-radius:10px;
            background:var(--bg-card2);border:1px solid var(--border);
            color:var(--text-sub);cursor:pointer;font-size:15px;
            display:flex;align-items:center;justify-content:center;
        }
        .search-btn:hover{border-color:var(--accent);color:var(--accent);}

        .header-tools{display:flex;align-items:center;gap:7px;flex-shrink:0;}
        .tool-btn{
            width:36px;height:36px;border-radius:10px;
            background:var(--bg-card2);border:1px solid var(--border);
            color:var(--text-sub);cursor:pointer;font-size:15px;
            display:flex;align-items:center;justify-content:center;
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

        /* ══ محتوى الأخبار ══ */
        .news-hero {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-card2));
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px 30px;
            margin: 20px 0 30px;
            text-align: center;
        }
        .news-hero h1 {
            font-size: 32px;
            font-weight: 900;
            color: var(--text);
        }
        .news-hero p {
            color: var(--text-sub);
            font-size: 16px;
            margin-top: 8px;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .news-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .news-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow);
        }

        .news-card .image-wrap {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: var(--bg-deep);
            position: relative;
        }
        .news-card .image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .news-card:hover .image-wrap img {
            transform: scale(1.03);
        }
        .news-card .image-wrap .no-img {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            font-size: 48px;
            color: var(--muted);
        }

        .news-card .content {
            padding: 18px 20px 20px;
        }
        .news-card .content .title {
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.4;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .news-card .content .title a {
            color: inherit;
            text-decoration: none;
        }
        .news-card .content .title a:hover {
            color: var(--accent);
        }

        .news-card .content .excerpt {
            color: var(--text-sub);
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 14px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-card .content .meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 14px;
            border-top: 1px solid var(--border);
            font-size: 13px;
        }
        .news-card .content .meta .date {
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .news-card .content .meta .read-more {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: gap 0.2s;
        }
        .news-card .content .meta .read-more:hover {
            gap: 8px;
        }

        .no-news {
            text-align: center;
            padding: 80px 20px;
            color: var(--muted);
            font-size: 18px;
        }
        .no-news .icon {
            font-size: 56px;
            display: block;
            margin-bottom: 12px;
            opacity: 0.4;
        }

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

        @media(max-width:900px){nav{display:none;}}
        @media(max-width:600px){
            .logo-ar{font-size:15px;}
            .footer-inner{grid-template-columns:1fr 1fr;gap:20px;padding:20px 16px;}
            .news-grid{grid-template-columns:1fr;}
            .news-hero{padding:24px 16px;}
            .news-hero h1{font-size:24px;}
        }
        @media(max-width:420px){.footer-inner{grid-template-columns:1fr;}}
    </style>
</head>
<body>

<!-- ══ هيدر ══ -->
<header>
    <div class="header-main">
        <a href="https://souikadz.com" class="logo">
            <div class="logo-mark">🏆</div>
            <div>
                <span class="logo-ar">صفارة لايف</span>
                <span class="logo-en">Saffara Live</span>
            </div>
        </a>
        <nav>
            <a href="/" class="nav-link">الرئيسية</a>
            <a href="/" class="nav-link">المباريات</a>
            <a href="/leagues/" class="nav-link">الترتيب</a>
            <a href="/news.php" class="nav-link active">الأخبار</a>
            <a href="/videos/" class="nav-link">الفيديوهات</a>
            <a href="/transfers/" class="nav-link">الانتقالات</a>
        </nav>
        <div class="search-wrap">
            <button class="search-btn">🔍</button>
        </div>
        <div class="header-tools">
            <button class="tool-btn" id="themeToggle" aria-label="الوضع الليلي">🌙</button>
            <button class="tool-btn" title="الإشعارات">🔔</button>
            <a href="/login/" class="btn-login">👤 دخول</a>
        </div>
    </div>
</header>

<!-- ══ محتوى الأخبار ══ -->
<main style="max-width:1100px;margin:0 auto;padding:0 20px 60px;">

    <div class="news-hero">
        <h1>📰 آخر أخبار كرة القدم</h1>
        <p>آخر المستجدات والأخبار العاجلة من عالم كرة القدم العربية والعالمية</p>
    </div>

    <?php if (empty($all_news)): ?>
        <div class="no-news">
            <span class="icon">📭</span>
            <p>لا توجد أخبار حالياً</p>
        </div>
    <?php else: ?>
        <div class="news-grid">
            <?php foreach ($all_news as $news): ?>
                <div class="news-card">
                    <div class="image-wrap">
                        <?php if (!empty($news['image_url']) && filter_var($news['image_url'], FILTER_VALIDATE_URL)): ?>
                            <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-img">⚽</div>
                        <?php endif; ?>
                    </div>
                    <div class="content">
                        <div class="title">
                            <a href="<?php echo htmlspecialchars(trim($news['link'])); ?>" target="_blank" rel="noopener">
                                <?php echo htmlspecialchars(cleanText($news['title'])); ?>
                            </a>
                        </div>
                        <div class="excerpt">
                            <?php 
                            $clean = cleanText($news['description']);
                            echo htmlspecialchars(mb_substr($clean, 0, 120)) . '...'; 
                            ?>
                        </div>
                        <div class="meta">
                            <span class="date">📅 <?php echo date('d/m/Y', strtotime($news['pub_date'])); ?></span>
                           <a href="/news-details.php?id=<?php echo $news['id']; ?>" class="read-more">
                                اقرأ المزيد ←
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function(){
    // ── الثيم ──
    var html = document.documentElement;
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
});
</script>
</body>
</html>
