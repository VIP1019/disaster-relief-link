/**
 * ReliefLink — shared sidebar (role: admin vs barangay official).
 * Include after <aside id="relief-sidebar-mount"></aside>; call await ReliefSidebar.mount() before page logic that uses #logoutBtn.
 */
(function () {
  'use strict';

  function getApiBase() {
    var path = (window.location.pathname || '').replace(/\\/g, '/');
    if (path.indexOf('/admin/') !== -1 || path.indexOf('/user/') !== -1) {
      return '../../php/api';
    }
    return '../php/api';
  }

  function currentPageFile() {
    var path = window.location.pathname || '';
    var parts = path.split('/').filter(Boolean);
    return parts.length ? parts[parts.length - 1] : '';
  }

  function detectArea() {
    var path = (window.location.pathname || '').replace(/\\/g, '/');
    if (path.indexOf('/admin/') !== -1) return 'admin';
    if (path.indexOf('/user/') !== -1) return 'user';
    return null;
  }

  function loginHref() {
    return detectArea() ? '../login.html' : 'login.html';
  }

  var ICONS = {
    grid: '<svg class="icon" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
    reports: '<svg class="icon" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
    priority: '<svg class="icon" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
    inventory: '<svg class="icon" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
    distribution: '<svg class="icon" aria-hidden="true"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
    weather: '<svg class="icon" aria-hidden="true"><path d="M17.5 19c.7 0 1.2-.6 1.2-1.2 0-.3-.1-.6-.3-.8l-1-1.1-1 1.1c-.2.2-.3.5-.3.8 0 .7.5 1.2 1.2 1.2z"></path><path d="M18 10c0-4.4-3.6-8-8-8s-8 3.6-8 8c0 2.2 1.8 4 4 4h1a4 4 0 0 1 4 4v1h1a4 4 0 0 0 4-4v-1h1a4 4 0 0 0 4-4z"></path></svg>',
    bell: '<svg class="icon" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>',
    plus: '<svg class="icon" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>',
    home: '<svg class="icon" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
    signout: '<svg class="icon" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>'
  };

  var ADMIN_NAV = [
    { href: 'dashboard.html', label: 'Command Center', icon: 'grid' },
    { href: 'review-reports.html', label: 'Disaster Reports', icon: 'reports' },
    { href: 'prioritize-barangays.html', label: 'Priority Ranking', icon: 'priority' },
    { href: 'relief-inventory.html', label: 'Resource Inventory', icon: 'inventory' },
    { href: 'distribution.html', label: 'Distribution Management', icon: 'distribution' },
    { href: 'evacuation-centers.html', label: 'Evacuation Centers', icon: 'home' },
    { href: 'weather-monitoring.html', label: 'Weather Monitoring', icon: 'weather' },
    { href: 'manage-notifications.html', label: 'Manage Notifications', icon: 'bell' }
  ];

  var USER_NAV = [
    { href: 'dashboard.html', label: 'Dashboard', icon: 'grid' },
    { href: 'submit-report.html', label: 'Submit Report', icon: 'plus' },
    { href: 'view-reports.html', label: 'My Reports', icon: 'reports' },
    { href: 'notifications.html', label: 'Notifications', icon: 'bell' }
  ];

  function navHtml(items, activeFile) {
    var lis = items.map(function (item) {
      var active = item.href === activeFile ? ' class="active"' : '';
      return (
        '<li><a href="' + item.href + '"' + active + '>' +
        ICONS[item.icon] +
        item.label +
        '</a></li>'
      );
    }).join('');
    return lis;
  }

  function bindLogout(apiBase) {
    var btn = document.getElementById('logoutBtn');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      fetch(apiBase + '/auth.php?action=logout', { credentials: 'include' }).finally(function () {
        window.location.href = loginHref();
      });
    });
  }

  function ensureModal() {
    var existing = document.getElementById('reliefUiModal');
    if (existing) return existing;

    var style = document.createElement('style');
    style.textContent =
      '.relief-ui-overlay{position:fixed;inset:0;background:rgba(9,30,66,.48);z-index:3000;display:none;align-items:center;justify-content:center;padding:24px;}' +
      '.relief-ui-overlay.open{display:flex;}' +
      '.relief-ui-box{width:100%;max-width:420px;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:var(--shadow-lg);padding:22px;}' +
      '.relief-ui-title{font-size:17px;font-weight:700;margin-bottom:8px;color:var(--text-main);}' +
      '.relief-ui-message{font-size:13px;color:var(--text-subtle);line-height:1.5;white-space:pre-wrap;}' +
      '.relief-ui-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;}' +
      '.relief-ui-actions .btn{min-width:92px;}';
    document.head.appendChild(style);

    var overlay = document.createElement('div');
    overlay.id = 'reliefUiModal';
    overlay.className = 'relief-ui-overlay';
    overlay.innerHTML =
      '<div class="relief-ui-box" role="dialog" aria-modal="true" aria-labelledby="reliefUiTitle">' +
      '<div class="relief-ui-title" id="reliefUiTitle"></div>' +
      '<div class="relief-ui-message" id="reliefUiMessage"></div>' +
      '<div class="relief-ui-actions">' +
      '<button type="button" class="btn" id="reliefUiCancel" style="border:1px solid var(--border);background:#fff;">Cancel</button>' +
      '<button type="button" class="btn btn-primary" id="reliefUiConfirm">Confirm</button>' +
      '</div></div>';
    document.body.appendChild(overlay);
    return overlay;
  }

  function modalConfirm(options) {
    var cfg = typeof options === 'string' ? { message: options } : (options || {});
    var overlay = ensureModal();
    var title = overlay.querySelector('#reliefUiTitle');
    var msg = overlay.querySelector('#reliefUiMessage');
    var cancel = overlay.querySelector('#reliefUiCancel');
    var confirm = overlay.querySelector('#reliefUiConfirm');
    title.textContent = cfg.title || 'Confirm action';
    msg.textContent = cfg.message || 'Continue?';
    cancel.textContent = cfg.cancelText || 'Cancel';
    cancel.style.display = cfg.cancelText === '' ? 'none' : '';
    confirm.textContent = cfg.confirmText || 'Confirm';
    confirm.className = 'btn ' + (cfg.danger ? '' : 'btn-primary');
    if (cfg.danger) {
      confirm.style.background = 'var(--danger)';
      confirm.style.color = '#fff';
    } else {
      confirm.style.background = '';
      confirm.style.color = '';
    }
    overlay.classList.add('open');

    return new Promise(function (resolve) {
      function done(value) {
        overlay.classList.remove('open');
        cancel.style.display = '';
        cancel.removeEventListener('click', cancelFn);
        confirm.removeEventListener('click', confirmFn);
        overlay.removeEventListener('click', overlayFn);
        document.removeEventListener('keydown', keyFn);
        resolve(value);
      }
      function cancelFn() { done(false); }
      function confirmFn() { done(true); }
      function overlayFn(e) { if (e.target === overlay) done(false); }
      function keyFn(e) { if (e.key === 'Escape') done(false); }
      cancel.addEventListener('click', cancelFn);
      confirm.addEventListener('click', confirmFn);
      overlay.addEventListener('click', overlayFn);
      document.addEventListener('keydown', keyFn);
      confirm.focus();
    });
  }

  function modalAlert(options) {
    var cfg = typeof options === 'string' ? { message: options } : (options || {});
    return modalConfirm({
      title: cfg.title || 'Notice',
      message: cfg.message || '',
      confirmText: cfg.confirmText || 'OK',
      cancelText: '',
    });
  }

  async function mount() {
    var mount = document.getElementById('relief-sidebar-mount');
    if (!mount) return null;

    var area = detectArea();
    if (!area) return null;

    var apiBase = getApiBase();
    var activeFile = currentPageFile();

    var res = await fetch(apiBase + '/auth.php?action=check', { credentials: 'include' });
    var data = await res.json().catch(function () {
      return { success: false };
    });

    if (!data.success) {
      window.location.href = loginHref();
      return null;
    }

    var ut = data.user && data.user.user_type;
    if (area === 'admin' && ut !== 'admin') {
      window.location.href = '../user/dashboard.html';
      return null;
    }
    if (area === 'user' && ut !== 'barangay_official') {
      window.location.href = '../admin/dashboard.html';
      return null;
    }

    var items = area === 'admin' ? ADMIN_NAV : USER_NAV;

    mount.innerHTML =
      '<div class="sidebar-header">' +
      '<div class="logo-box">R</div>' +
      '<div class="sidebar-brand" style="letter-spacing: 0.06em; text-transform: uppercase; font-weight: 700;">ReliefLink</div>' +
      '</div>' +
      '<ul class="sidebar-menu">' + navHtml(items, activeFile) + '</ul>' +
      '<div class="sidebar-footer">' +
      '<a href="#" id="logoutBtn" style="color: rgba(255,255,255,0.7); display: flex; align-items: center; gap: 10px; font-size: 14px;">' +
      ICONS.signout +
      'Sign Out' +
      '</a>' +
      '</div>';

    bindLogout(apiBase);
    return data.user;
  }

  window.ReliefSidebar = { mount: mount, getApiBase: getApiBase };
  window.ReliefUI = { confirm: modalConfirm, alert: modalAlert };
})();
