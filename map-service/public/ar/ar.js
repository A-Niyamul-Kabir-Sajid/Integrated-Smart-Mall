/**
 * AR directions of the ISM map service (ISM-65, decision D-023): WebXR
 * immersive-ar in Chrome on Android. The visitor finds the floor, lines the
 * phone up with a QR code of the mall (the camera sees the code, or the phone
 * is held flat against it), then follows arrows on the floor to the place
 * chosen on the embedded map. After a lift or escalator the visitor lines up
 * again at a code of the new floor. Opened in its own tab from a signed link;
 * talks only to this service, with the page's session token.
 *
 * Add #preview to the address for a walk along the route without a camera
 * (for checks on a computer).
 */
import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js';
import * as M from './ar-math.js';
import { CodeScanner } from './code-scanner.js';
import { yawToRotationY } from '../viewer/annotations.js';

const config = JSON.parse(document.getElementById('ar-config').textContent);
const $ = (id) => document.getElementById(id);
const PREVIEW = location.hash === '#preview';

const SIGHTINGS = 6;
const MAX_SPREAD_DEG = 4;
const MAX_SPREAD_M = 0.06;
const MAX_CODE_DISTANCE_M = 1.2;
const MAX_FIT_PX = 2.5;
const MIN_AMBIGUITY = 1.5;
const ARRIVE_M = 2;
const OFF_ROUTE_M = 4;
const OFF_ROUTE_MS = 3000;
const CONNECTOR_M = 3;
const WALK_MPS = 1.2;
const EYE_M = 1.55;

const ICONS = { ahead: '↑', left: '←', right: '→', 'bear-left': '↖', 'bear-right': '↗', back: '↶', up: '⇧', down: '⇩', arrive: '◎' };

const TEXT = {
    en: {
        title: 'AR directions', checking: 'Checking this phone…', loading: 'Loading the map…',
        ready: 'Ready. Stand by a QR code of the mall, then tap Start AR.', start: 'Start AR', again: 'Start AR again',
        preview: 'Preview without a camera', previewNote: 'Preview: a walk along the route without a camera. Arrow keys walk and turn, P pauses.',
        how1: 'Move the phone slowly over the floor until a ring appears.',
        how2: 'Point the camera at a QR code of the mall from about half a metre, or hold the phone flat against the code and tap Align.',
        how3: 'Follow the arrows on the floor. Line up again at any QR code if the arrows drift.',
        needs: 'AR needs Chrome on an Android phone with Google Play Services for AR. On other phones, use the map.',
        back: 'Back to the map', closeTab: 'You can close this tab now.',
        insecure: 'AR needs a secure (https) address. Use the map instead.',
        noXr: 'This browser cannot show AR. Use the map instead.',
        noAr: 'This phone cannot show AR here: it needs Chrome on Android with Google Play Services for AR. Use the map instead.',
        noDestination: 'No destination was chosen. Go back to the map and choose where to go.',
        expired: 'This AR page has expired. Go back to the map and tap View in AR again.',
        failed: 'AR could not start: {reason}', ended: 'AR ended.',
        toPlace: 'To {place}', tripFrom: '{metres} m · {minutes} min from {place}',
        floorStep: 'Move the phone slowly over the floor until the ring appears.', floorFound: 'Floor found.', skip: 'Skip',
        alignCamera: 'Point the camera at a QR code of the mall from about half a metre, a little to one side.',
        alignTouch: 'Or hold the phone flat against the code (camera to the code), choose it and tap Align.',
        alignTouchOnly: 'Hold the phone flat against a QR code of the mall (camera to the code), choose the code and tap Align.',
        alignFloor: 'On {floor}: line up at a QR code there.',
        whichCode: 'QR code', align: 'Align', keep: 'Keep the current line-up', continueFloor: 'Continue without lining up',
        closer: 'Come closer to the code (about half a metre).', holdStill: 'Hold still… {count} of {needed}',
        unknownCode: 'This QR code is not on this map.',
        aligned: 'Lined up with {code}. The blue frame should sit on the printed code.',
        upright: 'Hold the phone upright, flat against the code.',
        noRoute: 'No route: {reason}',
        realign: 'Re-align', details: 'Details', exit: 'Exit',
        left: '{metres} m · {minutes} min left', inMetres: 'in {metres} m', now: 'now',
        offRoute: 'You are off the route.', newRoute: 'New route from here',
        lost: 'Tracking lost. Move the phone slowly and point it at the floor.',
        behind: '↶ The route is behind you', toLeft: '← The route is to your left', toRight: 'The route is to your right →',
        connectorThen: 'Then line up at a QR code on {floor}.', onFloor: 'I am on {floor}',
        arrived: 'You have arrived at {place}.', shop: 'Shop', nearest: 'Nearest',
        types: { toilet: 'toilet', prayer_room: 'prayer room', atm: 'ATM', exit: 'exit', information: 'information desk', baby_care: 'baby care', first_aid: 'first aid', parking: 'parking' },
    },
    bn: {
        title: 'এআর দিকনির্দেশনা', checking: 'ফোনটি যাচাই করা হচ্ছে…', loading: 'ম্যাপ লোড হচ্ছে…',
        ready: 'প্রস্তুত। মলের একটি কিউআর কোডের কাছে দাঁড়িয়ে "এআর শুরু করুন" চাপুন।', start: 'এআর শুরু করুন', again: 'আবার এআর শুরু করুন',
        preview: 'ক্যামেরা ছাড়া দেখুন', previewNote: 'প্রিভিউ: ক্যামেরা ছাড়া পথ ধরে হাঁটা। তীর-চাবি দিয়ে হাঁটুন ও ঘুরুন, P চাপলে থামবে।',
        how1: 'মেঝের উপর দিয়ে ফোনটি ধীরে ধীরে নাড়ান, যতক্ষণ না একটি বৃত্ত দেখা যায়।',
        how2: 'প্রায় আধা মিটার দূর থেকে মলের একটি কিউআর কোডের দিকে ক্যামেরা ধরুন, অথবা ফোনটি কোডের উপর সমানভাবে চেপে ধরে "মেলান" চাপুন।',
        how3: 'মেঝের তীরচিহ্ন অনুসরণ করুন। তীরচিহ্ন সরে গেলে যেকোনো কিউআর কোডে আবার মিলিয়ে নিন।',
        needs: 'এআর-এর জন্য "গুগল প্লে সার্ভিসেস ফর এআর" সহ অ্যান্ড্রয়েড ফোনে ক্রোম লাগবে। অন্য ফোনে ম্যাপ ব্যবহার করুন।',
        back: 'ম্যাপে ফিরে যান', closeTab: 'এখন এই ট্যাবটি বন্ধ করতে পারেন।',
        insecure: 'এআর-এর জন্য নিরাপদ (https) ঠিকানা লাগবে। ম্যাপ ব্যবহার করুন।',
        noXr: 'এই ব্রাউজারে এআর দেখানো যায় না। ম্যাপ ব্যবহার করুন।',
        noAr: 'এই ফোনে এখানে এআর দেখানো যাচ্ছে না: "গুগল প্লে সার্ভিসেস ফর এআর" সহ অ্যান্ড্রয়েডে ক্রোম লাগবে। ম্যাপ ব্যবহার করুন।',
        noDestination: 'কোনো গন্তব্য বেছে নেওয়া হয়নি। ম্যাপে ফিরে গিয়ে কোথায় যাবেন তা বেছে নিন।',
        expired: 'এই এআর পাতার মেয়াদ শেষ। ম্যাপে ফিরে গিয়ে আবার "এআর-এ দেখুন" চাপুন।',
        failed: 'এআর চালু হয়নি: {reason}', ended: 'এআর বন্ধ হয়েছে।',
        toPlace: 'গন্তব্য: {place}', tripFrom: '{place} থেকে {metres} মিটার · {minutes} মিনিট',
        floorStep: 'মেঝের উপর দিয়ে ফোনটি ধীরে ধীরে নাড়ান, যতক্ষণ না বৃত্তটি দেখা যায়।', floorFound: 'মেঝে পাওয়া গেছে।', skip: 'বাদ দিন',
        alignCamera: 'প্রায় আধা মিটার দূর থেকে, একটু পাশ থেকে, মলের একটি কিউআর কোডের দিকে ক্যামেরা ধরুন।',
        alignTouch: 'অথবা ফোনটি কোডের উপর সমানভাবে চেপে ধরুন (ক্যামেরা কোডের দিকে), কোডটি বেছে নিয়ে "মেলান" চাপুন।',
        alignTouchOnly: 'ফোনটি মলের একটি কিউআর কোডের উপর সমানভাবে চেপে ধরুন (ক্যামেরা কোডের দিকে), কোডটি বেছে নিয়ে "মেলান" চাপুন।',
        alignFloor: '{floor}-এ: সেখানকার একটি কিউআর কোডে মিলিয়ে নিন।',
        whichCode: 'কিউআর কোড', align: 'মেলান', keep: 'আগের মতো রাখুন', continueFloor: 'না মিলিয়ে চালিয়ে যান',
        closer: 'কোডের আরও কাছে আসুন (প্রায় আধা মিটার)।', holdStill: 'স্থির রাখুন… {needed}-এর মধ্যে {count}',
        unknownCode: 'এই কিউআর কোডটি এই ম্যাপে নেই।',
        aligned: '{code}-এর সাথে মেলানো হয়েছে। নীল ফ্রেমটি ছাপানো কোডের উপর থাকা উচিত।',
        upright: 'ফোনটি সোজা করে কোডের উপর সমানভাবে চেপে ধরুন।',
        noRoute: 'পথ পাওয়া যায়নি: {reason}',
        realign: 'আবার মেলান', details: 'বিস্তারিত', exit: 'বের হন',
        left: 'বাকি {metres} মিটার · {minutes} মিনিট', inMetres: '{metres} মিটার পরে', now: 'এখন',
        offRoute: 'আপনি পথের বাইরে চলে গেছেন।', newRoute: 'এখান থেকে নতুন পথ',
        lost: 'অবস্থান হারিয়ে গেছে। ফোনটি ধীরে নাড়ান এবং মেঝের দিকে ধরুন।',
        behind: '↶ পথটি আপনার পেছনে', toLeft: '← পথটি আপনার বাঁ দিকে', toRight: 'পথটি আপনার ডান দিকে →',
        connectorThen: 'তারপর {floor}-এ একটি কিউআর কোডে মিলিয়ে নিন।', onFloor: 'আমি {floor}-এ আছি',
        arrived: 'আপনি {place}-এ পৌঁছে গেছেন।', shop: 'দোকান', nearest: 'সবচেয়ে কাছের',
        types: { toilet: 'টয়লেট', prayer_room: 'নামাজের ঘর', atm: 'এটিএম', exit: 'বের হওয়ার পথ', information: 'তথ্য কেন্দ্র', baby_care: 'শিশু যত্ন কক্ষ', first_aid: 'প্রাথমিক চিকিৎসা', parking: 'পার্কিং' },
    },
};
const words = TEXT[config.lang] || TEXT.en;
const t = (key, values = {}) => String(words[key] ?? TEXT.en[key] ?? key).replace(/\{(\w+)\}/g, (match, name) => (name in values ? values[name] : match));

