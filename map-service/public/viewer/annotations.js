/**
 * Annotation model for the ISM map viewer: shop signs and advertisements
 * placed in 3D on a map package.
 *
 * Pure functions only (no three.js, no DOM) so they run in the browser and in
 * `node --test tests/Viewer/`.
 *
 * Positions are stored in the mall frame of the map package
 * (`ism-mall-local-v1`: metres, x and y horizontal, z up). three.js and the
 * exported glTF are Y-up: three = (x, z, -y).
 */

export const FORMAT = 'ism-map-annotations';
export const FORMAT_VERSION = 1;

export const KINDS = {
    shop_sign: { label: 'Shop sign', aspect: 4, width: 2.4, background: '#1f2a44', color: '#ffffff', floorLift: 2.2 },
    advertisement: { label: 'Advertisement', aspect: 2 / 3, width: 0.8, background: '#ffffff', color: '#1b1b1b', floorLift: 0.1 },
};

export const MOUNTS = ['wall', 'floor'];

const HEX = /^#[0-9a-fA-F]{6}$/;
const MAX_TEXT = 60;
const MAX_SUBTITLE = 80;
const MAX_IMAGE_CHARS = 1_500_000;

/** Mall point {x, y, z} to a three.js position array. */
export function mallToThree(point) {
    return [point.x, point.z, -point.y];
}

/** three.js position (array or {x, y, z}) to a mall point. */
export function threeToMall(position) {
    const [x, y, z] = Array.isArray(position) ? position : [position.x, position.y, position.z];
    return { x, y: -z, z: y };
}

/** Normalise an angle in degrees to [0, 360). */
export function normaliseDegrees(degrees) {
    const value = ((degrees % 360) + 360) % 360;
    return value > 360 - 1e-9 ? 0 : value;
}

/** Whole degrees in [0, 360), for the facing slider and storage. */
export function roundYaw(degrees) {
    return Math.round(normaliseDegrees(degrees)) % 360;
}

/**
 * Mall yaw (degrees counterclockwise from +x, the direction a panel faces) to
 * the three.js rotation.y that turns a plane's +Z normal that way.
 */
export function yawToRotationY(yawDeg) {
    const radians = (yawDeg * Math.PI) / 180;
    return Math.atan2(Math.cos(radians), -Math.sin(radians));
}

/** Facing direction as a three.js vector (array) to a mall yaw in degrees. */
export function threeDirectionToYaw(direction) {
    const [x, , z] = Array.isArray(direction) ? direction : [direction.x, direction.y, direction.z];
    return normaliseDegrees((Math.atan2(-z, x) * 180) / Math.PI);
}

/** Height in metres of a panel of the given kind and width. */
export function panelHeight(annotation) {
    return annotation.width_m / KINDS[annotation.kind].aspect;
}

/**
 * Default height for a mount: on a wall the panel centre sits on the clicked
 * point (0); on the floor a shop sign hangs on a 2.2 m pole and an
 * advertisement stands 0.1 m above the floor.
 */
export function defaultLift(kind, mount) {
    return mount === 'floor' ? KINDS[kind].floorLift : 0;
}

/** A surface counts as a wall when its normal is mostly horizontal. */
export function mountForNormal(normal) {
    const y = Array.isArray(normal) ? normal[1] : normal.y;
    return Math.abs(y) < 0.6 ? 'wall' : 'floor';
}

/** Next free id such as ann-0007. */
export function nextId(annotations) {
    const highest = annotations.reduce((max, item) => {
        const match = /^ann-(\d+)$/.exec(item.id ?? '');
        return match ? Math.max(max, Number(match[1])) : max;
    }, 0);
    return `ann-${String(highest + 1).padStart(4, '0')}`;
}

/**
 * Create an annotation at a mall position.
 *
 * @param {'shop_sign'|'advertisement'} kind
 * @param {{x:number,y:number,z:number}} position clicked point in the mall frame (wall: panel centre before lift_m; floor: foot of the stand)
 * @param {{yaw_deg?:number, mount?:string, floor_code?:string, text?:string, now?:string, existing?:object[]}} options
 */
export function createAnnotation(kind, position, options = {}) {
    if (!KINDS[kind]) {
        throw new Error(`Unknown annotation kind: ${kind}`);
    }
    const defaults = KINDS[kind];
    const now = options.now ?? new Date().toISOString();

    return {
        id: nextId(options.existing ?? []),
        kind,
        floor_code: options.floor_code ?? null,
        unit_code: null,
        text: options.text ?? (kind === 'shop_sign' ? 'Shop name' : 'Advertisement'),
        subtitle: kind === 'advertisement' ? 'Your offer here' : '',
        background: defaults.background,
        color: defaults.color,
        image: null,
        width_m: defaults.width,
        mount: MOUNTS.includes(options.mount) ? options.mount : 'wall',
        lift_m: defaultLift(kind, MOUNTS.includes(options.mount) ? options.mount : 'wall'),
        position: { x: round(position.x), y: round(position.y), z: round(position.z) },
        yaw_deg: round(normaliseDegrees(options.yaw_deg ?? 0), 2) % 360,
        created_at: now,
        updated_at: now,
    };
}

