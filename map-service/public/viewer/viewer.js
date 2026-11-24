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
        pathsUrl: `/admin/maps/${encodeURIComponent(params.get('map'))}/paths`,
        graphUrl: `/admin/maps/${encodeURIComponent(params.get('map'))}/graph`,
    }
    : null;

const state = {
    points: [],
    paths: [],
    graph: null,
    graphNodes: new Map(),
    overlay: { edges: [] },
    editObjects: [],
    previewObjects: [],
    pending: null,
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
    if (['point', 'qr', 'path', 'move-edit', 'graph', 'graph-move', 'graph-connect', 'graph-pick'].includes(state.tool)) {
        const surface = pickSurface();
        if (!surface) {
            showEditError('That click missed the scanned floor and walls. Click on the scan itself.');
            return;
        }
        if (state.tool === 'point') {
            startPoint(surface);
        } else if (state.tool === 'qr') {
            startQr(surface);
        } else if (state.tool === 'move-edit') {
            moveEdited(surface);
        } else if (state.tool === 'graph') {
            graphClick(surface);
        } else if (state.tool === 'graph-move') {
            graphMove(surface);
        } else if (state.tool === 'graph-connect') {
            graphConnect(surface);
        } else if (state.tool === 'graph-pick') {
            graphPick(surface);
        } else {
            addPathCorner(surface);
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
    $('add-point').classList.toggle('active', tool === 'point');
    $('add-qr').classList.toggle('active', tool === 'qr');
    $('add-path').classList.toggle('active', tool === 'path');
    $('edit-graph').classList.toggle('active', ['graph', 'graph-move', 'graph-connect', 'graph-pick'].includes(tool));
    renderer.domElement.style.cursor = tool ? 'crosshair' : '';
    const hints = {
        shop_sign: 'Click a wall or the floor to place the shop sign (Shift+click to keep adding, Esc to stop)',
        advertisement: 'Click a wall or the floor to place the advertisement (Shift+click to keep adding, Esc to stop)',
        move: 'Click where the selected item should go (Esc to cancel)',
        point: 'Click the floor where the point goes (Esc to stop)',
        qr: 'Click the wall or pillar face where the QR code hangs (Esc to stop)',
        path: 'Click the floor at each corner of the path; a yellow dot joins an existing node or path end (Esc to cancel)',
        'move-edit': 'Click where it should go (Esc to cancel)',
        graph: 'Click a node (dot) or a walkway (line) to edit it (Esc to stop)',
        'graph-move': 'Click the new place of the node (Esc to cancel)',
        'graph-connect': 'Click the node to connect to (Esc to cancel)',
        'graph-pick': 'Click the node to pick (Esc to cancel)',
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
$('add-point').addEventListener('click', () => {
    closeEditForm();
    setTool(state.tool === 'point' ? null : 'point');
});
$('add-qr').addEventListener('click', () => {
    closeEditForm();
    setTool(state.tool === 'qr' ? null : 'qr');
});
$('edit-graph').addEventListener('click', () => {
    const editing = ['graph', 'graph-move', 'graph-connect'].includes(state.tool);
    closeEditForm();
    setTool(editing ? null : 'graph');
});
$('add-path').addEventListener('click', () => {
    const drawing = state.tool === 'path';
    closeEditForm();
    setTool(drawing ? null : 'path');
    if (!drawing) {
        startPath();
    }
});
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
        if (state.tool || state.pending) {
            setTool(null);
            closeEditForm();
        } else {
            select(null);
        }
    }
    if (state.pending) {
        return;
    }
    if ((event.key === 'Delete' || event.key === 'Backspace') && state.selectedId) {
        deleteSelected();
    }
});

// ---------------------------------------------------------------------------
// Points and paths (ISM-114): administrators add a node, shop entrance,
// facility or QR code by clicking, draw walkways corner by corner, and edit,
// move, redraw or merge them. The map service joins them to the graph and
// gives each a unique key.
// ---------------------------------------------------------------------------

const KIND_COLOURS = { node: 0xd946ef, shop_entrance: 0x16954a, facility: 0xe67e19, checkpoint: 0x1c63d9 };
const FACILITY_TYPES = { toilet: 'Toilet', prayer_room: 'Prayer room', information: 'Information desk', exit: 'Exit', atm: 'ATM', lift: 'Lift', baby_care: 'Baby care', first_aid: 'First aid', other: 'Other' };
const DEFAULT_HEIGHT = 1.5;
const DEFAULT_QR_HEIGHT = 1.4;
const SNAP_M = 0.5;

async function callEdits(url, method = 'GET', body = null) {
    const response = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : null,
    });
    const data = response.status === 204 ? {} : await response.json().catch(() => ({}));
    if (response.status === 401 || response.status === 419) {
        throw new Error('Your sign-in has expired. Sign in again on /login, then reopen this map.');
    }
    if (!response.ok) {
        const detail = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
        throw new Error(detail || `The map service answered HTTP ${response.status}.`);
    }
    return data;
}

async function loadEdits() {
    try {
        const data = await callEdits(service.endPointsUrl);
        state.points = data.data ?? [];
        state.paths = data.paths ?? [];
        state.overlay = data.overlay ?? { edges: [] };
    } catch (error) {
        showEditError(error.message);
    }
    renderEdits();
}

function showEditError(text) {
    const box = $('edit-error');
    box.textContent = text;
    box.hidden = !text;
    if (text) {
        message(text);
    }
}

// --- Small form helpers ----------------------------------------------------

function field(labelText, control) {
    const label = document.createElement('label');
    label.append(labelText, control);
    return label;
}

function textInput(attributes) {
    return Object.assign(document.createElement('input'), { type: 'text', ...attributes });
}

function numberInput(value, attributes = {}) {
    return Object.assign(document.createElement('input'), { type: 'number', value: String(value), step: '0.05', ...attributes });
}

function checkbox(text, checked = false) {
    const box = Object.assign(document.createElement('input'), { type: 'checkbox', checked });
    const label = document.createElement('label');
    label.className = 'check';
    label.append(box, ` ${text}`);
    return [label, box];
}

