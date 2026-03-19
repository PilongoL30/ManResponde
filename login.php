<?php
// login.php

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = __DIR__ . '/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0755, true);
    }
    session_save_path($sessionPath);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
}

require_once __DIR__ . '/includes/csrf.php';

$bootstrapError = '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Kreait\Firebase\Exception\Auth\InvalidPassword;
use Kreait\Firebase\Exception\Auth\UserNotFound;

if (!$bootstrapError && isset($_SESSION['user_id']) && $_SESSION['user_id']) {
    header('Location: dashboard.php');
    exit;
}

$error_message = $bootstrapError;

if (!$bootstrapError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once __DIR__ . '/db_config.php';
    } catch (Throwable $e) {
        $bootstrapError = 'System configuration error. Please contact administrator.';
        error_log('[login] Bootstrap error: ' . $e->getMessage());
    }

    if ($bootstrapError) {
        $error_message = $bootstrapError;
    } else {
    // Verify CSRF token
    if (!csrf_verify_token()) {
        // Safe debug logging (do not log token/id_token values)
        try {
            $hasCsrfPost = isset($_POST[CSRF_TOKEN_NAME]) && $_POST[CSRF_TOKEN_NAME] !== '';
            $hasCsrfSession = isset($_SESSION[CSRF_TOKEN_NAME]) && $_SESSION[CSRF_TOKEN_NAME] !== '';
            $isGoogleLogin = !empty($_POST['google_id_token']);
            error_log('[login] CSRF validation failed: google=' . ($isGoogleLogin ? '1' : '0') .
                ' post_csrf=' . ($hasCsrfPost ? '1' : '0') .
                ' sess_csrf=' . ($hasCsrfSession ? '1' : '0') .
                ' sid=' . session_id());
        } catch (Throwable $t) {
            // ignore logging errors
        }
        $error_message = 'Security token validation failed. Please try again.';
    } else {
        $uid = null;
        $email = null;

        try {
        $auth = initialize_auth();
        // CASE A: Google Sign-In
        if (!empty($_POST['google_id_token'])) {
            // Add 300 seconds (5 minutes) leeway for clock skew. 
            // Signature: verifyIdToken($idToken, bool $checkIfRevoked = false, int $leewayInSeconds = 0)
            $verifiedIdToken = $auth->verifyIdToken($_POST['google_id_token'], false, 300);
            $uid = $verifiedIdToken->claims()->get('sub');
            $email = $verifiedIdToken->claims()->get('email');
        } 
        // CASE B: Password Login
        else {
            $identifier = trim($_POST['identifier'] ?? '');
            $password   = (string)($_POST['password'] ?? '');

            if ($identifier === '' || $password === '') {
                throw new Exception('Please enter your email/username and password.');
            }

            // Resolve username -> email (if needed)
            $email = $identifier;
            $resolvedUid = null;
            if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $userByUsername = firestore_query_one_by_field('users', 'username', $identifier);
                if (!$userByUsername) {
                    throw new Exception('Invalid username or password.');
                }
                $email = $userByUsername['email'] ?? null;
                $resolvedUid = $userByUsername['_id'] ?? null;
                if (!$email) {
                    throw new Exception('User record is missing an email.');
                }
            }

            // Sign in with email/password
            $signInResult = $auth->signInWithEmailAndPassword($email, $password);

            // Extract UID across SDK versions
            if (is_callable([$signInResult, 'firebaseUserId'])) {
                $uid = $signInResult->firebaseUserId();
            } elseif (method_exists($signInResult, 'data')) {
                $data = $signInResult->data();
                $uid = $data['localId'] ?? null;
            }

            if (!$uid && method_exists($signInResult, 'idToken') && $signInResult->idToken()) {
                try {
                    $verified = $auth->verifyIdToken($signInResult->idToken());
                    $uid = $verified->claims()->get('sub');
                } catch (Throwable $t) {
                    // ignore
                }
            }
            if (!$uid && $resolvedUid) {
                $uid = $resolvedUid;
            }
            if (!$uid) {
                throw new Exception('Unable to determine user ID.');
            }
        }

        // COMMON: Check User Role in Firestore
        if ($uid) {
            $userData = firestore_get_doc_by_id('users', $uid);
            if (!$userData) {
                throw new Exception('User data not found in the database.');
            }

            $role = $userData['role'] ?? null;
            if ($role !== 'admin' && $role !== 'staff') {
                throw new Exception('Access Denied: You do not have permission to log in.');
            }

            // Store in session and go to dashboard
            $_SESSION['user_id'] = $uid;
            $_SESSION['user_email'] = $userData['email'] ?? $email;
            $_SESSION['user_role'] = $role;
            $_SESSION['user_fullname'] = $userData['fullName'] ?? '';
            $_SESSION['assignedBarangay'] = $userData['assignedBarangay'] ?? '';
            $_SESSION['user_categories'] = is_array(($userData['categories'] ?? null)) ? $userData['categories'] : [];

            header('Location: dashboard.php');
            exit;
        }

    } catch (InvalidPassword | UserNotFound $e) {
        $error_message = 'Invalid credentials.';
    } catch (Throwable $e) {
        $rawMessage = $e->getMessage();
        if (stripos($rawMessage, 'invalid_grant') !== false || stripos($rawMessage, 'Invalid JWT Signature') !== false) {
            $error_message = 'Server authentication key is invalid or expired. Please replace Firebase service account JSON credentials.';
        } else {
            $error_message = $rawMessage;
        }
    }
    } // End CSRF check
    }
}

