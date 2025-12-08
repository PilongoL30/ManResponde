document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('dashboardMap')) return;

    // Initialize Map centered on San Carlos City, Pangasinan
    const map = L.map('dashboardMap').setView([15.9281, 120.3489], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    let allReports = [];
    let activeMarkers = {}; // Store markers by report ID: { id: markerObj }
    const markerLayer = L.layerGroup().addTo(map);

    // Expose reports globally for modal access
    window.mapReports = [];

    const categoryColors = {
        'ambulance': '#10b981', // emerald-500
        'police': '#3b82f6',    // blue-500
        'tanod': '#f59e0b',     // amber-500
        'fire': '#ef4444',      // red-500
        'flood': '#06b6d4',     // cyan-500
        'other': '#64748b'      // slate-500
    };

    const categories = {
        'ambulance': 'ambulance_reports',
        'police': 'police_reports',
        'tanod': 'tanod_reports',
        'fire': 'fire_reports',
        'flood': 'flood_reports',
        'other': 'other_reports'
    };

    // Fetch Data
    async function loadMapData() {
        const loading = document.getElementById('mapLoading');
        // Only show loading on first load if empty
        if(loading && allReports.length === 0) loading.classList.remove('hidden');

        let fetchedReports = [];
        const promises = Object.entries(categories).map(async ([slug, collection]) => {
            try {
                const response = await fetch(`api/reports_feed.php?collection=${collection}`);
                const data = await response.json();
                if (data.items) {
                    return data.items.map(item => ({ ...item, category: slug }));
                }
                return [];
            } catch (error) {
                console.error(`Error fetching ${slug}:`, error);
                return [];
            }
        });

        try {
            const results = await Promise.all(promises);
            fetchedReports = results.flat();
            
            // Update global data
            allReports = fetchedReports;
            window.mapReports = allReports; 
            
            updateMarkers();
        } catch (err) {
            console.error('Error loading map data:', err);
        } finally {
            if(loading) loading.classList.add('hidden');
        }
    }

    function updateMarkers() {
        const categoryFilter = document.getElementById('mapCategoryFilter').value;
        const statusFilter = document.getElementById('mapStatusFilter').value;
        const searchTerm = document.getElementById('mapSearch').value.toLowerCase();

        // Get User Config for Staff Filtering
        const config = window.dashboardConfig || {};
        const isAdmin = config.isAdmin;
        const userCategories = (config.userCategories || []).map(c => c.toLowerCase());
        const userBarangay = (config.userBarangay || '').toLowerCase();

        // Filter reports to determine which ones should be visible
        const visibleReports = allReports.filter(report => {
            // --- STAFF PERMISSION FILTERS ---
            if (!isAdmin) {
                // 1. Category Permission
                if (userCategories.length > 0 && !userCategories.includes(report.category.toLowerCase())) {
                    return false;
                }

                // 2. Location Permission (Relaxed for now to ensure visibility)
                // Only apply strict filtering if we are sure about the location string format
                /*
                if ((report.category === 'tanod' || report.category === 'police') && userBarangay) {
                    const reportLocation = (report.location || '').toLowerCase();
                    if (!reportLocation.includes(userBarangay)) {
                        return false;
                    }
                }
                */
            }

            // --- UI FILTERS ---
            if (categoryFilter !== 'all' && report.category !== categoryFilter) return false;
            
            // Status Filter
            const rStatus = (report.status || 'pending').toLowerCase();
            const filterStatus = statusFilter.toLowerCase();

            if (filterStatus === 'all') {
                if (rStatus === 'declined') return false;
            } else {
                if (rStatus !== filterStatus) return false;
            }
            
            const searchable = `${report.fullName} ${report.location} ${report.purpose}`.toLowerCase();
            if (searchTerm && !searchable.includes(searchTerm)) return false;

            return true;
        });

        const visibleReportIds = new Set(visibleReports.map(r => r.id));

        // 1. Remove markers that are no longer visible
        Object.keys(activeMarkers).forEach(id => {
            if (!visibleReportIds.has(id)) {
                markerLayer.removeLayer(activeMarkers[id]);
                delete activeMarkers[id];
            }
        });

        // 2. Add or Update markers
        visibleReports.forEach(report => {
            if (activeMarkers[report.id]) {
                // Update existing marker popup content (e.g. status change)
                const color = categoryColors[report.category] || '#64748b';
                activeMarkers[report.id].setPopupContent(createPopupContent(report, color));
            } else {
                // Add new marker
                handleMarkerCreation(report);
            }
        });
    }

    function handleMarkerCreation(report) {
        let lat, lng;
        
        // 1. Try direct latitude/longitude properties
        if (report.latitude && report.longitude) {
            lat = parseFloat(report.latitude);
            lng = parseFloat(report.longitude);
        } 
        // 2. Try coordinates object
        else if (report.coordinates) {
            if (report.coordinates.latitude && report.coordinates.longitude) {
                lat = parseFloat(report.coordinates.latitude);
                lng = parseFloat(report.coordinates.longitude);
            } else if (report.coordinates._lat && report.coordinates._long) {
                lat = parseFloat(report.coordinates._lat);
                lng = parseFloat(report.coordinates._long);
            }
        }

        if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
            addMarker(lat, lng, report);
        } else {
            // 3. Fallback: Geocode or use city center
            geocodeAndAddMarker(report);
        }
    }

    function createPopupContent(report, color) {
        const locationNote = report.isApproximate ? '<span class="text-amber-600 text-[10px] font-bold ml-1">(Approximate Location)</span>' : '';
        return `
            <div class="p-1 min-w-[200px]">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full" style="background-color: ${color}"></span>
                    <span class="text-xs font-bold uppercase text-slate-500">${report.category}</span>
                    <span class="ml-auto text-xs font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">${report.status}</span>
                </div>
                <h4 class="font-bold text-slate-800 text-sm mb-1">${report.fullName || 'Anonymous'}</h4>
                <p class="text-xs text-slate-600 mb-2 line-clamp-2">${report.purpose || 'No description'}</p>
                <div class="text-xs text-slate-500 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    <span class="line-clamp-1">${report.location || 'Unknown'}</span>
                </div>
                ${locationNote}
                <button onclick="openReportModal('${report.id}')" class="mt-3 w-full py-1.5 bg-sky-50 text-sky-600 text-xs font-bold rounded hover:bg-sky-100 transition">View Details</button>
            </div>
        `;
    }

    const geocodingCache = {};

    function addMarker(lat, lng, report) {
        // Prevent duplicate markers for the same ID
        if (activeMarkers[report.id]) return;

        const color = categoryColors[report.category] || '#64748b';
        
        const customIcon = L.divIcon({
            className: 'custom-map-marker',
            html: `<div style="
                background-color: ${color}; 
                width: 14px; 
                height: 14px; 
                border-radius: 50%; 
                border: 2px solid white; 
                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                transform: translate(-50%, -50%);
            "></div>`,
            iconSize: [0, 0] // Handled by CSS/HTML
        });

        const marker = L.marker([lat, lng], { icon: customIcon })
            .bindPopup(createPopupContent(report, color))
            .addTo(markerLayer);
        
        activeMarkers[report.id] = marker;
    }

    // San Carlos City Center
    const CITY_CENTER = { lat: 15.9281, lng: 120.3489 };

    async function geocodeAndAddMarker(report) {
        const address = report.location;
        if (!address) {
            addFallbackMarker(report);
            return;
        }

        if (geocodingCache[address]) {
            const { lat, lng } = geocodingCache[address];
            addMarker(lat, lng, report);
            return;
        }

        try {
            // Add San Carlos City, Pangasinan context if not present
            let query = address;
            if (!query.toLowerCase().includes('san carlos') && !query.toLowerCase().includes('pangasinan')) {
                query += ', San Carlos City, Pangasinan, Philippines';
            }

            const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data && data.length > 0) {
                const lat = parseFloat(data[0].lat);
                const lng = parseFloat(data[0].lon);
                
                geocodingCache[address] = { lat, lng };
                addMarker(lat, lng, report);
            } else {
                // Geocoding returned no results
                console.warn('Geocoding no results for:', address);
                addFallbackMarker(report);
            }
        } catch (error) {
            console.warn('Geocoding failed for:', address, error);
            addFallbackMarker(report);
        }
    }

    function addFallbackMarker(report) {
        // Add a small random jitter to prevent markers from stacking perfectly
        // 0.02 degrees is roughly 2km, keeping it within city limits mostly
        const jitterLat = (Math.random() - 0.5) * 0.02;
        const jitterLng = (Math.random() - 0.5) * 0.02;
        
        const lat = CITY_CENTER.lat + jitterLat;
        const lng = CITY_CENTER.lng + jitterLng;
        
        report.isApproximate = true;
        addMarker(lat, lng, report);
    }

    // Event Listeners
    document.getElementById('mapCategoryFilter').addEventListener('change', updateMarkers);
    document.getElementById('mapStatusFilter').addEventListener('change', updateMarkers);
    document.getElementById('mapSearch').addEventListener('input', updateMarkers);

    // Initial Load
    loadMapData();
    
    // Real-time Polling (every 5 seconds)
    setInterval(loadMapData, 5000);
});