function button(text, onClick, className = '') {
    const element = Object.assign(document.createElement('button'), { type: 'button', textContent: text, className });
    element.addEventListener('click', onClick);
    return element;
}

function closeEditForm() {
    $('edit-form').replaceChildren();
    $('edit-form').hidden = true;
    state.pending = null;
    if (['move-edit', 'path', 'graph-move', 'graph-connect', 'graph-pick'].includes(state.tool)) {
        setTool(null);
    }
    clearPreview();
}

function showForm(title, fields, actions) {
    const box = $('edit-form');
    const heading = Object.assign(document.createElement('div'), { className: 'muted', id: 'edit-heading', textContent: title });
    const row = document.createElement('div');
    row.className = 'row';
    row.append(...actions);
    box.replaceChildren(heading, ...fields, row);
    box.hidden = false;
    box.scrollIntoView({ block: 'nearest' });
}

function setHeading(text) {
    const heading = $('edit-heading');
    if (heading) {
        heading.textContent = text;
    }
}

function where(pending) {
    const facing = pending.kind === 'checkpoint' ? `, facing ${Math.round(pending.yaw)}°` : '';
    return `floor ${pending.floorCode} · x ${pending.x.toFixed(2)} · y ${pending.y.toFixed(2)}${facing}`;
}

// --- Points: add or edit a node, shop entrance or facility ---------------

function startPoint(surface) {
    const position = A.threeToMall(surface.point);
    setTool(null);
    openPointForm(null, { kind: 'node', floorCode: surface.floorCode, x: position.x, y: position.y });
}

function startQr(surface) {
    if (A.mountForNormal(surface.normal) !== 'wall') {
        showEditError('Click a wall or pillar face: a QR code hangs on a wall. Visitors stand 0.8 m in front of it.');
        return;
    }
    const position = A.threeToMall(surface.point);
    setTool(null);
    openPointForm(null, { kind: 'checkpoint', floorCode: surface.floorCode, x: position.x, y: position.y, yaw: A.roundYaw(A.threeDirectionToYaw(surface.normal)) });
}

/**
 * The form for a new point (point = null) or for editing one. QR codes have
 * their own fields; other points can switch between node, shop entrance and
 * facility. "Move" lets the next click choose a new place.
 */
function openPointForm(point, place) {
    showEditError('');
    const isQr = (point?.kind ?? place.kind) === 'checkpoint';
    state.pending = {
        edit: point,
        kind: point?.kind ?? place.kind,
        floorCode: point?.floor_code ?? place.floorCode,
        x: point?.position.x ?? place.x,
        y: point?.position.y ?? place.y,
        yaw: point?.qr?.yaw_deg ?? place?.yaw ?? 0,
    };
    showPendingPin();

    const name = textInput({ maxLength: 120, value: point?.name ?? '' });
    const height = numberInput(point?.height_m ?? (isQr ? DEFAULT_QR_HEIGHT : DEFAULT_HEIGHT), { min: isQr ? '0.2' : '0', max: isQr ? '3' : '10' });
    const fields = [];
    let collect;

    if (isQr) {
        const code = textInput({ maxLength: 60, placeholder: 'G-C10', value: point?.checkpoint_code ?? '' });
        const facing = numberInput(Math.round(state.pending.yaw), { step: '1', min: '0', max: '359' });
        facing.addEventListener('input', () => {
            state.pending.yaw = Number(facing.value) || 0;
            setHeading(heading());
        });
        name.placeholder = 'e.g. Pillar by the lift, south face';
        fields.push(field('QR code', code), field('Where (label)', name), field('Height above the floor (m)', height), field('Facing (degrees: 0 east, 90 north, 180 west, 270 south)', facing));
        collect = () => ({ kind: 'checkpoint', checkpoint_code: code.value.trim(), qr_yaw: Number(facing.value) || 0 });
        state.pending.facingInput = facing;
    } else {
        const kind = document.createElement('select');
        kind.innerHTML = '<option value="node">Node (a place to go)</option><option value="shop_entrance">Shop entrance</option><option value="facility">Facility</option>';
        kind.value = state.pending.kind;
        const unit = textInput({ maxLength: 40, placeholder: 'G-05', value: point?.unit_code ?? '' });
        const [primaryLabel, primary] = checkbox('Main door', point?.is_primary ?? false);
        const type = document.createElement('select');
        type.innerHTML = Object.entries(FACILITY_TYPES).map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
        type.value = point?.facility_type ?? 'toilet';
        const unitField = field('Shop unit code', unit);
        const typeField = field('Facility type', type);
        const sync = () => {
            state.pending.kind = kind.value;
            unitField.hidden = kind.value !== 'shop_entrance';
            primaryLabel.hidden = kind.value !== 'shop_entrance';
            typeField.hidden = kind.value !== 'facility';
            name.placeholder = { node: 'e.g. Kiosk by the entrance', shop_entrance: 'e.g. Gadget Hub side door', facility: 'e.g. Washroom near the lift' }[kind.value];
        };
        kind.addEventListener('change', sync);
        sync();
        fields.push(field('What is it', kind), field('Name', name), unitField, primaryLabel, typeField, field('Marker height above the floor (m)', height));
        collect = () => {
            const body = { kind: kind.value };
            if (kind.value === 'shop_entrance') {
                Object.assign(body, { unit_code: unit.value.trim(), is_primary: primary.checked });
            }
            if (kind.value === 'facility') {
                body.facility_type = type.value;
            }
            return body;
        };
    }

    const heading = () => `${point ? `Edit ${point.kind_label.toLowerCase()} ${point.key}` : isQr ? 'New QR code' : 'New point'} · ${where(state.pending)}`;
    const save = button(point ? 'Save changes' : isQr ? 'Save QR code' : 'Save point', async () => {
        save.disabled = true;
        try {
            const body = { ...collect(), name: name.value.trim(), floor_code: state.pending.floorCode, x: state.pending.x, y: state.pending.y, height: Number(height.value) };
            const saved = (await callEdits(point ? point.update_url : service.endPointsUrl, point ? 'PATCH' : 'POST', body)).data;
            closeEditForm();
            await afterSave(saved, Boolean(point));
        } catch (error) {
            showEditError(error.message);
            save.disabled = false;
        }
    }, 'primary');
    const move = button(isQr ? 'Move (click a wall)' : 'Move (click the floor)', () => {
        setTool('move-edit');
    });
    showForm(heading(), fields, [save, move, button('Cancel', closeEditForm)]);
    state.pending.heading = heading;
    (point ? name : isQr ? fields[0].querySelector('input') : name).focus();
}

