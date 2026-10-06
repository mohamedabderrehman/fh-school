<?php

// Start the session to access session variables from management.txt
session_start();
require_once __DIR__.'/csrf.php';

// Check if the user is not logged in as an admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // If not logged in, redirect them back to the management login page
    header('Location: management.php');
    exit;
}

// Enable error display for easier debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// List of database paths
$db_paths = [
    'data/1/S/database.sqlite',
    'data/1/L/database.sqlite',
    'data/2/S/MAT/database.sqlite',
    'data/2/S/ECO/database.sqlite',
    'data/2/S/SC/database.sqlite',
    'data/2/S/TECH/database.sqlite',
    'data/2/L/LANG/database.sqlite',
    'data/2/L/PH&L/database.sqlite',
    'data/3/S/MAT/database.sqlite',
    'data/3/S/ECO/database.sqlite',
    'data/3/S/SC/database.sqlite',
    'data/3/L/LANG/database.sqlite',
    'data/3/L/PH&L/database.sqlite',
    'data/3/S/TECH/database.sqlite',
];

// List of subjects for each academic branch
$subject_mappings = [
    'علمي' => ['arabic_language', 'french_language', 'english_language', 'mathematics', 'history_geography', 'natural_sciences', 'physics_sciences', 'islamic_sciences', 'computer_science', 'physical_education', 'amazigh_language', 'technology'],
    'ادبي' => ['arabic_language', 'french_language', 'english_language', 'mathematics', 'history_geography', 'natural_sciences', 'physics_sciences', 'islamic_sciences', 'computer_science', 'physical_education', 'amazigh_language'],
    'العلوم التجريبية' => ['arabic_language', 'english_language', 'french_language', 'mathematics', 'natural_sciences', 'physics_sciences', 'history_geography', 'islamic_sciences', 'physical_education', 'amazigh_language'],
    'الرياضيات' => ['arabic_language', 'english_language', 'french_language', 'mathematics', 'natural_sciences', 'physics_sciences', 'history_geography', 'islamic_sciences', 'physical_education', 'amazigh_language'],
    'تقني رياضي' => ['arabic_language', 'english_language', 'french_language', 'mathematics', 'technology', 'physics_sciences', 'history_geography', 'islamic_sciences', 'physical_education', 'amazigh_language'],
    'تسيير واقتصاد' => ['arabic_language', 'english_language', 'french_language', 'mathematics', 'history_geography', 'islamic_sciences', 'economics', 'law', 'accounting_management', 'physical_education', 'amazigh_language'],
    'آداب وفلسفة' => ['arabic_language', 'french_language', 'english_language', 'history_geography', 'philosophy', 'islamic_sciences', 'mathematics', 'physics_sciences', 'natural_sciences', 'physical_education', 'amazigh_language'],
    'لغات أجنبية' => ['arabic_language', 'french_language', 'english_language', 'spanish', 'history_geography', 'islamic_sciences', 'mathematics', 'physical_education', 'amazigh_language'],
];

// Subject names in Arabic
$subject_names = [
    'arabic_language' => 'اللغة العربية', 
    'french_language' => 'اللغة الفرنسية', 
    'english_language' => 'اللغة الإنجليزية', 
    'spanish' => 'اللغة الإسبانية', 
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

$grades_and_subdivisions = [
    '1' => ['علمي' => 'علمي', 'ادبي' => 'ادبي'],
    '2' => ['العلوم التجريبية' => 'العلوم التجريبية', 'الرياضيات' => 'الرياضيات', 'تسيير واقتصاد' => 'تسيير واقتصاد', 'تقني رياضي' => 'تقني رياضي', 'لغات أجنبية' => 'لغات أجنبية', 'آداب وفلسفة' => 'آداب وفلسفة'],
    '3' => ['العلوم التجريبية' => 'العلوم التجريبية', 'الرياضيات' => 'الرياضيات', 'تسيير واقتصاد' => 'تسيير واقتصاد', 'لغات أجنبية' => 'لغات أجنبية', 'آداب وفلسفة' => 'آداب وفلسفة', 'تقني رياضي' => 'تقني رياضي'],
];

// --- Core Database Functions ---

/**
 * Function to determine database schema based on subdivision.
 */
function get_schema_for_subdivision($subdivision) {
    $schemas = [
        'علمي' => "arabic_language_term1 TEXT, arabic_language_term2 TEXT, arabic_language_term3 TEXT, french_language_term1 TEXT, french_language_term2 TEXT, french_language_term3 TEXT, english_language_term1 TEXT, english_language_term2 TEXT, english_language_term3 TEXT, mathematics_term1 TEXT, mathematics_term2 TEXT, mathematics_term3 TEXT, history_geography_term1 TEXT, history_geography_term2 TEXT, history_geography_term3 TEXT, natural_sciences_term1 TEXT, natural_sciences_term2 TEXT, natural_sciences_term3 TEXT, physics_sciences_term1 TEXT, physics_sciences_term2 TEXT, physics_sciences_term3 TEXT, islamic_sciences_term1 TEXT, islamic_sciences_term2 TEXT, islamic_sciences_term3 TEXT, computer_science_term1 TEXT, computer_science_term2 TEXT, computer_science_term3 TEXT, physical_education_term1 TEXT, physical_education_term2 TEXT, physical_education_term3 TEXT, amazigh_language_term1 TEXT, amazigh_language_term2 TEXT, amazigh_language_term3 TEXT, technology_term1 TEXT, technology_term2 TEXT, technology_term3 TEXT",
        'ادبي' => "arabic_language_term1 TEXT, arabic_language_term2 TEXT, arabic_language_term3 TEXT, french_language_term1 TEXT, french_language_term2 TEXT, french_language_term3 TEXT, english_language_term1 TEXT, english_language_term2 TEXT, english_language_term3 TEXT, mathematics_term1 TEXT, mathematics_term2 TEXT, mathematics_term3 TEXT, history_geography_term1 TEXT, history_geography_term2 TEXT, history_geography_term3 TEXT, natural_sciences_term1 TEXT, natural_sciences_term2 TEXT, natural_sciences_term3 TEXT, physics_sciences_term1 TEXT, physics_sciences_term2 TEXT, physics_sciences_term3 TEXT, islamic_sciences_term1 TEXT, islamic_sciences_term2 TEXT, islamic_sciences_term3 TEXT, computer_science_term1 TEXT, computer_science_term2 TEXT, computer_science_term3 TEXT, physical_education_term1 TEXT, physical_education_term2 TEXT, physical_education_term3 TEXT, amazigh_language_term1 TEXT, amazigh_language_term2 TEXT, amazigh_language_term3 TEXT",
        'العلوم التجريبية' => "arabic_language_term1 TEXT, arabic_language_term2 TEXT, arabic_language_term3 TEXT, english_language_term1 TEXT, english_language_term2 TEXT, english_language_term3 TEXT, french_language_term1 TEXT, french_language_term2 TEXT, french_language_term3 TEXT, mathematics_term1 TEXT, mathematics_term2 TEXT, mathematics_term3 TEXT, natural_sciences_term1 TEXT, natural_sciences_term2 TEXT, natural_sciences_term3 TEXT, physics_sciences_term1 TEXT, physics_sciences_term2 TEXT, physics_sciences_term3 TEXT, history_geography_term1 TEXT, history_geography_term2 TEXT, history_geography_term3 TEXT, islamic_sciences_term1 TEXT, islamic_sciences_term2 TEXT, islamic_sciences_term3 TEXT, physical_education_term1 TEXT, physical_education_term2 TEXT, physical_education_term3 TEXT, amazigh_language_term1 TEXT, amazigh_language_term2 TEXT, amazigh_language_term3 TEXT",
        'الرياضيات' => "arabic_language_term1 TEXT, arabic_language_term2 TEXT, arabic_language_term3 TEXT, english_language_term1 TEXT, english_language_term2 TEXT, english_language_term3 TEXT, french_language_term1 TEXT, french_language_term2 TEXT, french_language_term3 TEXT, mathematics_term1 TEXT, mathematics_term2 TEXT, mathematics_term3 TEXT, natural_sciences_term1 TEXT, natural_sciences_term2 TEXT, natural_sciences_term3 TEXT, physics_sciences_term1 TEXT, physics_sciences_term2 TEXT, physics_sciences_term3 TEXT, history_geography_term1 TEXT, history_geography_term2 TEXT, history_geography_term3 TEXT, islamic_sciences_term1 TEXT, islamic_sciences_term2 TEXT, islamic_sciences_term3 TEXT, physical_education_term1 TEXT, physical_education_term2 TEXT, physical_education_term3 TEXT, amazigh_language_term1 TEXT, amazigh_language_term2 TEXT, amazigh_language_term3 TEXT",
        'لغات أجنبية' => "arabic_language_term1 TEXT, arabic_language_term2 TEXT, arabic_language_term3 TEXT, french_language_term1 TEXT, french_language_term2 TEXT, french_language_term3 TEXT, english_language_term1 TEXT, english_language_term2 TEXT, english_language_term3 TEXT, spanish_term1 TEXT, spanish_term2 TEXT, spanish_term3 TEXT, history_geography_term1 TEXT, history_geography_term2 TEXT, history_geography_term3 TEXT, islamic_sciences_term1 TEXT, islamic_sciences_term2 TEXT, islamic_sciences_term3 TEXT, mathematics_term1 TEXT, mathematics_term2 TEXT, mathematics_term3 TEXT, physical_education_term1 TEXT, physical_education_term2 TEXT, physical_education_term3 TEXT, amazigh_language_term1 TEXT, amazigh_language_term2 TEXT, amazigh_language_term3 TEXT",
        'آداب وفلسفة' => "arabic_language_term1 TEXT, arabic_language_term2 TEXT, arabic_language_term3 TEXT, french_language_term1 TEXT, french_language_term2 TEXT, french_language_term3 TEXT, english_language_term1 TEXT, english_language_term2 TEXT, english_language_term3 TEXT, history_geography_term1 TEXT, history_geography_term2 TEXT, history_geography_term3 TEXT, philosophy_term1 TEXT, philosophy_term2 TEXT, philosophy_term3 TEXT, islamic_sciences_term1 TEXT, islamic_sciences_term2 TEXT, islamic_sciences_term3 TEXT, mathematics_term1 TEXT, mathematics_term2 TEXT, mathematics_term3 TEXT, physics_sciences_term1 TEXT, physics_sciences_term2 TEXT, physics_sciences_term3 TEXT, natural_sciences_term1 TEXT, natural_sciences_term2 TEXT, natural_sciences_term3 TEXT, physical_education_term1 TEXT, physical_education_term2 TEXT, physical_education_term3 TEXT, amazigh_language_term1 TEXT, amazigh_language_term2 TEXT, amazigh_language_term3 TEXT",
        'تقني رياضي' => "arabic_language_term1 TEXT, arabic_language_term2 TEXT, arabic_language_term3 TEXT, english_language_term1 TEXT, english_language_term2 TEXT, english_language_term3 TEXT, french_language_term1 TEXT, french_language_term2 TEXT, french_language_term3 TEXT, mathematics_term1 TEXT, mathematics_term2 TEXT, mathematics_term3 TEXT, technology_term1 TEXT, technology_term2 TEXT, technology_term3 TEXT, physics_sciences_term1 TEXT, physics_sciences_term2 TEXT, physics_sciences_term3 TEXT, history_geography_term1 TEXT, history_geography_term2 TEXT, history_geography_term3 TEXT, islamic_sciences_term1 TEXT, islamic_sciences_term2 TEXT, islamic_sciences_term3 TEXT, physical_education_term1 TEXT, physical_education_term2 TEXT, physical_education_term3 TEXT, amazigh_language_term1 TEXT, amazigh_language_term2 TEXT, amazigh_language_term3 TEXT",
        'تسيير واقتصاد' => "arabic_language_term1 TEXT, arabic_language_term2 TEXT, arabic_language_term3 TEXT, english_language_term1 TEXT, english_language_term2 TEXT, english_language_term3 TEXT, french_language_term1 TEXT, french_language_term2 TEXT, french_language_term3 TEXT, mathematics_term1 TEXT, mathematics_term2 TEXT, mathematics_term3 TEXT, history_geography_term1 TEXT, history_geography_term2 TEXT, history_geography_term3 TEXT, islamic_sciences_term1 TEXT, islamic_sciences_term2 TEXT, islamic_sciences_term3 TEXT, economics_term1 TEXT, economics_term2 TEXT, economics_term3 TEXT, law_term1 TEXT, law_term2 TEXT, law_term3 TEXT, accounting_management_term1 TEXT, accounting_management_term2 TEXT, accounting_management_term3 TEXT, physical_education_term1 TEXT, physical_education_term2 TEXT, physical_education_term3 TEXT, amazigh_language_term1 TEXT, amazigh_language_term2 TEXT, amazigh_language_term3 TEXT",
    ];
    return $schemas[$subdivision] ?? null;
}

/**
 * Function to determine DB path based on grade and subdivision.
 */
function get_db_path_for_class($grade, $subdivision) {
    $grade_map = ['1' => '1', '2' => '2', '3' => '3'];
    $subdivision_map = [
        'علمي' => ['folder' => 'S', 'file' => 'database.sqlite'],
        'ادبي' => ['folder' => 'L', 'file' => 'database.sqlite'],
        'العلوم التجريبية' => ['folder' => 'S/SC', 'file' => 'database.sqlite'],
        'الرياضيات' => ['folder' => 'S/MAT', 'file' => 'database.sqlite'],
        'تقني رياضي' => ['folder' => 'S/TECH', 'file' => 'database.sqlite'],
        'تسيير واقتصاد' => ['folder' => 'S/ECO', 'file' => 'database.sqlite'],
        'آداب وفلسفة' => ['folder' => 'L/PH&L', 'file' => 'database.sqlite'],
        'لغات أجنبية' => ['folder' => 'L/LANG', 'file' => 'database.sqlite'],
    ];

    if (isset($grade_map[$grade]) && isset($subdivision_map[$subdivision])) {
        if ($grade == '1' && ($subdivision == 'العلوم التجريبية' || $subdivision == 'الرياضيات' || $subdivision == 'تقني رياضي' || $subdivision == 'تسيير واقتصاد')) {
            return 'data/1/S/database.sqlite';
        } elseif ($grade == '1' && ($subdivision == 'آداب وفلسفة' || $subdivision == 'لغات أجنبية')) {
            return 'data/1/L/database.sqlite';
        }
        return "data/{$grade_map[$grade]}/{$subdivision_map[$subdivision]['folder']}/{$subdivision_map[$subdivision]['file']}";
    }
    return null;
}

/**
 * Function to determine table name based on subdivision.
 */
function get_table_name_for_subdivision($subdivision) {
    $mapping = [
        'علمي' => 'scientific_students',
        'ادبي' => 'literary_students',
        'العلوم التجريبية' => 'experimental_sciences_students',
        'الرياضيات' => 'mathematics_students',
        'تقني رياضي' => 'technical_mathematics_students',
        'تسيير واقتصاد' => 'management_economics_students',
        'آداب وفلسفة' => 'arts_philosophy_students',
        'لغات أجنبية' => 'foreign_languages_students',
    ];
    return $mapping[$subdivision] ?? null;
}

/**
 * Create table if it doesn't exist.
 */
function create_table_if_not_exists($pdo, $table_name, $subdivision) {
    $grade_columns = get_schema_for_subdivision($subdivision);
    if (!$grade_columns) return;
    $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
        student_id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        class_name TEXT NOT NULL,
        note TEXT,
        {$grade_columns},
        absence_count INTEGER DEFAULT 0,
        absence_details TEXT,
        username_password TEXT NOT NULL
    )";
    $pdo->exec($sql);
}

