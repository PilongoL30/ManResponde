<section class="mb-6 animate-fade-in-up" style="--anim-delay: 100ms;">
    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-sky-500/5 border border-slate-200/80 p-6 md:p-8">
        <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-10 h-10 bg-sky-100 rounded-lg"><?php echo svg_icon('user-plus', 'w-6 h-6 text-sky-600'); ?></span>
            <h2 class="text-xl font-bold text-slate-800">Create New Account</h2>
        </div>
        <p class="text-slate-500 mb-6">Create accounts with flexible role assignment for Staff and/or Responder.</p>

        <div class="w-full max-w-2xl md:max-w-3xl lg:max-w-4xl xl:max-w-6xl 2xl:max-w-[1600px] mx-auto">
            <form id="createAccountForm" class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-sky-500/5 border border-slate-200/80 p-6 md:p-8 space-y-8">
                <input type="hidden" name="api_action" value="create_account">
                
                <!-- Account Type Selection -->
                <div class="bg-gradient-to-br from-sky-50 to-blue-50 rounded-xl p-6 border-2 border-sky-200">
                    <h3 class="text-lg font-bold text-slate-800 mb-3 flex items-center gap-2">
                        <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Select Account Type(s) *
                    </h3>
                    <p class="text-sm text-slate-600 mb-4">Choose one or both roles for this account</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="relative cursor-pointer">
                            <input type="checkbox" name="accountTypes[]" value="staff" class="peer sr-only account-type-checkbox">
                            <div class="flex flex-col items-center gap-3 p-6 bg-white rounded-xl border-2 border-slate-200 peer-checked:border-sky-500 peer-checked:bg-sky-50 peer-checked:shadow-lg transition-all hover:border-sky-300 peer-checked:[&_.check-circle]:bg-sky-600 peer-checked:[&_.check-circle]:border-sky-600 peer-checked:[&_.check-circle_svg]:opacity-100">
                                <svg class="w-12 h-12 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <div class="text-center">
                                    <p class="font-bold text-slate-800">Staff</p>
                                    <p class="text-xs text-slate-500 mt-1">Manage reports</p>
                                </div>
                                <div class="check-circle absolute top-2 right-2 w-6 h-6 rounded-full border-2 border-slate-300 bg-white flex items-center justify-center transition-all">
                                    <svg class="w-4 h-4 text-white opacity-0 transition-opacity duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                            </div>
                        </label>
                        
                        <label class="relative cursor-pointer">
                            <input type="checkbox" name="accountTypes[]" value="responder" class="peer sr-only account-type-checkbox">
                            <div class="flex flex-col items-center gap-3 p-6 bg-white rounded-xl border-2 border-slate-200 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:shadow-lg transition-all hover:border-emerald-300 peer-checked:[&_.check-circle]:bg-emerald-600 peer-checked:[&_.check-circle]:border-emerald-600 peer-checked:[&_.check-circle_svg]:opacity-100">
                                <svg class="w-12 h-12 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.286z"/>
                                </svg>
                                <div class="text-center">
                                    <p class="font-bold text-slate-800">Responder</p>
                                    <p class="text-xs text-slate-500 mt-1">Emergency response</p>
                                </div>
                                <div class="check-circle absolute top-2 right-2 w-6 h-6 rounded-full border-2 border-slate-300 bg-white flex items-center justify-center transition-all">
                                    <svg class="w-4 h-4 text-white opacity-0 transition-opacity duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                            </div>
                        </label>
                    </div>
                    <div id="roleSelectionError" class="hidden mt-3 text-sm text-red-600 font-medium">
                        Please select at least one account type
                    </div>
                    <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-xs text-blue-800 flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span><strong>Flexible Multi-Role:</strong> You can select both roles. Staff handles report management, Responder provides emergency response. Select one or both as needed.</span>
                        </p>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="bg-slate-50 rounded-xl p-6 border border-slate-200">
                    <h3 class="text-lg font-bold text-slate-800 mb-4">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="acctLastName" class="block text-sm font-medium text-slate-700 mb-1.5">Last Name *</label>
                            <input id="acctLastName" name="lastName" type="text" required class="w-full rounded-md border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all" placeholder="Dela Cruz">
                        </div>
                        <div>
                            <label for="acctFirstName" class="block text-sm font-medium text-slate-700 mb-1.5">First Name *</label>
                            <input id="acctFirstName" name="firstName" type="text" required class="w-full rounded-md border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all" placeholder="Juan">
                        </div>
                        <div>
                            <label for="acctMiddleName" class="block text-sm font-medium text-slate-700 mb-1.5">Middle Name</label>
                            <input id="acctMiddleName" name="middleName" type="text" class="w-full rounded-md border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all" placeholder="Santos (optional)">
                        </div>
                    </div>
                </div>
                
                <!-- Login Credentials -->
                <div class="bg-slate-50 rounded-xl p-6 border border-slate-200">
                    <h3 class="text-lg font-bold text-slate-800 mb-4">Login Credentials</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="acctEmail" class="block text-sm font-medium text-slate-700 mb-1.5">Email Address *</label>
                            <input id="acctEmail" name="email" type="email" required class="w-full rounded-md border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all" placeholder="juan.delacruz@email.com">
                        </div>
                        <div>
                            <label for="acctUsername" class="block text-sm font-medium text-slate-700 mb-1.5">Username *</label>
                            <input id="acctUsername" name="username" type="text" required class="w-full rounded-md border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all" placeholder="Unique username">
                        </div>
                        <div class="md:col-span-2">
                            <label for="acctPassword" class="block text-sm font-medium text-slate-700 mb-1.5">Password *</label>
                            <input id="acctPassword" name="password" type="password" required class="w-full rounded-md border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all" placeholder="Strong password">
                        </div>
                    </div>
                </div>
                
                <!-- Category Assignment -->
                <div id="categorySection" class="bg-slate-50 rounded-xl p-6 border border-slate-200">
                    <h3 class="text-lg font-bold text-slate-800 mb-4">Assign Categories</h3>
                    <p class="text-sm text-slate-600 mb-4">Select which emergency types this account can manage.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                        <?php foreach ($categories as $slug => $meta): ?>
                        <?php if ($slug !== 'other'): ?>
                        <label class="custom-checkbox flex items-center gap-2 bg-white border border-slate-200 rounded-lg px-3 py-2 shadow-sm hover:bg-sky-50 transition cursor-pointer">
                            <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($slug); ?>" class="accent-sky-600">
                            <span class="box">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="w-5 h-5 text-sky-600">
                                    <path d="M4.5 12.75l6 6 9-13.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span class="text font-medium text-slate-700"><?php echo htmlspecialchars($meta['label']); ?></span>
                        </label>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Barangay Selection (Hidden by default) -->
                    <div id="barangaySelection" class="hidden mt-4 p-4 bg-sky-50 border border-sky-200 rounded-lg animate-fade-in-up">
                        <label for="assignedBarangay" class="block text-sm font-medium text-slate-700 mb-1.5">Assigned Barangay / Outpost (San Carlos City) *</label>
                        <select id="assignedBarangay" name="assignedBarangay" class="w-full rounded-md border-slate-300 bg-white px-4 py-2.5 text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all">
                            <option value="">Select Barangay</option>
                            <?php
                            $barangays = [
                                'Abanon', 'Agdao', 'Anando', 'Ano', 'Antipangol', 'Aponit', 'Bacnar', 'Balaya', 'Balayong', 'Baldog', 
                                'Balite Sur', 'Balococ', 'Bani', 'Bega', 'Bocboc', 'Bogaoan', 'Bolingit', 'Bolosan', 'Bonifacio', 'Buenglat', 
                                'Bugallon-Posadas', 'Burgos-Padlan', 'Cacaritan', 'Caingal', 'Calobaoan', 'Calomboyan', 'Caoayan-Kiling', 'Capataan', 'Cobol', 'Coliling', 
                                'Cruz', 'Doyong', 'Gamata', 'Guelew', 'Ilang', 'Inerangan', 'Isla', 'Libas', 'Lilimasan', 'Longos', 
                                'Lucban', 'M. Soriano', 'Mabalbalino', 'Mabini', 'Magtaking', 'Malacañang', 'Maliwara', 'Mamarlao', 'Manzon', 'Matagdem', 
                                'Mestizo Norte', 'Naguilayan', 'Nilintap', 'Padilla-Gomez', 'Pagal', 'Paitan-Panoypoy', 'Palaming', 'Palaris', 'Palospos', 'Pangalangan', 
                                'Pangoloan', 'Pangpang', 'Parayao', 'Payapa', 'Payar', 'Perez Boulevard', 'PNR Site', 'Polo', 'Quezon Boulevard', 'Quintong', 
                                'Rizal', 'Roxas Boulevard', 'Salinap', 'San Juan', 'San Pedro-Taloy', 'Sapinit', 'Supo', 'Talang', 'Tamayo', 'Tandang Sora', 
                                'Tandoc', 'Tarece', 'Tarectec', 'Tayambani', 'Tebag', 'Turac'
                            ];
                            sort($barangays);
                            foreach ($barangays as $brgy) {
                                echo '<option value="' . htmlspecialchars($brgy) . '">' . htmlspecialchars($brgy) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end mt-6">
                    <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white px-8 py-3 text-lg font-bold rounded-xl shadow-lg flex items-center gap-3 transition">
                        <span class="btn-text">Create Account</span>
                        <span class="btn-spinner hidden"><?php echo svg_icon('spinner', 'w-5 h-5 animate-spin'); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