/** "Move": the next click gives the point (or QR code) a new place. */
function moveEdited(surface) {
    const pending = state.pending;
    if (!pending) {
        return;
    }
    if (pending.kind === 'checkpoint') {
        if (A.mountForNormal(surface.normal) !== 'wall') {
            showEditError('Click a wall or pillar face for the QR code.');
            return;
        }
        pending.yaw = A.roundYaw(A.threeDirectionToYaw(surface.normal));
        if (pending.facingInput) {
            pending.facingInput.value = String(Math.round(pending.yaw));
        }
    }
    const position = A.threeToMall(surface.point);
    Object.assign(pending, { floorCode: surface.floorCode, x: position.x, y: position.y });
    showEditError('');
    setTool(null);
    showPendingPin();
    setHeading(pending.heading());
}

function showPendingPin() {
    const pending = state.pending;
    clearPreview();
    const pin = new THREE.Mesh(new THREE.SphereGeometry(0.2, 16, 12), new THREE.MeshBasicMaterial({ color: KIND_COLOURS[pending.kind] ?? KIND_COLOURS.node, transparent: true, opacity: 0.75 }));
    pin.position.copy(v3({ x: pending.x, y: pending.y, z: floorLevel(pending.floorCode) + 0.2 }));
    scene.add(pin);
    state.previewObjects.push(pin);
}

// --- Paths: draw, redraw, edit and merge ----------------------------------

/** Start drawing a new path, or redraw an existing one from its corners. */
function startPath(path = null) {
    const floorCode = path?.floor_code ?? null;
    state.pending = {
        path: true,
        edit: path,
        floorCode,
        corners: path ? path.points.map(([x, y]) => ({ x, y, z: floorLevel(floorCode), snapped: false })) : [],
    };
    showEditError('');
    setTool('path');
    const name = textInput({ maxLength: 120, placeholder: '(optional)', value: path?.name ?? '' });
    const [stepFreeLabel, stepFree] = checkbox('Step-free', path ? path.step_free : true);
    const [oneWayLabel, oneWay] = checkbox('One way (first corner to last)', path ? path.one_way : false);
    const counter = Object.assign(document.createElement('div'), { id: 'path-counter', className: 'muted' });
    const finish = button(path ? 'Save corners' : 'Finish path', async () => {
        if (state.pending.corners.length < 2) {
            showEditError('Click at least two corners on the floor.');
            return;
        }
        finish.disabled = true;
        try {
            const body = {
                floor_code: state.pending.floorCode,
                points: state.pending.corners.map((corner) => [corner.x, corner.y]),
                step_free: stepFree.checked,
                one_way: oneWay.checked,
                name: name.value.trim() || null,
            };
            const saved = (await callEdits(path ? path.update_url : service.pathsUrl, path ? 'PATCH' : 'POST', body)).data;
            closeEditForm();
            message(`Path ${saved.key} ${path ? 'saved' : 'added'}: ${saved.length_m} m with ${saved.corners} corners${saved.joined ? '' : ' (not joined to the rest of the map yet)'}.`);
            await loadEdits();
        } catch (error) {
            showEditError(error.message);
            finish.disabled = false;
        }
    }, 'primary');
    const undo = button('Undo last corner', () => {
        state.pending.corners.pop();
        if (!state.pending.corners.length) {
            state.pending.floorCode = null;
        }
        drawPathPreview();
    });
    const title = path
        ? `Redraw path ${path.key}: click more corners, Undo to remove the last one, then Save corners.`
        : 'New path: click the floor at each corner. A corner within 0.5 m of a node, a path end or a walkway joins it.';
    showForm(title, [counter, field('Name', name), stepFreeLabel, oneWayLabel], [finish, undo, button('Cancel', closeEditForm)]);
    drawPathPreview();
}

/** The nearest node, path corner or added link end within 0.5 m on the floor, if any. */
function snapTarget(floorCode, x, y) {
    const candidates = [];
    for (const node of state.pkg?.nodes ?? []) {
        if (node.floor_code === floorCode) {
            candidates.push({ x: node.x_m, y: node.y_m, what: node.node_code });
        }
    }
    for (const path of state.paths) {
        if (path.floor_code === floorCode && path.key !== state.pending?.edit?.key) {
            path.points.forEach(([px, py], index) => candidates.push({ x: px, y: py, what: index === 0 || index === path.points.length - 1 ? `the end of path ${path.key}` : `a corner of path ${path.key}` }));
        }
    }
    for (const point of state.points) {
        if (point.floor_code === floorCode && point.kind !== 'checkpoint') {
            candidates.push({ x: point.position.x, y: point.position.y, what: `${point.name} (${point.key})` });
        }
    }
    let best = null;
    for (const candidate of candidates) {
        const distance = Math.hypot(candidate.x - x, candidate.y - y);
        if (distance <= SNAP_M && (!best || distance < best.distance)) {
            best = { ...candidate, distance };
        }
    }
    return best;
}

function addPathCorner(surface) {
    const pending = state.pending;
    if (pending.floorCode && surface.floorCode !== pending.floorCode) {
        showEditError(`A path stays on one floor (floor ${pending.floorCode}). Finish it, then draw another.`);
        return;
    }
    showEditError('');
    pending.floorCode = surface.floorCode;
    const corner = A.threeToMall(surface.point);
    const target = snapTarget(surface.floorCode, corner.x, corner.y);
    pending.corners.push({
        x: target ? target.x : corner.x,
        y: target ? target.y : corner.y,
        z: floorLevel(surface.floorCode),
        snapped: Boolean(target),
        joins: target?.what ?? null,
    });
    drawPathPreview();
}

