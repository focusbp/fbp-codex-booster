import * as T from './vendor/three.module.js';
import {OrbitControls} from './vendor/OrbitControls.js';
import {RoundedBoxGeometry} from './vendor/RoundedBoxGeometry.js';
import {RoomEnvironment} from './vendor/RoomEnvironment.js';
import {Effects, normalizeRender, normalizeLighting} from './Effects.js';

const radians = n => n * Math.PI / 180;
const copy = value => JSON.parse(JSON.stringify(value));

function geometry(o) {
    switch (o.type) {
        case 'box': return new T.BoxGeometry(...o.size);
        case 'rounded_box': return new RoundedBoxGeometry(...o.size, 3, o.radius);
        case 'sphere': return new T.SphereGeometry(o.radius, 32, 20);
        case 'cylinder': return new T.CylinderGeometry(o.radius, o.radius, o.height, 32);
        case 'cone': return new T.ConeGeometry(o.radius, o.height, 32);
        case 'plane': return new T.PlaneGeometry(...o.size);
        case 'circle': return new T.CircleGeometry(o.radius, 48);
        case 'torus': return new T.TorusGeometry(o.radius, o.tube, 12, 48);
        case 'extrude': {
            const shape = new T.Shape();
            shape.moveTo(...o.points[0]);
            for (const point of o.points.slice(1)) shape.lineTo(...point);
            shape.closePath();
            return new T.ExtrudeGeometry(shape, {depth:o.depth, bevelEnabled:false, curveSegments:12});
        }
        default: throw new Error('Unknown primitive');
    }
}

// Built-in textures are deterministic and local. No arbitrary URLs, CORS or asset races.
function texture(m) {
    const c = document.createElement('canvas'); c.width = c.height = 128;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, 128, 128);
    if (m.texture === 'checker') {
        ctx.fillStyle = '#b8b8b8';ctx.fillRect(0, 0, 64, 64);ctx.fillRect(64, 64, 64, 64);
    } else if (m.texture === 'tile') {
        ctx.strokeStyle = '#909090';ctx.lineWidth = 3;ctx.strokeRect(0, 0, 128, 128);
    } else if (m.texture === 'wood') {
        ctx.fillStyle = '#e8dcc7';ctx.fillRect(0, 0, 128, 128);
        for (let i = 0; i < 22; i++) {
            ctx.strokeStyle = `rgba(110,75,40,${0.08 + (i % 4) * 0.035})`;
            ctx.beginPath();ctx.moveTo(i * 6, 0);ctx.bezierCurveTo(i * 6 + 8, 40, i * 6 - 5, 80, i * 6 + 2, 128);ctx.stroke();
        }
    } else throw new Error('Unknown texture');
    const t = new T.CanvasTexture(c);t.colorSpace = T.SRGBColorSpace;
    t.wrapS = t.wrapT = T.RepeatWrapping;t.repeat.fromArray(m.repeat || [1,1]);
    t.center.set(0.5, 0.5);t.rotation = radians(m.texture_rotation || 0);return t;
}

