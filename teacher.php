<?php
session_start();
require_once __DIR__.'/csrf.php';

// --- AJAX HANDLER FOR FETCHING STUDENTS AND GRADES, AND REGISTERING ABSENCE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['teacher_id'])) {
        http_response_code(401); echo json_encode(['success'=>false,'message'=>'Authentication required']); exit;
    }
    // Recheck the stored permission against every requested class and subject.
    $permitted = false;
    foreach (parsePermissions($_SESSION['permission'] ?? '') as $year=>$branches) {
        foreach ($branches as $branch=>$entries) foreach ($entries as $entry) {
            $requestedSubject = $_POST['subject_name'] ?? $_POST['subject'] ?? '';
            if ((string)$year === (string)($_POST['year'] ?? '') && $branch === ($_POST['branch'] ?? '')
                && $entry['part'] === ($_POST['part'] ?? '')
                && in_array((string)($_POST['classNum'] ?? ''), $entry['classes'], true)
                && ($requestedSubject === '' || $entry['subject'] === $requestedSubject)) $permitted = true;
        }
    }
    if (!$permitted) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Class or subject not permitted']); exit; }


    // إعداد خرائط التحويل (كما في الكود السابق)
    $year_map = ['اول ثانوي' => '1', 'ثاني ثانوي' => '2', 'ثالث ثانوي' => '3'];
    $branch_map = ['علمي' => 'S', 'ادبي' => 'L'];
    $part_map = [
        'لغات أجنبية' => 'LANG',
        'آداب وفلسفة' => 'PH&L',
        'تقني رياضي' => 'TECH',
        'العلوم التجريبية' => 'SC',
        'الرياضيات' => 'MAT',
        'تسيير واقتصاد' => 'ECO'
    ];
    $table_map = [
        'لغات أجنبية' => 'foreign_languages_students',
        'آداب وفلسفة' => 'arts_economics_students',
        'تقني رياضي' => 'technical_mathematics_students',
        'العلوم التجريبية' => 'experimental_sciences_students',
        'الرياضيات' => 'mathematics_students',
        'تسيير واقتصاد' => 'management_economics_students',
        'علمي' => 'scientific_students',
        'ادبي' => 'literary_students'
    ];
    // خريطة المواد
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
        'accounting_management' => 'تسيير ومحاسبة',
        'economics' => 'الاقتصاد',
        'law' => 'القانون',
        'technology' => 'تكنولوجيا',
        'computer_science' => 'الإعلام الآلي',
        'physical_education' => 'التربية البدنية',
        'amazigh_language' => 'اللغة الأمازيغية',
    ];

    // دالة مساعدة لجلب البيانات الأساسية (مسار DB واسم الجدول واسم الفصل)
    function getDbInfo($year, $branch, $part, $classNum, $year_map, $branch_map, $part_map, $table_map) {
        if (!preg_match('/^[1-9][0-9]?$/', (string)$classNum)) return ['error'=>'Invalid class'];
        $yearNum = $year_map[$year] ?? null;
        $branchCode = $branch_map[$branch] ?? null;

        if (!$yearNum || !$branchCode) return ['error' => 'بيانات السنة أو الفرع غير صالحة.'];

        $dbPath = "data/";
        $tableName = null;
        $className = "";

        if ($yearNum === '1') {
            $tableName = $table_map[$branch] ?? null;
            $dbPath .= "{$yearNum}/{$branchCode}/database.sqlite";
            $className = "{$yearNum} {$branch} {$classNum}";
        } else {
            $partCode = $part_map[$part] ?? null;
            if (!$partCode) return ['error' => 'بيانات الشعبة غير صالحة.'];
            $tableName = $table_map[$part] ?? null;
            $dbPath .= "{$yearNum}/{$branchCode}/{$partCode}/database.sqlite";
            $className = "{$yearNum} {$part} {$classNum}";
        }

        if (!$tableName) return ['error' => 'لم يتم العثور على اسم جدول مناسب.'];

        // Reject IDs outside the teacher's authorized class before any read/write.
        $ids = isset($_POST['student_ids']) ? json_decode($_POST['student_ids'],true) : [];
        if (isset($_POST['student_id'])) $ids = [$_POST['student_id']];
        if (!is_array($ids) || count($ids)>100) return ['error'=>'Invalid students'];
        if ($ids && file_exists($dbPath)) {
            $check = new PDO('sqlite:'.$dbPath);
            $statement=$check->prepare("SELECT COUNT(*) FROM {$tableName} WHERE student_id=? AND class_name=?");
            foreach ($ids as $id) {
                if (!is_scalar($id)) return ['error'=>'Invalid student'];
                $statement->execute([$id,$className]);
                if (!$statement->fetchColumn()) return ['error'=>'Student outside permitted class'];
            }
        }
        return ['dbPath' => $dbPath, 'tableName' => $tableName, 'className' => $className];
    }
    
    // --- 1. جلب قائمة التلاميذ (الإجراء الأصلي) ---
    if ($_POST['action'] === 'get_students') {
        header('Content-Type: text/html; charset=utf-8');
        $dbInfo = getDbInfo($_POST['year'], $_POST['branch'], $_POST['part'], $_POST['classNum'], $year_map, $branch_map, $part_map, $table_map);
        if (isset($dbInfo['error'])) {
            echo '<p class="modal-error">' . htmlspecialchars($dbInfo['error']) . '</p>';
            exit;
        }
        
        $dbPath = $dbInfo['dbPath'];
        $tableName = $dbInfo['tableName'];
        $className = $dbInfo['className'];

        if (!file_exists($dbPath)) {
            echo '<h3>قائمة التلاميذ - ' . htmlspecialchars($className) . '</h3>';
            echo '<p class="modal-error">خطأ: لم يتم العثور على قاعدة البيانات.</p>';
            exit;
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // تم التعديل: جلب student_id أيضاً
            $stmt = $pdo->prepare("SELECT student_id, full_name FROM {$tableName} WHERE class_name = ?");
            $stmt->execute([$className]);
            $students = $stmt->fetchAll();

            echo '<h3>قائمة التلاميذ - ' . htmlspecialchars($className) . '</h3>';
            if ($students) {
                echo '<div class="student-list-container">'; // Containe for scrollable list
                foreach ($students as $student) {
                    echo '<div class="student-row">';
                    // تم التعديل: إضافة data-student-id
                    echo '<span class="student-info" data-student-id="' . htmlspecialchars($student['student_id']) . '" data-student-name="' . htmlspecialchars($student['full_name']) . '">';
                    echo '<span class="student-name">' . htmlspecialchars($student['full_name']) . '</span>';
                    echo '<button type="button" class="grades-dropdown-toggle"></button>';
                    echo '</span>';
                    // تم استبدال زر الغياب بمربع التحقق
                    echo '<div class="absence-checkbox-wrapper">';
                    // تم التعديل: إضافة data-student-id
                    echo '<input type="checkbox" class="absence-checkbox" data-student-id="' . htmlspecialchars($student['student_id']) . '">';
                    echo '</div>';
                    echo '</div>'; // .student-row
                    echo '<div class="grades-dropdown-container" style="display: none;"></div>';
                }
                echo '</div>'; // .student-list-container
                // زر إرسال الغياب الجماعي
                echo '<div class="absence-time-inputs">';
                echo '    <div class="time-input-group">';
                echo '    <div class="separator-line" style="height: 1px; background: linear-gradient(to right, transparent, #ccc 20%, #ccc 80%, transparent); margin: 20px 0;"></div>';
                echo '        <label for="from-time">من الساعة:</label>';
                echo '        <input type="text" id="from-time" name="from-time" placeholder="00:00">';
                echo '    </div>';
                echo '    <div class="time-input-group">';
                echo '        <label for="to-time">إلى الساعة:</label>';
                echo '        <input type="text" id="to-time" name="to-time" placeholder="00:00">';
                echo '    <div class="separator-line" style="height: 1px; background: linear-gradient(to right, transparent, #ccc 20%, #ccc 80%, transparent); margin: 20px 0;"></div>';
                echo '    </div>';
                echo '</div>';
                echo '<div class="mass-absence-btn-wrapper">';
                echo '<button type="button" id="send-absences-btn">إرسال الغياب</button>';
                echo '</div>';
            } else {
                echo '<p class="modal-info">لا يوجد تلاميذ مسجلون في هذا القسم.</p>';
            }

        } catch (PDOException $e) {
            echo '<p class="modal-error">حدث خطأ أثناء الاتصال بقاعدة البيانات: ' . $e->getMessage() . '</p>';
        }
    // --- 2. جلب بيانات الدرجات للطالب المحدد ---
    } elseif ($_POST['action'] === 'get_grades') {
        header('Content-Type: text/html; charset=utf-8');
        // تم التعديل: استلام student_id
        $studentId = $_POST['student_id'] ?? '';
        $year = $_POST['year'] ?? '';
        $branch = $_POST['branch'] ?? '';
        $part = $_POST['part'] ?? '';
        $classNum = $_POST['classNum'] ?? '';
        $subjectName = $_POST['subject_name'] ?? '';

        // تم التعديل: فحص studentId
        if (empty($studentId)) {
            echo '<p class="modal-error">خطأ: رقم هوية الطالب غير محدد.</p>';
            exit;
        }
        
        $dbInfo = getDbInfo($year, $branch, $part, $classNum, $year_map, $branch_map, $part_map, $table_map);
        if (isset($dbInfo['error'])) {
            echo '<p class="modal-error">' . htmlspecialchars($dbInfo['error']) . '</p>';
            exit;
        }

        $dbPath = $dbInfo['dbPath'];
        $tableName = $dbInfo['tableName'];

        if (!file_exists($dbPath)) {
            echo '<p class="modal-error">خطأ: لم يتم العثور على قاعدة البيانات.</p>';
            exit;
        }

        // Find the database column name for the subject
        $subject_key = array_search($subjectName, $subject_map);
        if (!$subject_key) {
            echo '<p class="modal-error">خطأ: لم يتم العثور على المادة المحددة في قاعدة البيانات.</p>';
            exit;
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // تم التعديل: استخدام student_id في الاستعلام
            $stmt = $pdo->prepare("SELECT {$subject_key}_term1, {$subject_key}_term2, {$subject_key}_term3, note FROM {$tableName} WHERE student_id = ?");
            $stmt->execute([$studentId]);
            $student_data = $stmt->fetch();

            if ($student_data) {
                // عرض جدول الدرجات
                echo '<div class="grades-content">';
                echo '    <div class="table-responsive">';
                echo '        <table class="grades-table">';
                echo '            <thead>';
                echo '                <tr>';
                echo '                    <th>المادة: ' . htmlspecialchars($subjectName) . '</th>';
                echo '                    <th colspan="2">الفصل الأول</th>';
                echo '                    <th colspan="2">الفصل الثاني</th>';
                echo '                    <th colspan="2">الفصل الثالث</th>';
                echo '                </tr>';
                echo '                <tr><th>الدرجات</th><th>فرض</th><th>اختبار</th><th>فرض</th><th>اختبار</th><th>فرض</th><th>اختبار</th></tr>';
                echo '            </thead>';
                echo '            <tbody>';
                
                echo '<tr>';
                echo '<td class="subject-name">' . htmlspecialchars($subjectName) . '</td>';
                for ($term = 1; $term <= 3; $term++) {
                    $term_key = $subject_key . '_term' . $term;
                    if (isset($student_data[$term_key]) && !empty($student_data[$term_key])) {
                        $grades = explode(',', $student_data[$term_key]);
                        echo '<td><input type="text" value="' . (isset($grades[0]) ? htmlspecialchars(trim($grades[0])) : '') . '" class="grade-input" disabled data-term="' . $term . '" data-type="فرض"></td>';
                        echo '<td><input type="text" value="' . (isset($grades[1]) ? htmlspecialchars(trim($grades[1])) : '') . '" class="grade-input" disabled data-term="' . $term . '" data-type="اختبار"></td>';
                    } else {
                        echo '<td><input type="text" value="" class="grade-input" disabled data-term="' . $term . '" data-type="فرض"></td>';
                        echo '<td><input type="text" value="" class="grade-input" disabled data-term="' . $term . '" data-type="اختبار"></td>';
                    }
                }
                echo '</tr>';

                echo '            </tbody>';
                echo '        </table>';
                echo '    </div>';
                echo '    <div class="grade-actions">';
                echo '        <button class="edit-grades-btn">تعديل</button>';
                echo '        <button class="save-grades-btn" style="display: none;">حفظ</button>';
                echo '    </div>';
                
                // إضافة حقل الملاحظة الجديد
                echo '<div class="note-section">';
                echo '    <h4>إضافة ملاحظة للطالب</h4>';
                echo '    <textarea id="note-textarea" rows="4" placeholder="اكتب ملاحظاتك هنا..."></textarea>';
                echo '    <button class="send-note-btn">إرسال الملاحظة</button>';
                echo '</div>';
                
                echo '</div>';

            } else {
                echo '<p class="modal-info">لم يتم العثور على بيانات لهذا الطالب.</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="modal-error">حدث خطأ في جلب البيانات: ' . $e->getMessage() . '</p>';
        }

    // --- 3. تسجيل الغياب الجماعي للطلاب المحددين (الإجراء الجديد) ---
    } elseif ($_POST['action'] === 'register_mass_absence') {
        // تم التعديل: استلام student_ids
        $studentIds = json_decode($_POST['student_ids'], true) ?? [];
        $subject = $_POST['subject'] ?? '';
        $year = $_POST['year'] ?? '';
        $branch = $_POST['branch'] ?? '';
        $part = $_POST['part'] ?? '';
        $classNum = $_POST['classNum'] ?? '';
        // تم التعديل: استلام الوقت من الحقول الجديدة
        $fromTime = $_POST['from_time'] ?? '';
        $toTime = $_POST['to_time'] ?? '';

        // تم التعديل: فحص studentIds
        if (empty($studentIds) || empty($subject)) {
            echo json_encode(['success' => false, 'message' => 'بيانات الطلاب أو المادة غير محددة.']);
            exit;
        }
        
        $dbInfo = getDbInfo($year, $branch, $part, $classNum, $year_map, $branch_map, $part_map, $table_map);
        if (isset($dbInfo['error'])) {
            echo json_encode(['success' => false, 'message' => $dbInfo['error']]);
            exit;
        }

        $dbPath = $dbInfo['dbPath'];
        $tableName = $dbInfo['tableName'];

        if (!file_exists($dbPath)) {
            echo json_encode(['success' => false, 'message' => 'خطأ: لم يتم العثور على قاعدة البيانات.']);
            exit;
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $currentDate = date('Y-m-d');
            $absenceTime = "من الساعة " . htmlspecialchars($fromTime) . " إلى الساعة " . htmlspecialchars($toTime);
            $successfulRegistrations = 0;
            
            // تم التعديل: استخدام student_id في الاستعلام
            $stmt = $pdo->prepare("SELECT absence_details, absence_count, full_name FROM {$tableName} WHERE student_id = ?");
            $updateStmt = $pdo->prepare("UPDATE {$tableName} SET absence_details = ?, absence_count = ? WHERE student_id = ?");

            foreach ($studentIds as $studentId) {
                $stmt->execute([$studentId]);
                $studentData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($studentData) {
                    // تم التعديل: استخدام المتغيرات الجديدة للوقت والتاريخ
                    $absenceNote = htmlspecialchars($subject) . " يوم " . $currentDate . ": " . $absenceTime;
                    $newAbsenceDetails = !empty($studentData['absence_details'])
                        ? $studentData['absence_details'] . ' | ' . $absenceNote . ' |'
                        : $absenceNote . ' |';
                    $newAbsenceCount = (int)$studentData['absence_count'] + 1;
                    
                    $updateStmt->execute([$newAbsenceDetails, $newAbsenceCount, $studentId]);
                    $successfulRegistrations++;
                }
            }
            
            if ($successfulRegistrations > 0) {
                echo json_encode(['success' => true, 'message' => 'تم تسجيل الغياب بنجاح لـ ' . $successfulRegistrations . ' تلاميذ.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'لم يتم العثور على أي من الطلاب المحددين.']);
            }

        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'خطأ أثناء تسجيل الغياب: ' . $e->getMessage()]);
        }
    // --- 4. تحديث الدرجات (الإجراء الأصلي) ---
    } elseif ($_POST['action'] === 'save_grades') {
        // تم التعديل: استلام student_id
        $studentId = $_POST['student_id'] ?? '';
        $year = $_POST['year'] ?? '';
        $branch = $_POST['branch'] ?? '';
        $part = $_POST['part'] ?? '';
        $classNum = $_POST['classNum'] ?? '';
        $subjectName = $_POST['subject_name'] ?? '';
        $grades = json_decode($_POST['grades'], true) ?? [];
    
        // تم التعديل: فحص studentId
        if (empty($studentId) || empty($subjectName)) {
            echo json_encode(['success' => false, 'message' => 'بيانات الطالب أو المادة غير محددة.']);
            exit;
        }
    
        $dbInfo = getDbInfo($year, $branch, $part, $classNum, $year_map, $branch_map, $part_map, $table_map);
        if (isset($dbInfo['error'])) {
            echo json_encode(['success' => false, 'message' => $dbInfo['error']]);
            exit;
        }
    
        $dbPath = $dbInfo['dbPath'];
        $tableName = $dbInfo['tableName'];
    
        if (!file_exists($dbPath)) {
            echo json_encode(['success' => false, 'message' => 'خطأ: لم يتم العثور على قاعدة البيانات.']);
            exit;
        }

        $subject_key = array_search($subjectName, $subject_map);
        if (!$subject_key) {
            echo json_encode(['success' => false, 'message' => 'خطأ: لم يتم العثور على المادة المحددة في قاعدة البيانات.']);
            exit;
        }
    
        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $updateFields = [];
            $updateValues = [];
            
            foreach ($grades as $term => $grade_values) {
                if (!in_array($term,['term1','term2','term3'],true) || !is_array($grade_values) || count($grade_values)>10) {
                    http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid grades']); exit;
                }
                foreach ($grade_values as $value) if ($value !== '' && (!is_numeric($value) || (float)$value<0 || (float)$value>20)) {
                    http_response_code(422); echo json_encode(['success'=>false,'message'=>'Grade must be between 0 and 20']); exit;
                }
                $columnName = "{$subject_key}_{$term}";
                $updateFields[] = "{$columnName} = ?";
                $updateValues[] = implode(',', $grade_values);
            }
            
            if (empty($updateFields)) {
                echo json_encode(['success' => false, 'message' => 'لا توجد درجات للتحديث.']);
                exit;
            }
            
            // تم التعديل: استخدام student_id في الاستعلام
            $updateSql = "UPDATE {$tableName} SET " . implode(', ', $updateFields) . " WHERE student_id = ?";
            $updateValues[] = $studentId;
            
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($updateValues);
    
            echo json_encode(['success' => true, 'message' => 'تم حفظ التغييرات بنجاح.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'خطأ أثناء حفظ الدرجات: ' . $e->getMessage()]);
        }
    // --- 5. إضافة الملاحظة الجديدة (الإجراء الأصلي) ---
    } elseif ($_POST['action'] === 'send_note') {
        // تم التعديل: استلام student_id
        $studentId = $_POST['student_id'] ?? '';
        $noteText = $_POST['note_text'] ?? '';
        $subject = $_POST['subject_name'] ?? '';
        $year = $_POST['year'] ?? '';
        $branch = $_POST['branch'] ?? '';
        $part = $_POST['part'] ?? '';
        $classNum = $_POST['classNum'] ?? '';
    
        // تم التعديل: فحص studentId
        if (empty($studentId) || empty($noteText)) {
            echo json_encode(['success' => false, 'message' => 'بيانات الطالب أو الملاحظة غير محددة.']);
            exit;
        }

        $dbInfo = getDbInfo($year, $branch, $part, $classNum, $year_map, $branch_map, $part_map, $table_map);
        if (isset($dbInfo['error'])) {
            echo json_encode(['success' => false, 'message' => $dbInfo['error']]);
            exit;
        }
    
        $dbPath = $dbInfo['dbPath'];
        $tableName = $dbInfo['tableName'];
    
        if (!file_exists($dbPath)) {
            echo json_encode(['success' => false, 'message' => 'خطأ: لم يتم العثور على قاعدة البيانات.']);
            exit;
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            // تم التعديل: استخدام student_id في الاستعلام
            $stmt = $pdo->prepare("SELECT note FROM {$tableName} WHERE student_id = ?");
            $stmt->execute([$studentId]);
            $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $currentDate = date('Y-m-d');
            $newNote = "مدرس " . htmlspecialchars($subject) . " " . $currentDate . ": " . htmlspecialchars($noteText);
            
            // هذا هو السطر المعدّل ---
            $existingNote = $studentData['note'] ?? '';
            $updatedNote = !empty($existingNote) ? $existingNote . ' | ' . $newNote . ' |' : $newNote . ' |';
            
            // تم التعديل: استخدام student_id في التحديث
            $updateStmt = $pdo->prepare("UPDATE {$tableName} SET note = ? WHERE student_id = ?");
            $updateStmt->execute([$updatedNote, $studentId]);
    
            echo json_encode(['success' => true, 'message' => 'تم إرسال الملاحظة بنجاح.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'خطأ أثناء إرسال الملاحظة: ' . $e->getMessage()]);
        }
    }
    
    exit;
}

// معالج تسجيل الخروج
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    session_destroy();
    header('Location: teacher.php');
    exit;
}

// دالة للتحقق من بيانات تسجيل الدخول
function authenticateTeacher($username, $password) {
    $dbPath = "data/teachers/database.sqlite";
    if (!file_exists($dbPath)) return false;
    $username_password_combo = $username . ':' . $password;
    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("SELECT teacher_id, full_name, permission FROM teachers WHERE username_password = ?");
        $stmt->execute([$username_password_combo]);
        return $stmt->fetch() ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

// دالة لتحليل الصلاحيات الجديدة
function parsePermissions($permissionText) {
    $permissions = [];
    $entries = explode('|', $permissionText);
    foreach ($entries as $entry) {
        $entry = trim($entry);
        if (empty($entry)) continue;
        $data = [];
        $parts = explode('/', $entry);
        foreach ($parts as $part) {
            if (strpos($part, ':') !== false) {
                list($key, $value) = explode(':', $part, 2);
                $data[trim($key)] = trim($value);
            }
        }
        if (isset($data['Year']) && isset($data['branch']) && isset($data['subject'])) {
            $year = $data['Year'];
            $branch = $data['branch'];
            if (!isset($permissions[$year])) $permissions[$year] = [];
            if (!isset($permissions[$year][$branch])) $permissions[$year][$branch] = [];
            $permissions[$year][$branch][] = [
                'subject' => $data['subject'],
                'part' => $data['part'] ?? '',
                'classes' => isset($data['classes']) ? array_map('trim', explode(',', $data['classes'])) : []
            ];
        }
    }
    return $permissions;
}

$error_message = '';
$show_dashboard = false;
$teacher_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['teacher_id']) && !isset($_POST['action'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!empty($username) && !empty($password)) {
        $teacher_data = authenticateTeacher($username, $password);
        if ($teacher_data) {
            session_regenerate_id(true);
            $_SESSION['teacher_id'] = $teacher_data['teacher_id'];
            $_SESSION['full_name'] = $teacher_data['full_name'];
            $_SESSION['permission'] = $teacher_data['permission'];
            $show_dashboard = true;
        } else {
            $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        }
    } else {
        $error_message = 'الرجاء إدخال جميع البيانات المطلوبة.';
    }
}

if (isset($_SESSION['teacher_id'])) {
    $show_dashboard = true;
    $teacher_data = [
        'teacher_id' => $_SESSION['teacher_id'],
        'full_name' => $_SESSION['full_name'],
        'permission' => $_SESSION['permission']
    ];
}

if ($show_dashboard && $teacher_data) {
    $full_name = $teacher_data['full_name'];
    $name_parts = explode(' ', $full_name, 2);
    $first_name = $name_parts[0];
    $last_name = $name_parts[1] ?? '';
    $permissions = parsePermissions($teacher_data['permission']);
    $totalSubjects = 0;
    $totalSections = 0;
    if (!empty($permissions)) {
        foreach ($permissions as $year => $branches) {
            foreach ($branches as $branchName => $subjects) {
                foreach ($subjects as $subjectData) {
                    $totalSubjects++;
                    $totalSections += count($subjectData['classes']);
                }
            }
        }
    }
}

if (isset($_POST['subject_name'])) {
    $_SESSION['subject_name'] = $_POST['subject_name'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $show_dashboard ? 'لوحة تحكم المدرس' : 'تسجيل دخول المدرس'; ?></title>
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

        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-tertiary) 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            direction: rtl;
        }
        <?php if (!$show_dashboard): ?>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .login-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 50px 40px;
            width: 100%;
            max-width: 480px;
            text-align: center;
            box-shadow: var(--shadow-xl);
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

        .logo-container {
            margin-bottom: 25px;
        }

        .logo-container img {
            max-width: 120px;
            height: auto;
        }

        .login-title {
            font-size: 2.2em;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .login-subtitle {
            color: var(--text-secondary);
            margin-bottom: 30px;
            font-size: 1.1em;
        }

        .form-group {
            margin-bottom: 25px;
            text-align: right;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.95em;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1em;
            font-family: 'Tajawal', sans-serif;
            transition: var(--transition);
            background: var(--bg-secondary);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .login-btn {
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

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .error {
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
        <?php else: ?>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="header-pattern" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23header-pattern)"/></svg>');
        }
        .teacher-info {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .teacher-logo-dashboard {
            width: 80px;
            height: 80px;
        }

        .teacher-logo-dashboard img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .teacher-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .teacher-details h1 {
            color: white;
            font-size: 2.2em;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .teacher-details p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.2em;
            margin: 0;
            font-weight: 400;
        }

        .teacher-details .full-name-split {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.2em;
            display: flex;
            gap: 8px;
        }

        .teacher-details .full-name-split span {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 12px 24px;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            backdrop-filter: blur(10px);
            border: none;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .separator-line{width:100%;height:1px;background-color:var(--border-color);margin:20px 0;}
        .permissions-section {
            background: var(--bg-secondary);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
            border: 1px solid var(--border-light);
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.8em;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-light);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 30px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        .grades-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .grade-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border-right: 4px solid var(--primary-color);
            overflow: hidden;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
        }

        .grade-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .grade-header {
            padding: 25px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
            background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-secondary) 100%);
        }

        .grade-header:hover {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
        }

        .grade-card.expanded .grade-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .grade-card h3 {
            color: var(--primary-color);
            font-size: 1.5em;
            margin: 0;
            font-weight: 700;
        }

        .grade-card.expanded h3 {
            color: white;
        }
        .expand-arrow {
            display: inline-block;
            width: 24px;
            height: 24px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="%238B0000" d="M16.293 9.293L12 13.586l-4.293-4.293a1 1 0 0 0-1.414 1.414l5 5a1 1 0 0 0 1.414 0l5-5a1 1 0 0 0-1.414-1.414z"/></svg>');
            background-repeat: no-repeat;
            background-position: center;
            transition: var(--transition);
        }

        .grade-card.expanded .expand-arrow {
            transform: rotate(180deg);
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M16.293 9.293L12 13.586l-4.293-4.293a1 1 0 0 0-1.414 1.414l5 5a1 1 0 0 0 1.414 0l5-5a1 1 0 0 0-1.414-1.414z"/></svg>');
        }
        .subjects-dropdown {
            background: var(--bg-secondary);
            padding: 0;
            display: none;
            border-top: 1px solid var(--border-light);
        }

        .subjects-dropdown.show {
            display: block;
            animation: slideDown 0.4s ease-out;
        }

        .branch-section {
            border-bottom: 1px solid var(--border-light);
        }

        .branch-section:last-child {
            border-bottom: none;
        }

        .branch-header {
            padding: 20px;
            font-weight: 600;
            font-size: 1.2em;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-light);
            background: var(--bg-tertiary);
        }

        .subjects-grid {
            padding: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
        }

        .subject-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 25px;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .subject-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
        }

        .subject-title {
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sections-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-tag {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 0.95em;
            font-weight: 500;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        .section-tag:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }
        .no-permissions {
            text-align: center;
            padding: 50px;
            color: var(--text-secondary);
            font-size: 1.1em;
        }

        .no-permissions i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .stats-container {
            margin-top: 30px;
            background: var(--bg-secondary);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            justify-content: center;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: var(--radius-lg);
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="stat-pattern" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23stat-pattern)"/></svg>');
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 2.8em;
            font-weight: 800;
            margin-bottom: 10px;
            line-height: 1;
            position: relative;
            z-index: 1;
        }

        .stat-label {
            font-size: 1.1em;
            opacity: 0.9;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        @keyframes slideDown{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: var(--bg-secondary);
            margin: 5% auto;
            padding: 35px;
            border: 1px solid var(--border-light);
            width: 90%;
            max-width: 650px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            position: relative;
            animation: slideIn 0.4s ease-out;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .close-button {
            color: var(--text-secondary);
            position: absolute;
            left: 25px;
            top: 15px;
            font-size: 2em;
            font-weight: bold;
            transition: var(--transition);
            z-index: 1;
            background: none;
            border: none;
            cursor: pointer;
        }

        .close-button:hover,
        .close-button:focus {
            color: var(--danger-color);
            text-decoration: none;
        }

        #modal-body h3 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-light);
            font-size: 1.5em;
            font-weight: 700;
        }
        
        .student-list-container {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
            margin-bottom: 20px;
        }

        .student-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
            transition: var(--transition);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 0;
            margin-bottom: 10px;
        }

        .student-row.expanded {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            border-bottom: none;
        }

        .student-row:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            transform: translateX(-3px);
            box-shadow: var(--shadow-sm);
        }

        .student-info {
            flex-grow: 1;
            text-align: right;
            border: none;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            font-family: 'Tajawal', sans-serif;
            font-size: 1em;
            color: var(--text-primary);
            white-space: nowrap;
        }

        .student-name {
            flex-grow: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 600;
        }
        
        /* هذا هو الجزء الذي تم تعديله */
        .grades-dropdown-toggle,
        .grades-dropdown-toggle:active,
        .grades-dropdown-toggle:focus {
            width: 24px;
            height: 24px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="%238B0000" d="M16.293 9.293L12 13.586l-4.293-4.293a1 1 0 0 0-1.414 1.414l5 5a1 1 0 0 0 1.414 0l5-5a1 1 0 0 0-1.414-1.414z"/></svg>');
            background-repeat: no-repeat;
            background-position: center;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            background-color: transparent;
            outline: none !important;
            box-shadow: none !important;
            -webkit-tap-highlight-color: transparent; /* حل مشكلة الهواتف */
        }
        
        .grades-dropdown-toggle.expanded {
            transform: rotate(180deg);
        }
        
        /* CSS جديد لمربع التحقق */
        .absence-checkbox-wrapper {
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .absence-checkbox {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        /* CSS جديد لحقول الوقت */
        .absence-time-inputs {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            margin-bottom: 25px;
            font-family: 'Tajawal', sans-serif;
            color: var(--text-primary);
            flex-wrap: wrap; /* للسماح بالعرض في سطر واحد */
        }

        /* تم التعديل: تغيير حجم الحقل وتوسيط النص */
        .absence-time-inputs input[type="text"] {
            width: 90px;
            padding: 12px 15px;
            text-align: center;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 1em;
            font-family: 'Tajawal', sans-serif;
            transition: var(--transition);
            background: var(--bg-secondary);
        }

        .absence-time-inputs input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        /* CSS جديد لزر إرسال الغياب */
        .mass-absence-btn-wrapper {
            margin-top: 25px;
            text-align: center;
        }

        #send-absences-btn {
            padding: 15px 30px;
            background: linear-gradient(135deg, var(--danger-color), #B22222);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
            transition: var(--transition);
            font-size: 1em;
            -webkit-tap-highlight-color: transparent; /* حل مشكلة الهواتف */
        }

        #send-absences-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* إضافة: تأثير الضغط */
        #send-absences-btn:active {
            transform: translateY(0);
            box-shadow: var(--shadow-sm);
        }

        .grades-dropdown-container {
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            margin-top: 0;
            margin-bottom: 15px;
            overflow-x: auto;
            padding: 0;
            background: var(--bg-secondary);
            border: 1px solid var(--border-light);
            border-top: none;
            box-shadow: var(--shadow-sm);
        }

        .grades-dropdown-container.hidden {
            display: none !important;
        }

        .grades-dropdown-content {
            padding: 20px;
            background-color: var(--bg-secondary);
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            border-top: 1px solid var(--border-light);
        }

        .grades-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 15px;
            border-radius: var(--radius-sm);
        }

        .grades-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--bg-secondary);
            min-width: 600px;
            border-radius: var(--radius-sm);
            overflow: hidden;
        }

        .grades-table th,
        .grades-table td {
            border: 1px solid var(--border-light);
            padding: 12px 8px;
            text-align: center;
            white-space: nowrap;
        }

        .grades-table th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            font-size: 0.95em;
        }

        .grades-table tr:nth-child(even) {
            background-color: var(--bg-tertiary);
        }

        .grades-table tr:hover {
            background-color: var(--bg-tertiary);
            transform: scale(1.01);
        }

        .grades-table td.subject-name {
            text-align: right;
            font-weight: 600;
            color: var(--primary-color);
            background: var(--bg-tertiary);
        }

        .grade-input {
            width: 60px;
            padding: 8px;
            text-align: center;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-family: 'Tajawal', sans-serif;
            background-color: var(--bg-secondary);
            transition: var(--transition);
        }

        .grade-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
        }

        .grade-input:disabled {
            background-color: var(--bg-tertiary);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .grade-actions {
            text-align: center;
        }

        .edit-grades-btn,
        .save-grades-btn {
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: var(--transition);
            background: linear-gradient(135deg, var(--info-color), #87CEEB);
            color: white;
            font-family: 'Tajawal', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        .edit-grades-btn:hover,
        .save-grades-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .note-section {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid var(--border-light);
            text-align: center;
        }

        .note-section h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.2em;
            font-weight: 700;
        }

        #note-textarea {
            width: 100%;
            resize: vertical;
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: 'Tajawal', sans-serif;
            font-size: 1em;
            min-height: 60px;
            max-height: 120px;
            transition: var(--transition);
            background: var(--bg-secondary);
        }

        #note-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
        }

        .note-section .send-note-btn {
            margin-top: 15px;
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-family: 'Tajawal', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        .note-section .send-note-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .modal-alert {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            background-color: var(--bg-secondary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            z-index: 1001;
            padding: 30px;
            text-align: center;
            font-family: 'Tajawal', sans-serif;
            border: 1px solid var(--border-light);
        }

        .modal-alert .alert-header {
            display: flex;
            justify-content: flex-end;
        }

        .modal-alert .alert-close-btn {
            font-size: 2em;
            color: var(--text-secondary);
            cursor: pointer;
            line-height: 1;
            transition: var(--transition);
            position: absolute;
            top: 15px;
            left: 15px;
            background: none;
            border: none;
        }

        .modal-alert .alert-close-btn:hover {
            color: var(--danger-color);
        }

        .modal-alert .alert-icon {
            font-size: 3.5em;
            color: #fff;
            background: var(--accent-color);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px auto;
            box-shadow: var(--shadow-md);
        }

        .modal-alert .alert-icon svg {
            width: 35px;
            height: 35px;
        }

        .modal-alert.success .alert-icon {
            background: linear-gradient(135deg, var(--success-color), #32CD32);
        }

        .modal-alert.confirm .alert-icon {
            background: linear-gradient(135deg, var(--danger-color), #B22222);
        }

        .modal-alert h4 {
            color: var(--text-primary);
            font-size: 1.5em;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .modal-alert p {
            color: var(--text-secondary);
            margin-bottom: 25px;
            font-size: 1.1em;
            line-height: 1.6;
        }

        .modal-alert .alert-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .modal-alert .alert-actions button {
            padding: 12px 25px;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
            transition: var(--transition);
            font-size: 1em;
        }

        .modal-alert .btn-confirm {
            background: linear-gradient(135deg, var(--danger-color), #B22222);
            color: white;
        }

        .modal-alert .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .modal-alert .btn-cancel {
            background: var(--text-secondary);
            color: white;
        }

        .modal-alert .btn-cancel:hover {
            background: var(--text-primary);
            transform: translateY(-2px);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .logout-btn {
                align-self: flex-start;
                margin-top: 10px;
            }
            
            .subjects-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .grade-card {
                margin-bottom: 20px;
            }
            
            .grade-header {
                padding: 20px;
            }
            
            .subject-card {
                padding: 20px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 25px;
            }
            
            .modal-alert {
                width: 90%;
                padding: 25px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 20px 15px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .grade-header {
                padding: 15px;
            }
            
            .subject-card {
                padding: 15px;
            }
            
            .section-tag {
                padding: 8px 15px;
                font-size: 0.9em;
            }
        }
        <?php endif; ?>
    </style>
</head>
<body>
<?php if (!$show_dashboard): ?>
    <div class="login-container">
        <div class="login-box">
            <div class="logo-container"><img src="https://iili.io/FQLtDjs.md.png" alt="شعار المدرسة"></div>
            <h1 class="login-title">تسجيل دخول المدرس</h1>
            <p class="login-subtitle">أهلاً بك في المنطقة الخاصة بالمدرسين</p>
            <?php if (!empty($error_message)): ?><div class="error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
            <form method="POST" action="teacher.php">
                <div class="form-group"><label for="username">اسم المستخدم</label><input type="text" id="username" name="username" required></div>
                <div class="form-group"><label for="password">كلمة المرور</label><input type="password" id="password" name="password" required></div>
                <button type="submit" class="login-btn">دخول</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="teacher-info">
                <div class="teacher-logo-dashboard">
                    <i class="fas fa-chalkboard-teacher" style="font-size: 3em; color: white;"></i>
                </div>
                <div class="teacher-details">
                    <h1>أهلاً بك، <?php echo htmlspecialchars($first_name); ?></h1>
                    <p class="full-name-split"><span style="font-weight: bold;">الاسم:</span> <span><?php echo htmlspecialchars($first_name); ?></span></p>
                    <p class="full-name-split"><span style="font-weight: bold;">اللقب:</span> <span><?php echo htmlspecialchars($last_name); ?></span></p>
                </div>
            </div>
            <a href="teacher.php?action=logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                تسجيل الخروج
            </a>
        </header>

        <div class="separator-line"></div>
        
        <main class="permissions-section">
            <h2 class="section-title">
                <i class="fas fa-users"></i>
                منطقة التلاميذ تحت التدريس
            </h2>
            <div class="grades-container">
                <?php
                $gradeNames = ['اول ثانوي' => 'السنة الأولى ثانوي','ثاني ثانوي' => 'السنة الثانية ثانوي','ثالث ثانوي' => 'السنة الثالثة ثانوي'];
                if (!empty($permissions)):
                    foreach ($permissions as $year => $branches):
                        $displayYear = $gradeNames[$year] ?? $year;
                ?>
                    <div class="grade-card" id="card-<?php echo str_replace(' ', '_', $year); ?>">
                        <div class="grade-header" onclick="toggleDropdown('<?php echo str_replace(' ', '_', $year); ?>')">
                            <h3><?php echo htmlspecialchars($displayYear); ?></h3>
                            <div class="expand-arrow"></div>
                        </div>
                        <div class="subjects-dropdown" id="dropdown-<?php echo str_replace(' ', '_', $year); ?>">
                            <?php foreach ($branches as $branchName => $subjects): ?>
                                <div class="branch-section">
                                    <div class="branch-header">الفرع <?php echo htmlspecialchars($branchName); ?></div>
                                    <div class="subjects-grid">
                                        <?php foreach ($subjects as $subjectData): ?>
                                            <div class="subject-card">
                                                <div class="subject-title">
                                                    <span class="subject-icon"></span><?php echo htmlspecialchars($subjectData['subject']); ?>
                                                    <?php if (!empty($subjectData['part'])): ?><span style="color: #666; font-size: 0.9em; font-weight: normal;"> (<?php echo htmlspecialchars($subjectData['part']); ?>)</span><?php endif; ?>
                                                </div>
                                                <div class="sections-container">
                                                    <?php if (!empty($subjectData['classes'])): ?>
                                                        <?php foreach ($subjectData['classes'] as $class): ?>
                                                            <button type="button" class="section-tag"
                                                                data-year="<?php echo htmlspecialchars($year); ?>" 
                                                                data-branch="<?php echo htmlspecialchars($branchName); ?>" 
                                                                data-part="<?php echo htmlspecialchars($subjectData['part']); ?>"
                                                                data-class-num="<?php echo htmlspecialchars($class); ?>"
                                                                data-subject-name="<?php echo htmlspecialchars($subjectData['subject']); ?>">
                                                                قسم <?php echo htmlspecialchars($class); ?>
                                                            </button>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="section-tag" style="background: #6c757d; cursor: default;">لا توجد أقسام محددة</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="no-permissions">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>لا توجد لديك صلاحيات لعرض أي صف دراسي حالياً.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        
        <div class="separator-line"></div>
        
        <div class="stats-container">
            <h2 class="section-title">
                <i class="fas fa-chart-bar"></i>
                الإحصائيات
            </h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalSubjects; ?></div>
                    <div class="stat-label">إجمالي المواد</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalSections; ?></div>
                    <div class="stat-label">إجمالي الأقسام</div>
                </div>
            </div>
        </div>
    </div>

    <div id="studentModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div id="modal-body"></div>
        </div>
    </div>
    
            <div id="noStudentsSelectedAlert" class="modal-alert">
            <div class="alert-header">
                <span class="alert-close-btn">&times;</span>
            </div>
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h4>تنبيه!</h4>
            <p>الرجاء تحديد تلميذ واحد على الأقل لتسجيل الغياب.</p>
        </div>

    <div id="timeMissingAlert" class="modal-alert">
        <div class="alert-header">
            <span class="alert-close-btn">&times;</span>
        </div>
        <div class="alert-icon">
            <i class="fas fa-clock"></i><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
        </div>
        <h4>تنبيه!</h4>
        <p>الرجاء إدخال وقتي البداية والنهاية.</p>
    </div>
    
    <div id="absenceSuccessModal" class="modal-alert success">
        <div class="alert-header">
            <span class="alert-close-btn">&times;</span>
        </div>
        <div class="alert-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <h4>تم التسجيل بنجاح</h4>
        <p id="success-message"></p>
    </div>

    <div id="gradesSuccessModal" class="modal-alert success">
        <div class="alert-header">
            <span class="alert-close-btn">&times;</span>
        </div>
        <div class="alert-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <h4>تم الحفظ بنجاح</h4>
        <p id="grades-success-message">تم حفظ الدرجات بنجاح.</p>
    </div>
    
    <div id="noteSuccessModal" class="modal-alert success">
        <div class="alert-header">
            <span class="alert-close-btn">&times;</span>
        </div>
        <div class="alert-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <h4>تم إرسال الملاحظة</h4>
        <p id="note-success-message">تم إرسال الملاحظة بنجاح.</p>
    </div>

    <div id="modal-overlay" class="modal-overlay"></div>

    <script>
        function toggleDropdown(yearId) {
            const card = document.getElementById('card-' + yearId);
            const dropdown = document.getElementById('dropdown-' + yearId);
            const isExpanded = card.classList.contains('expanded');
            document.querySelectorAll('.grade-card').forEach(c => c.id !== 'card-' + yearId && c.classList.remove('expanded'));
            document.querySelectorAll('.subjects-dropdown').forEach(dd => dd.id !== 'dropdown-' + yearId && dd.classList.remove('show'));
            card.classList.toggle('expanded', !isExpanded);
            dropdown.classList.toggle('show', !isExpanded);
        }
        
        function showCustomAlert(type, message) {
            const overlay = document.getElementById('modal-overlay');
            const successModal = document.getElementById('absenceSuccessModal');
            const gradesSuccessModal = document.getElementById('gradesSuccessModal');
            const noteSuccessModal = document.getElementById('noteSuccessModal');
            const noStudentsSelectedAlert = document.getElementById('noStudentsSelectedAlert');
            const timeMissingAlert = document.getElementById('timeMissingAlert');
            
            overlay.style.display = 'block';

            if (type === 'success_absence') {
                document.getElementById('success-message').textContent = message;
                successModal.style.display = 'block';
            } else if (type === 'success_grades') {
                gradesSuccessModal.style.display = 'block';
            } else if (type === 'success_note') {
                noteSuccessModal.style.display = 'block';
            } else if (type === 'no_selection') {
                noStudentsSelectedAlert.style.display = 'block';
            } else if (type === 'time_missing') {
                timeMissingAlert.style.display = 'block';
            }
        }
        
        function hideCustomAlert() {
            document.getElementById('modal-overlay').style.display = 'none';
            document.getElementById('absenceSuccessModal').style.display = 'none';
            document.getElementById('gradesSuccessModal').style.display = 'none';
            document.getElementById('noteSuccessModal').style.display = 'none';
            document.getElementById('noStudentsSelectedAlert').style.display = 'none';
            document.getElementById('timeMissingAlert').style.display = 'none';
        }

        document.querySelectorAll('.alert-close-btn').forEach(btn => {
            btn.onclick = hideCustomAlert;
        });

        document.addEventListener('DOMContentLoaded', function() {
            // --- Modal Logic ---
            const modal = document.getElementById('studentModal');
            if (modal) {
                const modalBody = document.getElementById('modal-body');
                const closeButton = modal.querySelector('.close-button');
                
                closeButton.onclick = () => modal.style.display = "none";
                window.onclick = event => { if (event.target == modal) modal.style.display = "none"; };
                
                // Handle click on Section Tag
                document.querySelectorAll('.section-tag').forEach(button => {
                    button.addEventListener('click', function(event) {
                        const buttonData = event.currentTarget.dataset;
                        modal.style.display = "block";
                        modalBody.innerHTML = '<p class="modal-info">جاري تحميل قائمة التلاميذ...</p>';

                        const formData = new URLSearchParams({
                            action: 'get_students',
                            year: buttonData.year,
                            branch: buttonData.branch,
                            part: buttonData.part,
                            classNum: buttonData.classNum
                        });

                        // Store section info for later use
                        modal.dataset.year = buttonData.year;
                        modal.dataset.branch = buttonData.branch;
                        modal.dataset.part = buttonData.part;
                        modal.dataset.classNum = buttonData.classNum;
                        modal.dataset.subjectName = buttonData.subjectName;

                        fetch('teacher.php', { method: 'POST', body: formData })
                        .then(response => response.ok ? response.text() : Promise.reject('Network response was not ok.'))
                        .then(html => { modalBody.innerHTML = html; })
                        .catch(error => {
                            console.error('Fetch Error:', error);
                            modalBody.innerHTML = `<p class="modal-error">حدث خطأ في جلب البيانات. الرجاء التأكد من اتصالك بالشبكة والمحاولة مرة أخرى.</p>`;
                        });
                    });
                });

                // Handle clicks inside the modal (for absence and grades buttons)
                modalBody.addEventListener('click', function(event) {
                    // Send Mass Absence
                    if (event.target.id === 'send-absences-btn') {
                        // تم التعديل: استهداف student_id
                        const checkedCheckboxes = modalBody.querySelectorAll('.absence-checkbox:checked');
                        const studentIds = Array.from(checkedCheckboxes).map(cb => cb.dataset.studentId);
                        
                        // إضافة: التحقق من حقول الوقت
                        const fromTime = modalBody.querySelector('#from-time').value.trim();
                        const toTime = modalBody.querySelector('#to-time').value.trim();
                        if (fromTime === '' || toTime === '') {
                            // تم التعديل: استدعاء النافذة الجديدة بدلاً من alert
                            showCustomAlert('time_missing');
                            return;
                        }

                        if (studentIds.length === 0) {
                            // تم التعديل: استدعاء النافذة الجديدة بدلاً من alert
                            showCustomAlert('no_selection');
                            return;
                        }

                        const subjectName = modal.dataset.subjectName;
                        const year = modal.dataset.year;
                        const branch = modal.dataset.branch;
                        const part = modal.dataset.part;
                        const classNum = modal.dataset.classNum;
                        
                        // تم التعديل: جلب قيم الوقت من الحقول الجديدة
                        // تم نقلها إلى بداية الدالة
                        // const fromTime = modalBody.querySelector('#from-time').value;
                        // const toTime = modalBody.querySelector('#to-time').value;

                        const formData = new URLSearchParams({
                            action: 'register_mass_absence',
                            // تم التعديل: إرسال student_ids
                            student_ids: JSON.stringify(studentIds),
                            subject: subjectName,
                            year: year,
                            branch: branch,
                            part: part,
                            classNum: classNum,
                            // إضافة قيم الوقت الجديدة
                            from_time: fromTime,
                            to_time: toTime
                        });

                        fetch('teacher.php', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showCustomAlert('success_absence', data.message);
                                // Uncheck all boxes after successful submission
                                checkedCheckboxes.forEach(cb => cb.checked = false);
                            } else {
                                alert('خطأ: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('حدث خطأ أثناء الاتصال بالخادم.');
                        });
                    }

                    // Toggle Grades Dropdown via the arrow button
                    if (event.target.classList.contains('grades-dropdown-toggle')) {
                        const toggleButton = event.target;
                        const studentInfo = toggleButton.closest('.student-info');
                        const studentRow = toggleButton.closest('.student-row');
                        const dropdownContainer = studentRow.nextElementSibling;
                        // تم التعديل: استهداف student_id
                        const studentId = studentInfo.dataset.studentId;

                        // Toggle active class and rotate arrow
                        toggleButton.classList.toggle('expanded');
                        studentRow.classList.toggle('expanded');
                        
                        if (dropdownContainer.style.display !== 'none') {
                            dropdownContainer.style.display = 'none';
                            return;
                        }

                        document.querySelectorAll('.grades-dropdown-container').forEach(d => {
                            if (d !== dropdownContainer) {
                                d.style.display = 'none';
                                d.previousElementSibling.classList.remove('expanded');
                                d.previousElementSibling.querySelector('.grades-dropdown-toggle').classList.remove('expanded');
                            }
                        });
                        
                        dropdownContainer.style.display = 'block';

                        dropdownContainer.innerHTML = '<div class="grades-dropdown-content"><p class="modal-info">جاري تحميل الدرجات...</p></div>';

                        const formData = new URLSearchParams({
                            action: 'get_grades',
                            // تم التعديل: إرسال student_id
                            student_id: studentId,
                            year: modal.dataset.year,
                            branch: modal.dataset.branch,
                            part: modal.dataset.part,
                            classNum: modal.dataset.classNum,
                            subject_name: modal.dataset.subjectName
                        });

                        fetch('teacher.php', { method: 'POST', body: formData })
                        .then(response => response.ok ? response.text() : Promise.reject('Network response was not ok.'))
                        .then(html => { dropdownContainer.innerHTML = `<div class="grades-dropdown-content">${html}</div>`; })
                        .catch(error => {
                            console.error('Fetch Error:', error);
                            dropdownContainer.innerHTML = `<div class="grades-dropdown-content"><p class="modal-error">حدث خطأ في جلب البيانات.</p></div>`;
                        });
                    }

                    // Edit Grades
                    if (event.target.classList.contains('edit-grades-btn')) {
                        const gradesDropdown = event.target.closest('.grades-dropdown-content');
                        const inputs = gradesDropdown.querySelectorAll('.grade-input');
                        inputs.forEach(input => input.disabled = false);
                        event.target.style.display = 'none';
                        gradesDropdown.querySelector('.save-grades-btn').style.display = 'inline-block';
                    }

                    // Save Grades
                    if (event.target.classList.contains('save-grades-btn')) {
                        const gradesDropdown = event.target.closest('.grades-dropdown-content');
                        const studentRow = gradesDropdown.closest('.grades-dropdown-container').previousElementSibling;
                        // تم التعديل: استهداف student_id
                        const studentId = studentRow.querySelector('.student-info').dataset.studentId;
                        
                        const grades = {};
                        gradesDropdown.querySelectorAll('.grades-table tbody tr').forEach(row => {
                            const term1Inputs = row.querySelectorAll('[data-term="1"]');
                            const term2Inputs = row.querySelectorAll('[data-term="2"]');
                            const term3Inputs = row.querySelectorAll('[data-term="3"]');

                            grades['term1'] = [term1Inputs[0].value.trim(), term1Inputs[1].value.trim()];
                            grades['term2'] = [term2Inputs[0].value.trim(), term2Inputs[1].value.trim()];
                            grades['term3'] = [term3Inputs[0].value.trim(), term3Inputs[1].value.trim()];
                        });
                        
                        const postData = {
                            action: 'save_grades',
                            // تم التعديل: إرسال student_id
                            student_id: studentId,
                            year: modal.dataset.year,
                            branch: modal.dataset.branch,
                            part: modal.dataset.part,
                            classNum: modal.dataset.classNum,
                            subject_name: modal.dataset.subjectName,
                            grades: JSON.stringify(grades)
                        };

                        fetch('teacher.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams(postData)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showCustomAlert('success_grades', data.message);
                                gradesDropdown.querySelectorAll('.grade-input').forEach(input => input.disabled = true);
                                gradesDropdown.querySelector('.save-grades-btn').style.display = 'none';
                                gradesDropdown.querySelector('.edit-grades-btn').style.display = 'inline-block';
                            } else {
                                alert('خطأ: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('حدث خطأ أثناء الاتصال بالخادم.');
                            // Reset UI even if fetch fails
                            gradesDropdown.querySelectorAll('.grade-input').forEach(input => input.disabled = true);
                            gradesDropdown.querySelector('.save-grades-btn').style.display = 'none';
                            gradesDropdown.querySelector('.edit-grades-btn').style.display = 'inline-block';
                        });
                    }
                    
                    // Send Note
                    if (event.target.classList.contains('send-note-btn')) {
                        const gradesContainer = event.target.closest('.grades-content');
                        const noteTextarea = gradesContainer.querySelector('#note-textarea');
                        const noteText = noteTextarea.value.trim();
                        
                        if (noteText.length === 0) {
                            alert('الرجاء كتابة الملاحظة قبل الإرسال.');
                            return;
                        }

                        const gradesDropdownContainer = event.target.closest('.grades-dropdown-container');
                        const studentRow = gradesDropdownContainer.previousElementSibling;
                        const studentInfoElement = studentRow.querySelector('.student-info');
                        // تم التعديل: استهداف student_id
                        const studentId = studentInfoElement.dataset.studentId;
                        
                        const postData = {
                            action: 'send_note',
                            // تم التعديل: إرسال student_id
                            student_id: studentId,
                            note_text: noteText,
                            subject_name: modal.dataset.subjectName,
                            year: modal.dataset.year,
                            branch: modal.dataset.branch,
                            part: modal.dataset.part,
                            classNum: modal.dataset.classNum,
                        };

                        fetch('teacher.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams(postData)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showCustomAlert('success_note', data.message);
                                noteTextarea.value = '';
                            } else {
                                alert('خطأ: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('حدث خطأ أثناء إرسال الملاحظة.');
                        });
                    }
                });
            }
        });
    </script>
<?php endif; ?>
<script>
const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;
if (window.jQuery) $.ajaxSetup({headers: {'X-CSRF-Token': csrfToken}});
document.querySelectorAll('form').forEach(form => {
    const token=document.createElement('input');token.type='hidden';token.name='csrf_token';token.value=csrfToken;form.appendChild(token);
});
</script>
</body>
</html>
