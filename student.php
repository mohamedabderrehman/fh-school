<?php
session_start();

// معالج تسجيل الخروج
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    session_destroy();
    header('Location: student.php');
    exit;
}

// دالة للتحقق من بيانات تسجيل الدخول بمرونة
function authenticateUser($username, $password, $grade) {
    $databases = [];
    switch ($grade) {
        case '1':
            $databases = [
                'data/1/L/database.sqlite',
                'data/1/S/database.sqlite'
            ];
            break;
        case '2':
            $databases = [
                'data/2/L/LANG/database.sqlite',
                'data/2/L/PH&L/database.sqlite',
                'data/2/S/SC/database.sqlite',
                'data/2/S/TECH/database.sqlite',
                'data/2/S/ECO/database.sqlite',
                'data/2/S/MAT/database.sqlite'
            ];
            break;
        case '3':
            $databases = [
                'data/3/L/LANG/database.sqlite',
                'data/3/L/PH&L/database.sqlite',
                'data/3/S/SC/database.sqlite',
                'data/3/S/MAT/database.sqlite',
                'data/3/S/TECH/database.sqlite',
                'data/3/S/ECO/database.sqlite'
            ];
            break;
    }
    
    $username_password_combo = $username . ':' . $password;
    
    foreach ($databases as $dbPath) {
        if (!file_exists($dbPath)) {
            continue;
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // البحث ديناميكيًا عن جميع جداول المستخدم
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                // تجاهل جداول النظام
                if (str_starts_with($table, 'sqlite_')) {
                    continue;
                }
                
                $sql = "SELECT student_id, full_name, class_name FROM " . $table . " WHERE username_password = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username_password_combo]);
                $student = $stmt->fetch();
                
                if ($student) {
                    return ['student' => $student, 'db_path' => $dbPath, 'table_name' => $table];
                }
            }
        } catch (PDOException $e) {
            continue;
        }
    }
    
    return false;
}

// دالة لحساب عدد الغيابات من حقل absence_details باستخدام كلمة "يوم"
function calculateAbsenceCount($absence_details) {
    if (empty($absence_details) || trim($absence_details) === '') {
        return 0;
    }
    
    // حساب عدد مرات ظهور كلمة "يوم"
    return substr_count($absence_details, 'يوم');
}

// متغيرات للرسائل والأخطاء
$error_message = '';
$show_dashboard = false;
$student_data = null;

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['student_id'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $grade = $_POST['grade'] ?? '';

    if (!empty($username) && !empty($password) && !empty($grade)) {
        $auth_result = authenticateUser($username, $password, $grade);
        
        if ($auth_result) {
            $_SESSION['student_id'] = $auth_result['student']['student_id'];
            $_SESSION['full_name'] = $auth_result['student']['full_name'];
            $_SESSION['class_name'] = $auth_result['student']['class_name'];
            $_SESSION['db_path'] = $auth_result['db_path'];
            $_SESSION['table_name'] = $auth_result['table_name'];
            $_SESSION['grade'] = $grade;
            
            header('Location: student.php');
            exit;
        } else {
            $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة أو الشعبة غير مطابقة.';
        }
    } else {
        $error_message = 'الرجاء إدخال جميع البيانات المطلوبة.';
    }
}