const state = {
    phase: 'intro',
    data: null,
    nodes: new Map(),
    codes: [],
    codesBySlug: new Map(),
    floors: new Map(),
    destination: '',
    route: null,
    lengths: null,
    starts: null,
    where: null,
    floor: null,
    expectFloor: null,
    connectorStep: null,
    alignment: null,
    generation: 0,
    floorY: null,
    floorYFloor: null,
    floorHits: [],
    sightings: [],
    touchPending: null,
    viewer: null,
    viewerLocal: new THREE.Vector3(),
    offSince: null,
    banner: null,
    bannerAction: null,
    notices: new Map(),
    session: null,
    hitSource: null,
    scanner: null,
    cameraSeen: false,
    anchor: null,
    anchorFromWorld: null,
    anchorPending: null,
    walker: null,
    keys: new Set(),
    last: 0,
    lastUpdate: 0,
    phaseSince: 0,
    goal: null,
};

// ---- three.js ------------------------------------------------------------------

const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setPixelRatio(Math.min(2, window.devicePixelRatio || 1));
renderer.setSize(window.innerWidth, window.innerHeight);
renderer.xr.enabled = true;
renderer.domElement.className = 'scene';
document.body.prepend(renderer.domElement);

const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(65, window.innerWidth / window.innerHeight, 0.05, 150);
scene.add(new THREE.HemisphereLight(0xffffff, 0x334155, 1.5));

// The mall, put in place by the line-up: its children are in mall coordinates as three = (x, z, -y).
const world = new THREE.Group();
world.matrixAutoUpdate = false;
world.visible = false;
scene.add(world);
const routeLayer = new THREE.Group();
const codeLayer = new THREE.Group();
const previewLayer = new THREE.Group();
previewLayer.visible = false;
world.add(routeLayer, codeLayer, previewLayer);

const reticle = new THREE.Mesh(new THREE.RingGeometry(0.12, 0.16, 40).rotateX(-Math.PI / 2), new THREE.MeshBasicMaterial({ color: 0x38bdf8 }));
reticle.matrixAutoUpdate = false;
reticle.visible = false;
scene.add(reticle);

const COLOURS = { route: 0x38bdf8, ribbon: 0x0ea5e9, goal: 0xf43f5e, code: 0x38bdf8, otherCode: 0x94a3b8 };
const chevronGeometry = new THREE.ShapeGeometry(new THREE.Shape([[0.2, 0], [-0.1, 0.24], [-0.22, 0.24], [0.06, 0], [-0.22, -0.24], [-0.1, -0.24]].map(([x, y]) => new THREE.Vector2(x, y)))).rotateX(-Math.PI / 2);
const frameTexture = squareFrameTexture();
const inverse = new THREE.Matrix4();

