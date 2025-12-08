<head>
    <meta charset="UTF-8">
    <title>ManResponde • Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Emergency response management system for barangay services">
    <meta name="theme-color" content="#2563eb">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ManResponde">
    <link rel="manifest" href="manifest.json">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="responde.png">
    <link rel="icon" type="image/png" sizes="16x16" href="responde.png">
    <link rel="apple-touch-icon" href="responde.png">
    <link rel="shortcut icon" href="responde.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ========================================
           PREMIUM DESIGN SYSTEM - PROFESSIONAL DASHBOARD
           Sophisticated Color Palette | Aurora Effects | Glassmorphism
           ======================================== */
        
        :root {
            /* === PREMIUM COLOR PALETTE === */
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --accent: #06b6d4;
            --accent-light: #0891b2;
            --success: #10b981;
            --success-light: #34d399;
            --warning: #f59e0b;
            --warning-light: #fbbf24;
            --danger: #ef4444;
            --danger-light: #f87171;
            
            /* === SOPHISTICATED NEUTRALS === */
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* === PREMIUM SHADOWS === */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --shadow-inner: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
            
            /* === GLASSMORPHISM === */
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.2);
            --glass-blur: blur(20px);
            
            /* === TRANSITIONS === */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-smooth: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
            
            /* === BORDER RADIUS === */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.25rem;
            --radius-3xl: 1.5rem;
        }

        /* === DARK MODE VARIABLES === */
        html.dark {
            --white: #0f172a;
            --gray-50: #1e293b;
            --gray-100: #334155;
            --gray-200: #475569;
            --gray-300: #64748b;
            --gray-400: #94a3b8;
            --gray-500: #cbd5e1;
            --gray-600: #e2e8f0;
            --gray-700: #f1f5f9;
            --gray-800: #f8fafc;
            --gray-900: #ffffff;
            
            --glass-bg: rgba(30, 41, 59, 0.85);
            --glass-border: rgba(255, 255, 255, 0.1);
            
            --primary: #3b82f6;
            --primary-light: #60a5fa;
            --primary-dark: #2563eb;
        }
        
        * {
            transition: background-color var(--transition-smooth), 
                       border-color var(--transition-smooth),
                       color var(--transition-smooth);
        }

        /* ========================================
           FOUNDATION STYLES
           ======================================== */
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-feature-settings: "cv02", "cv03", "cv04", "cv11";
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* ========================================
           STUNNING AURORA BACKGROUND
           ======================================== */
        
        .aurora-background {
            position: relative;
            min-height: 100vh;
            background: linear-gradient(135deg, 
                var(--gray-50) 0%, 
                #fafbff 25%, 
                #f0f4ff 50%, 
                #e8f2ff 75%, 
                var(--gray-100) 100%);
            overflow: hidden;
        }

        .aurora-background::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(ellipse 800px 600px at -10% -20%, rgba(59, 130, 246, 0.15), transparent 50%),
                radial-gradient(ellipse 600px 800px at 110% -10%, rgba(16, 185, 129, 0.12), transparent 50%),
                radial-gradient(ellipse 700px 500px at 50% 120%, rgba(139, 92, 246, 0.1), transparent 50%),
                radial-gradient(ellipse 500px 400px at 80% 80%, rgba(6, 182, 212, 0.08), transparent 50%);
            animation: aurora-drift 20s ease-in-out infinite alternate;
            z-index: -1;
        }

        .aurora-background::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse 400px 300px at 30% 70%, rgba(236, 72, 153, 0.08), transparent 40%),
                radial-gradient(ellipse 300px 400px at 70% 30%, rgba(245, 158, 11, 0.06), transparent 40%);
            animation: aurora-pulse 15s ease-in-out infinite alternate-reverse;
            z-index: -1;
        }
        
        /* Dark mode aurora */
        html.dark .aurora-background {
            background: linear-gradient(135deg, 
                #0f172a 0%, 
                #1e293b 25%, 
                #1e2538 50%, 
                #1a2332 75%, 
                #0f172a 100%);
        }
        
        html.dark .aurora-background::before {
            background: 
                radial-gradient(ellipse 800px 600px at -10% -20%, rgba(59, 130, 246, 0.25), transparent 50%),
                radial-gradient(ellipse 600px 800px at 110% -10%, rgba(16, 185, 129, 0.18), transparent 50%),
                radial-gradient(ellipse 700px 500px at 50% 120%, rgba(139, 92, 246, 0.15), transparent 50%),
                radial-gradient(ellipse 500px 400px at 80% 80%, rgba(6, 182, 212, 0.12), transparent 50%);
        }
        
        html.dark .aurora-background::after {
            background: 
                radial-gradient(ellipse 400px 300px at 30% 70%, rgba(236, 72, 153, 0.12), transparent 40%),
                radial-gradient(ellipse 300px 400px at 70% 30%, rgba(245, 158, 11, 0.09), transparent 40%);
        }

        @keyframes aurora-drift {
            0% { transform: translateX(0) translateY(0) rotate(0deg); }
            50% { transform: translateX(100px) translateY(-50px) rotate(180deg); }
            100% { transform: translateX(0) translateY(0) rotate(360deg); }
        }

        @keyframes aurora-pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.1); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* ========================================
           PREMIUM GLASSMORPHISM COMPONENTS
           ======================================== */
        
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-xl), 
                        0 0 0 1px rgba(255, 255, 255, 0.05),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            transition: all var(--transition-smooth);
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.4), 
                transparent);
        }

        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-2xl), 
                        0 0 0 1px rgba(255, 255, 255, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.15);
        }

        /* ========================================
           SOPHISTICATED KPI CARDS
           ======================================== */
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 1.75rem;
            position: relative;
            overflow: hidden;
            transition: all var(--transition-smooth);
            box-shadow: var(--shadow-lg),
                        0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, 
                var(--primary) 0%, 
                var(--accent) 50%, 
                var(--success) 100%);
            opacity: 0.8;
        }

        .kpi-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: var(--shadow-2xl),
                        0 0 0 1px rgba(255, 255, 255, 0.1),
                        0 0 50px rgba(37, 99, 235, 0.15);
        }

        .kpi-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-500);
            margin-bottom: 0.5rem;
            display: block;
        }

        .kpi-value {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--gray-900);
            line-height: 1;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .kpi-spark {
            height: 32px;
            margin-top: 0.75rem;
            opacity: 0.8;
        }

        /* ========================================
           ELEVATED STAT CARDS
           ======================================== */
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 2rem;
            position: relative;
            overflow: hidden;
            transition: all var(--transition-smooth);
            box-shadow: var(--shadow-lg),
                        0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(37, 99, 235, 0.03), transparent);
            animation: stat-card-rotate 20s linear infinite;
            z-index: -1;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-2xl),
                        0 0 0 1px rgba(255, 255, 255, 0.1),
                        0 0 60px rgba(37, 99, 235, 0.2);
        }

        @keyframes stat-card-rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ========================================
           PREMIUM BUTTON SYSTEM
           ======================================== */
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1;
            border-radius: var(--radius-lg);
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-fast);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left var(--transition-smooth);
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            box-shadow: var(--shadow-md), 0 0 20px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            box-shadow: var(--shadow-xl), 0 0 30px rgba(37, 99, 235, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), var(--success-light));
            color: var(--white);
            box-shadow: var(--shadow-md), 0 0 20px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, var(--success));
            box-shadow: var(--shadow-xl), 0 0 30px rgba(16, 185, 129, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), var(--warning-light));
            color: var(--white);
            box-shadow: var(--shadow-md), 0 0 20px rgba(245, 158, 11, 0.3);
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #d97706, var(--warning));
            box-shadow: var(--shadow-xl), 0 0 30px rgba(245, 158, 11, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), var(--danger-light));
            color: var(--white);
            box-shadow: var(--shadow-md), 0 0 20px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, var(--danger));
            box-shadow: var(--shadow-xl), 0 0 30px rgba(239, 68, 68, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, var(--accent), #0891b2);
            color: var(--white);
            box-shadow: var(--shadow-md), 0 0 20px rgba(6, 182, 212, 0.3);
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #0e7490, var(--accent));
            box-shadow: var(--shadow-xl), 0 0 30px rgba(6, 182, 212, 0.4);
        }

        .btn-secondary {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.9);
            border-color: var(--gray-300);
        }

        /* Legacy button compatibility */
        .btn-view { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: var(--white); }
        .btn-approve { background: linear-gradient(135deg, var(--success), var(--success-light)); color: var(--white); }
        .btn-decline { background: linear-gradient(135deg, var(--danger), var(--danger-light)); color: var(--white); }
        .btn-confirm { background: linear-gradient(135deg, var(--success), var(--success-light)); color: var(--white); }
        .btn-disabled { background: var(--gray-200); color: var(--gray-400); cursor: not-allowed; box-shadow: none; }

        /* ========================================
           PREMIUM INPUT SYSTEM
           ======================================== */
        
        .input, .activity-filter input, .activity-filter select,
        #createStaffForm input, #createStaffForm select,
        #createResponderForm input, #createResponderForm select,
        #vuSearch, #vuPageSize {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            color: var(--gray-900);
            transition: all var(--transition-fast);
            box-shadow: var(--shadow-sm), inset 0 1px 2px rgba(0, 0, 0, 0.05);
            outline: none;
        }

        .input:focus, .activity-filter input:focus, .activity-filter select:focus,
        #createStaffForm input:focus, #createStaffForm select:focus,
        #createResponderForm input:focus, #createResponderForm select:focus,
        #vuSearch:focus, #vuPageSize:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1),
                        var(--shadow-md);
            transform: translateY(-1px);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            display: inline-flex;
            z-index: 1;
        }

        .input.with-icon {
            padding-left: 2.75rem;
        }

        .input-premium {
            background: var(--glass-bg) !important;
            backdrop-filter: var(--glass-blur) !important;
            -webkit-backdrop-filter: var(--glass-blur) !important;
            border: 1px solid var(--glass-border) !important;
            border-radius: var(--radius-xl) !important;
        }

        .input-premium:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15) !important;
        }

        /* ========================================
           SOPHISTICATED TABLE STYLING
           ======================================== */
        
        .table-premium table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .table-premium thead th {
            background: linear-gradient(135deg, 
                rgba(37, 99, 235, 0.05), 
                rgba(6, 182, 212, 0.05));
            color: var(--gray-700);
            font-weight: 700;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            position: relative;
        }

        .table-premium thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            opacity: 0.6;
        }

        .table-premium tbody td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            transition: all var(--transition-fast);
        }

        .table-premium tbody tr {
            transition: all var(--transition-fast);
        }

        .table-premium tbody tr:nth-child(even) {
            background: rgba(248, 250, 252, 0.4);
        }

        .table-premium tbody tr:hover {
            background: rgba(37, 99, 235, 0.08);
            transform: scale(1.01);
        }

        /* ========================================
           ELEGANT CUSTOM CHECKBOX
           ======================================== */
        
        .custom-checkbox {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            cursor: pointer;
            transition: all var(--transition-smooth);
            user-select: none;
            box-shadow: var(--shadow-sm);
        }

        .custom-checkbox:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .custom-checkbox input[type="checkbox"] {
            position: absolute;
            inset: 0;
            margin: 0;
            opacity: 0;
            cursor: pointer;
        }

        .custom-checkbox .box {
            width: 24px;
            height: 24px;
            border-radius: var(--radius-sm);
            border: 2px solid var(--gray-300);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--white);
            transition: all var(--transition-fast);
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .custom-checkbox .box::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            transform: translate(-50%, -50%) scale(0);
            transition: transform var(--transition-fast);
            border-radius: 50%;
        }

        .custom-checkbox .box svg {
            width: 16px;
            height: 16px;
            color: var(--white);
            opacity: 0;
            transform: scale(0.6);
            transition: all var(--transition-fast);
            z-index: 1;
        }

        .custom-checkbox input[type="checkbox"]:checked + .box {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 0 20px rgba(37, 99, 235, 0.4);
        }

        .custom-checkbox input[type="checkbox"]:checked + .box::before {
            transform: translate(-50%, -50%) scale(1);
        }

        .custom-checkbox input[type="checkbox"]:checked + .box svg {
            opacity: 1;
            transform: scale(1);
        }

        .custom-checkbox .text {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        /* ========================================
           FLUID ANIMATIONS & MICRO-INTERACTIONS
           ======================================== */
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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

        @keyframes fadeInDown {
            from { 
                opacity: 0; 
                transform: translateY(-30px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }

        @keyframes fadeOutDown {
            from { 
                opacity: 1; 
                transform: translateY(0);
            }
            to { 
                opacity: 0; 
                transform: translateY(20px);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {
            0%, 100% { 
                opacity: 1;
                transform: scale(1);
            }
            50% { 
                opacity: 0.8;
                transform: scale(1.05);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% {
                animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
                transform: translate3d(0,0,0);
            }
            40%, 43% {
                animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
                transform: translate3d(0, -30px, 0);
            }
            70% {
                animation-timing-function: cubic-bezier(0.755, 0.050, 0.855, 0.060);
                transform: translate3d(0, -15px, 0);
            }
            90% {
                transform: translate3d(0,-4px,0);
            }
        }

        /* Animation Classes */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .animate-fade-in-up {
            opacity: 0;
            animation: fadeInUp 0.6s cubic-bezier(0.215, 0.610, 0.355, 1.000) forwards;
            animation-delay: var(--anim-delay, 0ms);
        }

        .animate-fade-in-down {
            opacity: 0;
            animation: fadeInDown 0.6s cubic-bezier(0.215, 0.610, 0.355, 1.000) forwards;
            animation-delay: var(--anim-delay, 0ms);
        }

        .animate-fade-out-down {
            animation: fadeOutDown 0.4s ease-in forwards;
        }

        .animate-slide-in-right {
            opacity: 0;
            animation: slideInRight 0.6s cubic-bezier(0.215, 0.610, 0.355, 1.000) forwards;
            animation-delay: var(--anim-delay, 0ms);
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .animate-spin {
            animation: spin 1s linear infinite;
        }

        .animate-spin-fast {
            animation: spin 0.8s linear infinite;
        }

        .animate-bounce {
            animation: bounce 1s infinite;
        }

        /* ========================================
           MODAL ENHANCEMENTS
           ======================================== */
        
        .modal-header {
            background: linear-gradient(135deg, 
                rgba(37, 99, 235, 0.05), 
                rgba(6, 182, 212, 0.03));
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-bottom: 1px solid var(--glass-border);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-2xl);
        }

        /* ========================================
           RESPONSIVE DESIGN
           ======================================== */
        
        @media (max-width: 768px) {
            .kpi-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .kpi-card, .stat-card {
                padding: 1.25rem;
            }
            
            .btn {
                padding: 0.625rem 1rem;
                font-size: 0.8125rem;
            }
        }

        /* ========================================
           DARK MODE ENHANCEMENTS (DISABLED)
           ======================================== */
        /*
        html.dark body {
            background: linear-gradient(135deg, 
                #0f172a 0%, 
                #1e293b 25%, 
                #334155 50%, 
                #1e293b 75%, 
                #0f172a 100%);
        }

        html.dark .aurora-background {
            background: linear-gradient(135deg, 
                #0f172a 0%, 
                #1a1f2e 25%, 
                #252a3a 50%, 
                #1a1f2e 75%, 
                #0f172a 100%);
        }

        html.dark .aurora-background::before {
            background: 
                radial-gradient(ellipse 800px 600px at -10% -20%, rgba(59, 130, 246, 0.2), transparent 50%),
                radial-gradient(ellipse 600px 800px at 110% -10%, rgba(16, 185, 129, 0.15), transparent 50%),
                radial-gradient(ellipse 700px 500px at 50% 120%, rgba(139, 92, 246, 0.12), transparent 50%);
        }

        html.dark .kpi-card,
        html.dark .stat-card,
        html.dark .glass-card,
        html.dark .table-premium table,
        html.dark .custom-checkbox {
            background: rgba(15, 23, 42, 0.8);
            border-color: rgba(255, 255, 255, 0.1);
        }

        html.dark .kpi-label {
            color: var(--gray-400);
        }

        html.dark .kpi-value {
            color: var(--gray-100);
        }
        */

        /* ========================================
           UTILITY CLASSES
           ======================================== */
        
        .text-gradient {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .glow {
            box-shadow: 0 0 30px rgba(37, 99, 235, 0.3);
        }

        .glow-success {
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.3);
        }

        .glow-warning {
            box-shadow: 0 0 30px rgba(245, 158, 11, 0.3);
        }

        .glow-danger {
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.3);
        }

        .backdrop-blur {
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
        }

        /* ========================================
           LOADING STATES
           ======================================== */
        
        .loading-shimmer {
            background: linear-gradient(90deg, 
                rgba(255, 255, 255, 0.0) 0%, 
                rgba(255, 255, 255, 0.2) 20%, 
                rgba(255, 255, 255, 0.5) 60%, 
                rgba(255, 255, 255, 0.0) 100%);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* ========================================
           ADDITIONAL LEGACY STYLES INTEGRATION
           ======================================== */
        
        @keyframes fadeOutDown {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(20px);
            }
        }

        @keyframes pulse { 
            0% { transform: scale(0.9) rotate(0deg); } 
            100% { transform: scale(1.2) rotate(20deg); } 
        }

        /* Stats progress bar */
        .progress-track { 
            height: 10px; 
            width: 100%; 
            background: rgba(148,163,184,0.28); 
            border-radius: 9999px; 
            overflow: hidden; 
        }
        
        .progress-seg { 
            height: 100%; 
            display: inline-block; 
            width: 0%; 
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .progress-seg.approved { 
            background: linear-gradient(90deg, #10b981, #34d399); 
        }
        
        .progress-seg.pending { 
            background: linear-gradient(90deg, #f59e0b, #fbbf24); 
        }
        
        .progress-seg.other { 
            background: linear-gradient(90deg, #94a3b8, #cbd5e1); 
        }
        
        .progress-seg.declined { 
            background: linear-gradient(90deg, #ef4444, #f87171); 
        }

        /* Recent Activity */
        .activity-filter { 
            display: grid; 
            gap: 0.5rem; 
            grid-template-columns: 1fr; 
        }
        
        @media (min-width: 768px) { 
            .activity-filter { 
                grid-template-columns: 1fr 180px 160px auto; 
            } 
        }
        
        .activity-scroll { 
            max-height: calc(100vh - 360px); 
            overflow-y: auto; 
            overscroll-behavior: contain; 
        }
        
        .activity-scroll::-webkit-scrollbar { 
            width: 10px; 
        }
        
        .activity-scroll::-webkit-scrollbar-track { 
            background: var(--gray-100); 
            border-radius: 9999px; 
        }
        
        .activity-scroll::-webkit-scrollbar-thumb { 
            background: var(--gray-300); 
            border-radius: 9999px; 
            transition: background var(--transition-fast);
        }
        
        .activity-scroll::-webkit-scrollbar-thumb:hover { 
            background: var(--gray-400); 
        }
        
        /* Video Player Styles */
        #m_video {
            background: #000;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
        }
        
        #m_video::-webkit-media-controls-panel {
            background-color: rgba(0, 0, 0, 0.8);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }
        
        #m_video::-webkit-media-controls-play-button,
        #m_video::-webkit-media-controls-pause-button {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
        }
        
        .video-container {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-50);
            border-radius: var(--radius-xl);
            overflow: hidden;
        }
        
        /* Brand pill for section headers */
        .brand-pill { 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            padding: 0.375rem 0.75rem; 
            border-radius: 9999px; 
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border); 
            font-weight: 700; 
            font-size: 0.75rem; 
            color: var(--gray-700);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-fast);
        }

        .brand-pill:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* ========================================
           PREMIUM ACTIVITY CARDS
           ======================================== */
        
        .activity-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: all var(--transition-smooth);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                var(--primary) 0%, 
                var(--accent) 50%, 
                var(--success) 100%);
            opacity: 0;
            transition: opacity var(--transition-fast);
        }

        .activity-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: var(--shadow-xl), 
                        0 0 40px rgba(37, 99, 235, 0.15);
        }

        .activity-card:hover::before {
            opacity: 1;
        }

        .activity-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-2xl);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
            box-shadow: var(--shadow-lg);
        }

        .activity-icon::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: var(--shadow-sm);
        }

        .activity-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.9));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all var(--transition-fast);
        }

        .activity-status-badge .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .activity-status-approved {
            color: var(--success);
            border-color: rgba(16, 185, 129, 0.2);
        }

        .activity-status-approved .status-dot {
            background: var(--success);
            animation: pulse 2s infinite;
        }

        .activity-status-pending {
            color: var(--warning);
            border-color: rgba(245, 158, 11, 0.2);
        }

        .activity-status-pending .status-dot {
            background: var(--warning);
            animation: pulse 2s infinite;
        }

        .activity-status-declined {
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.2);
        }

        .activity-status-declined .status-dot {
            background: var(--danger);
            animation: pulse 2s infinite;
        }

        /* Status Badges for Tables */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
        }
        
        .status-badge-success {
            background-color: #dcfce7; /* green-100 */
            color: #166534; /* green-800 */
        }
        
        .status-badge-pending {
            background-color: #fef3c7; /* amber-100 */
            color: #92400e; /* amber-800 */
        }
        
        .status-badge-declined {
            background-color: #fee2e2; /* red-100 */
            color: #991b1b; /* red-800 */
        }

        .activity-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-400);
            font-size: 0.875rem;
        }

        .activity-hover-arrow {
            opacity: 0;
            transform: translateX(-10px);
            transition: all var(--transition-fast);
        }

        .activity-card:hover .activity-hover-arrow {
            opacity: 1;
            transform: translateX(0);
        }

        /* Enhanced scrollbar for activity list */
        .activity-scroll::-webkit-scrollbar {
            width: 12px;
        }

        .activity-scroll::-webkit-scrollbar-track {
            background: rgba(148, 163, 184, 0.1);
            border-radius: 6px;
        }

        .activity-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 6px;
            border: 2px solid transparent;
            background-clip: content-box;
        }

        .activity-scroll::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            background-clip: content-box;
        }

        /* ========================================
           SKELETON LOADING SCREENS
           ======================================== */
        
        @keyframes skeleton-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        @keyframes skeleton-shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
        
        .skeleton {
            animation: skeleton-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            background: linear-gradient(
                90deg,
                var(--gray-200) 0%,
                var(--gray-100) 20%,
                var(--gray-200) 40%,
                var(--gray-200) 100%
            );
            background-size: 1000px 100%;
            animation: skeleton-shimmer 2s linear infinite;
            border-radius: var(--radius-md);
        }
        
        html.dark .skeleton {
            background: linear-gradient(
                90deg,
                var(--gray-700) 0%,
                var(--gray-600) 20%,
                var(--gray-700) 40%,
                var(--gray-700) 100%
            );
            background-size: 1000px 100%;
        }
        
        .skeleton-card {
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
        }
        
        .skeleton-text {
            height: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .skeleton-title {
            height: 1.5rem;
            width: 60%;
            margin-bottom: 1rem;
        }
        
        .skeleton-avatar {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
        }
        
        .skeleton-button {
            height: 2.5rem;
            width: 8rem;
            border-radius: var(--radius-lg);
        }

        /* ========================================
           DARK MODE TOGGLE BUTTON
           ======================================== */
        
        .theme-toggle {
            position: relative;
            width: 3.5rem;
            height: 2rem;
            background: var(--gray-200);
            border-radius: 9999px;
            cursor: pointer;
            transition: all var(--transition-smooth);
            border: 2px solid transparent;
            box-shadow: var(--shadow-sm);
        }
        
        html.dark .theme-toggle {
            background: var(--gray-700);
        }
        
        .theme-toggle:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }
        
        .theme-toggle-slider {
            position: absolute;
            top: 0.125rem;
            left: 0.125rem;
            width: 1.5rem;
            height: 1.5rem;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 50%;
            transition: all var(--transition-smooth);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        html.dark .theme-toggle-slider {
            transform: translateX(1.5rem);
            background: linear-gradient(135deg, #818cf8, #6366f1);
        }
        
        .theme-toggle-icon {
            font-size: 0.75rem;
            color: white;
        }
        
        /* ========================================
           ENHANCED ANIMATIONS
           ======================================== */
        
        @keyframes fade-in-up {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fade-in-down {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes scale-in {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes slide-in-right {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fade-in-up 0.6s ease-out forwards;
        }
        
        .animate-fade-in-down {
            animation: fade-in-down 0.6s ease-out forwards;
        }
        
        .animate-scale-in {
            animation: scale-in 0.4s ease-out forwards;
        }
        
        .animate-slide-in-right {
            animation: slide-in-right 0.5s ease-out forwards;
        }
        
        /* Staggered animation delays */
        .animate-delay-100 { animation-delay: 100ms; }
        .animate-delay-200 { animation-delay: 200ms; }
        .animate-delay-300 { animation-delay: 300ms; }
        .animate-delay-400 { animation-delay: 400ms; }
        .animate-delay-500 { animation-delay: 500ms; }
        
        /* Smooth hover transitions */
        .hover-lift {
            transition: transform var(--transition-smooth), box-shadow var(--transition-smooth);
        }
        
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-2xl);
        }
        
        .hover-glow {
            position: relative;
            transition: all var(--transition-smooth);
        }
        
        .hover-glow::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            opacity: 0;
            background: linear-gradient(135deg, 
                rgba(59, 130, 246, 0.1), 
                rgba(16, 185, 129, 0.1));
            transition: opacity var(--transition-smooth);
            pointer-events: none;
        }
        
        .hover-glow:hover::after {
            opacity: 1;
        }
    </style>
</head>