export async function mount(element, input, options = {}, assets = {}, signal = null) {
    if (input.schema_version !== 1 || input.units !== 'mm' || !Array.isArray(input.objects) || input.objects.length > 1500) throw new Error('Unsupported scene');
    const data = copy(input), nodes = new Map(), geometries = [], materials = [], textures = [];
    // PHP serializes an empty associative array as []; camera names must remain an object.
    data.views = {...data.views};
    data.render = normalizeRender(data.render);data.lighting = normalizeLighting(data.lighting);
    let renderer = null, controls = null, environment = null, resizeObserver = null, visibilityObserver = null;
    let effects = null;
    let disposed = false, failed = false, frame = 0, clock = 0, previous = 0, visible = true, paused = false, dirty = true, renderedFrames = 0, lastDraw = 0;
    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const scene = new T.Scene();scene.background = new T.Color(data.background);
    const camera = new T.PerspectiveCamera(45, 1, 0.1, 10000000);
    const root = new T.Group();scene.add(root);
    const status = document.createElement('div');status.setAttribute('role', 'status');
    function release() {
        effects?.dispose();controls?.dispose();environment?.dispose();
        for (const g of geometries) g.dispose();for (const m of materials) m.dispose();for (const t of textures) t.dispose();
        if (renderer) {
            renderer.domElement.removeEventListener('webglcontextlost', contextLost);
            renderer.dispose();renderer.forceContextLoss();renderer.domElement.remove();
        }
    }
    function stop() {if (frame) cancelAnimationFrame(frame);frame = 0;previous = 0;}
    function disconnect() {
        resizeObserver?.disconnect();visibilityObserver?.disconnect();
        document.removeEventListener('visibilitychange', visibilityChanged);reduceMotion.removeEventListener('change', motionChanged);
    }
    function fallback() {
        if (disposed || failed) return;failed = true;stop();disconnect();release();
        element.replaceChildren(status);status.textContent = 'この環境では3Dを表示できません。';element.dataset.canvas3dMode = 'unavailable';
    }
    function contextLost(event) {event.preventDefault();fallback();}
    function canDraw() {return !disposed && !failed && element.isConnected && visible && !document.hidden && element.clientWidth > 0 && element.clientHeight > 0;}
    function wake() {dirty = true;if (canDraw() && !frame) frame = requestAnimationFrame(tick);}
    function visibilityChanged() {if (document.hidden) stop();else wake();}
    function motionChanged() {previous = 0;wake();}
    function render() {effects.draw();renderedFrames++;}
    function tick(now) {
        frame = 0;if (!canDraw()) {previous = 0;return;}
        const active = !paused && options.animate !== false && !reduceMotion.matches && data.animations.length > 0;
        if (active && now - lastDraw < 1000 / 30) {frame = requestAnimationFrame(tick);return;}
        if (active) {
            clock += previous ? Math.min((now - previous) / 1000, 0.1) : 0;previous = now;
            for (const a of data.animations) {
                const node = nodes.get(a.id), base = node.userData.base[a.property][a.axis];
                node[a.property][a.axis] = base + Math.sin(clock * Math.PI * 2 / a.period + radians(a.phase)) * (a.property === 'rotation' ? radians(a.amplitude) : a.amplitude);
            }
        } else previous = 0;
        if (dirty || active) {try {render();lastDraw = now;dirty = false;} catch (error) {console.error('3D rendering:',error.message);fallback();return;}}
        if (active) frame = requestAnimationFrame(tick);
    }
    function resize() {
        if (disposed || failed) return;
        const width = element.clientWidth, height = element.clientHeight;
        if (width < 1 || height < 1) {stop();return;}
        renderer.setSize(width, height, false);camera.aspect = width / height;camera.updateProjectionMatrix();wake();
        effects.resize(width,height);
    }
    function setView(name) {
        const view = data.views[name];if (!view) throw new Error('Unknown view');
        if (disposed || failed) return;
        camera.position.fromArray(view.position);camera.fov = view.fov;camera.updateProjectionMatrix();
        controls.target.fromArray(view.target);controls.update();wake();
    }
    function setVisible(id, value) {
        const node = nodes.get(id);if (!node || typeof value !== 'boolean') throw new Error('Invalid object visibility');
        node.visible = value;data.objects.find(o => o.id === id).visible = value;effects?.fitShadows();wake();
    }
    function setColor(id, color) {
        const node = nodes.get(id);if (!node?.material || !/^#[a-f0-9]{6}$/i.test(color)) throw new Error('Invalid color target');
        node.material.color.set(color);data.objects.find(o => o.id === id).material.color = color;wake();
    }
    function getScene() {
        const result = copy(data);
        if (controls) result.views.current = {position:camera.position.toArray(),target:controls.target.toArray(),fov:camera.fov};
        return result;
    }
    function setRender(options) {
        const settings=normalizeRender(options,data.render);
        if (disposed || failed) return;effects.configure(settings,data.lighting);data.render=settings;wake();
    }
    function setLighting(options) {
        const settings=normalizeLighting(options,data.lighting);
        if (disposed || failed) return;effects.configure(data.render,settings);data.lighting=settings;wake();
    }
    function destroy() {
        if (disposed) return;disposed = true;stop();disconnect();release();
        if (status.parentNode === element) status.remove();element.dataset.canvas3dMode = 'destroyed';
    }
    const api = {setView, setVisible, setColor, setRender, setLighting, getScene, destroy,
        pause() {paused = true;stop();wake();}, resume() {paused = false;wake();},
        getState:() => ({mode:disposed?'destroyed':failed?'unavailable':'webgl', paused, objects:nodes.size, clock, renderedFrames, textures:textures.length, effects:effects?.state()??null}),
        exportImage() {
            if (disposed || failed) return Promise.reject(new Error('3D unavailable'));
            render();return new Promise((resolve, reject) => renderer.domElement.toBlob(blob => blob?resolve(blob):reject(new Error('PNG export failed')), 'image/png'));
        }};
    try {
        // Load every unique registered map once before creating GPU resources. Copies keep per-material UV transforms independent.
        const sources=new Map(), loader=new T.TextureLoader();
        for (const o of data.objects) {
            const id=o.material?.texture;if (!id || ['checker','wood','tile'].includes(id)) continue;
            const maps=assets[id];if (!maps?.color || !maps.normal || !maps.roughness) throw new Error('Registered texture URLs missing');
            for (const url of Object.values(maps)) {
                if (typeof url !== 'string' || new URL(url,location.href).origin!==location.origin) throw new Error('Invalid texture origin');
                if (!sources.has(url)) sources.set(url,loader.loadAsync(url).then(t=>{textures.push(t);return t;}));
            }
        }
        const results=await Promise.allSettled([...sources.values()]);
        if (signal?.aborted) throw new DOMException('Cancelled','AbortError');
        const rejected=results.find(r=>r.status==='rejected');if (rejected) throw new Error('Texture loading failed');
        const loaded=new Map();let i=0;for (const url of sources.keys()) loaded.set(url,results[i++].value);
        function mapTexture(m, map) {
            const base=loaded.get(assets[m.texture][map]), t=base.clone();textures.push(t);
            t.colorSpace=map==='color'?T.SRGBColorSpace:T.NoColorSpace;t.wrapS=t.wrapT=T.RepeatWrapping;
            t.repeat.fromArray(m.repeat??[1,1]);t.center.set(.5,.5);t.rotation=radians(m.texture_rotation??0);return t;
        }
        for (const o of data.objects) {
            if (nodes.has(o.id)) throw new Error('Duplicate object');
            let node;
            if (o.type === 'group') node = new T.Group();
            if (o.type !== 'group') {
                const g = geometry(o);geometries.push(g);const m = o.material;
                const material = new T.MeshStandardMaterial({color:m.color,roughness:m.roughness,metalness:m.metalness,
                    transparent:m.opacity<1,opacity:m.opacity,side:T.DoubleSide,emissive:m.emissive,emissiveIntensity:m.emissive_intensity});
                materials.push(material);
                if (m.texture && m.texture_size) {
                    material.map=mapTexture(m,'color');material.normalMap=mapTexture(m,'normal');material.roughnessMap=mapTexture(m,'roughness');
                    material.normalScale.setScalar(m.normal_strength);
                } else if (m.texture) {const t = texture(m);textures.push(t);material.map = t;}
                node = new T.Mesh(g, material);
            }
            node.name = o.id;node.position.fromArray(o.position);node.rotation.set(...o.rotation.map(radians));node.scale.fromArray(o.scale);node.visible = o.visible;
            node.userData.base = {position:node.position.clone(),rotation:node.rotation.clone()};nodes.set(o.id, node);
            node.castShadow=o.cast_shadow??true;node.receiveShadow=o.receive_shadow??true;
        }
        for (const o of data.objects) {
            const parent = o.parent ? nodes.get(o.parent) : root;
            if (!parent || (o.parent && data.objects.find(p => p.id === o.parent)?.type !== 'group')) throw new Error('Invalid parent');
            let p = parent, depth = 0;while (p) {if (p === nodes.get(o.id) || ++depth > 34) throw new Error('Cyclic hierarchy');p = p.parent;}
            parent.add(nodes.get(o.id));
        }
        root.updateMatrixWorld(true);
        for (const o of data.objects) {
            const node=nodes.get(o.id);let parent=node.parent;
            while(parent && parent!==root){if(!parent.castShadow)node.castShadow=false;if(!parent.receiveShadow)node.receiveShadow=false;parent=parent.parent;}
            const m=o.material;
            if (!m?.texture_size || !['box','rounded_box','plane','extrude'].includes(o.type)) continue;
            const g=node.geometry,positions=g.attributes.position,normals=g.attributes.normal,uv=g.attributes.uv,s=node.getWorldScale(new T.Vector3()),[u,v]=m.texture_size;
            for(let j=0;j<positions.count;j++) {
                const nx=Math.abs(normals.getX(j)),ny=Math.abs(normals.getY(j)),nz=Math.abs(normals.getZ(j));
                if(ny>=nx && ny>=nz) uv.setXY(j,positions.getX(j)*s.x/u,positions.getZ(j)*s.z/v);
                else if(nx>nz) uv.setXY(j,positions.getZ(j)*s.z/u,positions.getY(j)*s.y/v);
                else uv.setXY(j,positions.getX(j)*s.x/u,positions.getY(j)*s.y/v);
            }
            uv.needsUpdate=true;
        }
        for (const a of data.animations) if (!nodes.has(a.id)) throw new Error('Invalid animation');
        element.replaceChildren();
        renderer = new T.WebGLRenderer({antialias:true,alpha:false,powerPreference:'low-power'});
        renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 2));renderer.toneMapping = T.ACESFilmicToneMapping;
        renderer.domElement.style.cssText = 'width:100%;height:100%;display:block;touch-action:none;';
        renderer.domElement.setAttribute('aria-label','3D表示。ドラッグで回転、ホイールでズーム、右ドラッグで移動。');
        renderer.domElement.addEventListener('webglcontextlost', contextLost);element.append(renderer.domElement);
        const pmrem = new T.PMREMGenerator(renderer), room = new RoomEnvironment();
        try {environment = pmrem.fromScene(room, 0.04);}
        finally {room.dispose();pmrem.dispose();}
        controls = new OrbitControls(camera, renderer.domElement);controls.enabled = options.interactive !== false;
        controls.enableDamping = false;controls.minDistance = 1;controls.maxDistance = 2000000;controls.addEventListener('change', wake);
        const bounds = new T.Box3().setFromObject(root), size = bounds.getSize(new T.Vector3()), center = bounds.getCenter(new T.Vector3());
        const radius = Math.max(size.length(), 100);
        camera.near = Math.max(0.1, radius / 10000);camera.far = Math.max(10000000, radius * 20);
        if(data.render.ao.enabled)camera.near=Math.max(1,radius/500);
        effects=new Effects(renderer,scene,camera,root,environment.texture);effects.configure(data.render,data.lighting);
        camera.position.copy(center).add(new T.Vector3(radius * 0.8, radius * 0.7, radius));controls.target.copy(center);controls.update();
        if (options.view) setView(options.view);
        resizeObserver = new ResizeObserver(resize);resizeObserver.observe(element);
        visibilityObserver = new IntersectionObserver(entries => {visible = entries[0].isIntersecting;if (visible) wake();else stop();});visibilityObserver.observe(element);
        document.addEventListener('visibilitychange', visibilityChanged);reduceMotion.addEventListener('change', motionChanged);
        element.dataset.canvas3dMode = 'webgl';resize();
    } catch (error) {
        if(signal?.aborted){stop();disconnect();release();throw new DOMException('Cancelled','AbortError');}
        fallback();console.error('3D initialization:', error.message);
    }
    return api;
}
