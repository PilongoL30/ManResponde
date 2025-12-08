<nav class="mobile-nav">
    <a href="dashboard.php" class="mobile-nav-item <?php echo (!isset($_GET['view']) || $_GET['view'] === 'dashboard') ? 'active' : ''; ?>">
        <?php echo svg_icon('home', 'w-6 h-6'); ?>
        <span>Home</span>
    </a>
    <a href="dashboard.php?view=live-support" class="mobile-nav-item <?php echo (isset($_GET['view']) && $_GET['view'] === 'live-support') ? 'active' : ''; ?>">
        <?php echo svg_icon('chat-bubble-left-right', 'w-6 h-6'); ?>
        <span>Chat</span>
    </a>
    <a href="dashboard.php?view=map" class="mobile-nav-item <?php echo (isset($_GET['view']) && $_GET['view'] === 'map') ? 'active' : ''; ?>">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
        </svg>
        <span>Map</span>
    </a>
    <?php if ($isAdmin): ?>
    <a href="dashboard.php?view=create-account" class="mobile-nav-item <?php echo (isset($_GET['view']) && $_GET['view'] === 'create-account') ? 'active' : ''; ?>">
        <?php echo svg_icon('user-plus', 'w-6 h-6'); ?>
        <span>Add User</span>
    </a>
    <?php endif; ?>
    <button onclick="toggleDarkMode()" class="mobile-nav-item">
        <?php echo svg_icon('moon', 'w-6 h-6'); ?>
        <span>Theme</span>
    </button>
    <a href="logout.php" class="mobile-nav-item">
        <?php echo svg_icon('arrow-right-on-rectangle', 'w-6 h-6'); ?>
        <span>Logout</span>
    </a>
</nav>