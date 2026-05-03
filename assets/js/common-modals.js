
// --- EXPORT FUNCTIONS ---
window.showExportModal = function() {
    const exportModal = document.getElementById('exportModal');
    if (!exportModal) return;
    const modalContent = exportModal.querySelector('.relative');
    
    exportModal.classList.remove('opacity-0', 'pointer-events-none');
    modalContent.classList.remove('scale-95', 'opacity-0');
};

window.closeExportModal = function() {
    const exportModal = document.getElementById('exportModal');
    if (!exportModal) return;
    const modalContent = exportModal.querySelector('.relative');
    
    exportModal.classList.add('opacity-0', 'pointer-events-none');
    modalContent.classList.add('scale-95', 'opacity-0');
};

window.toggleCustomDates = function() {
    const range = document.getElementById('exportRange').value;
    const customDiv = document.getElementById('customDateRange');
    if (range === 'custom') {
        customDiv.classList.remove('hidden');
    } else {
        customDiv.classList.add('hidden');
    }
};

window.exportReports = function(format) {
    const category = document.getElementById('exportCategory').value;
    const range = document.getElementById('exportRange').value;
    const startDate = document.getElementById('exportStartDate').value;
    const endDate = document.getElementById('exportEndDate').value;
    
    let url = `export_reports.php?format=${format}&category=${category}&range=${range}`;
    
    if (range === 'custom') {
        if (!startDate || !endDate) {
            if (typeof showToast === 'function') showToast('Please select both start and end dates', 'error');
            else alert('Please select both start and end dates');
            return;
        }
        url += `&startDate=${startDate}&endDate=${endDate}`;
    }
    
    // Show loading state
    if (typeof showToast === 'function') showToast('Preparing export...', 'info');
    
    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = url;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Close modal and show success message
    closeExportModal();
    setTimeout(() => {
        if (typeof showToast === 'function') showToast(`Export completed! ${format.toUpperCase()} file downloaded.`, 'success');
    }, 1000);
};

// --- TOAST NOTIFICATIONS FALLBACK ---
if (typeof window.showToast !== 'function') {
    window.showToast = function(message, type = 'info') {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'fixed top-5 right-5 z-[100] w-full max-w-xs space-y-3';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        const colors = {
            success: 'border-emerald-500/30 bg-emerald-50 text-emerald-800',
            error: 'border-red-500/30 bg-red-50 text-red-800',
            info: 'border-sky-500/30 bg-sky-50 text-sky-800'
        };

        toast.className = `relative w-full p-4 pr-12 rounded-lg shadow-lg border ${colors[type] || colors.info} transform transition-all duration-300 translate-x-full opacity-0 backdrop-blur-sm bg-opacity-95`;
        toast.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    ${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}
                </div>
                <p class="text-sm font-medium">${message}</p>
            </div>
        `;
        
        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
        });

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    };
}
