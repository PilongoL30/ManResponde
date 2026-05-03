<?php
$dashboardPath = 'd:/Xampp/htdocs/ManResponde/dashboard.php';
$content = file_get_contents($dashboardPath);

// We want to find the large script blocks and extract them.
// Let's just find the script block starting at line 4608 and line 9296.
// Instead of complex regex, let's use DOMDocument or basic string search.

$lines = file($dashboardPath);
$inScript1 = false;
$inScript2 = false;

$script1Lines = [];
$script2Lines = [];

$newLines = [];

foreach ($lines as $i => $line) {
    $lineNum = $i + 1;
    
    if ($lineNum == 4608 && trim($line) === '<script>') {
        $inScript1 = true;
        $newLines[] = "    <script src=\"assets/js/dashboard-main.js?v=<?php echo filemtime(__DIR__ . '/assets/js/dashboard-main.js'); ?>\"></script>\n";
        continue;
    }
    
    if ($lineNum == 9296 && strpos(trim($line), '<script type="module">') === 0) {
        $inScript2 = true;
        $newLines[] = "    <script type=\"module\" src=\"assets/js/dashboard-map.js?v=<?php echo filemtime(__DIR__ . '/assets/js/dashboard-map.js'); ?>\"></script>\n";
        continue;
    }
    
    if ($inScript1) {
        if (trim($line) === '</script>') {
            $inScript1 = false;
            continue;
        }
        $script1Lines[] = $line;
    } elseif ($inScript2) {
        if (trim($line) === '</script>') {
            $inScript2 = false;
            continue;
        }
        $script2Lines[] = $line;
    } else {
        $newLines[] = $line;
    }
}

file_put_contents('d:/Xampp/htdocs/ManResponde/assets/js/dashboard-main.js', implode("", $script1Lines));
file_put_contents('d:/Xampp/htdocs/ManResponde/assets/js/dashboard-map.js', implode("", $script2Lines));
file_put_contents($dashboardPath, implode("", $newLines));

echo "Extracted script 1: " . count($script1Lines) . " lines.\n";
echo "Extracted script 2: " . count($script2Lines) . " lines.\n";
echo "Updated dashboard.php.\n";
