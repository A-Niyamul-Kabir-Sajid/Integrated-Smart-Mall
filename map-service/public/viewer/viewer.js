/**
 * ISM map viewer: shows a map package (floor models, navigation graph, QR
 * checkpoints) and lets you place, edit and save shop signs and
 * advertisements in 3D.
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import * as A from './annotations.js';

const NODE_COLOURS = { junction: 0xd7263d, checkpoint: 0x1c63d9, entrance: 0x16954a, connector: 0x7e3bc4, facility: 0xe67e19 };
const $ = (id) => document.getElementById(id);

const params = new URLSearchParams(location.search);
const service = params.get('map')
    ? {
        mapId: params.get('map'),
        packageUrl: `/admin/maps/${encodeURIComponent(params.get('map'))}/package`,
        annotationsUrl: `/admin/maps/${encodeURIComponent(params.get('map'))}/annotations`,
        endPointsUrl: `/admin/maps/${encodeURIComponent(params.get('map'))}/end-points`,
    }
    : null;

const state = {
    points: [],
    pointObjects: [],
    pkg: null,
    resolve: null,
    sourceLabel: '',
    floors: new Map(),
    connectorGroup: null,
    annotations: [],
    objects: new Map(),
    images: new Map(),
    selectedId: null,
    tool: null,
    storageKey: null,
};

// ---------------------------------------------------------------------------
// Scene
// ---------------------------------------------------------------------------

const host = $('canvas-host');
const renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.outputColorSpace = THREE.SRGBColorSpace;
host.appendChild(renderer.domElement);

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0f141c);
const camera = new THREE.PerspectiveCamera(50, 1, 0.05, 2000);
camera.position.set(10, 12, 14);
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.1;

scene.add(new THREE.HemisphereLight(0xffffff, 0x445066, 1.6));
const sun = new THREE.DirectionalLight(0xffffff, 1.4);
sun.position.set(20, 40, 10);
scene.add(sun);

const grid = new THREE.GridHelper(40, 40, 0x2f3b4d, 0x1d2532);
scene.add(grid);

const annotationRoot = new THREE.Group();
scene.add(annotationRoot);

function resize() {
    const { clientWidth, clientHeight } = host;
    renderer.setSize(clientWidth, clientHeight, false);
    camera.aspect = clientWidth / Math.max(1, clientHeight);
    camera.updateProjectionMatrix();
}
new ResizeObserver(resize).observe(host);
resize();

renderer.setAnimationLoop(() => {
    controls.update();
    renderer.render(scene, camera);
});

const v3 = (mallPoint) => new THREE.Vector3(...A.mallToThree(mallPoint));

// ---------------------------------------------------------------------------
// Loading a map package
// ---------------------------------------------------------------------------

async function loadFromUrl(base) {
    const baseUrl = new URL(base.endsWith('/') ? base : `${base}/`, location.href);
    const response = await fetch(new URL('map_package.json', baseUrl));
    if (!response.ok) {
        throw new Error(`Could not read ${new URL('map_package.json', baseUrl)} (HTTP ${response.status}).`);
    }
    const pkg = await response.json();
    await loadPackage(pkg, (file) => new URL(file, baseUrl).href, base);
}

async function loadFromService() {
    const response = await fetch(service.packageUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (response.status === 401 || response.status === 419) {
        throw new Error('Sign in to the map service first (/login), then open this map again from the Maps page.');
    }
    if (!response.ok) {
        throw new Error(`The map service could not load this map (HTTP ${response.status}).`);
    }
    const pkg = await response.json();
    const urls = pkg.file_urls ?? {};
    await loadPackage(pkg, (file) => {
        if (!urls[file]) {
            throw new Error(`The map service sent no link for ${file}.`);
        }
        return urls[file];
    }, pkg.map?.name ?? 'map service');
}

async function loadFromFolder(fileList) {
    const files = [...fileList];
    const manifest = files.find((f) => f.name === 'map_package.json');
    if (!manifest) {
        throw new Error('That folder has no map_package.json. Choose the package folder written by the export command.');
    }
    const prefix = manifest.webkitRelativePath.slice(0, -'map_package.json'.length);
    const urls = new Map(files.map((f) => [f.webkitRelativePath.slice(prefix.length), URL.createObjectURL(f)]));
    const pkg = JSON.parse(await manifest.text());
    await loadPackage(pkg, (file) => {
        const url = urls.get(file);
        if (!url) {
            throw new Error(`The folder is missing ${file}.`);
        }
        return url;
    }, prefix.replace(/\/$/, '') || 'package folder');
}

async function loadPackage(pkg, resolve, label) {
    if (pkg.format !== 'ism-map-package') {
        throw new Error('This is not an ism-map-package file.');
    }
    showOverlay('Loading floor models…');
    clearPackage();
    state.pkg = pkg;
    state.resolve = resolve;
    state.sourceLabel = label;

    const loader = new GLTFLoader();
    const nodesByCode = new Map(pkg.nodes.map((n) => [n.node_code, n]));

    for (const floor of pkg.floors) {
        const group = new THREE.Group();
        group.name = `floor ${floor.floor_code}`;
        const entry = { floor, group, modelMeshes: [], graph: new THREE.Group(), checkpoints: new THREE.Group(), originalMaterials: new Map() };

        const gltf = await loader.loadAsync(resolve(floor.model.file));
        gltf.scene.traverse((object) => {
            if (object.isMesh) {
                object.userData.floorCode = floor.floor_code;
                entry.modelMeshes.push(object);
                entry.originalMaterials.set(object, object.material);
            }
        });
        group.add(gltf.scene);
        group.add(entry.graph, entry.checkpoints);
        scene.add(group);
        state.floors.set(floor.floor_code, entry);
    }

    buildGraph(pkg, nodesByCode);
    buildCheckpoints(pkg);
    applyUnlit($('unlit').checked);
    fillFloorSelect();
    showStats();
    state.storageKey = service ? null : `ism-annotations:${label}:${pkg.generated_at}`;
    await loadInitialAnnotations();
    fitView('3d');
    hideOverlay();
}

function clearPackage() {
    for (const entry of state.floors.values()) {
        scene.remove(entry.group);
    }
    if (state.connectorGroup) {
        scene.remove(state.connectorGroup);
    }
    state.floors.clear();
    setAnnotations([]);
}

function buildGraph(pkg, nodesByCode) {
    const sphere = new THREE.SphereGeometry(0.09, 12, 8);
    const materials = Object.fromEntries(Object.entries(NODE_COLOURS).map(([type, colour]) => [type, new THREE.MeshBasicMaterial({ color: colour })]));
    for (const node of pkg.nodes) {
        const entry = state.floors.get(node.floor_code);
        const mesh = new THREE.Mesh(sphere, materials[node.node_type] ?? materials.junction);
        mesh.position.copy(v3({ x: node.x_m, y: node.y_m, z: node.z_m + 0.05 }));
        mesh.scale.setScalar(node.node_type === 'junction' ? 1 : 1.8);
        entry.graph.add(mesh);
    }

    const perFloor = new Map();
    const vertical = [];
    const seen = new Set();
    for (const edge of pkg.edges) {
        const key = [edge.from_node_code, edge.to_node_code].sort().join('|');
        if (seen.has(key)) {
            continue;
        }
        seen.add(key);
        const a = nodesByCode.get(edge.from_node_code);
        const b = nodesByCode.get(edge.to_node_code);
        const points = [v3({ x: a.x_m, y: a.y_m, z: a.z_m + 0.05 }), v3({ x: b.x_m, y: b.y_m, z: b.z_m + 0.05 })];
        if (edge.connector) {
            vertical.push(...points);
        } else {
            if (!perFloor.has(a.floor_code)) {
                perFloor.set(a.floor_code, []);
            }
            perFloor.get(a.floor_code).push(...points);
        }
    }
    for (const [code, points] of perFloor) {
        const lines = new THREE.LineSegments(new THREE.BufferGeometry().setFromPoints(points), new THREE.LineBasicMaterial({ color: 0xff3b55 }));
        lines.renderOrder = 2;
        lines.material.depthTest = false;
        state.floors.get(code).graph.add(lines);
    }
    state.connectorGroup = new THREE.Group();
    if (vertical.length) {
        const lines = new THREE.LineSegments(new THREE.BufferGeometry().setFromPoints(vertical), new THREE.LineDashedMaterial({ color: 0xb48cff, dashSize: 0.3, gapSize: 0.2 }));
        lines.computeLineDistances();
        state.connectorGroup.add(lines);
    }
    scene.add(state.connectorGroup);
}

function buildCheckpoints(pkg) {
    for (const checkpoint of pkg.checkpoints) {
        const entry = state.floors.get(checkpoint.floor_code);
        const size = checkpoint.qr.physical_width_m;
        const group = new THREE.Group();
        const face = new THREE.Mesh(new THREE.PlaneGeometry(size, size), new THREE.MeshBasicMaterial({ color: 0x1c63d9, side: THREE.DoubleSide }));
        const arrow = new THREE.ArrowHelper(new THREE.Vector3(0, 0, 1), new THREE.Vector3(0, 0, 0.01), 0.6, 0x4f8cff, 0.15, 0.08);
        group.add(face, arrow);
        group.position.copy(v3({ x: checkpoint.qr.x_m, y: checkpoint.qr.y_m, z: checkpoint.qr.z_m }));
        group.rotation.y = A.yawToRotationY(checkpoint.qr.yaw_deg);
        group.add(makeLabelSprite(checkpoint.checkpoint_code));
        entry.checkpoints.add(group);
    }
}

function makeLabelSprite(text) {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 64;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#1c63d9';
    ctx.beginPath();
    ctx.roundRect(0, 0, 256, 64, 14);
    ctx.fill();
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 34px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, 128, 34);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: texture, depthTest: false }));
    sprite.scale.set(0.6, 0.15, 1);
    sprite.position.set(0, 0.3, 0);
    sprite.renderOrder = 3;
    return sprite;
}

function applyUnlit(unlit) {
    for (const entry of state.floors.values()) {
        for (const mesh of entry.modelMeshes) {
            const original = entry.originalMaterials.get(mesh);
            if (!unlit) {
                mesh.material = original;
                continue;
            }
            if (!mesh.userData.unlitMaterial) {
                mesh.userData.unlitMaterial = new THREE.MeshBasicMaterial({
                    map: original.map ?? null,
                    color: original.map ? 0xffffff : original.color,
                    side: original.side,
                });
            }
            mesh.material = mesh.userData.unlitMaterial;
        }
    }
}

function fillFloorSelect() {
    const select = $('floor-select');
    select.replaceChildren();
    const all = new Option('All floors', '');
    select.add(all);
    for (const floor of state.pkg.floors) {
        select.add(new Option(`${floor.floor_code} · ${floor.name} (${floor.elevation_m} m)`, floor.floor_code));
    }
    select.value = state.pkg.floors.length > 1 ? '' : state.pkg.floors[0].floor_code;
    applyVisibility();
}

function showStats() {
    const s = state.pkg.stats;
    $('package-name').textContent = `${state.sourceLabel} · exported ${state.pkg.generated_at.replace('T', ' ').replace('+00:00', ' UTC')}`;
    const items = [['floors', s.floors], ['nodes', s.nodes], ['edges', s.edges], ['QR codes', s.checkpoints], ['entrances', s.shop_entrances], ['connectors', s.connectors]];
    $('stats').replaceChildren(...items.map(([name, value]) => {
        const div = document.createElement('div');
        div.className = 'stat';
        const b = document.createElement('b');
        b.textContent = value;
        const span = document.createElement('span');
        span.textContent = name;
        div.append(b, span);
        return div;
    }));
}

function applyVisibility() {
    const only = $('floor-select').value;
    for (const [code, entry] of state.floors) {
        entry.group.visible = !only || only === code;
        entry.graph.visible = $('show-graph').checked;
        entry.checkpoints.visible = $('show-checkpoints').checked;
    }
    if (state.connectorGroup) {
        state.connectorGroup.visible = !only && $('show-graph').checked;
    }
    annotationRoot.visible = $('show-annotations').checked;
    for (const [id, object] of state.objects) {
        const annotation = state.annotations.find((a) => a.id === id);
        object.visible = !only || !annotation?.floor_code || annotation.floor_code === only;
    }
}

// ---------------------------------------------------------------------------
// Camera views
// ---------------------------------------------------------------------------

function visibleBox() {
    const box = new THREE.Box3();
    for (const entry of state.floors.values()) {
        if (entry.group.visible) {
            for (const mesh of entry.modelMeshes) {
                box.expandByObject(mesh);
            }
        }
    }
    if (box.isEmpty()) {
        box.setFromCenterAndSize(new THREE.Vector3(), new THREE.Vector3(10, 3, 10));
    }
    return box;
}

function fitView(mode) {
    const box = visibleBox();
    const centre = box.getCenter(new THREE.Vector3());
    const size = box.getSize(new THREE.Vector3());
    const span = Math.max(size.x, size.z, 4);
    grid.position.set(centre.x, box.min.y - 0.01, centre.z);
    grid.scale.setScalar(Math.max(1, span / 30));
    controls.target.copy(centre);
    const distance = span / (2 * Math.tan((camera.fov * Math.PI) / 360));
    if (mode === 'top') {
        camera.up.set(0, 0, -1);
        camera.position.set(centre.x, centre.y + distance * 1.1, centre.z + 0.001);
    } else {
        camera.up.set(0, 1, 0);
        camera.position.set(centre.x + span * 0.35, centre.y + distance * 0.75, centre.z + distance * 0.75);
    }
    camera.lookAt(centre);
    controls.update();
}

function focusOn(annotation) {
    const target = v3(annotation.position);
    const offset = camera.position.clone().sub(controls.target);
    const length = Math.min(Math.max(offset.length(), 3), 8);
    controls.target.copy(target);
    camera.position.copy(target.clone().add(offset.setLength(length)));
}

// ---------------------------------------------------------------------------
// Annotation rendering
// ---------------------------------------------------------------------------

function drawPanel(annotation) {
    const kind = A.KINDS[annotation.kind];
    const width = 1024;
    const height = Math.round(width / kind.aspect);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    const radius = Math.min(width, height) * 0.08;

    ctx.fillStyle = annotation.background;
    ctx.beginPath();
    ctx.roundRect(0, 0, width, height, radius);
    ctx.fill();
    ctx.lineWidth = Math.max(6, height * 0.02);
    ctx.strokeStyle = 'rgba(255,255,255,0.25)';
    ctx.stroke();

    ctx.fillStyle = annotation.color;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

    const fitLines = (text, maxWidth, maxHeight, maxLines, weight) => {
        let size = maxHeight;
        let lines = [];
        while (size > 12) {
            ctx.font = `${weight} ${size}px ${family}`;
            lines = A.wrapText(text, maxWidth, (t) => ctx.measureText(t).width, maxLines);
            const widest = Math.max(...lines.map((l) => ctx.measureText(l).width));
            if (widest <= maxWidth && lines.length * size * 1.15 <= maxHeight * maxLines) {
                break;
            }
            size *= 0.92;
        }
        return { lines, size };
    };

    const drawBlock = (block, centreY) => {
        const top = centreY - ((block.lines.length - 1) * block.size * 1.15) / 2;
        block.lines.forEach((line, i) => ctx.fillText(line, width / 2, top + i * block.size * 1.15));
    };

    const image = annotation.image ? state.images.get(annotation.image) : null;
    if (annotation.kind === 'shop_sign') {
        const hasSubtitle = annotation.subtitle.trim() !== '';
        const title = fitLines(annotation.text, width * 0.88, height * (hasSubtitle ? 0.42 : 0.55), hasSubtitle ? 1 : 2, 800);
        drawBlock(title, height * (hasSubtitle ? 0.4 : 0.5));
        if (hasSubtitle) {
            ctx.globalAlpha = 0.85;
            drawBlock(fitLines(annotation.subtitle, width * 0.85, height * 0.2, 1, 500), height * 0.76);
            ctx.globalAlpha = 1;
        }
        if (image?.complete && image.naturalWidth) {
            const size = height * 0.7;
            ctx.save();
            ctx.beginPath();
            ctx.roundRect(height * 0.15, height * 0.15, size, size, radius * 0.6);
            ctx.clip();
            drawCover(ctx, image, height * 0.15, height * 0.15, size, size);
            ctx.restore();
        }
    } else {
        let textTop = height * 0.12;
        if (image?.complete && image.naturalWidth) {
            const pad = width * 0.06;
            ctx.save();
            ctx.beginPath();
            ctx.roundRect(pad, pad, width - 2 * pad, height * 0.58, radius * 0.6);
            ctx.clip();
            drawCover(ctx, image, pad, pad, width - 2 * pad, height * 0.58);
            ctx.restore();
            textTop = height * 0.66;
        }
        const titleHeight = image ? height * 0.1 : height * 0.16;
        const title = fitLines(annotation.text, width * 0.86, titleHeight, image ? 2 : 3, 800);
        drawBlock(title, textTop + (image ? height * 0.1 : height * 0.3));
        if (annotation.subtitle.trim() !== '') {
            ctx.globalAlpha = 0.8;
            drawBlock(fitLines(annotation.subtitle, width * 0.84, height * 0.05, 2, 500), image ? height * 0.9 : height * 0.72);
            ctx.globalAlpha = 1;
        }
        ctx.font = `600 ${height * 0.025}px ${family}`;
        ctx.globalAlpha = 0.6;
        ctx.textAlign = 'left';
        ctx.fillText('AD', width * 0.05, height * 0.035);
        ctx.globalAlpha = 1;
    }
    return canvas;
}

function drawCover(ctx, image, x, y, w, h) {
    const scale = Math.max(w / image.naturalWidth, h / image.naturalHeight);
    const sw = w / scale;
    const sh = h / scale;
    ctx.drawImage(image, (image.naturalWidth - sw) / 2, (image.naturalHeight - sh) / 2, sw, sh, x, y, w, h);
}

function ensureImage(annotation) {
    if (!annotation.image || state.images.has(annotation.image)) {
        return;
    }
    const image = new Image();
    image.onload = () => {
        for (const item of state.annotations) {
            if (item.image === annotation.image) {
                rebuildObject(item);
            }
        }
    };
    image.src = annotation.image;
    state.images.set(annotation.image, image);
}

function buildObject(annotation) {
    ensureImage(annotation);
    const width = annotation.width_m;
    const height = A.panelHeight(annotation);
    const group = new THREE.Group();
    group.userData.annotationId = annotation.id;

    const texture = new THREE.CanvasTexture(drawPanel(annotation));
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = renderer.capabilities.getMaxAnisotropy();
    const geometry = new THREE.PlaneGeometry(width, height);
    const front = new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({ map: texture, side: THREE.FrontSide }));
    const back = new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({ color: 0x2b313c, side: THREE.FrontSide }));
    back.rotation.y = Math.PI;
    const panel = new THREE.Group();
    panel.add(front, back);

    const base = v3(annotation.position);
    if (annotation.mount === 'wall') {
        panel.position.set(0, annotation.lift_m, 0.03);
    } else {
        panel.position.set(0, annotation.lift_m + height / 2, 0);
        const poleHeight = annotation.lift_m;
        if (poleHeight > 0.05) {
            const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, poleHeight, 10), new THREE.MeshBasicMaterial({ color: 0x8a94a6 }));
            pole.position.set(0, poleHeight / 2, -0.03);
            const foot = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.2, 0.03, 20), new THREE.MeshBasicMaterial({ color: 0x5d6675 }));
            foot.position.set(0, 0.015, -0.03);
            group.add(pole, foot);
        }
    }
    group.add(panel);
    group.position.copy(base);
    group.rotation.y = A.yawToRotationY(annotation.yaw_deg);

    if (annotation.id === state.selectedId) {
        const outline = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.PlaneGeometry(width + 0.06, height + 0.06)), new THREE.LineBasicMaterial({ color: 0xffd60a, depthTest: false }));
        outline.position.z = 0.005;
        outline.renderOrder = 4;
        panel.add(outline);
    }
    return group;
}

function rebuildObject(annotation) {
    const old = state.objects.get(annotation.id);
    if (old) {
        annotationRoot.remove(old);
        disposeObject(old);
    }
    const object = buildObject(annotation);
    annotationRoot.add(object);
    state.objects.set(annotation.id, object);
    applyVisibility();
}

function disposeObject(object) {
    object.traverse((child) => {
        child.geometry?.dispose();
        if (child.material) {
            child.material.map?.dispose();
            child.material.dispose();
        }
    });
}

function setAnnotations(list) {
    for (const object of state.objects.values()) {
        annotationRoot.remove(object);
        disposeObject(object);
    }
    state.objects.clear();
    state.annotations = list;
    state.selectedId = null;
    list.forEach(rebuildObject);
    renderList();
    renderEditor();
}

// ---------------------------------------------------------------------------
// Picking and placing
// ---------------------------------------------------------------------------

const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();

function setPointer(event) {
    const rect = renderer.domElement.getBoundingClientRect();
    pointer.set(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1);
    raycaster.setFromCamera(pointer, camera);
}

function pickSurface() {
    const meshes = [...state.floors.values()].filter((e) => e.group.visible).flatMap((e) => e.modelMeshes);
    const hit = raycaster.intersectObjects(meshes, false)[0];
    if (!hit) {
        return null;
    }
    const normal = hit.face.normal.clone().transformDirection(hit.object.matrixWorld).normalize();
    if (normal.dot(raycaster.ray.direction) > 0) {
        normal.negate();
    }
    return { point: hit.point.clone(), normal, floorCode: hit.object.userData.floorCode };
}

function pickAnnotation() {
    if (!annotationRoot.visible) {
        return null;
    }
    const hit = raycaster.intersectObjects([...state.objects.values()].filter((o) => o.visible), true)[0];
    if (!hit) {
        return null;
    }
    let object = hit.object;
    while (object && !object.userData.annotationId) {
        object = object.parent;
    }
    return object?.userData.annotationId ?? null;
}

function placement(surface) {
    const mount = A.mountForNormal(surface.normal);
    let yaw;
    if (mount === 'wall') {
        yaw = A.threeDirectionToYaw(surface.normal);
    } else {
        const toCamera = camera.position.clone().sub(surface.point);
        yaw = A.threeDirectionToYaw(toCamera);
    }
    return { mount, yaw: A.roundYaw(yaw), position: A.threeToMall(surface.point), floorCode: surface.floorCode };
}

function floorLevel(code) {
    return state.pkg?.floors.find((f) => f.floor_code === code)?.elevation_m ?? 0;
}

let downAt = null;
renderer.domElement.addEventListener('pointerdown', (event) => {
    downAt = { x: event.clientX, y: event.clientY };
});
renderer.domElement.addEventListener('pointerup', (event) => {
    if (!downAt || Math.hypot(event.clientX - downAt.x, event.clientY - downAt.y) > 5 || event.button !== 0) {
        return;
    }
    setPointer(event);
    handleClick(event);
});

let lastHover = 0;
renderer.domElement.addEventListener('pointermove', (event) => {
    const now = performance.now();
    if (now - lastHover < 90 || !state.pkg) {
        return;
    }
    lastHover = now;
    setPointer(event);
    const surface = pickSurface();
    if (!surface) {
        setStatus('Drag to orbit · right-drag to pan · scroll to zoom');
        return;
    }
    const p = A.threeToMall(surface.point);
    const mount = A.mountForNormal(surface.normal);
    setStatus(`x ${p.x.toFixed(2)} · y ${p.y.toFixed(2)} · z ${p.z.toFixed(2)} m · floor ${surface.floorCode} · ${mount}`);
});

function handleClick(event) {
    if (state.tool === 'end_point') {
        const surface = pickSurface();
        if (surface) {
            addEndPoint(surface, event.shiftKey);
        }
        return;
    }
    if (state.tool === 'shop_sign' || state.tool === 'advertisement') {
        const surface = pickSurface();
        if (!surface) {
            return;
        }
        const where = placement(surface);
        if (where.mount === 'floor') {
            where.position.z = Math.max(where.position.z, floorLevel(where.floorCode));
        }
        const annotation = A.createAnnotation(state.tool, where.position, { yaw_deg: where.yaw, mount: where.mount, floor_code: where.floorCode, existing: state.annotations });
        state.annotations.push(annotation);
        state.selectedId = annotation.id;
        rebuildObject(annotation);
        if (!event.shiftKey) {
            setTool(null);
        }
        changed();
        $('f-text').focus();
        $('f-text').select();
        return;
    }
    if (state.tool === 'move') {
        const annotation = selected();
        const surface = pickSurface();
        if (annotation && surface) {
            const where = placement(surface);
            Object.assign(annotation, { position: where.position, mount: where.mount, yaw_deg: where.yaw, floor_code: where.floorCode, lift_m: A.defaultLift(annotation.kind, where.mount) });
            touch(annotation);
        }
        setTool(null);
        return;
    }
    select(pickAnnotation());
}

function setTool(tool) {
    state.tool = tool;
    $('add-sign').classList.toggle('active', tool === 'shop_sign');
    $('add-ad').classList.toggle('active', tool === 'advertisement');
    $('move').classList.toggle('active', tool === 'move');
    $('add-point').classList.toggle('active', tool === 'end_point');
    renderer.domElement.style.cursor = tool ? 'crosshair' : '';
    const hints = {
        shop_sign: 'Click a wall or the floor to place the shop sign (Shift+click to keep adding, Esc to stop)',
        advertisement: 'Click a wall or the floor to place the advertisement (Shift+click to keep adding, Esc to stop)',
        move: 'Click where the selected item should go (Esc to cancel)',
        end_point: 'Click the floor where the end point goes (Esc to stop)',
    };
    $('hint').textContent = hints[tool] ?? '';
    $('hint').style.display = tool ? 'block' : 'none';
}

// ---------------------------------------------------------------------------
// Selection, editor and list
// ---------------------------------------------------------------------------

function selected() {
    return state.annotations.find((a) => a.id === state.selectedId) ?? null;
}

function select(id) {
    const previous = state.selectedId;
    state.selectedId = id;
    for (const changedId of new Set([previous, id])) {
        const annotation = state.annotations.find((a) => a.id === changedId);
        if (annotation) {
            rebuildObject(annotation);
        }
    }
    renderList();
    renderEditor();
}

function renderEditor() {
    const annotation = selected();
    $('editor').hidden = !annotation;
    if (!annotation) {
        return;
    }
    $('f-kind').value = annotation.kind;
    $('f-mount').value = annotation.mount;
    $('f-text').value = annotation.text;
    $('f-subtitle').value = annotation.subtitle;
    $('f-unit').value = annotation.unit_code ?? '';
    $('f-background').value = annotation.background;
    $('f-color').value = annotation.color;
    $('f-width').value = annotation.width_m;
    $('f-lift').value = annotation.lift_m;
    $('f-yaw').value = annotation.yaw_deg;
    $('clear-image').disabled = !annotation.image;
    updateEditorLabels(annotation);
}

function updateEditorLabels(annotation) {
    $('v-width').textContent = `${annotation.width_m.toFixed(2)} m × ${A.panelHeight(annotation).toFixed(2)} m`;
    $('v-lift').textContent = annotation.mount === 'floor' ? `${annotation.lift_m.toFixed(2)} m above the floor` : `${annotation.lift_m >= 0 ? '+' : ''}${annotation.lift_m.toFixed(2)} m from the clicked point`;
    $('v-yaw').textContent = `${Math.round(annotation.yaw_deg)}°`;
    const p = annotation.position;
    $('f-where').textContent = `${annotation.id} · floor ${annotation.floor_code ?? '?'} · x ${p.x.toFixed(2)}, y ${p.y.toFixed(2)}, z ${p.z.toFixed(2)} m`;
}

function renderList() {
    $('count').textContent = state.annotations.length ? `(${state.annotations.length})` : '';
    $('list').replaceChildren(...state.annotations.map((annotation) => {
        const li = document.createElement('li');
        li.classList.toggle('selected', annotation.id === state.selectedId);
        const swatch = document.createElement('span');
        swatch.className = 'swatch';
        swatch.style.background = annotation.background;
        const name = document.createElement('span');
        name.className = 'name';
        name.textContent = annotation.text;
        const meta = document.createElement('span');
        meta.className = 'muted';
        meta.textContent = `${annotation.kind === 'shop_sign' ? 'sign' : 'ad'} · ${annotation.floor_code ?? ''}`;
        li.append(swatch, name, meta);
        li.addEventListener('click', () => {
            select(annotation.id);
            focusOn(annotation);
        });
        return li;
    }));
    if (!state.annotations.length) {
        const li = document.createElement('li');
        li.className = 'muted';
        li.style.cursor = 'default';
        li.textContent = 'None yet. Use “Add in 3D”.';
        $('list').append(li);
    }
}

function touch(annotation) {
    annotation.updated_at = new Date().toISOString();
    rebuildObject(annotation);
    renderList();
    renderEditor();
    changed();
}

function bindField(id, apply, event = 'input') {
    $(id).addEventListener(event, () => {
        const annotation = selected();
        if (!annotation) {
            return;
        }
        apply(annotation, $(id).value);
        annotation.updated_at = new Date().toISOString();
        rebuildObject(annotation);
        updateEditorLabels(annotation);
        renderList();
        changed();
    });
}

bindField('f-text', (a, v) => { a.text = v.trim() === '' ? a.text : v.slice(0, 60); });
bindField('f-subtitle', (a, v) => { a.subtitle = v.slice(0, 80); });
bindField('f-unit', (a, v) => { a.unit_code = v.trim() === '' ? null : v.trim().slice(0, 40); });
bindField('f-background', (a, v) => { a.background = v; });
bindField('f-color', (a, v) => { a.color = v; });
bindField('f-width', (a, v) => { a.width_m = Number(v); });
bindField('f-lift', (a, v) => { a.lift_m = Number(v); });
bindField('f-yaw', (a, v) => { a.yaw_deg = Number(v); });
bindField('f-kind', (a, v) => {
    a.kind = v;
    a.width_m = A.KINDS[v].width;
    a.lift_m = A.defaultLift(v, a.mount);
    if (v === 'advertisement' && a.subtitle === '') {
        a.subtitle = 'Your offer here';
    }
    renderEditor();
}, 'change');
bindField('f-mount', (a, v) => {
    a.mount = v;
    a.lift_m = A.defaultLift(a.kind, v);
    if (v === 'floor') {
        a.position.z = floorLevel(a.floor_code);
    }
    renderEditor();
}, 'change');

$('face-camera').addEventListener('click', () => {
    const annotation = selected();
    if (!annotation) {
        return;
    }
    annotation.yaw_deg = A.roundYaw(A.threeDirectionToYaw(camera.position.clone().sub(v3(annotation.position))));
    touch(annotation);
});
$('move').addEventListener('click', () => setTool(state.tool === 'move' ? null : 'move'));
$('pick-image').addEventListener('click', () => $('image-input').click());
$('image-input').addEventListener('change', async () => {
    const file = $('image-input').files[0];
    $('image-input').value = '';
    const annotation = selected();
    if (!file || !annotation) {
        return;
    }
    annotation.image = await shrinkImage(file, 1024);
    touch(annotation);
});
$('clear-image').addEventListener('click', () => {
    const annotation = selected();
    if (annotation) {
        annotation.image = null;
        touch(annotation);
    }
});
$('duplicate').addEventListener('click', () => {
    const annotation = selected();
    if (!annotation) {
        return;
    }
    const copy = structuredClone(annotation);
    copy.id = A.nextId(state.annotations);
    const yaw = (annotation.yaw_deg * Math.PI) / 180;
    const shift = annotation.width_m + 0.3;
    copy.position.x += Math.sin(yaw) * shift;
    copy.position.y -= Math.cos(yaw) * shift;
    copy.created_at = copy.updated_at = new Date().toISOString();
    state.annotations.push(copy);
    select(copy.id);
    changed();
});
$('delete').addEventListener('click', deleteSelected);

function deleteSelected() {
    const annotation = selected();
    if (!annotation) {
        return;
    }
    const object = state.objects.get(annotation.id);
    annotationRoot.remove(object);
    disposeObject(object);
    state.objects.delete(annotation.id);
    state.annotations = state.annotations.filter((a) => a.id !== annotation.id);
    state.selectedId = null;
    renderList();
    renderEditor();
    changed();
}

async function shrinkImage(file, maxSide) {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.85);
}

// ---------------------------------------------------------------------------
// Saving and loading annotations
// ---------------------------------------------------------------------------

let saveTimer = null;
let saving = Promise.resolve();
function changed() {
    clearTimeout(saveTimer);
    if (service) {
        message('Saving…');
        saveTimer = setTimeout(() => {
            saving = saving.then(saveToService);
        }, 600);
        return;
    }
    saveTimer = setTimeout(() => {
        try {
            localStorage.setItem(state.storageKey, JSON.stringify(A.serialise(state.annotations, state.pkg)));
        } catch {
            message('This browser did not keep the changes (storage blocked or full). Use “Save annotations…”.');
        }
    }, 300);
}

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function saveToService() {
    const sent = state.annotations.map((a) => ({ ...a, position: { ...a.position } }));
    try {
        const response = await fetch(service.annotationsUrl, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body: JSON.stringify({ annotations: sent }),
        });
        if (response.status === 422) {
            const body = await response.json();
            message(`Not saved: ${Object.values(body.errors ?? {}).flat().join(' ') || body.message}`);
            return;
        }
        if (response.status === 401 || response.status === 419) {
            message('Not saved: your sign-in expired. Sign in again in another tab, then change anything to save.');
            return;
        }
        if (!response.ok) {
            message(`Not saved: the map service answered HTTP ${response.status}.`);
            return;
        }
        const saved = A.parse(await response.json()).annotations;
        const byId = new Map(saved.map((a) => [a.id, a]));
        for (const annotation of state.annotations) {
            const stored = byId.get(annotation.id);
            if (stored && annotation.image && annotation.image.startsWith('data:') && stored.image) {
                state.images.set(stored.image, state.images.get(annotation.image));
                annotation.image = stored.image;
            }
        }
        message(`Saved ${saved.length} item(s) to the map service at ${new Date().toLocaleTimeString()}.`);
    } catch (error) {
        message(`Not saved: ${error.message}`);
    }
}

async function loadInitialAnnotations() {
    if (service) {
        const response = await fetch(service.annotationsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) {
            message(`Could not read the signs and advertisements (HTTP ${response.status}).`);
            return;
        }
        applyParsed(A.parse(await response.json()), '');
        message(`Editing “${state.pkg.map?.name ?? 'map'}” on the map service: every change is saved automatically.`);
        return;
    }
    const param = new URLSearchParams(location.search).get('annotations');
    if (param) {
        try {
            const response = await fetch(new URL(param, location.href));
            if (response.ok) {
                applyParsed(A.parse(await response.json()), `Loaded ${param}`);
                return;
            }
            message(`Could not read ${param} (HTTP ${response.status}).`);
        } catch (error) {
            message(`Could not read ${param}: ${error.message}`);
        }
    }
    try {
        const stored = state.storageKey ? localStorage.getItem(state.storageKey) : null;
        if (stored) {
            applyParsed(A.parse(stored), 'Restored the signs and advertisements kept in this browser.');
        }
    } catch {
        // Storage unavailable: start empty.
    }
}

function applyParsed(result, note) {
    setAnnotations(result.annotations);
    message([note, ...result.errors.map((e) => `Skipped: ${e}`)].filter(Boolean).join('\n'));
}

$('save').addEventListener('click', async () => {
    const text = JSON.stringify(A.serialise(state.annotations, state.pkg), null, 2);
    if (window.showSaveFilePicker) {
        try {
            const handle = await window.showSaveFilePicker({ suggestedName: 'annotations.json', types: [{ description: 'Annotations', accept: { 'application/json': ['.json'] } }] });
            const writable = await handle.createWritable();
            await writable.write(text);
            await writable.close();
            message(`Saved ${state.annotations.length} item(s) to ${handle.name}.`);
            return;
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
        }
    }
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([text], { type: 'application/json' }));
    link.download = 'annotations.json';
    link.click();
    URL.revokeObjectURL(link.href);
    message(`Downloaded annotations.json with ${state.annotations.length} item(s).`);
});
$('load').addEventListener('click', () => $('annotations-input').click());
$('annotations-input').addEventListener('change', async () => {
    const file = $('annotations-input').files[0];
    $('annotations-input').value = '';
    if (!file) {
        return;
    }
    try {
        if (state.annotations.length && !confirm(`Replace the ${state.annotations.length} item(s) on screen with ${file.name}?`)) {
            return;
        }
        applyParsed(A.parse(await file.text()), `Opened ${file.name}.`);
        changed();
    } catch (error) {
        message(`${file.name} is not a valid annotations file: ${error.message}`);
    }
});

// ---------------------------------------------------------------------------
// Wiring
// ---------------------------------------------------------------------------

$('add-sign').addEventListener('click', () => setTool(state.tool === 'shop_sign' ? null : 'shop_sign'));
$('add-ad').addEventListener('click', () => setTool(state.tool === 'advertisement' ? null : 'advertisement'));
$('add-point').addEventListener('click', () => setTool(state.tool === 'end_point' ? null : 'end_point'));
$('floor-select').addEventListener('change', () => {
    applyVisibility();
    fitView('3d');
});
for (const id of ['show-graph', 'show-checkpoints', 'show-annotations']) {
    $(id).addEventListener('change', applyVisibility);
}
$('unlit').addEventListener('change', () => applyUnlit($('unlit').checked));
$('view-3d').addEventListener('click', () => fitView('3d'));
$('view-top').addEventListener('click', () => fitView('top'));
$('view-fit').addEventListener('click', () => fitView(camera.up.z < 0 ? 'top' : '3d'));
$('open-folder').addEventListener('click', () => $('folder-input').click());
$('folder-input').addEventListener('change', () => {
    loadFromFolder($('folder-input').files).catch(fail);
});

window.addEventListener('keydown', (event) => {
    if (event.target.matches('input, select, textarea')) {
        return;
    }
    if (event.key === 'Escape') {
        if (state.tool) {
            setTool(null);
        } else {
            select(null);
        }
    }
    if ((event.key === 'Delete' || event.key === 'Backspace') && state.selectedId) {
        deleteSelected();
    }
});

// ---------------------------------------------------------------------------
// End points (ISM-114): destinations added by clicking the floor; the map
// service joins each to the nearest walkway and gives it a key.
// ---------------------------------------------------------------------------

async function callEndPoints(url, method = 'GET', body = null) {
    const response = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : null,
    });
    const data = response.status === 204 ? {} : await response.json().catch(() => ({}));
    if (!response.ok) {
        const detail = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
        throw new Error(detail || `The map service answered HTTP ${response.status}.`);
    }
    return data.data;
}

async function loadEndPoints() {
    try {
        state.points = await callEndPoints(service.endPointsUrl);
    } catch (error) {
        message(error.message);
        state.points = [];
    }
    renderEndPoints();
}

async function addEndPoint(surface, keepTool) {
    const position = A.threeToMall(surface.point);
    const name = window.prompt(`Name of the end point on floor ${surface.floorCode} (x ${position.x.toFixed(2)}, y ${position.y.toFixed(2)})`, 'End point');
    if (name === null || name.trim() === '') {
        return;
    }
    try {
        const point = await callEndPoints(service.endPointsUrl, 'POST', { name: name.trim(), floor_code: surface.floorCode, x: position.x, y: position.y });
        state.points.push(point);
        state.points.sort((a, b) => a.name.localeCompare(b.name));
        showNewKey(point);
        renderEndPoints();
        message(`End point “${point.name}” added with key ${point.key}.`);
    } catch (error) {
        message(error.message);
    }
    if (!keepTool) {
        setTool(null);
    }
}

function showNewKey(point) {
    const box = $('point-new');
    box.replaceChildren(
        Object.assign(document.createElement('span'), { className: 'muted', textContent: `New end point “${point.name}”, key:` }),
        Object.assign(document.createElement('span'), { className: 'key', textContent: point.key }),
        Object.assign(document.createElement('span'), { className: 'muted', textContent: `Route to it with ${point.place}` }),
    );
    box.hidden = false;
}

function pointLabel(text) {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 64;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#a21caf';
    ctx.beginPath();
    ctx.roundRect(0, 0, 512, 64, 14);
    ctx.fill();
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 30px ui-monospace, Consolas, monospace';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text.length > 30 ? text.slice(0, 29) + '…' : text, 256, 34);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: texture, depthTest: false }));
    sprite.scale.set(1.6, 0.2, 1);
    sprite.position.set(0, 1.55, 0);
    sprite.renderOrder = 4;
    return sprite;
}

function buildEndPointObjects() {
    for (const object of state.pointObjects) {
        object.parent?.remove(object);
    }
    state.pointObjects = [];
    for (const point of state.points) {
        const entry = state.floors.get(point.floor_code);
        if (!entry) {
            continue;
        }
        const group = new THREE.Group();
        const pin = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 1.3, 12), new THREE.MeshBasicMaterial({ color: point.linked ? 0xd946ef : 0x9ca3af }));
        pin.position.y = 0.65;
        const head = new THREE.Mesh(new THREE.SphereGeometry(0.22, 20, 14), new THREE.MeshBasicMaterial({ color: point.linked ? 0xd946ef : 0x9ca3af }));
        head.position.y = 1.3;
        group.add(pin, head, pointLabel(`${point.key} · ${point.name}`));
        group.position.copy(v3(point.position));
        entry.group.add(group);
        state.pointObjects.push(group);
        if (point.joined_at) {
            const line = new THREE.Line(
                new THREE.BufferGeometry().setFromPoints([v3({ ...point.position, z: point.position.z + 0.05 }), v3({ ...point.joined_at, z: point.joined_at.z + 0.05 })]),
                new THREE.LineDashedMaterial({ color: 0xd946ef, dashSize: 0.15, gapSize: 0.1 }),
            );
            line.computeLineDistances();
            entry.group.add(line);
            state.pointObjects.push(line);
        }
    }
}

function renderEndPoints() {
    $('points-count').textContent = state.points.length ? `(${state.points.length})` : '';
    const items = state.points.map((point) => {
        const li = document.createElement('li');
        const key = Object.assign(document.createElement('span'), { className: 'key', textContent: point.key });
        const actions = Object.assign(document.createElement('span'), { className: 'actions' });
        const copy = Object.assign(document.createElement('button'), { textContent: 'Copy', type: 'button' });
        copy.addEventListener('click', (event) => {
            event.stopPropagation();
            navigator.clipboard?.writeText(point.key).then(() => { copy.textContent = 'Copied'; });
        });
        const remove = Object.assign(document.createElement('button'), { textContent: 'Delete', type: 'button' });
        remove.addEventListener('click', async (event) => {
            event.stopPropagation();
            if (!window.confirm(`Delete ${point.name} (${point.key})? Links that use its key stop working.`)) {
                return;
            }
            try {
                await callEndPoints(point.update_url, 'DELETE');
                state.points = state.points.filter((item) => item.key !== point.key);
                renderEndPoints();
                message(`End point ${point.key} deleted.`);
            } catch (error) {
                message(error.message);
            }
        });
        actions.append(copy, remove);
        const meta = Object.assign(document.createElement('span'), {
            className: 'muted',
            textContent: `${point.name} · floor ${point.floor_code}${point.linked ? '' : ' · not on the map'}`,
        });
        li.append(key, actions, meta);
        li.addEventListener('click', () => focusOn(point));
        return li;
    });
    if (!items.length) {
        items.push(Object.assign(document.createElement('li'), { className: 'muted', textContent: 'None yet. Use “＋ End point”.' }));
    }
    $('points').replaceChildren(...items);
    buildEndPointObjects();
}

function setStatus(text) {
    $('status').textContent = text;
}

function message(text) {
    $('messages').textContent = text;
}

function showOverlay(text) {
    $('overlay-text').textContent = text;
    $('overlay').style.display = 'flex';
}

function hideOverlay() {
    $('overlay').style.display = 'none';
}

function fail(error) {
    console.error(error);
    showOverlay(`${error.message}`);
    $('overlay').querySelector('.card').append(Object.assign(document.createElement('p'), {
        className: 'muted',
        textContent: 'Choose the package folder with “Open package folder…” on the left, or open this page with ?package=<folder>/ in the address.',
    }));
}

renderList();
const packageParam = params.get('package');
if (service) {
    $('open-folder').hidden = true;
    $('save-note').textContent = 'Every change is saved to the map service automatically.';
    const back = Object.assign(document.createElement('a'), { href: '/admin/maps', textContent: '← Back to maps', className: 'muted' });
    $('package-name').before(back);
    $('add-point').hidden = false;
    $('points-section').hidden = false;
    $('points-page').href = service.endPointsUrl;
    loadFromService().then(loadEndPoints).catch(fail);
} else if (packageParam) {
    loadFromUrl(packageParam).catch(fail);
} else {
    showOverlay('Open a map package: use “Open package folder…” on the left and choose the folder that contains map_package.json (for example tools/blender/work/sample/package). To save signs and advertisements to the map service instead, open the map from its Maps page.');
}

window.ismViewer = { state, scene, camera, controls, select, fitView };