window.addEventListener('resize', () => {
    if (renderer.xr.isPresenting) return;
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
});

function squareFrameTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 128;
    canvas.height = 128;
    const context = canvas.getContext('2d');
    context.strokeStyle = '#ffffff';
    context.lineWidth = 12;
    context.strokeRect(6, 6, 116, 116);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    return texture;
}

/** A label that always faces the camera; `height` in metres. */
function label(text, { height = 0.3, background = 'rgba(11, 18, 32, 0.88)', colour = '#ffffff' } = {}) {
    const canvas = document.createElement('canvas');
    const font = '600 56px system-ui, "Noto Sans", "Noto Sans Bengali", sans-serif';
    const context = canvas.getContext('2d');
    context.font = font;
    canvas.width = Math.min(2048, Math.ceil(context.measureText(text).width) + 72);
    canvas.height = 96;
    context.font = font;
    context.fillStyle = background;
    if (context.roundRect) {
        context.beginPath();
        context.roundRect(0, 0, canvas.width, canvas.height, 28);
        context.fill();
    } else {
        context.fillRect(0, 0, canvas.width, canvas.height);
    }
    context.fillStyle = colour;
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText(text, canvas.width / 2, canvas.height / 2 + 2);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: texture, transparent: true, depthWrite: false }));
    sprite.scale.set((height * canvas.width) / canvas.height, height, 1);
    sprite.renderOrder = 10;
    return sprite;
}

function clear(group) {
    for (const child of [...group.children]) {
        child.traverse((item) => {
            if (item.geometry && item.geometry !== chevronGeometry) item.geometry.dispose();
            if (item.material) {
                if (item.material.map && item.material.map !== frameTexture) item.material.map.dispose();
                item.material.dispose();
            }
        });
        group.remove(child);
    }
}

const three = (point) => new THREE.Vector3(point.x, point.z, -point.y);

// ---- data -----------------------------------------------------------------------

async function call(url, params) {
    const target = new URL(url, location.href);
    for (const [name, value] of Object.entries(params || {})) {
        if (Array.isArray(value)) value.forEach((item) => target.searchParams.append(name + '[]', item));
        else if (value !== null && value !== undefined && value !== '') target.searchParams.set(name, value);
    }
    const response = await fetch(target, { headers: { Accept: 'application/json', 'X-Embed-Token': config.token }, credentials: 'omit' });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(response.status === 401 ? t('expired') : body.message || response.statusText);
        error.reason = body.reason || 'http_' + response.status;
        throw error;
    }
    return body.data;
}

async function loadData() {
    state.data = await call(config.dataUrl);
    state.data.labels = state.data.labels || {};
    state.data.signs = state.data.signs || {};
    state.data.nodes.forEach((node) => state.nodes.set(node.code, node));
    state.data.floors.forEach((floor) => state.floors.set(floor.floor_code, floor));
    state.codes = state.data.qr_codes || [];
    state.codes.forEach((code) => state.codesBySlug.set(code.slug, code));
    state.destination = placeLabel(config.to);
}

function floorName(code) {
    return state.floors.get(code)?.name ?? code;
}

function floorElevation(code) {
    return Number(state.floors.get(code)?.elevation_m ?? 0);
}

/** A readable name for a place written kind:value. */
function placeLabel(place) {
    const [kind, ...rest] = String(place || '').split(':');
    const value = rest.join(':');
    const places = state.data?.places || {};
    switch (kind) {
        case 'unit': {
            const sign = state.data.signs[value];
            return sign ? `${sign} (${value})` : `${t('shop')} ${value}`;
        }
        case 'facility':
            return value;
        case 'facility-type':
            return `${t('nearest')} ${words.types?.[value] ?? TEXT.en.types[value] ?? value.replace(/_/g, ' ')}`;
        case 'point':
            return (places.end_points || []).find((item) => item.key === value)?.name ?? value;
        case 'checkpoint': {
            const code = state.codesBySlug.get(value);
            return code ? code.label || code.checkpoint_code : value;
        }
        case 'node':
            return state.data.labels[value] ?? value;
        default:
            return place || '';
    }
}

// ---- before AR starts -------------------------------------------------------------

function translate() {
    document.querySelectorAll('[data-t]').forEach((element) => { element.textContent = t(element.dataset.t); });
}

function status(text, isError = false) {
    $('status').textContent = text;
    $('status').classList.toggle('error', isError);
}

async function arSupport() {
    if (!window.isSecureContext) return { ok: false, reason: 'insecure' };
    if (!navigator.xr) return { ok: false, reason: 'noXr' };
    try {
        return (await navigator.xr.isSessionSupported('immersive-ar')) ? { ok: true } : { ok: false, reason: 'noAr' };
    } catch {
        return { ok: false, reason: 'noAr' };
    }
}

async function tripSummary() {
    if (!config.from) return;
    try {
        const route = await call(config.routeUrl, { from: config.from, to: [config.to], step_free: config.stepFree ? 1 : 0, cost: config.cost });
        $('trip').textContent = t('tripFrom', { metres: Math.round(route.total.length_m), minutes: route.total.minutes, place: route.from.label });
        state.destination = route.to[route.to.length - 1]?.label ?? state.destination;
        $('destination').textContent = t('toPlace', { place: state.destination });
    } catch (error) {
        $('trip').textContent = t('noRoute', { reason: error.message });
    }
}

function closePage() {
    window.close();
    setTimeout(() => status(t('closeTab')), 300);
}

// ---- the AR session -----------------------------------------------------------------

async function startAr() {
    $('start').disabled = true;
    $('overlay').hidden = false;
    try {
        const session = await navigator.xr.requestSession('immersive-ar', {
            requiredFeatures: ['local'],
            optionalFeatures: ['hit-test', 'dom-overlay', 'anchors', 'camera-access'],
            domOverlay: { root: $('overlay') },
        });
        await beginSession(session);
    } catch (error) {
        $('overlay').hidden = true;
        status(t('failed', { reason: error.message || error.name }), true);
    } finally {
        $('start').disabled = false;
    }
}

async function beginSession(session) {
    state.session = session;
    session.addEventListener('end', sessionEnded);
    renderer.xr.setReferenceSpaceType('local');
    await renderer.xr.setSession(session);
    try {
        const viewerSpace = await session.requestReferenceSpace('viewer');
        state.hitSource = await session.requestHitTestSource({ space: viewerSpace });
    } catch {
        state.hitSource = null;
    }
    state.scanner = null;
    const features = session.enabledFeatures;
    if ((!features || features.includes('camera-access')) && (await CodeScanner.supported())) {
        try {
            state.scanner = new CodeScanner(renderer, session, onCodes);
        } catch {
            state.scanner = null;
        }
    }
    $('intro').hidden = true;
    resetGuidance();
    setPhase(state.hitSource ? 'floor' : 'align');
    renderer.setAnimationLoop(frameLoop);
}

