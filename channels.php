<?php
// admin/channels.php - نسخة مع Grid وصور ورابط بث + رفع ملف m3u
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$message = '';
$error = '';

// حذف قناة
if (isset($_GET['delete_stream'])) {
    $pdo->prepare("DELETE FROM channel_streams WHERE id = ?")->execute([$_GET['delete_stream']]);
    header('Location: channels.php');
    exit;
}

// حذف مصدر
if (isset($_GET['delete_source'])) {
    $id = $_GET['delete_source'];
    $pdo->prepare("DELETE FROM channel_streams WHERE channel_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM channels WHERE id = ?")->execute([$id]);
    header('Location: channels.php');
    exit;
}

// مزامنة Xtream
if (isset($_GET['sync'])) {
    $source_id = $_GET['sync'];
    $stmt = $pdo->prepare("SELECT * FROM channels WHERE id = ?");
    $stmt->execute([$source_id]);
    $source = $stmt->fetch();
    
    if ($source && $source['source_type'] == 'xtream') {
        $host = rtrim($source['xtream_host'], '/');
        $port = $source['xtream_port'];
        $user = $source['xtream_username'];
        $pass = $source['xtream_password'];
        
        $baseUrl = "$host:$port";
        
        // 1. جلب الفئات أولاً
        $catUrl = "$baseUrl/player_api.php?username=$user&password=$pass&action=get_live_categories";
        $catData = @file_get_contents($catUrl);
        $categoryMap = [];
        
        if ($catData) {
            $cats = json_decode($catData, true);
            if (is_array($cats)) {
                foreach ($cats as $cat) {
                    $categoryMap[$cat['category_id']] = $cat['category_name'];
                }
            }
        }
        
        // 2. جلب القنوات
        $apiUrl = "$baseUrl/player_api.php?username=$user&password=$pass&action=get_live_streams";
        $data = @file_get_contents($apiUrl);
        
        if ($data) {
            $streams = json_decode($data, true);
            if (is_array($streams)) {
                $pdo->prepare("DELETE FROM channel_streams WHERE channel_id = ?")->execute([$source_id]);
                $added = 0;
                $insert = $pdo->prepare("INSERT INTO channel_streams (channel_id, stream_id, name, logo, group_title, stream_url) VALUES (?, ?, ?, ?, ?, ?)");
                
                foreach ($streams as $stream) {
                    $categoryId = $stream['category_id'] ?? 0;
                    $categoryName = $categoryMap[$categoryId] ?? '';
                    $streamUrl = "$baseUrl/live/$user/$pass/{$stream['stream_id']}.ts";
                    
                    $insert->execute([
                        $source_id,
                        $stream['stream_id'],
                        $stream['name'],
                        $stream['stream_icon'] ?? '',
                        $categoryName,
                        $streamUrl
                    ]);
                    $added++;
                }
                $pdo->prepare("UPDATE channels SET last_sync = NOW() WHERE id = ?")->execute([$source_id]);
                $message = "✅ تم استيراد $added قناة مع الفئات";
            } else {
                $error = "❌ بيانات غير صالحة";
            }
        } else {
            $error = "❌ تعذر الاتصال";
        }
    }
    
// مزامنة m3u (يدعم group-title والملفات المرفوعة)
if ($source && $source['source_type'] == 'm3u' && $source['m3u_url']) {
    // تحديد المسار الصحيح للملف
    $file_path = $source['m3u_url'];
    
    // إذا كان الملف مرفوعاً محلياً (يبدأ بـ uploads/)
    if (strpos($file_path, 'uploads/') === 0) {
        $full_path = __DIR__ . '/../' . $file_path;
        if (file_exists($full_path)) {
            $content = file_get_contents($full_path);
        } else {
            $error = "❌ الملف غير موجود: " . $full_path;
            $content = false;
        }
    } else {
        // رابط خارجي
        $content = @file_get_contents($file_path);
    }
    
    if ($content) {
        $pdo->prepare("DELETE FROM channel_streams WHERE channel_id = ?")->execute([$source_id]);
        $lines = explode("\n", $content);
        $added = 0;
        $insert = $pdo->prepare("INSERT INTO channel_streams (channel_id, name, logo, group_title, stream_url) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#EXTINF:') === 0) {
                $group_title = '';
                if (preg_match('/group-title="([^"]+)"/', $line, $matches)) {
                    $group_title = $matches[1];
                }
                $logo = '';
                if (preg_match('/tvg-logo="([^"]+)"/', $line, $matches)) {
                    $logo = $matches[1];
                }
                $name_parts = explode(',', $line);
                $name = trim(end($name_parts));
                $temp = ['name' => $name, 'logo' => $logo, 'group' => $group_title];
            } elseif ($line && strpos($line, '#') !== 0 && isset($temp)) {
                $insert->execute([$source_id, $temp['name'], $temp['logo'], $temp['group'], $line]);
                $added++;
                unset($temp);
            }
        }
        $pdo->prepare("UPDATE channels SET last_sync = NOW() WHERE id = ?")->execute([$source_id]);
        $message = "✅ تم استيراد $added قناة من m3u";
    } else {
        $error = "❌ تعذر تحميل ملف m3u";
    }
}
}
// إضافة مصدر Xtream
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_xtream'])) {
    $name = $_POST['name'];
    $host = $_POST['xtream_host'];
    $port = $_POST['xtream_port'];
    $user = $_POST['xtream_username'];
    $pass = $_POST['xtream_password'];
    
    $stmt = $pdo->prepare("INSERT INTO channels (name, source_type, xtream_host, xtream_port, xtream_username, xtream_password, is_active) VALUES (?, 'xtream', ?, ?, ?, ?, 1)");
    $stmt->execute([$name, $host, $port, $user, $pass]);
    $message = "✅ تم إضافة مصدر Xtream";
}