// إذا كان المستخدم مسجل الدخول بالفعل، اعرض لوحة التحكم
if (isset($_SESSION['student_id'])) {
    $show_dashboard = true;
    try {
        $pdo = new PDO("sqlite:" . $_SESSION['db_path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        $table = $_SESSION['table_name'];
        if (str_starts_with($table, 'sqlite_')) {
            throw new Exception("اسم جدول غير صالح.");
        }

        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE student_id = ?");
        $stmt->execute([$_SESSION['student_id']]);
        $student_data = $stmt->fetch();

        if (!$student_data) {
             throw new Exception("لم يتم العثور على بيانات التلميذ.");
        }

    } catch (Exception $e) {
        $error_message = 'خطأ في جلب بيانات التلميذ: ' . $e->getMessage();
        $show_dashboard = false;
    }
}

// إعداد بيانات لوحة التحكم
if ($show_dashboard && $student_data) {
    $full_name = $_SESSION['full_name'];
    $class_name = $_SESSION['class_name'];
    $display_class_name = $class_name;
    
    $name_parts = explode(' ', $full_name, 2);
    $first_name = $name_parts[0];
    $last_name = $name_parts[1] ?? '';
    
    // *** هذا هو الجزء المعدل لمعالجة الملاحظات باستخدام الفاصل | ***
    $notes_string = $student_data['note'] ?? '';
    
    // تقسيم الملاحظات باستخدام الفاصل |
    $notes_array = array_filter(
        array_map(
            'trim',
            explode('|', $notes_string)
        ),
        'strlen'
    );
    
    // إزالة الملاحظات المكررة
    $notes_array = array_unique($notes_array);
    
    if (empty($notes_array)) {
        $notes_array = ['لا توجد ملاحظات'];
    }
    
    // حساب عدد الغيابات من حقل absence_details
    $absence_count = calculateAbsenceCount($student_data['absence_details'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $show_dashboard ? 'لوحة تحكم التلميذ' : 'تسجيل الدخول'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #e74c3c;
            --primary-purple: #8e44ad;
            --primary-brown: #a0522d;
            --accent-yellow: #f39c12;
            --neutral-light: #f8f9fa;
            --neutral-dark: #2c3e50;
            --neutral-gray: #6c757d;
            --success-green: #27ae60;
            --warning-orange: #e67e22;
            --info-blue: #3498db;
            --white: #ffffff;
            --shadow-light: 0 2px 10px rgba(0,0,0,0.1);
            --shadow-medium: 0 4px 20px rgba(0,0,0,0.15);
            --shadow-heavy: 0 8px 30px rgba(0,0,0,0.2);
            --border-radius: 12px;
            --border-radius-small: 8px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--neutral-dark);
            line-height: 1.6;
        }

        /* Login Page Styles */
        <?php if (!$show_dashboard): ?>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-heavy);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-red), var(--primary-purple), var(--primary-brown));
        }

        .login-header {
            margin-bottom: 30px;
        }

        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-red), var(--primary-purple));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--white);
            font-size: 2em;
            min-width: 80px; /* Prevents shrinking on small screens */
            flex-shrink: 0; /* Prevents shrinking on small screens */
        }

        .login-title {
            font-size: 2.2em;
            font-weight: 700;
            color: var(--neutral-dark);
            margin-bottom: 10px;
        }

        .login-subtitle {
            color: var(--neutral-gray);
            font-size: 1.1em;
        }

        .grade-selection {
            margin-bottom: 25px;
        }

        .grade-selection label {
            display: block;
            margin-bottom: 15px;
            color: var(--primary-purple);
            font-weight: 600;
            font-size: 1.1em;
        }

        .grade-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .grade-btn {
            padding: 15px 10px;
            border: 2px solid var(--primary-purple);
            background: transparent;
            color: var(--primary-purple);
            border-radius: var(--border-radius-small);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-family: 'Tajawal', sans-serif;
            min-height: 44px; /* Touch-friendly minimum height */
        }

        .grade-btn:hover, .grade-btn.active {
            background: var(--primary-purple);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .form-group {
            margin-bottom: 20px;
            text-align: right;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--neutral-dark);
            font-weight: 600;
            font-size: 1em;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius-small);
            font-size: 1em;
            font-family: 'Tajawal', sans-serif;
            transition: var(--transition);
            background: var(--white);
            min-height: 44px; /* Touch-friendly minimum height */
            font-size: 16px; /* Prevents zoom on iOS */
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.1);
            transform: translateY(-1px);
        }

        .login-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary-red), var(--primary-purple));
            color: var(--white);
            border: none;
            border-radius: var(--border-radius-small);
            cursor: pointer;
            font-size: 1.2em;
            font-weight: 700;
            transition: var(--transition);
            font-family: 'Tajawal', sans-serif;
            min-height: 44px; /* Touch-friendly minimum height */
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            background: linear-gradient(135deg, #c0392b, #7d3c98);
        }

        .error {
            background: linear-gradient(135deg, var(--primary-red), #c0392b);
            color: var(--white);
            padding: 15px 20px;
            border-radius: var(--border-radius-small);
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
            box-shadow: var(--shadow-light);
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 15px;
            }
            
            .login-card {
                padding: 25px 15px;
                max-width: 100%;
            }
            
            .grade-buttons {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .grade-btn {
                padding: 12px 8px;
                font-size: 0.9em;
            }
            
            .login-title {
                font-size: 1.6em;
            }
            
            .login-subtitle {
                font-size: 1em;
            }
            
            .form-group input {
                padding: 12px 15px;
                font-size: 0.9em;
            }
            
            .login-btn {
                padding: 15px;
                font-size: 1.1em;
            }
        }
        <?php endif; ?>

        /* Dashboard Styles */
        <?php if ($show_dashboard): ?>
        .dashboard-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 10px;
            max-width: 100%;
            margin: 0 auto;
        }

        .dashboard-header {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-medium);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .student-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-red), var(--primary-purple));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 2em;
            font-weight: 700;
            box-shadow: var(--shadow-medium);
            min-width: 80px; /* Prevents shrinking on small screens */
            flex-shrink: 0; /* Prevents shrinking on small screens */
        }

        .student-details h1 {
            color: var(--neutral-dark);
            font-size: 2.2em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .student-details .class-info {
            color: var(--primary-purple);
            font-size: 1.2em;
            font-weight: 600;
            background: linear-gradient(135deg, rgba(142, 68, 173, 0.1), rgba(231, 76, 60, 0.1));
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, var(--primary-red), #c0392b);
            color: var(--white);
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius-small);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            min-height: 44px; /* Touch-friendly minimum height */
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            background: linear-gradient(135deg, #c0392b, #a93226);
        }

        .dashboard-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border-right: 4px solid var(--primary-purple);
            position: relative;
            overflow: hidden;
        }

        .info-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .info-card:hover::before {
            left: 100%;
        }

        /* Loading skeleton animation */
        .skeleton-loader {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Pulse effect for important elements */
        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(142, 68, 173, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(142, 68, 173, 0); }
            100% { box-shadow: 0 0 0 0 rgba(142, 68, 173, 0); }
        }

        .info-card .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-card .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5em;
            min-width: 50px; /* Prevents shrinking on small screens */
            flex-shrink: 0; /* Prevents shrinking on small screens */
        }

        .info-card .card-icon.red { background: var(--primary-red); }
        .info-card .card-icon.purple { background: var(--primary-purple); }
        .info-card .card-icon.brown { background: var(--primary-brown); }
        .info-card .card-icon.yellow { background: var(--accent-yellow); }

        .info-card .card-title {
            font-size: 1.3em;
            font-weight: 600;
            color: var(--neutral-dark);
        }

        .info-card .card-value {
            font-size: 2.5em;
            font-weight: 800;
            color: var(--primary-purple);
            margin-bottom: 10px;
        }

        .info-card .card-description {
            color: var(--neutral-gray);
            font-size: 0.95em;
        }

        .notes-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow-medium);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--neutral-dark);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .section-title i {
            color: var(--primary-purple);
            min-width: 24px; /* Prevents shrinking on small screens */
            flex-shrink: 0; /* Prevents shrinking on small screens */
        }



        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .note-card {
            background: linear-gradient(135deg, rgba(142, 68, 173, 0.05), rgba(231, 76, 60, 0.05));
            border: 1px solid rgba(142, 68, 173, 0.2);
            border-radius: var(--border-radius-small);
            padding: 20px;
            transition: var(--transition);
            cursor: pointer;
        }

        .note-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-light);
            border-color: var(--primary-purple);
        }

        .note-card .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .note-card .note-icon {
            color: var(--primary-purple);
            font-size: 1.2em;
            min-width: 20px; /* Prevents shrinking on small screens */
            flex-shrink: 0; /* Prevents shrinking on small screens */
        }

        .note-card .note-content {
            color: var(--neutral-dark);
            line-height: 1.6;
            font-size: 1em;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .grades-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow-medium);
            margin-bottom: 30px;
        }

        .grades-table-container {
            overflow-x: auto;
            border-radius: var(--border-radius-small);
            box-shadow: var(--shadow-light);
            position: relative;
        }

        .table-responsive-wrapper {
            position: relative;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-purple) transparent;
        }

        .table-responsive-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive-wrapper::-webkit-scrollbar-track {
            background: transparent;
        }

        .table-responsive-wrapper::-webkit-scrollbar-thumb {
            background: var(--primary-purple);
            border-radius: 4px;
        }

        .table-responsive-wrapper::-webkit-scrollbar-thumb:hover {
            background: var(--primary-red);
        }

        .subject-header {
            position: sticky;
            left: 0;
            background: linear-gradient(135deg, var(--primary-purple), var(--primary-red));
            z-index: 10;
        }

        .term-header {
            background: linear-gradient(135deg, var(--primary-purple), var(--primary-red));
            color: var(--white);
            text-align: center;
            font-weight: 600;
            padding: 15px 10px;
            white-space: nowrap;
        }

        .grade-type {
            background: rgba(142, 68, 173, 0.9);
            color: var(--white);
            text-align: center;
            font-weight: 600;
            padding: 12px 8px;
            white-space: nowrap;
        }

        .grades-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 100%;
            background: var(--white);
        }

        .grades-table th {
            background: linear-gradient(135deg, var(--primary-purple), var(--primary-red));
            color: var(--white);
            padding: 18px 15px;
            text-align: center;
            font-weight: 600;
            font-size: 1em;
            white-space: nowrap;
        }

        .grades-table th:first-child {
            text-align: right;
            border-radius: var(--border-radius-small) 0 0 0;
        }

        .grades-table th:last-child {
            border-radius: 0 var(--border-radius-small) 0 0;
        }

        .grades-table td {
            border: 1px solid #e9ecef;
            padding: 15px;
            text-align: center;
            transition: var(--transition);
        }

        .grades-table tbody tr:nth-child(even) {
            background-color: rgba(142, 68, 173, 0.02);
        }

        .grades-table tbody tr:hover {
            background-color: rgba(142, 68, 173, 0.05);
            transform: scale(1.01);
        }

        .grades-table td.subject-name {
            text-align: right;
            font-weight: 600;
            color: var(--primary-purple);
            background: linear-gradient(135deg, rgba(142, 68, 173, 0.1), rgba(231, 76, 60, 0.1));
        }

        .grade-value {
            font-weight: 600;
            color: var(--neutral-dark);
            padding: 8px 12px;
            border-radius: 6px;
            background: rgba(142, 68, 173, 0.1);
            display: inline-block;
            min-width: 40px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .grade-value:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(142, 68, 173, 0.3);
        }

        .grade-value.empty {
            color: var(--neutral-gray);
            background: rgba(108, 117, 125, 0.1);
        }

        /* Grade value color coding */
        .grade-value.excellent {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .grade-value.good {
            background: linear-gradient(135deg, #3498db, #5dade2);
            color: white;
        }

        .grade-value.average {
            background: linear-gradient(135deg, #f39c12, #f4d03f);
            color: white;
        }

        .grade-value.poor {
            background: linear-gradient(135deg, #e74c3c, #ec7063);
            color: white;
        }

        /* Animated counter for statistics */
        .animated-counter {
            display: inline-block;
            transition: transform 0.3s ease;
        }

        .animated-counter:hover {
            transform: scale(1.1);
        }



        /* Notification badge */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, var(--primary-red), #c0392b);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: 700;
            animation: pulse 2s infinite;
        }

        .absences-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow-medium);
            margin-bottom: 30px;
        }

        .filter-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 220px;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius-small);
            font-family: 'Tajawal', sans-serif;
            transition: var(--transition);
            min-height: 44px; /* Touch-friendly minimum height */
            font-size: 16px; /* Prevents zoom on iOS */
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.1);
        }

        .sort-btn {
            padding: 10px 14px;
            background: linear-gradient(135deg, var(--primary-purple), var(--primary-red));
            color: var(--white);
            border: none;
            border-radius: var(--border-radius-small);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            min-height: 44px; /* Touch-friendly minimum height */
            white-space: nowrap;
        }

        .sort-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-light);
        }

        .absences-table td {
            text-align: right;
        }

        .absence-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            background: rgba(231, 76, 60, 0.1);
            color: var(--primary-red);
            font-size: 0.85em;
            margin-right: 8px;
        }

        .accordion {
            border-radius: var(--border-radius-small);
            overflow: hidden;
        }

        .accordion-item {
            border: 1px solid #e9ecef;
            border-radius: var(--border-radius-small);
            margin-bottom: 10px;
            background: var(--white);
            box-shadow: var(--shadow-light);
        }

        .accordion-header {
            width: 100%;
            text-align: right;
            background: linear-gradient(135deg, rgba(142, 68, 173, 0.06), rgba(231, 76, 60, 0.06));
            border: none;
            padding: 14px 18px;
            font-weight: 700;
            color: var(--neutral-dark);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: var(--transition);
            font-family: 'Tajawal', sans-serif;
            flex-wrap: wrap;
            gap: 10px;
        }

        .accordion-header span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .accordion-header:hover {
            background: linear-gradient(135deg, rgba(142, 68, 173, 0.12), rgba(231, 76, 60, 0.12));
        }

        .accordion-content {
            display: none;
            padding: 16px 18px;
            color: var(--neutral-dark);
            background: var(--white);
        }

        .accordion-header i {
            color: var(--primary-purple);
            min-width: 16px; /* Prevents shrinking on small screens */
            flex-shrink: 0; /* Prevents shrinking on small screens */
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 30px;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow-heavy);
            position: relative;
            animation: slideIn 0.4s ease-out;
        }

        @media (max-width: 600px) {
            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 20px;
            }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close-btn {
            color: var(--neutral-gray);
            position: absolute;
            top: 15px;
            left: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-btn:hover {
            color: var(--primary-red);
        }
        
        .note-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
            max-height: 60vh;
            overflow-y: auto;
        }

        .note-list li {
            background: linear-gradient(135deg, rgba(142, 68, 173, 0.05), rgba(231, 76, 60, 0.05));
            padding: 20px;
            border-radius: var(--border-radius-small);
            margin-bottom: 15px;
            border-right: 4px solid var(--primary-purple);
            transition: var(--transition);
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .note-list li:hover {
            transform: translateX(-5px);
            box-shadow: var(--shadow-light);
        }

        .note-list li:last-child {
            margin-bottom: 0;
        }

        /* Mobile-first responsive design */
        @media (max-width: 1200px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .dashboard-content {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 10px;
            }
            
            .dashboard-header {
                flex-direction: column;
                text-align: center;
                padding: 20px;
                gap: 15px;
            }
            
            .student-info {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .student-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5em;
            }
            
            .student-details h1 {
                font-size: 1.8em;
            }
            
            .dashboard-content {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .info-card {
                padding: 20px;
            }
            
            .info-card .card-value {
                font-size: 2em;
            }
            
            .grades-section,
            .absences-section,
            .notes-section {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .section-title {
                font-size: 1.5em;
                margin-bottom: 20px;
            }
            
            .grades-table th,
            .grades-table td {
                padding: 10px 8px;
                font-size: 0.9em;
            }
            
            .grade-value {
                padding: 6px 8px;
                min-width: 35px;
                font-size: 0.9em;
            }
            
            .filter-controls {
                flex-direction: column;
                gap: 10px;
            }
            
            .search-input {
                min-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 5px;
            }
            
            .dashboard-header {
                padding: 15px;
            }
            
            .student-details h1 {
                font-size: 1.5em;
            }
            
            .info-card {
                padding: 15px;
            }
            
            .info-card .card-value {
                font-size: 1.8em;
            }
            
            .grades-section,
            .absences-section,
            .notes-section {
                padding: 15px;
            }
            
            .section-title {
                font-size: 1.3em;
            }
            
            .grades-table th,
            .grades-table td {
                padding: 8px 6px;
                font-size: 0.8em;
            }
            
            .grade-value {
                padding: 4px 6px;
                min-width: 30px;
                font-size: 0.8em;
            }
            
            .modal-content {
                width: 95%;
                padding: 20px;
                margin: 10% auto;
            }
        }

        /* Phone-specific table improvements */
        @media (max-width: 600px) {
            .grades-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: var(--border-radius-small);
                box-shadow: var(--shadow-light);
            }
            
            .grades-table {
                min-width: 600px; /* Minimum width for readability */
            }
            
            .grades-table th,
            .grades-table td {
                white-space: nowrap;
                min-width: 80px;
            }
            
            .grades-table th:first-child {
                min-width: 120px; /* Subject name column */
            }
            
            /* Stack grade columns for very small screens */
            .grades-table th[rowspan="2"] {
                min-width: 100px;
            }
            
            .grade-value {
                display: block;
                text-align: center;
                margin: 2px 0;
            }
        }
        <?php endif; ?>
    </style>
</head>
<body>

<?php if (!$show_dashboard): ?>
    <div class="login-container">
        <div class="login-card">
        <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h1 class="login-title">تسجيل الدخول</h1>
                <p class="login-subtitle">منطقة تسجيل الدخول للتلاميذ</p>
        </div>

        <?php if (!empty($error_message)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
        <?php endif; ?>

        <form method="POST" action="student.php">
            <div class="grade-selection">
                <label>اختر المستوى الدراسي:</label>
                <div class="grade-buttons">
                        <button type="button" class="grade-btn" data-grade="1">
                            <i class="fas fa-star"></i> أول ثانوي
                        </button>
                        <button type="button" class="grade-btn" data-grade="2">
                            <i class="fas fa-star"></i> ثاني ثانوي
                        </button>
                        <button type="button" class="grade-btn" data-grade="3">
                            <i class="fas fa-star"></i> ثالث ثانوي
                        </button>
                </div>
                <input type="hidden" name="grade" id="selectedGrade" required>
            </div>

            <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> اسم المستخدم
                    </label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> كلمة المرور
                    </label>
                <input type="password" id="password" name="password" required>
            </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> دخول
                </button>
        </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.grade-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.grade-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('selectedGrade').value = this.dataset.grade;
            });
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            if (!document.getElementById('selectedGrade').value) {
                e.preventDefault();
                alert('الرجاء اختيار المستوى الدراسي');
            }
        });
    </script>

