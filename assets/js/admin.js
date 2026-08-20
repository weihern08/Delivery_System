document.addEventListener('DOMContentLoaded', () => {
    const mapElement = document.getElementById(window.ADMIN_MAP_CONFIG.mapElement);
    if (!mapElement || typeof L === 'undefined') return;

    const map = L.map(mapElement, {
        zoomControl: true,
        dragging: true,
        tap: true
    }).setView([5.3667, 100.3167], 13);
    
    // 保存初始视图，防止自动调整
    let initialZoom = 13;
    let initialCenter = [5.3667, 100.3167];
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let markers = [];
    let liveEnabled = true;
    let refreshTimer = null;

    const riderLiveIcon = L.divIcon({
        className: 'admin-rider-live-icon',
        html: '<div style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:rgba(20,184,166,0.18);box-shadow:0 8px 18px rgba(20,184,166,0.28);font-size:22px;line-height:1;">🏍️</div>',
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -12]
    });

    function setRefreshState() {
        const liveEl = document.querySelector('[data-live-toggle="status"]');
        if (liveEl) {
            liveEl.textContent = liveEnabled ? 'Live' : 'Paused';
            liveEl.classList.toggle('muted-toggle', !liveEnabled);
            liveEl.setAttribute('aria-pressed', String(liveEnabled));
        }
    }

    function setUpdatedText() {
        const refreshEl = document.querySelector('[data-live-toggle="refresh"]');
        if (refreshEl) {
            refreshEl.textContent = 'Updated now';
            refreshEl.classList.remove('muted-toggle');
            refreshEl.setAttribute('aria-pressed', 'true');
        }
    }

    function startLiveRefresh() {
        if (refreshTimer) return;
        refreshTimer = setInterval(() => {
            if (liveEnabled) {
                refreshLocations();
            }
        }, 20000);
    }

    function stopLiveRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    async function refreshLocations() {
        if (!liveEnabled) return;

        // 保存当前地图状态
        const currentZoom = map.getZoom();
        const currentCenter = map.getCenter();

        const response = await fetch(`${window.ADMIN_MAP_CONFIG.apiBase}/ajax.php?action=fetch_rider_locations`);
        const data = await response.json();
        if (!data.success) return;

        markers.forEach((marker) => map.removeLayer(marker));
        markers = [];

        // 使用Map按rider id去重，每个rider只保留一个坐标点
        const riderMap = new Map();
        data.locations.forEach((item) => {
            if (!item.latitude || !item.longitude) return;
            if (!item.id) return;
            // 如果这个rider还没在map中，或者这条记录更新时间更晚，就更新
            if (!riderMap.has(item.id)) {
                riderMap.set(item.id, item);
            }
        });

        // 为每个rider创建一个marker
        riderMap.forEach((item) => {
            const lat = parseFloat(item.latitude);
            const lng = parseFloat(item.longitude);
            const marker = L.marker([lat, lng], { icon: riderLiveIcon }).addTo(map);
            const destination = item.destination_address || 'No active parcel';
            marker.bindPopup(`<strong>${item.name}</strong><br>${item.status}<br>Destination: ${destination}<br>${item.updated_at}`);
            markers.push(marker);
        });

        // 恢复地图到之前的位置和缩放级别
        map.setView(currentCenter, currentZoom, { animate: false });
        
        setUpdatedText();
    }

    document.querySelectorAll('[data-live-toggle]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = button.dataset.liveToggle;
            if (target === 'status') {
                liveEnabled = !liveEnabled;
                setRefreshState();
                if (liveEnabled) {
                    startLiveRefresh();
                    await refreshLocations();
                } else {
                    stopLiveRefresh();
                }
            }

            if (target === 'refresh') {
                await refreshLocations();
                setUpdatedText();
            }
        });
    });

    document.querySelectorAll('[data-map-toggle]').forEach((button) => {
        button.addEventListener('click', async () => {
            const mode = button.dataset.mapToggle;
            if (mode === 'live') {
                liveEnabled = !liveEnabled;
                setRefreshState();
                button.classList.toggle('active', liveEnabled);
                button.textContent = liveEnabled ? 'Live' : 'Paused';
                if (liveEnabled) {
                    startLiveRefresh();
                    await refreshLocations();
                } else {
                    stopLiveRefresh();
                }
            }

            if (mode === 'online') {
                button.classList.toggle('active');
                button.textContent = button.classList.contains('active') ? 'Showing online' : 'Online riders';
                if (button.classList.contains('active')) {
                    await refreshLocations();
                }
            }
        });
    });

    const liveMapButton = document.querySelector('[data-map-toggle="live"]');
    if (liveMapButton) {
        liveMapButton.classList.add('active');
        liveMapButton.textContent = 'Live';
    }

    const onlineMapButton = document.querySelector('[data-map-toggle="online"]');
    if (onlineMapButton) {
        onlineMapButton.classList.add('active');
        onlineMapButton.textContent = 'Showing online';
    }

    setRefreshState();
    startLiveRefresh();
    refreshLocations();
});
