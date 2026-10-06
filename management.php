<?php
session_start();
require_once __DIR__.'/csrf.php';

// --- إعدادات ومتغيرات الصفحة ---
$error_message = '';
$show_dashboard = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// --- إعدادات وقواعد بيانات إدارة المعلمين ---
define('DB_PATH', __DIR__ . '/data/teachers/database.sqlite');
function authenticateAdmin($username, $password) {
    $expected = getenv('DEMO_ADMIN_PASSWORD') ?: '';
    return $expected !== '' && $username === 'demo-admin' && hash_equals($expected, $password);
}
function connectDatabase() {
    try {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS teachers (
            teacher_id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            username_password TEXT NOT NULL UNIQUE,
            permission TEXT NOT NULL
        )");
        return $pdo;
    } catch (PDOException $e) {
        die("خطأ في الاتصال بقاعدة بيانات المعلمين: " . $e->getMessage());
    }
}
function getTeachers() {
    $pdo = connectDatabase();
    $stmt = $pdo->query("SELECT * FROM teachers ORDER BY teacher_id DESC");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($teachers as &$teacher) {
        $permissions = [];
        $permissionStrings = explode('|', $teacher['permission']);
        foreach ($permissionStrings as $permString) {
            $perm = [];
            $parts = explode('/', trim($permString));
            foreach ($parts as $part) {
                $key_value = explode(':', trim($part), 2);
                if (count($key_value) === 2) {
                    $key = trim($key_value[0]);
                    $value = trim($key_value[1]);
                    $perm[$key] = $value;
                }
            }
            if (!empty($perm)) {
                $permissions[] = $perm;
            }
        }
        $teacher['permissions_array'] = $permissions;
    }
    return $teachers;
}
function deleteTeacher($teacherId) {
    $pdo = connectDatabase();
    $stmt = $pdo->prepare("DELETE FROM teachers WHERE teacher_id = ?");
    $stmt->execute([$teacherId]);
    return $stmt->rowCount() > 0;
}
function addTeacher($fullName, $username, $password, $permission) {
    $pdo = connectDatabase();
    try {
        $username_password = $username . ':' . $password;
        $stmt = $pdo->prepare("INSERT INTO teachers (full_name, username_password, permission) VALUES (?, ?, ?)");
        return $stmt->execute([$fullName, $username_password, $permission]);
    } catch (PDOException $e) {
        return false;
    }
}
function updateTeacher($teacherId, $fullName, $username, $password, $permission) {
    $pdo = connectDatabase();
    $username_password = $username . ':' . $password;

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE username_password = ? AND teacher_id != ?");
    $stmtCheck->execute([$username_password, $teacherId]);
    if ($stmtCheck->fetchColumn() > 0) {
        return false;
    }

    $stmt = $pdo->prepare("UPDATE teachers SET full_name = ?, username_password = ?, permission = ? WHERE teacher_id = ?");
    return $stmt->execute([$fullName, $username_password, $permission, $teacherId]);
}
function processUploadedTeachers($teachersData) {
    $pdo = connectDatabase();
    $addedCount = 0;
    $updatedCount = 0;
    $errors = [];

    $pdo->beginTransaction();

    $stmtCheck = $pdo->prepare("SELECT teacher_id FROM teachers WHERE username_password = ?");
    $stmtUpdate = $pdo->prepare("UPDATE teachers SET full_name = ?, permission = ? WHERE teacher_id = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO teachers (full_name, username_password, permission) VALUES (?, ?, ?)");

    foreach ($teachersData as $data) {
        if (count($data) >= 4) {
            $fullName = trim($data[0]);
            $username = trim($data[1]);
            $password = trim($data[2]);
            $permission = trim($data[3]);
            $username_password = $username . ':' . $password;

            if (empty($fullName) || empty($username) || empty($password)) {
                $errors[] = "تجاهل صف فارغ أو غير مكتمل.";
                continue;
            }

            try {
                $stmtCheck->execute([$username_password]);
                $existingTeacher = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existingTeacher) {
                    $stmtUpdate->execute([$fullName, $permission, $existingTeacher['teacher_id']]);
                    $updatedCount++;
                } else {
                    $stmtInsert->execute([$fullName, $username_password, $permission]);
                    $addedCount++;
                }
            } catch (PDOException $e) {
                $errors[] = "فشل معالجة المدرس '{$fullName}': " . $e->getMessage();
            }
        }
    }
    $pdo->commit();

    $message = "تمت المعالجة بنجاح. ";
    if ($addedCount > 0) {
        $message .= "تم إضافة {$addedCount} مدرسين جدد. ";
    }
    if ($updatedCount > 0) {
        $message .= "تم تحديث بيانات {$updatedCount} مدرسين. ";
    }
    if ($addedCount === 0 && $updatedCount === 0) {
        $message = "لم يتم إضافة أو تحديث أي مدرسين.";
    }

    return [
        'success' => true,
        'message' => $message,
        'errors' => $errors
    ];
}

