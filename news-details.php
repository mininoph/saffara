<?php
require_once __DIR__ . '/config.php';
$page_title = htmlspecialchars($news['title']);
include __DIR__ . '/header.php';
// جلب معرف الخبر من الرابط
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: news.php');
    exit;
}

// جلب تفاصيل الخبر من قاعدة البيانات
$stmt = $db->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$id]);
$news = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$news) {
    header('Location: news.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($news['title']); ?> - صفارة لايف</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root,[data-theme="dark"]{
            --bg-page:#070c18;--bg-card:#0c1120;--bg-card2:#111827;
            --accent:#00e5a0;--accent2:#00b87a;
            --text:#e8eaf0;--text-sub:#9ca3af;--muted:#6b7280;
            --border:rgba(255,255,255,0.08);
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Cairo',sans-serif;background:var(--bg-page);color:var(--text);min-height:100vh;padding:20px;}
        .container{max-width:800px;margin:0 auto;background:var(--bg-card);padding:30px;border-radius:16px;border:1px solid var(--border);}
        .back{display:inline-block;margin-bottom:20px;color:var(--accent);text-decoration:none;font-weight:600;}
        .back:hover{text-decoration:underline;}
        h1{font-size:26px;margin:10px 0;line-height:1.4;}
        .meta{color:var(--text-sub);font-size:14px;margin:10px 0;}
        img{max-width:100%;border-radius:12px;margin:15px 0;}
        .content{line-height:1.8;font-size:16px;color:var(--text);}
        .content p{margin-bottom:12px;}
        .source-link{display:inline-block;margin-top:20px;padding:12px 30px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#050810;border-radius:12px;text-decoration:none;font-weight:800;font-size:15px;transition:all .2s;border:none;cursor:pointer;}
        .source-link:hover{transform:translateY(-2px);box-shadow:0 6px 22px rgba(0,229,160,0.3);}
        .no-news{text-align:center;padding:40px;color:var(--muted);}
        @media(max-width:600px){.container{padding:16px;} h1{font-size:20px;}}
    </style>
</head>
<body>
<div class="container">
    <a href="/news.php" class="back">← العودة للأخبار</a>
    
    <h1><?php echo htmlspecialchars($news['title']); ?></h1>
    
    <div class="meta">📅 <?php echo date('d/m/Y', strtotime($news['pub_date'])); ?></div>
    
    <?php if (!empty($news['image_url'])): ?>
        <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>">
    <?php endif; ?>
    
    <div class="content">
        <?php echo nl2br(htmlspecialchars($news['description'])); ?>
    </div>
    
    <!-- ══ زر المصدر ══ -->
    <div style="text-align:center;margin-top:25px;padding-top:20px;border-top:1px solid var(--border);">
        <a href="<?php echo htmlspecialchars($news['link']); ?>" 
           target="_blank" 
           rel="noopener" 
           class="source-link">
            📰 اقرأ الخبر كاملاً في المصدر
        </a>
    </div>
</div>
</body>
</html>