function drawPathPreview() {
    clearPreview();
    const corners = state.pending?.corners ?? [];
    let length = 0;
    corners.forEach((corner, index) => {
        const dot = new THREE.Mesh(new THREE.SphereGeometry(corner.snapped ? 0.18 : 0.12, 12, 8), new THREE.MeshBasicMaterial({ color: corner.snapped ? 0xfacc15 : 0x22d3ee }));
        dot.position.copy(v3({ ...corner, z: corner.z + 0.05 }));
        scene.add(dot);
        state.previewObjects.push(dot);
        if (index > 0) {
            length += Math.hypot(corner.x - corners[index - 1].x, corner.y - corners[index - 1].y);
        }
    });
    if (corners.length > 1) {
        const line = new THREE.Line(new THREE.BufferGeometry().setFromPoints(corners.map((c) => v3({ ...c, z: c.z + 0.05 }))), new THREE.LineBasicMaterial({ color: 0x22d3ee }));
        scene.add(line);
        state.previewObjects.push(line);
    }
    const counter = $('path-counter');
    if (counter) {
        const last = corners[corners.length - 1];
        counter.textContent = corners.length
            ? `${corners.length} corner${corners.length > 1 ? 's' : ''} · ${length.toFixed(1)} m on floor ${state.pending.floorCode}${last?.joins ? ` · last corner joins ${last.joins}` : ''}`
            : 'Click the floor at the first corner.';
    }
}

/** Edit a path's name and flags, redraw its corners, or merge another path into it. */
function openPathForm(path) {
    closeEditForm();
    showEditError('');
    state.pending = { pathEdit: path };
    const name = textInput({ maxLength: 120, placeholder: '(optional)', value: path.name ?? '' });
    const [stepFreeLabel, stepFree] = checkbox('Step-free', path.step_free);
    const [oneWayLabel, oneWay] = checkbox('One way (first corner to last)', path.one_way);
    const meets = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1]) <= SNAP_M;
    const partners = state.paths.filter((other) => other.key !== path.key && other.floor_code === path.floor_code
        && path.ends.some((end) => other.ends.some((otherEnd) => meets(end, otherEnd))));
    const mergeWith = document.createElement('select');
    mergeWith.innerHTML = partners.length
        ? partners.map((other) => `<option value="${other.key}">${other.key}${other.name ? ` · ${other.name}` : ''} (${other.length_m} m)</option>`).join('')
        : '<option value="">No other path meets this one at an end</option>';
    mergeWith.disabled = !partners.length;

    const save = button('Save changes', async () => {
        save.disabled = true;
        try {
            await callEdits(path.update_url, 'PATCH', { name: name.value.trim() || null, step_free: stepFree.checked, one_way: oneWay.checked });
            closeEditForm();
            message(`Path ${path.key} saved.`);
            await loadEdits();
        } catch (error) {
            showEditError(error.message);
            save.disabled = false;
        }
    }, 'primary');
    const merge = button('Merge', async () => {
        if (!mergeWith.value) {
            return;
        }
        merge.disabled = true;
        try {
            const merged = (await callEdits(path.merge_url, 'POST', { with: mergeWith.value })).data;
            closeEditForm();
            message(`Path ${mergeWith.value} merged into ${merged.key}: ${merged.length_m} m with ${merged.corners} corners.`);
            await loadEdits();
        } catch (error) {
            showEditError(error.message);
            merge.disabled = false;
        }
    });
    merge.disabled = !partners.length;
    showForm(`Edit path ${path.key} · floor ${path.floor_code} · ${path.corners} corners · ${path.length_m} m`, [
        field('Name', name), stepFreeLabel, oneWayLabel,
        field('Merge with a path that meets this one at an end', mergeWith),
    ], [save, button('Redraw corners', () => startPath(path)), merge, button('Cancel', closeEditForm)]);
}

// --- Shared ---------------------------------------------------------------

function clearPreview() {
    for (const object of state.previewObjects) {
        scene.remove(object);
    }
    state.previewObjects = [];
}

async function afterSave(saved, edited) {
    if (!edited) {
        showNewKey(saved);
    }
    message(`${saved.kind_label} “${saved.name}” ${edited ? 'saved' : `added with key ${saved.key}`}.${saved.link_m === null && !saved.connected ? ' It is not joined to a walkway yet: draw a path to it with ＋ Path.' : ''}`);
    await loadEdits();
}

function showNewKey(point) {
    const parts = [
        Object.assign(document.createElement('span'), { className: 'muted', textContent: `${point.kind_label} “${point.name}” added. Key:` }),
        Object.assign(document.createElement('span'), { className: 'key', textContent: point.key }),
        Object.assign(document.createElement('span'), { className: 'muted', textContent: `Route to it with ${point.place}` }),
    ];
    if (point.link_m === null && !point.connected) {
        parts.push(Object.assign(document.createElement('span'), { className: 'warn', textContent: 'Not joined to a walkway yet: draw a path to it.' }));
    }
    if (point.qr_url) {
        parts.push(Object.assign(document.createElement('span'), { className: 'muted', textContent: `QR address: ${point.qr_url}` }));
    }
    $('point-new').replaceChildren(...parts);
    $('point-new').hidden = false;
}

function editLabel(text, colour) {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 64;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = `#${colour.toString(16).padStart(6, '0')}`;
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
    sprite.renderOrder = 4;
    return sprite;
}

