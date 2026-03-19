<section class="space-y-6">
    <div class="glass-card p-4 md:p-6">
        <h3 class="text-xl font-bold text-slate-800 mb-1">Create Account</h3>
        <p class="text-sm text-slate-500 mb-5">Create Staff or Responder accounts and assign categories.</p>

        <form id="createAccountForm" class="space-y-4">
            <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
            <input type="hidden" name="api_action" value="create_account">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input name="lastName" type="text" placeholder="Last name" required class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <input name="firstName" type="text" placeholder="First name" required class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <input name="middleName" type="text" placeholder="Middle name" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input name="email" type="email" placeholder="Email" required class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <input name="username" type="text" placeholder="Username" required class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <input name="password" type="password" placeholder="Password" required class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-700">Account Type</label>
                <div class="mt-2 flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="accountTypes[]" value="staff"> Staff</label>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="accountTypes[]" value="responder"> Responder</label>
                </div>
                <p id="roleSelectionError" class="hidden mt-2 text-sm text-red-600">Select at least one account type.</p>
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-700">Categories</label>
                <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach (($categories ?? []) as $slug => $meta): ?>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars((string)$slug); ?>">
                            <?php echo htmlspecialchars((string)($meta['label'] ?? $slug)); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="barangaySelection" class="hidden">
                <label for="assignedBarangay" class="text-sm font-semibold text-slate-700">Assigned Barangay / Outpost</label>
                <select id="assignedBarangay" name="assignedBarangay" class="mt-2 w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select assigned area</option>
                    <option value="Poblacion">Poblacion</option>
                    <option value="Pangpang">Pangpang</option>
                    <option value="Naguilayan">Naguilayan</option>
                    <option value="Bocboc East">Bocboc East</option>
                    <option value="Bocboc West">Bocboc West</option>
                    <option value="Barangay Outpost">Barangay Outpost</option>
                    <option value="Police Station">Police Station</option>
                </select>
            </div>

            <div class="pt-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 text-white font-semibold hover:bg-sky-700">Create Account</button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="glass-card p-4"><p class="text-xs text-slate-500">Total Staff</p><p id="totalStaffCount" class="text-2xl font-bold text-slate-800">0</p></div>
        <div class="glass-card p-4"><p class="text-xs text-slate-500">Active Staff</p><p id="activeStaffCount" class="text-2xl font-bold text-emerald-600">0</p></div>
        <div class="glass-card p-4"><p class="text-xs text-slate-500">Reports Assigned</p><p id="reportsAssignedCount" class="text-2xl font-bold text-cyan-600">0</p></div>
    </div>

    <div class="glass-card p-4 md:p-6">
        <h4 class="text-lg font-semibold text-slate-800 mb-3">Staff List</h4>
        <div id="staffLoading" class="text-sm text-slate-500">Loading staff data...</div>
        <div id="staffEmpty" class="hidden text-sm text-slate-500">No staff records found.</div>
        <div id="staffList" class="space-y-3"></div>
    </div>
</section>
