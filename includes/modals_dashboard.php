    <div id="toastContainer" class="fixed top-5 right-5 z-[100] w-full max-w-xs space-y-3"></div>
    <div id="exportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 transition-opacity duration-300 opacity-0 pointer-events-none">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeExportModal()"></div>
        <div class="relative max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden transition-transform duration-300 scale-95 opacity-0">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">Export Reports</h3>
                <button class="text-slate-400 hover:text-slate-800 transition-colors" onclick="closeExportModal()"><?php echo svg_icon('x-mark', 'w-6 h-6'); ?></button>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Category</label>
                        <select id="exportCategory" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $slug => $meta): ?>
                                <option value="<?php echo $slug; ?>"><?php echo htmlspecialchars($meta['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Time Range</label>
                        <select id="exportRange" onchange="toggleCustomDates()" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="year">This Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div id="customDateRange" class="hidden grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Start Date</label>
                            <input type="date" id="exportStartDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">End Date</label>
                            <input type="date" id="exportEndDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Format</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" onclick="exportReports('excel')" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition-all shadow-md hover:shadow-lg font-semibold w-full">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Excel (CSV)
                            </button>
                            <button type="button" onclick="exportReports('pdf')" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-rose-600 text-white rounded-xl hover:bg-rose-700 transition-all shadow-md hover:shadow-lg font-semibold w-full">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                PDF (HTML)
                            </button>
                                    </div>
                                </div>
                    <div class="text-xs text-slate-500">
                        <p>• Excel: Downloads as CSV file that can be opened in Excel</p>
                        <p>• PDF: Downloads as HTML file that can be printed or converted to PDF</p>
                                </div>
                                </div>
                                </div>
                            </div>
    </div>
    <!-- Confirmation Modal for Approve Action -->
    <div id="approveModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 transition-opacity duration-300 opacity-0 pointer-events-none">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeApproveModal()"></div>
        <div class="relative max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden transition-transform duration-300 scale-95 opacity-0">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-green-800">Approve Report</h3>
                <button class="text-slate-400 hover:text-slate-800 transition-colors" onclick="closeApproveModal()"><?php echo svg_icon('x-mark', 'w-6 h-6'); ?></button>
            </div>
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <?php echo svg_icon('check-circle', 'w-8 h-8 text-green-600'); ?>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-semibold text-gray-900">Confirm Approval</h4>
                        <p class="text-sm text-gray-600">You are about to approve this emergency report</p>
                    </div>
                </div>

                <div class="bg-green-50 p-4 rounded-lg mb-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <?php echo svg_icon('check-circle', 'w-5 h-5 text-green-400'); ?>
                        </div>
                        <div class="ml-3">
                            <h5 class="text-sm font-medium text-green-800">What happens when you approve:</h5>
                            <ul class="mt-2 text-sm text-green-700 list-disc list-inside">
                                <li>Emergency responders will be notified</li>
                                <li>Report status changes to "Approved"</li>
                                <li>Response team will be dispatched</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div id="approve-report-details" class="text-sm text-gray-600 mb-4">
                    <!-- Report details will be populated here -->
                </div>

                <div class="flex gap-3">
                    <button class="btn btn-secondary flex-1" onclick="closeApproveModal()">Cancel</button>
                    <button class="btn btn-primary flex-1" onclick="confirmApprove()" style="background-color: #10b981;">
                        <?php echo svg_icon('check-circle', 'w-4 h-4'); ?>
                        Yes, Approve Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal for Decline Action -->
    <div id="declineModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 transition-opacity duration-300 opacity-0 pointer-events-none">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeDeclineModal()"></div>
        <div class="relative max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden transition-transform duration-300 scale-95 opacity-0">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-red-800">Decline Report</h3>
                <button class="text-slate-400 hover:text-slate-800 transition-colors" onclick="closeDeclineModal()"><?php echo svg_icon('x-mark', 'w-6 h-6'); ?></button>
            </div>
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <?php echo svg_icon('x-circle', 'w-8 h-8 text-red-600'); ?>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-semibold text-gray-900">Confirm Decline</h4>
                        <p class="text-sm text-gray-600">You are about to decline this emergency report</p>
                    </div>
                </div>

                <div class="bg-red-50 p-4 rounded-lg mb-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <?php echo svg_icon('x-circle', 'w-5 h-5 text-red-400'); ?>
                        </div>
                        <div class="ml-3">
                            <h5 class="text-sm font-medium text-red-800">What happens when you decline:</h5>
                            <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                <li>Reporter will be notified of the decline</li>
                                <li>They will receive instructions to resubmit</li>
                                <li>No emergency response will be dispatched</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div id="decline-report-details" class="text-sm text-gray-600 mb-4">
                    <!-- Report details will be populated here -->
                </div>

                <div class="mb-4">
                    <label for="declineReason" class="block text-sm font-medium text-gray-700 mb-2">
                        Reason for Decline <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        id="declineReason" 
                        name="declineReason" 
                        rows="3" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none text-sm"
                        placeholder="Please provide a clear reason for declining this report (e.g., insufficient information, duplicate report, not an emergency, etc.)"
                        required
                        maxlength="500"></textarea>
                    <div class="flex justify-between items-center mt-1">
                        <p class="text-xs text-gray-500">This reason will be sent to the reporter in the notification.</p>
                        <span class="text-xs text-gray-400" id="reasonCharCount">0/500</span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button class="btn btn-secondary flex-1" onclick="closeDeclineModal()">Cancel</button>
                    <button class="btn btn-danger flex-1" onclick="confirmDecline()" style="background-color: #dc2626; color: white;">
                        <?php echo svg_icon('x-circle', 'w-4 h-4'); ?>
                        Yes, Decline Report
                    </button>
                </div>
            </div>
        </div>
    </div>