// Provide local svg_icon helper if not available
if (!function_exists('svg_icon')) {
    function svg_icon($name, $class = 'w-5 h-5') {
        $svgOpen = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="'.htmlspecialchars($class, ENT_QUOTES).'">';
        $svgClose = '</svg>';
        switch ($name) {
            case 'shield-check':
                return $svgOpen.
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5c0 4.28-2.99 8.12-7 9-4.01-.88-7-4.72-7-9V7l7-4z"/>'.
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2.2 2.2L15 10.5"/>'.
                $svgClose;
            case 'user-circle':
                return $svgOpen.
                    '<circle cx="12" cy="12" r="10"/>'.
                    '<circle cx="12" cy="9" r="3.2"/>'.
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M6.5 18.2c1.9-2.2 4.1-3.2 5.5-3.2s3.6 1 5.5 3.2"/>'.
                $svgClose;
            case 'lock-closed':
                return $svgOpen.
                    '<rect x="5" y="10" width="14" height="10" rx="2"/>' .
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M8 10V8a4 4 0 118 0v2"/>' .
                $svgClose;
            case 'eye':
                return $svgOpen.
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/>'.
                    '<circle cx="12" cy="12" r="3"/>' .
                $svgClose;
            case 'eye-slash':
                return $svgOpen.
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/>' .
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-6 10-6c2.4 0 4.3.8 5.8 1.8"/>' .
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M22 12s-3.5 6-10 6c-2.4 0-4.3-.8-5.8-1.8"/>' .
                $svgClose;
            case 'arrow-right':
                return $svgOpen.
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>' .
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M13 6l6 6-6 6"/>' .
                $svgClose;
            default:
                return $svgOpen.'<circle cx="12" cy="12" r="9" />'.$svgClose;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ManResponde • Admin & Staff Login</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="responde.png">
    <link rel="icon" type="image/png" sizes="16x16" href="responde.png">
    <link rel="apple-touch-icon" href="responde.png">
    <link rel="shortcut icon" href="responde.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --bg:#0b1220; --card:#ffffff; --glass:rgba(255,255,255,0.9); --ring:#3b82f6; --brand:#0ea5e9; --brand2:#22c55e; }
        body { font-family: 'Inter', sans-serif; }
        .bg-hero {
            position: relative;
            background:
                radial-gradient(1200px 600px at -10% -10%, rgba(59,130,246,.18), transparent 60%),
                radial-gradient(1000px 500px at 110% 110%, rgba(16,185,129,.18), transparent 60%),
                linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
        }
        .bg-hero:before {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cg fill='none' stroke='%23cbd5e1' stroke-opacity='.18'%3E%3Cpath d='M0 16h32M16 0v32'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 28px 28px; opacity: .3; pointer-events: none;
        }
        .glass { background: var(--glass); backdrop-filter: blur(12px); border: 1px solid rgba(148,163,184,.35); }
        .card { border-radius: 20px; box-shadow: 0 24px 60px rgba(2,6,23,.14), 0 8px 24px rgba(2,6,23,.08); overflow: hidden; }
        .brand-pill { display:inline-flex; align-items:center; gap:.5rem; padding:.5rem .75rem; border-radius:9999px; background:rgba(2,6,23,.06); border:1px solid rgba(148,163,184,.25); font-weight:700; font-size:.75rem; color:#0f172a; }
        .heading { letter-spacing:-0.02em; }

        /* Inputs */
        .input-group { position: relative; }
        .input { width:100%; border-radius: 14px; border:1px solid #e2e8f0; background:#fff; padding:.75rem .875rem; font-size: .95rem; color:#0f172a; transition: box-shadow .2s, border-color .2s, transform .06s; }
        .input:focus { border-color: var(--ring); box-shadow: 0 0 0 6px rgba(59,130,246,.14); outline: none; }
        .input-icon { position:absolute; left:.75rem; top:50%; transform:translateY(-50%); color:#94a3b8; display:inline-flex; }
        .input.with-icon { padding-left: 2.5rem; }
        .field-label { font-size:.85rem; font-weight:600; color:#475569; margin-bottom:.4rem; }
        .error { border-color:#ef4444 !important; box-shadow: 0 0 0 6px rgba(239,68,68,.12) !important; }
        .error-text { color:#b91c1c; font-size:.85rem; }

        /* Buttons */
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; font-weight:700; border-radius: 14px; padding:.75rem 1rem; transition: transform .06s ease, box-shadow .2s ease, filter .2s; }
        .btn:active { transform: translateY(1px); }
        .btn-gradient { color:#fff; background: linear-gradient(135deg, #0ea5e9 0%, #22c55e 100%); box-shadow: 0 10px 24px rgba(14,165,233,.28), 0 2px 8px rgba(16,185,129,.18); }
        .btn-gradient:hover { filter: brightness(1.03); box-shadow: 0 12px 28px rgba(14,165,233,.33), 0 3px 10px rgba(16,185,129,.22); }

        /* Animations */
        .fade-up { opacity:0; transform: translateY(8px); animation: fadeUp .45s ease forwards; }
        .fade-up.delay-1 { animation-delay: .06s; }
        .fade-up.delay-2 { animation-delay: .12s; }
        .fade-up.delay-3 { animation-delay: .18s; }
        @keyframes fadeUp { to { opacity:1; transform: translateY(0); } }

        /* Password toggle */
        .toggle { position:absolute; right:.5rem; top:50%; transform: translateY(-50%); color:#94a3b8; padding:.25rem .35rem; border-radius:10px; }
        .toggle:hover { color:#475569; background:#f1f5f9; }

        /* Left panel overlay */
        .hero-overlay { position:absolute; inset:0; background: radial-gradient(800px 400px at 10% 10%, rgba(14,165,233,.25), transparent 50%), radial-gradient(700px 360px at 90% 90%, rgba(34,197,94,.22), transparent 55%); mix-blend: multiply; }
    </style>
</head>
<body class="min-h-screen bg-hero">
    <div class="min-h-screen flex flex-col lg:flex-row">
        <!-- Left: Brand/hero -->
        <div class="hidden lg:flex lg:w-1/2 items-center justify-center relative overflow-hidden">
            <div class="absolute inset-0">
                <img src="SanCarlos.avif" alt="" class="w-full h-full object-cover opacity-25" onerror="this.style.display='none'">
                <div class="hero-overlay"></div>
            </div>
            <div class="relative z-10 max-w-xl px-12 py-16 text-center fade-up">
                <span class="brand-pill">
                    <?php echo svg_icon('shield-check','w-4 h-4 text-emerald-600'); ?>
                    ManResponde Admin
                </span>
                <h1 class="mt-4 text-4xl font-extrabold text-gray-900 heading">Secure, Monitor, and Protect</h1>
                <p class="mt-4 text-lg text-gray-600">The central hub for rapid response. Designed for clarity, speed, and confidence.</p>
            </div>
        </div>

        <!-- Right: Login -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-md fade-up delay-1">
                <div class="glass card">
                    <div class="px-8 pt-8 pb-4 text-center">
                        <div class="mx-auto h-16 w-16 rounded-2xl bg-white/90 shadow ring-1 ring-black/5 flex items-center justify-center">
                            <img src="responde.png" alt="ManResponde" class="h-10 w-10 object-contain" onerror="this.onerror=null; this.src='https://placehold.co/80x80?text=MR';">
                        </div>
                        <h2 class="mt-4 text-2xl sm:text-3xl font-extrabold text-gray-900 heading">Welcome back</h2>
                        <p class="text-gray-500 mt-1">Sign in to your Admin or Staff account</p>
                    </div>

                    <div class="px-8 pb-8">
                        <?php if (!empty($error_message)): ?>
                            <div class="mb-4 rounded-xl border border-red-200/70 bg-red-50/80 px-4 py-3 text-sm text-red-700 fade-up delay-2" role="alert" aria-live="assertive">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form action="login.php" method="POST" class="space-y-5">
                            <?php echo csrf_field(); ?>
                            <div>
                                <label for="identifier" class="field-label">Email or Username</label>
                                <div class="input-group">
                                    <span class="input-icon">
                                        <?php echo svg_icon('user-circle','w-5 h-5'); ?>
                                    </span>
                                    <input type="text" id="identifier" name="identifier" required autocomplete="username" class="input with-icon" placeholder="you@example.com or username">
                                </div>
                            </div>

                            <div>
                                <label for="password" class="field-label">Password</label>
                                <div class="input-group">
                                    <span class="input-icon">
                                        <?php echo svg_icon('lock-closed','w-5 h-5'); ?>
                                    </span>
                                    <input type="password" id="password" name="password" required autocomplete="current-password" class="input with-icon" placeholder="••••••••">
                                    <button type="button" id="togglePwd" class="toggle" aria-label="Show password">
                                        <?php echo svg_icon('eye','w-5 h-5'); ?>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="w-full btn btn-gradient">
                                <?php echo svg_icon('arrow-right','w-5 h-5'); ?>
                                Sign In
                            </button>

                            <div class="relative my-4">
                                <div class="absolute inset-0 flex items-center">
                                    <div class="w-full border-t border-gray-200"></div>
                                </div>
                                <div class="relative flex justify-center text-sm">
                                    <span class="px-2 bg-white text-gray-500">Or continue with</span>
                                </div>
                            </div>

                            <button type="button" onclick="signInWithGoogle()" class="w-full btn bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 transition-colors">
                                <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" class="w-5 h-5 mr-2" alt="Google">
                                Sign in with Google
                            </button>

                            <div class="mt-4 text-center">
                                <a href="index.php" class="text-sm font-medium text-blue-600 hover:text-blue-500 transition-colors">
                                    &larr; Back to Home
                                </a>
                            </div>

                            <p class="text-center text-xs text-gray-400">© <?php echo date('Y'); ?> ManResponde. All rights reserved.</p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Password toggle
    (function(){
        const toggle = document.getElementById('togglePwd');
        const pwd = document.getElementById('password');
        if (!toggle || !pwd) return;
        let shown = false;
        toggle.addEventListener('click', () => {
            shown = !shown; pwd.type = shown ? 'text' : 'password';
            toggle.innerHTML = shown
                ? `<?php echo str_replace('`','\`', svg_icon('eye-slash','w-5 h-5')); ?>`
                : `<?php echo str_replace('`','\`', svg_icon('eye','w-5 h-5')); ?>`;
        });
    })();

    // Optional: mark inputs error on failed submit (visual only)
    <?php if (!empty($error_message)): ?>
    document.getElementById('identifier')?.classList.add('error');
    document.getElementById('password')?.classList.add('error');
    <?php endif; ?>
    </script>
    <!-- Firebase SDKs -->
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-auth-compat.js"></script>

    <script>
      const firebaseConfig = {
        apiKey: "AIzaSyDiNgvmttAwhAjPthjJtcZ1Hr9PLWnhErQ",
        authDomain: "ibantayv2.firebaseapp.com",
        projectId: "ibantayv2"
      };
      firebase.initializeApp(firebaseConfig);
      
      function signInWithGoogle() {
        const provider = new firebase.auth.GoogleAuthProvider();
        firebase.auth().signInWithPopup(provider)
          .then((result) => {
            return result.user.getIdToken();
          })
          .then((idToken) => {
                        // Include CSRF token (required by login.php POST handler)
                        const csrfField = document.querySelector('input[name="<?php echo CSRF_TOKEN_NAME; ?>"]');
                        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = csrfField?.value || csrfMeta?.getAttribute('content') || '';

                        if (!csrfToken) {
                            // If token is missing, refresh to regenerate session token
                            window.location.reload();
                            return;
                        }

            // Submit token to server
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'login.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'google_id_token';
            input.value = idToken;

                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '<?php echo CSRF_TOKEN_NAME; ?>';
                        csrfInput.value = csrfToken;
            
            form.appendChild(input);
                        form.appendChild(csrfInput);
            document.body.appendChild(form);
            form.submit();
          })
          .catch((error) => {
            console.error(error);
            alert('Google Sign-In Error: ' + error.message);
          });
      }
    </script>
</body>
</html>