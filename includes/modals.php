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
    
    <div id="reportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 transition-all duration-500 opacity-0 pointer-events-none backdrop-blur-sm">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900/80 via-slate-800/70 to-slate-900/80" onclick="closeReportModal()"></div>
        <div id="modalContent" class="relative max-w-6xl w-full glass-card overflow-hidden transition-all duration-500 scale-90 opacity-0 animate-fade-in-up">
            <!-- Premium Header with Gradient -->
            <div class="relative px-8 py-6 bg-gradient-to-r from-emerald-600 via-cyan-600 to-teal-600 text-white overflow-hidden">
                <div class="absolute inset-0 bg-black/10"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div id="m_header" class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold">Emergency Report Details</h2>
                            <p class="text-white/80 text-sm">Detailed incident information</p>
                        </div>
                    </div>
                    <button class="w-10 h-10 rounded-xl bg-white/20 hover:bg-white/30 backdrop-blur-sm transition-all duration-300 flex items-center justify-center group" onclick="closeReportModal()">
                        <svg class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="absolute -bottom-1 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 via-cyan-400 to-teal-400 opacity-60"></div>
            </div>

            <!-- Content Area with Premium Cards -->
            <div class="p-8 max-h-[75vh] overflow-y-auto bg-gradient-to-br from-gray-50/50 to-white/80">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    
                    <!-- Reporter Information Card -->
                    <div class="xl:col-span-2 space-y-6">
                        <div class="glass-card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800">Reporter Information</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        Full Name
                                    </label>
                                    <div id="m_fullName" class="text-xl font-bold text-gray-900 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">—</div>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        Contact Number
                                    </label>
                                    <div id="m_contact" class="text-lg font-semibold text-gray-700">—</div>
                                </div>
                            </div>
                        </div>

                        <!-- Location & Details Card -->
                        <div class="glass-card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800">Incident Details</h3>
                            </div>
                            
                            <div class="space-y-6">
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        </svg>
                                        Location
                                    </label>
                                    <div id="m_location" class="text-base font-semibold text-gray-700 p-3 bg-gray-50/80 rounded-xl border border-gray-200">—</div>
                                    
                                    <!-- Embedded Map Container -->
                                    <div id="m_map_container" class="hidden mt-3 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
                                        <div id="m_map" class="w-full h-64 z-0"></div>
                                        <div class="bg-gray-50 px-3 py-2 text-xs text-gray-500 flex justify-between items-center border-t border-gray-200">
                                            <span><i class="fas fa-map-marker-alt mr-1"></i> Incident Location</span>
                                            <span id="m_map_status">Loading map...</span>
                                        </div>
                                        <div class="bg-white px-3 py-3 flex flex-wrap gap-2 border-t border-gray-200">
                                            <button id="m_btn_google" type="button" class="flex items-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition" disabled>
                                                <i class="fa-solid fa-route"></i> Google Maps
                                            </button>
                                            <button id="m_btn_waze" type="button" class="flex items-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition" disabled>
                                                <i class="fa-brands fa-waze"></i> Waze
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Incident Description
                                    </label>
                                    <div id="m_purpose" class="text-gray-700 p-4 bg-gray-50/80 rounded-xl border border-gray-200 leading-relaxed">—</div>
                                </div>
                            </div>
                        </div>

                        <!-- Metadata Card -->
                        <div class="glass-card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800">Report Metadata</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Submitted At
                                    </label>
                                    <div id="m_timestamp" class="text-base font-semibold text-gray-700 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">—</div>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Reporter ID
                                    </label>
                                    <div id="m_reporterId" class="text-sm font-mono text-gray-600 p-3 bg-gray-50/80 rounded-lg border border-gray-200 break-all">—</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Media & Actions Sidebar -->
                    <div class="space-y-6">
                        <!-- Media Card -->
                        <div class="glass-card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800">Attached Evidence</h3>
                            </div>
                            
                            <a id="m_image_link" href="#" target="_blank" class="group block rounded-2xl overflow-hidden border-2 border-dashed border-gray-200 bg-gradient-to-br from-gray-50 to-gray-100 aspect-[4/3] flex items-center justify-center hover:border-blue-300 hover:bg-gradient-to-br hover:from-blue-50 hover:to-indigo-50 transition-all duration-300">
                                <img id="m_image" src="" alt="Report evidence" class="w-full h-full object-cover hidden transition-all duration-500 group-hover:scale-105 rounded-xl">
                                <video id="m_video" controls class="w-full h-full object-cover hidden rounded-xl shadow-lg" preload="metadata">
                                    <source id="m_video_source" src="" type="">
                                    Your browser does not support the video tag.
                                </video>
                                <div id="m_image_none" class="text-center text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <p class="font-medium">No Evidence Attached</p>
                                    <p class="text-sm">No media was provided with this report</p>
                                </div>
                            </a>
                            <div class="text-center mt-3">
                                <span id="m_media_hint" class="text-xs text-gray-500 bg-gray-100 px-3 py-1 rounded-full">Click to view full size</span>
                            </div>
                        </div>

                        <!-- Status Card -->
                        <div class="glass-card p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800">Current Status</h3>
                            </div>
                            
                            <div id="m_status_container" class="text-center">
                                <span id="m_status" class="inline-flex items-center gap-3 px-6 py-3 rounded-2xl text-base font-bold shadow-lg">—</span>
                                <div id="m_approved_by_container" class="mt-4 hidden transition-all duration-300"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Footer -->
            <div class="px-8 py-6 bg-gradient-to-r from-gray-50 to-gray-100 border-t border-gray-200/50 flex items-center justify-between">
                <div class="flex items-center gap-3 text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium">Review and take action on this emergency report</span>
                </div>
                <div id="m_actions" class="flex items-center gap-3"></div>
            </div>
        </div>
    </div>

    <div id="proofModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 transition-opacity duration-300 opacity-0 pointer-events-none">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeProofModal()"></div>
        <div id="proofModalContent" class="relative max-w-lg w-full bg-white rounded-2xl shadow-xl overflow-hidden transition-transform duration-300 scale-95 opacity-0">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 id="p_header" class="text-lg font-bold text-slate-900">Proof of Residency</h3>
                <button class="text-slate-400 hover:text-slate-800 transition-colors" onclick="closeProofModal()"><?php echo svg_icon('x-mark', 'w-6 h-6'); ?></button>
            </div>
            <div class="p-6">
                <a id="p_image_link" href="#" target="_blank" class="block rounded-lg overflow-hidden border-2 border-slate-200 bg-slate-50 aspect-w-16 aspect-h-9 flex items-center justify-center group">
                    <img id="p_image" src="" alt="Proof of Residency" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105">
                </a>
                <div class="text-xs text-slate-400 mt-2 text-center">Click image to open in new tab.</div>
            </div>
        </div>
    </div>
    
    <div id="toastContainer" class="fixed top-5 right-5 z-[100] w-full max-w-xs space-y-3"></div>

