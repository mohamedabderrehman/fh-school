<?php
// جلب آخر 3 منشورات من قاعدة البيانات
try {
    $pdo = new PDO('sqlite:data/posts/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // جلب آخر 3 منشورات مرتبة حسب التاريخ
    $stmt = $pdo->prepare("SELECT * FROM posts ORDER BY date DESC LIMIT 3");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $posts = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثانوية متقن محمد قروف العالية - بسكرة</title>
    <meta name="description" content="نظام الإدارة التربوي لثانوية متقن محمد قروف العالية">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        * {
            font-family: 'Cairo', sans-serif;
        }
        
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .bg-gradient-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .bg-gradient-accent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .text-primary {
            color: #3b82f6;
        }
        
        .text-accent {
            color: #10b981;
        }
        
        .bg-primary {
            background-color: #3b82f6;
        }
        
        .bg-accent {
            background-color: #10b981;
        }
        
        .border-primary {
            border-color: #3b82f6;
        }
        
        .border-accent {
            border-color: #10b981;
        }
        
        .hover\:bg-primary:hover {
            background-color: #2563eb;
        }
        
        .hover\:bg-accent:hover {
            background-color: #059669;
        }
        
        .hover\:border-primary:hover {
            border-color: #2563eb;
        }
        
        .hover\:border-accent:hover {
            border-color: #059669;
        }
        
        /* Enhanced Colors and Shadows */
        .shadow-soft {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .shadow-medium {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        .shadow-strong {
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        /* Enhanced Gradients */
        .bg-gradient-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .bg-gradient-features {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .bg-gradient-about {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        /* Enhanced Animations */
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        .animate-bounce-slow {
            animation: bounce 2s infinite;
        }
        
        .animate-pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }
        
        .animate-fade-in-left {
            animation: fadeInLeft 0.8s ease-out;
        }
        
        .animate-fade-in-right {
            animation: fadeInRight 0.8s ease-out;
        }
        
        /* Keyframes */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Enhanced Hover Effects */
        .hover-glow:hover {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.4);
        }
        
        .hover-rotate:hover {
            transform: rotate(5deg) scale(1.05);
        }
        
        /* Responsive Typography */
        .text-responsive-xl {
            font-size: clamp(1.5rem, 4vw, 3rem);
        }
        
        .text-responsive-lg {
            font-size: clamp(1.25rem, 3vw, 2rem);
        }
        
        /* Glass Morphism */
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Enhanced Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.6);
        }
        
        .btn-accent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            transition: all 0.3s ease;
        }
        
        .btn-accent:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.6);
        }
        
        /* Center the login cards */
        .login-cards-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        /* Ensure cards are properly centered on mobile */
        @media (max-width: 768px) {
            .login-cards-container {
                flex-direction: column;
                gap: 2rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
         <!-- Header/Navbar - Navigation -->
     <header id="navbar" class="bg-white/95 shadow-lg border-b z-50 transition-all duration-300">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-blue-600 rounded-full p-2 animate-pulse">
                        <i data-lucide="graduation-cap" class="h-6 w-6 text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-blue-600">ثانوية متقن محمد قروف العالية</h1>
                        <p class="text-sm text-gray-600">بسكرة</p>
                    </div>
                </div>

                <nav class="hidden md:flex items-center gap-6">
                    <a href="#" class="text-blue-600 font-medium hover:text-blue-700 transition-all duration-300 hover:scale-105">الرئيسية</a>
                    <a href="#about" class="text-gray-700 hover:text-blue-600 transition-all duration-300 hover:scale-105">عن النظام</a>
                    <a href="#login" class="text-gray-700 hover:text-blue-600 transition-all duration-300 hover:scale-105">تسجيل الدخول</a>
                    <a href="#contact" class="text-gray-700 hover:text-blue-600 transition-all duration-300 hover:scale-105">تواصل معنا</a>
                </nav>

                <button id="mobile-menu-btn" class="md:hidden bg-transparent border border-gray-300 rounded-lg px-3 py-2 text-sm hover:bg-gray-50 transition-all duration-300">
                    <i data-lucide="menu" class="h-5 w-5"></i>
                </button>
            </div>
            
            <!-- Mobile Menu -->
            <nav id="mobile-menu" class="hidden md:hidden mt-4 pt-4 border-t border-gray-200">
                <div class="flex flex-col space-y-3">
                    <a href="#" class="text-blue-600 font-medium hover:text-blue-700 transition-colors py-2 px-3 rounded-lg hover:bg-blue-50">الرئيسية</a>
                    <a href="#about" class="text-gray-700 hover:text-blue-600 transition-colors py-2 px-3 rounded-lg hover:bg-blue-50">عن النظام</a>
                    <a href="#login" class="text-gray-700 hover:text-blue-600 transition-colors py-2 px-3 rounded-lg hover:bg-blue-50">تسجيل الدخول</a>
                    <a href="#contact" class="text-gray-700 hover:text-blue-600 transition-colors py-2 px-3 rounded-lg hover:bg-blue-50">تواصل معنا</a>
                </div>
            </nav>
        </div>
    </header>

    

    <!-- Hero Section -->
    <section class="py-20 px-4 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 relative overflow-hidden">
        <!-- Background Decorations -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-gradient-to-br from-blue-200/30 to-purple-200/30 rounded-full animate-float"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-gradient-to-br from-green-200/30 to-blue-200/30 rounded-full animate-float" style="animation-delay: 1s;"></div>
            <div class="absolute top-1/2 left-1/4 w-40 h-40 bg-gradient-to-br from-pink-200/20 to-red-200/20 rounded-full animate-pulse-slow"></div>
        </div>
        
        <div class="container mx-auto text-center relative z-10">
            <div class="max-w-4xl mx-auto">
                <div class="animate-fade-in-up">
                    <h2 class="text-responsive-xl font-bold bg-gradient-to-r from-blue-600 via-purple-600 to-green-600 bg-clip-text text-transparent mb-6 leading-tight">
                        مرحباً بكم في نظام الإدارة التربوي
                    </h2>
                </div>
                <div class="animate-fade-in-up" style="animation-delay: 0.2s;">
                    <h3 class="text-responsive-lg font-semibold text-gray-800 mb-8">
                        لثانوية متقن محمد قروف العالية – بسكرة
                    </h3>
                </div>
                <div class="animate-fade-in-up" style="animation-delay: 0.4s;">
                    <p class="text-lg text-gray-600 mb-10 max-w-2xl mx-auto">
                        نظام إدارة تعليمي متطور يوفر حلولاً شاملة لإدارة المؤسسات التعليمية بكفاءة وسهولة
                    </p>
                </div>
                <div class="animate-fade-in-up" style="animation-delay: 0.6s;">
                                         <a href="#login" class="btn-primary text-white text-lg px-8 py-6 rounded-xl hover-glow transition-all duration-300 transform hover:scale-105 inline-block">
                         ابدأ الآن
                         <i data-lucide="chevron-left" class="mr-2 h-5 w-5 inline animate-bounce-slow"></i>
                     </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-16 px-4 bg-gradient-to-r from-gray-50 to-blue-50 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-5">
            <div class="absolute inset-0" style="background-image: radial-gradient(circle at 25% 25%, #3b82f6 2px, transparent 2px); background-size: 50px 50px;"></div>
        </div>
        
        <div class="container mx-auto relative z-10">
                            <div class="text-center mb-12">
                    <div class="animate-fade-in-up">
                        <h2 class="text-3xl font-bold text-blue-600 mb-4">مميزات النظام</h2>
                        <p class="text-gray-600 max-w-2xl mx-auto">
                            نوفر مجموعة شاملة من الأدوات والخدمات لتسهيل العملية التعليمية والإدارية
                        </p>
                    </div>
                </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-soft hover:shadow-medium cursor-pointer p-6 border border-white/20 hover-glow transition-all duration-500 hover:scale-105 animate-fade-in-up" style="animation-delay: 0.1s;">
                    <div class="text-center">
                        <div class="bg-gradient-to-br from-blue-100 to-blue-200 rounded-full p-4 w-16 h-16 mx-auto mb-4 flex items-center justify-center shadow-soft hover:shadow-medium transition-all duration-300 hover:scale-110">
                            <i data-lucide="users" class="h-8 w-8 text-primary animate-pulse-slow"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-2 text-primary">إدارة الأساتذة</h3>
                        <p class="text-gray-600 text-center leading-relaxed">
                            إدارة شاملة لبيانات الأساتذة، الجداول الدراسية، والتقييمات
                        </p>
                    </div>
                </div>

                <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-soft hover:shadow-medium cursor-pointer p-6 border border-white/20 hover-glow transition-all duration-500 hover:scale-105 animate-fade-in-up" style="animation-delay: 0.2s;">
                    <div class="text-center">
                        <div class="bg-gradient-to-br from-green-100 to-green-200 rounded-full p-4 w-16 h-16 mx-auto mb-4 flex items-center justify-center shadow-soft hover:shadow-medium transition-all duration-300 hover:scale-110">
                            <i data-lucide="graduation-cap" class="h-8 w-8 text-accent animate-pulse-slow"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-2 text-accent">إدارة التلاميذ</h3>
                        <p class="text-gray-600 text-center leading-relaxed">متابعة شاملة للطلاب، الدرجات، الحضور والغياب</p>
                    </div>
                </div>

                <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-soft hover:shadow-medium cursor-pointer p-6 border border-white/20 hover-glow transition-all duration-500 hover:scale-105 animate-fade-in-up" style="animation-delay: 0.3s;">
                    <div class="text-center">
                        <div class="bg-gradient-to-br from-purple-100 to-purple-200 rounded-full p-4 w-16 h-16 mx-auto mb-4 flex items-center justify-center shadow-soft hover:shadow-medium transition-all duration-300 hover:scale-110">
                            <i data-lucide="book-open" class="h-8 w-8 text-purple-600 animate-pulse-slow"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-2 text-purple-600">النشر والإعلانات</h3>
                        <p class="text-gray-600 text-center leading-relaxed">
                            نشر الأخبار، الإعلانات، والمعلومات المهمة للمجتمع المدرسي
                        </p>
                    </div>
                </div>

                <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-soft hover:shadow-medium cursor-pointer p-6 border border-white/20 hover-glow transition-all duration-500 hover:scale-105 animate-fade-in-up" style="animation-delay: 0.4s;">
                    <div class="text-center">
                        <div class="bg-gradient-to-br from-orange-100 to-orange-200 rounded-full p-4 w-16 h-16 mx-auto mb-4 flex items-center justify-center shadow-soft hover:shadow-medium transition-all duration-300 hover:scale-110">
                            <i data-lucide="bar-chart-3" class="h-8 w-8 text-orange-600 animate-pulse-slow"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-2 text-orange-600">إحصائيات تفاعلية</h3>
                        <p class="text-gray-600 text-center leading-relaxed">تقارير وإحصائيات مفصلة لمتابعة الأداء والتطور</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Latest Posts Section -->
    <section class="py-16 px-4 bg-gradient-to-l from-gray-50 to-indigo-50 relative overflow-hidden">
        <!-- Background Elements -->
        <div class="absolute inset-0 opacity-5">
            <div class="absolute top-20 right-20 w-32 h-32 bg-gradient-to-br from-blue-300 to-purple-300 rounded-full animate-float"></div>
            <div class="absolute bottom-20 left-20 w-24 h-24 bg-gradient-to-br from-green-300 to-blue-300 rounded-full animate-float" style="animation-delay: 2s;"></div>
        </div>
        
        <div class="container mx-auto relative z-10">
            <div class="flex items-center justify-between mb-12">
                <div class="animate-fade-in-left">
                    <h2 class="text-3xl font-bold text-blue-600 mb-2">آخر الأخبار</h2>
                    <p class="text-gray-600">تابع آخر الأخبار والإعلانات المدرسية</p>
                </div>
                <a href="post.php" class="btn-primary text-white px-6 py-3 rounded-xl hover-glow transition-all duration-300 transform hover:scale-105 animate-fade-in-right inline-block">
                    عرض الكل
                    <i data-lucide="chevron-left" class="mr-2 h-4 w-4 inline animate-bounce-slow"></i>
                </a>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <?php if (empty($posts)): ?>
                <div class="col-span-3 text-center py-12">
                    <div class="bg-white/90 backdrop-blur-sm rounded-xl shadow-soft p-8">
                        <i data-lucide="newspaper" class="h-16 w-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">لا توجد منشورات حالياً</h3>
                        <p class="text-gray-500">سيتم إضافة الأخبار والإعلانات قريباً</p>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($posts as $post): ?>
                <div class="bg-white/90 backdrop-blur-sm rounded-xl shadow-soft hover:shadow-medium cursor-pointer overflow-hidden border border-white/20 hover-glow transition-all duration-500 hover:scale-105 animate-fade-in-up" style="animation-delay: <?php echo array_search($post, $posts) * 0.1 + 0.1; ?>s;">
                    <div class="h-48 bg-gradient-to-br from-blue-100 via-blue-200 to-indigo-200 relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-t from-blue-600/20 to-transparent"></div>
                        <?php if (!empty($post['files'])): ?>
                        <?php 
                        $files = explode(' | ', $post['files']);
                        $firstFile = trim($files[0], '/');
                        if (!empty($firstFile)): 
                        ?>
                        <img src="<?php echo htmlspecialchars($firstFile); ?>" alt="صورة المنشور" class="w-full h-full object-cover">
                        <?php endif; ?>
                        <?php endif; ?>
                        <div class="absolute top-4 right-4 bg-blue-500 text-white px-3 py-1 rounded-full text-xs font-medium">إعلان</div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium">إعلان</span>
                            <span class="text-sm text-gray-500">
                                <?php 
                                $dateParts = explode(' | ', $post['date']);
                                $dateOnly = $dateParts[0] ?? $post['date'];
                                echo htmlspecialchars($dateOnly); 
                                ?>
                            </span>
                        </div>
                        <h3 class="text-lg font-semibold mb-3 text-gray-800">
                            <?php echo htmlspecialchars(substr($post['letter'], 0, 50)) . (strlen($post['letter']) > 50 ? '...' : ''); ?>
                        </h3>
                        <p class="text-gray-600 leading-relaxed">
                            <?php echo htmlspecialchars(substr($post['letter'], 0, 120)) . (strlen($post['letter']) > 120 ? '...' : ''); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Quick Access Section -->
    <section id="login" class="py-16 px-4 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: linear-gradient(45deg, #3b82f6 1px, transparent 1px), linear-gradient(-45deg, #10b981 1px, transparent 1px); background-size: 60px 60px;"></div>
        </div>
        
        <div class="container mx-auto relative z-10">
            <div class="text-center mb-12">
                <div class="animate-fade-in-up">
                    <h2 class="text-3xl font-bold text-blue-600 mb-4">الدخول السريع</h2>
                    <p class="text-gray-600 max-w-2xl mx-auto">
                        اختر نوع حسابك للدخول إلى النظام والوصول إلى الخدمات المخصصة لك
                    </p>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <div class="bg-white/90 backdrop-blur-sm rounded-2xl shadow-soft hover:shadow-strong cursor-pointer border-2 border-transparent hover:border-primary/50 transition-all duration-500 hover:scale-105 p-6 animate-fade-in-up" style="animation-delay: 0.1s;">
                    <div class="text-center">
                        <div class="bg-gradient-to-br from-blue-100 to-blue-200 rounded-full p-6 w-20 h-20 mx-auto mb-4 flex items-center justify-center shadow-soft hover:shadow-medium transition-all duration-300 hover:scale-110">
                            <i data-lucide="graduation-cap" class="h-10 w-10 text-blue-600 animate-pulse-slow"></i>
                        </div>
                        <h3 class="text-2xl font-semibold mb-4 text-blue-600">دخول الطلاب</h3>
                        <p class="text-gray-600 mb-6 leading-relaxed">
                            الوصول إلى الدرجات، الجداول الدراسية، والمواد التعليمية
                        </p>
                        <a href="student.php" class="w-full btn-primary text-white py-3 rounded-xl hover-glow transition-all duration-300 transform hover:scale-105 inline-block text-center">
                            دخول الطلاب
                        </a>
                    </div>
                </div>

                <div class="bg-white/90 backdrop-blur-sm rounded-2xl shadow-soft hover:shadow-strong cursor-pointer border-2 border-transparent hover:border-accent/50 transition-all duration-500 hover:scale-105 p-6 animate-fade-in-up" style="animation-delay: 0.2s;">
                    <div class="text-center">
                        <div class="bg-gradient-to-br from-green-100 to-green-200 rounded-full p-6 w-20 h-20 mx-auto mb-4 flex items-center justify-center shadow-soft hover:shadow-medium transition-all duration-300 hover:scale-110">
                            <i data-lucide="user-check" class="h-10 w-10 text-accent animate-pulse-slow"></i>
                        </div>
                        <h3 class="text-2xl font-semibold mb-4 text-accent">دخول الأساتذة</h3>
                        <p class="text-gray-600 mb-6 leading-relaxed">إدارة الفصول، رصد الدرجات، ومتابعة أداء الطلاب</p>
                        <a href="teacher.php" class="w-full btn-accent text-white py-3 rounded-xl hover-glow transition-all duration-300 transform hover:scale-105 inline-block text-center">
                            دخول الأساتذة
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-16 px-4 bg-gradient-to-r from-gray-50 to-blue-50 relative overflow-hidden">
        <!-- Background Elements -->
        <div class="absolute inset-0 opacity-5">
            <div class="absolute top-1/4 right-1/4 w-64 h-64 bg-gradient-to-br from-blue-300 to-purple-300 rounded-full animate-float"></div>
            <div class="absolute bottom-1/4 left-1/4 w-48 h-48 bg-gradient-to-br from-green-300 to-blue-300 rounded-full animate-float" style="animation-delay: 1.5s;"></div>
        </div>
        
        <div class="container mx-auto relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="animate-fade-in-left">
                    <h2 class="text-3xl font-bold text-blue-600 mb-6">عن ثانوية متقن محمد قروف العالية</h2>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        تأسست ثانوية متقن محمد قروف العالية في بسكرة كمؤسسة تعليمية رائدة تهدف إلى تقديم تعليم عالي الجودة
                        وإعداد جيل من الطلاب المتميزين أكاديمياً وأخلاقياً. تضم المدرسة نخبة من الأساتذة المؤهلين والمتخصصين في
                        مختلف المجالات العلمية والأدبية.
                    </p>
                    <p class="text-gray-600 mb-8 leading-relaxed">
                        نسعى من خلال نظام الإدارة التربوي الحديث إلى تطوير العملية التعليمية وتسهيل التواصل بين جميع أطراف
                        المجتمع المدرسي، مما يساهم في تحقيق أهدافنا التعليمية والتربوية.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <div class="flex items-center gap-3 bg-white/70 backdrop-blur-sm rounded-xl px-4 py-3 shadow-soft hover:shadow-medium transition-all duration-300 hover:scale-105">
                            <div class="bg-gradient-to-br from-blue-100 to-blue-200 rounded-full p-3 shadow-soft">
                                <i data-lucide="users" class="h-5 w-5 text-primary animate-pulse-slow"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">+500 طالب</span>
                        </div>
                        <div class="flex items-center gap-3 bg-white/70 backdrop-blur-sm rounded-xl px-4 py-3 shadow-soft hover:shadow-medium transition-all duration-300 hover:scale-105">
                            <div class="bg-gradient-to-br from-green-100 to-green-200 rounded-full p-3 shadow-soft">
                                <i data-lucide="user-check" class="h-5 w-5 text-accent animate-pulse-slow"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">+40 أستاذ</span>
                        </div>
                        <div class="flex items-center gap-3 bg-white/70 backdrop-blur-sm rounded-xl px-4 py-3 shadow-soft hover:shadow-medium transition-all duration-300 hover:scale-105">
                            <div class="bg-gradient-to-br from-purple-100 to-purple-200 rounded-full p-3 shadow-soft">
                                <i data-lucide="book-open" class="h-5 w-5 text-purple-600 animate-pulse-slow"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-700">جميع التخصصات</span>
                        </div>
                    </div>
                </div>
                <div class="relative animate-fade-in-right">
                    <div class="aspect-square bg-gradient-to-br from-blue-200 via-indigo-200 to-purple-200 rounded-3xl flex items-center justify-center shadow-strong hover:shadow-strong hover:scale-105 transition-all duration-500 overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-t from-blue-600/20 via-transparent to-transparent"></div>
                        <div class="text-center relative z-10">
                            <i data-lucide="graduation-cap" class="h-24 w-24 text-blue-600 mx-auto mb-4 animate-float"></i>
                            <p class="text-lg font-semibold text-blue-600">صورة المدرسة</p>
                            <p class="text-sm text-gray-600">سيتم استبدالها بصورة حقيقية</p>
                        </div>
                    </div>
                    <!-- Floating Elements -->
                    <div class="absolute -top-4 -right-4 w-8 h-8 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full animate-bounce-slow"></div>
                    <div class="absolute -bottom-4 -left-4 w-6 h-6 bg-gradient-to-br from-pink-400 to-red-500 rounded-full animate-pulse-slow"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800 text-white py-12 px-4 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: radial-gradient(circle at 20% 80%, #ffffff 1px, transparent 1px); background-size: 40px 40px;"></div>
        </div>
        
        <div class="container mx-auto relative z-10">
            <div class="grid md:grid-cols-3 gap-8">
                <div class="animate-fade-in-up">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-white/20 backdrop-blur-sm rounded-full p-3 shadow-soft hover:shadow-medium transition-all duration-300 hover:scale-110">
                            <i data-lucide="graduation-cap" class="h-6 w-6 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">ثانوية متقن محمد قروف العالية</h3>
                            <p class="text-blue-100">بسكرة</p>
                        </div>
                    </div>
                    <p class="text-blue-100 leading-relaxed">
                        مؤسسة تعليمية رائدة تسعى لتقديم تعليم متميز وإعداد جيل واعد للمستقبل
                    </p>
                </div>

                <div class="animate-fade-in-up" style="animation-delay: 0.1s;">
                    <h4 class="text-lg font-semibold text-white mb-4">روابط سريعة</h4>
                    <ul class="space-y-3">
                        <li>
                            <a href="#" class="text-blue-100 hover:text-white transition-all duration-300 hover:translate-x-1 inline-block">الرئيسية</a>
                        </li>
                        <li>
                            <a href="#about" class="text-blue-100 hover:text-white transition-all duration-300 hover:translate-x-1 inline-block">عن المدرسة</a>
                        </li>
                        <li>
                            <a href="#login" class="text-blue-100 hover:text-white transition-all duration-300 hover:translate-x-1 inline-block">تسجيل الدخول</a>
                        </li>
                        <li>
                            <a href="post.php" class="text-blue-100 hover:text-white transition-all duration-300 hover:translate-x-1 inline-block">الأخبار</a>
                        </li>
                    </ul>
                </div>

                <div class="animate-fade-in-up" style="animation-delay: 0.2s;">
                    <h4 class="text-lg font-semibold text-white mb-4">تواصل معنا</h4>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-3 hover:bg-white/20 transition-all duration-300 hover:scale-105">
                            <i data-lucide="map-pin" class="h-5 w-5 text-blue-100"></i>
                            <span class="text-blue-100">بسكرة، الجزائر</span>
                        </div>
                        <div class="flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-3 hover:bg-white/20 transition-all duration-300 hover:scale-105">
                            <i data-lucide="phone" class="h-5 w-5 text-blue-100"></i>
                            <span class="text-blue-100">+213 XX XX XX XX</span>
                        </div>
                        <div class="flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-3 hover:bg-white/20 transition-all duration-300 hover:scale-105">
                            <i data-lucide="mail" class="h-5 w-5 text-blue-100"></i>
                            <span class="text-blue-100">info@school.edu.dz</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-white/20 mt-8 pt-8 text-center">
                <p class="text-blue-100">
                    © 2025 ثانوية متقن محمد قروف العالية - بسكرة. جميع الحقوق محفوظة.
                </p>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="back-to-top" class="fixed bottom-8 left-8 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg transition-all duration-300 opacity-0 invisible hover:scale-110 z-40">
        <i data-lucide="chevron-up" class="h-6 w-6"></i>
    </button>





    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Enhanced JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu functionality
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                    // Animate icon rotation
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.style.transform = mobileMenu.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(90deg)';
                        icon.style.transition = 'transform 0.3s ease';
                    }
                });
            }
            
                         // Navbar scroll effect - simplified to prevent auto-scrolling issues
             const navbar = document.getElementById('navbar');
             
             window.addEventListener('scroll', function() {
                 const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                 
                 // Add/remove background shadow based on scroll
                 if (scrollTop > 50) {
                     navbar.classList.add('bg-white/95', 'shadow-lg');
                 } else {
                     navbar.classList.remove('bg-white/95', 'shadow-lg');
                 }
             });
            
            // Back to top button functionality
            const backToTopBtn = document.getElementById('back-to-top');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTopBtn.classList.remove('opacity-0', 'invisible');
                    backToTopBtn.classList.add('opacity-100', 'visible');
                } else {
                    backToTopBtn.classList.add('opacity-0', 'invisible');
                    backToTopBtn.classList.remove('opacity-100', 'visible');
                }
            });
            
            backToTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                        
                        // Close mobile menu if open
                        if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                            mobileMenu.classList.add('hidden');
                        }
                    }
                });
            });
            

            

            

            

            
            // Enhanced hover effects for cards
            document.querySelectorAll('.hover-glow').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02) translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) translateY(0)';
                });
            });
            

            
            // Keyboard navigation support
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    // Close mobile menu
                    if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                        mobileMenu.classList.add('hidden');
                    }
                }
                
                // Back to top with Ctrl+Up
                if (e.ctrlKey && e.key === 'ArrowUp') {
                    e.preventDefault();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });
            
            
            
            
        });
        
        
    </script>
</body>
</html>