function buildEditObjects() {
    for (const object of state.editObjects) {
        object.parent?.remove(object);
    }
    state.editObjects = [];
    const add = (floorCode, object) => {
        const entry = state.floors.get(floorCode);
        if (entry) {
            entry.group.add(object);
            state.editObjects.push(object);
        }
    };

    for (const edge of state.overlay?.edges ?? []) {
        const colour = edge.closed ? 0xe5484d : edge.kind === 'path' ? 0x22d3ee : 0xd946ef;
        const material = edge.kind === 'path'
            ? new THREE.LineBasicMaterial({ color: colour })
            : new THREE.LineDashedMaterial({ color: colour, dashSize: 0.15, gapSize: 0.1 });
        const line = new THREE.Line(new THREE.BufferGeometry().setFromPoints([v3({ ...edge.from, z: edge.from.z + 0.06 }), v3({ ...edge.to, z: edge.to.z + 0.06 })]), material);
        line.computeLineDistances();
        add(edge.floor_code, line);
    }

    for (const point of state.points) {
        const colour = point.problem ? 0x9ca3af : KIND_COLOURS[point.kind] ?? KIND_COLOURS.node;
        if (point.kind === 'checkpoint' && point.qr) {
            const qr = new THREE.Group();
            qr.add(
                new THREE.Mesh(new THREE.PlaneGeometry(0.18, 0.18), new THREE.MeshBasicMaterial({ color: colour, side: THREE.DoubleSide })),
                new THREE.ArrowHelper(new THREE.Vector3(0, 0, 1), new THREE.Vector3(0, 0, 0.01), 0.6, 0x4f8cff, 0.15, 0.08),
            );
            const label = editLabel(`${point.key} · ${point.checkpoint_code}`, colour);
            label.position.set(0, 0.35, 0);
            qr.add(label);
            qr.position.copy(v3({ x: point.qr.x_m, y: point.qr.y_m, z: point.qr.z_m }));
            qr.rotation.y = A.yawToRotationY(point.qr.yaw_deg);
            add(point.floor_code, qr);
            continue;
        }
        const height = Math.max(0.05, point.height_m ?? DEFAULT_HEIGHT);
        const group = new THREE.Group();
        const pin = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, height, 12), new THREE.MeshBasicMaterial({ color: colour }));
        pin.position.y = height / 2;
        const head = new THREE.Mesh(new THREE.SphereGeometry(0.2, 20, 14), new THREE.MeshBasicMaterial({ color: colour }));
        head.position.y = height;
        const label = editLabel(`${point.key} · ${point.name}`, colour);
        label.position.set(0, height + 0.3, 0);
        group.add(pin, head, label);
        group.position.copy(v3(point.position));
        add(point.floor_code, group);
    }
}

function listItem(keyText, metaText, metaClass, actions, onClick) {
    const li = document.createElement('li');
    const key = Object.assign(document.createElement('span'), { className: 'key', textContent: keyText });
    const box = Object.assign(document.createElement('span'), { className: 'actions' });
    box.append(...actions);
    const meta = Object.assign(document.createElement('span'), { className: metaClass, textContent: metaText });
    li.append(key, box, meta);
    li.addEventListener('click', onClick);
    return li;
}

function removeButton(question, url, done) {
    return button('Delete', async (event) => {
        event.stopPropagation();
        if (!window.confirm(question)) {
            return;
        }
        try {
            await callEdits(url, 'DELETE');
            closeEditForm();
            message(done);
            await loadEdits();
        } catch (error) {
            showEditError(error.message);
        }
    });
}

function renderEdits() {
    $('points-count').textContent = state.points.length ? `(${state.points.length})` : '';
    $('paths-count').textContent = state.paths.length ? `(${state.paths.length})` : '';

    const pointItems = state.points.map((point) => {
        const copy = button('Copy', (event) => {
            event.stopPropagation();
            navigator.clipboard?.writeText(point.key).then(() => { copy.textContent = 'Copied'; });
        });
        const edit = button('Edit', (event) => {
            event.stopPropagation();
            closeEditForm();
            openPointForm(point, null);
            focusOn(point);
        });
        const detail = { shop_entrance: ` · unit ${point.unit_code}${point.is_primary ? ', main door' : ''}`, facility: ` · ${point.facility_type}`, checkpoint: ` · ${point.checkpoint_code}` }[point.kind] ?? '';
        const status = point.problem ? ` · not applied: ${point.problem}` : point.connected ? '' : ' · not connected: draw a path to it';
        return listItem(
            point.key,
            `${point.kind_label}${detail} · ${point.name} · floor ${point.floor_code} · ${Number(point.height_m).toFixed(2)} m high${status}`,
            point.problem || !point.connected ? 'warn' : 'muted',
            [edit, copy, removeButton(`Delete ${point.name} (${point.key})? Links that use its key stop working.`, point.update_url, `${point.kind_label} ${point.key} deleted.`)],
            () => focusOn(point),
        );
    });
    if (!pointItems.length) {
        pointItems.push(Object.assign(document.createElement('li'), { className: 'muted', textContent: 'None yet. Use “＋ Point” or “＋ QR code”.' }));
    }
    $('points').replaceChildren(...pointItems);

    const pathItems = state.paths.map((path) => listItem(
        path.key,
        `${path.name ?? 'Path'} · floor ${path.floor_code} · ${path.corners} corners · ${path.length_m} m${path.step_free ? '' : ' · not step-free'}${path.one_way ? ' · one way' : ''}${path.problem ? ` · ${path.problem}` : path.joined ? '' : ' · not joined to the map'}`,
        path.problem || !path.joined ? 'warn' : 'muted',
        [
            button('Edit', (event) => {
                event.stopPropagation();
                openPathForm(path);
            }),
            removeButton(`Delete path ${path.key}?`, path.update_url, `Path ${path.key} deleted.`),
        ],
        () => focusOn({ position: { x: path.points[0][0], y: path.points[0][1], z: floorLevel(path.floor_code) } }),
    ));
    if (!pathItems.length) {
        pathItems.push(Object.assign(document.createElement('li'), { className: 'muted', textContent: 'None yet. Use “＋ Path”.' }));
    }
    $('paths').replaceChildren(...pathItems);
    buildEditObjects();
}

// ---------------------------------------------------------------------------
// The navigation graph itself (ISM-114): drawn as it is now on the server;
// with "✎ Graph" an administrator clicks a node or a walkway and moves,
// connects, removes or changes it. Every change can be undone.
// ---------------------------------------------------------------------------

