<?php
/**
 * Staff Dashboard View
 * Shows reports for staff members based on their assigned categories
 */
?>

<div class="mb-4 rounded-xl bg-white/70 backdrop-blur-sm border border-slate-200/80 shadow-sm p-4 animate-fade-in-up" style="--anim-delay: 100ms;">
    <p class="text-sm text-slate-600">
        Your assigned categories:
        <?php
            if (!empty($userCategories)) {
                foreach ($userCategories as $cat) {
                    $catSlug = strtolower($cat);
                    $catLabel = $categories[$catSlug]['label'] ?? ucfirst($cat);
                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">' . htmlspecialchars($catLabel) . '</span>';
                }
            } else {
                echo '<span class="text-slate-400 italic">None</span>';
            }
        ?>
    </p>
</div>

<section class="space-y-6" id="staffReportCards">
    <div class="text-center py-12 text-slate-500">
        <div class="inline-flex items-center gap-3">
            <?php echo svg_icon('spinner', 'w-5 h-5 animate-spin'); ?>
            <div>
                <div class="text-lg font-medium">Loading your reports...</div>
                <div class="text-sm text-slate-400">Please wait a moment.</div>
            </div>
        </div>
    </div>
</section>