// إضافة مصدر m3u
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_m3u'])) {
    $name = $_POST['name'];
    $m3u_url = $_POST['m3u_url'];
    
    $stmt = $pdo->prepare("INSERT INTO channels (name, source_type, m3u_url, is_active) VALUES (?, 'm3u', ?, 1)");
    $stmt->execute([$name, $m3u_url]);
    $message = "✅ تم إضافة مصدر m3u";
}

// رفع ملف m3u
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_m3u'])) {
    $name = $_POST['name'] ?? 'مصدر m3u';
    
    if (isset($_FILES['m3u_file']) && $_FILES['m3u_file']['error'] == 0) {
        $file_tmp = $_FILES['m3u_file']['tmp_name'];
        $file_content = file_get_contents($file_tmp);
        
        if ($file_content) {
            // حفظ الملف على الخادم
           $upload_dir = __DIR__ . '/../uploads/m3u/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = time() . '_' . basename($_FILES['m3u_file']['name']);
            $file_path = $upload_dir . $file_name;
            file_put_contents($file_path, $file_content);
            
            // حفظ المسار في قاعدة البيانات
          $m3u_url = 'uploads/m3u/' . $file_name;
            $stmt = $pdo->prepare("INSERT INTO channels (name, source_type, m3u_url, is_active) VALUES (?, 'm3u', ?, 1)");
            $stmt->execute([$name, $m3u_url]);
            $message = "✅ تم رفع ملف m3u واستيراده بنجاح";
        } else {
            $error = "❌ فشل في قراءة الملف";
        }
    } else {
        $error = "❌ يرجى اختيار ملف m3u صالح";
    }
}

// جلب البيانات
$sources = $pdo->query("SELECT * FROM channels ORDER BY id DESC")->fetchAll();
$totalStreams = $pdo->query("SELECT COUNT(*) FROM channel_streams")->fetchColumn();
$totalSources = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();

// الفئات
$all_categories = $pdo->query("SELECT group_title, COUNT(*) as count FROM channel_streams WHERE group_title IS NOT NULL AND group_title != '' GROUP BY group_title")->fetchAll();

// تقسيم الفئات إلى رياضية وغير رياضية
$sports_categories = [];
$other_categories = [];

