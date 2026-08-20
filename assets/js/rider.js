window.addEventListener('load', () => {
    console.log('Rider script loaded');
    console.log('RIDER_CONFIG:', window.RIDER_CONFIG);
    console.log('Assigned Parcels:', window.RIDER_CONFIG?.assignedParcels);
    console.log('Available Parcels:', window.RIDER_CONFIG?.availableParcels);
    
    const statusButton = document.getElementById('status-button');
    if (!statusButton) {
        console.warn('Rider status button not found.');
        return;
    }

    const riderStatus = window.RIDER_CONFIG?.status || 'offline';
    let online = riderStatus === 'online';
    let watcherId = null;
    let map; let marker; let routeLayer; let destinationMarker;
    let activeDestination = null;
    let hasUserCenteredMap = false;
    let lastRenderedRouteKey = '';
    let lastRenderedParcelId = null;
    let routeRenderInFlight = false;
    let activeRouteParcelId = null;

    function getAssignedParcelById(parcelId) {
        const parcels = Array.isArray(window.RIDER_CONFIG?.assignedParcels) ? window.RIDER_CONFIG.assignedParcels : [];
        return parcels.find((parcel) => Number(parcel.id) === Number(parcelId)) || null;
    }

    function centerMapOnce(location, zoom = 15) {
        if (!map || !location || hasUserCenteredMap) {
            return;
        }
        map.setView(location, zoom, { animate: false });
        hasUserCenteredMap = true;
    }

    function getActiveParcel() {
        const parcels = Array.isArray(window.RIDER_CONFIG?.assignedParcels) ? window.RIDER_CONFIG.assignedParcels : [];
        const validParcel = parcels.find((parcel) => parcel && parcel.address && parcel.address.trim() && parcel.status !== 'delivered');
        return validParcel || null;
    }

    function setButton() {
        statusButton.textContent = online ? 'Go Offline' : 'Go Online';
        statusButton.classList.toggle('online', online);
        statusButton.classList.toggle('offline', !online);
    }

    function updateDestinationSummary(text, lat = null, lng = null) {
        const label = document.getElementById('active-destination-label');
        const mapsButton = document.getElementById('open-maps-button');
        const statusMessage = document.getElementById('route-status-message');

        if (label) {
            label.textContent = text || 'No active delivery';
        }

        if (statusMessage) {
            const isError = typeof lat !== 'number' || typeof lng !== 'number';
            statusMessage.textContent = isError
                ? 'Address could not be mapped to a valid destination. Please check the address format or try a more precise location.'
                : 'Destination located successfully.';
            statusMessage.style.color = isError ? '#b91c1c' : '#166534';
        }

        if (mapsButton) {
            const canNavigate = typeof lat === 'number' && typeof lng === 'number';
            mapsButton.disabled = !canNavigate;
            mapsButton.title = canNavigate ? 'Open route in Maps' : 'No destination available';
        }

        activeDestination = lat !== null && lng !== null ? { lat, lng } : null;
    }

    function setRouteStatus(message, isError = false) {
        const statusMessage = document.getElementById('route-status-message');
        if (!statusMessage) {
            return;
        }

        statusMessage.textContent = message;
        statusMessage.style.color = isError ? '#b91c1c' : '#166534';
    }

    async function toggleStatus() {
        online = !online;
        const response = await fetch(`${window.RIDER_CONFIG.apiBase}/ajax.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_status&status=${online ? 'online' : 'offline'}`
        });
        const data = await response.json();
        if (!data.success) {
            alert(data.message || 'Unable to update status');
            online = !online;
            return;
        }
        setButton();
        if (online) {
            enableLocationUpdates();
        } else {
            stopLocationUpdates();
        }
    }

    function enableLocationUpdates() {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported in this browser.');
            return;
        }

        watcherId = navigator.geolocation.watchPosition(sendLocation, handleError, {
            enableHighAccuracy: true,
            maximumAge: 5000,
            timeout: 15000,
        });
    }

    function stopLocationUpdates() {
        if (watcherId !== null) {
            navigator.geolocation.clearWatch(watcherId);
            watcherId = null;
        }
    }

    function clearRouteOverlay() {
        if (routeLayer) {
            map.removeLayer(routeLayer);
            routeLayer = null;
        }
        if (destinationMarker) {
            map.removeLayer(destinationMarker);
            destinationMarker = null;
        }
    }

    function normalizeAddress(address) {
        if (typeof address !== 'string') {
            return '';
        }

        return address
            .replace(/,/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return (R * c).toFixed(2);
    }

    function renderSearchResults(results, userLat = null, userLon = null) {
        const resultsContainer = document.getElementById('destination-search-results');
        const searchInput = document.getElementById('destination-search-input');
        if (!resultsContainer || !searchInput) {
            return;
        }

        if (!Array.isArray(results) || !results.length) {
            resultsContainer.innerHTML = '<div style="padding:10px 12px; color:#6b7280; font-size:14px;">No addresses found near your location.</div>';
            resultsContainer.style.display = 'block';
            return;
        }

        let sortedResults = [...results];
        if (typeof userLat === 'number' && typeof userLon === 'number') {
            sortedResults = sortedResults.map(result => ({
                ...result,
                distance: calculateDistance(userLat, userLon, parseFloat(result.lat), parseFloat(result.lon))
            })).sort((a, b) => parseFloat(a.distance) - parseFloat(b.distance));
        }

        resultsContainer.innerHTML = sortedResults.map((result, index) => {
            const distanceText = result.distance ? ` • ${result.distance} km away` : '';
            return `
                <button type="button" class="destination-search-item" data-search-result-index="${index}" style="display:block; width:100%; text-align:left; border:0; border-bottom:1px solid #f3f4f6; background:#fff; padding:10px 12px; cursor:pointer; font-size:14px; color:#111827;">
                    <strong style="display:block; margin-bottom:3px;">${(result.display_name || 'Address').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</strong>
                    <span style="color:#6b7280; font-size:12px;">${result.type || 'Address'}${distanceText}</span>
                </button>
            `;
        }).join('');

        resultsContainer.style.display = 'block';

        resultsContainer.querySelectorAll('[data-search-result-index]').forEach((button) => {
            button.addEventListener('click', async () => {
                const index = Number(button.dataset.searchResultIndex);
                const result = sortedResults[index];
                if (!result) {
                    return;
                }

                const selectedName = result.display_name || searchInput.value;
                searchInput.value = selectedName;
                resultsContainer.style.display = 'none';

                const selectedDisplay = document.getElementById('destination-selected-display');
                const selectedNameSpan = document.getElementById('destination-selected-name');
                if (selectedDisplay && selectedNameSpan) {
                    selectedNameSpan.textContent = selectedName;
                    selectedDisplay.style.display = 'block';
                }

                await routeToCoordinates(Number(result.lat), Number(result.lon), selectedName);
            });
        });
    }

    async function searchAddressSuggestions(query) {
        const normalizedQuery = normalizeAddress(query);
        if (!normalizedQuery || normalizedQuery.length < 2) {
            const resultsContainer = document.getElementById('destination-search-results');
            if (resultsContainer) {
                resultsContainer.style.display = 'none';
            }
            return;
        }

        let userLat = null;
        let userLon = null;

        try {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: false,
                    timeout: 5000,
                    maximumAge: 30000
                });
            });
            userLat = position.coords.latitude;
            userLon = position.coords.longitude;
        } catch (locationError) {
            console.warn('Could not get current location:', locationError);
        }

        try {
            let searchUrl = `${window.RIDER_CONFIG.apiBase}/search.php?q=${encodeURIComponent(normalizedQuery)}`;
            if (typeof userLat === 'number' && typeof userLon === 'number') {
                searchUrl += `&lat=${userLat}&lon=${userLon}`;
            }

            const response = await fetch(searchUrl, {
                headers: { Accept: 'application/json' }
            });

            if (!response.ok) {
                throw new Error(`Search failed (${response.status})`);
            }

            const data = await response.json();
            if (data.error) {
                throw new Error(data.error);
            }

            if (!Array.isArray(data) || !data.length) {
                const resultsContainer = document.getElementById('destination-search-results');
                if (resultsContainer) {
                    resultsContainer.innerHTML = '<div style="padding:10px 12px; color:#6b7280; font-size:14px;">No addresses found near your location.</div>';
                    resultsContainer.style.display = 'block';
                }
                return;
            }
            renderSearchResults(data, userLat, userLon);
        } catch (error) {
            console.warn('Address search failed:', error);
            const resultsContainer = document.getElementById('destination-search-results');
            if (resultsContainer) {
                resultsContainer.innerHTML = '<div style="padding:10px 12px; color:#b91c1c; font-size:14px;">Unable to search this location right now. Please try again later.</div>';
                resultsContainer.style.display = 'block';
            }
        }
    }

    async function routeToCoordinates(lat, lng, displayName) {
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            setRouteStatus('Selected destination is invalid.', true);
            return;
        }

        try {
            const currentPosition = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 10000
                });
            });

            const start = {
                lat: currentPosition.coords.latitude,
                lng: currentPosition.coords.longitude
            };

            const routePoints = await getDrivingRoute(start.lat, start.lng, lat, lng);
            clearRouteOverlay();

            if (routePoints.length) {
                routeLayer = L.polyline(routePoints, { color: '#2563eb', weight: 5, opacity: 0.85 }).addTo(map);
                const bounds = L.latLngBounds(routePoints);
                if (!hasUserCenteredMap) {
                    map.fitBounds(bounds, { padding: [40, 40] });
                    hasUserCenteredMap = true;
                }
            } else if (!hasUserCenteredMap) {
                map.setView([lat, lng], 14);
                hasUserCenteredMap = true;
            }

            destinationMarker = L.marker([lat, lng]).addTo(map).bindPopup(`Destination: ${displayName}`);
            L.circleMarker([lat, lng], {
                radius: 8,
                color: '#f97316',
                fillColor: '#f97316',
                fillOpacity: 0.9,
                weight: 1
            }).addTo(map).bindPopup(`Destination: ${displayName}`);

            updateDestinationSummary(displayName, lat, lng);
            setRouteStatus(`Destination selected: ${displayName}`, false);
        } catch (error) {
            console.warn('Route to selected destination failed:', error);
            setRouteStatus(error?.message || 'Unable to generate route for the selected destination.', true);
            updateDestinationSummary(displayName, null, null);
        }
    }

    async function geocodeAddress(address) {
        const normalizedAddress = normalizeAddress(address);
        if (!normalizedAddress) {
            console.error('geocodeAddress: empty normalized address');
            throw new Error('Address is empty');
        }

        console.log('geocodeAddress: looking up', normalizedAddress);
        setRouteStatus('Looking up destination address...', false);

        // 生成多个搜索查询 - 从复杂地址提取关键词
        const searchQueries = [];
        
        // 1. 完整地址（作为备选）
        searchQueries.push(normalizedAddress);
        
        // 2. 只取前 2-3 个词（通常是建筑名或主要地标）
        const words = normalizedAddress.split(/\s+/).filter(w => w.length > 0);
        if (words.length >= 2) {
            searchQueries.push(words.slice(0, 2).join(' '));
        }
        if (words.length >= 3) {
            searchQueries.push(words.slice(0, 3).join(' '));
        }
        
        // 3. 只取第一个词
        if (words.length > 0 && words[0].length > 2) {
            searchQueries.push(words[0]);
        }
        
        // 4. 查找城市名称
        const cityPatterns = [
            /George\s+Town/i,
            /Penang/i,
            /KL/i,
            /Kuala\s+Lumpur/i,
            /Seberang\s+Jaya/i,
            /Subang/i,
            /Petaling/i,
            /Selangor/i,
            /Bukit/i
        ];
        
        for (const pattern of cityPatterns) {
            const match = normalizedAddress.match(pattern);
            if (match) {
                searchQueries.push(match[0]);
            }
        }
        
        // 5. 如果是 "Prangin" 直接搜索
        if (normalizedAddress.toLowerCase().includes('prangin')) {
            searchQueries.unshift('Prangin'); // 优先级最高
        }

        // 去重，过滤空字符串和太短的查询
        const uniqueQueries = [...new Set(searchQueries)]
            .filter(q => q && q.trim().length > 1)
            .slice(0, 8); // 最多8个查询

        console.log('geocodeAddress: trying queries', uniqueQueries);

        let lastError = null;
        for (const query of uniqueQueries) {
            try {
                console.log('geocodeAddress: attempting query:', query);
                const response = await fetch(`${window.RIDER_CONFIG.apiBase}/search.php?q=${encodeURIComponent(query)}`, {
                    headers: {
                        Accept: 'application/json'
                    }
                });

                if (!response.ok) {
                    console.warn('geocodeAddress: fetch failed', response.status);
                    lastError = new Error(`Address lookup failed (${response.status})`);
                    continue;
                }

                const data = await response.json();
                console.log('geocodeAddress: response data for query "' + query + '":', data);
                
                if (data.error) {
                    console.warn('geocodeAddress: API error for query "' + query + '":', data.error);
                    lastError = new Error(data.error);
                    continue;
                }

                if (!Array.isArray(data) || !data.length) {
                    console.warn('geocodeAddress: no results for query "' + query + '"');
                    lastError = new Error(`No map result found for "${query}"`);
                    continue;
                }

                // 成功找到结果！
                setRouteStatus(`Address located: ${query}`, false);

                const lat = parseFloat(data[0].lat);
                const lng = parseFloat(data[0].lon);

                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    console.warn('geocodeAddress: invalid coordinates for query "' + query + '":', { lat, lng });
                    lastError = new Error(`Invalid coordinates returned for "${query}"`);
                    continue;
                }

                console.log('geocodeAddress: found', { query, lat, lng, displayName: data[0].display_name });
                return {
                    lat,
                    lng,
                    displayName: data[0].display_name || query
                };
            } catch (e) {
                console.warn('geocodeAddress: exception for query "' + query + '":', e);
                lastError = e;
                continue;
            }
        }

        // 所有查询都失败了
        console.error('geocodeAddress: all queries failed. last error:', lastError);
        throw lastError || new Error(`No map result found for "${normalizedAddress}"`);
    }

    async function reverseGeocodeLocation(lat, lng) {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18`, {
            headers: {
                Accept: 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`Nearby location lookup failed (${response.status})`);
        }

        const data = await response.json();
        return data?.display_name || 'Nearby location';
    }

    async function getDrivingRoute(startLat, startLng, endLat, endLng) {
        const url = `https://router.project-osrm.org/route/v1/driving/${startLng},${startLat};${endLng},${endLat}?overview=full&geometries=geojson`;
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`Route lookup failed (${response.status})`);
        }

        const data = await response.json();
        if (!data.routes || !data.routes.length) {
            throw new Error('No road route found for this destination');
        }

        const geometry = data.routes[0].geometry;
        if (!geometry || !Array.isArray(geometry.coordinates) || !geometry.coordinates.length) {
            throw new Error('Route geometry is empty');
        }

        const coordinates = geometry.coordinates.map(([lng, lat]) => [lat, lng]);
        return coordinates;
    }

    async function drawDestinationRoute(parcel) {
        console.log('drawDestinationRoute called with parcel:', parcel);
        
        if (!map || !parcel) {
            console.warn('drawDestinationRoute: map or parcel missing', { map: !!map, parcel: !!parcel });
            return;
        }

        const parcelId = Number(parcel.id);
        if (routeRenderInFlight) {
            console.warn('drawDestinationRoute: route render already in flight');
            return;
        }
        if (lastRenderedParcelId !== null && Number(lastRenderedParcelId) === parcelId) {
            console.warn('drawDestinationRoute: same parcel already rendered');
            return;
        }

        const normalizedAddress = normalizeAddress(parcel.address);
        if (!normalizedAddress) {
            console.warn('drawDestinationRoute: address normalization failed for:', parcel.address);
            updateDestinationSummary('No address available', null, null);
            return;
        }

        if (!navigator.geolocation) {
            console.warn('drawDestinationRoute: geolocation not available');
            updateDestinationSummary(normalizedAddress, null, null);
            return;
        }

        const routeKey = `${parcelId}|${normalizedAddress}`;
        routeRenderInFlight = true;
        activeRouteParcelId = parcelId;
        let lastKnownValidDestination = null;

        try {
            const currentPosition = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 10000
                });
            });

            const start = {
                lat: currentPosition.coords.latitude,
                lng: currentPosition.coords.longitude
            };

            let destination = null;
            let nearbyLocationName = normalizedAddress;

            try {
                destination = await geocodeAddress(normalizedAddress);
                lastKnownValidDestination = destination;
            } catch (addressError) {
                console.error('drawDestinationRoute: address lookup failed:', addressError);
                setRouteStatus(`Address not found: ${normalizedAddress}. Try searching manually above.`, true);
                updateDestinationSummary(normalizedAddress, null, null);
                
                // 不要使用当前位置作为目的地，只更新UI并返回
                lastRenderedRouteKey = routeKey;
                lastRenderedParcelId = Number(parcel.id);
                routeRenderInFlight = false;
                return;
            }

            let routePoints = [];

            try {
                console.log('drawDestinationRoute: requesting driving route from', start, 'to', destination);
                routePoints = await getDrivingRoute(start.lat, start.lng, destination.lat, destination.lng);
                console.log('drawDestinationRoute: got', routePoints.length, 'route points');
            } catch (routeError) {
                console.warn('Driving route unavailable:', routeError);
                setRouteStatus('Address mapped, but no drivable route was found from your current location.', true);
            }

            // 现在清除旧的显示
            clearRouteOverlay();
            console.log('drawDestinationRoute: cleared previous overlays');

            // 立即添加新的显示
            if (routePoints.length) {
                console.log('drawDestinationRoute: adding polyline to map');
                routeLayer = L.polyline(routePoints, { color: '#2563eb', weight: 5, opacity: 0.85 }).addTo(map);
                const bounds = L.latLngBounds(routePoints);
                console.log('drawDestinationRoute: fitting bounds', bounds);
                map.fitBounds(bounds, { padding: [40, 40] });
                hasUserCenteredMap = true;
                lastRenderedRouteKey = routeKey;
                lastRenderedParcelId = Number(parcel.id);
                console.log('drawDestinationRoute: route drawn successfully');
            } else if (destination) {
                // 没有驾驶路线，但有目的地，只显示目标位置
                console.log('drawDestinationRoute: no driving route, setting view to destination');
                map.setView([destination.lat, destination.lng], 14);
                hasUserCenteredMap = true;
                lastRenderedRouteKey = routeKey;
                lastRenderedParcelId = Number(parcel.id);
                console.log('drawDestinationRoute: destination only mode');
            }

            // 添加目标位置标记
            if (destination) {
                console.log('drawDestinationRoute: adding destination marker at', destination.lat, destination.lng);
                destinationMarker = L.marker([destination.lat, destination.lng]).addTo(map).bindPopup(`Destination: ${destination.displayName}`);
                L.circleMarker([destination.lat, destination.lng], {
                    radius: 8,
                    color: '#f97316',
                    fillColor: '#f97316',
                    fillOpacity: 0.9,
                    weight: 1
                }).addTo(map).bindPopup(`Destination: ${destination.displayName}`);

                updateDestinationSummary(destination.displayName, destination.lat, destination.lng);
                if (!routePoints.length) {
                    setRouteStatus(`Destination: ${destination.displayName} (no driving route found)`, true);
                } else {
                    setRouteStatus(`Route to ${destination.displayName}`, false);
                }
                console.log('drawDestinationRoute: destination marker added');
            }
        } catch (error) {
            console.warn('Route drawing failed:', error);
            setRouteStatus(error?.message || 'Unable to search this address or generate a route.', true);

            if (lastKnownValidDestination) {
                updateDestinationSummary(normalizedAddress, lastKnownValidDestination.lat, lastKnownValidDestination.lng);
                return;
            }

            updateDestinationSummary(normalizedAddress, null, null);
        } finally {
            routeRenderInFlight = false;
        }
    }

    async function sendLocation(position) {
        console.log('sendLocation: position update', position.coords);
        
        if (!map) {
            console.log('sendLocation: initializing map');
            initMap();
        }
        const payload = new URLSearchParams();
        payload.append('action', 'save_location');
        payload.append('latitude', String(position.coords.latitude));
        payload.append('longitude', String(position.coords.longitude));
        await fetch(`${window.RIDER_CONFIG.apiBase}/ajax.php`, {
            method: 'POST',
            body: payload
        });
        if (map) {
            const location = [position.coords.latitude, position.coords.longitude];
            marker.setLatLng(location);

            if (!hasUserCenteredMap) {
                centerMapOnce(location, 15);
                if (!marker.getPopup()) {
                    marker.bindPopup('Current position');
                }
                marker.openPopup();
            } else {
                console.log('sendLocation: map already centered, skipping centerMapOnce');
                if (routeLayer || destinationMarker) {
                    return;
                }
            }

            const hasManualRoute = activeRouteParcelId !== null;
            const hasDisplayedRoute = routeLayer !== null || destinationMarker !== null;

            if (hasManualRoute) {
                console.log('sendLocation: manual route active (parcel', activeRouteParcelId + '), not auto-routing');
                return;
            }

            if (hasDisplayedRoute) {
                console.log('sendLocation: route already displayed on map, not auto-routing');
                return;
            }

            if (routeRenderInFlight) {
                console.log('sendLocation: route drawing in progress, skipping');
                return;
            }

            const parcel = getActiveParcel();
            if (parcel) {
                console.log('sendLocation: auto-drawing route for first parcel', parcel.id);
                drawDestinationRoute(parcel);
            } else {
                console.log('sendLocation: no active parcels to route');
            }
        }
    }

    function handleError(error) {
        if (error.code === 1) {
            alert('Location access denied. Please allow location permission to view the map.');
        } else if (error.code === 2) {
            alert('Unable to determine your location. Please try again.');
        } else if (error.code === 3) {
            alert('Location request timed out. Please try again.');
        }
        console.warn('Location error', error);
    }

    function initMap() {
        const mapElement = document.getElementById(window.RIDER_CONFIG.mapElement);
        console.log('initMap called, mapElement:', mapElement, 'Leaflet L:', typeof L);
        if (!mapElement) {
            console.warn('Map element not found.');
            return;
        }
        if (typeof L === 'undefined') {
            console.warn('Leaflet is not loaded.');
            return;
        }

        map = L.map(mapElement).setView([5.3667, 100.3167], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        marker = L.marker([5.3667, 100.3167]).addTo(map).bindPopup('Your approximate location');

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((position) => {
                const location = [position.coords.latitude, position.coords.longitude];
                marker.setLatLng(location);
                centerMapOnce(location, 15);
                marker.bindPopup('Your location').openPopup();
                setTimeout(() => map.invalidateSize(), 200);
                const parcel = getActiveParcel();
                if (parcel && !routeLayer && !destinationMarker) {
                    drawDestinationRoute(parcel);
                }
            }, handleError, { enableHighAccuracy: true, timeout: 15000 });
        }
    }

    document.addEventListener('click', async (event) => {
        const claimButton = event.target.closest('[data-claim-parcel]');
        if (claimButton) {
            const parcelId = claimButton.dataset.claimParcel;
            try {
                const response = await fetch(`${window.RIDER_CONFIG.apiBase}/ajax.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=claim_parcel&parcel_id=${encodeURIComponent(parcelId)}`
                });
                const data = await response.json();
                if (!data.success) {
                    alert(data.message || 'Unable to claim parcel.');
                    return;
                }
                alert(data.message || 'Parcel claimed successfully.');
                window.location.reload();
            } catch (error) {
                console.error('Claim parcel failed:', error);
                alert('Unable to claim parcel right now.');
            }
            return;
        }

        const releaseButton = event.target.closest('[data-release-parcel]');
        if (releaseButton) {
            const parcelId = releaseButton.dataset.releaseParcel;
            const confirmed = window.confirm('Release this parcel and make it available again?');
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch(`${window.RIDER_CONFIG.apiBase}/ajax.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=release_parcel&parcel_id=${encodeURIComponent(parcelId)}`
                });
                const data = await response.json();
                if (!data.success) {
                    alert(data.message || 'Unable to release parcel.');
                    return;
                }
                alert(data.message || 'Parcel released successfully.');
                window.location.reload();
            } catch (error) {
                console.error('Release parcel failed:', error);
                alert('Unable to release parcel right now.');
            }
            return;
        }

        const button = event.target.closest('[data-route-parcel]');
        if (!button) {
            return;
        }

        // 搜索分配给用户的包裹和可用包裹
        const assignedParcel = Array.isArray(window.RIDER_CONFIG?.assignedParcels)
            ? window.RIDER_CONFIG.assignedParcels.find((item) => Number(item.id) === Number(button.dataset.routeParcel)) || null
            : null;

        const availableParcel = Array.isArray(window.RIDER_CONFIG?.availableParcels)
            ? window.RIDER_CONFIG.availableParcels.find((item) => Number(item.id) === Number(button.dataset.routeParcel)) || null
            : null;

        const selectedParcel = assignedParcel || availableParcel;

        if (selectedParcel) {
            console.log('Route button clicked for parcel:', selectedParcel.id, selectedParcel.address);
            
            const destinationDisplay = document.getElementById('destination-selected-display');
            const destinationName = document.getElementById('destination-selected-name');
            const searchInput = document.getElementById('destination-search-input');

            if (searchInput) {
                searchInput.value = selectedParcel.address || '';
            }

            if (destinationDisplay && destinationName) {
                destinationName.textContent = selectedParcel.address || 'Selected destination';
                destinationDisplay.style.display = 'block';
            }

            // 重置状态以允许重新绘制此包裹
            console.log('Clearing previous route state...');
            lastRenderedParcelId = null;
            activeRouteParcelId = null;
            routeRenderInFlight = false;
            clearRouteOverlay(); // 清除旧的路线显示
            
            console.log('Calling drawDestinationRoute for parcel', selectedParcel.id);
            await drawDestinationRoute(selectedParcel);
        }
    });

    const mapsButton = document.getElementById('open-maps-button');
    if (mapsButton) {
        mapsButton.addEventListener('click', () => {
            if (!activeDestination) {
                return;
            }

            const userAgent = navigator.userAgent || '';
            const isApple = /iPhone|iPad|iPod/i.test(userAgent);
            const googleMapsUrl = `https://www.google.com/maps/dir/?api=1&destination=${activeDestination.lat},${activeDestination.lng}&travelmode=driving`;
            const appleMapsUrl = `http://maps.apple.com/?daddr=${activeDestination.lat},${activeDestination.lng}&dirflg=d`;
            window.open(isApple ? appleMapsUrl : googleMapsUrl, '_blank');
        });
    }

    const destinationSearchInput = document.getElementById('destination-search-input');
    const destinationSearchResults = document.getElementById('destination-search-results');
    const destinationSelectedDisplay = document.getElementById('destination-selected-display');

    if (destinationSearchInput) {
        destinationSearchInput.addEventListener('input', async (event) => {
            const query = event.target.value || '';
            if (!query.trim()) {
                if (destinationSearchResults) {
                    destinationSearchResults.style.display = 'none';
                }
                return;
            }
            await searchAddressSuggestions(query);
        });

        destinationSearchInput.addEventListener('keydown', async (event) => {
            if (event.key !== 'Enter' || !destinationSearchResults || destinationSearchResults.style.display === 'none') {
                return;
            }

            const firstResultButton = destinationSearchResults.querySelector('[data-search-result-index]');
            if (firstResultButton) {
                firstResultButton.click();
            }
        });

        destinationSearchInput.addEventListener('focus', () => {
            if (destinationSelectedDisplay) {
                destinationSelectedDisplay.style.display = 'none';
            }
            if (destinationSearchResults && destinationSearchInput.value.trim().length >= 2) {
                destinationSearchResults.style.display = 'block';
            }
        });
    }

    statusButton.addEventListener('click', toggleStatus);
    setButton();
    updateDestinationSummary('No active delivery', null, null);
    initMap();
    if (online) {
        enableLocationUpdates();
    }
});