<?php else: ?>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="student-info">
                <div class="student-avatar">
                    <?php echo strtoupper(substr($first_name, 0, 1)); ?>
                </div>
                <div class="student-details">
                <h1>أهلاً بك، <?php echo htmlspecialchars($first_name); ?></h1>
                    <div class="class-info">
                        <i class="fas fa-school"></i>
                        <?php echo htmlspecialchars($display_class_name); ?>
            </div>
                </div>
            </div>
            <a href="student.php?action=logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                تسجيل خروج
            </a>
        </div>
        
        <div class="dashboard-content">
            <div class="info-card">
                <div class="card-header">
                    <div class="card-icon red">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="card-title">الاسم الكامل</div>
                </div>
                <div class="card-value animated-counter"><?php echo htmlspecialchars($first_name); ?></div>
                <div class="card-description">الاسم الأول</div>
            </div>

            <div class="info-card">
                <div class="card-header">
                    <div class="card-icon purple">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="card-title">اللقب</div>
                </div>
                <div class="card-value animated-counter"><?php echo htmlspecialchars($last_name); ?></div>
                <div class="card-description">اسم العائلة</div>
            </div>

            <div class="info-card">
                <div class="card-header">
                    <div class="card-icon brown">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="card-title">عدد الغيابات</div>
                </div>
                <div class="card-value animated-counter" data-count="<?php echo $absence_count; ?>">0</div>
                <div class="card-description">إجمالي أيام الغياب</div>
            </div>

            <div class="info-card" style="position: relative;">
                <div class="card-header">
                    <div class="card-icon yellow">
                        <i class="fas fa-sticky-note"></i>
                        <?php if (count($notes_array) > 0 && trim($notes_array[0]) != 'لا توجد ملاحظات'): ?>
                            <div class="notification-badge"><?php echo count($notes_array); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="card-title">الملاحظات</div>
                </div>
                <div class="card-value animated-counter" data-count="<?php echo count($notes_array); ?>">0</div>
                <div class="card-description">
                <?php if (!empty($notes_array) && trim($notes_array[0]) != 'لا توجد ملاحظات'): ?>
                        <button onclick="openModal()" class="logout-btn" style="background: linear-gradient(135deg, var(--primary-purple), var(--primary-red)); margin-top: 10px; padding: 8px 16px; font-size: 0.9em;">
                            <i class="fas fa-eye"></i> عرض الملاحظات
                        </button>
                <?php else: ?>
                        لا توجد ملاحظات حاليًا
                <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grades-section">
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i>
                الدرجات الأكاديمية
            </h2>
            <div class="grades-table-container">
                <div class="table-responsive-wrapper">
                    <table class="grades-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="subject-header">المادة</th>
                                <th colspan="2" class="term-header">الفصل الأول</th>
                                <th colspan="2" class="term-header">الفصل الثاني</th>
                                <th colspan="2" class="term-header">الفصل الثالث</th>
                            </tr>
                            <tr>
                                <th class="grade-type">فرض</th>
                                <th class="grade-type">اختبار</th>
                                <th class="grade-type">فرض</th>
                                <th class="grade-type">اختبار</th>
                                <th class="grade-type">فرض</th>
                                <th class="grade-type">اختبار</th>
                            </tr>
                        </thead>
                <tbody>
                    <?php
                    $subject_map = [
                        'arabic_language' => 'اللغة العربية',
                        'french_language' => 'اللغة الفرنسية',
                        'english_language' => 'اللغة الإنجليزية',
                        'spanish' => 'اللغة الإسبانية',
                        'german' => 'اللغة الألمانية',
                        'philosophy' => 'الفلسفة',
                        'history_geography' => 'التاريخ والجغرافيا',
                        'islamic_sciences' => 'العلوم الإسلامية',
                        'mathematics' => 'الرياضيات',
                        'natural_sciences' => 'العلوم الطبيعية',
                        'physics_sciences' => 'الفيزياء',
                        'economics' => 'الاقتصاد',
                        'accounting_management' => 'تسيير ومحاسبة',
                        'management_economics' => 'تسيير واقتصاد',
                        'law' => 'القانون',
                        'technology' => 'تكنولوجيا',
                        'computer_science' => 'الإعلام الآلي',
                        'physical_education' => 'التربية البدنية',
                        'amazigh_language' => 'اللغة الأمازيغية',
                    ];
                        
                        function getGradeClass($grade) {
                            if (empty($grade) || $grade === '-' || !is_numeric($grade)) return 'empty';
                            $grade = floatval($grade);
                            if ($grade >= 16) return 'excellent';
                            if ($grade >= 14) return 'good';
                            if ($grade >= 10) return 'average';
                            return 'poor';
                        }
                    
                    foreach ($subject_map as $key => $label) {
                        if (array_key_exists($key . '_term1', $student_data)) {
                            echo '<tr>';
                            echo '<td class="subject-name">' . htmlspecialchars($label) . '</td>';

                            for ($term = 1; $term <= 3; $term++) {
                                $term_key = $key . '_term' . $term;
                                if (isset($student_data[$term_key]) && !empty($student_data[$term_key])) {
                                    $grades = explode(',', $student_data[$term_key]);
                                        $fard = isset($grades[0]) ? trim($grades[0]) : '-';
                                        $ikhtibar = isset($grades[1]) ? trim($grades[1]) : '-';
                                        
                                        $fard_class = getGradeClass($fard);
                                        $ikhtibar_class = getGradeClass($ikhtibar);
                                        
                                        echo '<td><span class="grade-value ' . $fard_class . '">' . htmlspecialchars($fard) . '</span></td>';
                                        echo '<td><span class="grade-value ' . $ikhtibar_class . '">' . htmlspecialchars($ikhtibar) . '</span></td>';
                                } else {
                                        echo '<td><span class="grade-value empty">-</span></td><td><span class="grade-value empty">-</span></td>';
                                }
                            }
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
            </div>
        </div>

        <div class="absences-section" id="absencesSection" data-absences="<?php echo htmlspecialchars($student_data['absence_details'] ?? '', ENT_QUOTES); ?>">
            <h2 class="section-title">
                <i class="fas fa-user-times"></i>
                الغيابات
            </h2>
            <div class="filter-controls">
                <input type="text" id="absenceSearch" class="search-input" placeholder="ابحث في الغيابات...">
                <button type="button" id="sortByDays" class="sort-btn"><i class="fas fa-sort-amount-down"></i> ترتيب حسب الأيام</button>
            </div>
            <div class="table-responsive-wrapper">
                <table class="grades-table absences-table">
                    <thead>
                        <tr>
                            <th class="subject-header">الوصف</th>
                            <th class="grade-type">الأيام</th>
                        </tr>
                    </thead>
                    <tbody id="absencesBody">
                        <tr><td colspan="2" style="text-align:center; color: var(--neutral-gray);">جارٍ التحميل...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="notes-section">
            <h2 class="section-title">
                <i class="fas fa-sticky-note"></i>
                الملاحظات
            </h2>
            <?php if (!empty($notes_array) && trim($notes_array[0]) != 'لا توجد ملاحظات'): ?>
                <div class="accordion" id="notesAccordion">
                    <?php foreach ($notes_array as $index => $note): ?>
                        <div class="accordion-item">
                            <button class="accordion-header">
                                <span><i class="fas fa-chevron-down"></i> ملاحظة رقم <?php echo $index + 1; ?></span>
                                <span style="color: var(--neutral-gray); font-weight: 600;">اليوم: <?php echo date('Y/m/d'); ?></span>
                            </button>
                            <div class="accordion-content">
                                <?php echo htmlspecialchars(trim($note)); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="note-card" style="cursor: default;">
                    <div class="note-header">
                        <i class="fas fa-info-circle note-icon"></i>
                    </div>
                    <div class="note-content">لا توجد ملاحظات حاليًا</div>
                </div>
            <?php endif; ?>
        </div>


    </div>

    <div id="notesModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2 class="section-title">
                <i class="fas fa-sticky-note"></i>
                ملاحظات الإدارة والأساتذة
            </h2>
            <ul class="note-list">
                <?php if (!empty($notes_array) && trim($notes_array[0]) != 'لا توجد ملاحظات'): ?>
                    <?php foreach ($notes_array as $note) : ?>
                        <li>
                            <div class="note-header">
                                <i class="fas fa-comment-dots note-icon"></i>
                                <span class="note-date"><?php echo date('Y/m/d'); ?></span>
                            </div>
                            <div class="note-content"><?php echo htmlspecialchars(trim($note)); ?></div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>
                        <div class="note-header">
                            <i class="fas fa-info-circle note-icon"></i>
                        </div>
                        <div class="note-content">لا توجد ملاحظات حاليًا</div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('notesModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('notesModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('notesModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Enhanced animations and interactions
        document.addEventListener('DOMContentLoaded', function() {


            // Animate cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe all cards and sections
            document.querySelectorAll('.info-card, .grades-section, .notes-section, .absences-section').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });

            // Add loading skeleton effect
            setTimeout(() => {
                document.querySelectorAll('.skeleton-loader').forEach(el => {
                    el.classList.remove('skeleton-loader');
                });
            }, 1000);

            // Animated counters
            const counters = document.querySelectorAll('.animated-counter[data-count]');
            counters.forEach(counter => {
                const target = parseInt(counter.dataset.count);
                const duration = 2000; // 2 seconds
                const increment = target / (duration / 16); // 60fps
                let current = 0;
                
                const updateCounter = () => {
                    if (current < target) {
                        current += increment;
                        counter.textContent = Math.floor(current);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target;
                    }
                };
                
                // Start animation when card comes into view
                setTimeout(() => {
                    updateCounter();
                }, 500);
            });
        });

        // Add hover effects for table rows
        const tableRows = document.querySelectorAll('.grades-table tbody tr');
        tableRows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Accordion behavior for notes
        document.querySelectorAll('.accordion-header').forEach(header => {
            header.addEventListener('click', function() {
                const content = this.nextElementSibling;
                const isOpen = content.style.display === 'block';
                document.querySelectorAll('.accordion-content').forEach(c => c.style.display = 'none');
                if (!isOpen) {
                    content.style.display = 'block';
                }
            });
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                if (document.querySelector('.logout-btn[onclick="openModal()"]')) {
                    openModal();
                }
            }
        });

        // Add smooth scrolling to sections
        function scrollToSection(sectionId) {
            document.getElementById(sectionId).scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        // Add data refresh functionality
        let refreshInterval;
        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                // Simulate data refresh animation
                document.querySelectorAll('.animated-counter[data-count]').forEach(counter => {
                    counter.style.animation = 'pulse 0.5s ease-in-out';
                    setTimeout(() => {
                        counter.style.animation = '';
                    }, 500);
                });
            }, 30000); // Refresh every 30 seconds
        }

        // Start auto refresh
        startAutoRefresh();

        // Build absences table from raw text
        (function buildAbsences() {
            const container = document.getElementById('absencesSection');
            if (!container) return;
            const raw = (container.dataset.absences || '').trim();
            const tbody = document.getElementById('absencesBody');
            const searchInput = document.getElementById('absenceSearch');
            const sortBtn = document.getElementById('sortByDays');

            let records = [];
            if (raw.length > 0) {
                const parts = raw.split(/[\n\r;|،]+/).map(s => s.trim()).filter(Boolean);
                records = parts.map(p => {
                    const match = p.match(/(\d+)\s*يوم/);
                    const days = match ? parseInt(match[1], 10) : 0;
                    return { description: p, days };
                });
            }

            function render(list) {
                if (!list.length) {
                    tbody.innerHTML = '<tr><td colspan="2" style="text-align:center; color: var(--neutral-gray);">لا توجد غيابات</td></tr>';
                    return;
                }
                tbody.innerHTML = list.map(item => `
                    <tr>
                        <td style="text-align:right">${item.description}</td>
                        <td><span class="absence-badge">${item.days} يوم</span></td>
                    </tr>
                `).join('');
            }

            function applyFilterAndSort() {
                const q = (searchInput.value || '').trim();
                let list = records;
                if (q) {
                    const rx = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
                    list = list.filter(r => rx.test(r.description));
                }
                if (sortBtn.dataset.order === 'desc') {
                    list = list.slice().sort((a, b) => b.days - a.days);
                } else if (sortBtn.dataset.order === 'asc') {
                    list = list.slice().sort((a, b) => a.days - b.days);
                }
                render(list);
            }

            sortBtn.addEventListener('click', function() {
                const current = this.dataset.order || 'none';
                this.dataset.order = current === 'none' ? 'desc' : current === 'desc' ? 'asc' : 'none';
                this.innerHTML = this.dataset.order === 'desc'
                    ? '<i class="fas fa-sort-amount-down"></i> ترتيب حسب الأيام (تنازلي)'
                    : this.dataset.order === 'asc'
                        ? '<i class="fas fa-sort-amount-up"></i> ترتيب حسب الأيام (تصاعدي)'
                        : '<i class="fas fa-sort"></i> ترتيب حسب الأيام';
                applyFilterAndSort();
            });

            searchInput.addEventListener('input', applyFilterAndSort);

            applyFilterAndSort();
        })();
    </script>
<?php endif; ?>

</body>
</html>