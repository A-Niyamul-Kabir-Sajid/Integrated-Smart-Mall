/**
 * Finds QR codes in the AR camera picture (ISM-65). WebXR raw camera access
 * ("camera-access", Chrome on Android) gives the camera picture of each frame
 * as a texture that is valid only during that frame. A few times a second it
 * is drawn into a smaller picture, read back and handed to the browser's
 * BarcodeDetector; every code found comes back with the camera pose and
 * projection of its frame, so ar-math.js can work out where the code hangs.
 *
 * Whether the texture's first row is the top or the bottom of the picture is
 * not specified, so both are tried until a code gives a good pose, and the
 * one that worked is kept.
 */
import * as M from './ar-math.js';

const VERTEX = `#version 300 es
out vec2 uv;
void main() {
    vec2 corner = vec2(float((gl_VertexID << 1) & 2), float(gl_VertexID & 2));
    uv = corner;
    gl_Position = vec4(corner * 2.0 - 1.0, 0.0, 1.0);
}`;

const FRAGMENT = `#version 300 es
precision mediump float;
uniform sampler2D picture;
in vec2 uv;
out vec4 colour;
void main() {
    colour = texture(picture, uv);
}`;

export class CodeScanner {
    /** Whether this browser can look for QR codes in a picture. */
    static async supported() {
        if (typeof window === 'undefined' || !('BarcodeDetector' in window) || typeof window.XRWebGLBinding === 'undefined') {
            return false;
        }
        try {
            return (await window.BarcodeDetector.getSupportedFormats()).includes('qr_code');
        } catch {
            return false;
        }
    }

    /**
     * @param {import('three').WebGLRenderer} renderer  the renderer of the AR session
     * @param {XRSession} session  a session started with the "camera-access" feature
     * @param {(codes: {text: string, corners: number[][]}[], snapshot: object) => boolean} onCodes
     *   called with the codes of one picture; returns whether one of them gave a good pose
     */
    constructor(renderer, session, onCodes, { shortSide = 720 } = {}) {
        this.renderer = renderer;
        this.gl = renderer.getContext();
        this.binding = new window.XRWebGLBinding(session, this.gl);
        this.detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        this.onCodes = onCodes;
        this.shortSide = shortSide;
        this.interval = 300;
        this.busy = false;
        this.last = 0;
        this.flip = null;
        this.pictures = 0;
        this.failures = 0;
    }

    /** Take a picture if it is time to; call once in every XR frame, before rendering. */
    capture(frame, referenceSpace, now) {
        if (this.busy || now - this.last < this.interval) {
            return;
        }
        const pose = frame.getViewerPose(referenceSpace);
        const view = pose?.views?.[0];
        if (!view || !view.camera) {
            return;
        }
        this.last = now;
        let texture = null;
        try {
            texture = this.binding.getCameraImage(view.camera);
        } catch {
            this.failures++;
            return;
        }
        if (!texture) {
            return;
        }
        const scale = Math.min(1, this.shortSide / Math.min(view.camera.width, view.camera.height));
        const width = Math.max(1, Math.round(view.camera.width * scale));
        const height = Math.max(1, Math.round(view.camera.height * scale));
        const target = this.renderer.getRenderTarget();
        let pixels = null;
        try {
            pixels = this.read(texture, width, height);
        } catch {
            this.failures++;
        } finally {
            // three.js keeps its own copy of the WebGL state; make it forget ours.
            this.renderer.resetState();
            this.renderer.setRenderTarget(target);
        }
        if (!pixels) {
            return;
        }
        this.pictures++;
        const snapshot = {
            width,
            height,
            pixels,
            projection: Float64Array.from(view.projectionMatrix),
            transform: Float64Array.from(view.transform.matrix),
            emulated: pose.emulatedPosition === true,
        };
        this.busy = true;
        this.detect(snapshot)
            .catch(() => { this.failures++; })
            .finally(() => { this.busy = false; });
    }

