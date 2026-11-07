import * as T from './vendor/three.module.js';
import {EffectComposer} from './vendor/addons/postprocessing/EffectComposer.js';
import {RenderPass} from './vendor/addons/postprocessing/RenderPass.js';
import {GTAOPass} from './vendor/addons/postprocessing/GTAOPass.js';
import {OutputPass} from './vendor/addons/postprocessing/OutputPass.js';

export const renderDefaults = () => ({shadows:{enabled:false,quality:'medium'},ao:{enabled:false,intensity:0.8,radius:300,quality:'medium'},exposure:1});
export const lightingDefaults = () => ({ambient:{color:'#ffffff',ground_color:'#8795aa',intensity:0.75},sun:{color:'#ffffff',intensity:1.8,position:[3000,6000,5000],target:[0,0,0]},environment:{preset:'studio',intensity:0.5}});
function keys(o, allowed) {if (!o || typeof o !== 'object' || Array.isArray(o) || Object.keys(o).some(k=>!allowed.includes(k))) throw new Error('Invalid setting');}
function number(n, lo, hi) {if (typeof n !== 'number' || !Number.isFinite(n) || n < lo || n > hi) throw new Error('Invalid setting range');return n;}
function quality(v) {if (!['low','medium','high'].includes(v)) throw new Error('Invalid quality');return v;}
function bool(v) {if (typeof v !== 'boolean') throw new Error('Invalid boolean');return v;}
function color(v) {if (typeof v !== 'string' || !/^#[0-9a-f]{6}$/i.test(v)) throw new Error('Invalid light color');return v;}
export function normalizeRender(input={}, base=renderDefaults()) {
    keys(input,['shadows','ao','exposure']);
    for (const k of ['shadows','ao']) if (k in input) keys(input[k],Object.keys(base[k]));
    const s={...base.shadows,...input.shadows},a={...base.ao,...input.ao};
    return {shadows:{enabled:bool(s.enabled),quality:quality(s.quality)},ao:{enabled:bool(a.enabled),quality:quality(a.quality),intensity:number(a.intensity,0,2),radius:number(a.radius,1,5000)},exposure:number(input.exposure??base.exposure,.1,4)};
}
export function normalizeLighting(input={}, base=lightingDefaults()) {
    keys(input,['ambient','sun','environment']);const out={};
    for (const k of Object.keys(base)) {
        if (k in input) keys(input[k],Object.keys(base[k]));const v={...base[k],...input[k]};v.intensity=number(v.intensity,0,8);
        if (k==='environment') {if (!['studio','none'].includes(v.preset)) throw new Error('Invalid environment');}
        else {v.color=color(v.color);if (k==='ambient') v.ground_color=color(v.ground_color);}
        if (k==='sun') {
            for (const name of ['position','target']) {if (!Array.isArray(v[name]) || v[name].length!==3) throw new Error('Invalid light vector');v[name]=v[name].map(n=>number(n,-1000000,1000000));}
            if (v.position.every((n,i)=>n===v.target[i])) throw new Error('Light target equals position');
        }
        out[k]=v;
    }
    return out;
}

/** Fixed lighting/shadow/AO pipeline. JSON lengths are mm, matching the scene's world units. */
export class Effects {
    constructor(renderer, scene, camera, root, environment) {
        Object.assign(this,{renderer,scene,camera,root,environment});this.composer=null;this.ao=null;this.passes=[];
        this.ambient=new T.HemisphereLight();this.sun=new T.DirectionalLight();scene.add(this.ambient,this.sun,this.sun.target);
        this.width=1;this.height=1;
    }
    configure(render, lighting) {
        this.renderSettings=render;this.lighting=lighting;const {renderer,scene,sun,ambient}=this;
        renderer.toneMappingExposure=render.exposure;
        ambient.color.set(lighting.ambient.color);ambient.groundColor.set(lighting.ambient.ground_color);ambient.intensity=lighting.ambient.intensity;
        sun.color.set(lighting.sun.color);sun.intensity=lighting.sun.intensity;sun.position.fromArray(lighting.sun.position);sun.target.position.fromArray(lighting.sun.target);
        scene.environment=lighting.environment.preset==='none'?null:this.environment;scene.environmentIntensity=lighting.environment.intensity;
        renderer.shadowMap.enabled=render.shadows.enabled;renderer.shadowMap.type=T.PCFSoftShadowMap;sun.castShadow=render.shadows.enabled;
        const pixels={low:1024,medium:2048,high:4096}[render.shadows.quality];
        if (sun.shadow.mapSize.x!==pixels) {sun.shadow.map?.dispose();sun.shadow.map=null;sun.shadow.mapSize.set(pixels,pixels);}
        this.fitShadows();renderer.shadowMap.needsUpdate=true;
        if (render.ao.enabled && !this.composer) {
            this.composer=new EffectComposer(renderer);
            this.composer.renderTarget1.samples=this.composer.renderTarget2.samples=Math.min(4,renderer.capabilities.maxSamples);
            const first=new RenderPass(scene,this.camera), ao=new GTAOPass(scene,this.camera,1,1), output=new OutputPass();
            this.ao=ao;this.passes=[first,ao,output];for (const pass of this.passes) this.composer.addPass(pass);
            this.resize(this.width,this.height);
        }
        if (this.ao) {
            this.ao.blendIntensity=render.ao.intensity;
            this.ao.updateGtaoMaterial({radius:render.ao.radius,thickness:Math.max(20,render.ao.radius*.3),distanceExponent:1,distanceFallOff:1,scale:1,screenSpaceRadius:false,samples:{low:8,medium:16,high:32}[render.ao.quality]});
            this.resize(this.width,this.height);
        }
        // Shadow participation affects shader defines. Refresh existing materials on toggles.
        this.root.traverse(o=>{if (o.material) o.material.needsUpdate=true;});
    }
    fitShadows() {
        const bounds=new T.Box3().setFromObject(this.root);if (bounds.isEmpty()) return;
        const {sun}=this;sun.updateMatrixWorld();sun.target.updateMatrixWorld();sun.shadow.updateMatrices(sun);
        const lightBounds=new T.Box3();
        for (const x of [bounds.min.x,bounds.max.x]) for (const y of [bounds.min.y,bounds.max.y]) for (const z of [bounds.min.z,bounds.max.z])
            lightBounds.expandByPoint(new T.Vector3(x,y,z).applyMatrix4(sun.shadow.camera.matrixWorldInverse));
        const span=bounds.getSize(new T.Vector3()).length(),pad=Math.max(50,span*.04),c=sun.shadow.camera;
        c.left=lightBounds.min.x-pad;c.right=lightBounds.max.x+pad;c.bottom=lightBounds.min.y-pad;c.top=lightBounds.max.y+pad;
        c.near=Math.max(1,-lightBounds.max.z-pad);c.far=Math.max(c.near+1,-lightBounds.min.z+pad);c.updateProjectionMatrix();
        sun.shadow.bias=-.0001;sun.shadow.normalBias=Math.max(.2,span*.0002);sun.shadow.radius=3;
    }
    resize(width,height) {
        this.width=width;this.height=height;this.composer?.setSize(width,height);
        if(this.ao){const factor={low:.35,medium:.5,high:1}[this.renderSettings.ao.quality],ratio=this.renderer.getPixelRatio();this.ao.setSize(Math.max(1,Math.round(width*ratio*factor)),Math.max(1,Math.round(height*ratio*factor)));}
    }
    draw() {if (this.renderSettings.ao.enabled && this.composer) this.composer.render();else this.renderer.render(this.scene,this.camera);}
    state() {return {shadows:this.renderer.shadowMap.enabled,ao:!!(this.renderSettings.ao.enabled&&this.composer),shadowMapSize:this.sun.shadow.mapSize.x};}
    dispose() {
        for (const pass of this.passes) pass.dispose?.();
        // r180's GTAOPass.dispose does not dispose these two owned materials.
        this.ao?.gtaoMaterial.dispose();this.ao?.blendMaterial.dispose();
        this.composer?.dispose();this.sun.shadow.dispose();this.scene.remove(this.sun,this.sun.target,this.ambient);
    }
}
