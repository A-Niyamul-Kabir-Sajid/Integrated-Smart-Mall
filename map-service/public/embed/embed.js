// The embedded map of the ISM map service (ISM-64). Opened in an iframe from a
// signed link; talks only to this service with the page's session token.
// Messages to the host page: ism-map:ready, ism-map:route, ism-map:error, ism-map:floor.
// Messages from the host page (allowed origins only): ism-map:navigate, ism-map:floor.
(() => {
    'use strict';

    const config = JSON.parse(document.getElementById('embed-config').textContent);
    const SVG = 'http://www.w3.org/2000/svg';
    const $ = (id) => document.getElementById(id);

    const TEXT = {
        en: {
            from: 'From', to: 'To', stepFree: 'Step-free', go: 'Show route', fit: 'Fit', ar: 'View in AR', openAr: 'Open AR in a new tab',
            chooseStart: 'Where are you?', chooseDest: 'Where to?', qr: 'QR codes', shops: 'Shops', facilities: 'Facilities', nearest: 'Nearest', points: 'Places',
            shop: 'Shop', min: 'min', m: 'm', s: 's', loading: 'Loading the map…', failed: 'Something went wrong. Try again.',
            expired: 'This map has expired. Reload the page.', pick: 'Choose where you are and where to go.', updated: 'Route updated: a walkway changed.',
            youAreHere: 'You are here', floor: 'Floor', start: 'Start',
            types: { toilet: 'Toilet', prayer_room: 'Prayer room', atm: 'ATM', exit: 'Exit', information: 'Information desk', baby_care: 'Baby care', first_aid: 'First aid', parking: 'Parking' },
        },
        bn: {
            from: 'কোথা থেকে', to: 'কোথায়', stepFree: 'সিঁড়ি ছাড়া', go: 'পথ দেখাও', fit: 'পুরো ম্যাপ', ar: 'এআর-এ দেখুন', openAr: 'নতুন ট্যাবে এআর খুলুন',
            chooseStart: 'আপনি কোথায় আছেন?', chooseDest: 'কোথায় যাবেন?', qr: 'কিউআর কোড', shops: 'দোকান', facilities: 'সুবিধা', nearest: 'সবচেয়ে কাছের', points: 'স্থান',
            shop: 'দোকান', min: 'মিনিট', m: 'মিটার', s: 'সেকেন্ড', loading: 'ম্যাপ লোড হচ্ছে…', failed: 'কিছু একটা ভুল হয়েছে। আবার চেষ্টা করুন।',
            expired: 'এই ম্যাপের মেয়াদ শেষ। পাতাটি আবার লোড করুন।', pick: 'আপনি কোথায় আছেন এবং কোথায় যাবেন তা বেছে নিন।', updated: 'পথ বদলেছে: একটি হাঁটার পথ বন্ধ বা খোলা হয়েছে।',
            youAreHere: 'আপনি এখানে', floor: 'তলা', start: 'শুরু',
            types: { toilet: 'টয়লেট', prayer_room: 'নামাজের ঘর', atm: 'এটিএম', exit: 'বের হওয়ার পথ', information: 'তথ্য কেন্দ্র', baby_care: 'শিশু যত্ন কক্ষ', first_aid: 'প্রাথমিক চিকিৎসা', parking: 'পার্কিং' },
        },
    };
    const words = TEXT[config.lang] || TEXT.en;
    const t = (key) => words[key] ?? TEXT.en[key] ?? key;
    const typeName = (type) => words.types[type] ?? TEXT.en.types[type] ?? type.replace(/_/g, ' ');

    const state = { data: null, nodes: new Map(), floor: null, route: null, view: null, drag: null, pointers: new Map(), refresh: null, ar: false };

    // ---- host page messages ------------------------------------------------
    function post(type, payload = {}) {
        if (window.parent === window) return;
        for (const origin of config.origins) {
            try { window.parent.postMessage({ source: 'ism-map', type: 'ism-map:' + type, mapId: config.mapId, ...payload }, origin); } catch (error) { /* another origin */ }
        }
    }

    window.addEventListener('message', (event) => {
        if (!config.origins.includes(event.origin) || !event.data || typeof event.data !== 'object' || event.data.source === 'ism-map') return;
        if (event.data.type === 'ism-map:navigate') {
            if (typeof event.data.from === 'string') setSelect($('from'), event.data.from);
            if (typeof event.data.to === 'string') setSelect($('to'), event.data.to);
            if (typeof event.data.stepFree === 'boolean') $('step-free').checked = event.data.stepFree;
            askRoute();
        } else if (event.data.type === 'ism-map:floor' && typeof event.data.floor === 'string') {
            setFloor(event.data.floor);
        }
    });

    // ---- calls to the service ---------------------------------------------
    async function call(url, params) {
        const target = new URL(url, location.href);
        for (const [name, value] of Object.entries(params || {})) {
            if (Array.isArray(value)) value.forEach((item) => target.searchParams.append(name + '[]', item));
            else if (value !== null && value !== undefined && value !== '') target.searchParams.set(name, value);
        }
        const response = await fetch(target, { headers: { Accept: 'application/json', 'X-Embed-Token': config.token }, credentials: 'omit' });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(response.status === 401 ? t('expired') : (body.message || t('failed')));
            error.reason = body.reason || 'http_' + response.status;
            throw error;
        }
        return body.data;
    }

    // ---- form --------------------------------------------------------------
    function option(select, value, label, group) {
        const item = document.createElement('option');
        item.value = value;
        item.textContent = label;
        (group || select).appendChild(item);
        return item;
    }

    function group(select, label) {
        const element = document.createElement('optgroup');
        element.label = label;
        select.appendChild(element);
        return element;
    }

    function setSelect(select, value) {
        if (![...select.options].some((item) => item.value === value)) option(select, value, value);
        select.value = value;
    }

    function shopLabel(unit) {
        const sign = state.data.signs[unit.unit_code];
        return sign ? `${sign} (${unit.unit_code})` : `${t('shop')} ${unit.unit_code}`;
    }

    function fillForm() {
        const places = state.data.places;
        const from = $('from');
        const to = $('to');
        from.innerHTML = '';
        to.innerHTML = '';
        option(from, '', t('chooseStart'));
        option(to, '', t('chooseDest'));

        if (places.checkpoints.length) {
            const qr = group(from, t('qr'));
            places.checkpoints.forEach((item) => option(from, item.place, `${item.label || item.checkpoint_code} (${item.floor_code})`, qr));
        }
        if (places.units.length) {
            const shopsFrom = group(from, t('shops'));
            const shopsTo = group(to, t('shops'));
            places.units.forEach((unit) => {
                option(from, unit.place, shopLabel(unit), shopsFrom);
                option(to, unit.place, shopLabel(unit), shopsTo);
            });
        }
        if (places.facility_types.length) {
            const nearest = group(to, t('nearest'));
            places.facility_types.forEach((item) => option(to, item.place, `${t('nearest')}: ${typeName(item.type)}`, nearest));
        }
        const nodes = (places.end_points || []).filter((item) => (item.kind || 'node') === 'node');
        if (nodes.length) {
            const pointsTo = group(to, t('points'));
            nodes.forEach((item) => option(to, item.place, `${item.name} (${item.floor_code})`, pointsTo));
        }
        if (places.facilities.length) {
            const facilities = group(to, t('facilities'));
            places.facilities.forEach((item) => option(to, item.place, `${item.name} (${item.floor_code})`, facilities));
        }

        if (config.from) setSelect(from, config.from);
        if (config.to.length) setSelect(to, config.to[0]);
        $('step-free').checked = !!config.stepFree;
    }

    function translateChrome() {
        document.querySelectorAll('[data-t]').forEach((element) => { element.textContent = t(element.dataset.t); });
        $('steps').dataset.empty = t('pick');
    }

    // ---- route ------------------------------------------------------------
    async function askRoute(options = {}) {
        const from = $('from').value;
        const to = $('to').value;
        if (!from || !to) {
            showSummary(t('pick'));
            return;
        }
        const stops = !options.quiet && config.to.length > 1 && to === config.to[0] ? config.to : [to];
        $('go').disabled = true;
        try {
            const route = await call(config.routeUrl, { from, to: stops, step_free: $('step-free').checked ? 1 : 0, cost: config.cost });
            const changed = options.quiet && state.route && JSON.stringify(route.path) !== JSON.stringify(state.route.path);
            if (options.quiet && !changed) return;
            state.route = route;
            if (!options.quiet) setFloor(route.path[0].floor_code, false);
            render();
            showRoute(changed);
            showArButton();
            post('route', { total: route.total, floors: route.floors, from: route.from, to: route.to, step_free: route.step_free });
            scheduleRefresh();
        } catch (error) {
            if (options.quiet && error.reason !== 'unreachable' && error.reason !== 'session') return;
            state.route = null;
            showArButton();
            render();
            showSummary(error.message, true);
            $('steps').innerHTML = '';
            post('error', { reason: error.reason, message: error.message });
        } finally {
            $('go').disabled = false;
        }
    }

    // ---- AR directions (ISM-65) ---------------------------------------------
    // WebXR AR runs in Chrome on Android only, and not inside a frame of
    // another site, so the AR page opens in its own tab from a signed link.
    async function checkAr() {
        if (!window.isSecureContext || !navigator.xr) return false;
        try {
            return await navigator.xr.isSessionSupported('immersive-ar');
        } catch (error) {
            // The host page did not allow xr-spatial-tracking for this frame: the AR page checks again.
            return /Android/i.test(navigator.userAgent);
        }
    }

    function showArButton() {
        $('ar').hidden = !(state.ar && state.route && $('to').value);
    }

    async function openAr() {
        const to = $('to').value;
        if (!to) return;
        // Open the tab during the tap, so it is not taken for a pop-up, then send it to the link.
        const tab = window.open('', '_blank');
        $('ar').disabled = true;
        try {
            const link = await call(config.arLinkUrl, { to, from: $('from').value, step_free: $('step-free').checked ? 1 : 0, cost: config.cost, lang: config.lang });
            if (tab) {
                tab.location.href = link.url;
            } else {
                const summary = $('summary');
                summary.textContent = '';
                const anchor = document.createElement('a');
                anchor.href = link.url;
                anchor.target = '_blank';
                anchor.rel = 'noopener';
                anchor.textContent = t('openAr');
                summary.appendChild(anchor);
            }
        } catch (error) {
            if (tab) tab.close();
            showSummary(error.message, true);
        } finally {
            $('ar').disabled = false;
        }
    }

    function scheduleRefresh() {
        clearInterval(state.refresh);
        state.refresh = setInterval(() => {
            if (document.visibilityState === 'visible' && state.route) askRoute({ quiet: true });
        }, 60000);
    }

    function showSummary(text, isError = false) {
        const summary = $('summary');
        summary.textContent = text;
        summary.classList.toggle('error', isError);
    }

    function floorName(code) {
        return state.data.floors.find((floor) => floor.floor_code === code)?.name ?? code;
    }

    function showRoute(changed) {
        const route = state.route;
        const floors = route.floors.map(floorName).join(' → ');
        showSummary(`${changed ? t('updated') + ' ' : ''}${route.total.minutes} ${t('min')} · ${Math.round(route.total.length_m)} ${t('m')} · ${floors}`);
        const list = $('steps');
        list.innerHTML = '';
        route.steps.forEach((step) => {
            const item = document.createElement('li');
            item.className = step.type;
            item.textContent = step.instruction;
            if (step.length_m > 0) {
                const meta = document.createElement('div');
                meta.className = 'meta';
                meta.textContent = `${Math.round(step.length_m)} ${t('m')} · ${Math.round(step.seconds)} ${t('s')} · ${t('floor')} ${step.floor_code}`;
                item.appendChild(meta);
            }
            item.addEventListener('click', () => {
                setFloor(step.floor_code, false);
                render();
                const node = state.nodes.get(step.node_code);
                if (node) centreOn(node.x, -node.y);
            });
            list.appendChild(item);
        });
        renderFloorTabs();
    }

    // ---- drawing ----------------------------------------------------------
    function element(name, attributes, parent) {
        const node = document.createElementNS(SVG, name);
        for (const [key, value] of Object.entries(attributes)) node.setAttribute(key, value);
        if (parent) parent.appendChild(node);
        return node;
    }

    function floorBounds(code) {
        let minX = Infinity; let minY = Infinity; let maxX = -Infinity; let maxY = -Infinity;
        const grow = (x, y) => { minX = Math.min(minX, x); maxX = Math.max(maxX, x); minY = Math.min(minY, y); maxY = Math.max(maxY, y); };
        state.data.nodes.filter((node) => node.floor === code).forEach((node) => grow(node.x, node.y));
        const plan = state.data.floors.find((floor) => floor.floor_code === code)?.plan;
        if (plan) {
            grow(plan.world_origin_x_m, plan.world_origin_y_m);
            grow(plan.world_origin_x_m + plan.width_px * plan.metres_per_pixel, plan.world_origin_y_m - plan.height_px * plan.metres_per_pixel);
        }
        if (!isFinite(minX)) return { x: -10, y: -10, w: 20, h: 20 };
        const pad = Math.max(2, 0.06 * Math.max(maxX - minX, maxY - minY));
        return { x: minX - pad, y: -maxY - pad, w: maxX - minX + 2 * pad, h: maxY - minY + 2 * pad };
    }

    function applyView() {
        const v = state.view;
        $('map').setAttribute('viewBox', `${v.x} ${v.y} ${v.w} ${v.h}`);
    }

    function fit() {
        state.view = floorBounds(state.floor);
        applyView();
    }

    function centreOn(x, y) {
        state.view = { ...state.view, x: x - state.view.w / 2, y: y - state.view.h / 2 };
        applyView();
    }

    function setFloor(code, redraw = true) {
        if (!state.data.floors.some((floor) => floor.floor_code === code)) return;
        const changed = state.floor !== code;
        state.floor = code;
        if (changed) {
            fit();
            post('floor', { floor: code });
        }
        if (redraw) render();
    }

    function renderFloorTabs() {
        const tabs = $('floors');
        tabs.innerHTML = '';
        state.data.floors.forEach((floor) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.role = 'tab';
            button.textContent = floor.name;
            button.setAttribute('aria-selected', String(floor.floor_code === state.floor));
            if (state.route && state.route.floors.includes(floor.floor_code)) button.classList.add('on-route');
            button.addEventListener('click', () => setFloor(floor.floor_code));
            tabs.appendChild(button);
        });
    }

    function render() {
        const svg = $('map');
        [...svg.childNodes].forEach((child) => { if (child.nodeName !== 'title') svg.removeChild(child); });
        const code = state.floor;
        const layer = element('g', {}, svg);

        const plan = state.data.floors.find((floor) => floor.floor_code === code)?.plan;
        if (plan && plan.url) {
            element('image', {
                href: plan.url,
                x: plan.world_origin_x_m,
                y: -plan.world_origin_y_m,
                width: plan.width_px * plan.metres_per_pixel,
                height: plan.height_px * plan.metres_per_pixel,
                preserveAspectRatio: 'none',
                opacity: 0.9,
            }, layer);
        }

        state.data.edges.filter((edge) => edge[2] === code).forEach(([a, b, , status]) => {
            const from = state.nodes.get(a);
            const to = state.nodes.get(b);
            if (from && to) element('line', { x1: from.x, y1: -from.y, x2: to.x, y2: -to.y, class: 'walk' + (status === 'closed' ? ' closed' : '') }, layer);
        });

        const route = state.route;
        if (route) {
            let run = [];
            const flush = () => {
                if (run.length > 1) element('polyline', { points: run.map((point) => `${point.position.x},${-point.position.y}`).join(' '), class: 'route' }, layer);
                run = [];
            };
            route.path.forEach((point) => {
                if (point.floor_code === code) run.push(point);
                else flush();
            });
            flush();
        }

        state.data.nodes.filter((node) => node.floor === code && node.type !== 'junction').forEach((node) => {
            element('circle', { cx: node.x, cy: -node.y, r: node.type === 'checkpoint' ? 0.45 : 0.35, class: 'place ' + node.type }, layer);
            const label = node.type === 'checkpoint' ? 'QR' : (state.data.labels[node.code] ?? null);
            if (label) element('text', { x: node.x + 0.6, y: -node.y + 0.3, class: 'label' }, layer).textContent = label;
        });

        if (!route) {
            const start = startNode();
            if (start && start.floor === code) {
                element('circle', { cx: start.x, cy: -start.y, r: 0.8, class: 'marker-start' }, layer);
                element('text', { x: start.x + 1, y: -start.y - 0.8, class: 'label' }, layer).textContent = t('youAreHere');
            }
        }

        if (route) {
            const first = route.path[0];
            const last = route.path[route.path.length - 1];
            if (first.floor_code === code) {
                element('circle', { cx: first.position.x, cy: -first.position.y, r: 0.8, class: 'marker-start' }, layer);
                element('text', { x: first.position.x + 1, y: -first.position.y - 0.8, class: 'label' }, layer).textContent = t('youAreHere');
            }
            if (last.floor_code === code) element('circle', { cx: last.position.x, cy: -last.position.y, r: 0.8, class: 'marker-end' }, layer);
            route.steps.filter((step) => step.type === 'connector' && step.floor_code === code).forEach((step) => {
                const node = state.nodes.get(step.node_code);
                if (!node) return;
                const arrow = route.path.findIndex((point) => point.node_code === step.node_code);
                const up = arrow >= 0 && route.path[arrow + 1] && route.path[arrow + 1].position.z > route.path[arrow].position.z;
                const hop = element('text', { x: node.x + 0.8, y: -node.y - 0.6, class: 'hop', role: 'button', tabindex: 0 }, layer);
                hop.textContent = `${up ? '↑' : '↓'} ${floorName(step.to_floor_code)}`;
                const go = () => setFloor(step.to_floor_code);
                hop.addEventListener('click', go);
                hop.addEventListener('keydown', (event) => { if (event.key === 'Enter') go(); });
            });
        }
        renderFloorTabs();
    }

    // ---- pan and zoom -----------------------------------------------------
    function svgPoint(event) {
        const box = $('map').getBoundingClientRect();
        return { x: state.view.x + ((event.clientX - box.left) / box.width) * state.view.w, y: state.view.y + ((event.clientY - box.top) / box.height) * state.view.h };
    }

    function zoom(factor, centre) {
        const c = centre || { x: state.view.x + state.view.w / 2, y: state.view.y + state.view.h / 2 };
        const w = Math.min(2000, Math.max(3, state.view.w * factor));
        const h = state.view.h * (w / state.view.w);
        state.view = { x: c.x - (c.x - state.view.x) * (w / state.view.w), y: c.y - (c.y - state.view.y) * (h / state.view.h), w, h };
        applyView();
    }

    function bindPanZoom() {
        const svg = $('map');
        svg.addEventListener('wheel', (event) => { event.preventDefault(); zoom(event.deltaY > 0 ? 1.15 : 1 / 1.15, svgPoint(event)); }, { passive: false });
        svg.addEventListener('pointerdown', (event) => {
            svg.setPointerCapture(event.pointerId);
            state.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            state.drag = { x: event.clientX, y: event.clientY, view: { ...state.view } };
        });
        svg.addEventListener('pointermove', (event) => {
            if (!state.pointers.has(event.pointerId)) return;
            const before = [...state.pointers.values()];
            state.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            const box = svg.getBoundingClientRect();
            if (state.pointers.size === 2) {
                const after = [...state.pointers.values()];
                const distance = (points) => Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
                if (before.length === 2 && distance(after) > 0) zoom(distance(before) / distance(after));
                return;
            }
            if (state.drag) {
                const dx = ((event.clientX - state.drag.x) / box.width) * state.drag.view.w;
                const dy = ((event.clientY - state.drag.y) / box.height) * state.drag.view.h;
                state.view = { ...state.drag.view, x: state.drag.view.x - dx, y: state.drag.view.y - dy };
                applyView();
            }
        });
        const end = (event) => { state.pointers.delete(event.pointerId); state.drag = null; };
        svg.addEventListener('pointerup', end);
        svg.addEventListener('pointercancel', end);
        svg.addEventListener('keydown', (event) => {
            const step = state.view.w * 0.1;
            const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
            if (moves[event.key]) {
                event.preventDefault();
                state.view = { ...state.view, x: state.view.x + moves[event.key][0], y: state.view.y + moves[event.key][1] };
                applyView();
            } else if (event.key === '+' || event.key === '=') zoom(1 / 1.2);
            else if (event.key === '-') zoom(1.2);
        });
        $('zoom-in').addEventListener('click', () => zoom(1 / 1.3));
        $('zoom-out').addEventListener('click', () => zoom(1.3));
        $('zoom-fit').addEventListener('click', fit);
    }

    // The floor of the start place, so a scanned QR code opens where the visitor stands.
    function startFloor() {
        const places = state.data.places;
        const from = config.from || '';
        const match = [...places.checkpoints, ...places.units, ...places.facilities, ...(places.end_points || [])].find((item) => item.place === from);
        if (match) return match.floor_code;
        if (from.startsWith('node:')) return state.nodes.get(from.slice(5))?.floor ?? null;
        return null;
    }

    // The node of the chosen start when it is a QR code or a graph node.
    function startNode() {
        const from = $('from').value || '';
        if (from.startsWith('node:')) return state.nodes.get(from.slice(5)) ?? null;
        const checkpoint = state.data.places.checkpoints.find((item) => item.place === from);
        return checkpoint ? state.nodes.get(checkpoint.node_code) ?? null : null;
    }

    // ---- start ------------------------------------------------------------
    async function start() {
        translateChrome();
        showSummary(t('loading'));
        try {
            state.data = await call(config.dataUrl);
        } catch (error) {
            showSummary(error.message, true);
            post('error', { reason: error.reason, message: error.message });
            return;
        }
        state.data.nodes.forEach((node) => state.nodes.set(node.code, node));
        state.data.signs = state.data.signs || {};
        state.data.labels = state.data.labels || {};
        fillForm();
        bindPanZoom();
        setFloor(startFloor() ?? state.data.floors[0].floor_code, false);
        render();
        $('ask').addEventListener('submit', (event) => { event.preventDefault(); askRoute(); });
        $('from').addEventListener('change', () => { if (!state.route) render(); });
        $('to').addEventListener('change', showArButton);
        $('ar').addEventListener('click', openAr);
        checkAr().then((supported) => { state.ar = supported; showArButton(); });
        $('app').setAttribute('aria-busy', 'false');
        post('ready', { floors: state.data.floors.map((floor) => floor.floor_code), name: state.data.map.name });
        if ($('from').value && $('to').value) askRoute();
        else showSummary(t('pick'));
    }

    start();
})();