foreach ($all_categories as $cat) {
    $title = $cat['group_title'];
    // الكلمات المفتاحية للفئات الرياضية
    if (preg_match('/(beIN|BeIN|BEIN|Sport|رياضة|كأس|دوري|HD|Koora|Goal|Match|Premier|Champions|FIFA|UEFA|Bundesliga|LaLiga|SerieA|EPL|WNBA|NBA|MLS|WWE|UFC)/i', $title)) {
        $sports_categories[] = $cat;
    } else {
        $other_categories[] = $cat;
    }
}

// ترتيب الفئات الرياضية حسب الأهمية
usort($sports_categories, function($a, $b) {
    // beIN تتصدر القائمة
    $a_is_bein = preg_match('/(beIN|BeIN|BEIN)/i', $a['group_title']);
    $b_is_bein = preg_match('/(beIN|BeIN|BEIN)/i', $b['group_title']);
    
    if ($a_is_bein && !$b_is_bein) return -1;
    if (!$a_is_bein && $b_is_bein) return 1;
    
    // ثم ترتيب أبجدي لباقي الرياضية
    return strcmp($a['group_title'], $b['group_title']);
});

// ترتيب الغير رياضية أبجدياً
usort($other_categories, function($a, $b) {
    return strcmp($a['group_title'], $b['group_title']);
});

// دمج القائمتين (الرياضية أولاً)
$categories = array_merge($sports_categories, $other_categories);
$selectedCategory = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// القنوات مع البحث
$sql = "SELECT * FROM channel_streams WHERE 1=1";
if ($selectedCategory) {
    $sql .= " AND group_title = '" . addslashes($selectedCategory) . "'";
}
if ($search) {
    $sql .= " AND name LIKE '%" . addslashes($search) . "%'";
}
$sql .= " ORDER BY 
    CASE 
        WHEN name LIKE '%beIN%' OR name LIKE '%BeIN%' OR name LIKE '%BEIN%' THEN 1
        WHEN name REGEXP 'Sport|HD|Match|Goal|Premier|Champions|FIFA|UEFA|Bundesliga|LaLiga|SerieA|EPL|NBA|MLS|WWE' THEN 2
        ELSE 3
    END, name ASC LIMIT 200";