function sessionEnded() {
    renderer.setAnimationLoop(null);
    state.hitSource?.cancel?.();
    state.hitSource = null;
    state.scanner = null;
    state.session = null;
    dropAnchor();
    reticle.visible = false;
    $('overlay').hidden = true;
    $('intro').hidden = false;
    $('start').textContent = t('again');
    status(t('ended'));
    setPhase('intro');
}

function exitAr() {
    if (state.session) state.session.end();
    else if (state.walker) stopPreview();
}

function resetGuidance() {
    state.alignment = null;
    state.route = null;
    state.lengths = null;
    state.starts = null;
    state.where = null;
    state.floor = null;
    state.expectFloor = null;
    state.connectorStep = null;
    state.floorY = null;
    state.floorYFloor = null;
    state.floorHits = [];
    state.sightings = [];
    state.offSince = null;
    state.goal = null;
    world.visible = false;
    world.matrix.identity();
    world.matrixWorldNeedsUpdate = true;
    clear(routeLayer);
    clear(codeLayer);
    hideBanner();
    $('hint').hidden = true;
    $('details').hidden = true;
}

function frameLoop(time, frame) {
    const now = performance.now();
    if (frame) xrFrame(frame, now);
    else if (state.walker) previewFrame(now);
    if (now - state.lastUpdate > 100) {
        state.lastUpdate = now;
        update(now);
    }
    animate(time / 1000);
    renderer.render(scene, camera);
}

function xrFrame(frame, now) {
    const space = renderer.xr.getReferenceSpace();
    const pose = frame.getViewerPose(space);
    if (pose) {
        const m = pose.transform.matrix;
        state.viewer = { position: [m[12], m[13], m[14]], forward: [-m[8], -m[9], -m[10]], emulated: pose.emulatedPosition === true };
        if (pose.views[0]?.camera) state.cameraSeen = true;
    } else {
        state.viewer = null;
    }
    followAnchor(frame, space);
    if (state.hitSource && (state.phase === 'floor' || (state.phase === 'align' && state.floorY === null))) {
        findFloor(frame.getHitTestResults(state.hitSource), space, now);
    } else {
        reticle.visible = false;
    }
    if (state.scanner && (state.phase === 'align' || state.phase === 'guide')) {
        state.scanner.interval = state.phase === 'align' ? 300 : 1000;
        state.scanner.capture(frame, space, now);
    }
    if (state.touchPending && state.viewer) {
        const code = state.touchPending;
        state.touchPending = null;
        const heading = M.headingOf(state.viewer.forward) + Math.PI;
        if (M.alignByTouch(state.viewer.position, state.viewer.forward, code.qr) === null) {
            toast(t('upright'));
        } else {
            lineUp(code, state.viewer.position, heading, 'touch', { limited: state.viewer.emulated });
        }
    }
}

/** An anchor at the code keeps the mall in place when the phone corrects its map of the room. */
function followAnchor(frame, space) {
    if (state.anchorPending && typeof frame.createAnchor === 'function' && typeof XRRigidTransform !== 'undefined') {
        const { centre, heading, generation } = state.anchorPending;
        state.anchorPending = null;
        const turn = new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 1, 0), heading);
        frame.createAnchor(new XRRigidTransform({ x: centre[0], y: centre[1], z: centre[2] }, { x: turn.x, y: turn.y, z: turn.z, w: turn.w }), space)
            .then((anchor) => {
                if (generation !== state.generation) {
                    anchor.delete?.();
                    return;
                }
                dropAnchor();
                state.anchor = anchor;
            })
            .catch(() => {});
    }
    if (!state.anchor || (frame.trackedAnchors && !frame.trackedAnchors.has(state.anchor))) return;
    const pose = frame.getPose(state.anchor.anchorSpace, space);
    if (!pose) return;
    const anchorMatrix = new THREE.Matrix4().fromArray(pose.transform.matrix);
    if (state.anchorFromWorld === null) {
        state.anchorFromWorld = anchorMatrix.clone().invert().multiply(world.matrix);
        return;
    }
    world.matrix.multiplyMatrices(anchorMatrix, state.anchorFromWorld);
    world.matrixWorldNeedsUpdate = true;
}

function dropAnchor() {
    state.anchor?.delete?.();
    state.anchor = null;
    state.anchorFromWorld = null;
}

/** Floor hits below the phone, steady for a moment, give the height of the floor. */
function findFloor(results, space, now) {
    const pose = results.length && state.viewer ? results[0].getPose(space) : null;
    if (!pose) {
        reticle.visible = false;
        return;
    }
    const m = pose.transform.matrix;
    if (m[5] < 0.9 || m[13] > state.viewer.position[1] - 0.5) {
        reticle.visible = false;
        return;
    }
    reticle.matrix.fromArray(m);
    reticle.matrixWorldNeedsUpdate = true;
    reticle.visible = state.phase === 'floor';
    state.floorHits = state.floorHits.filter((hit) => now - hit.at < 2000);
    state.floorHits.push({ y: m[13], at: now });
    if (state.floorHits.length < 12) return;
    const heights = state.floorHits.map((hit) => hit.y).sort((a, b) => a - b);
    if (heights[heights.length - 1] - heights[0] > 0.05) return;
    state.floorY = heights[Math.floor(heights.length / 2)];
    state.floorYFloor = state.expectFloor;
    state.floorHits = [];
    if (state.phase === 'floor') {
        toast(t('floorFound'));
        setPhase('align');
    }
}

// ---- lining up ---------------------------------------------------------------------------

/** Codes in one camera picture; returns whether one gave a good pose. */
function onCodes(found, snapshot) {
    if (state.phase !== 'align' && state.phase !== 'guide') return false;
    let good = false;
    for (const item of found) {
        const code = state.codesBySlug.get(M.slugFromCode(item.text));
        if (!code) {
            notice('unknown', t('unknownCode'));
            continue;
        }
        const pose = M.estimateCodePose(item.corners, snapshot.projection, snapshot.transform, code.qr.width);
        if (!pose) continue;
        const pixels = (pose.error * Math.min(snapshot.width, snapshot.height)) / 2;
        if (pixels > MAX_FIT_PX || pose.ambiguity < MIN_AMBIGUITY) continue;
        good = true;
        if (pose.distance > MAX_CODE_DISTANCE_M) {
            if (state.phase === 'align') notice('closer', t('closer'));
            continue;
        }
        addSighting(code, pose, pixels, snapshot.emulated);
    }
    return good;
}