const GRAPH_COLOURS = { walk: 0xe5484d, stairs: 0xf59e0b, closed: 0x6b7280, connector: 0x7e3bc4, selected: 0xfacc15 };
const GRAPH_NODE_COLOURS = { junction: 0xe5484d, checkpoint: 0x1c63d9, entrance: 0x16954a, connector: 0x7e3bc4, facility: 0xe67e19, endpoint: 0xd946ef };
const PICK_M = 0.5;

async function loadGraph() {
    try {
        state.graph = await callEdits(service.graphUrl);
    } catch (error) {
        showEditError(error.message);
        return;
    }
    state.graphNodes = new Map(state.graph.nodes.map((node) => [node.code, node]));
    renderGraph();
    renderGraphEdits();
}

function renderGraph() {
    for (const entry of state.floors.values()) {
        entry.graph.clear();
    }
    const point = (node, lift = 0.04) => v3({ x: node.x, y: node.y, z: node.z + lift });
    for (const edge of state.graph.edges) {
        const a = state.graphNodes.get(edge.from);
        const b = state.graphNodes.get(edge.to);
        const entry = a && b ? state.floors.get(a.floor) : null;
        if (!entry) {
            continue;
        }
        const colour = edge.closed ? GRAPH_COLOURS.closed : edge.connector ? GRAPH_COLOURS.connector : edge.step_free ? GRAPH_COLOURS.walk : GRAPH_COLOURS.stairs;
        const material = edge.direction === 'both' && !edge.closed
            ? new THREE.LineBasicMaterial({ color: colour })
            : new THREE.LineDashedMaterial({ color: colour, dashSize: 0.25, gapSize: 0.15 });
        const line = new THREE.Line(new THREE.BufferGeometry().setFromPoints([point(a), point(b)]), material);
        line.computeLineDistances();
        entry.graph.add(line);
        if (edge.direction !== 'both' && !edge.connector) {
            const [from, to] = edge.direction === 'forward' ? [a, b] : [b, a];
            const middle = v3({ x: (from.x + to.x) / 2, y: (from.y + to.y) / 2, z: from.z + 0.06 });
            const arrow = new THREE.ArrowHelper(v3({ x: to.x - from.x, y: to.y - from.y, z: 0 }).normalize(), middle, 0.5, colour, 0.3, 0.18);
            entry.graph.add(arrow);
        }
    }
    for (const node of state.graph.nodes) {
        const entry = state.floors.get(node.floor);
        if (!entry || node.type === 'endpoint') {
            continue;
        }
        const dot = new THREE.Mesh(new THREE.SphereGeometry(node.type === 'junction' ? 0.09 : 0.13, 10, 8), new THREE.MeshBasicMaterial({ color: GRAPH_NODE_COLOURS[node.type] ?? GRAPH_NODE_COLOURS.junction }));
        dot.position.copy(point(node, 0.05));
        entry.graph.add(dot);
    }
    applyVisibility();
}

function renderGraphEdits() {
    const edits = state.graph?.edits ?? [];
    $('graph-count').textContent = edits.length ? `(${edits.length})` : '';
    const items = edits.map((edit) => listItem(
        edit.key,
        `${edit.description}${edit.problem ? ` · not applied: ${edit.problem}` : ''}`,
        edit.problem ? 'warn' : 'muted',
        [
            button('Edit', (event) => {
                event.stopPropagation();
                openGraphEditForm(edit);
            }),
            button('Undo', async (event) => {
                event.stopPropagation();
                try {
                    await callEdits(edit.undo_url, 'DELETE');
                    closeEditForm();
                    message(`Undone: ${edit.description}.`);
                    await reloadAll();
                } catch (error) {
                    showEditError(error.message);
                }
            }),
        ],
        () => showGraphEdit(edit, true),
    ));
    if (!items.length) {
        items.push(Object.assign(document.createElement('li'), { className: 'muted', textContent: 'No changes. Use “✎ Graph” and click a node or a walkway.' }));
    }
    $('graph-edits').replaceChildren(...items);
}

async function reloadAll() {
    await loadEdits();
    await loadGraph();
}

/** The node or walkway nearest to a click on a floor, within half a metre. */
function pickGraph(surface) {
    const at = A.threeToMall(surface.point);
    let best = null;
    for (const node of state.graph?.nodes ?? []) {
        if (node.floor !== surface.floorCode) {
            continue;
        }
        const distance = Math.hypot(node.x - at.x, node.y - at.y);
        if (distance <= PICK_M && (!best || distance < best.distance)) {
            best = { kind: 'node', node, distance };
        }
    }
    if (best) {
        return best;
    }
    for (const edge of state.graph?.edges ?? []) {
        const a = state.graphNodes.get(edge.from);
        const b = state.graphNodes.get(edge.to);
        if (!a || !b || a.floor !== surface.floorCode || b.floor !== surface.floorCode) {
            continue;
        }
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        const lengthSquared = dx * dx + dy * dy || 1e-9;
        const t = Math.max(0, Math.min(1, ((at.x - a.x) * dx + (at.y - a.y) * dy) / lengthSquared));
        const distance = Math.hypot(at.x - (a.x + t * dx), at.y - (a.y + t * dy));
        if (distance <= PICK_M && (!best || distance < best.distance)) {
            best = { kind: 'edge', edge, a, b, distance };
        }
    }
    return best;
}

function highlight(objects) {
    clearPreview();
    for (const object of objects) {
        scene.add(object);
        state.previewObjects.push(object);
    }
}

function highlightNode(node) {
    const ring = new THREE.Mesh(new THREE.SphereGeometry(0.22, 16, 12), new THREE.MeshBasicMaterial({ color: GRAPH_COLOURS.selected, transparent: true, opacity: 0.8 }));
    ring.position.copy(v3({ x: node.x, y: node.y, z: node.z + 0.08 }));
    return ring;
}

function highlightEdge(a, b) {
    const line = new THREE.Line(new THREE.BufferGeometry().setFromPoints([v3({ x: a.x, y: a.y, z: a.z + 0.1 }), v3({ x: b.x, y: b.y, z: b.z + 0.1 })]), new THREE.LineBasicMaterial({ color: GRAPH_COLOURS.selected }));
    return line;
}