    read(texture, width, height) {
        const gl = this.gl;
        if (!this.program) {
            this.setup(gl);
        }
        if (this.width !== width || this.height !== height) {
            this.resize(gl, width, height);
        }
        gl.bindFramebuffer(gl.FRAMEBUFFER, this.framebuffer);
        gl.viewport(0, 0, width, height);
        gl.disable(gl.DEPTH_TEST);
        gl.disable(gl.STENCIL_TEST);
        gl.disable(gl.BLEND);
        gl.disable(gl.CULL_FACE);
        gl.disable(gl.SCISSOR_TEST);
        gl.colorMask(true, true, true, true);
        gl.useProgram(this.program);
        gl.bindVertexArray(this.vertexArray);
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.bindSampler(0, this.sampler);
        gl.uniform1i(this.pictureUniform, 0);
        gl.drawArrays(gl.TRIANGLES, 0, 3);
        gl.readPixels(0, 0, width, height, gl.RGBA, gl.UNSIGNED_BYTE, this.buffer);
        gl.bindSampler(0, null);
        gl.bindTexture(gl.TEXTURE_2D, null);
        gl.bindVertexArray(null);
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        return this.buffer.slice();
    }

    setup(gl) {
        const shader = (type, source) => {
            const made = gl.createShader(type);
            gl.shaderSource(made, source);
            gl.compileShader(made);
            if (!gl.getShaderParameter(made, gl.COMPILE_STATUS)) {
                throw new Error(gl.getShaderInfoLog(made) || 'shader');
            }
            return made;
        };
        const program = gl.createProgram();
        gl.attachShader(program, shader(gl.VERTEX_SHADER, VERTEX));
        gl.attachShader(program, shader(gl.FRAGMENT_SHADER, FRAGMENT));
        gl.linkProgram(program);
        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            throw new Error(gl.getProgramInfoLog(program) || 'program');
        }
        this.program = program;
        this.pictureUniform = gl.getUniformLocation(program, 'picture');
        this.vertexArray = gl.createVertexArray();
        // A sampler, so the camera texture is read with linear filtering without changing the texture itself.
        this.sampler = gl.createSampler();
        gl.samplerParameteri(this.sampler, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.samplerParameteri(this.sampler, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.samplerParameteri(this.sampler, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.samplerParameteri(this.sampler, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        this.framebuffer = gl.createFramebuffer();
        this.target = gl.createTexture();
    }

    resize(gl, width, height) {
        gl.bindTexture(gl.TEXTURE_2D, this.target);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA8, width, height, 0, gl.RGBA, gl.UNSIGNED_BYTE, null);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
        gl.bindTexture(gl.TEXTURE_2D, null);
        gl.bindFramebuffer(gl.FRAMEBUFFER, this.framebuffer);
        gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, this.target, 0);
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        this.buffer = new Uint8Array(width * height * 4);
        this.width = width;
        this.height = height;
    }

    async detect(snapshot) {
        const orders = this.flip === null ? [true, false] : [this.flip];
        for (const flip of orders) {
            const codes = await this.detector.detect(picture(snapshot, flip));
            if (codes.length === 0) {
                continue;
            }
            const found = codes.map((code) => ({
                text: code.rawValue,
                corners: code.cornerPoints.map((point) => M.pixelToNdc(point.x, point.y, snapshot.width, snapshot.height)),
            }));
            if (this.onCodes(found, snapshot)) {
                this.flip ??= flip;
                return;
            }
            if (this.flip !== null) {
                return;
            }
        }
    }
}

/** The read-back pixels as a picture with its first row at the top (flip: the rows come bottom first). */
function picture(snapshot, flip) {
    const { width, height, pixels } = snapshot;
    if (!flip) {
        return new ImageData(new Uint8ClampedArray(pixels.buffer, pixels.byteOffset, pixels.byteLength), width, height);
    }
    const rows = new Uint8ClampedArray(pixels.length);
    const stride = width * 4;
    for (let row = 0; row < height; row++) {
        rows.set(pixels.subarray(row * stride, (row + 1) * stride), (height - 1 - row) * stride);
    }
    return new ImageData(rows, width, height);
}