function addSighting(code, pose, pixels, emulated) {
    const now = performance.now();
    if (state.sightings.length && state.sightings[0].code !== code) state.sightings = [];
    state.sightings.push({ code, centre: pose.centre, heading: pose.heading, distance: pose.distance, pixels, emulated, at: now });
    state.sightings = state.sightings.filter((sighting) => now - sighting.at < 3000).slice(-SIGHTINGS);
    if (state.sightings.length < SIGHTINGS) return;
    const average = M.averagePose(state.sightings);
    if (average.spreadDeg > MAX_SPREAD_DEG || average.spreadM > MAX_SPREAD_M) {
        state.sightings.shift();
        return;
    }
    const sightings = state.sightings;
    state.sightings = [];
    const mean = (key) => sightings.reduce((sum, sighting) => sum + sighting[key], 0) / sightings.length;
    lineUp(code, average.centre, average.heading, 'camera', {
        distance: mean('distance'),
        fit: mean('pixels'),
        spreadDeg: average.spreadDeg,
        spreadM: average.spreadM,
        limited: sightings.some((sighting) => sighting.emulated),
    });
}

/** Put the mall where the code was found, then ask for the route from that code. */
async function lineUp(code, centre, heading, method, extra = {}) {
    let alignment = M.alignAt(centre, heading, code.qr);
    const floorKnown = state.floorY !== null && (state.floorYFloor === null || state.floorYFloor === code.floor_code);
    if (floorKnown) {
        alignment = M.onFloor(alignment, state.floorY, floorElevation(code.floor_code));
        state.floorYFloor = code.floor_code;
    }
    state.generation++;
    state.alignment = { ...alignment, code, method, floorFrom: floorKnown ? 'the floor the camera found' : 'the height of the code', ...extra };
    state.floor = code.floor_code;
    state.expectFloor = null;
    state.sightings = [];
    world.matrix.makeRotationY(alignment.angle).setPosition(alignment.offset[0], alignment.offset[1], alignment.offset[2]);
    world.matrixWorldNeedsUpdate = true;
    dropAnchor();
    if (method !== 'preview') state.anchorPending = { centre: [...centre], heading, generation: state.generation };
    world.visible = true;
    buildCodes();
    showFloor();
    if (method !== 'preview') toast(t('aligned', { code: code.label || code.checkpoint_code }));
    await planRoute(`checkpoint:${code.slug}`);
}

function keepLineUp() {
    if (state.expectFloor !== null) {
        state.floor = state.expectFloor;
        state.expectFloor = null;
        world.visible = state.alignment !== null;
        showFloor();
    }
    setPhase(state.route ? 'guide' : 'align');
}

function preferredCode() {
    if (state.expectFloor !== null) {
        const arrival = state.connectorStep ? state.route?.path[state.connectorStep.index + 1] : null;
        const onFloor = state.codes.filter((code) => code.floor_code === state.expectFloor);
        if (onFloor.length) {
            const from = arrival?.position ?? onFloor[0].qr;
            return onFloor.reduce((best, code) => (Math.hypot(code.qr.x - from.x, code.qr.y - from.y) < Math.hypot(best.qr.x - from.x, best.qr.y - from.y) ? code : best)).slug;
        }
    }
    if (state.alignment) return state.alignment.code.slug;
    if (String(config.from || '').startsWith('checkpoint:')) return config.from.slice('checkpoint:'.length);
    return state.codes[0]?.slug ?? '';
}

function fillCodes() {
    const select = $('code');
    select.innerHTML = '';
    for (const floor of state.data.floors) {
        const codes = state.codes.filter((code) => code.floor_code === floor.floor_code);
        if (!codes.length) continue;
        const group = document.createElement('optgroup');
        group.label = floor.name ?? floor.floor_code;
        for (const code of codes) {
            const option = document.createElement('option');
            option.value = code.slug;
            option.textContent = code.label ? `${code.label} (${code.checkpoint_code})` : code.checkpoint_code;
            group.appendChild(option);
        }
        select.appendChild(group);
    }
    const preferred = preferredCode();
    if ([...select.options].some((option) => option.value === preferred)) select.value = preferred;
}

// ---- the route -----------------------------------------------------------------------------

async function planRoute(from) {
    try {
        const route = await call(config.routeUrl, { from, to: [config.to], step_free: config.stepFree ? 1 : 0, cost: config.cost });
        state.route = route;
        state.lengths = M.pathLengths(route.path);
        state.starts = M.stepStarts(route, state.lengths);
        state.destination = route.to[route.to.length - 1]?.label ?? state.destination;
        state.offSince = null;
        buildRoute();
        hideBanner();
        setPhase('guide');
    } catch (error) {
        setPhase('align');
        banner(t('noRoute', { reason: error.message }), null, null, 'route');
    }
}

function buildRoute() {
    clear(routeLayer);
    const path = state.route.path;
    for (const run of M.routeRuns(path)) {
        const group = new THREE.Group();
        group.userData.floor = run.floor;
        const points = path.slice(run.from, run.to + 1).map((point) => point.position);
        group.add(ribbon(points));
        M.placeAlong(points, 0.9, 0.6).forEach((place, index) => {
            const chevron = new THREE.Mesh(chevronGeometry, new THREE.MeshBasicMaterial({ color: COLOURS.route, transparent: true, opacity: 0.9, depthWrite: false, side: THREE.DoubleSide }));
            chevron.position.set(place.x, place.z + 0.03, -place.y);
            chevron.rotation.y = place.heading;
            chevron.userData.chevron = index;
            group.add(chevron);
        });
        routeLayer.add(group);
    }

    const last = path[path.length - 1];
    const goal = new THREE.Group();
    goal.userData.floor = last.floor_code;
    goal.position.copy(three(last.position));
    const ring = new THREE.Mesh(new THREE.RingGeometry(0.45, 0.62, 48).rotateX(-Math.PI / 2), new THREE.MeshBasicMaterial({ color: COLOURS.goal, transparent: true, opacity: 0.85, depthWrite: false, side: THREE.DoubleSide }));
    ring.position.y = 0.02;
    ring.userData.ring = true;
    const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 1.7, 12), new THREE.MeshStandardMaterial({ color: 0xffffff }));
    pole.position.y = 0.85;
    const ball = new THREE.Mesh(new THREE.SphereGeometry(0.13, 24, 16), new THREE.MeshStandardMaterial({ color: COLOURS.goal, emissive: 0x7f1d1d }));
    ball.position.y = 1.8;
    ball.userData.ball = true;
    const name = label(state.destination, { height: 0.32, background: 'rgba(159, 18, 57, 0.92)' });
    name.position.y = 2.25;
    goal.add(ring, pole, ball, name);
    routeLayer.add(goal);
    state.goal = goal;

    for (const step of state.starts) {
        const point = path[step.index];
        const icon = M.stepIcon(step);
        let sign = null;
        if (step.type === 'connector') {
            sign = label(`${ICONS[icon]} ${step.connector ?? ''} · ${floorName(step.to_floor_code)}`, { height: 0.24, background: 'rgba(88, 28, 135, 0.92)' });
            sign.position.copy(three(point.position)).add(new THREE.Vector3(0, 2.1, 0));
        } else if (step.type === 'walk' && step.index > 0 && icon !== 'ahead') {
            sign = label(ICONS[icon], { height: 0.5 });
            sign.position.copy(three(point.position)).add(new THREE.Vector3(0, 1.4, 0));
        }
        if (sign) {
            const holder = new THREE.Group();
            holder.userData.floor = point.floor_code;
            holder.add(sign);
            routeLayer.add(holder);
        }
    }
    showFloor();
}