async function sendGraphChange(body, done) {
    try {
        await callEdits(service.graphUrl, 'POST', body);
        closeEditForm();
        message(done);
        await reloadAll();
    } catch (error) {
        showEditError(error.message);
    }
}

function graphClick(surface) {
    const picked = pickGraph(surface);
    if (!picked) {
        showEditError('No node or walkway within half a metre of that click. Click on a red line or dot.');
        return;
    }
    showEditError('');
    if (picked.kind === 'node') {
        openNodeForm(picked.node);
    } else {
        openEdgeForm(picked.edge, picked.a, picked.b);
    }
}

function openNodeForm(node) {
    state.pending = { graphNode: node, kind: node.type };
    highlight([highlightNode(node)]);
    const [stepFreeLabel, stepFree] = checkbox('New walkway is step-free', true);
    const [oneWayLabel, oneWay] = checkbox('New walkway is one way (from this node)');
    const remove = button('Remove node', () => {
        if (window.confirm(`Remove node ${node.code} and its walkways?`)) {
            sendGraphChange({ action: 'remove_node', node_code: node.code }, `Node ${node.code} removed.`);
        }
    }, 'danger');
    remove.disabled = Boolean(node.holds);
    const fields = [];
    if (node.holds) {
        fields.push(Object.assign(document.createElement('div'), { className: 'warn', textContent: `It ${node.holds}, so it cannot be removed here.` }));
    }
    fields.push(stepFreeLabel, oneWayLabel);
    showForm(`Node ${node.code} · ${node.type} · floor ${node.floor} · x ${node.x.toFixed(2)} · y ${node.y.toFixed(2)}`, fields, [
        button('Move (click the new place)', () => {
            state.pending.graphAction = 'move';
            setTool('graph-move');
        }),
        button('Connect to… (click another node)', () => {
            state.pending.graphAction = 'connect';
            state.pending.connectOptions = () => ({ step_free: stepFree.checked, one_way: oneWay.checked });
            setTool('graph-connect');
        }),
        remove,
        button('Close', closeEditForm),
    ]);
}

function openEdgeForm(edge, a, b) {
    state.pending = { graphEdge: edge };
    highlight([highlightEdge(a, b)]);
    if (edge.connector) {
        showForm(`Lift, escalator or stairs ${edge.from} – ${edge.to} · ${edge.length_m} m`, [
            Object.assign(document.createElement('div'), { className: 'muted', textContent: 'Lifts, escalators and stairs come from the map package (Blender). Close one for a while on the Closures page.' }),
        ], [button('Close', closeEditForm)]);
        return;
    }
    const [stepFreeLabel, stepFree] = checkbox('Step-free', edge.step_free);
    const direction = document.createElement('select');
    direction.innerHTML = `<option value="both">Both ways</option><option value="forward">One way ${edge.from} → ${edge.to}</option><option value="backward">One way ${edge.to} → ${edge.from}</option>`;
    direction.value = edge.direction;
    const save = button('Save walkway', () => {
        const body = { action: 'edit_edge', from_code: edge.from, to_code: edge.to };
        if (stepFree.checked !== edge.step_free) {
            body.step_free = stepFree.checked;
        }
        if (direction.value !== edge.direction) {
            body.direction = direction.value;
        }
        if (body.step_free === undefined && body.direction === undefined) {
            showEditError('Nothing changed.');
            return;
        }
        sendGraphChange(body, `Walkway ${edge.from} – ${edge.to} changed.`);
    }, 'primary');
    const remove = button('Remove walkway', () => {
        if (window.confirm(`Remove the walkway ${edge.from} – ${edge.to}?`)) {
            sendGraphChange({ action: 'remove_edge', from_code: edge.from, to_code: edge.to }, `Walkway ${edge.from} – ${edge.to} removed.`);
        }
    }, 'danger');
    showForm(`Walkway ${edge.from} – ${edge.to} · ${edge.length_m} m${edge.closed ? ' · closed now' : ''}`, [stepFreeLabel, field('Direction', direction)], [save, remove, button('Close', closeEditForm)]);
}

/**
 * Show where a change to the graph acts: its node, or the walkway between its
 * two nodes (where it was, for a removed one). Returns whether it was found.
 */
function showGraphEdit(edit, focus = false) {
    const nodes = (edit.node_code ? [edit.node_code] : [edit.from_code, edit.to_code]).map((code) => state.graphNodes.get(code));
    if (nodes.some((node) => !node)) {
        clearPreview();
        if (focus) {
            message(edit.action === 'remove_node' ? `Node ${edit.node_code} is removed by this change, so it is not drawn.` : `This change names a node that is not on the map now${edit.problem ? `: ${edit.problem}` : '.'}`);
        }
        return false;
    }
    highlight(nodes.length === 1 ? [highlightNode(nodes[0])] : [highlightEdge(nodes[0], nodes[1]), highlightNode(nodes[0]), highlightNode(nodes[1])]);
    if (focus) {
        const [a, b = a] = nodes;
        focusOn({ position: { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2, z: (a.z + b.z) / 2 } });
    }
    return true;
}

/** A node code box with a "Pick" button: the next click on a node fills it. */
function nodeCodeField(labelText, value) {
    const input = textInput({ maxLength: 80, value: value ?? '' });
    input.setAttribute('list', 'graph-node-codes');
    input.style.flex = '1';
    const pick = button('Pick', () => {
        state.pending.pickTarget = input;
        setTool('graph-pick');
    });
    const row = document.createElement('div');
    row.className = 'row';
    row.append(input, pick);
    return [field(labelText, row), input];
}

function fillNodeCodes() {
    let list = $('graph-node-codes');
    if (!list) {
        list = Object.assign(document.createElement('datalist'), { id: 'graph-node-codes' });
        document.body.append(list);
    }
    list.replaceChildren(...(state.graph?.nodes ?? []).map((node) => Object.assign(document.createElement('option'), { value: node.code, label: `${node.type} · floor ${node.floor}` })));
}