// Global functions for Modal
function openReportModal(reportId) {
    const report = window.mapReports.find(r => r.id === reportId);
    if (!report) {
        console.error('Report not found:', reportId);
        return;
    }

    const modal = document.getElementById('mapReportModal');
    const title = document.getElementById('mapModalTitle');
    const content = document.getElementById('mapModalContent');
    const viewBtn = document.getElementById('mapModalViewBtn');

    if (!modal || !title || !content) return;

    // Set Title
    title.textContent = `${report.category.charAt(0).toUpperCase() + report.category.slice(1)} Report Details`;

    // Set Content
    const imageUrl = report.imageUrl ? `<img src="${report.imageUrl}" alt="Report Image" class="w-full h-48 object-cover rounded-lg mb-4">` : '';
    
    content.innerHTML = `
        ${imageUrl}
        <div class="space-y-3">
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Reporter</label>
                <p class="text-slate-800 font-medium">${report.fullName || 'Anonymous'}</p>
                <p class="text-slate-600 text-sm">${report.contact || 'No contact info'}</p>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Location</label>
                <p class="text-slate-800 text-sm">${report.location || 'Unknown location'}</p>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Description</label>
                <p class="text-slate-700 text-sm bg-slate-50 p-3 rounded-lg border border-slate-100">${report.purpose || 'No description provided.'}</p>
            </div>
            <div class="flex justify-between items-center pt-2">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Status</label>
                    <span class="inline-block px-2 py-1 rounded text-xs font-bold bg-slate-100 text-slate-700 mt-1">${report.status || 'Pending'}</span>
                </div>
                <div class="text-right">
                    <label class="text-xs font-bold text-slate-500 uppercase">Time</label>
                    <p class="text-slate-600 text-xs mt-1">${report.timestamp || 'Unknown'}</p>
                </div>
            </div>
        </div>
    `;

    // Configure View Button (if we had a dedicated page, we'd link it here)
    // For now, we can just make it close the modal or link to dashboard with query param
    if (viewBtn) {
        viewBtn.href = `dashboard?view=dashboard&highlight=${report.id}`;
        viewBtn.onclick = function() {
            // Optional: Switch to dashboard view logic if needed
            // window.location.href = ...
        };
    }

    // Show Modal
    modal.classList.remove('hidden');
}

function closeMapModal() {
    document.getElementById('mapReportModal').classList.add('hidden');
}