/** A pale strip along the route under the arrows. */
function ribbon(points) {
    const half = 0.18;
    const positions = [];
    for (let i = 0; i + 1 < points.length; i++) {
        const a = points[i];
        const b = points[i + 1];
        const length = Math.hypot(b.x - a.x, b.y - a.y);
        if (length < 1e-6) continue;
        const nx = (-(b.y - a.y) / length) * half;
        const ny = ((b.x - a.x) / length) * half;
        const corner = (point, side) => [point.x + side * nx, point.z + 0.015, -(point.y + side * ny)];
        const [a1, a2, b1, b2] = [corner(a, 1), corner(a, -1), corner(b, 1), corner(b, -1)];
        positions.push(...a1, ...a2, ...b1, ...b1, ...a2, ...b2);
    }
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    return new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({ color: COLOURS.ribbon, transparent: true, opacity: 0.25, depthWrite: false, side: THREE.DoubleSide }));
}

/** A frame on every QR code of the map: on the code lined up with, it should sit on the printed code. */
function buildCodes() {
    clear(codeLayer);
    for (const code of state.codes) {
        const lined = state.alignment?.code === code;
        const group = new THREE.Group();
        group.userData.floor = code.floor_code;
        const size = code.qr.width + 0.06;
        const frame = new THREE.Mesh(new THREE.PlaneGeometry(size, size), new THREE.MeshBasicMaterial({ map: frameTexture, color: lined ? COLOURS.code : COLOURS.otherCode, transparent: true, depthWrite: false, side: THREE.DoubleSide }));
        const facing = new THREE.Vector3(Math.cos(code.qr.yaw * M.DEG), 0, -Math.sin(code.qr.yaw * M.DEG));
        frame.position.copy(three(code.qr)).addScaledVector(facing, 0.01);
        frame.rotation.y = yawToRotationY(code.qr.yaw);
        const tag = label(`QR ${code.checkpoint_code}`, { height: 0.09 });
        tag.position.copy(frame.position).add(new THREE.Vector3(0, -(size / 2 + 0.08), 0));
        group.add(frame, tag);
        codeLayer.add(group);
    }
}

function showFloor() {
    for (const layer of [routeLayer, codeLayer, previewLayer]) {
        for (const child of layer.children) {
            child.visible = child.userData.floor === undefined || child.userData.floor === state.floor;
        }
    }
}

function animate(seconds) {
    const here = state.viewerLocal;
    for (const group of routeLayer.children) {
        if (!group.visible) continue;
        for (const item of group.children) {
            if (item.userData.chevron === undefined) continue;
            const wave = 0.5 + 0.5 * Math.cos(2 * Math.PI * (seconds * 0.9 - item.userData.chevron * 0.12));
            const distance = Math.hypot(item.position.x - here.x, item.position.z - here.z);
            item.material.opacity = (0.35 + 0.65 * wave) * Math.min(1, Math.max(0, 1.4 - distance / 25));
        }
    }
    if (state.goal) {
        for (const item of state.goal.children) {
            if (item.userData.ring) item.scale.setScalar(1 + 0.12 * Math.sin(seconds * 3));
            if (item.userData.ball) item.position.y = 1.8 + 0.06 * Math.sin(seconds * 2);
        }
    }
}

// ---- following the route -------------------------------------------------------------------

function update(now) {
    if (state.viewer) {
        inverse.copy(world.matrix).invert();
        state.viewerLocal.set(...state.viewer.position).applyMatrix4(inverse);
    }
    switch (state.phase) {
        case 'floor':
            headline('', t('toPlace', { place: state.destination }), '');
            panelText(t('floorStep'));
            $('skip').hidden = now - state.phaseSince < 4000;
            refreshPanel();
            break;
        case 'align':
            alignPanel(now);
            break;
        case 'guide':
        case 'connector':
            guide(now);
            break;
        default:
            break;
    }
    const lost = state.session !== null && (state.viewer === null || state.viewer.emulated);
    if (lost && state.phase !== 'intro') {
        $('hint').textContent = t('lost');
        $('hint').hidden = false;
    } else if (state.phase !== 'guide' && state.phase !== 'connector') {
        $('hint').hidden = true;
    }
    if (!$('details').hidden) $('details').textContent = details();
}

function alignPanel(now) {
    const scanning = state.scanner !== null && (state.cameraSeen || now - state.phaseSince < 2500);
    headline('', t('toPlace', { place: state.destination }), '');
    let text = scanning ? `${t('alignCamera')} ${t('alignTouch')}` : t('alignTouchOnly');
    if (state.expectFloor !== null) text = `${t('alignFloor', { floor: floorName(state.expectFloor) })} ${text}`;
    if (state.sightings.length > 0) text = t('holdStill', { count: state.sightings.length, needed: SIGHTINGS });
    panelText(text);
    $('aim').hidden = !scanning;
    refreshPanel();
}

function guide(now) {
    const path = state.route.path;
    const here = { x: state.viewerLocal.x, y: -state.viewerLocal.z, z: state.viewerLocal.y };
    const where = M.locate(path, state.lengths, state.floor, here);
    state.where = where ? { ...where, here } : null;
    const last = path[path.length - 1];
    if (last.floor_code === state.floor && Math.hypot(here.x - last.position.x, here.y - last.position.y) < ARRIVE_M) {
        arrive();
        return;
    }
    if (!where) {
        headline(ICONS.back, t('offRoute'), '');
        offRoute(now, Infinity);
        return;
    }
    const total = state.lengths[state.lengths.length - 1];
    const remaining = Math.max(0, total - where.along);
    const minutes = Math.max(1, Math.ceil((state.route.total.seconds * (total > 0 ? remaining / total : 0)) / 60));
    const next = M.nextStep(state.starts, where.along);
    const when = next.distance > 2 ? t('inMetres', { metres: Math.round(next.distance) }) : t('now');
    headline(ICONS[M.stepIcon(next.step)], next.step.instruction, `${when} · ${t('left', { metres: Math.round(remaining), minutes })}`);
    if (state.phase === 'guide' && next.step.type === 'connector' && next.step.floor_code === state.floor && next.distance < CONNECTOR_M) {
        reachConnector(next.step);
    }
    offRoute(now, where.distance);
    bearingHint(where);
}