$channels = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شبكة القنوات - صفارة لايف</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', 'Segoe UI', sans-serif;
            background: #f0f2f5;
            direction: rtl;
            padding: 20px;
        }
        .navbar {
            background: #1a1a2e;
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-radius: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .logo { font-size: 1.3rem; }
        .logo span { color: #e94560; }
        .nav-buttons {
            display: flex;
            gap: 10px;
        }
        .back-btn {
            background: #e94560;
            color: white;
            padding: 6px 15px;
            border-radius: 8px;
            text-decoration: none;
        }
        .home-btn {
            background: #28a745;
            color: white;
            padding: 6px 15px;
            border-radius: 8px;
            text-decoration: none;
        }
        .stats {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            text-align: center;
        }
        .stat-number { font-size: 1.8rem; font-weight: bold; color: #e94560; }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .card h2 {
            margin-bottom: 15px;
            border-right: 3px solid #e94560;
            padding-right: 10px;
        }
        .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .form-row input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        button {
            background: #e94560;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-small {
            padding: 4px 10px;
            font-size: 0.7rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        .btn-green { background: #28a745; color: white; }
        .btn-red { background: #dc3545; color: white; }
        .btn-blue { background: #007bff; color: white; }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .split {
            display: flex;
            gap: 20px;
        }
        .categories-box {
            width: 260px;
            background: white;
            border-radius: 12px;
            padding: 15px;
            height: fit-content;
            max-height: 70vh;
            overflow-y: auto;
        }
        .categories-box h3 {
            margin-bottom: 10px;
        }
        .categories-box a {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            margin-bottom: 5px;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-size: 0.85rem;
        }
        .categories-box a.active {
            background: #e94560;
            color: white;
        }
        .channels-box {
            flex: 1;
            background: white;
            border-radius: 12px;
            padding: 15px;
        }
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-bar input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .channels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 15px;
        }
        .channel-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid #e9ecef;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .channel-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .channel-logo {
            width: 70px;
            height: 70px;
            object-fit: contain;
            margin: 0 auto 10px;
            display: block;
            border-radius: 10px;
            background: #e9ecef;
        }
        .channel-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 8px;
            word-break: break-word;
            height: 40px;
            overflow: hidden;
        }
        .channel-group {
            font-size: 0.65rem;
            color: #888;
            margin-bottom: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .channel-actions {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 25px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }
        .modal-content h3 {
            margin-bottom: 15px;
            color: #1a1a2e;
        }
        .modal-content input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 12px;
            direction: ltr;
        }
        .modal-content button {
            margin: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #1a1a2e;
            color: white;
        }
        @media (max-width: 700px) {
            .split { flex-direction: column; }
            .categories-box { width: 100%; }
            .channels-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
            .channel-logo { width: 50px; height: 50px; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">📡 شبكة <span>صفارة لايف</span></div>
        <div class="nav-buttons">
            <a href="javascript:history.back()" class="back-btn">🔙 رجوع</a>
            <a href="index.php" class="home-btn">🏠 الرئيسية</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card"><div class="stat-number"><?php echo $totalSources; ?></div><div>مصادر</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo number_format($totalStreams); ?></div><div>قنوات</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo count($categories); ?></div><div>فئات</div></div>
        </div>

        <div class="split">
            <div class="categories-box">
                <h3>📁 الفئات</h3>
                <a href="channels.php" class="<?php echo !$selectedCategory ? 'active' : ''; ?>">
                    <span>🎬 جميع القنوات</span>
                    <span><?php echo number_format($totalStreams); ?></span>
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="?category=<?php echo urlencode($cat['group_title']); ?>" class="<?php echo $selectedCategory == $cat['group_title'] ? 'active' : ''; ?>">
                    <span>📺 <?php echo htmlspecialchars(mb_substr($cat['group_title'], 0, 25)); ?></span>
                    <span><?php echo $cat['count']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="channels-box">
                <h3>
                    <?php echo $selectedCategory ? htmlspecialchars($selectedCategory) : 'جميع القنوات'; ?>
                    (<?php echo count($channels); ?>)
                </h3>
                
                <div class="search-bar">
                    <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                        <input type="hidden" name="tab" value="channels">
                        <?php if($selectedCategory): ?>
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($selectedCategory); ?>">
                        <?php endif; ?>
                        <input type="text" name="search" placeholder="🔍 بحث عن قناة..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit">بحث</button>
                        <?php if($search): ?>
                            <a href="channels.php<?php echo $selectedCategory ? '?category='.urlencode($selectedCategory) : ''; ?>" class="btn-small btn-blue">إلغاء</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="channels-grid">
                    <?php foreach ($channels as $ch): ?>
                    <div class="channel-card" onclick="showStreamUrl('<?php echo htmlspecialchars($ch['stream_url']); ?>', '<?php echo htmlspecialchars(addslashes($ch['name'])); ?>')">
                        <?php if (!empty($ch['logo']) && filter_var($ch['logo'], FILTER_VALIDATE_URL)): ?>
                            <img src="<?php echo htmlspecialchars($ch['logo']); ?>" class="channel-logo" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 100 100%27%3E%3Crect width=%27100%27 height=%27100%27 fill=%27%23e9ecef%27/%3E%3Ctext x=%2750%27 y=%2755%27 text-anchor=%27middle%27 font-size=%2740%27 fill=%27%23999%27%3E📺%3C/text%3E%3C/svg%3E'">
                        <?php else: ?>
                            <div class="channel-logo" style="background:#e9ecef; display:flex; align-items:center; justify-content:center; font-size:2rem;">📺</div>
                        <?php endif; ?>
                        <div class="channel-name" title="<?php echo htmlspecialchars($ch['name']); ?>">
                            <?php echo htmlspecialchars(mb_substr($ch['name'], 0, 35)); ?>
                        </div>
                        <div class="channel-group"><?php echo htmlspecialchars(mb_substr($ch['group_title'] ?? '-', 0, 20)); ?></div>
                        <div class="channel-actions" onclick="event.stopPropagation()">
    <button onclick="playStream('<?php echo htmlspecialchars($ch['stream_url']); ?>', '<?php echo htmlspecialchars(addslashes($ch['name'])); ?>')" class="btn-small btn-green" style="cursor: pointer;">▶️ تشغيل</button>
    <a href="?delete_stream=<?php echo $ch['id']; ?><?php echo $selectedCategory ? '&category='.urlencode($selectedCategory) : ''; ?>" class="btn-small btn-red" onclick="return confirm('حذف هذه القناة؟')">🗑️ حذف</a>
</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- إضافة مصدر Xtream -->
        <div class="card">
            <h2>➕ إضافة مصدر Xtream</h2>
            <form method="POST">
                <div class="form-row">
                    <input type="text" name="name" placeholder="اسم المصدر" required>
                    <input type="text" name="xtream_host" placeholder="المضيف (مثال: http://live.lynxiptv.xyz)" required>
                    <input type="text" name="xtream_port" placeholder="المنفذ (مثال: 80)" required>
                    <input type="text" name="xtream_username" placeholder="Username" required>
                    <input type="text" name="xtream_password" placeholder="Password" required>
                </div>
                <button type="submit" name="add_xtream">💾 إضافة Xtream</button>
            </form>
        </div>

        <!-- إضافة مصدر m3u -->
        <div class="card">
            <h2>➕ إضافة مصدر m3u</h2>
            <form method="POST">
                <div class="form-row">
                    <input type="text" name="name" placeholder="اسم المصدر" required>
                    <input type="text" name="m3u_url" placeholder="رابط ملف m3u" required>
                </div>
                <button type="submit" name="add_m3u">💾 إضافة m3u</button>
            </form>
        </div>

        <!-- رفع ملف m3u -->
        <div class="card">
            <h2>📤 رفع ملف m3u</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div>
                        <label>🏷️ اسم المصدر</label>
                        <input type="text" name="name" placeholder="مثال: ملفي m3u" required>
                    </div>
                    <div>
                        <label>📁 اختر ملف m3u</label>
                        <input type="file" name="m3u_file" accept=".m3u,.txt" required>
                    </div>
                </div>
                <button type="submit" name="upload_m3u">📤 رفع الملف</button>
            </form>
        </div>

        <!-- قائمة المصادر -->
        <div class="card">
            <h2>📡 المصادر</h2>
            <table>
                <thead><tr><th>الاسم</th><th>النوع</th><th>الرابط</th><th>آخر مزامنة</th><th>إجراءات</th></tr></thead>
                <tbody>
                <?php foreach ($sources as $src): ?>
                <tr>
                    <td><?php echo htmlspecialchars($src['name']); ?></td>
                    <td><?php echo $src['source_type'] == 'xtream' ? 'Xtream' : 'm3u'; ?></td>
                    <td><small><?php echo $src['source_type'] == 'xtream' ? htmlspecialchars($src['xtream_host']).':'.$src['xtream_port'] : htmlspecialchars($src['m3u_url']); ?></small></td>
                    <td><?php echo $src['last_sync'] ? date('Y-m-d H:i', strtotime($src['last_sync'])) : 'لم تتم'; ?></td>
                    <td>
                        <a href="?sync=<?php echo $src['id']; ?>" class="btn-small btn-green">🔄 مزامنة</a>
                        <a href="?delete_source=<?php echo $src['id']; ?>" class="btn-small btn-red" onclick="return confirm('حذف المصدر وجميع قنواته؟')">🗑️ حذف</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="streamModal" class="modal" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <h3>📺 رابط البث المباشر</h3>
            <p id="modalChannelName" style="color:#666; margin-bottom:10px;"></p>
            <input type="text" id="streamUrlInput" readonly onclick="this.select()">
            <div>
                <button onclick="copyStreamUrl()" style="background:#007bff;">📋 نسخ الرابط</button>
                <button onclick="closeModal()" style="background:#6c757d;">إغلاق</button>
            </div>
        </div>
    </div>

    <script>
        function showStreamUrl(url, name) {
            document.getElementById('streamUrlInput').value = url;
            document.getElementById('modalChannelName').innerHTML = 'القناة: ' + name;
            document.getElementById('streamModal').style.display = 'flex';
        }
        
        function closeModal(event) {
            document.getElementById('streamModal').style.display = 'none';
        }
        
        function copyStreamUrl() {
            var input = document.getElementById('streamUrlInput');
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
            alert('✅ تم نسخ رابط البث');
        }
    </script>
    <!-- مشغل الفيديو -->
<div id="videoPlayerModal" class="modal" onclick="closeVideoModal(event)">
    <div class="modal-content" style="max-width: 800px; width: 90%;" onclick="event.stopPropagation()">
        <h3>🎬 تجربة البث المباشر</h3>
        <div style="position: relative; margin: 15px 0;">
            <video id="livePlayer" controls width="100%" height="auto" style="background: #000; border-radius: 12px;">
                متصفحك لا يدعم تشغيل الفيديو.
            </video>
        </div>
        <div id="playerStatus" style="font-size: 12px; color: #666; margin-bottom: 10px; text-align: center;"></div>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button onclick="closeVideoModal()" style="background: #6c757d;">إغلاق</button>
        </div>
    </div>
</div>

<!-- مكتبات التشغيل -->
<link href="https://vjs.zencdn.net/7.20.3/video-js.css" rel="stylesheet" />
<script src="https://vjs.zencdn.net/7.20.3/video.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

<script>
    let currentPlayer = null;
function playStream(url, channelName) {
    if (currentPlayer) {
        if (currentPlayer.dispose) currentPlayer.dispose();
        else if (currentPlayer.pause) currentPlayer.pause();
        currentPlayer = null;
    }
    
    // تحويل الرابط من .ts إلى .m3u8
    let streamUrl = url;
    if (streamUrl.endsWith('.ts')) {
        streamUrl = streamUrl.replace('.ts', '.m3u8');
        document.getElementById('playerStatus').innerHTML = '🔄 تحويل الرابط إلى m3u8...';
    }
    
    document.getElementById('videoPlayerModal').style.display = 'flex';
    document.getElementById('playerStatus').innerHTML = '🔄 جاري تحميل البث...';
    
    var video = document.getElementById('livePlayer');
    
    if (Hls.isSupported()) {
        var hls = new Hls({
            xhrSetup: function(xhr, url) {
                xhr.withCredentials = false;
            }
        });
        hls.loadSource(streamUrl);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, function() {
            video.play();
            document.getElementById('playerStatus').innerHTML = '✅ جاري التشغيل: ' + channelName;
        });
        hls.on(Hls.Events.ERROR, function(event, data) {
            if (data.fatal) {
                document.getElementById('playerStatus').innerHTML = '❌ فشل التشغيل. الرابط قد لا يكون صالحاً لـ m3u8';
            }
        });
        currentPlayer = hls;
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = streamUrl;
        video.play();
        document.getElementById('playerStatus').innerHTML = '✅ جاري التشغيل (Safari): ' + channelName;
        currentPlayer = video;
    } else {
        document.getElementById('playerStatus').innerHTML = '❌ متصفحك لا يدعم تشغيل m3u8';
    }
}
    
  function closeVideoModal() {
    // إيقاف المشغل
    if (currentPlayer) {
        if (currentPlayer.dispose) {
            currentPlayer.dispose();
        } else if (currentPlayer.pause) {
            currentPlayer.pause();
            currentPlayer.src = '';
            currentPlayer.load();
        }
        currentPlayer = null;
    }
    
    // إخفاء المودال
    document.getElementById('videoPlayerModal').style.display = 'none';
    
    // إيقاف جميع عناصر الفيديو
    var video = document.getElementById('livePlayer');
    if (video) {
        video.pause();
        video.src = '';
        video.load();
    }
    
    // مسح رسالة الحالة
    document.getElementById('playerStatus').innerHTML = '';
}
</script>
</body>
</html>