/**
 * Function to count students in a single database.
 */
function get_student_count($db_path) {
    if (!file_exists($db_path)) {
        return 0;
    }
    $total_students_in_db = 0;
    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
            $total_students_in_db += (int)$stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log("Error in get_student_count for $db_path: " . $e->getMessage());
        return 0;
    }
    return $total_students_in_db;
}

/**
 * Function to find a student by username_password AND full_name.
 * Returns student_id if found, otherwise false.
 */
function find_student_by_username_password($username_password, $full_name, $grade, $subdivision) {
    $db_path = get_db_path_for_class($grade, $subdivision);
    $table_name = get_table_name_for_subdivision($subdivision);

    if (!$db_path || !$table_name) {
        return false;
    }

    if (!file_exists($db_path)) {
        return false;
    }

    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "SELECT student_id FROM `{$table_name}` WHERE username_password = ? AND full_name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username_password, $full_name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['student_id'] : false;
    } catch (PDOException $e) {
        error_log("Error finding student: " . $e->getMessage());
        return false;
    }
}

/**
 * Function to update an existing student.
 */
function update_student($student_id, $data) {
    $db_path = get_db_path_for_class($data['grade'], $data['subdivision']);
    $table_name = get_table_name_for_subdivision($data['subdivision']);
    if (!$db_path || !$table_name) {
        return false;
    }

    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $columns = [
            'full_name', 'class_name', 'note', 'absence_details', 'absence_count', 'username_password'
        ];
        $params = [
            $data['full_name'],
            $data['class_name'],
            $data['note'],
            $data['absence_details'],
            $data['absence_count'],
            $data['username_password']
        ];

        $grades = json_decode($data['grades'], true);
        foreach ($grades as $key => $value) {
            $columns[] = $key;
            $params[] = $value;
        }

        $set_clause = implode(', ', array_map(function($c) { return "`{$c}` = ?"; }, $columns));
        $params[] = $student_id;

        $sql = "UPDATE `{$table_name}` SET {$set_clause} WHERE student_id = ?";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error updating student: " . $e->getMessage());
        return false;
    }
}

/**
 * Function to add or update a single student to the database.
 */
