<?php
// الاتصال بقاعدة البيانات
try {
    $pdo = new PDO('sqlite:data/posts/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // جلب المنشورات مرتبة حسب التاريخ (الأحدث أولاً)
    $stmt = $pdo->prepare("SELECT * FROM posts ORDER BY date DESC");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تحديث المشاهدات عند طلب الصفحة
    if (!empty($posts)) {
        foreach ($posts as $index => $post) {
            $postId = $post['postid'];
            if (!isset($_COOKIE['viewed_post_' . $postId])) {
                $stmt = $pdo->prepare("UPDATE posts SET views = views + 1 WHERE postid = ?");
                $stmt->execute([$postId]);
                setcookie('viewed_post_' . $postId, 'true', time() + (60 * 60 * 24), "/"); // صلاحية الكوكيز ليوم واحد
            }
        }

        // إعادة جلب المنشورات بعد التحديث لضمان عرض العدد الصحيح
        $stmt = $pdo->prepare("SELECT * FROM posts ORDER BY date DESC");
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // حساب الإحصائيات
    $totalPosts = count($posts);
    $lastPostDate = !empty($posts) ? $posts[0]['date'] : 'لا توجد منشورات';
    $totalViews = array_sum(array_column($posts, 'views'));

} catch (PDOException $e) {
    $posts = [];
    $error_message = "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage();
    $totalPosts = 0;
    $lastPostDate = 'غير متاح';
    $totalViews = 0;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المنشورات - مدرسة FH</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --secondary-color: #f8fafc;
            --accent-color: #f59e0b;
            --text-color: #1e293b;
            --light-text-color: #64748b;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1);
            --border-radius: 16px;
            --border-radius-sm: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px 15px;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--card-border);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
            border-radius: 50%;
            transform: translate(30px, -30px);
            opacity: 0.1;
        }

        .header-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-btn {
            background: var(--primary-color);
            border: none;
            font-size: 18px;
            color: white;
            cursor: pointer;
            padding: 12px;
            border-radius: 50%;
            transition: all 0.3s ease;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-md);
        }

        .menu-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .logo {
            width: 70px;
            height: auto;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-sm);
        }

        .developer-name {
            font-size: 1.4em;
            font-weight: 700;
            color: var(--primary-color);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        /* Stats Section */
        .stats-section {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--card-border);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, #ffffff 100%);
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--card-border);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--light-text-color);
            font-weight: 500;
        }

        /* Search Section */
        .search-section {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--card-border);
        }

        .search-container {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 18px 20px 18px 60px;
            border: 2px solid var(--card-border);
            border-radius: 50px;
            font-size: 1.1em;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s ease;
            background: var(--secondary-color);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-text-color);
            font-size: 1.2em;
        }

        /* Posts Container */
        .posts-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* Post Card */
        .post-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 0;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--card-border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .post-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--accent-color));
        }

        .post-header {
            padding: 25px 25px 20px;
            border-bottom: 1px solid var(--card-border);
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }

        .post-title {
            font-size: 1.3em;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .post-type-icon {
            width: 35px;
            height: 35px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1em;
            box-shadow: var(--shadow-sm);
        }

        .post-content {
            padding: 20px 25px;
            font-size: 1.1em;
            line-height: 1.8;
            color: var(--text-color);
            background: white;
        }

        /* Media Container */
        .media-container {
            position: relative;
            margin: 0 25px 20px;
            border-radius: var(--border-radius-sm);
            overflow: hidden;
            background: var(--secondary-color);
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }

        .media-container.loading {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .media-item {
            width: 100%;
            height: 100%;
            display: none;
            position: absolute;
            top: 0;
            left: 0;
        }

        .media-item.active {
            display: block;
        }

        .media-item img,
        .media-item video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: opacity 0.3s ease;
        }

        .media-item img:not([src]),
        .media-item img[src=""],
        .media-item img[src*="undefined"] {
            display: none;
        }

        .media-item video {
            background: #000;
        }

        .media-error {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #dc2626;
            font-size: 1.1em;
            text-align: center;
            width: 100%;
            height: 100%;
            background: #fef2f2;
            border: 2px dashed #fecaca;
        }

        .media-error i {
            font-size: 2em;
            margin-bottom: 10px;
        }

        /* Navigation Arrows */
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(37, 99, 235, 0.9);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }

        .nav-arrow:hover {
            background: var(--primary-color);
            transform: translateY(-50%) scale(1.1);
            box-shadow: var(--shadow-lg);
        }

        .nav-arrow.left {
            left: 15px;
        }

        .nav-arrow.right {
            right: 15px;
        }

        /* Media Counter */
        .media-counter {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(37, 99, 235, 0.9);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }

        /* Post Footer */
        .post-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--light-text-color);
            font-size: 0.95em;
            padding: 20px 25px;
            background: var(--secondary-color);
            border-top: 1px solid var(--card-border);
        }

        .post-meta {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .meta-icon {
            color: var(--primary-color);
            font-size: 1.1em;
        }

        /* Admin Badge */
        .admin-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 15px 25px;
            font-size: 0.9em;
            font-weight: 600;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .admin-badge .post-id {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.8em;
            font-weight: 500;
        }

        /* Menu Popup */
        .menu-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .menu-popup.active {
            display: flex;
            opacity: 1;
        }

        .menu-content {
            background: var(--card-bg);
            color: var(--text-color);
            border-radius: var(--border-radius);
            padding: 35px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: relative;
            box-shadow: var(--shadow-xl);
            transform: translateY(20px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .menu-popup.active .menu-content {
            transform: translateY(0);
            opacity: 1;
        }

        .menu-close {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--secondary-color);
            border: none;
            font-size: 20px;
            color: var(--text-color);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .menu-close:hover {
            background: var(--card-border);
            transform: scale(1.1);
        }

        .menu-title {
            font-size: 1.6em;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 30px;
        }

        .menu-item {
            display: block;
            width: 100%;
            padding: 16px 20px;
            margin-bottom: 15px;
            background: var(--secondary-color);
            color: var(--text-color);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 1.1em;
            border: 2px solid transparent;
        }

        .menu-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .menu-item:last-child {
            margin-bottom: 0;
        }

        .no-posts {
            text-align: center;
            padding: 80px 20px;
            color: var(--light-text-color);
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        .no-posts h3 {
            font-size: 1.8em;
            margin-bottom: 15px;
            color: var(--text-color);
        }

        .no-posts p {
            font-size: 1.1em;
            opacity: 0.8;
        }

        .error-message {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
            padding: 20px;
            text-align: center;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px 10px;
            }
            
            .header {
                padding: 20px;
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .logo {
                width: 60px;
            }
            
            .developer-name {
                font-size: 1.2em;
            }
            
            .stats-section {
                grid-template-columns: 1fr;
                gap: 15px;
                padding: 20px;
            }
            
            .stat-item {
                padding: 15px;
            }
            
            .search-section {
                padding: 20px;
            }
            
            .search-input {
                padding: 15px 20px 15px 50px;
                font-size: 1em;
            }
            
            .post-card {
                margin: 0 5px;
            }
            
            .post-header,
            .post-content {
                padding: 20px;
            }
            
            .media-container {
                margin: 0 20px 15px;
                height: 300px;
            }
            
            .post-footer {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            .post-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .menu-content {
                padding: 25px 20px;
                margin: 20px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 10px 5px;
            }
            
            .header {
                padding: 15px;
            }
            
            .stats-section {
                padding: 15px;
            }
            
            .search-section {
                padding: 15px;
            }
            
            .post-header,
            .post-content {
                padding: 15px;
            }
            
            .media-container {
                margin: 0 15px 10px;
                height: 250px;
            }
            
            .nav-arrow {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.6s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in {
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-info">
                <img src="https://i.ibb.co/nqKS9cbR/1000147798-removebg-preview.png" alt="الشعار" class="logo">
                <div class="developer-name">متقن محمد قروف</div>
            </div>
            <button class="menu-btn" onclick="openMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="stat-number"><?php echo number_format($totalPosts); ?></div>
                <div class="stat-label">إجمالي المنشورات</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-number"><?php echo number_format($totalViews); ?></div>
                <div class="stat-label">إجمالي المشاهدات</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo !empty($posts) ? date('d/m', strtotime($posts[0]['date'])) : '--'; ?></div>
                <div class="stat-label">آخر منشور</div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <div class="search-container">
                <input type="text" class="search-input" id="searchInput" placeholder="ابحث في المنشورات..." onkeyup="filterPosts()">
                <i class="fas fa-search search-icon"></i>
            </div>
        </div>

        <!-- Posts Container -->
        <div class="posts-container" id="postsContainer">
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($posts)): ?>
                <div class="no-posts">
                    <i class="fas fa-inbox" style="font-size: 3em; color: var(--light-text-color); margin-bottom: 20px;"></i>
                    <h3>لا توجد منشورات متاحة حالياً</h3>
                    <p>سيتم إضافة المنشورات قريباً.</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $index => $post): ?>
                    <?php
                    // تحديد نوع المنشور
                    $hasFiles = !empty($post['files']);
                    $postType = 'text';
                    $postTypeIcon = 'fas fa-file-alt';
                    $postTypeText = 'نص';
                    
                    if ($hasFiles) {
                        $files = explode(' | ', $post['files']);
                        $firstFile = ltrim($files[0], '/');
                        if (strpos(strtolower($firstFile), '.mp4') !== false || strpos(strtolower($firstFile), '.webm') !== false || strpos(strtolower($firstFile), '.mov') !== false) {
                            $postType = 'video';
                            $postTypeIcon = 'fas fa-video';
                            $postTypeText = 'فيديو';
                        } else {
                            $postType = 'image';
                            $postTypeIcon = 'fas fa-image';
                            $postTypeText = 'صورة';
                        }
                    }
                    ?>
                    <div class="post-card fade-in" data-post-id="<?php echo htmlspecialchars($post['postid']); ?>" data-type="<?php echo $postType; ?>">
                        <div class="post-header">
                            <div class="post-title">
                                <div class="post-type-icon">
                                    <i class="<?php echo $postTypeIcon; ?>"></i>
                                </div>
                                <span><?php echo $postTypeText; ?> جديد</span>
                            </div>
                        </div>

                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['letter'])); ?>
                        </div>

                        <?php if ($hasFiles): ?>
                            <?php
                            $files = explode(' | ', $post['files']);
                            $fileCount = count($files);
                            ?>
                            <div class="media-container" data-total="<?php echo $fileCount; ?>">
                                <?php foreach ($files as $index => $file): ?>
                                    <?php
                                    $isVideo = (strpos(strtolower(ltrim($file, '/')), '.mp4') !== false || strpos(strtolower(ltrim($file, '/')), '.webm') !== false || strpos(strtolower(ltrim($file, '/')), '.mov') !== false);
                                    $activeClass = $index === 0 ? 'active' : '';
                                    ?>
                                    <div class="media-item <?php echo $activeClass; ?>">
                                        <?php if ($isVideo): ?>
                                            <?php
                                            $fileExtension = strtolower(pathinfo(ltrim($file, '/'), PATHINFO_EXTENSION));
                                            $videoType = 'video/mp4'; // default
                                            if ($fileExtension === 'webm') $videoType = 'video/webm';
                                            elseif ($fileExtension === 'mov') $videoType = 'video/quicktime';
                                            ?>
                                            <video controls>
                                                <source src="<?php echo htmlspecialchars(ltrim($file, '/')); ?>" type="<?php echo $videoType; ?>">
                                                متصفحك لا يدعم تشغيل الفيديو.
                                            </video>
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars(ltrim($file, '/')); ?>" alt="صورة المنشور" loading="lazy">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php if ($fileCount > 1): ?>
                                    <button class="nav-arrow left" onclick="event.stopPropagation(); prevMedia(this);">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <button class="nav-arrow right" onclick="event.stopPropagation(); nextMedia(this);">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <div class="media-counter">1 / <?php echo $fileCount; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="post-footer">
                            <div class="post-meta">
                                <div class="meta-item">
                                    <i class="fas fa-eye meta-icon"></i>
                                    <span><?php echo number_format($post['views']); ?> مشاهدة</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar meta-icon"></i>
                                    <span>
                                        <?php
                                        $dateParts = explode(' | ', $post['date']);
                                        echo htmlspecialchars($dateParts[0] . ' في ' . $dateParts[1]);
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="admin-badge">
                            <i class="fas fa-shield-alt"></i>
                            منشور من قبل الإدارة
                            <div class="post-id">ID: <?php echo htmlspecialchars($post['postid']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Menu Popup -->
    <div class="menu-popup" id="menuPopup">
        <div class="menu-content">
            <button class="menu-close" onclick="closeMenu()">
                <i class="fas fa-times"></i>
            </button>
            <div class="menu-title">منطقة الدخول</div>
            <a href="management.php" class="menu-item">
                <i class="fas fa-cog"></i>
                منطقة الإدارة
            </a>
            <a href="teacher.php" class="menu-item">
                <i class="fas fa-chalkboard-teacher"></i>
                منطقة الأساتذة
            </a>
            <a href="student.php" class="menu-item">
                <i class="fas fa-user-graduate"></i>
                منطقة التلاميذ
            </a>
        </div>
    </div>

    <script>
        // Search functionality
        function filterPosts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const postCards = document.querySelectorAll('.post-card');
            
            postCards.forEach(card => {
                const content = card.querySelector('.post-content').textContent.toLowerCase();
                const title = card.querySelector('.post-title').textContent.toLowerCase();
                const type = card.getAttribute('data-type');
                
                const matchesSearch = content.includes(searchTerm) || 
                                    title.includes(searchTerm) || 
                                    type.includes(searchTerm);
                
                if (matchesSearch) {
                    card.style.display = 'block';
                    card.classList.add('fade-in');
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Menu functions
        function openMenu() {
            document.getElementById('menuPopup').classList.add('active');
        }

        function closeMenu() {
            document.getElementById('menuPopup').classList.remove('active');
        }

        // Media navigation functions
        function nextMedia(button) {
            const container = button.closest('.media-container');
            const items = container.querySelectorAll('.media-item');
            const counter = container.querySelector('.media-counter');

            let currentIndex = -1;
            for (let i = 0; i < items.length; i++) {
                if (items[i].classList.contains('active')) {
                    currentIndex = i;
                    break;
                }
            }

            if (currentIndex !== -1) {
                items[currentIndex].classList.remove('active');
                currentIndex = (currentIndex + 1) % items.length;
                items[currentIndex].classList.add('active');
            }

            if (counter) {
                counter.textContent = `${currentIndex + 1} / ${items.length}`;
            }
        }

        function prevMedia(button) {
            const container = button.closest('.media-container');
            const items = container.querySelectorAll('.media-item');
            const counter = container.querySelector('.media-counter');

            let currentIndex = -1;
            for (let i = 0; i < items.length; i++) {
                if (items[i].classList.contains('active')) {
                    currentIndex = i;
                    break;
                }
            }

            if (currentIndex !== -1) {
                items[currentIndex].classList.remove('active');
                currentIndex = (currentIndex - 1 + items.length) % items.length;
                items[currentIndex].classList.add('active');
            }

            if (counter) {
                counter.textContent = `${currentIndex + 1} / ${items.length}`;
            }
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const menuPopup = document.getElementById('menuPopup');
            const menuContent = menuPopup.querySelector('.menu-content');
            const menuBtn = document.querySelector('.menu-btn');

            if (menuPopup.classList.contains('active') &&
                !menuContent.contains(event.target) &&
                !menuBtn.contains(event.target)) {
                closeMenu();
            }
        });

        // Handle escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        // Add animation delay to posts
        document.addEventListener('DOMContentLoaded', function() {
            const postCards = document.querySelectorAll('.post-card');
            postCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });

            // Handle image and video loading
            const mediaContainers = document.querySelectorAll('.media-container');
            mediaContainers.forEach(container => {
                const images = container.querySelectorAll('img');
                const videos = container.querySelectorAll('video');
                
                // Debug: Log media paths
                images.forEach(img => {
                    console.log('Image src:', img.src);
                });
                videos.forEach(video => {
                    console.log('Video src:', video.querySelector('source').src);
                });
                
                images.forEach(img => {
                    img.addEventListener('load', function() {
                        container.classList.remove('loading');
                    });
                    
                    img.addEventListener('error', function() {
                        container.classList.remove('loading');
                        this.style.display = 'none';
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'media-error';
                        errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i><div>فشل في تحميل الصورة</div>';
                        this.parentNode.appendChild(errorDiv);
                    });
                    
                    if (!img.complete) {
                        container.classList.add('loading');
                    }
                });

                videos.forEach(video => {
                    video.addEventListener('loadeddata', function() {
                        container.classList.remove('loading');
                    });
                    
                    video.addEventListener('error', function() {
                        container.classList.remove('loading');
                        this.style.display = 'none';
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'media-error';
                        errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i><div>فشل في تحميل الفيديو</div>';
                        this.parentNode.appendChild(errorDiv);
                    });
                    
                    container.classList.add('loading');
                });
            });
        });

        // Smooth scroll to top when searching
        document.getElementById('searchInput').addEventListener('input', function() {
            if (this.value.length > 0) {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        });
    </script>
</body>
</html>
