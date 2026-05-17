/**
 * Structured disaster report payload stored in description (JSON).
 */
(function (global) {
    function parseDescription(raw) {
        if (!raw || typeof raw !== 'string') {
            return { isStructured: false, notes: '', raw: raw || '' };
        }
        const t = raw.trim();
        if (!t.startsWith('{')) {
            return { isStructured: false, notes: raw, raw };
        }
        try {
            const d = JSON.parse(t);
            if (d && d.is_structured) {
                return {
                    isStructured: true,
                    notes: d.notes || '',
                    vulnerable: d.vulnerable || {},
                    lifelines: d.lifelines || {},
                    requests: d.requests || {},
                    raw: d,
                };
            }
        } catch (e) { /* plain text */ }
        return { isStructured: false, notes: raw, raw };
    }

    function buildPayload(form) {
        return {
            is_structured: true,
            notes: (form.notes || '').trim(),
            vulnerable: {
                children: +(form.vulnerable?.children || 0),
                seniors: +(form.vulnerable?.seniors || 0),
                pregnant: +(form.vulnerable?.pregnant || 0),
                pwd: +(form.vulnerable?.pwd || 0),
            },
            lifelines: {
                power: form.lifelines?.power || 'Operational',
                water: form.lifelines?.water || 'Operational',
                road: form.lifelines?.road || 'Passable',
            },
            requests: {
                food: +(form.requests?.food || 0),
                hygiene: +(form.requests?.hygiene || 0),
                water: +(form.requests?.water || 0),
            },
        };
    }

    function lifelineClass(value, type) {
        const v = String(value || '').toLowerCase();
        if (type === 'road') {
            if (v.includes('block')) return 'danger';
            if (v.includes('light')) return 'warning';
            return 'success';
        }
        if (v.includes('outage') || v.includes('contaminat')) return 'danger';
        if (v.includes('interrupt')) return 'warning';
        return 'success';
    }

    function renderHtml(parsed, escFn) {
        const esc = escFn || ((s) => String(s == null ? '' : s));
        if (!parsed.isStructured) {
            const text = parsed.notes || parsed.raw || '';
            if (!text) return '<span style="color:var(--text-subtle);">—</span>';
            return '<div style="background:#f8f9fa;padding:12px;border-radius:6px;color:var(--text-subtle);white-space:pre-wrap;font-size:13px;">'
                + esc(text) + '</div>';
        }

        const v = parsed.vulnerable;
        const life = parsed.lifelines;
        const req = parsed.requests;
        const parts = [];

        if (parsed.notes) {
            parts.push('<div style="margin-bottom:14px;background:#f8f9fa;padding:12px;border-radius:6px;border-left:3px solid var(--primary);font-size:13px;white-space:pre-wrap;">'
                + esc(parsed.notes) + '</div>');
        } else {
            parts.push('<div style="color:var(--text-subtle);margin-bottom:10px;font-size:13px;">No field notes attached.</div>');
        }

        parts.push('<div style="background:#fff;border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:12px;">');
        parts.push('<div style="font-weight:700;font-size:11px;text-transform:uppercase;color:var(--primary);margin-bottom:10px;">Vulnerable demographics</div>');
        parts.push('<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;text-align:center;">');
        [['Infants / Children', v.children], ['Seniors', v.seniors], ['Pregnant', v.pregnant], ['PWDs', v.pwd]].forEach(([label, n]) => {
            parts.push('<div style="background:#f4f5f7;padding:8px;border-radius:6px;"><div style="font-size:9px;color:var(--text-subtle);text-transform:uppercase;font-weight:700;">'
                + label + '</div><div style="font-size:16px;font-weight:700;margin-top:4px;">' + esc(n || 0) + '</div></div>');
        });
        parts.push('</div></div>');

        parts.push('<div style="background:#fff;border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:12px;">');
        parts.push('<div style="font-weight:700;font-size:11px;text-transform:uppercase;color:var(--primary);margin-bottom:10px;">Utility and lifeline status</div>');
        parts.push('<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">');
        [['Electricity', life.power, 'power'], ['Water supply', life.water, 'water'], ['Road passage', life.road, 'road']].forEach(([label, val, t]) => {
            const c = lifelineClass(val, t);
            parts.push('<div style="border-left:4px solid var(--' + c + ');padding:8px 10px;background:#fafbfc;border-radius:0 4px 4px 0;">');
            parts.push('<div style="font-size:9px;color:var(--text-subtle);text-transform:uppercase;">' + label + '</div>');
            parts.push('<div style="font-size:12px;font-weight:700;margin-top:4px;">' + esc(val || 'Unknown') + '</div></div>');
        });
        parts.push('</div></div>');

        parts.push('<div style="background:#fff;border:1px solid var(--border);border-radius:8px;padding:14px;">');
        parts.push('<div style="font-weight:700;font-size:11px;text-transform:uppercase;color:var(--primary);margin-bottom:10px;">Relief quantities requested</div>');
        parts.push('<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center;">');
        [['Food packs', req.food, 'pcs'], ['Hygiene kits', req.hygiene, 'pcs'], ['Drinking water', req.water, 'L']].forEach(([label, n, unit]) => {
            parts.push('<div style="border:1px solid var(--border);padding:10px;border-radius:6px;">');
            parts.push('<div style="font-size:9px;color:var(--text-subtle);text-transform:uppercase;">' + label + '</div>');
            parts.push('<div style="font-size:15px;font-weight:700;color:var(--primary);margin-top:4px;">' + esc(n || 0) + ' ' + unit + '</div></div>');
        });
        parts.push('</div></div>');

        return parts.join('');
    }

    global.ReliefStructuredReport = {
        parseDescription,
        buildPayload,
        lifelineClass,
        renderHtml,
        toJson: (form) => JSON.stringify(buildPayload(form)),
    };
})(typeof window !== 'undefined' ? window : global);
