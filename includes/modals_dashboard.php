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
                        <label class="block text-sm font-medium text-slate-700 mb-2">Format</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" onclick="exportReports('excel')" class="btn btn-primary w-full justify-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                                </svg>
                                Excel (CSV)
                                        </button>
                            <button type="button" onclick="exportReports('pdf')" class="btn btn-view w-full justify-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
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
