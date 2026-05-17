/**
 * ReliefLink AJAX layer — JSON fetch with session cookies.
 */
(function (global) {
    const API_BASE = (function () {
        const path = window.location.pathname.replace(/\\/g, '/');
        if (path.includes('/html/admin/') || path.includes('/html/user/')) {
            return '../../php/api';
        }
        if (path.includes('/html/')) {
            return '../php/api';
        }
        return '/php/api';
    })();

    async function request(url, options = {}) {
        const opts = {
            credentials: 'include',
            headers: { Accept: 'application/json', ...(options.headers || {}) },
            ...options,
        };
        if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(opts.body);
        }
        const res = await fetch(url, opts);
        const text = await res.text();
        let data = {};
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            data = { success: false, message: 'Invalid server response' };
        }
        if (!res.ok && data.success !== false) {
            data.success = false;
            data.message = data.message || `Request failed (${res.status})`;
        }
        data._httpStatus = res.status;
        return data;
    }

    function apiUrl(file, action, params = {}) {
        const q = new URLSearchParams({ action, ...params });
        return `${API_BASE}/${file}?${q.toString()}`;
    }

    const ReliefAjax = {
        base: API_BASE,
        get(file, action, params = {}) {
            return request(apiUrl(file, action, params), { method: 'GET' });
        },
        post(file, action, body = {}) {
            return request(`${API_BASE}/${file}?action=${encodeURIComponent(action)}`, { method: 'POST', body });
        },
        put(file, action, body = {}) {
            return request(`${API_BASE}/${file}?action=${encodeURIComponent(action)}`, { method: 'PUT', body });
        },
        reports: {
            get: (id) => ReliefAjax.get('reports.php', 'get', { report_id: id }),
            listUser: () => ReliefAjax.get('reports.php', 'list_user'),
            listAll: (status) => ReliefAjax.get('reports.php', 'list_all', status ? { status } : {}),
            submit: (body) => ReliefAjax.post('reports.php', 'submit', body),
            updateOwn: (body) => ReliefAjax.put('reports.php', 'update_own', body),
            deployAid: (reportId) => ReliefAjax.post('reports.php', 'deploy_aid', { report_id: reportId }),
            confirmDelivery: (body) => ReliefAjax.post('reports.php', 'confirm_delivery', body),
            myBarangayGeo: () => ReliefAjax.get('reports.php', 'my_barangay_geo'),
        },
        hazards: {
            list: () => ReliefAjax.get('hazards.php', 'list'),
            save: (body) => ReliefAjax.post('hazards.php', 'save', body),
            remove: (id) => ReliefAjax.post('hazards.php', 'delete', { id }),
        },
        relief: {
            deployStatus: () => ReliefAjax.get('relief.php', 'deploy_status'),
        },
        notifications: {
            emergencyBroadcast: () => ReliefAjax.get('notifications.php', 'emergency_broadcast'),
        },
        auth: {
            register: (body) => ReliefAjax.post('auth.php', 'register', body),
        },
        evacuation: {
            listNearby: (params) => ReliefAjax.get('evacuation.php', 'list_nearby', params),
        },
    };

    global.ReliefAjax = ReliefAjax;
})(typeof window !== 'undefined' ? window : global);
