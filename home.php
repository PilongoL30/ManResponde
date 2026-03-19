<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Man-Responde • Emergency Response System</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="responde.png">
    <link rel="icon" type="image/png" sizes="16x16" href="responde.png">
    
    <!-- Tailwind CSS & Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        inter: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            900: '#0c4a6e',
                        },
                        accent: {
                            500: '#22c55e',
                            600: '#16a34a',
                        }
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'fade-up': 'fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' },
                        },
                        fadeUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        .glass-nav {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
        }
        .hero-pattern {
            background-color: #fafbfc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(14, 165, 233, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(34, 197, 94, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(14, 165, 233, 0.05) 0px, transparent 50%);
            background-attachment: fixed;
        }
        .feature-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -5px rgba(0, 0, 0, 0.1);
        }
        .gradient-text {
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* 3D Phone Styles - Android Style */
        .phone-container {
            perspective: 1000px;
            transform-style: preserve-3d;
            height: 600px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .phone-mockup {
            width: 300px;
            height: 600px;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.1s ease-out;
        }
        /* Common shape for all layers */
        .phone-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 36px; /* Sharper Android corners */
            background: linear-gradient(135deg, #1f2937 0%, #111827 50%, #000000 100%); /* Cosmic Black */
            backface-visibility: hidden;
            box-shadow: inset 0 0 6px rgba(255,255,255,0.1), 0 0 20px rgba(0,0,0,0.5);
        }
        
        /* Layer Stacking for "Solid Block" effect */
        .pl-1  { transform: translateZ(0px); }
        .pl-2  { transform: translateZ(-1px); }
        .pl-3  { transform: translateZ(-2px); }
        .pl-4  { transform: translateZ(-3px); }
        .pl-5  { transform: translateZ(-4px); }
        .pl-6  { transform: translateZ(-5px); }
        .pl-7  { transform: translateZ(-6px); }
        .pl-8  { transform: translateZ(-7px); }
        .pl-9  { transform: translateZ(-8px); }
        .pl-10 { transform: translateZ(-9px); }
        .pl-11 { transform: translateZ(-10px); }
        .pl-12 { transform: translateZ(-11px); }
        .pl-13 { transform: translateZ(-12px); }
        .pl-14 { transform: translateZ(-13px); }
        .pl-15 { transform: translateZ(-14px); }

        /* Buttons (Right side) */
        .phone-button {
            position: absolute;
            background: #1f2937;
            border-radius: 2px;
            transform: translateZ(-8px);
            box-shadow: -1px 1px 3px rgba(0,0,0,0.4);
        }
        /* Buttons placed on the right side */
        .btn-right-1 { right: -3px; top: 120px; width: 3px; height: 40px; transform: rotateY(90deg) translateZ(1px); } /* Power */
        .btn-right-2 { right: -3px; top: 180px; width: 3px; height: 60px; transform: rotateY(90deg) translateZ(1px); } /* Vol Rocker */

        .phone-screen {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #000;
            border-radius: 36px;
            overflow: hidden;
            transform: translateZ(2px); 
            border: 3px solid #111; /* Thin black bezel */
            backface-visibility: hidden;
            box-shadow: 0 12px 30px rgba(0,0,0,0.25);
        }
        
        .phone-screen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 32px; 
        }

        /* Punch Hole Camera */
        .punch-hole-camera {
            position: absolute;
            top: 15px;
            left: 50%;
            transform: translateX(-50%);
            width: 12px;
            height: 12px;
            background: #000;
            border-radius: 50%;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .camera-sensor {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #1a1a1a;
            box-shadow: inset 0 0 2px rgba(255,255,255,0.2);
        }

        .phone-back {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #111827; /* Matte Black */
            border-radius: 36px;
            backface-visibility: hidden;
            transform: translateZ(-16px) rotateY(180deg);
            border: 1px solid #000;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.5);
        }
        
        /* Vertical Camera Module */
        .camera-module {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 40px;
            padding-top: 10px;
            padding-bottom: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .camera-lens-wrapper {
             width: 36px;
             height: 36px;
             border-radius: 50%;
             background: #000;
             display: flex;
             align-items: center;
             justify-content: center;
             border: 1px solid #333;
             box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }

        .camera-lens {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #111;
            border: 2px solid #222;
            position: relative;
            overflow: hidden;
        }

        .lens-glass {
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 30% 30%, #444, #000);
            border-radius: 50%;
        }
        
        .lens-glass::after {
            content: '';
            position: absolute;
            top: 25%;
            left: 25%;
            width: 20%;
            height: 20%;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            filter: blur(1px);
        }

        .flash-led {
            width: 10px;
            height: 10px;
            background: #fffde7;
            border-radius: 50%;
            border: 1px solid #fff;
            margin-top: 5px;
            box-shadow: 0 0 5px rgba(255,255,255,0.5);
        }

        .phone-branding {
            position: absolute;
            top: 50%;
            left: 0;
            width: 100%;
            display: flex;
            justify-content: center;
            transform: translateY(-50%);
            opacity: 1;
        }

        .phone-branding img {
            width: 86px;
            height: 86px;
            object-fit: contain;
            filter: none;
            mix-blend-mode: normal;
        }
    </style>
</head>
<body class="font-sans text-slate-800 antialiased bg-slate-50">

    <!-- Navbar -->
    <nav class="fixed w-full z-50 glass-nav transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <a href="#" class="flex items-center gap-3">
                    <img src="responde.png" alt="Man-Responde" class="w-10 h-10 object-contain drop-shadow-sm">
                    <span class="font-bold text-xl tracking-tight text-slate-900">Man-Responde</span>
                </a>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center gap-8">
                    <a href="#home" class="text-sm font-medium text-slate-600 hover:text-brand-600 transition-colors">Home</a>
                    <a href="#features" class="text-sm font-medium text-slate-600 hover:text-brand-600 transition-colors">Features</a>
                    <a href="#download" class="text-sm font-medium text-slate-600 hover:text-brand-600 transition-colors">Download</a>
                    <a href="#about" class="text-sm font-medium text-slate-600 hover:text-brand-600 transition-colors">About</a>
                </div>

                <!-- Action Button -->
                <!-- <div class="hidden md:flex items-center gap-4">
                    <a href="login.php" class="px-5 py-2.5 rounded-full text-sm font-semibold bg-slate-900 text-white hover:bg-slate-800 hover:shadow-lg hover:shadow-slate-900/20 transition-all duration-300 transform active:scale-95">
                        Admin Login
                    </a>
                </div> -->

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobileMenuButton" type="button" class="p-2 text-slate-600 hover:text-slate-900" aria-controls="mobileMenu" aria-expanded="false" aria-label="Toggle navigation menu">
                        <svg id="menuIconOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        <svg id="menuIconClose" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            <div id="mobileMenu" class="md:hidden hidden pb-4 border-t border-slate-200">
                <div class="pt-4 flex flex-col gap-2">
                    <a href="#home" class="px-2 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-brand-600 hover:bg-slate-100 transition-colors mobile-nav-link">Home</a>
                    <a href="#features" class="px-2 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-brand-600 hover:bg-slate-100 transition-colors mobile-nav-link">Features</a>
                    <a href="#download" class="px-2 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-brand-600 hover:bg-slate-100 transition-colors mobile-nav-link">Download</a>
                    <a href="#about" class="px-2 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-brand-600 hover:bg-slate-100 transition-colors mobile-nav-link">About</a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        const menuIconOpen = document.getElementById('menuIconOpen');
        const menuIconClose = document.getElementById('menuIconClose');
        const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');

        if (mobileMenuButton && mobileMenu && menuIconOpen && menuIconClose) {
            const toggleMobileMenu = () => {
                const isHidden = mobileMenu.classList.contains('hidden');

                if (isHidden) {
                    mobileMenu.classList.remove('hidden');
                    menuIconOpen.classList.add('hidden');
                    menuIconClose.classList.remove('hidden');
                    mobileMenuButton.setAttribute('aria-expanded', 'true');
                    return;
                }

                mobileMenu.classList.add('hidden');
                menuIconOpen.classList.remove('hidden');
                menuIconClose.classList.add('hidden');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
            };

            mobileMenuButton.addEventListener('click', toggleMobileMenu);

            mobileNavLinks.forEach((link) => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.add('hidden');
                    menuIconOpen.classList.remove('hidden');
                    menuIconClose.classList.add('hidden');
                    mobileMenuButton.setAttribute('aria-expanded', 'false');
                });
            });
        }
    </script>

    <!-- Hero Section -->
    <section id="home" class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden hero-pattern">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="lg:grid lg:grid-cols-2 gap-16 items-center">
                
                <!-- Text Content -->
                <div class="text-center lg:text-left max-w-2xl mx-auto lg:mx-0">
                    <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-blue-50/80 border border-blue-100 text-brand-600 text-sm font-semibold mb-8 animate-fade-up">
                        <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span>
                        Emergency Response System v2.0
                    </span>
                    
                    <h1 class="text-5xl lg:text-7xl font-extrabold tracking-tight mb-8 animate-fade-up [animation-delay:200ms] text-slate-900 leading-tight">
                        Rapid Response when <br>
                        <span class="bg-gradient-to-r from-brand-600 to-accent-500 bg-clip-text text-transparent">Every Second Counts</span>
                    </h1>
                    
                    <p class="text-lg lg:text-xl text-slate-600 mb-10 leading-relaxed animate-fade-up [animation-delay:400ms]">
                        San Carlos City's advanced emergency coordination platform connecting citizens, responders, and command centers in real-time.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row items-center lg:justify-start justify-center gap-4 animate-fade-up [animation-delay:600ms]">
                        <a href="#download" class="w-full sm:w-auto px-8 py-4 rounded-full bg-brand-600 text-white font-semibold text-lg hover:bg-brand-700 hover:shadow-xl hover:shadow-brand-500/25 transition-all duration-300">
                            Get the App
                        </a>
                        <a href="#features" class="w-full sm:w-auto px-8 py-4 rounded-full text-slate-600 font-semibold text-lg hover:text-brand-600 hover:bg-slate-50 transition-all duration-300">
                            Learn More &rarr;
                        </a>
                    </div>
                </div>

                <!-- 3D Phone Area -->
                <div class="mt-20 lg:mt-0 relative perspective-container w-full flex justify-center lg:block" id="phoneContainer">
                    <div class="phone-container transform scale-75 sm:scale-90 lg:scale-100 origin-center lg:origin-top-left">
                        <div class="phone-mockup" id="phone3d">
                            <!-- Stacked Layers for Rounded 3D Thickness (Android Style) -->
                            <div class="phone-layer pl-1 android-layer"></div>
                            <div class="phone-layer pl-2 android-layer"></div>
                            <div class="phone-layer pl-3 android-layer"></div>
                            <div class="phone-layer pl-4 android-layer"></div>
                            <div class="phone-layer pl-5 android-layer"></div>
                            <div class="phone-layer pl-6 android-layer"></div>
                            <div class="phone-layer pl-7 android-layer"></div>
                            <div class="phone-layer pl-8 android-layer"></div>
                            <div class="phone-layer pl-9 android-layer"></div>
                            <div class="phone-layer pl-10 android-layer"></div>
                            <div class="phone-layer pl-11 android-layer"></div>
                            <div class="phone-layer pl-12 android-layer"></div>
                            <div class="phone-layer pl-13 android-layer"></div>
                            <div class="phone-layer pl-14 android-layer"></div>
                            <div class="phone-layer pl-15 android-layer"></div>

                            <!-- Buttons (Right side usually for Android) -->
                            <div class="phone-button btn-right-1"></div> <!-- Power -->
                            <div class="phone-button btn-right-2"></div> <!-- Vol Up -->
                            <div class="phone-button btn-right-3"></div> <!-- Vol Down -->

                            <!-- Front Screen -->
                            <div class="phone-screen shadow-2xl android-screen">
                                <!-- Punch Hole Camera -->
                                <div class="punch-hole-camera">
                                    <div class="camera-sensor"></div>
                                </div>
                                <div class="absolute top-0 w-full h-8 z-20 flex justify-between px-6 items-center text-[10px] text-white font-medium">
                                    <span class="mt-2">10:00</span>
                                    <div class="flex gap-1.5 mt-2">
                                        <svg class="w-4 h-3 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9v-2h2v2zm0-4H9V7h2v5z"/></svg> 
                                        <svg class="w-5 h-3 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M15.67 4H14V2h-4v2H8.33C7.6 4 7 4.6 7 5.33v15.33C7 21.4 7.6 22 8.33 22h7.33c.74 0 1.34-.6 1.34-1.33V5.33C17 4.6 16.4 4 15.67 4z"/></svg>
                                    </div>
                                </div>
                                <img src="image.png" alt="App Interface" class="h-full w-full object-cover rounded-[32px]">
                            </div>

                            <!-- Back of Phone -->
                            <div class="phone-back android-back">
                                <!-- Camera Module (Vertical Pill like S24 or similar) -->
                                <div class="camera-module">
                                    <div class="camera-lens-wrapper">
                                        <div class="camera-lens">
                                            <div class="lens-glass"></div>
                                        </div>
                                    </div>
                                    <div class="camera-lens-wrapper">
                                        <div class="camera-lens">
                                            <div class="lens-glass"></div>
                                        </div>
                                    </div>
                                    <div class="camera-lens-wrapper">
                                        <div class="camera-lens">
                                            <div class="lens-glass"></div>
                                        </div>
                                    </div>
                                    <!-- Flash -->
                                     <div class="flash-led"></div>
                                </div>

                                <!-- App Logo -->
                                <div class="phone-branding">
                                    <img src="responde.png" alt="Man-Responde Logo">
                                </div>
                            </div>
                        </div>
                    </div>

                    
                    <!-- Floating Badge Removed -->
                </div>

            </div>
        </div>
    </section>

    <!-- Script for 3D Phone Interaction -->
    <script>
        const phone = document.getElementById('phone3d');
        const container = document.getElementById('phoneContainer');

        if(container && phone) {
            // Mouse Interaction (Desktop)
            container.addEventListener('mousemove', (e) => {
                handleMove(e.clientX, e.clientY);
            });

            container.addEventListener('mouseleave', () => {
                resetPhone();
            });
            
            container.addEventListener('mouseenter', () => {
                 phone.style.transition = 'transform 0.1s ease-out';
            });

            // Touch Interaction (Mobile/Tablet)
            container.addEventListener('touchmove', (e) => {
                e.preventDefault(); // Prevent scrolling while rotating phone
                const touch = e.touches[0];
                handleMove(touch.clientX, touch.clientY);
            }, { passive: false });

            container.addEventListener('touchend', () => {
                resetPhone();
            });

            function handleMove(clientX, clientY) {
                const rect = container.getBoundingClientRect();
                const x = clientX - rect.left;
                const y = clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                // Calculate rotation based on cursor/finger position
                const rotateX = ((y - centerY) / centerY) * 15; // Vertical tilt (limited 15deg)
                const rotateY = ((x - centerX) / centerX) * 180; // Full 360 spin allowed
                
                phone.style.transform = `rotateX(${-rotateX}deg) rotateY(${rotateY}deg)`;
            }

            function resetPhone() {
                phone.style.transform = 'rotateX(0deg) rotateY(0deg)';
                phone.style.transition = 'transform 0.5s ease-out';
            }
        }
    </script>

    <!-- Stats Overview -->
    <section class="border-y border-slate-200 bg-white/50 backdrop-blur-sm relative z-20 -mt-8 mx-4 lg:mx-8 rounded-2xl shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div class="space-y-1">
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight">2 min</div>
                    <div class="text-sm text-slate-500 font-semibold uppercase tracking-wider">Avg. Response</div>
                </div>
                <div class="space-y-1 border-l border-slate-100">
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight">24/7</div>
                    <div class="text-sm text-slate-500 font-semibold uppercase tracking-wider">Monitoring</div>
                </div>
                <div class="space-y-1 border-l border-slate-100">
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight">100%</div>
                    <div class="text-sm text-slate-500 font-semibold uppercase tracking-wider">Verified</div>
                </div>
                <div class="space-y-1 border-l border-slate-100">
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight">GPS</div>
                    <div class="text-sm text-slate-500 font-semibold uppercase tracking-wider">Tracking</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Emergency Services -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-brand-600 font-semibold text-sm uppercase tracking-wider mb-2 block">Comprehensive Coverage</span>
                <h2 class="text-3xl font-bold text-slate-900">Emergency Services</h2>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <!-- Service 1 -->
                <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-all text-center group cursor-default border border-slate-100">
                    <div class="w-12 h-12 mx-auto bg-red-100 rounded-full flex items-center justify-center text-red-600 mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z" /></svg>
                    </div>
                    <h3 class="font-bold text-slate-900">Fire Department</h3>
                </div>
                <!-- Service 2 -->
                <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-all text-center group cursor-default border border-slate-100">
                    <div class="w-12 h-12 mx-auto bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    </div>
                    <h3 class="font-bold text-slate-900">Police Assistance</h3>
                </div>
                <!-- Service 3 -->
                <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-all text-center group cursor-default border border-slate-100">
                    <div class="w-12 h-12 mx-auto bg-green-100 rounded-full flex items-center justify-center text-green-600 mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                    </div>
                    <h3 class="font-bold text-slate-900">Medical Emergency</h3>
                </div>
                <!-- Service 4 -->
                <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition-all text-center group cursor-default border border-slate-100">
                    <div class="w-12 h-12 mx-auto bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    <h3 class="font-bold text-slate-900">Traffic Incident</h3>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Timeline -->
    <section class="py-24 bg-white overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
             <div class="text-center mb-20">
                <span class="text-brand-600 font-semibold text-sm uppercase tracking-wider mb-2 block">Workflow</span>
                <h2 class="text-3xl font-bold text-slate-900">How It Works</h2>
                <p class="text-slate-600 mt-4 max-w-2xl mx-auto">A seamless connection between you and the help you need.</p>
            </div>

            <div class="relative">
                <!-- Connector Line (Desktop) -->
                <div class="hidden md:block absolute top-1/2 left-0 w-full h-0.5 bg-gradient-to-r from-transparent via-slate-200 to-transparent -translate-y-1/2 z-0"></div>

                <div class="grid md:grid-cols-3 gap-12 relative z-10">
                    <!-- Step 1 -->
                    <div class="bg-white p-6 text-center">
                        <div class="w-16 h-16 mx-auto bg-slate-900 text-white rounded-2xl flex items-center justify-center text-2xl font-bold mb-6 shadow-xl shadow-slate-900/20 relative">
                            1
                            <div class="absolute -bottom-3 inset-x-0 mx-auto w-3 h-3 bg-slate-900 rotate-45"></div>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-3">Report</h3>
                        <p class="text-slate-600">Open the app and tap the emergency button. Your location is automatically captured.</p>
                    </div>

                    <!-- Step 2 -->
                    <div class="bg-white p-6 text-center">
                        <div class="w-16 h-16 mx-auto bg-brand-600 text-white rounded-2xl flex items-center justify-center text-2xl font-bold mb-6 shadow-xl shadow-brand-600/20 relative">
                            2
                            <div class="absolute -bottom-3 inset-x-0 mx-auto w-3 h-3 bg-brand-600 rotate-45"></div>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-3">Verify & Dispatch</h3>
                        <p class="text-slate-600">Command Center verifies the alert and dispatches the nearest patrol unit.</p>
                    </div>

                    <!-- Step 3 -->
                    <div class="bg-white p-6 text-center">
                        <div class="w-16 h-16 mx-auto bg-accent-600 text-white rounded-2xl flex items-center justify-center text-2xl font-bold mb-6 shadow-xl shadow-accent-600/20 relative">
                            3
                            <div class="absolute -bottom-3 inset-x-0 mx-auto w-3 h-3 bg-accent-600 rotate-45"></div>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-3">Resolve</h3>
                        <p class="text-slate-600">Responders arrive at the scene to provide assistance and resolve updates.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Detailed Features (Previously Feature Grid) -->
    <section id="features" class="py-24 bg-slate-50 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-accent-600 font-semibold text-sm uppercase tracking-wider mb-2 block">Technology</span>
                <h2 class="text-3xl font-bold text-slate-900 mb-4">Core Capabilities</h2>
                <p class="text-slate-600 max-w-2xl mx-auto">Engineered for reliability during critical moments.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Card 1 -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 relative group overflow-hidden hover:shadow-lg transition-all">
                    <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:opacity-10 transition-opacity bg-gradient-to-bl from-brand-500 to-transparent w-32 h-32 rounded-bl-full"></div>
                    <div class="w-14 h-14 rounded-2xl bg-brand-50 shadow-inner flex items-center justify-center mb-6 text-brand-600">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">One-Tap Alert</h3>
                    <p class="text-slate-600 leading-relaxed text-sm">Instantly transmit geolocation, type of emergency, and user priority status to the nearest command center.</p>
                </div>

                <!-- Card 2 -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 relative group overflow-hidden hover:shadow-lg transition-all">
                    <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:opacity-10 transition-opacity bg-gradient-to-bl from-accent-500 to-transparent w-32 h-32 rounded-bl-full"></div>
                    <div class="w-14 h-14 rounded-2xl bg-accent-50 shadow-inner flex items-center justify-center mb-6 text-accent-600">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Real-time Verification</h3>
                    <p class="text-slate-600 leading-relaxed text-sm">AI-assisted filtering and staff verification protocols ensure resources are deployed to genuine emergencies.</p>
                </div>

                <!-- Card 3 -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 relative group overflow-hidden hover:shadow-lg transition-all">
                    <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:opacity-10 transition-opacity bg-gradient-to-bl from-purple-500 to-transparent w-32 h-32 rounded-bl-full"></div>
                    <div class="w-14 h-14 rounded-2xl bg-purple-50 shadow-inner flex items-center justify-center mb-6 text-purple-600">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Live Tracking</h3>
                    <p class="text-slate-600 leading-relaxed text-sm">Monitor responder units and incident reports on an interactive map with live updates and status changes.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- App CTA -->
    <section id="download" class="py-24 bg-slate-900 relative overflow-hidden">
        <!-- Background Effects -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[600px] bg-brand-500 opacity-20 blur-[120px] rounded-full pointer-events-none"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            <h2 class="text-3xl lg:text-4xl font-bold text-white mb-6">Ready to ensure safety?</h2>
            <p class="text-slate-400 text-lg mb-10 max-w-2xl mx-auto">Download the Man-Responde mobile app today and be connected to the city's emergency network.</p>
            
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <button class="bg-gray-800 text-white border border-gray-700 px-6 py-3 rounded-xl font-bold flex items-center justify-center gap-3 hover:bg-gray-700 transition-colors cursor-pointer group">
                    <img src="google play.png" alt="Google Play" class="w-6 h-6 object-contain group-hover:scale-110 transition-transform">
                    <span>Google Play</span>
                </button>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-24 bg-white border-t border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-start">
                <div>
                    <span class="text-brand-600 font-semibold text-sm uppercase tracking-wider mb-2 block">About Man-Responde</span>
                    <h2 class="text-3xl lg:text-4xl font-bold text-slate-900 mb-6">A smarter emergency response platform for San Carlos City</h2>
                    <p class="text-slate-600 leading-relaxed mb-4">
                        Man-Responde is designed to connect citizens, responders, and command center personnel through one coordinated emergency response system. It streamlines report submission, validation, dispatching, and incident tracking to help reduce response delays and improve situational awareness.
                    </p>
                    <p class="text-slate-600 leading-relaxed">
                        By combining real-time reporting, location-based monitoring, and operational dashboards, the platform supports faster decision-making and more organized public safety operations for the community.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <h3 class="font-bold text-slate-900 mb-2">Mission</h3>
                        <p class="text-sm text-slate-600">Deliver reliable and timely digital emergency coordination for every citizen.</p>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
                        <h3 class="font-bold text-slate-900 mb-2">Vision</h3>
                        <p class="text-sm text-slate-600">Build a safer, more responsive city through smart and connected public safety systems.</p>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 sm:col-span-2">
                        <h3 class="font-bold text-slate-900 mb-2">Core Services</h3>
                        <p class="text-sm text-slate-600">Emergency reporting, incident verification, responder dispatch tracking, and operational analytics in one centralized platform.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center gap-3 mb-6">
                        <img src="responde.png" alt="Logo" class="w-10 h-10 object-contain">
                        <span class="font-bold text-xl text-slate-900">Man-Responde</span>
                    </div>
                    <p class="text-slate-500 mb-6 leading-relaxed max-w-sm">
                        Empowering San Carlos City with rapid emergency response technology. Connecting citizens to help when it matters most.
                    </p>
                </div>
                
                <div>
                    <h4 class="font-bold text-slate-900 mb-6">Contact Us</h4>
                    <ul class="space-y-4 text-sm text-slate-500">
                        <li class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-brand-600 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>San Carlos City Hall, Pangasinan<br>Philippines 2420</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-brand-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span>Hotline: 911 / (075) 123-4567</span>
                        </li>
                        <li class="flex items-center gap-3">
                           <svg class="w-5 h-5 text-brand-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span>support@manresponde.gov.ph</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                     <h4 class="font-bold text-slate-900 mb-6">Legal</h4>
                     <ul class="space-y-3 text-sm text-slate-500">
                        <li><a href="#" data-policy="privacy" class="hover:text-brand-600 transition-colors policy-link">Privacy Policy</a></li>
                        <li><a href="#" data-policy="terms" class="hover:text-brand-600 transition-colors policy-link">Terms of Service</a></li>
                        <li><a href="#" data-policy="cookies" class="hover:text-brand-600 transition-colors policy-link">Cookie Policy</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-slate-100 mt-16 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-slate-400">
                <div class="text-center md:text-left">
                    <div>&copy; <?php echo date('Y'); ?> Man-Responde System. All rights reserved.</div>
                    <div>Developer: Lord Don Ace Pilongo</div>
                </div>
                <div class="flex items-center gap-6">
                    <!-- <a href="login.php" class="hover:text-slate-600 transition-colors">Admin Portal</a> -->
                </div>
            </div>
        </div>
    </footer>

    <div id="policyModal" class="fixed inset-0 z-[100] hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="policyModalTitle">
        <div id="policyModalBackdrop" class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4 sm:p-6">
            <div class="w-full max-w-2xl bg-white/95 backdrop-blur-xl rounded-3xl border border-white/70 shadow-[0_25px_80px_rgba(15,23,42,0.35)] overflow-hidden">
                <div class="px-6 sm:px-8 py-5 border-b border-slate-200/80 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shadow-sm">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                        </div>
                        <h3 id="policyModalTitle" class="text-xl font-bold tracking-tight text-slate-900">Policy</h3>
                    </div>
                    <button id="policyModalClose" type="button" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg p-1.5 transition-colors" aria-label="Close modal">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div id="policyModalBody" class="px-6 sm:px-8 py-6 text-[15px] text-slate-600 leading-7 max-h-[70vh] overflow-y-auto"></div>
            </div>
        </div>
    </div>

    <script>
        const policyLinks = document.querySelectorAll('.policy-link');
        const policyModal = document.getElementById('policyModal');
        const policyModalTitle = document.getElementById('policyModalTitle');
        const policyModalBody = document.getElementById('policyModalBody');
        const policyModalClose = document.getElementById('policyModalClose');
        const policyModalBackdrop = document.getElementById('policyModalBackdrop');

        const policyContent = {
            privacy: {
                title: 'Privacy Policy',
                body: `
                    <p>Man-Responde is committed to protecting your personal information. Data collected through emergency reports, such as name, contact details, incident description, and approximate location, is used solely to validate incidents, coordinate emergency response, and improve public safety operations.</p>
                    <p class="mt-4">Only authorized personnel of the Man-Responde system may access report data. Information is handled with confidentiality and is not shared with unauthorized third parties except when required by law or for legitimate emergency response coordination.</p>
                    <p class="mt-4">By using Man-Responde, users acknowledge and consent to the collection and use of relevant report information for operational and security purposes within the system.</p>
                `
            },
            terms: {
                title: 'Terms of Service',
                body: `
                    <p>By accessing and using Man-Responde, users agree to provide truthful and accurate emergency information. Any false reporting, misuse, or malicious activity within the platform may result in account restriction and possible legal action under applicable regulations.</p>
                    <p class="mt-4">Users are responsible for safeguarding their account credentials and for all activities performed under their accounts. The system administrators reserve the right to monitor usage and suspend access if violations are detected.</p>
                    <p class="mt-4">Man-Responde is intended for emergency coordination support. While the platform aims to improve response processes, response time and outcomes may still depend on operational constraints, network availability, and field conditions.</p>
                `
            },
            cookies: {
                title: 'Cookie Policy',
                body: `
                    <p>Man-Responde uses essential cookies and session technologies to maintain secure logins, improve navigation, and support core platform functionality. These cookies are necessary for the system to operate reliably.</p>
                    <p class="mt-4">Cookies may also be used for basic performance monitoring and user experience improvement. No sensitive personal data is intentionally stored in cookies beyond what is required for secure session handling.</p>
                    <p class="mt-4">By continuing to use the platform, users agree to the use of cookies required for system security, performance, and service continuity.</p>
                `
            }
        };

        if (policyLinks.length && policyModal && policyModalTitle && policyModalBody && policyModalClose && policyModalBackdrop) {
            const openPolicyModal = (type) => {
                const selectedPolicy = policyContent[type];
                if (!selectedPolicy) {
                    return;
                }

                policyModalTitle.textContent = selectedPolicy.title;
                policyModalBody.innerHTML = selectedPolicy.body;
                policyModal.classList.remove('hidden');
                policyModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            };

            const closePolicyModal = () => {
                policyModal.classList.add('hidden');
                policyModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            };

            policyLinks.forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    const policyType = link.getAttribute('data-policy');
                    openPolicyModal(policyType);
                });
            });

            policyModalClose.addEventListener('click', closePolicyModal);
            policyModalBackdrop.addEventListener('click', closePolicyModal);

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !policyModal.classList.contains('hidden')) {
                    closePolicyModal();
                }
            });
        }
    </script>

</body>
</html>