/** Problems with one annotation; an empty list means it is valid. */
export function validateAnnotation(annotation) {
    const errors = [];
    const label = annotation?.id ?? 'annotation';

    if (!annotation || typeof annotation !== 'object') {
        return ['not an object'];
    }
    if (typeof annotation.id !== 'string' || annotation.id === '') {
        errors.push(`${label}: id is missing`);
    }
    if (!KINDS[annotation.kind]) {
        errors.push(`${label}: kind must be one of ${Object.keys(KINDS).join(', ')}`);
    }
    if (!MOUNTS.includes(annotation.mount)) {
        errors.push(`${label}: mount must be wall or floor`);
    }
    if (typeof annotation.text !== 'string' || annotation.text.trim() === '' || annotation.text.length > MAX_TEXT) {
        errors.push(`${label}: text must be 1 to ${MAX_TEXT} characters`);
    }
    if (typeof annotation.subtitle !== 'string' || annotation.subtitle.length > MAX_SUBTITLE) {
        errors.push(`${label}: subtitle must be at most ${MAX_SUBTITLE} characters`);
    }
    if (!HEX.test(annotation.background ?? '') || !HEX.test(annotation.color ?? '')) {
        errors.push(`${label}: colours must look like #1f2a44`);
    }
    if (!(annotation.width_m >= 0.2 && annotation.width_m <= 10)) {
        errors.push(`${label}: width must be between 0.2 and 10 m`);
    }
    if (!(annotation.lift_m >= -5 && annotation.lift_m <= 10)) {
        errors.push(`${label}: height must be between -5 and 10 m`);
    }
    const p = annotation.position;
    if (!p || ![p.x, p.y, p.z].every(Number.isFinite)) {
        errors.push(`${label}: position needs finite x, y and z`);
    }
    if (!Number.isFinite(annotation.yaw_deg) || annotation.yaw_deg < 0 || annotation.yaw_deg >= 360) {
        errors.push(`${label}: yaw must be in [0, 360)`);
    }
    if (annotation.unit_code !== null && (typeof annotation.unit_code !== 'string' || annotation.unit_code.length > 40)) {
        errors.push(`${label}: unit code must be empty or up to 40 characters`);
    }
    if (annotation.image !== null && !isImageReference(annotation.image)) {
        errors.push(`${label}: image must be a PNG, JPEG or WebP data URL under about 1 MB, or a link from the map service`);
    }

    return errors;
}

/**
 * An image is either a new picture (data URL, under about 1 MB) or the link
 * the map service gave for a stored picture (http(s) or same-site path).
 */
export function isImageReference(value) {
    if (typeof value !== 'string') {
        return false;
    }
    if (value.startsWith('data:')) {
        return /^data:image\/(png|jpeg|webp);base64,/.test(value) && value.length <= MAX_IMAGE_CHARS;
    }
    return /^(https?:\/\/|\/)[^\s"'<>]+$/.test(value);
}

/** Document written to annotations.json. */
export function serialise(annotations, packageInfo = {}) {
    return {
        format: FORMAT,
        format_version: FORMAT_VERSION,
        coordinate_frame: 'ism-mall-local-v1 (same as map_package.json)',
        package_generated_at: packageInfo.generated_at ?? null,
        saved_at: new Date().toISOString(),
        annotations: annotations.map((item) => ({ ...item })),
    };
}

/**
 * Read an annotations document. Invalid entries are left out and reported;
 * duplicate ids are renumbered.
 *
 * @returns {{annotations: object[], errors: string[]}}
 */
export function parse(document) {
    const data = typeof document === 'string' ? JSON.parse(document) : document;
    if (!data || data.format !== FORMAT) {
        return { annotations: [], errors: [`not an ${FORMAT} file`] };
    }
    if (data.format_version !== FORMAT_VERSION) {
        return { annotations: [], errors: [`unsupported format_version ${data.format_version}`] };
    }

    const annotations = [];
    const errors = [];
    const seen = new Set();
    for (const item of Array.isArray(data.annotations) ? data.annotations : []) {
        const problems = validateAnnotation(item);
        if (problems.length) {
            errors.push(...problems);
            continue;
        }
        const copy = { ...item, position: { ...item.position } };
        if (seen.has(copy.id)) {
            copy.id = nextId([...annotations, { id: copy.id }]);
        }
        seen.add(copy.id);
        annotations.push(copy);
    }

    return { annotations, errors };
}

/**
 * Split text into at most maxLines lines that each fit maxWidth, using a
 * measure(text) function (canvas measureText in the browser).
 */
export function wrapText(text, maxWidth, measure, maxLines = 2) {
    const words = String(text).trim().split(/\s+/).filter(Boolean);
    const lines = [];
    let current = '';
    for (const word of words) {
        const candidate = current ? `${current} ${word}` : word;
        if (measure(candidate) <= maxWidth || current === '') {
            current = candidate;
        } else {
            lines.push(current);
            current = word;
        }
    }
    if (current) {
        lines.push(current);
    }
    if (lines.length > maxLines) {
        const kept = lines.slice(0, maxLines);
        kept[maxLines - 1] = `${kept[maxLines - 1]}…`;
        return kept;
    }
    return lines;
}

function round(value, digits = 4) {
    const factor = 10 ** digits;
    return Math.round(value * factor) / factor;
}