function add_or_update_student($data) {
    $db_path = get_db_path_for_class($data['grade'], $data['subdivision']);
    $table_name = get_table_name_for_subdivision($data['subdivision']);
    if (!$db_path || !$table_name) {
        error_log("Could not find DB path or table name for grade {$data['grade']} and subdivision {$data['subdivision']}");
        return false;
    }

    if (!file_exists(dirname($db_path))) {
        mkdir(dirname($db_path), 0755, true);
    }

    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        create_table_if_not_exists($pdo, $table_name, $data['subdivision']);
    } catch (PDOException $e) {
        error_log("Error connecting to DB or creating table: " . $e->getMessage());
        return false;
    }

    // Check if the student already exists based on username_password AND full_name
    $student_id = find_student_by_username_password($data['username_password'], $data['full_name'], $data['grade'], $data['subdivision']);

    if ($student_id) {
        // Student exists, update their record
        return update_student($student_id, $data);
    } else {
        // Student does not exist, insert new record
        $absence_details = $data['absence_details'] ?? '';
        $absence_count = $data['absence_count'] ?? 0;

        $columns = [
            'full_name', 'class_name', 'note', 'absence_details', 'absence_count', 'username_password'
        ];
        $params = [
            $data['full_name'],
            $data['class_name'],
            $data['note'],
            $absence_details,
            $absence_count,
            $data['username_password']
        ];

        $grades = json_decode($data['grades'], true);
        foreach ($grades as $key => $value) {
            $columns[] = $key;
            $params[] = $value;
        }

        $column_names = implode(', ', array_map(function($c) { return "`{$c}`"; }, $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = "INSERT INTO `{$table_name}` ({$column_names}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute($params);
    }
}

/**
 * Function to add a single student to the database (for individual additions).
 */
function add_student($data) {
    $db_path = get_db_path_for_class($data['grade'], $data['subdivision']);
    $table_name = get_table_name_for_subdivision($data['subdivision']);
    if (!$db_path || !$table_name) {
        error_log("Could not find DB path or table name for grade {$data['grade']} and subdivision {$data['subdivision']}");
        return false;
    }

    if (!file_exists(dirname($db_path))) {
        mkdir(dirname($db_path), 0755, true);
    }

    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        create_table_if_not_exists($pdo, $table_name, $data['subdivision']);
        
        $absence_details = $data['absence_details'] ?? '';
        $absence_count = substr_count($absence_details, 'يوم');

        $columns = [
            'full_name', 'class_name', 'note', 'absence_details', 'absence_count', 'username_password'
        ];
        $params = [
            $data['full_name'],
            $data['class_name'],
            $data['note'],
            $absence_details,
            $absence_count,
            $data['username_password']
        ];
        
        $grades = json_decode($data['grades'], true);
        foreach ($grades as $key => $value) {
            $columns[] = $key;
            $params[] = $value;
        }

        $column_names = implode(', ', array_map(function($c) { return "`{$c}`"; }, $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        
        $sql = "INSERT INTO `{$table_name}` ({$column_names}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error in add_student for table `{$table_name}`: " . $e->getMessage());
        return false;
    }
}

/**
 * Process a CSV file and upload students in bulk.
 */
function process_csv_upload($csv_data) {
    global $subject_mappings;
    $rows = array_map(function($line) {
        return str_getcsv($line, ',', '"', '\\');
    }, explode("\n", $csv_data));

    $processed_count = 0;

    if (empty($rows) || count($rows) < 2) {
        return 0;
    }

    $header = array_shift($rows);

    foreach ($rows as $row) {
        if (empty(array_filter($row))) {
            continue;
        }

        $full_name = $row[0] ?? '';
        $class_name = $row[1] ?? '';
        $absence_details = $row[2] ?? '';
        $note = $row[3] ?? '';
        $username_password = $row[4] ?? '';

        if (empty($full_name) || empty($class_name) || empty($username_password)) {
            continue;
        }

        preg_match('/^(\d)\s+([^\d]+)/', $class_name, $matches);
        $grade = $matches[1] ?? '';
        $subdivision = trim($matches[2] ?? '');

        if (empty($grade) || empty($subdivision)) {
            continue;
        }

        $grades_data = [];
        $subjects = $subject_mappings[$subdivision] ?? [];

        $header_map = [];
        for ($i = 5; $i < count($header); $i++) {
            $header_map[$header[$i]] = $i;
        }

        foreach ($subjects as $subject) {
            $term1_key = $subject . '_term1';
            $term2_key = $subject . '_term2';
            $term3_key = $subject . '_term3';

            $term1_value = isset($header_map[$term1_key]) ? trim($row[$header_map[$term1_key]] ?? '') : '';
            $term2_value = isset($header_map[$term2_key]) ? trim($row[$header_map[$term2_key]] ?? '') : '';
            $term3_value = isset($header_map[$term3_key]) ? trim($row[$header_map[$term3_key]] ?? '') : '';

            $grades_data[$term1_key] = $term1_value;
            $grades_data[$term2_key] = $term2_value;
            $grades_data[$term3_key] = $term3_value;
        }

        $absence_count = 0;
        if (!empty($absence_details)) {
            $absence_count = substr_count($absence_details, 'يوم');
        }

        $student_data = [
            'full_name' => $full_name,
            'username_password' => $username_password,
            'class_name' => $class_name,
            'note' => $note,
            'absence_details' => $absence_details,
            'absence_count' => $absence_count,
            'grade' => $grade,
            'subdivision' => $subdivision,
            'grades' => json_encode($grades_data)
        ];

        if (add_or_update_student($student_data)) {
            $processed_count++;
        }
    }
    return $processed_count;
}

/**
 * Function to get absences for a specific date or date range.
 */
function get_absences_for_range($start_date, $end_date) {
    global $db_paths;
    $all_absences = [];
    
    foreach ($db_paths as $path) {
        if (!file_exists($path)) {
            continue;
        }
        
        try {
            $pdo = new PDO("sqlite:" . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("SELECT full_name, class_name, absence_details FROM `{$table}` WHERE absence_details IS NOT NULL AND absence_details != ''");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $details = $row['absence_details'];
                    preg_match_all('/(.*? يوم (\d{4}-\d{2}-\d{2}): من الساعة \d{2}:\d{2} إلى الساعة \d{2}:\d{2})/', $details, $matches, PREG_SET_ORDER);
                    
                    $student_absences = [];
                    foreach ($matches as $match) {
                        $full_text = $match[1];
                        $absence_date = $match[2];

                        if ($absence_date >= $start_date && $absence_date <= $end_date) {
                            $subject_time_parts = explode(':', $full_text, 2);
                            $subject = trim(str_replace(" يوم " . $absence_date, "", $subject_time_parts[0]));
                            $time_info = isset($subject_time_parts[1]) ? trim($subject_time_parts[1]) : '';
                            
                            $student_absences[] = [
                                'subject' => $subject,
                                'date' => $absence_date,
                                'time' => $time_info
                            ];
                        }
                    }
                    
                    if (!empty($student_absences)) {
                        $all_absences[] = [
                            'full_name' => $row['full_name'],
                            'class_name' => $row['class_name'],
                            'absences' => $student_absences
                        ];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching absences from " . basename($path) . ": " . $e->getMessage());
        }
    }
    return $all_absences;
}

/**
 * Get student information from database.
 */
function get_student_info($db_path, $full_name, $class_name) {
    if (!file_exists($db_path)) {
        return null;
    }
    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE full_name = ? AND class_name = ?");
            $stmt->execute([$full_name, $class_name]);
            $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($student_data) {
                return $student_data;
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching student info: " . $e->getMessage());
    }
    return null;
}

/**
 * Update student data in database.
 */
function update_student_data($db_path, $student_id, $table_name, $updates) {
    if (!file_exists($db_path)) {
        return false;
    }
    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $set_parts = [];
        $params = [];
        foreach ($updates as $key => $value) {
            $set_parts[] = "`{$key}` = ?";
            $params[] = $value;
        }
        $params[] = $student_id;
        
        $sql = "UPDATE `{$table_name}` SET " . implode(', ', $set_parts) . " WHERE student_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error updating student data: " . $e->getMessage());
        return false;
    }
}

/**
 * Add note to student record.
 */
function add_student_note($db_path, $student_id, $table_name, $new_note) {
    if (!file_exists($db_path)) {
        return false;
    }
    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt_select = $pdo->prepare("SELECT note FROM `{$table_name}` WHERE student_id = ?");
        $stmt_select->execute([$student_id]);
        $current_note = $stmt_select->fetchColumn();

        $formatted_new_note = '| الإدارة ' . date('Y-m-d') . ': ' . $new_note;
        $updated_note = ($current_note ? $current_note . ' ' : '') . $formatted_new_note;

        $stmt_update = $pdo->prepare("UPDATE `{$table_name}` SET note = ? WHERE student_id = ?");
        return $stmt_update->execute([$updated_note, $student_id]);
    } catch (PDOException $e) {
        error_log("Error adding note: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete student from database.
 */
function delete_student($db_path, $student_id, $table_name) {
    if (!file_exists($db_path)) {
        return false;
    }
    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("DELETE FROM `{$table_name}` WHERE student_id = ?");
        return $stmt->execute([$student_id]);
    } catch (PDOException $e) {
        error_log("Error deleting student: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all students from all databases.
 */
function get_all_students() {
    global $db_paths;
    $all_students = [];

    foreach ($db_paths as $path) {
        if (!file_exists($path)) {
            continue;
        }
        try {
            $pdo = new PDO("sqlite:" . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT * FROM `{$table}`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $row['db_path'] = $path;
                    $row['table_name'] = $table;
                    $all_students[] = $row;
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching students from " . basename($path) . ": " . $e->getMessage());
        }
    }
    return $all_students;
}

/**
 * Main function to get overall statistics.
 */
function get_stats() {
    global $db_paths;
    
    $total_students = 0;
    foreach ($db_paths as $path) {
        $total_students += get_student_count($path);
    }
    
    $dates_data = [];
    $day_names = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_of_week = date('w', strtotime($date));
        $day_name = $day_names[$day_of_week];
        
        $total_absences_for_this_date = 0;
        $absences_data_for_date = get_absences_for_range($date, $date);
        
        foreach ($absences_data_for_date as $student_data) {
            $total_absences_for_this_date += count($student_data['absences']);
        }
        
        $dates_data[] = [
            'date' => $date,
            'day_name' => $day_name,
            'absences' => $total_absences_for_this_date
        ];
    }
    
    return [
        'totalStudents' => $total_students,
        'datesData' => $dates_data
    ];
}

// Process AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start output buffering to capture any unwanted output
    ob_start();
    
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'uploadCsv') {
        $csv_data = $_POST['csv_data'] ?? '';
        $processed_count = process_csv_upload($csv_data);
        
        // Get any error messages that might have been produced
        $error_message = ob_get_clean();

        if (empty($error_message)) {
            echo json_encode(['success' => true, 'processed_count' => $processed_count]);
        } else {
            // Send back the error message to the client
            echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $error_message]);
        }
        exit;
    } elseif ($action === 'getAbsences') {
        ob_end_clean();
        $startDate = $_POST['startDate'] ?? date('Y-m-d');
        $endDate = $_POST['endDate'] ?? date('Y-m-d');
        echo json_encode(get_absences_for_range($startDate, $endDate));
        exit;
    } elseif ($action === 'getStudents') {
        ob_end_clean();
        echo json_encode(get_all_students());
        exit;
    } elseif ($action === 'deleteStudent') {
        ob_end_clean();
        $db_path = $_POST['db_path'];
        $table_name = $_POST['table_name'];
        $student_id = $_POST['student_id'];
        $result = delete_student($db_path, $student_id, $table_name);
        echo json_encode(['success' => $result]);
        exit;
    } elseif ($action === 'updateStudent') {
        ob_end_clean();
        $db_path = $_POST['db_path'];
        $table_name = $_POST['table_name'];
        $student_id = $_POST['student_id'];
        $updates = json_decode($_POST['updates'], true);
        $result = update_student_data($db_path, $student_id, $table_name, $updates);
        echo json_encode(['success' => $result]);
        exit;
    } elseif ($action === 'addNote') {
        ob_end_clean();
        $db_path = $_POST['db_path'];
        $table_name = $_POST['table_name'];
        $student_id = $_POST['student_id'];
        $note = $_POST['note'];
        
        $result = add_student_note($db_path, $student_id, $table_name, $note);
        echo json_encode(['success' => $result]);
        exit;
    } elseif ($action === 'addStudent') {
        ob_end_clean();
        $student_data = [
            'full_name' => $_POST['full_name'] ?? '',
            'username_password' => $_POST['username_password'] ?? '',
            'class_name' => $_POST['class_name'] ?? '',
            'note' => $_POST['note'] ?? '',
            'absence_details' => $_POST['absence_details'] ?? '',
            'grade' => $_POST['grade'] ?? '',
            'subdivision' => $_POST['subdivision'] ?? '',
            'grades' => $_POST['grades'] ?? '[]'
        ];
        $result = add_student($student_data);
        echo json_encode(['success' => $result]);
        exit;
    }
    ob_end_clean();
}

// Generate data for the front-end
$stats_data = get_stats();
$js_data = json_encode($stats_data);
$js_subjects = json_encode($subject_names);
$js_subject_mappings = json_encode($subject_mappings);
$js_filters = json_encode($grades_and_subdivisions);
?>
<!DOCTYPE html>
<html>
	<head> <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>إدارة التلاميذ - واجهة مستخدم</title> <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> <style>:root{--primary-color:#ffc107;--primary-dark-color:#e0a800;--secondary-color:#fffbea;--text-color:#5c4c00;--light-bg:#fffdf4;--card-bg:#fff;--border-color:#ffecb3;--shadow:0 6px 20px rgba(0,0,0,0.08);--border-radius:12px;--student-card-bg:#fdfaf0;--delete-btn-color:#d32f2f;--save-btn-color:#4caf50;--btn-hover-color:#b88a00}*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Cairo',sans-serif;background:rgba(255,255,255,0.9);color:var(--text-color);line-height:1.6;direction:rtl;text-align:right;min-height:100vh}.container{max-width:1200px;margin:40px auto;padding:0 20px}.header{text-align:center;margin-bottom:40px}.header h1{color:var(--primary-dark-color);font-size:2.5em;position:relative;padding-bottom:15px;display:inline-block}.header h1::after{content:'';position:absolute;bottom:0;right:0;width:50%;height:4px;background-color:var(--primary-color);border-radius:2px;transition:width .3s ease}.header h1:hover::after{width:100%}.tabs-nav{display:flex;background-color:var(--card-bg);border-radius:var(--border-radius);box-shadow:var(--shadow);margin-bottom:30px;overflow:hidden;border:1px solid var(--border-color)}.tabs-nav-item{flex-grow:1;text-align:center;padding:18px 0;font-size:1.1em;font-weight:700;color:#888;cursor:pointer;transition:all .3s ease;position:relative}.tabs-nav-item:hover{color:var(--primary-dark-color)}.tabs-nav-item.active{color:var(--text-color);background-color:var(--secondary-color);border-bottom:3px solid var(--primary-color)}.content-section{display:none;background-color:var(--card-bg);padding:30px;border-radius:var(--border-radius);box-shadow:var(--shadow)}.content-section.active{display:block}.section-heading{color:var(--primary-dark-color);font-size:1.8em;margin-bottom:25px;border-bottom:2px solid var(--border-color);padding-bottom:10px;text-align:center}.placeholder-box{padding:20px;text-align:center;color:#999;border:2px dashed var(--border-color);border-radius:var(--border-radius)}.stats-container{display:flex;flex-direction:column;gap:30px}.stats-box{background-color:var(--secondary-color);border:1px dashed var(--primary-color);padding:25px;text-align:center;border-radius:8px}.stats-label{font-weight:700;font-size:1.1em;color:var(--primary-dark-color);margin-bottom:10px}.stats-number{font-size:2.5em;font-weight:700;color:var(--text-color)}.chart-wrapper{background:var(--card-bg);padding:30px;border-radius:var(--border-radius);box-shadow:var(--shadow);border:1px solid var(--border-color)}.chart-wrapper hr{border:0;height:1px;background-color:var(--border-color);margin:20px auto 10px;width:80%}.chart-wrapper .chart-label{text-align:center;font-weight:600;color:#666;margin-top:5px}.absence-search-container{display:flex;flex-direction:column;gap:25px}.absence-today-box{background-color:var(--secondary-color);border:1px dashed var(--primary-color);padding:25px;border-radius:8px}.absence-today-box h3{margin-bottom:15px;color:var(--primary-dark-color);border-bottom:1px solid var(--border-color);padding-bottom:10px;font-size:1.4em}.grade-button,.subdivision-button,.department-button,.csv-button,.submit-btn,.action-buttons .save-btn,.action-buttons .csv-btn,.note-sender .send-btn,.csv-upload-btn,.submit-csv-btn{-webkit-tap-highlight-color:transparent}.grade-button,.subdivision-button,.department-button{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;margin-top:10px;font-size:1.1em;font-weight:bold;color:var(--text-color);background-color:#fffbea;border:1px solid var(--border-color);border-radius:var(--border-radius);cursor:pointer;transition:all .3s ease;width:100%}.grade-button.open,.subdivision-button.open,.department-button.open{background-color:#fceecb}.grade-button .toggle-icon,.subdivision-button .toggle-icon,.department-button .toggle-icon{font-size:1.4em;transform:rotate(0deg);transition:transform .3s ease}.grade-button.open .toggle-icon,.subdivision-button.open .toggle-icon,.department-button.open .toggle-icon{transform:rotate(90deg)}.student-list{list-style:none;padding:10px 0 0 0;display:none;max-height:400px;overflow-y:auto}.student-list li{background-color:var(--light-bg);border:1px solid var(--border-color);padding:10px 15px;margin-bottom:8px;border-radius:6px;display:flex;flex-direction:column;align-items:flex-start;cursor:pointer;transition:all .2s ease}.student-list li:hover{background-color:#fceecb}.student-list li .student-name-row{display:flex;justify-content:space-between;align-items:center;width:100%;font-weight:600}.student-list li .toggle-details{font-size:1.2em;color:var(--primary-dark-color);transition:transform .3s ease}.student-list li.open .toggle-details{transform:rotate(90deg)}.absence-details{margin-top:10px;font-size:.9em;color:#666;padding-right:20px;border-right:2px solid var(--primary-color);display:none}.absence-details p{margin:5px 0}.csv-button-container{text-align:center;margin-top:20px}.csv-button{padding:10px 20px;background-color:var(--primary-dark-color);color:white;border:0;border-radius:5px;font-weight:bold;cursor:pointer;transition:background-color .3s ease}.csv-button:hover{background-color:var(--btn-hover-color)}.date-filter-container{display:flex;align-items:center;gap:15px;background-color:var(--secondary-color);padding:20px;border-radius:8px;border:1px dashed var(--primary-color)}.date-filter-container label{font-weight:600}.date-filter-container input[type="date"]{padding:8px;border:1px solid var(--border-color);border-radius:5px;font-family:'Cairo',sans-serif}.date-filter-container button{padding:10px 20px;background-color:var(--primary-dark-color);color:white;border:0;border-radius:5px;font-weight:bold;cursor:pointer}.results-section{display:flex;flex-direction:column;gap:20px}.display-search-box{margin-bottom:20px}.display-search-box input{width:100%;padding:12px;font-size:1.1em;border-radius:8px;border:1px solid var(--border-color);font-family:'Cairo',sans-serif}.student-item-card{background:var(--student-card-bg);border:1px solid #ffecb3;border-radius:10px;margin-bottom:15px;padding:15px;transition:all .3s ease}.student-item-card:hover{box-shadow:0 4px 15px rgba(0,0,0,0.05);transform:translateY(-2px)}.student-header{display:flex;justify-content:space-between;align-items:center;font-weight:bold;cursor:pointer;color:var(--text-color)}.student-header h4{font-size:1.1em;margin:0;color:var(--primary-dark-color);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex-grow:1}.student-header .actions{display:flex;gap:10px;align-items:center}.student-header .delete-btn,.student-header .toggle-btn{background:0;border:0;cursor:pointer;font-size:1.5em;transition:transform .2s ease;padding:0 5px}.student-header .delete-btn{color:var(--delete-btn-color)}.student-header .toggle-btn{color:var(--primary-dark-color);transform:rotate(0)}.student-header.open .toggle-btn{transform:rotate(90deg)}.student-details{padding-top:15px;margin-top:15px;border-top:1px dashed var(--border-color);display:none}.student-details .detail-group{margin-bottom:20px}.student-details .detail-group h5{font-size:1.1em;margin-bottom:10px;border-bottom:2px solid #ffecb3;padding-bottom:5px}.student-details .detail-group label{display:block;font-weight:600;margin-bottom:5px}.student-details .detail-group input,.student-details .detail-group textarea{width:100%;padding:8px;border-radius:5px;border:1px solid var(--border-color);background-color:var(--light-bg);font-family:'Cairo',sans-serif;resize:vertical}.student-details .detail-group textarea{min-height:80px}.grades-table-container{overflow-x:auto;margin-top:15px}.grades-table{width:100%;border-collapse:collapse;min-width:600px}.grades-table th,.grades-table td{border:1px solid var(--border-color);padding:10px;text-align:center}.grades-table th{background-color:var(--secondary-color);font-weight:bold}.grades-table td input{width:70px;text-align:center;border:1px solid #ccc;padding:5px;border-radius:4px;margin:2px}.action-buttons{margin-top:20px;display:flex;gap:10px;justify-content:flex-end}.action-buttons .save-btn,.action-buttons .csv-btn,.note-sender .send-btn{padding:10px 20px;border:0;border-radius:5px;font-weight:bold;cursor:pointer;transition:background-color .3s ease;background-color:var(--primary-dark-color);color:white}.action-buttons .save-btn:hover,.action-buttons .csv-btn:hover,.note-sender .send-btn:hover{background-color:var(--btn-hover-color)}.note-sender{margin-top:20px;display:flex;flex-direction:column;gap:10px}.note-sender textarea{width:100%;min-height:60px;padding:10px;border-radius:5px;border:1px solid var(--border-color);font-family:'Cairo',sans-serif}.add-student-container{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start}.add-single-student,.under-development-section{background:var(--light-bg);padding:30px;border-radius:var(--border-radius);border:1px solid var(--border-color)}.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:20px}.form-group{display:flex;flex-direction:column}.form-group label{font-weight:600;margin-bottom:8px}.form-group input,.form-group select,.form-group textarea{padding:10px;border-radius:8px;border:1px solid var(--border-color);background-color:white;font-family:'Cairo',sans-serif;width:100%}.form-group select:disabled{background-color:#f5f5f5;cursor:not-allowed}.more-options-btn,.submit-btn{width:100%;padding:12px;margin-top:15px;font-size:1.1em;font-weight:bold;border-radius:8px;border:0;cursor:pointer;transition:background-color .3s ease}.more-options-btn{background-color:#fff;color:var(--text-color);border:2px solid var(--primary-color)}.more-options-btn:hover{background-color:var(--secondary-color)}.more-options-btn:disabled{background-color:#eee;border-color:#ddd;color:#999;cursor:not-allowed}.submit-btn{background-color:var(--primary-dark-color);color:white}.submit-btn:hover{background-color:var(--btn-hover-color)}#moreOptionsContainer{margin-top:25px;padding-top:25px;border-top:2px dashed var(--border-color)}.subject-card{background:var(--card-bg);border:1px solid var(--border-color);border-radius:8px;margin-bottom:10px}.subject-header{display:flex;justify-content:space-between;align-items:center;padding:15px;cursor:pointer;font-weight:bold}.subject-header .toggle-icon{transition:transform .3s ease}.subject-header.open .toggle-icon{transform:rotate(90deg)}.grades-fields{display:none;padding:10px;border-top:1px dashed var(--border-color)}.grades-fields .term-group{margin-bottom:15px}.grades-fields .term-group h6{font-size:1em;color:var(--primary-dark-color);margin-bottom:10px}.grades-fields .grade-inputs{display:grid;grid-template-columns:1fr 1fr;gap:10px}.grades-fields .grade-inputs .form-group{margin-bottom:0}.grades-fields .grade-inputs input{width:100%}.under-development-section{text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%}.under-development-section .development-message{margin-top:20px;font-size:1.2em;color:#999}.under-development-section .fas.fa-cog{font-size:3em;color:#ffc107;animation:spin 2s linear infinite}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.5)}.modal-content{background-color:#fff;margin:15% auto;padding:30px;border:1px solid #888;width:80%;max-width:800px;border-radius:var(--border-radius);position:relative;box-shadow:var(--shadow)}.close-button{color:#aaa;float:left;font-size:28px;font-weight:bold;cursor:pointer}.close-button:hover,.close-button:focus{color:black}.modal-body{max-height:70vh;overflow-y:auto;text-align:right}.modal-body h3{border-bottom:1px solid #eee;padding-bottom:10px;margin-bottom:15px}.modal-body pre{background-color:#f4f4f4;padding:15px;border-radius:5px;direction:ltr;text-align:left;overflow-x:auto;white-space:pre}.modal-body ul{padding-right:20px}@media(max-width:992px){.add-student-container{grid-template-columns:1fr}}@media(max-width:768px){.container{margin:20px auto}.header h1{font-size:2em}.tabs-nav{flex-direction:column}.date-filter-container,.display-controls{flex-direction:column;align-items:stretch}}</style> </head> <body> <div class="container"> <div class="tabs-nav"> <div class="tabs-nav-item active" data-tab="stats">قسم الإحصائيات</div> <div class="tabs-nav-item" data-tab="absences">باحث الغيابات</div> <div class="tabs-nav-item" data-tab="display">قسم عرض التلاميذ</div> <div class="tabs-nav-item" data-tab="add">قسم إضافة تلميذ</div> </div> <div id="stats" class="content-section active"> <h2 class="section-heading">إحصائيات عامة</h2> <div class="stats-container"> <div class="stats-box"> <div class="stats-label">إجمالي عدد التلاميذ</div> <div class="stats-number" id="total-students-count">...</div> </div> <div class="chart-wrapper"> <canvas id="absencesChart"></canvas> <hr><div class="chart-label">غيابات الأسبوع</div> </div> </div> </div> <div id="absences" class="content-section"> <h2 class="section-heading">باحث الغيابات</h2> <div class="absence-search-container"> <div class="date-filter-container"> <label for="startDate">من تاريخ:</label> <input type="date" id="startDate" name="startDate"> <label for="endDate">إلى تاريخ:</label> <input type="date" id="endDate" name="endDate"> <button onclick="searchAbsences()">بحث</button> <button onclick="clearDates()">مسح التواريخ</button> </div> <div class="absence-today-box"> <h3 id="absences-title">غيابات اليوم</h3> <div id="absences-results" class="results-section"></div> <div class="csv-button-container"> <button id="downloadAbsencesCsvBtn" class="csv-button">تحميل بيانات الغيابات (CSV)</button> </div> </div> </div> </div> <div id="display" class="content-section"> <h2 class="section-heading">عرض بيانات التلاميذ</h2> <div class="display-search-box"> <input type="text" id="studentSearchInput" placeholder="بحث باسم التلميذ..."> </div> <div id="searchResults" class="results-section" style="display:none"></div> <div id="studentsDisplayList" class="results-section"> <div class="placeholder-box"><p>يتم جلب بيانات التلاميذ...</p></div> </div> </div> <div id="add" class="content-section"> <div class="add-student-container"> <div class="add-single-student"> <h2 class="section-heading">إضافة تلميذ فردي</h2> <form id="addStudentForm"> <div class="form-grid"> <div class="form-group"><label for="firstName">الاسم:</label><input type="text" id="firstName" name="firstName" required></div> <div class="form-group"><label for="lastName">اللقب:</label><input type="text" id="lastName" name="lastName" required></div> <div class="form-group"><label for="username">اسم المستخدم:</label><input type="text" id="username" name="username" required></div> <div class="form-group"><label for="password">كلمة المرور:</label><input type="text" id="password" name="password" required></div> </div> <div class="form-grid"> <div class="form-group"> <label for="gradeLevel">الصف:</label> <select id="gradeLevel" name="gradeLevel" required> <option value="" disabled selected>اختر الصف...</option> <option value="1">أولى ثانوي</option> <option value="2">ثانية ثانوي</option> <option value="3">ثالثة ثانوي</option> </select> </div> <div class="form-group"> <label for="subdivision">الشعبة:</label> <select id="subdivision" name="subdivision" required disabled><option value="" disabled selected>اختر الشعبة...</option></select> </div> <div class="form-group"> <label for="department">القسم:</label> <select id="department" name="department" required disabled><option value="" disabled selected>اختر القسم...</option></select> </div> </div> <button type="button" id="toggleMoreOptions" class="more-options-btn" disabled>إضافة المزيد من الخيارات <i class="fas fa-chevron-down"></i></button> <div id="moreOptionsContainer" style="display:none"> <div class="form-group"><label for="newNote">ملاحظات:</label><textarea id="newNote" name="note"></textarea></div> <div class="form-group"><label for="newAbsences">غيابات:</label><textarea id="newAbsences" name="absence_details"></textarea></div> <div id="gradesContainer"></div> </div> <button type="submit" class="submit-btn">إضافة التلميذ</button> </form> </div> <div class="under-development-section"> <h2 class="section-heading">رفع دفعة من التلاميذ</h2><div class="csv-upload-wrapper" style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px"><div style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px"><input type="file" id="csvFile" accept=".csv" style="display:none"><button type="button" id="csvUploadBtn" style="padding:15px 30px;font-size:1.1em;font-weight:bold;border-radius:8px;border:0;cursor:pointer;transition:all .3s ease;background-color:var(--primary-dark-color);color:white;display:inline-flex;align-items:center;gap:10px"><i class="fas fa-file-csv"></i> اختر ملف CSV</button><button type="button" id="submitCsvBtn" style="padding:15px 30px;font-size:1.1em;font-weight:bold;border-radius:8px;border:0;cursor:pointer;transition:all .3s ease;background-color:var(--primary-dark-color);color:white;display:inline-flex;align-items:center;gap:10px;margin-top:10px" disabled>رفع الملف</button></div><div class="csv-note" style="margin-top:auto;padding-top:20px;width:100%;font-size:.9em"><p><strong>تنبيه:</strong> يرجى التأكد من أن صيغة ملف CSV تتوافق مع الهيكل المطلوب.</p><a href="#" id="csvHelpLink" style="color:var(--primary-dark-color);font-weight:bold;text-decoration:none">اضغط هنا للتعرف على المزيد حول كيفية ترتيب البيانات بالملف.</a></div></div></div><div id="csvHelpModal" class="modal" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.5)"><div class="modal-content" style="background-color:#fff;margin:15% auto;padding:30px;border:1px solid #888;width:80%;max-width:800px;border-radius:var(--border-radius);position:relative;box-shadow:var(--shadow)"><span class="close-button" style="color:#aaa;float:left;font-size:28px;font-weight:bold;cursor:pointer">&times;</span><div class="modal-body" style="max-height:70vh;overflow-y:auto;text-align:right"><h2 class="section-heading">تنسيق ملف CSV لرفع الدفعة</h2><p>يجب أن يكون ملف CSV منسقاً بشكل صحيح حتى يتم إدراج بيانات التلاميذ بشكل سليم. يرجى اتباع الترتيب التالي للأعمدة:</p><ul><li><strong>العمود 1:</strong> الاسم الكامل (full_name)</li><li><strong>العمود 2:</strong> الصف والشعبة والقسم (class_name)، مثال: <code>1 علمي 3</code> أو <code>2 لغات أجنبية 1</code>.</li><li><strong>العمود 3:</strong> تفاصيل الغيابات (absence_details). يتم فصل كل غياب بعلامة <code>|</code>.</li><li><strong>العمود 4:</strong> الملاحظات (note). يتم فصل كل ملاحظة بعلامة <code>|</code>.</li><li><strong>العمود 5:</strong> اسم المستخدم وكلمة المرور (username_password) مفصولان بـ <code>:</code>.</li><li><strong>الأعمدة 6 وما بعدها:</strong> درجات المواد. يتم ترتيبها حسب الشعبة. كل مادة لها 3 درجات للفصول الثلاثة، وكل درجة تتكون من "فرض،اختبار".</li><br><li style="color:red;font-weight:bold">ملاحظة هامة: يجب أن تتطابق الشعبة في عمود Class_name تماما مع المسميات التالية: <code>علمي</code> ، <code>ادبي</code> ، <code>لغات أجنبية</code> ، <code>آداب وفلسفة</code> ، <code>العلوم التجريبية</code> ، <code>الرياضيات</code> ، <code>تقني رياضي</code> ، <code>تسيير واقتصاد</code>.</li></ul><hr><h3>ترتيب المواد لكل شعبة</h3><p><strong>1. الصف الأول ثانوي:</strong></p><ul><li><strong>1 علمي:</strong> (اللغة العربية - اللغة الفرنسية - اللغة الإنجليزية - الرياضيات - التاريخ والجغرافيا - العلوم الطبيعية - الفيزياء - العلوم الإسلامية - الإعلام الآلي - التربية البدنية - اللغة الأمازيغية - تكنولوجيا)</li><li><strong>1 ادبي:</strong> (اللغة العربية - اللغة الفرنسية - اللغة الإنجليزية - الرياضيات - التاريخ والجغرافيا - العلوم الطبيعية - الفيزياء - العلوم الإسلامية - الإعلام الآلي - التربية البدنية - اللغة الأمازيغية)</li></ul><p><strong>2. الصف الثاني ثانوي:</strong></p><ul><li><strong>2 العلوم التجريبية:</strong> (اللغة العربية - اللغة الإنجليزية - اللغة الفرنسية - الرياضيات - العلوم الطبيعية - الفيزياء - التاريخ والجغرافيا - العلوم الإسلامية - التربية البدنية - اللغة الأمازيغية)</li><li><strong>2 الرياضيات:</strong> (اللغة العربية - اللغة الإنجليزية - اللغة الفرنسية - الرياضيات - العلوم الطبيعية - الفيزياء - التاريخ والجغرافيا - العلوم الإسلامية - التربية البدنية - اللغة الأمازيغية)</li><li><strong>2 تقني رياضي:</strong> (اللغة العربية - اللغة الإنجليزية - اللغة الفرنسية - الرياضيات - تكنولوجيا - الفيزياء - التاريخ والجغرافيا - العلوم الإسلامية - التربية البدنية - اللغة الأمازيغية)</li><li><strong>2 تسيير واقتصاد:</strong> (اللغة العربية - اللغة الإنجليزية - اللغة الفرنسية - الرياضيات - التاريخ والجغرافيا - العلوم الإسلامية - الاقتصاد - القانون - تسيير ومحاسبة - التربية البدنية - اللغة الأمازيغية)</li><li><strong>2 لغات أجنبية:</strong> (اللغة العربية - اللغة الفرنسية - اللغة الإنجليزية - اللغة الإسبانية - التاريخ والجغرافيا - العلوم الإسلامية - الرياضيات - التربية البدنية - اللغة الأمازيغية)</li><li><strong>2 آداب وفلسفة:</strong> (اللغة العربية - اللغة الفرنسية - اللغة الإنجليزية - التاريخ والجغرافيا - الفلسفة - العلوم الإسلامية - الرياضيات - الفيزياء - العلوم الطبيعية - التربية البدنية - اللغة الأمازيغية)</li></ul><p><strong>ملاحظة هامة:</strong> الصف الثالث ثانوي يستخدم نفس ترتيب المواد، فقط غيّر رقم الصف من 2 إلى 3 في حقل "الصف والشعبة والقسم" (Class_name).</p><hr><h3>نموذج شامل لملف CSV</h3><p>هنا مثال لصفين مختلفين في نفس الملف. لاحظ أن ترتيب الأعمدة ثابت لجميع التلاميذ في الملف، بينما القيم الفارغة <code>""</code> هي التي تحدد المواد التي يدرسها التلميذ. كل حقل درجة يمكن أن يحتوي على قيمتين مفصولتين بفاصلة (فرض, اختبار)، مثل <code>"15,17"</code>.</p><pre style="background-color:#f4f4f4;padding:15px;border-radius:5px;direction:ltr;text-align:left;overflow-x:auto;white-space:pre"><code>full_name,class_name,absence_details,note,username_password,arabic_language_term1,arabic_language_term2,arabic_language_term3,french_language_term1,french_language_term2,french_language_term3,english_language_term1,english_language_term2,english_language_term3,spanish_term1,spanish_term2,spanish_term3,history_geography_term1,history_geography_term2,history_geography_term3,islamic_sciences_term1,islamic_sciences_term2,islamic_sciences_term3,mathematics_term1,mathematics_term2,mathematics_term3,physical_education_term1,physical_education_term2,physical_education_term3,amazigh_language_term1,amazigh_language_term2,amazigh_language_term3,philosophy_term1,philosophy_term2,philosophy_term3,technology_term1,technology_term2,technology_term3,natural_sciences_term1,natural_sciences_term2,natural_sciences_term3,physics_sciences_term1,physics_sciences_term2,physics_sciences_term3,economics_term1,economics_term2,economics_term3,law_term1,law_term2,law_term3,accounting_management_term1,accounting_management_term2,accounting_management_term3,computer_science_term1,computer_science_term2,computer_science_term3
"علي عباس","2 لغات أجنبية 3","","","ali:123","15,17","18,19","14,16","10,12","11,13","14,15","16,17","18,19","15,16","12,13","14,15","10,11","17,18","19,20","16,17","18,19","17,16","15,18","10,11","12,13","14,15","19,20","18,19","17,18","14,15","13,14","15,16","","","","","","","","","","","","","","","","","","","","",""
"نور أحمد","2 آداب وفلسفة 2","","","noura:456","16,18","17,19","15,16","10,11","12,13","14,15","15,16","17,18","19,20","","","","18,19","16,17","15,16","17,18","19,20","16,17","11,12","13,14","15,16","18,19","17,18","19,20","14,15","13,14","15,16","17,18","19,20","16,17","","","","15,16","17,18","19,20","14,15","16,17","18,19","","","","","","","","","",""
"سامي مراد","3 تسيير واقتصاد 1","","","sami:789","18,19","17,18","16,17","15,16","14,15","13,14","12,13","11,12","10,11","","","","19,20","18,19","17,18","16,17","15,16","14,15","13,14","12,13","11,12","10,11","9,10","8,9","7,8","6,7","5,6","","","","","","","","","","","","","19,18","17,16","15,14","13,12","11,10","9,8","18,19","17,16","15,14","","",""
</code></pre><p><strong>ملاحظة:</strong> إذا أردت رفع دفعة ضخمة، يمكنك ببساطة نسخ هذا النموذج الشامل وتعبئة البيانات الخاصة بكل طالب. البرنامج سيتعرف تلقائياً على الشعبة من حقل <code>class_name</code> ويحفظ الدرجات المناسبة فقط، متجاهلاً الحقول الفارغة الأخرى.</p></div></div></div><script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script><script>/*<![CDATA[*/document.addEventListener('DOMContentLoaded',()=>{const csvUploadBtn=document.getElementById('csvUploadBtn');const csvFileInput=document.getElementById('csvFile');const submitCsvBtn=document.getElementById('submitCsvBtn');const csvHelpLink=document.getElementById('csvHelpLink');const modal=document.getElementById('csvHelpModal');const closeBtn=document.querySelector('.close-button');csvUploadBtn.addEventListener('click',()=>csvFileInput.click());csvFileInput.addEventListener('change',()=>{if(csvFileInput.files.length>0){const fileName=csvFileInput.files[0].name;csvUploadBtn.innerHTML=`<i class="fas fa-file-csv"></i>${fileName}`;submitCsvBtn.disabled=false;}else{csvUploadBtn.innerHTML=`<i class="fas fa-file-csv"></i>اختر ملف CSV`;submitCsvBtn.disabled=true;}});submitCsvBtn.addEventListener('click',()=>{if(csvFileInput.files.length>0){const file=csvFileInput.files[0];const reader=new FileReader();reader.onload=function(event){const csv_data=event.target.result;$.post('<?php echo $_SERVER['PHP_SELF']; ?>',{action:'uploadCsv',csv_data:csv_data},function(response){if(response.success){alert(`تم إضافة ${response.processed_count} تلميذ بنجاح.`);csvUploadBtn.innerHTML=`<i class="fas fa-file-csv"></i>اختر ملف CSV`;csvFileInput.value='';submitCsvBtn.disabled=true;}else{alert(`فشل رفع الملف.الرجاء التحقق من التنسيق.السبب:${response.message||'غير معروف'}`);}},'json').fail((jqXHR,textStatus,errorThrown)=>{const errorDetails=`Status:${textStatus},Error:${errorThrown}\nServer Response:\n${jqXHR.responseText}`;alert(`حدث خطأ في الاتصال بالخادم.\nللمزيد من التفاصيل:\n${errorDetails}`);});};reader.readAsText(file);}});csvHelpLink.addEventListener('click',(e)=>{e.preventDefault();modal.style.display='block';});closeBtn.addEventListener('click',()=>{modal.style.display='none';});window.addEventListener('click',(e)=>{if(e.target==modal)modal.style.display='none';});});/*]]>*/</script> </div> </div> </div> <div id="add" class="content-section"> </div> <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script> <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <script>/*<![CDATA[*/document.addEventListener('DOMContentLoaded',()=>{const navItems=document.querySelectorAll('.tabs-nav-item');const contentSections=document.querySelectorAll('.content-section');navItems.forEach(item=>{item.addEventListener('click',()=>{const targetTab=item.getAttribute('data-tab');navItems.forEach(nav=>nav.classList.remove('active'));contentSections.forEach(section=>section.classList.remove('active'));item.classList.add('active');document.getElementById(targetTab).classList.add('active');if(targetTab==='absences')getDailyAbsences();if(targetTab==='display')fetchAllStudents();});});const phpData=<?php echo $js_data; ?>;updateStats(phpData);const today=new Date().toISOString().split('T')[0];document.getElementById('startDate').value=today;document.getElementById('endDate').value=today;if(document.querySelector('.tabs-nav-item.active').getAttribute('data-tab')==='absences')getDailyAbsences();if(document.querySelector('.tabs-nav-item.active').getAttribute('data-tab')==='display')fetchAllStudents();const gradeLevelSelect=document.getElementById('gradeLevel');const subdivisionSelect=document.getElementById('subdivision');const departmentSelect=document.getElementById('department');const toggleMoreOptionsBtn=document.getElementById('toggleMoreOptions');const moreOptionsContainer=document.getElementById('moreOptionsContainer');const gradesContainer=document.getElementById('gradesContainer');const addStudentForm=document.getElementById('addStudentForm');const subdivisions=<?php echo $js_filters; ?>;const subjectMappings=<?php echo $js_subject_mappings; ?>;const subjectsList=<?php echo $js_subjects; ?>;gradeLevelSelect.addEventListener('change',()=>{const grade=gradeLevelSelect.value;subdivisionSelect.innerHTML='<option value="" disabled selected>اختر الشعبة...</option>';departmentSelect.innerHTML='<option value="" disabled selected>اختر القسم...</option>';departmentSelect.disabled=true;toggleMoreOptionsBtn.disabled=true;$(moreOptionsContainer).slideUp();toggleMoreOptionsBtn.innerHTML='إضافة المزيد من الخيارات <i class="fas fa-chevron-down"></i>';document.getElementById('newNote').value='';document.getElementById('newAbsences').value='';gradesContainer.innerHTML='';if(grade&&subdivisions[grade]){for(const key in subdivisions[grade]){const option=document.createElement('option');option.value=key;option.textContent=subdivisions[grade][key];subdivisionSelect.appendChild(option);}
subdivisionSelect.disabled=false;}});subdivisionSelect.addEventListener('change',()=>{departmentSelect.innerHTML='<option value="" disabled selected>اختر القسم...</option>';for(let i=1;i<=10;i++){const option=document.createElement('option');option.value=i;option.textContent=i;departmentSelect.appendChild(option);}
departmentSelect.disabled=false;toggleMoreOptionsBtn.disabled=false;generateGradesFields();});departmentSelect.addEventListener('change',()=>{const departmentValue=departmentSelect.value;toggleMoreOptionsBtn.disabled=!departmentValue;if(!departmentValue){$(moreOptionsContainer).slideUp();toggleMoreOptionsBtn.innerHTML='إضافة المزيد من الخيارات <i class="fas fa-chevron-down"></i>';}});toggleMoreOptionsBtn.addEventListener('click',()=>{if($(moreOptionsContainer).is(':hidden')){$(moreOptionsContainer).slideDown();toggleMoreOptionsBtn.innerHTML='إخفاء الخيارات الإضافية <i class="fas fa-chevron-up"></i>';}else{$(moreOptionsContainer).slideUp();toggleMoreOptionsBtn.innerHTML='إضافة المزيد من الخيارات <i class="fas fa-chevron-down"></i>';document.getElementById('newNote').value='';document.getElementById('newAbsences').value='';gradesContainer.innerHTML='';}});function generateGradesFields(){const subdivision=subdivisionSelect.value;const subjects=subjectMappings[subdivision]||[];let fieldsHTML='<h5>الدرجات</h5>';subjects.forEach(subjectKey=>{fieldsHTML+=`<div class="subject-card"><div class="subject-header"><span>${subjectsList[subjectKey]||subjectKey}</span><span class="toggle-icon fas fa-chevron-right"></span></div><div class="grades-fields"><div class="term-group"><h6>الفصل الأول</h6><div class="grade-inputs"><div class="form-group"><label>فرض</label><input type="text"placeholder="مثال: 15"data-subject="${subjectKey}"data-term="1"data-type="فرض"></div><div class="form-group"><label>اختبار</label><input type="text"placeholder="مثال: 17.5"data-subject="${subjectKey}"data-term="1"data-type="اختبار"></div></div></div><div class="term-group"><h6>الفصل الثاني</h6><div class="grade-inputs"><div class="form-group"><label>فرض</label><input type="text"placeholder="مثال: 14"data-subject="${subjectKey}"data-term="2"data-type="فرض"></div><div class="form-group"><label>اختبار</label><input type="text"placeholder="مثال: 18"data-subject="${subjectKey}"data-term="2"data-type="اختبار"></div></div></div><div class="term-group"><h6>الفصل الثالث</h6><div class="grade-inputs"><div class="form-group"><label>فرض</label><input type="text"placeholder="مثال: 16"data-subject="${subjectKey}"data-term="3"data-type="فرض"></div><div class="form-group"><label>اختبار</label><input type="text"placeholder="مثال: 19"data-subject="${subjectKey}"data-term="3"data-type="اختبار"></div></div></div></div></div>`;});gradesContainer.innerHTML=fieldsHTML;gradesContainer.querySelectorAll('.subject-card .subject-header').forEach(header=>{header.addEventListener('click',()=>{header.classList.toggle('open');$(header).next('.grades-fields').slideToggle();});});}
addStudentForm.addEventListener('submit',function(e){e.preventDefault();const formData=new FormData(this);const fullName=`${formData.get('firstName')}${formData.get('lastName')}`;const usernamePassword=`${formData.get('username')}:${formData.get('password')}`;const className=`${formData.get('gradeLevel')}${formData.get('subdivision')}${formData.get('department')}`;const postData={action:'addStudent',full_name:fullName,username_password:usernamePassword,class_name:className,note:formData.get('note')||'',absence_details:formData.get('absence_details')||'',grade:formData.get('gradeLevel'),subdivision:formData.get('subdivision')};const grades={};const gradeInputs=gradesContainer.querySelectorAll('input');gradeInputs.forEach(input=>{const subject=input.dataset.subject;const term=input.dataset.term;const type=input.dataset.type;const key=`${subject}_term${term}`;if(!grades[key])grades[key]={'فرض':'','اختبار':''};grades[key][type]=input.value;});const formattedGrades={};for(const key in grades){const fard=grades[key]['فرض']||'';const ikhtibar=grades[key]['اختبار']||'';if(fard===''&&ikhtibar===''){formattedGrades[key]='';}else{formattedGrades[key]=`${fard},${ikhtibar}`;}}
postData.grades=JSON.stringify(formattedGrades);$.post('<?php echo $_SERVER['PHP_SELF']; ?>',postData,function(response){if(response.success){alert('تم إضافة التلميذ بنجاح!');addStudentForm.reset();subdivisionSelect.disabled=true;departmentSelect.disabled=true;toggleMoreOptionsBtn.disabled=true;moreOptionsContainer.style.display='none';}else{alert('فشلت عملية الإضافة. يرجى مراجعة البيانات أو سجل الأخطاء.');}},'json').fail(function(){alert('حدث خطأ في الاتصال بالخادم.');});});});function updateStats(data){document.getElementById('total-students-count').textContent=data.totalStudents;const labels=data.datesData.map(d=>d.day_name);const absencesData=data.datesData.map(d=>d.absences);const ctx=document.getElementById('absencesChart').getContext('2d');if(window.absencesChartInstance){window.absencesChartInstance.destroy();}
window.absencesChartInstance=new Chart(ctx,{type:'line',data:{labels:labels,datasets:[{label:'عدد الغيابات',data:absencesData,borderColor:'#E0A800',backgroundColor:'rgba(224, 168, 0, 0.3)',tension:0.4,fill:true,pointBackgroundColor:'#FFC107',pointBorderColor:'#FFF',pointBorderWidth:2,pointRadius:6,pointHoverRadius:8,}]},options:{responsive:true,plugins:{legend:{display:false},tooltip:{callbacks:{label:function(context){const dateInfo=data.datesData[context.dataIndex];return`التاريخ:${dateInfo.date}|الغيابات:${dateInfo.absences}`;}}}},scales:{x:{title:{display:false},grid:{display:false}},y:{title:{display:false},beginAtZero:true,ticks:{precision:0,stepSize:1},grid:{color:'#FFECB3'}}}}});}
function getDailyAbsences(){const today=new Date().toISOString().split('T')[0];document.getElementById('startDate').value=today;document.getElementById('endDate').value=today;searchAbsences(today,today);}
function searchAbsences(startDate=null,endDate=null){const start=startDate||document.getElementById('startDate').value;const end=endDate||document.getElementById('endDate').value;document.getElementById('absences-results').innerHTML='<p style="text-align: center;">جاري البحث عن الغيابات...</p>';const titleElement=document.getElementById('absences-title');if(start===end){titleElement.textContent=`غيابات يوم:(${start})`;}
else{titleElement.textContent=`غيابات من ${start}إلى ${end}`;}
$.post('<?php echo $_SERVER['PHP_SELF']; ?>',{action:'getAbsences',startDate:start,endDate:end},function(data){if(data&&data.length>0){displayAbsences(data);}
else{document.getElementById('absences-results').innerHTML='<p style="text-align: center;">لا توجد غيابات في الفترة المحددة.</p>';}},'json').fail(function(jqXHR,textStatus,errorThrown){console.error("AJAX Error:",textStatus,errorThrown);document.getElementById('absences-results').innerHTML='<p style="text-align: center; color: red;">حدث خطأ أثناء جلب البيانات. الرجاء المحاولة مرة أخرى.</p>';});}
function displayAbsences(data){const resultsContainer=document.getElementById('absences-results');resultsContainer.innerHTML='';const groupedAbsences=groupData(data);window.currentAbsencesData=data;for(const gradeName in groupedAbsences){const gradeButton=document.createElement('button');gradeButton.className='grade-button';gradeButton.innerHTML=`<span>${gradeName}</span><span class="toggle-icon fas fa-chevron-right"></span>`;resultsContainer.appendChild(gradeButton);const gradeContent=document.createElement('div');gradeContent.className='grade-content';gradeContent.style.display='none';for(const subdivisionName in groupedAbsences[gradeName]){const subdivisionButton=document.createElement('button');subdivisionButton.className='subdivision-button';subdivisionButton.innerHTML=`<span>${subdivisionName}</span><span class="toggle-icon fas fa-chevron-right"></span>`;gradeContent.appendChild(subdivisionButton);const subdivisionContent=document.createElement('div');subdivisionContent.className='subdivision-content';subdivisionContent.style.display='none';for(const departmentName in groupedAbsences[gradeName][subdivisionName]){const departmentButton=document.createElement('button');departmentButton.className='department-button';departmentButton.innerHTML=`<span>${departmentName}</span><span class="toggle-icon fas fa-chevron-right"></span>`;subdivisionContent.appendChild(departmentButton);const studentList=document.createElement('ul');studentList.className='student-list';studentList.style.display='none';for(const student of groupedAbsences[gradeName][subdivisionName][departmentName]){const studentItem=document.createElement('li');studentItem.innerHTML=`<div class="student-name-row"><span class="student-name">${student.full_name}</span><span class="toggle-details fas fa-chevron-right"></span></div>`;const detailsDiv=document.createElement('div');detailsDiv.className='absence-details';const consolidated=consolidateAbsences(student.absences);for(const absence of consolidated){const countText=absence.count>1?`(${absence.count}مرات)`:'';const detailsText=absence.details.join(' | ');detailsDiv.innerHTML+=`<p>${absence.subject}${countText}:${detailsText}</p>`;}
studentItem.appendChild(detailsDiv);studentList.appendChild(studentItem);studentItem.addEventListener('click',(e)=>{e.stopPropagation();studentItem.classList.toggle('open');$(detailsDiv).slideToggle();});}
subdivisionContent.appendChild(studentList);departmentButton.addEventListener('click',()=>{$(subdivisionContent).find('.department-button.open + .student-list').not($(studentList)).slideUp();$(subdivisionContent).find('.department-button.open').not($(departmentButton)).removeClass('open');departmentButton.classList.toggle('open');$(studentList).slideToggle();});}
gradeContent.appendChild(subdivisionContent);subdivisionButton.addEventListener('click',()=>{$(gradeContent).find('.subdivision-button.open + .subdivision-content').not($(subdivisionContent)).slideUp();$(gradeContent).find('.subdivision-button.open').not($(subdivisionButton)).removeClass('open');subdivisionButton.classList.toggle('open');$(subdivisionContent).slideToggle();});}
resultsContainer.appendChild(gradeContent);gradeButton.addEventListener('click',()=>{$('.grade-button.open + .grade-content').not($(gradeContent)).slideUp();$('.grade-button.open').not($(gradeButton)).removeClass('open');gradeButton.classList.toggle('open');$(gradeContent).slideToggle();});}
const downloadBtn=document.getElementById('downloadAbsencesCsvBtn');if(downloadBtn){const newDownloadBtn=downloadBtn.cloneNode(true);downloadBtn.parentNode.replaceChild(newDownloadBtn,downloadBtn);newDownloadBtn.addEventListener('click',()=>{downloadAbsencesCsv(window.currentAbsencesData);});}}
function groupData(data){const grouped={};const gradeNames={'1':'أولى ثانوي','2':'ثانية ثانوي','3':'ثالثة ثانوي'};const subdivisions=['العلوم التجريبية','آداب وفلسفة','لغات أجنبية','الرياضيات','تسيير واقتصاد','تقني رياضي','علمي','ادبي'];data.forEach(item=>{const className=item.class_name;const gradeNumMatch=className.match(/^\d+/);const gradeNum=gradeNumMatch?gradeNumMatch[0]:'غير محدد';const gradeName=gradeNames[gradeNum]||`سنة ${gradeNum}`;let subdivisionName='غير محدد';let departmentNum='';for(const sub of subdivisions){const subIndex=className.indexOf(sub);if(subIndex!==-1){subdivisionName=sub;const remainingString=className.substring(subIndex+sub.length).trim();const departmentNumMatch=remainingString.match(/^\d+/);if(departmentNumMatch){departmentNum=departmentNumMatch[0];}
break;}}
const departmentName=departmentNum?`القسم ${departmentNum}`:'غير محدد';if(!grouped[gradeName]){grouped[gradeName]={};}
if(!grouped[gradeName][subdivisionName]){grouped[gradeName][subdivisionName]={};}
if(!grouped[gradeName][subdivisionName][departmentName]){grouped[gradeName][subdivisionName][departmentName]=[];}
grouped[gradeName][subdivisionName][departmentName].push(item);});return grouped;}
function consolidateAbsences(absences){const uniqueAbsences={};absences.forEach(absence=>{const key=absence.subject;if(!uniqueAbsences[key]){uniqueAbsences[key]={subject:absence.subject,count:0,details:[]};}
uniqueAbsences[key].count++;uniqueAbsences[key].details.push(`يوم ${absence.date}${absence.time}`);});return Object.values(uniqueAbsences);}
function downloadAbsencesCsv(data){let csvContent="data:text/csv;charset=utf-8,\uFEFF";csvContent+="الاسم,الصف,الشعبة,القسم,تفاصيل الغياب,عدد الغيابات\n";const gradeNames={'1':'أولى ثانوي','2':'ثانية ثانوي','3':'ثالثة ثانوي'};const subdivisions=['العلوم التجريبية','آداب وفلسفة','لغات أجنبية','الرياضيات','تسيير واقتصاد','تقني رياضي','علمي','ادبي'];data.forEach(student=>{const fullName=`"${student.full_name}"`;const className=student.class_name;const gradeNumMatch=className.match(/^\d+/);const gradeNum=gradeNumMatch?gradeNumMatch[0]:'';const gradeName=gradeNames[gradeNum]||gradeNum;let subdivisionName='غير محدد';let departmentNum='';for(const sub of subdivisions){const subIndex=className.indexOf(sub);if(subIndex!==-1){subdivisionName=sub;const remainingString=className.substring(subIndex+sub.length).trim();const departmentNumMatch=remainingString.match(/^\d+/);if(departmentNumMatch){departmentNum=departmentNumMatch[0];}
break;}}
const departmentName=`القسم ${departmentNum}`;const absencesDetails=[];let absenceCount=0;const consolidated=consolidateAbsences(student.absences);consolidated.forEach(abs=>{const detailText=`${abs.subject}(${abs.count}مرات):${abs.details.join(' | ')}`;absencesDetails.push(detailText);absenceCount+=abs.count;});const detailsString=`"${absencesDetails.join(' | ')}"`;csvContent+=`${fullName},"${gradeName}","${subdivisionName}","${departmentName}",${detailsString},${absenceCount}\n`;});const encodedUri=encodeURI(csvContent);const link=document.createElement("a");link.setAttribute("href",encodedUri);link.setAttribute("download","absences.csv");document.body.appendChild(link);link.click();document.body.removeChild(link);}
function clearDates(){const today=new Date().toISOString().split('T')[0];document.getElementById('startDate').value=today;document.getElementById('endDate').value=today;document.getElementById('absences-title').textContent=`غيابات اليوم:(${today})`;searchAbsences(today,today);}
const subjectNames=<?php echo $js_subjects; ?>;window.allStudents=[];function fetchAllStudents(){const listContainer=document.getElementById('studentsDisplayList');listContainer.innerHTML='<div class="placeholder-box"><p>جاري جلب بيانات التلاميذ...</p></div>';$.post('<?php echo $_SERVER['PHP_SELF']; ?>',{action:'getStudents'},function(data){window.allStudents=data;displayStudents(window.allStudents);},'json').fail(function(){listContainer.innerHTML='<div class="placeholder-box"><p style="color: red;">حدث خطأ أثناء جلب البيانات. الرجاء المحاولة مرة أخرى.</p></div>';});}
function displayStudents(students){const listContainer=document.getElementById('studentsDisplayList');listContainer.innerHTML='';const groupedStudents=groupData(students);if(Object.keys(groupedStudents).length===0){listContainer.innerHTML='<div class="placeholder-box"><p>لا توجد بيانات تلاميذ لعرضها.</p></div>';return;}
for(const gradeName in groupedStudents){const gradeButton=document.createElement('button');gradeButton.className='grade-button';gradeButton.innerHTML=`<span>${gradeName}</span><span class="toggle-icon fas fa-chevron-right"></span>`;listContainer.appendChild(gradeButton);const gradeContent=document.createElement('div');gradeContent.className='grade-content';gradeContent.style.display='none';for(const subdivisionName in groupedStudents[gradeName]){const subdivisionButton=document.createElement('button');subdivisionButton.className='subdivision-button';subdivisionButton.innerHTML=`<span>${subdivisionName}</span><span class="toggle-icon fas fa-chevron-right"></span>`;gradeContent.appendChild(subdivisionButton);const subdivisionContent=document.createElement('div');subdivisionContent.className='subdivision-content';subdivisionContent.style.display='none';for(const departmentName in groupedStudents[gradeName][subdivisionName]){const departmentButton=document.createElement('button');departmentButton.className='department-button';departmentButton.innerHTML=`<span>${departmentName}</span><span class="toggle-icon fas fa-chevron-right"></span>`;subdivisionContent.appendChild(departmentButton);const studentList=document.createElement('div');studentList.className='student-list';studentList.style.display='none';for(const student of groupedStudents[gradeName][subdivisionName][departmentName]){const studentItemCard=document.createElement('div');studentItemCard.className='student-item-card';studentItemCard.setAttribute('data-student-id',student.student_id);studentItemCard.innerHTML=`<div class="student-header"><h4>${student.full_name}</h4><div class="actions"><button class="delete-btn"onclick="event.stopPropagation(); deleteStudent(${student.student_id}, '${student.db_path}', '${student.table_name}')"title="حذف التلميذ"><i class="fas fa-trash-alt"></i></button><span>|</span><button class="toggle-btn fas fa-chevron-right"></button></div></div>`;const detailsDiv=document.createElement('div');detailsDiv.className='student-details';detailsDiv.style.display='none';detailsDiv.innerHTML=createStudentDetailsHTML(student);studentItemCard.appendChild(detailsDiv);studentItemCard.querySelector('.student-header').addEventListener('click',()=>{studentItemCard.classList.toggle('open');$(detailsDiv).slideToggle();});studentList.appendChild(studentItemCard);}
subdivisionContent.appendChild(studentList);departmentButton.addEventListener('click',()=>{$(subdivisionContent).find('.department-button.open + .student-list').not($(studentList)).slideUp();$(subdivisionContent).find('.department-button.open').not($(departmentButton)).removeClass('open');departmentButton.classList.toggle('open');$(studentList).slideToggle();});}
gradeContent.appendChild(subdivisionContent);subdivisionButton.addEventListener('click',()=>{$(gradeContent).find('.subdivision-button.open + .subdivision-content').not($(subdivisionContent)).slideUp();$(gradeContent).find('.subdivision-button.open').not($(subdivisionButton)).removeClass('open');subdivisionButton.classList.toggle('open');$(subdivisionContent).slideToggle();});}
listContainer.appendChild(gradeContent);gradeButton.addEventListener('click',()=>{$('.grade-button.open + .grade-content').not($(gradeContent)).slideUp();$('.grade-button.open').not($(gradeButton)).removeClass('open');gradeButton.classList.toggle('open');$(gradeContent).slideToggle();});}}
function createStudentDetailsHTML(student){const absencesCount=(student.absence_details.match(/يوم/g)||[]).length;let username='';let password='';if(student.username_password){const parts=student.username_password.split(':');username=parts[0]||'';password=parts[1]||'';}
return`<div class="detail-group"><h5>الدرجات</h5><div class="grades-table-container">${createGradesTable(student)}</div></div><div class="detail-group"><h5>بيانات التلميذ</h5><label>الاسم الكامل:</label><input type="text"class="full-name-input"value="${student.full_name}"></div><div class="detail-group"><label>اسم المستخدم:</label><input type="text"class="username-input"value="${username}"></div><div class="detail-group"><label>كلمة المرور:</label><input type="text"class="password-input"value="${password}"></div><div class="detail-group"><label>الصف:</label><input type="text"class="class-input"value="${student.class_name}"></div><div class="detail-group"><label>الملاحظات:</label><textarea class="note-input">${student.note||''}</textarea></div><div class="detail-group"><label>الغيابات(${absencesCount}):<button onclick="downloadStudentAbsencesCsv('${student.full_name}', '${student.class_name}', '${student.absence_details}')"class="csv-button"style="float: left; margin-top: -5px;">تحميل الغيابات</button></label><textarea class="absences-input">${student.absence_details||''}</textarea></div><div class="action-buttons"><button class="save-btn"onclick="saveStudentData(this, ${student.student_id}, '${student.db_path}', '${student.table_name}')">تعديل وحفظ</button><button class="csv-btn"onclick="downloadStudentGradesCsv(this, ${student.student_id}, '${student.full_name}', '${student.class_name}')">تحميل الدرجات(CSV)</button></div><div class="note-sender"><label>إرسال ملاحظة جديدة:</label><textarea class="new-note-input"placeholder="اكتب ملاحظة جديدة هنا..."></textarea><button class="send-btn"onclick="addNoteToStudent(this, ${student.student_id}, '${student.db_path}', '${student.table_name}')">إرسال</button></div>`;}
function createGradesTable(student){let tableHtml='<table class="grades-table"><thead><tr><th rowspan="2">المادة</th><th colspan="2">الفصل الأول</th><th colspan="2">الفصل الثاني</th><th colspan="2">الفصل الثالث</th></tr><tr><th>فرض</th><th>اختبار</th><th>فرض</th><th>اختبار</th><th>فرض</th><th>اختبار</th></tr></thead><tbody>';const gradeKeys=Object.keys(student).filter(key=>key.endsWith('_term1'));gradeKeys.forEach(key=>{const subject=key.replace('_term1','');const term1_parts=(student[subject+'_term1']||'').split(',');const term2_parts=(student[subject+'_term2']||'').split(',');const term3_parts=(student[subject+'_term3']||'').split(',');const term1_فرض=term1_parts[0]||'';const term1_اختبار=term1_parts[1]||'';const term2_فرض=term2_parts[0]||'';const term2_اختبار=term2_parts[1]||'';const term3_فرض=term3_parts[0]||'';const term3_اختبار=term3_parts[1]||'';tableHtml+=`<tr><td>${subjectNames[subject]||subject}</td><td><input type="text"class="grade-input"data-subject="${subject}"data-term="1"data-type="فرض"value="${term1_فرض}"></td><td><input type="text"class="grade-input"data-subject="${subject}"data-term="1"data-type="اختبار"value="${term1_اختبار}"></td><td><input type="text"class="grade-input"data-subject="${subject}"data-term="2"data-type="فرض"value="${term2_فرض}"></td><td><input type="text"class="grade-input"data-subject="${subject}"data-term="2"data-type="اختبار"value="${term2_اختبار}"></td><td><input type="text"class="grade-input"data-subject="${subject}"data-term="3"data-type="فرض"value="${term3_فرض}"></td><td><input type="text"class="grade-input"data-subject="${subject}"data-term="3"data-type="اختبار"value="${term3_اختبار}"></td></tr>`;});tableHtml+='</tbody></table>';return tableHtml;}
function saveStudentData(saveButton,studentId,dbPath,tableName){if(!confirm('هل أنت متأكد من حفظ التغييرات؟')){return;}
const studentCard=saveButton.closest('.student-item-card');const updates={full_name:studentCard.querySelector('.full-name-input').value,class_name:studentCard.querySelector('.class-input').value,note:studentCard.querySelector('.note-input').value,absence_details:studentCard.querySelector('.absences-input').value,};const username=studentCard.querySelector('.username-input').value;const password=studentCard.querySelector('.password-input').value;updates.username_password=`${username}:${password}`;const gradeInputs=studentCard.querySelectorAll('.grade-input');const gradesData={};gradeInputs.forEach(input=>{const subject=input.getAttribute('data-subject');const term=input.getAttribute('data-term');const type=input.getAttribute('data-type');const key=`${subject}_term${term}`;if(!gradesData[key]){gradesData[key]={'فرض':'','اختبار':''};}
gradesData[key][type]=input.value;});for(const key in gradesData){updates[key]=`${gradesData[key]['فرض']},${gradesData[key]['اختبار']}`;}
$.post('<?php echo $_SERVER['PHP_SELF']; ?>',{action:'updateStudent',db_path:dbPath,table_name:tableName,student_id:studentId,updates:JSON.stringify(updates)},function(response){if(response.success){alert('تم حفظ البيانات بنجاح!');const studentHeader=studentCard.querySelector('.student-header h4');if(studentHeader){studentHeader.textContent=updates.full_name;}}else{alert('حدث خطأ أثناء الحفظ. الرجاء التحقق من المدخلات.');}},'json').fail(function(jqXHR,textStatus,errorThrown){alert('حدث خطأ في الاتصال بالخادم. '+textStatus+': '+errorThrown);});}
function deleteStudent(studentId,dbPath,tableName){if(confirm('هل أنت متأكد من رغبتك في حذف هذا التلميذ؟ لا يمكن التراجع عن هذا الإجراء.')){$.post('<?php echo $_SERVER['PHP_SELF']; ?>',{action:'deleteStudent',db_path:dbPath,table_name:tableName,student_id:studentId},function(response){if(response.success){alert('تم حذف التلميذ بنجاح.');document.querySelector(`.student-item-card[data-student-id="${studentId}"]`).remove();}else{alert('حدث خطأ أثناء الحذف.');}},'json').fail(function(){alert('حدث خطأ في الاتصال بالخادم.');});}}
function addNoteToStudent(sendButton,studentId,dbPath,tableName){const studentCard=sendButton.closest('.student-item-card');const newNoteInput=studentCard.querySelector('.new-note-input');const newNote=newNoteInput.value.trim();if(!newNote){alert('الرجاء كتابة ملاحظة لإرسالها.');return;}
$.post('<?php echo $_SERVER['PHP_SELF']; ?>',{action:'addNote',db_path:dbPath,table_name:tableName,student_id:studentId,note:newNote},function(response){if(response.success){alert('تم إرسال الملاحظة بنجاح!');const noteField=studentCard.querySelector('.note-input');noteField.value+=(noteField.value?' ':'')+'| الإدارة '+new Date().toISOString().slice(0,10)+': '+newNote;newNoteInput.value='';}else{alert('حدث خطأ أثناء إرسال الملاحظة.');}},'json').fail(function(){alert('حدث خطأ في الاتصال بالخادم.');});}
function downloadStudentGradesCsv(csvButton,studentId,studentName,className){const studentCard=csvButton.closest('.student-item-card');const gradesTable=studentCard.querySelector('.grades-table');let csvContent="data:text/csv;charset=utf-8,\uFEFF";csvContent+="الاسم,الصف,المادة,الفصل الأول (فرض),الفصل الأول (اختبار),الفصل الثاني (فرض),الفصل الثاني (اختبار),الفصل الثالث (فرض),الفصل الثالث (اختبار)\n";const rows=gradesTable.querySelectorAll('tbody tr');rows.forEach(row=>{const subject=row.querySelector('td:first-child').textContent;const term1_فرض=row.querySelector('input[data-term="1"][data-type="فرض"]').value;const term1_اختبار=row.querySelector('input[data-term="1"][data-type="اختبار"]').value;const term2_فرض=row.querySelector('input[data-term="2"][data-type="فرض"]').value;const term2_اختبار=row.querySelector('input[data-term="2"][data-type="اختبار"]').value;const term3_فرض=row.querySelector('input[data-term="3"][data-type="فرض"]').value;const term3_اختبار=row.querySelector('input[data-term="3"][data-type="اختبار"]').value;csvContent+=`"${studentName}","${className}","${subject}","${term1_فرض}","${term1_اختبار}","${term2_فرض}","${term2_اختبار}","${term3_فرض}","${term3_اختبار}"\n`;});const encodedUri=encodeURI(csvContent);const link=document.createElement("a");link.setAttribute("href",encodedUri);link.setAttribute("download",`grades_${studentName}.csv`);document.body.appendChild(link);link.click();document.body.removeChild(link);}
function downloadStudentAbsencesCsv(studentName,className,absenceDetails){let csvContent="data:text/csv;charset=utf-8,\uFEFF";csvContent+="الاسم,الصف,تفاصيل الغياب\n";const absences=(absenceDetails||'').split('|').filter(a=>a.trim()!=='');const formattedAbsences=absences.map(a=>a.trim().replace(/\s*يوم\s*/,' - يوم ').replace(/:/,' - '));csvContent+=`"${studentName}","${className}","${formattedAbsences.join(' | ')}"\n`;const encodedUri=encodeURI(csvContent);const link=document.createElement("a");link.setAttribute("href",encodedUri);link.setAttribute("download",`absences_${studentName}.csv`);document.body.appendChild(link);link.click();document.body.removeChild(link);}
document.getElementById('studentSearchInput').addEventListener('input',(e)=>{const searchTerm=e.target.value.trim().toLowerCase();const searchResultsDiv=document.getElementById('searchResults');const studentsDisplayList=document.getElementById('studentsDisplayList');if(searchTerm.length>0){studentsDisplayList.style.display='none';searchResultsDiv.style.display='block';searchResultsDiv.innerHTML='';const matchedStudents=window.allStudents.filter(student=>student.full_name.toLowerCase().includes(searchTerm)||student.class_name.toLowerCase().includes(searchTerm));if(matchedStudents.length>0){matchedStudents.forEach(student=>{const searchResultCard=document.createElement('div');searchResultCard.className='student-item-card';searchResultCard.setAttribute('data-student-id',student.student_id);searchResultCard.style.cursor='pointer';searchResultCard.innerHTML=`<div class="student-header"><h4>${student.full_name}</h4><div style="font-size: 0.9em; color: #666;"><span>${student.class_name}</span></div></div>`;searchResultCard.onclick=()=>{searchResultsDiv.innerHTML='';searchResultsDiv.appendChild(createStudentCard(student));$(searchResultsDiv.querySelector('.student-details')).slideDown();searchResultsDiv.querySelector('.student-item-card').classList.add('open');};searchResultsDiv.appendChild(searchResultCard);});}else{searchResultsDiv.innerHTML='<div class="placeholder-box"><p>لا توجد نتائج مطابقة.</p></div>';}}else{searchResultsDiv.style.display='none';studentsDisplayList.style.display='block';}});function createStudentCard(student){const studentItemCard=document.createElement('div');studentItemCard.className='student-item-card';studentItemCard.setAttribute('data-student-id',student.student_id);studentItemCard.innerHTML=`<div class="student-header"><h4>${student.full_name}</h4><div class="actions"><button class="delete-btn"onclick="event.stopPropagation(); deleteStudent(${student.student_id}, '${student.db_path}', '${student.table_name}')"title="حذف التلميذ"><i class="fas fa-trash-alt"></i></button><span>|</span><button class="toggle-btn fas fa-chevron-right"></button></div></div>`;const detailsDiv=document.createElement('div');detailsDiv.className='student-details';detailsDiv.innerHTML=createStudentDetailsHTML(student);studentItemCard.appendChild(detailsDiv);studentItemCard.querySelector('.student-header').addEventListener('click',()=>{studentItemCard.classList.toggle('open');$(detailsDiv).slideToggle();});return studentItemCard;}/*]]>*/</script> <script>
const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;
if (window.jQuery) $.ajaxSetup({headers: {'X-CSRF-Token': csrfToken}});
document.querySelectorAll('form').forEach(form => {
    const token=document.createElement('input');token.type='hidden';token.name='csrf_token';token.value=csrfToken;form.appendChild(token);
});
</script>
</body>
</html>