/** Edit a change to the graph: its nodes, its new place, its settings. */
function openGraphEditForm(edit) {
    closeEditForm();
    showEditError('');
    state.pending = { graphEdit: edit };
    fillNodeCodes();
    showGraphEdit(edit);
    const data = edit.data ?? {};
    const fields = [];
    const actions = [];
    let collect;

    if (edit.action === 'move_node' || edit.action === 'remove_node') {
        const [nodeField, node] = nodeCodeField('Node', edit.node_code);
        fields.push(nodeField);
        collect = () => ({ node_code: node.value.trim() });
        if (edit.action === 'move_node') {
            const x = numberInput(data.x ?? 0, { step: '0.01' });
            const y = numberInput(data.y ?? 0, { step: '0.01' });
            fields.push(field('New x (m)', x), field('New y (m)', y));
            state.pending.moveInputs = { node, x, y };
            collect = () => ({ node_code: node.value.trim(), x: Number(x.value), y: Number(y.value) });
            actions.push(button('Pick the new place (click the floor)', () => setTool('graph-move')));
        }
    } else {
        const [fromField, from] = nodeCodeField('From node', edit.from_code);
        const [toField, to] = nodeCodeField('To node', edit.to_code);
        fields.push(fromField, toField);
        const codes = () => ({ from_code: from.value.trim(), to_code: to.value.trim() });
        collect = codes;
        if (edit.action === 'connect') {
            const [stepFreeLabel, stepFree] = checkbox('Step-free', data.step_free ?? true);
            const [oneWayLabel, oneWay] = checkbox('One way (from → to)', data.one_way ?? false);
            fields.push(stepFreeLabel, oneWayLabel);
            collect = () => ({ ...codes(), step_free: stepFree.checked, one_way: oneWay.checked });
        } else if (edit.action === 'edit_edge') {
            const direction = document.createElement('select');
            direction.innerHTML = '<option value="">(unchanged)</option><option value="both">Both ways</option><option value="forward">One way, from → to</option><option value="backward">One way, to → from</option>';
            direction.value = data.direction ?? '';
            const stepFree = document.createElement('select');
            stepFree.innerHTML = '<option value="">(unchanged)</option><option value="1">Step-free</option><option value="0">Not step-free (stairs)</option>';
            stepFree.value = data.step_free === undefined ? '' : data.step_free ? '1' : '0';
            fields.push(field('Direction', direction), field('Step-free', stepFree));
            collect = () => ({ ...codes(), direction: direction.value || null, step_free: stepFree.value === '' ? null : stepFree.value === '1' });
        }
    }
    if (edit.problem) {
        fields.unshift(Object.assign(document.createElement('div'), { className: 'warn', textContent: `Not applied now: ${edit.problem}` }));
    }

    const save = button('Save change', async () => {
        save.disabled = true;
        try {
            const saved = (await callEdits(edit.update_url, 'PATCH', collect())).data;
            closeEditForm();
            message(`Change ${saved.key} saved: ${saved.description}.`);
            await reloadAll();
        } catch (error) {
            showEditError(error.message);
            save.disabled = false;
        }
    }, 'primary');
    showForm(`Edit change ${edit.key} · ${edit.description}`, fields, [save, ...actions, button('Cancel', closeEditForm)]);
}

/** "Pick": the next click on a node puts its code in the box. */
function graphPick(surface) {
    const input = state.pending?.pickTarget;
    setTool(null);
    const picked = pickGraph(surface);
    if (!input || !picked || picked.kind !== 'node') {
        showEditError('Click on a node (a dot) to pick it.');
        return;
    }
    showEditError('');
    input.value = picked.node.code;
    highlight([highlightNode(picked.node)]);
}

function graphMove(surface) {
    const editing = state.pending?.moveInputs;
    if (editing) {
        setTool(null);
        const node = state.graphNodes.get(editing.node.value.trim());
        if (node && surface.floorCode !== node.floor) {
            showEditError(`Keep node ${node.code} on floor ${node.floor}.`);
            return;
        }
        const at = A.threeToMall(surface.point);
        editing.x.value = at.x.toFixed(2);
        editing.y.value = at.y.toFixed(2);
        showEditError('');
        const marker = highlightNode({ x: at.x, y: at.y, z: node?.z ?? floorLevel(surface.floorCode) });
        highlight(node ? [highlightNode(node), marker, highlightEdge(node, { x: at.x, y: at.y, z: node.z })] : [marker]);
        message(`New place x ${at.x.toFixed(2)}, y ${at.y.toFixed(2)}: click Save change to keep it.`);
        return;
    }
    const node = state.pending?.graphNode;
    setTool(null);
    if (!node) {
        return;
    }
    if (surface.floorCode !== node.floor) {
        showEditError(`Keep node ${node.code} on floor ${node.floor}.`);
        return;
    }
    const at = A.threeToMall(surface.point);
    sendGraphChange({ action: 'move_node', node_code: node.code, x: at.x, y: at.y }, `Node ${node.code} moved.`);
}

function graphConnect(surface) {
    const node = state.pending?.graphNode;
    const options = state.pending?.connectOptions?.() ?? {};
    setTool(null);
    const picked = pickGraph(surface);
    if (!node || !picked || picked.kind !== 'node') {
        showEditError('Click on another node (a dot) to connect to.');
        return;
    }
    sendGraphChange({ action: 'connect', from_code: node.code, to_code: picked.node.code, ...options }, `Walkway ${node.code} – ${picked.node.code} added.`);
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
    for (const id of ['add-point', 'add-qr', 'add-path', 'edit-graph', 'edits-section']) {
        $(id).hidden = false;
    }
    $('points-page').href = service.endPointsUrl;
    loadFromService().then(reloadAll).catch(fail);
} else if (packageParam) {
    loadFromUrl(packageParam).catch(fail);
} else {
    showOverlay('Open a map package: use “Open package folder…” on the left and choose the folder that contains map_package.json (for example tools/blender/work/sample/package). To save signs and advertisements to the map service instead, open the map from its Maps page.');
}

window.ismViewer = { state, scene, camera, controls, select, fitView };