function reachConnector(step) {
    state.connectorStep = step;
    setPhase('connector');
    const floor = floorName(step.to_floor_code);
    banner(`${step.instruction} ${t('connectorThen', { floor })}`, t('onFloor', { floor }), () => changeFloor(step.to_floor_code), 'connector');
}

function changeFloor(floor) {
    hideBanner();
    if (state.walker) {
        previewChangeFloor(floor);
        return;
    }
    state.expectFloor = floor;
    state.floorY = null;
    state.floorYFloor = null;
    state.floorHits = [];
    // After a lift the phone's sense of place may have jumped: hide the route until the next line-up.
    world.visible = false;
    setPhase('align');
}

function offRoute(now, distance) {
    if (distance > OFF_ROUTE_M) {
        state.offSince ??= now;
        if (now - state.offSince > OFF_ROUTE_MS && state.banner !== 'off' && state.banner !== 'connector') {
            banner(t('offRoute'), t('newRoute'), rerouteFromHere, 'off');
        }
    } else {
        state.offSince = null;
        if (state.banner === 'off') hideBanner();
    }
}

function rerouteFromHere() {
    const here = { x: state.viewerLocal.x, y: -state.viewerLocal.z };
    const node = M.nearestNode(state.data.nodes, state.floor, here);
    hideBanner();
    state.offSince = null;
    if (node) planRoute(`node:${node.code}`);
}

function bearingHint(where) {
    const hint = $('hint');
    const viewer = state.viewer;
    if (!viewer || Math.abs(viewer.forward[1]) > 0.9) {
        hint.hidden = true;
        return;
    }
    const target = M.pointAt(state.route.path, state.lengths, where.along + 3);
    if (target.floor_code !== state.floor) {
        hint.hidden = true;
        return;
    }
    const ahead = three(target).applyMatrix4(world.matrix);
    const bearing = M.bearingTo(M.headingOf(viewer.forward), viewer.position, [ahead.x, ahead.y, ahead.z]);
    const text = Math.abs(bearing) > 120 ? t('behind') : bearing > 55 ? t('toLeft') : bearing < -55 ? t('toRight') : '';
    hint.textContent = text;
    hint.hidden = text === '';
}

function arrive() {
    setPhase('arrived');
    hideBanner();
    $('hint').hidden = true;
    headline(ICONS.arrive, t('arrived', { place: state.destination }), '');
}

// ---- what the visitor sees on top ----------------------------------------------------------------

function headline(icon, text, meta) {
    if ($('icon').textContent !== icon) $('icon').textContent = icon;
    if ($('say').textContent !== text) $('say').textContent = text;
    if ($('meta').textContent !== meta) $('meta').textContent = meta;
}

function panelText(text) {
    if ($('panel-text').textContent !== text) $('panel-text').textContent = text;
}

function refreshPanel() {
    const rowsShown = !$('touch-row').hidden || !$('skip').hidden || !$('keep').hidden;
    $('step-row').hidden = $('skip').hidden && $('keep').hidden;
    $('panel').hidden = $('panel-text').textContent === '' && !rowsShown;
}

function setPhase(phase) {
    state.phase = phase;
    state.phaseSince = performance.now();
    state.sightings = [];
    const aligning = phase === 'align';
    $('aim').hidden = !aligning;
    $('touch-row').hidden = !aligning || state.walker !== null;
    $('skip').hidden = phase !== 'floor';
    const keep = aligning && state.alignment !== null;
    $('keep').hidden = !keep;
    $('keep').textContent = state.expectFloor !== null ? t('continueFloor') : t('keep');
    if (aligning) fillCodes();
    if (phase !== 'floor' && phase !== 'align') panelText('');
    if (phase !== 'guide' && phase !== 'connector') $('hint').hidden = true;
    $('realign').hidden = state.walker !== null;
    refreshPanel();
}

function banner(text, action, run, kind) {
    state.banner = kind;
    state.bannerAction = run;
    $('banner-text').textContent = text;
    $('banner-action').textContent = action ?? '';
    $('banner-action').hidden = !action;
    $('banner').hidden = false;
}

function hideBanner() {
    state.banner = null;
    state.bannerAction = null;
    $('banner').hidden = true;
}

let toastTimer = null;
function toast(text) {
    $('toast').textContent = text;
    $('toast').hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { $('toast').hidden = true; }, 3500);
}

/** A toast at most every few seconds for the same kind of message. */
function notice(kind, text) {
    const now = performance.now();
    if (now - (state.notices.get(kind) ?? -1e9) < 4000) return;
    state.notices.set(kind, now);
    toast(text);
}

/** What the page knows, for checking the line-up on site (English only). */
function details() {
    const a = state.alignment;
    const lines = [];
    if (a) {
        const parts = [`${a.method}`, `QR ${a.code.checkpoint_code}`];
        if (a.distance !== undefined) parts.push(`${a.distance.toFixed(2)} m from the code`);
        if (a.fit !== undefined) parts.push(`fit ${a.fit.toFixed(1)} px`);
        if (a.spreadDeg !== undefined) parts.push(`spread ${a.spreadDeg.toFixed(1)}° and ${Math.round(a.spreadM * 100)} cm`);
        if (a.limited) parts.push('tracking was limited');
        lines.push(`Line-up: ${parts.join(' · ')}`);
        lines.push(`Height of the route from ${a.floorFrom}`);
    } else {
        lines.push('Line-up: none yet');
    }
    lines.push(`Floor: ${state.floorY === null ? 'not found' : `found at ${state.floorY.toFixed(2)} m`}`);
    const scanner = state.scanner;
    lines.push(`Camera pictures: ${scanner ? `${scanner.pictures} read${scanner.flip === null ? '' : scanner.flip ? ', rows bottom first' : ', rows top first'}${scanner.failures ? `, ${scanner.failures} failed` : ''}` : 'not available (hold the phone to the code)'}`);
    lines.push(`Anchor: ${state.anchor ? 'following the code' : 'none'}`);
    if (state.where) {
        const { here, distance, along } = state.where;
        lines.push(`You: x ${here.x.toFixed(2)}, y ${here.y.toFixed(2)} on ${state.floor} · ${distance.toFixed(1)} m from the route · ${Math.round(along)} of ${Math.round(state.lengths.at(-1))} m`);
    }
    lines.push(`Tracking: ${state.viewer === null ? 'lost' : state.viewer.emulated ? 'limited' : 'normal'}`);
    return lines.join('\n');
}

// ---- preview without a camera ----------------------------------------------------------------------