// --- إعدادات وقواعد بيانات إدارة المنشورات ---
define('DB_POSTS_PATH', __DIR__ . '/data/posts/database.sqlite');
define('POST_FILES_PATH', __DIR__ . '/data/posts/files/');
function connectPostsDatabase() {
    try {
        $dir = dirname(DB_POSTS_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . DB_POSTS_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
            postid TEXT PRIMARY KEY,
            letter TEXT NOT NULL,
            views INTEGER DEFAULT 0,
            files TEXT,
            date TEXT NOT NULL
        )");
        return $pdo;
    } catch (PDOException $e) {
        die("خطأ في الاتصال بقاعدة بيانات المنشورات: " . $e->getMessage());
    }
}
function generateUniquePostID($pdo) {
    do {
        $postid = str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE postid = ?");
        $stmt->execute([$postid]);
    } while ($stmt->fetchColumn() > 0);
    return $postid;
}
function addPost($letter, $files) {
    $pdo = connectPostsDatabase();
    
    $uploadedFilePaths = [];
    if (!empty($files['name'][0])) {
        if (!is_dir(POST_FILES_PATH)) {
            mkdir(POST_FILES_PATH, 0777, true);
        }
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $fileExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $mime=(new finfo(FILEINFO_MIME_TYPE))->file($files['tmp_name'][$key]);
                $types=['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
                if (!isset($types[$fileExtension]) || $mime !== $types[$fileExtension] || $files['size'][$key]>5*1024*1024) return false;
                do {
                    $newFileName = mt_rand(100000000000, 999999999999) . '.' . $fileExtension;
                    $targetPath = POST_FILES_PATH . $newFileName;
                } while (file_exists($targetPath));

                if (move_uploaded_file($files['tmp_name'][$key], $targetPath)) {
                    $relativePath = str_replace(__DIR__ . '/', '', $targetPath);
                    $uploadedFilePaths[] = '/' . $relativePath;
                }
            }
        }
    }
    $filesString = count($uploadedFilePaths) > 0 ? implode(' | ', $uploadedFilePaths) : NULL;
    
    $postid = generateUniquePostID($pdo);
    date_default_timezone_set('Asia/Baghdad');
    $date = date('Y/n/j | H:i');
    try {
        $stmt = $pdo->prepare("INSERT INTO posts (postid, letter, files, date) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$postid, $letter, $filesString, $date]);
    } catch (PDOException $e) {
        return false;
    }
}
function deletePost($postId) {
    $pdo = connectPostsDatabase();
    $stmt = $pdo->prepare("SELECT files FROM posts WHERE postid = ?");
    $stmt->execute([$postId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !empty($result['files'])) {
        $files = explode(' | ', $result['files']);
        foreach ($files as $file) {
            $filePath = __DIR__ . ltrim($file, '/');
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
    $deleteStmt = $pdo->prepare("DELETE FROM posts WHERE postid = ?");
    $deleteStmt->execute([$postId]);
    return $deleteStmt->rowCount() > 0;
}
function getPostStats() {
    $pdo = connectPostsDatabase();
    $totalPosts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $stmt = $pdo->query("SELECT date, views FROM posts ORDER BY date ASC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $chartData = ['labels' => [], 'data' => []];
    $maxViewsByDate = [];
    $today = new DateTime('now', new DateTimeZone('Asia/Baghdad'));
    $startDate = (clone $today)->modify('-6 days');

    for ($i = 0; $i < 7; $i++) {
        $date = (clone $startDate)->modify("+$i days")->format('Y/n/j');
        $maxViewsByDate[$date] = 0;
    }

    foreach ($posts as $post) {
        $postDate = explode(' | ', $post['date'])[0];
        $views = (int)$post['views'];
        if (isset($maxViewsByDate[$postDate]) && $views > $maxViewsByDate[$postDate]) {
            $maxViewsByDate[$postDate] = $views;
        }
    }

    uksort($maxViewsByDate, function($a, $b) {
        return strtotime(str_replace('/', '-', $a)) - strtotime(str_replace('/', '-', $b));
    });

    foreach ($maxViewsByDate as $date => $maxViews) {
        $chartData['labels'][] = (new DateTime(str_replace('/', '-', $date)))->format('l');
        $chartData['data'][] = $maxViews;
    }

    return ['totalPosts' => $totalPosts ?? 0, 'chartData' => $chartData];
}

// ----------------------------------------------------
//  معالج طلبات AJAX الموحد
// ----------------------------------------------------
if (isset($_GET['api']) && $show_dashboard) {
    header('Content-Type: application/json');
    $api = $_GET['api'];

    // معالجات إدارة المعلمين
    if ($api === 'getTeachers') {
        echo json_encode(getTeachers());
    } elseif ($api === 'deleteTeacher' && isset($_POST['id'])) {
        $result = deleteTeacher($_POST['id']);
        echo json_encode(['success' => $result]);
    } elseif ($api === 'addTeacher' && isset($_POST['fullName']) && isset($_POST['username']) && isset($_POST['password'])) {
        $fullName = trim($_POST['fullName']);
        $permission = $_POST['permission'] ?? '';
        $result = addTeacher($fullName, $_POST['username'], $_POST['password'], $permission);
        echo json_encode(['success' => $result, 'message' => $result ? 'تمت إضافة المدرس بنجاح.' : 'فشل إضافة المدرس، قد يكون اسم المستخدم موجودًا.']);
    } elseif ($api === 'updateTeacher' && isset($_POST['id'])) {
        $fullName = trim($_POST['fullName']);
        $username = $_POST['username'];
        $password = $_POST['password'];
        $permission = $_POST['permission'] ?? '';
        $result = updateTeacher($_POST['id'], $fullName, $username, $password, $permission);
        echo json_encode(['success' => $result, 'message' => $result ? 'تم التحديث بنجاح' : 'خطأ: اسم المستخدم موجود بالفعل.']);
    } elseif ($api === 'getStats') {
        $pdo = connectDatabase();
        $totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
        $stats = ['totalTeachers' => $totalTeachers ?? 0];
        echo json_encode($stats);
    } elseif ($api === 'uploadTeachers' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data && isset($data['teachers'])) {
            $result = processUploadedTeachers($data['teachers']);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة.']);
        }
    }

    // معالجات إدارة المنشورات
    if ($api === 'getPostStats') {
        echo json_encode(getPostStats());
    } elseif ($api === 'addPost' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $letter = trim($_POST['letter'] ?? '');
        $files = $_FILES['files'] ?? [];
        if (empty($letter)) {
            echo json_encode(['success' => false, 'message' => 'محتوى المنشور مطلوب.']);
        } else {
            $result = addPost($letter, $files);
            echo json_encode(['success' => $result, 'message' => $result ? 'تم نشر المنشور بنجاح.' : 'حدث خطأ أثناء النشر.']);
        }
    } elseif ($api === 'deletePost' && isset($_POST['postId'])) {
        $result = deletePost($_POST['postId']);
        echo json_encode(['success' => $result, 'message' => $result ? 'تم حذف المنشور بنجاح.' : 'فشل الحذف، قد يكون ID المنشور غير صحيح.']);
    }
    
    exit;
}

// ----------------------------------------------------
//  معالجات تسجيل الدخول وتسجيل الخروج
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    session_destroy();
    header('Location: management.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$show_dashboard) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'الرجاء إدخال اسم المستخدم وكلمة المرور.';
    } elseif (authenticateAdmin($username, $password)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $show_dashboard = true;
    } else {
        $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
    }
}

// تحديد متغيرات الصفحة بناءً على حالة تسجيل الدخول
if ($show_dashboard) {
    $page_title = 'لوحة تحكم الإدارة';
    $body_class = 'page-dashboard';
} else {
    $page_title = 'تسجيل دخول الإدارة';
    $body_class = 'page-login';
}

?>	

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8B0000;
            --primary-light: #A52A2A;
            --secondary-color: #4B0082;
            --accent-color: #8B4513;
            --success-color: #228B22;
            --warning-color: #FF8C00;
            --danger-color: #DC143C;
            --info-color: #4169E1;
            
            --bg-primary: #FAFAFA;
            --bg-secondary: #FFFFFF;
            --bg-tertiary: #F5F5F5;
            
            --text-primary: #2C3E50;
            --text-secondary: #7F8C8D;
            --text-light: #BDC3C7;
            
            --border-color: #E8E8E8;
            --border-light: #F0F0F0;
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.15);
            
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        *:not(input, textarea) {
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-tertiary) 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            direction: rtl;
        }
        /* تصميم صفحة الدخول الحديثة */
        .page-login .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            position: relative;
            overflow: hidden;
        }

        .page-login .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .page-login .login-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 50px 40px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 480px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-login .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2.5em;
            box-shadow: var(--shadow-lg);
        }

        .page-login .login-title {
            font-size: 2.2em;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .page-login .login-subtitle {
            color: var(--text-secondary);
            margin-bottom: 40px;
            font-size: 1.1em;
            font-weight: 400;
        }

        .page-login .form-group {
            margin-bottom: 25px;
            text-align: right;
        }

        .page-login .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95em;
        }

        .page-login .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1em;
            font-family: 'Tajawal', sans-serif;
            transition: var(--transition);
            background: var(--bg-secondary);
        }

        .page-login .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .page-login .login-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 700;
            font-family: 'Tajawal', sans-serif;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .page-login .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .page-login .login-btn:hover::before {
            left: 100%;
        }

        .page-login .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .page-login .error {
            background: linear-gradient(135deg, var(--danger-color), #E74C3C);
            color: white;
            padding: 15px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            box-shadow: var(--shadow-md);
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* تصميم لوحة التحكم الحديثة */
        .page-dashboard .dashboard-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 60px;
        }

        .page-dashboard .main-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .page-dashboard .main-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="header-pattern" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23header-pattern)"/></svg>');
        }

        .page-dashboard .main-logo-container {
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .page-dashboard .main-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            color: white;
            font-size: 2.5em;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: var(--transition);
        }

        .page-dashboard .main-logo:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
        }

        .page-dashboard .main-title {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .page-dashboard .developer-name {
            font-size: 1.2em;
            font-weight: 400;
            opacity: 0.9;
        }

        .page-dashboard .logout-container {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 2;
        }

        .page-dashboard .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 12px 24px;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .page-dashboard .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }
        .page-dashboard .management-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .page-dashboard .card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 40px 30px;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border-light);
        }

        .page-dashboard .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--card-color, var(--primary-color)), var(--secondary-color));
        }

        .page-dashboard .card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .page-dashboard .card[data-page="publishing"] {
            --card-color: var(--danger-color);
        }

        .page-dashboard .card[data-page="teachers"] {
            --card-color: var(--secondary-color);
        }

        .page-dashboard .card[data-page="students"] {
            --card-color: var(--warning-color);
        }
        .page-dashboard .card-icon {
            margin: 0 auto 25px;
            position: relative;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-dashboard .card-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--card-color, var(--primary-color));
            opacity: 0.1;
            transition: var(--transition);
        }

        .page-dashboard .card:hover .card-icon::before {
            opacity: 0.2;
            transform: scale(1.1);
        }

        .page-dashboard .card-icon i {
            font-size: 2.5rem;
            color: var(--card-color, var(--primary-color));
            position: relative;
            z-index: 2;
            transition: var(--transition);
        }

        .page-dashboard .card:hover .card-icon i {
            transform: scale(1.1);
        }

        .page-dashboard .card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .page-dashboard .card p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.6;
        }
        .sub-page {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: var(--bg-primary);
            text-align: center;
            padding: 20px;
            overflow-y: auto;
        }

        .sub-page.active {
            display: block;
            animation: slideUp 0.3s ease-out;
        }

        .sub-page .sub-page-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .sub-page .sub-page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--page-color, var(--primary-color)), var(--secondary-color));
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .sub-page .sub-page-header .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .sub-page .sub-page-header .page-icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--page-color, var(--primary-color));
            color: white;
            border-radius: 50%;
            margin-bottom: 20px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
        }

        .sub-page .sub-page-header .page-icon:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }

        .sub-page .sub-page-header .page-icon i {
            font-size: 2.5rem;
        }
        .sub-page .sub-page-header .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .sub-page .header-line {
            background: linear-gradient(90deg, var(--page-color, var(--primary-color)), transparent);
            height: 4px;
            width: 60%;
            max-width: 150px;
            margin: 0 auto 20px;
            border-radius: 2px;
        }

        .sub-page .development-note {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-top: 30px;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--page-color, var(--primary-color));
        }

        #publishing-page {
            --page-color: var(--danger-color);
        }

        #teachers-page {
            --page-color: var(--secondary-color);
        }
        #students-page {
            --page-color: var(--warning-color);
        }

        .teachers-management-section {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            text-align: right;
        }

        .teachers-management-section h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            padding-bottom: 20px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
        }

        .teachers-management-section h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--page-color), var(--secondary-color));
            border-radius: 2px;
        }

        .teachers-management-section .teacher-stats {
            background: var(--bg-secondary);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            text-align: center;
            margin-bottom: 40px;
            border: 1px solid var(--border-light);
        }

        .teachers-management-section .stat-card h4 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--text-secondary);
            font-weight: 600;
        }
        .teachers-management-section .submit-btn {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)) !important;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .teachers-management-section .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .teachers-management-section .info-button {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)) !important;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .teachers-management-section .info-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .teachers-management-section .add-permission-btn {
            background: linear-gradient(135deg, var(--warning-color), #e67e22) !important;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .teachers-management-section .add-permission-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .teachers-management-section .stat-card p {
            font-size: 2.5rem;
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
        }
        .teachers-list-container {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 15px;
            margin-bottom: 40px;
        }

        .teachers-list-container::-webkit-scrollbar {
            width: 8px;
        }

        .teachers-list-container::-webkit-scrollbar-track {
            background: var(--bg-primary);
            border-radius: 4px;
        }

        .teachers-list-container::-webkit-scrollbar-thumb {
            background: var(--text-secondary);
            border-radius: 4px;
            border: 2px solid var(--bg-primary);
        }

        .teachers-list-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }

        .teachers-list {
            list-style: none;
            padding: 0;
            margin-bottom: 0;
        }

        .teachers-list li {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }

        .teachers-list li:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .teachers-list .teacher-name {
            font-weight: 600;
            font-size: 1.1rem;
            flex-grow: 1;
            color: var(--text-primary);
            background: linear-gradient(135deg, var(--bg-primary), #f8f9fa);
            padding: 12px 24px;
            border-radius: var(--radius-full);
            max-width: calc(100% - 120px);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 45px;
            border: 1px solid var(--border-light);
        }

        .teachers-list .teacher-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .teachers-list .teacher-actions button {
            background: var(--bg-secondary);
            border: 1px solid var(--border-light);
            cursor: pointer;
            padding: 8px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .teachers-list .teacher-actions button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .teachers-list .teacher-actions button i {
            font-size: 1.2rem;
            transition: var(--transition);
        }

        .teachers-list .teacher-actions .edit-btn {
            border-color: var(--warning-color);
        }

        .teachers-list .teacher-actions .edit-btn i {
            color: var(--warning-color);
        }

        .teachers-list .teacher-actions .edit-btn:hover {
            background: var(--warning-color);
        }

        .teachers-list .teacher-actions .edit-btn:hover i {
            color: white;
        }

        .teachers-list .teacher-actions .delete-btn {
            border-color: var(--danger-color);
        }

        .teachers-list .teacher-actions .delete-btn i {
            color: var(--danger-color);
        }

        .teachers-list .teacher-actions .delete-btn:hover {
            background: var(--danger-color);
        }

        .teachers-list .teacher-actions .delete-btn:hover i {
            color: white;
        }
        .edit-fields { background: #eef1f4; padding: 20px; margin-top: 15px; border-radius: 12px; display: none; text-align: right; width: 100%; }
        .edit-fields.show { display: block; animation: slideDown 0.3s ease-out; }
        .form-row { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px; }
        @media (min-width: 600px) { .form-row { grid-template-columns: 1fr 1fr; } }
        .edit-fields .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .edit-fields .form-group input, .add-teacher-section input, .add-teacher-section select { width: 100%; padding: 10px 15px; border: 1px solid var(--border-colorr); border-radius: 8px; transition: none; }
        .edit-fields .form-group input:focus, .add-teacher-section input:focus, .add-teacher-section select:focus { outline: none; border-color: var(--primary-colorr); box-shadow: 0 0 0 3px rgba(92, 114, 150, 0.1); }
        .edit-fields .form-actions { text-align: center; margin-top: 20px; }
        .edit-fields .save-btn, .edit-fields .cancel-btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; transition: none; }
        .edit-fields .save-btn, .edit-fields .cancel-btn { background-color: var(--edit-btn-color); color: white; }
        .edit-fields .cancel-btn { margin-right: 10px; }
        .add-teacher-section h2 { cursor: pointer; }
        .add-teacher-section form, .upload-teachers-section form { background: var(--card-bgg); padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: right; }
        .add-teacher-section .submit-btn { background-color: var(--primary-colorr); color: white; padding: 15px 15px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: none; width: auto; display: inline-block; margin-top: 20px; }
        .upload-teachers-section h2 { cursor: pointer; }
        .upload-teachers-section { text-align: center; }
        .upload-teachers-section form { max-width: 600px; margin: 0 auto; }
        .upload-teachers-section .file-input-container { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 15px; }
        .upload-teachers-section .file-input { border: 2px dashed #b0bec5; padding: 15px 20px; border-radius: 10px; cursor: pointer; transition: none; width: 100%; text-align: center; }
        .upload-teachers-section .submit-btn { background-color: var(--primary-colorr); color: white; padding: 12px 25px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: none; margin-top: 20px; }
        .upload-teachers-section .upload-note { background: var(--note-bg); border-radius: 10px; padding: 15px; font-size: 0.9em; color: var(--secondary-colorr); margin-top: 15px; text-align: right; }
        .upload-teachers-section .upload-note code { display: block; margin-top: 10px; font-family: monospace; background: #c5c5d0; padding: 10px; border-radius: 5px; direction: ltr; text-align: left; white-space: pre-wrap; word-break: break-all; }
        .section-separator { height: 1px; background: var(--border-colorr); margin: 50px auto; width: 80%; max-width: 400px; }
        .status-message { padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; position: relative; }
        .status-message.success { background-color: #d4edda; color: var(--success-color); border: 1px solid #c3e6cb; }
        .status-message.error { background-color: #f8d7da; color: var(--danger-color); border: 1px solid #f5c6cb; }
        .status-message .close-btn { position: absolute; top: 5px; left: 10px; font-size: 20px; cursor: pointer; color: var(--secondary-colorr); }
        .permissions-container { border: 1px solid var(--border-colorr); border-radius: 8px; padding: 10px; background-color: #f8f9fa; }
        .permission-item { display: flex; flex-direction: column; background-color: #e9ecef; padding: 8px 12px; border-radius: 6px; margin-bottom: 8px; font-size: 0.95em; color: var(--text-colorr); }
        .permission-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .permission-item-header .remove-permission-btn { background: none; border: none; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; margin-right: 10px; }
        .permission-item-header .remove-permission-btn i {
            font-size: 1rem;
            color: var(--danger-color);
            transition: var(--transition);
        }

        .permission-item-header .remove-permission-btn:hover i {
            color: white;
        }
        .permission-item:last-child { margin-bottom: 0; }
        .permission-field { display: flex; align-items: center; margin-bottom: 5px; gap: 10px; }
        .permission-field label { font-weight: bold; width: 80px; text-align: right; }
        .permission-field input { flex-grow: 1; padding: 5px 10px; border: 1px solid var(--border-colorr); border-radius: 5px; }
        .permission-creator { border: 1px solid var(--border-colorr); padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .permission-inputs-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .permission-inputs-row > div { flex: 1; min-width: 150px; }
        .permission-counter { font-size: 1.1em; color: var(--primary-colorr); font-weight: bold; margin-bottom: 15px; text-align: center; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 5px; }
        .checkbox-group label { display: flex; align-items: center; gap: 5px; cursor: pointer; }
        .checkbox-group input[type="checkbox"] { width: auto; }
        .add-permission-btn, .permission-form .add-btn, .permission-form .remove-btn { background-color: var(--edit-btn-color); color: white; padding: 8px 15px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 15px; }
        .permission-form .remove-btn { background-color: var(--danger-color); }
        .permission-form { border: 1px solid var(--border-colorr); padding: 15px; border-radius: 10px; margin-bottom: 15px; }
        .permission-form .form-actions { text-align: left; }
        .add-teacher-section #addPermissionBtn { background-color: var(--edit-btn-color); }
        .info-button-container { text-align: center; margin-top: 20px; }
        .info-button { background-color: var(--primary-colorr); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        /* Popup styles */
        .popup {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .popup-content {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 100%;
            position: relative;
            text-align: right;
            direction: rtl;
            max-height: 80vh; /* Set a maximum height */
            overflow-y: auto; /* Enable vertical scroll */
        }
        .popup-content::-webkit-scrollbar {
            width: 8px;
        }
        .popup-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .popup-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .popup-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .popup-content .close-btn {
            color: #aaa;
            float: left;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
        .popup-content h3 {
            font-size: 1.2em;
            margin-bottom: 15px;
            color: var(--primary-colorr);
            text-align: center;
        }
        .popup-content p, .popup-content ul, .popup-content code {
            color: var(--text-colorr);
            margin-bottom: 10px;
            line-height: 1.6;
            font-size: 0.9em;
        }
        .popup-content ul {
            padding-right: 20px;
            list-style-type: square;
        }
        .popup-content code {
            display: block;
            background-color: #f0f0f5;
            padding: 8px;
            border-radius: 8px;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
            font-size: 0.8em;
        }
        .popup-content .separator-line {
            height: 1px;
            background-color: var(--border-colorr);
            margin: 15px 0;
        }
        .popup-content .note {
            font-size: 0.8em;
            color: var(--secondary-colorr);
            margin-top: 10px;
        }
        
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        /* Responsive Design */
        @media (max-width: 768px) {
            .page-dashboard .management-cards {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .page-dashboard .main-header {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .page-dashboard .main-logo {
                width: 60px;
                height: 60px;
            }
            
            .page-dashboard .main-title {
                font-size: 1.5rem;
            }
            
            .sub-page .sub-page-header .page-icon {
                width: 60px;
                height: 60px;
            }
            
            .teachers-list .teacher-name {
                max-width: 100%;
                text-align: right;
                margin-bottom: 10px;
            }
            
            .teachers-list .teacher-actions {
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
            
            .teachers-list li {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .teachers-management-section {
                padding: 15px;
            }
            
            .upload-teachers-section .upload-note code {
                text-align: right;
            }
            
            .form-row {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-width: 480px) {
            .page-dashboard .card {
                padding: 30px 20px;
            }
            
            .page-dashboard .card-icon {
                width: 60px;
                height: 60px;
            }
            
            .page-dashboard .card-icon i {
                font-size: 2rem;
            }
            
            .sub-page {
                padding: 15px;
            }
            
            .teachers-management-section h2 {
                font-size: 1.5rem;
            }
        }

        .submit-btn.loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body class="<?php echo $body_class; ?>">
    <?php if (!$show_dashboard): ?>
        <div class="login-container">
            <div class="login-box">
                <img src="https://i.ibb.co/nqKS9cbR/1000147798-removebg-preview.png" alt="الشعار" class="login-logo">
                <h1 class="login-title">لوحة تحكم الإدارة</h1>
                <p class="login-subtitle">مرحباً بك في النظام الإداري</p>
                <?php if (!empty($error_message)): ?>
                    <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <form method="POST" action="management.php" novalidate>
                    <div class="form-group">
                        <label for="username">اسم المستخدم</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">كلمة المرور</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="login-btn">دخول</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="dashboard-container">
            <div class="dashboard-content">
                <header class="main-header" id="mainHeader">
                    <div class="logout-container">
                        <a href="management.php?action=logout" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>تسجيل الخروج</span>
                        </a>
                    </div>
                    <div class="main-logo-container">
                        <div class="main-logo">
                            <i class="fas fa-school"></i>
                        </div>
                        <h1 class="main-title">نظام إدارة المدرسة</h1>
                        <p class="developer-name">متقن محمد قروف</p>
                    </div>
                </header>
                <main class="management-cards" id="managementCards">
                    <div class="card" data-page="publishing">
                        <div class="card-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <h3>إدارة النشر</h3>
                        <p>نشر الإعلانات والأخبار والتحديثات المهمة.</p>
                    </div>
                    <div class="card" data-page="teachers">
                        <div class="card-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h3>إدارة الأساتذة</h3>
                        <p>إدارة بيانات الأساتذة وصلاحياتهم التعليمية.</p>
                    </div>
                    <div class="card" data-page="students">
                        <div class="card-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h3>إدارة التلاميذ</h3>
                        <p>إدارة شاملة لبيانات التلاميذ والدرجات والملاحظات.</p>
                    </div>
                </main>
            </div>
            
            <div id="publishing-page" class="sub-page">
                <header class="sub-page-header">
                    <div class="header-content">
                        <div class="page-icon" onclick="goBack()">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <h2 class="page-title">إدارة النشر</h2>
                        <div class="header-line"></div>
                    </div>
                </header>
                <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>إدارة النشر</title><style>@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap');:root{--primary-colorr:#ff6b6b;--secondary-colorr:#9898b3;--text-colorr:#3b3b54;--border-colorr:#e0e0e0;--danger-color:#d9534f;--success-color:#28a745;--edit-btn-color:#726a95;--publishing-color:#ff6b6b;--bg-color:#f2f3f5;--card-bgg:#ffffff;}*{margin:0;padding:0;box-sizing:border-box;}.management-section{padding:20px;max-width:900px;margin:0 auto;text-align:right;}.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;background:var(--card-bgg);padding:20px;border-radius:15px;box-shadow:0 4px 15px rgba(0,0,0,0.05);text-align:center;margin-bottom:40px;}.stat-card h4{font-size:1.2em;margin-bottom:5px;color:var(--secondary-colorr);}.stat-card p{font-size:2.5em;color:var(--primary-colorr);font-weight:700;}.chart-container{margin-top:20px;grid-column:1/-1;}.section-separator{height:1px;background:var(--border-colorr);margin:50px auto;width:80%;max-width:400px;}.status-message{padding:10px 15px;border-radius:8px;margin-bottom:20px;font-weight:bold;text-align:center;position:relative;}.status-message.success{background-color:#d4edda;color:var(--success-color);border:1px solid #c3e6cb;}.status-message.error{background-color:#f8d7da;color:var(--danger-color);border:1px solid #f5c6cb;}.status-message .close-btn{position:absolute;top:5px;left:10px;font-size:20px;cursor:pointer;color:var(--secondary-colorr);}.submit-btn{background-color:var(--primary-colorr);color:white;padding:12px 25px;border:none;border-radius:8px;font-weight:bold;cursor:pointer;transition:opacity 0.3s;}.submit-btn:disabled{background-color:var(--publishing-color);opacity:0.6;cursor:not-allowed;}.submit-btn.loading{opacity:0.6;pointer-events:none;}.publish-section,.publish-actions{background:var(--card-bgg);padding:30px;border-radius:15px;box-shadow:0 4px 15px rgba(0,0,0,0.05);}.publish-section textarea{width:100%;min-height:120px;padding:15px;border:1px solid var(--border-colorr);border-radius:8px;resize:vertical;font-size:1.1em;}.publish-section .publish-footer{display:flex;justify-content:space-between;align-items:center;margin-top:20px;}.publish-section .file-upload-btn{background-color:var(--publishing-color);color:white;padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:bold;}.publish-actions{text-align:center;margin-top:40px;padding:20px;}.publish-actions h3{text-decoration:none;text-decoration-thickness:1px;text-underline-offset:8px;margin-bottom:25px;cursor:pointer;color:var(--text-colorr);display:inline-block;margin:0 15px;}.popup{display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.5);justify-content:center;align-items:center;}.popup-content{background-color:white;padding:30px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.3);max-width:450px;width:90%;position:relative;text-align:center;}.popup-content .close-btn{color:#aaa;position:absolute;top:10px;left:15px;font-size:28px;font-weight:bold;cursor:pointer;}.popup-content h3{font-size:1.5em;margin-bottom:20px;color:var(--primary-colorr);}.popup-content input{width:100%;padding:12px;border:1px solid var(--border-colorr);border-radius:8px;margin-bottom:20px;text-align:center;font-size:1.2em;}.popup-content .submit-btn.danger{background-color:var(--danger-color);}.iframe-viewer{display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,0.8);backdrop-filter:blur(5px);}.iframe-viewer iframe{width:100%;height:100%;border:none;}.iframe-viewer .close-viewer-btn{position:absolute;top:20px;left:20px;background:white;color:black;border:none;border-radius:50%;width:40px;height:40px;font-size:24px;cursor:pointer;box-shadow:0 0 10px rgba(0,0,0,0.3);display:flex;justify-content:center;align-items:center;}body{-webkit-tap-highlight-color: transparent;}.submit-btn{-webkit-tap-highlight-color: transparent;}.file-upload-btn{-webkit-tap-highlight-color: transparent;}.close-btn{-webkit-tap-highlight-color: transparent;}.publish-actions h3{-webkit-tap-highlight-color: transparent;}.popup-content .close-btn{-webkit-tap-highlight-color: transparent;}.iframe-viewer .close-viewer-btn{-webkit-tap-highlight-color: transparent;}</style></head><body><div class="management-section"><div class="stats-grid"><div class="stat-card"><h4>إجمالي المنشورات</h4><p id="totalPosts">0</p></div><div class="chart-container"><canvas id="viewsChart"></canvas></div></div><div class="status-message" id="posts-status-message" style="display:none;"></div><div class="section-separator"></div><form id="addPostForm" class="publish-section"><h3>إنشاء منشور جديد</h3><textarea id="postLetter" name="letter" placeholder="اكتب منشورك هنا"></textarea><input type="file" name="files[]" id="postFiles" multiple style="display:none;" accept="image/*,video/*"><div class="publish-footer"><button type="button" class="file-upload-btn" onclick="$('#postFiles').click();">رفع صورة أو فيديو</button><button type="submit" id="publishBtn" class="submit-btn" disabled>نشر</button></div></form><div class="section-separator"></div><div class="publish-actions"><h3 id="showDeletePostModalBtn">حذف منشور</h3></div><div class="publish-actions"><h3 id="showPostsViewerBtn">عرض المنشورات</h3></div></div><div id="deletePostModal" class="popup"><div class="popup-content"><span class="close-btn" onclick="$(this).parent().parent().fadeOut()">&times;</span><h3>حذف منشور</h3><p>الرجاء إدخال ID المنشور لحذفه نهائياً.</p><form id="deletePostForm"><input type="text" id="postIdToDelete" placeholder="ID المنشور" required><button type="submit" class="submit-btn danger">حذف</button></form></div></div><div id="postsViewer" class="iframe-viewer"><button class="close-viewer-btn" onclick="$('#postsViewer').fadeOut()">&times;</button><iframe src="post.php"></iframe></div><script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>const totalPostsStat=$('#totalPosts');const postsStatusMessage=$('#posts-status-message');const addPostForm=$('#addPostForm');const postLetterInput=$('#postLetter');const publishBtn=$('#publishBtn');let viewsChartInstance=null;function showPostsStatus(message,type='success'){postsStatusMessage.html(message).removeClass().addClass('status-message '+type).append('<span class="close-btn" onclick="$(this).parent().fadeOut()">&times;</span>').fadeIn();setTimeout(()=>postsStatusMessage.fadeOut(),5000);}function fetchPostStats(){$.ajax({url:'management.php?api=getPostStats',method:'GET',dataType:'json',success:function(result){totalPostsStat.text(result.totalPosts);renderViewsChart(result.chartData);},error:function(){showPostsStatus('فشل في تحميل إحصائيات النشر.','error');}});}function renderViewsChart(chartData){const ctx=document.getElementById('viewsChart').getContext('2d');if(viewsChartInstance){viewsChartInstance.destroy();}viewsChartInstance=new Chart(ctx,{type:'line',data:{labels:chartData.labels,datasets:[{label:'أعلى مشاهدة يومية',data:chartData.data,backgroundColor:'rgba(255, 107, 107, 0.5)',borderColor:'#ff6b6b',borderWidth:2,tension:0.4,fill:true}]},options:{responsive:true,plugins:{legend:{display:false},tooltip:{callbacks:{title:function(tooltipItems){return'تاريخ: '+chartData.labels[tooltipItems[0].dataIndex];},label:function(tooltipItem){let label='المشاهدات: '+tooltipItem.raw;return label;}}}},scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false},title:{display:true,text:'مقارنة المشاهدات للأسبوع الماضي'},ticks:{display:false}}}}});}postLetterInput.on('input',function(){publishBtn.prop('disabled',$(this).val().trim()==='');});addPostForm.on('submit',function(e){e.preventDefault();publishBtn.addClass('loading').prop('disabled',true);const formData=new FormData(this);$.ajax({url:'management.php?api=addPost',method:'POST',data:formData,processData:false,contentType:false,dataType:'json',success:function(response){showPostsStatus(response.message,response.success?'success':'error');if(response.success){addPostForm[0].reset();postLetterInput.trigger('input');fetchPostStats();}},error:function(){showPostsStatus('حدث خطأ في الشبكة.','error');},complete:function(){publishBtn.removeClass('loading');}});});$('#showDeletePostModalBtn').on('click',()=>$('#deletePostModal').fadeIn().css('display','flex'));$('#deletePostForm').on('submit',function(e){e.preventDefault();const postId=$('#postIdToDelete').val().trim();if(!postId)return;const deleteBtn=$(this).find('.submit-btn');deleteBtn.addClass('loading').prop('disabled',true);$.ajax({url:'management.php?api=deletePost',method:'POST',data:{postId:postId},dataType:'json',success:function(response){showPostsStatus(response.message,response.success?'success':'error');if(response.success){$('#deletePostModal').fadeOut();$('#postIdToDelete').val('');fetchPostStats();}},error:function(){showPostsStatus('حدث خطأ في الشبكة.','error');},complete:function(){deleteBtn.removeClass('loading').prop('disabled',false);}});});$('#showPostsViewerBtn').on('click',()=>$('#postsViewer').fadeIn());fetchPostStats();</script><script>
const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;
if (window.jQuery) $.ajaxSetup({headers: {'X-CSRF-Token': csrfToken}});
document.querySelectorAll('form').forEach(form => {
    const token=document.createElement('input');token.type='hidden';token.name='csrf_token';token.value=csrfToken;form.appendChild(token);
});
</script>
</body>
            </div>
            
            <div id="teachers-page" class="sub-page">
                <header class="sub-page-header">
                    <div class="header-content">
                        <div class="page-icon" onclick="goBack()">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h2 class="page-title">إدارة الأساتذة</h2>
                        <div class="header-line"></div>
                    </div>
                </header>
                <div class="sub-page-content">
                    <div class="teachers-management-section">
                        <div class="teacher-stats">
                            <div class="stat-card">
                                <h4>إجمالي الأساتذة</h4>
                                <p id="totalTeachers">0</p>
                            </div>
                        </div>

                        <div class="status-message" id="teachers-status-message" style="display: none;"></div>
                        
                        <div class="teachers-list-container">
                            <ul class="teachers-list" id="teachersList"></ul>
                        </div>

                        <div class="section-separator"></div>

                        <div class="add-teacher-section">
                            <h2 id="addTeacherToggle">إضافة مدرس جديد</h2>
                            <form id="addTeacherForm" action="" method="POST" style="display: none;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="newFullName">الاسم الكامل</label>
                                        <input type="text" id="newFullName" name="fullName" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="newUsername">اسم المستخدم</label>
                                        <input type="text" id="newUsername" name="username" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="newPassword">كلمة المرور</label>
                                        <input type="password" id="newPassword" name="password" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>صلاحيات التدريس</label>
                                    <div class="permission-creator">
                                        <div class="permission-counter">عدد الصلاحيات المضافة: <span id="permission-count">0</span></div>
                                        <div class="permission-creator-content">
                                            </div>
                                        <button type="button" class="add-permission-btn" id="addPermissionBtn">إضافة</button>
                                    </div>
                                    <textarea name="permission" id="permissionInput" style="display: none;"></textarea>
                                </div>
                                <button type="submit" class="submit-btn">إضافة المدرس</button>
                            </form>
                        </div>
                        
                        <div class="section-separator"></div>

                        <div class="upload-teachers-section">
                            <h2 id="uploadTeachersToggle">رفع دفعة مدرسين</h2>
                            <form id="uploadTeachersForm" action="#" method="POST" enctype="multipart/form-data" style="display: none;">
                                <div class="file-input-container">
                                    <label for="teacherFile" class="file-input" id="fileInputLabel">اختر ملف CSV</label>
                                    <input type="file" id="teacherFile" name="teacherFile" accept=".csv" style="display: none;">
                                    <button type="submit" class="submit-btn" id="uploadBtn">رفع الملف</button>
                                </div>
                                <div class="upload-note">
                                    <p><strong>ملاحظة:</strong> يجب أن يكون الملف بتنسيق CSV بالترتيب التالي: الاسم الكامل، اسم المستخدم، كلمة المرور، الصلاحيات.</p>
                                    <p><strong>مثال على التنسيق (CSV):</strong></p>
                                    <code>Full Name,Username,Password,Permission<br>"أحمد محمد","ahmed","pass123","Year: اول ثانوي / branch: علمي / subject: اللغة العربية / classes: 1,2"</code>
                                </div>
                                <div class="info-button-container" id="infoButtonContainer" style="display:none;">
                                    <button type="button" class="info-button" id="showInfoBtn">اضغط للتعرف على المزيد</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="students-page" class="sub-page">
                <header class="sub-page-header">
                    <div class="header-content">
                        <svg class="page-icon" onclick="goBack()" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.905 59.905 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-127.0.0.1 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                        </svg>
                        <h2 class="page-title">إدارة التلاميذ</h2>
                        <div class="header-line"></div>
                    </div>
                </header>
                ​<div class="publish-actions"><h3 id="showStudentsViewerBtn">عرض القسم</h3></div><div id="studentsViewer" class="iframe-viewer"><button class="close-viewer-btn" onclick="$('#studentsViewer').fadeOut()">&times;</button><iframe src="student_management.php"></iframe></div><script>$('#showStudentsViewerBtn').on('click', () => $('#studentsViewer').fadeIn());</script>
            </div>
        </div>
        <div id="infoPopup" class="popup">
            <div class="popup-content">
                <span class="close-btn" onclick="closePopup()">&times;</span>
                <h3>معلومات عن الصلاحيات</h3>
                <p><strong>المواد:</strong></p>
                <code>اللغة العربية، اللغة الفرنسية، اللغة الإنجليزية، اللغة الإسبانية، اللغة الألمانية، الفلسفة، التاريخ والجغرافيا، العلوم الإسلامية، الرياضيات، العلوم الطبيعية، الفيزياء، تسيير ومحاسبة، الاقتصاد، القانون، تكنولوجيا، الإعلام الآلي، التربية البدنية، اللغة الأمازيغية</code>
                <div class="separator-line"></div>
                <p><strong>الشعب:</strong></p>
                <code>تسيير واقتصاد، تقني رياضي، الرياضيات، العلوم التجريبية، آداب وفلسفة، لغات أجنبية</code>
                <div class="separator-line"></div>
                <p><strong>الفروع:</strong></p>
                <code>علمي، ادبي</code>
                <div class="separator-line"></div>
                <p><strong>السنوات:</strong></p>
                <code>اول ثانوي، ثاني ثانوي، ثالث ثانوي</code>
                <div class="separator-line"></div>
                <p><strong>قاعدة الصلاحيات للأول ثانوي:</strong></p>
                <code>Year: [السنة] / branch: [الفرع] / subject: [المادة] / classes: [الأقسام]</code>
                <p class="note">لإضافة أكثر من صلاحية، افصل بينها بالرمز |</p>
                <div class="separator-line"></div>
                <p><strong>مثال:</strong></p>
                <code>Year: اول ثانوي / branch: علمي / subject: اللغة العربية / classes: 1,2 | Year: اول ثانوي / branch: ادبي / subject: اللغة الإنجليزية / classes: 3</code>
                <div class="separator-line"></div>
                <p><strong>قاعدة الصلاحية للثاني والثالث ثانوي:</strong></p>
                <code>Year: [السنة] / branch: [الفرع] / subject: [المادة] / classes: [الأقسام] / part: [الشعبة]</code>
                <p class="note">لإضافة أكثر من صلاحية، افصل بينها بالرمز |</p>
                <div class="separator-line"></div>
                <p><strong>مثال:</strong></p>
                <code>Year: ثاني ثانوي / branch: علمي / subject: الفيزياء / classes: 5,6 / part: علوم تجريبية</code>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.0/papaparse.min.js"></script>
    <script>
        if (document.body.classList.contains('page-dashboard')) {
            $(document).ready(function() {
                const cards = $('.card');
                const subPages = $('.sub-page');
                const mainHeader = $('#mainHeader');
                const managementCards = $('#managementCards');
                const teachersList = $('#teachersList');
                const totalTeachersStat = $('#totalTeachers');
                const addTeacherToggle = $('#addTeacherToggle');
                const addTeacherForm = $('#addTeacherForm');
                const teachersStatusMessage = $('#teachers-status-message');
                const addPermissionBtn = $('#addPermissionBtn');
                const permissionCreatorContent = $('.permission-creator-content');
                const permissionCountSpan = $('#permission-count');
                const uploadTeachersToggle = $('#uploadTeachersToggle');
                const uploadTeachersForm = $('#uploadTeachersForm');
                const fileInput = $('#teacherFile');
                const fileInputLabel = $('#fileInputLabel');
                const uploadBtn = $('#uploadBtn');
                const infoButtonContainer = $('#infoButtonContainer');
                const showInfoBtn = $('#showInfoBtn');
                const infoPopup = $('#infoPopup');

                // تعريفات المواد والأقسام
                const subjects = {
                    'ادبي': ['اللغة العربية', 'اللغة الفرنسية', 'اللغة الإنجليزية', 'الرياضيات', 'التاريخ والجغرافيا', 'العلوم الطبيعية', 'الفيزياء', 'العلوم الإسلامية', 'الإعلام الآلي', 'التربية البدنية', 'اللغة الأمازيغية'],
                    'علمي': ['اللغة العربية', 'اللغة الفرنسية', 'اللغة الإنجليزية', 'الرياضيات', 'التاريخ والجغرافيا', 'العلوم الطبيعية', 'الفيزياء', 'العلوم الإسلامية', 'الإعلام الآلي', 'تكنولوجيا', 'التربية البدنية', 'اللغة الأمازيغية'],
                    'لغات أجنبية': ['اللغة العربية', 'اللغة الإنجليزية', 'اللغة الفرنسية', 'اللغة الإسبانية', 'التاريخ والجغرافيا', 'العلوم الإسلامية', 'الرياضيات', 'التربية البدنية', 'اللغة الأمازيغية'],
                    'آداب وفلسفة': ['اللغة العربية', 'اللغة الإنجليزية', 'اللغة الفرنسية', 'التاريخ والجغرافيا', 'الفلسفة', 'العلوم الإسلامية', 'الرياضيات', 'الفيزياء', 'العلوم الطبيعية', 'التربية البدنية', 'اللغة الأمازيغية'],
                    'تسيير واقتصاد': ['اللغة العربية', 'اللغة الإنجليزية', 'اللغة الفرنسية', 'الرياضيات', 'التاريخ والجغرافيا', 'العلوم الإسلامية', 'الاقتصاد', 'القانون', 'تسيير ومحاسبة', 'التربية البدنية', 'اللغة الأمازيغية'],
                    'تقني رياضي': ['اللغة العربية', 'اللغة الإنجليزية', 'اللغة الفرنسية', 'الرياضيات', 'تكنولوجيا', 'الفيزياء', 'التاريخ والجغرافيا', 'العلوم الإسلامية', 'التربية البدنية', 'اللغة الأمازيغية'],
                    'الرياضيات': ['اللغة العربية', 'اللغة الإنجليزية', 'اللغة الفرنسية', 'الرياضيات', 'العلوم الطبيعية', 'الفيزياء', 'التاريخ والجغرافيا', 'العلوم الإسلامية', 'التربية البدنية', 'اللغة الأمازيغية'],
                    'العلوم التجريبية': ['اللغة العربية', 'اللغة الإنجليزية', 'اللغة الفرنسية', 'الرياضيات', 'العلوم الطبيعية', 'الفيزياء', 'التاريخ والجغرافيا', 'العلوم الإسلامية', 'التربية البدنية', 'اللغة الأمازيغية']
                };

                const branches = {
                    'ادبي': ['لغات أجنبية', 'آداب وفلسفة'],
                    'علمي': ['تسيير واقتصاد', 'تقني رياضي', 'الرياضيات', 'العلوم التجريبية']
                };

                let permissionCounter = 0;

                function showStatus(message, type = 'success') {
                    teachersStatusMessage.html(message).removeClass().addClass('status-message ' + type).append('<span class="close-btn" onclick="$(this).parent().fadeOut()">&times;</span>').fadeIn();
                    setTimeout(() => {
                        teachersStatusMessage.fadeOut();
                    }, 5000);
                }

                function fetchAndRenderTeachers() {
                    $.ajax({
                        url: 'management.php?api=getTeachers',
                        method: 'GET',
                        dataType: 'json',
                        success: function(data) {
                            teachersList.empty();
                            if (data.length === 0) {
                                teachersList.html('<p style="text-align: center; color: var(--secondary-colorr);">لا يوجد أساتذة مضافين حاليًا.</p>');
                            } else {
                                data.forEach(teacher => {
                                    const [username, password] = teacher.username_password.split(':');
                                    const li = $('<li>').attr('data-id', teacher.teacher_id);
                                    
                                    const nameParts = teacher.full_name.split(' ');
                                    const firstName = nameParts[0];
                                    const lastName = nameParts.slice(1).join(' ');

                                    const permissionsHtml = teacher.permissions_array.map(perm => {
                                        let fieldsHtml = '';
                                        for (const key in perm) {
                                            fieldsHtml += `
                                                <div class="permission-field">
                                                    <label>${key}:</label>
                                                    <input type="text" value="${perm[key]}" data-key="${key}">
                                                </div>
                                            `;
                                        }
                                        return `
                                            <div class="permission-item">
                                                <div class="permission-item-header">
                                                    <button type="button" class="remove-permission-btn" title="حذف الصلاحية">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                                ${fieldsHtml}
                                            </div>
                                        `;
                                    }).join('');

                                    li.html(`
                                        <span class="teacher-name">${teacher.full_name}</span>
                                        <div class="teacher-actions">
                                            <button class="edit-btn" title="تحرير">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="delete-btn" title="حذف">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        <div class="edit-fields">
                                            <form class="edit-form" data-id="${teacher.teacher_id}">
                                                <div class="form-row">
                                                    <div class="form-group">
                                                        <label>الاسم</label>
                                                        <input type="text" name="firstName" value="${firstName}" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>اللقب</label>
                                                        <input type="text" name="lastName" value="${lastName}" required>
                                                    </div>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group">
                                                        <label>اسم المستخدم</label>
                                                        <input type="text" name="username" value="${username}" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>كلمة المرور</label>
                                                        <input type="text" name="password" value="${password}" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>الصلاحيات</label>
                                                    <div class="permissions-container" id="permissionsContainer-${teacher.teacher_id}">
                                                        ${permissionsHtml}
                                                    </div>
                                                </div>
                                                <div class="form-actions">
                                                    <button type="button" class="cancel-btn">إلغاء</button>
                                                    <button type="submit" class="save-btn">حفظ</button>
                                                </div>
                                            </form>
                                        </div>
                                    `);
                                    teachersList.append(li);
                                });
                            }
                            fetchStats();
                        },
                        error: function() {
                            showStatus('حدث خطأ أثناء تحميل بيانات الأساتذة.', 'error');
                        }
                    });
                }

                function fetchStats() {
                    $.ajax({
                        url: 'management.php?api=getStats',
                        method: 'GET',
                        dataType: 'json',
                        success: function(result) {
                            totalTeachersStat.text(result.totalTeachers);
                        }
                    });
                }

                // إصلاح مشكلة زر التعديل
                teachersList.on('click', '.edit-btn', function() {
                    const editFields = $(this).closest('li').find('.edit-fields');
                    if (editFields.hasClass('show')) {
                        editFields.removeClass('show');
                    } else {
                        $('.edit-fields.show').removeClass('show');
                        editFields.addClass('show');
                    }
                });

                teachersList.on('click', '.cancel-btn', function() {
                    $(this).closest('.edit-fields').removeClass('show');
                });

                teachersList.on('click', '.remove-permission-btn', function() {
                    $(this).closest('.permission-item').remove();
                });

                teachersList.on('submit', '.edit-form', function(event) {
                    event.preventDefault();
                    const form = $(this);
                    const teacherId = form.data('id');
                    const fullName = form.find('input[name="firstName"]').val() + ' ' + form.find('input[name="lastName"]').val();
                    const username = form.find('input[name="username"]').val();
                    const password = form.find('input[name="password"]').val();
                    
                    const permissionsContainer = form.find('.permissions-container');
                    const newPermissions = permissionsContainer.find('.permission-item').map(function() {
                        const permissionItem = $(this);
                        const fields = permissionItem.find('input[data-key]').map(function() {
                            const key = $(this).data('key');
                            const value = $(this).val();
                            return `${key}: ${value}`;
                        }).get().join(' / ');
                        return fields;
                    }).get().join(' | ');

                    $.ajax({
                        url: 'management.php?api=updateTeacher',
                        method: 'POST',
                        data: {
                            id: teacherId,
                            fullName: fullName,
                            username: username,
                            password: password,
                            permission: newPermissions
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showStatus('تم تحديث بيانات المدرس بنجاح.');
                                form.closest('.edit-fields').removeClass('show');
                                fetchAndRenderTeachers();
                            } else {
                                showStatus('فشل التحديث: ' + response.message, 'error');
                            }
                        },
                        error: function() {
                            showStatus('حدث خطأ أثناء التحديث.', 'error');
                        }
                    });
                });

                teachersList.on('click', '.delete-btn', function() {
                    if (confirm('هل أنت متأكد من حذف هذا المدرس؟')) {
                        const teacherId = $(this).closest('li').data('id');
                        $.ajax({
                            url: 'management.php?api=deleteTeacher',
                            method: 'POST',
                            data: { id: teacherId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showStatus('تم حذف المدرس بنجاح.');
                                    fetchAndRenderTeachers();
                                } else {
                                    showStatus('فشل الحذف.', 'error');
                                }
                            }
                        });
                    }
                });

                // ----------------------------------------------------
                //  منطق إضافة صلاحيات المدرس الجديدة (الواجهة الديناميكية)
                // ----------------------------------------------------

                function updatePermissionCount() {
                    permissionCountSpan.text(permissionCreatorContent.children().length);
                }

                function createPermissionForm() {
                    const newForm = $('<div>').addClass('permission-form');
                    
                    const yearSelect = $('<select>').attr('name', 'year').append('<option value="">اختر السنة</option><option value="اول ثانوي">اول ثانوي</option><option value="ثاني ثانوي">ثاني ثانوي</option><option value="ثالث ثانوي">ثالث ثانوي</option>');
                    const branchDiv = $('<div>').addClass('branch-group').hide().append('<label>الفرع</label>').append($('<select>').attr('name', 'branch').prop('disabled', true).append('<option value="">اختر الفرع</option>'));
                    const partDiv = $('<div>').addClass('part-group').hide().append('<label>الشعبة</label>').append($('<select>').attr('name', 'part').prop('disabled', true).append('<option value="">اختر الشعبة</option>'));
                    const subjectDiv = $('<div>').addClass('subject-group').hide().append('<label>المادة</label>').append($('<select>').attr('name', 'subject').prop('disabled', true).append('<option value="">اختر المادة</option>'));
                    const classesDiv = $('<div>').addClass('classes-group').hide().append('<label>القسم</label>').append($('<div>').addClass('checkbox-group'));
                    
                    const removeBtn = $('<button>').attr('type', 'button').text('إزالة').addClass('remove-btn');

                    newForm.append(
                        $('<div>').addClass('permission-inputs-row').append(
                            $('<div>').append('<label>السنة</label>').append(yearSelect),
                            branchDiv,
                            partDiv,
                            subjectDiv
                        ),
                        classesDiv,
                        removeBtn
                    );
                    
                    // إخفاء حقول الشعبة والمادة والقسم عند إنشاء النموذج
                    partDiv.hide();
                    subjectDiv.hide();
                    classesDiv.hide();


                    yearSelect.on('change', function() {
                        const year = $(this).val();
                        branchDiv.hide().find('select').empty().prop('disabled', true).append('<option value="">اختر الفرع</option>');
                        partDiv.hide().find('select').empty().prop('disabled', true).append('<option value="">اختر الشعبة</option>');
                        subjectDiv.hide().find('select').empty().prop('disabled', true).append('<option value="">اختر المادة</option>');
                        classesDiv.hide().find('.checkbox-group').empty();
                        
                        if (year) {
                            branchDiv.show().find('select').prop('disabled', false);
                            if (year === 'اول ثانوي') {
                                ['علمي', 'ادبي'].forEach(b => branchDiv.find('select').append(`<option value="${b}">${b}</option>`));
                            } else {
                                ['علمي', 'ادبي'].forEach(b => branchDiv.find('select').append(`<option value="${b}">${b}</option>`));
                            }
                        }
                    });

                    branchDiv.find('select').on('change', function() {
                        const year = yearSelect.val();
                        const branch = $(this).val();
                        
                        partDiv.hide().find('select').empty().prop('disabled', true).append('<option value="">اختر الشعبة</option>');
                        subjectDiv.hide().find('select').empty().prop('disabled', true).append('<option value="">اختر المادة</option>');
                        classesDiv.hide().find('.checkbox-group').empty();

                        if (branch) {
                            if (year === 'اول ثانوي') {
                                subjectDiv.show().find('select').prop('disabled', false);
                                subjects[branch].forEach(s => subjectDiv.find('select').append(`<option value="${s}">${s}</option>`));
                            } else {
                                partDiv.show().find('select').prop('disabled', false);
                                branches[branch].forEach(p => partDiv.find('select').append(`<option value="${p}">${p}</option>`));
                            }
                        }
                    });

                    partDiv.find('select').on('change', function() {
                        const part = $(this).val();
                        subjectDiv.hide().find('select').empty().prop('disabled', true).append('<option value="">اختر المادة</option>');
                        classesDiv.hide().find('.checkbox-group').empty();
                        
                        if (part) {
                            subjectDiv.show().find('select').prop('disabled', false);
                            subjects[part].forEach(s => subjectDiv.find('select').append(`<option value="${s}">${s}</option>`));
                        }
                    });

                    subjectDiv.find('select').on('change', function() {
                        classesDiv.show().find('.checkbox-group').empty();
                        for (let i = 1; i <= 10; i++) {
                            classesDiv.find('.checkbox-group').append(`
                                <label>
                                    <input type="checkbox" name="class-${i}" value="${i}">
                                    ${i}
                                </label>
                            `);
                        }
                    });

                    removeBtn.on('click', function() {
                        $(this).closest('.permission-form').remove();
                        updatePermissionCount();
                    });

                    return newForm;
                }

                addPermissionBtn.on('click', function() {
                    permissionCreatorContent.append(createPermissionForm());
                    updatePermissionCount();
                });

                addTeacherForm.on('submit', function(event) {
                    event.preventDefault();

                    const permissionsArray = [];
                    let isValid = true;

                    $('.permission-form').each(function() {
                        const year = $(this).find('select[name="year"]').val();
                        const branch = $(this).find('select[name="branch"]').val();
                        const subject = $(this).find('select[name="subject"]').val();
                        const part = $(this).find('select[name="part"]').val();
                        const classes = $(this).find('input[type="checkbox"]:checked').map(function() {
                            return $(this).val();
                        }).get().join(',');

                        if (!year || !branch || !subject || !classes) {
                            isValid = false;
                            showStatus('الرجاء إكمال جميع الحقول في كل الصلاحيات.', 'error');
                            return false;
                        }

                        let permissionString = `Year: ${year} / branch: ${branch}`;
                        if (year !== 'اول ثانوي' && part) {
                           permissionString += ` / part: ${part}`;
                        }
                        permissionString += ` / subject: ${subject} / classes: ${classes}`;
                        
                        permissionsArray.push(permissionString);
                    });

                    if (!isValid) return;

                    const allPermissionsString = permissionsArray.join(' | ');
                    $('#permissionInput').val(allPermissionsString);

                    const formData = new FormData(this);
                    
                    $.ajax({
                        url: 'management.php?api=addTeacher',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showStatus('تم إضافة المدرس بنجاح.');
                                addTeacherForm[0].reset();
                                permissionCreatorContent.empty();
                                updatePermissionCount();
                                fetchAndRenderTeachers();
                            } else {
                                showStatus('فشل إضافة المدرس. قد يكون اسم المستخدم موجودًا بالفعل.', 'error');
                            }
                        }
                    });
                });
                
                // ----------------------------------------------------
                //  منطق رفع دفعة مدرسين (الجديد)
                // ----------------------------------------------------
                uploadTeachersToggle.on('click', () => {
                    uploadTeachersForm.slideToggle();
                    infoButtonContainer.slideToggle();
                });
                
                fileInput.on('change', function() {
                    const fileName = $(this).val().split('\\').pop();
                    if (fileName) {
                        fileInputLabel.text(fileName);
                    } else {
                        fileInputLabel.text('اختر ملف CSV');
                    }
                });

                uploadTeachersForm.on('submit', function(event) {
                    event.preventDefault();
                    
                    const file = fileInput[0].files[0];
                    if (!file) {
                        showStatus('الرجاء اختيار ملف CSV.', 'error');
                        return;
                    }

                    uploadBtn.addClass('loading');
                    
                    Papa.parse(file, {
                        header: false,
                        complete: function(results) {
                            const teachersData = results.data;
                            if (teachersData.length <= 1) { // Check for header only or empty file
                                showStatus('الملف فارغ أو يحتوي على ترويسة فقط.', 'error');
                                uploadBtn.removeClass('loading');
                                return;
                            }
                            
                            // Remove header row
                            teachersData.shift();

                            // Send parsed data to the server
                            $.ajax({
                                url: 'management.php?api=uploadTeachers',
                                method: 'POST',
                                contentType: 'application/json',
                                data: JSON.stringify({ teachers: teachersData }),
                                dataType: 'json',
                                success: function(response) {
                                    uploadBtn.removeClass('loading');
                                    if (response && response.success) {
                                        showStatus(response.message, 'success');
                                        fetchAndRenderTeachers();
                                    } else {
                                        let errorMessage = (response && response.message) ? response.message : 'حدث خطأ غير متوقع.';
                                        if (response && response.errors && response.errors.length > 0) {
                                            errorMessage += '<br>' + response.errors.join('<br>');
                                        }
                                        showStatus(errorMessage, 'error');
                                    }
                                },
                                error: function() {
                                    uploadBtn.removeClass('loading');
                                    showStatus('حدث خطأ أثناء إرسال البيانات إلى الخادم.', 'error');
                                }
                            });
                        },
                        error: function(err, file) {
                            uploadBtn.removeClass('loading');
                            showStatus('فشل قراءة الملف: ' + err, 'error');
                        }
                    });
                });


                // ----------------------------------------------------
                //  إدارة عرض الصفحات والنافذة المنبثقة
                // ----------------------------------------------------

                cards.on('click', function() {
                    const pageName = $(this).data('page');
                    showSubPage(pageName + '-page');
                });
                
                addTeacherToggle.on('click', () => {
                    addTeacherForm.slideToggle();
                });
                
                showInfoBtn.on('click', () => {
                    infoPopup.css('display', 'flex');
                });
                
                window.closePopup = function() {
                    infoPopup.css('display', 'none');
                };

                function showSubPage(pageId) {
                    const targetPage = $('#' + pageId);
                    if (targetPage.length) {
                        mainHeader.hide();
                        managementCards.hide();
                        subPages.removeClass('active');
                        targetPage.addClass('active');
                        if (pageId === 'teachers-page') {
                            fetchAndRenderTeachers();
                        }
                    }
                }
            });

            function goBack() {
                const subPages = $('.sub-page');
                const mainHeader = $('#mainHeader');
                const managementCards = $('#managementCards');
                subPages.removeClass('active');
                mainHeader.show();
                managementCards.css('display', 'grid');
            }
        }
    </script>
<script>
const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;
if (window.jQuery) $.ajaxSetup({headers: {'X-CSRF-Token': csrfToken}});
document.querySelectorAll('form').forEach(form => {
    const token=document.createElement('input');token.type='hidden';token.name='csrf_token';token.value=csrfToken;form.appendChild(token);
});
</script>
</body>
</html>