async function startPreview() {
    const start = state.codesBySlug.get(String(config.from || '').replace(/^checkpoint:/, '')) ?? state.codes[0];
    if (!start) {
        status(t('noRoute', { reason: 'no QR code' }), true);
        return;
    }
    $('intro').hidden = true;
    $('overlay').hidden = false;
    renderer.domElement.style.display = 'block';
    scene.background = new THREE.Color(0x0b1220);
    buildPreviewFloors();
    previewLayer.visible = true;
    resetGuidance();
    state.walker = { along: 0, x: start.qr.x, y: start.qr.y, heading: (start.qr.yaw * M.DEG) + Math.PI, auto: true, paused: false };
    state.last = performance.now();
    toast(t('previewNote'));
    renderer.setAnimationLoop(frameLoop);
    await lineUp(start, [start.qr.x, start.qr.z, -start.qr.y], start.qr.yaw * M.DEG, 'preview');
    const first = state.route?.path[0]?.position;
    if (first) {
        state.walker.x = first.x;
        state.walker.y = first.y;
    }
}

function stopPreview() {
    renderer.setAnimationLoop(null);
    renderer.domElement.style.display = 'none';
    scene.background = null;
    previewLayer.visible = false;
    state.walker = null;
    resetGuidance();
    $('overlay').hidden = true;
    $('intro').hidden = false;
    setPhase('intro');
}

function buildPreviewFloors() {
    clear(previewLayer);
    for (const floor of state.data.floors) {
        const nodes = state.data.nodes.filter((node) => node.floor === floor.floor_code);
        if (!nodes.length) continue;
        const group = new THREE.Group();
        group.userData.floor = floor.floor_code;
        const xs = nodes.map((node) => node.x);
        const ys = nodes.map((node) => node.y);
        const size = Math.ceil(Math.max(10, Math.max(...xs) - Math.min(...xs), Math.max(...ys) - Math.min(...ys)) + 10);
        const grid = new THREE.GridHelper(size, size, 0x334155, 0x1e293b);
        grid.position.set((Math.max(...xs) + Math.min(...xs)) / 2, floorElevation(floor.floor_code) - 0.005, -(Math.max(...ys) + Math.min(...ys)) / 2);
        const positions = [];
        for (const [a, b, code] of state.data.edges) {
            if (code !== floor.floor_code) continue;
            const from = state.nodes.get(a);
            const to = state.nodes.get(b);
            if (!from || !to) continue;
            positions.push(from.x, from.z + 0.005, -from.y, to.x, to.z + 0.005, -to.y);
        }
        const walkways = new THREE.BufferGeometry();
        walkways.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
        group.add(grid, new THREE.LineSegments(walkways, new THREE.LineBasicMaterial({ color: 0x64748b })));
        previewLayer.add(group);
    }
}

function previewFrame(now) {
    const walker = state.walker;
    const dt = Math.min(0.1, (now - state.last) / 1000);
    state.last = now;
    if (state.route && walker.auto && !walker.paused && state.phase === 'guide') {
        const run = M.routeRuns(state.route.path).find((item) => item.floor === state.floor && state.lengths[item.to] >= walker.along - 1e-6);
        const end = run ? state.lengths[run.to] : walker.along;
        walker.along = Math.min(end, walker.along + WALK_MPS * dt);
        const at = M.pointAt(state.route.path, state.lengths, walker.along);
        const ahead = M.pointAt(state.route.path, state.lengths, Math.min(end, walker.along + 2));
        walker.x = at.x;
        walker.y = at.y;
        if (Math.hypot(ahead.x - at.x, ahead.y - at.y) > 0.05) walker.heading = Math.atan2(ahead.y - at.y, ahead.x - at.x);
    } else if (!walker.auto && !walker.paused) {
        if (state.keys.has('ArrowLeft')) walker.heading += 1.6 * dt;
        if (state.keys.has('ArrowRight')) walker.heading -= 1.6 * dt;
        const step = (state.keys.has('ArrowUp') ? 1.4 : 0) - (state.keys.has('ArrowDown') ? 1.4 : 0);
        walker.x += Math.cos(walker.heading) * step * dt;
        walker.y += Math.sin(walker.heading) * step * dt;
    }
    if (walker.auto && state.phase === 'connector' && now - state.phaseSince > 1500 && state.connectorStep) {
        changeFloor(state.connectorStep.to_floor_code);
    }
    const elevation = floorElevation(state.floor);
    camera.position.set(walker.x, elevation + EYE_M, -walker.y);
    const look = new THREE.Vector3(walker.x + Math.cos(walker.heading) * 3, elevation + 0.5, -(walker.y + Math.sin(walker.heading) * 3));
    camera.lookAt(look);
    const forward = look.sub(camera.position).normalize();
    state.viewer = { position: camera.position.toArray(), forward: forward.toArray(), emulated: false };
}

function previewChangeFloor(floor) {
    const index = state.route.path.findIndex((point, i) => i > (state.connectorStep?.index ?? 0) && point.floor_code === floor);
    state.floor = floor;
    if (index >= 0) {
        state.walker.along = state.lengths[index];
        state.walker.x = state.route.path[index].position.x;
        state.walker.y = state.route.path[index].position.y;
    }
    showFloor();
    setPhase('guide');
}

window.addEventListener('keydown', (event) => {
    if (!state.walker) return;
    if (event.key.startsWith('Arrow')) {
        event.preventDefault();
        state.walker.auto = false;
        state.keys.add(event.key);
    } else if (event.key === 'p' || event.key === 'P') {
        state.walker.paused = !state.walker.paused;
    }
});
window.addEventListener('keyup', (event) => state.keys.delete(event.key));

// ---- start -----------------------------------------------------------------------------------------

async function boot() {
    translate();
    $('close').addEventListener('click', closePage);
    $('start').addEventListener('click', startAr);
    $('preview').addEventListener('click', startPreview);
    $('exit').addEventListener('click', exitAr);
    $('realign').addEventListener('click', () => {
        if (['guide', 'connector', 'arrived'].includes(state.phase)) {
            hideBanner();
            setPhase('align');
        }
    });
    $('details-button').addEventListener('click', () => {
        $('details').hidden = !$('details').hidden;
        if (!$('details').hidden) $('details').textContent = details();
    });
    $('skip').addEventListener('click', () => setPhase('align'));
    $('keep').addEventListener('click', keepLineUp);
    $('touch').addEventListener('click', () => {
        const code = state.codesBySlug.get($('code').value);
        if (code) state.touchPending = code;
    });
    $('banner-action').addEventListener('click', () => state.bannerAction?.());
    // Taps on the buttons on top of the camera are not taps in the AR scene.
    $('overlay').addEventListener('beforexrselect', (event) => event.preventDefault());

    if (!config.to) {
        status(t('noDestination'), true);
        return;
    }
    status(t('loading'));
    try {
        await loadData();
    } catch (error) {
        status(error.message, true);
        return;
    }
    $('destination').textContent = t('toPlace', { place: state.destination });
    tripSummary();
    if (PREVIEW) $('preview').hidden = false;
    status(t('checking'));
    const support = await arSupport();
    if (support.ok) {
        $('start').hidden = false;
        status(t('ready'));
    } else {
        status(t(support.reason), true);
    }
    $('intro').setAttribute('aria-busy', 'false');
}

// For checks in a browser without a camera.
window.ismAr = { state, world, camera, scene, maths: M };

boot();
