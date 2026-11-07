"""Rebuild original, seamless 512px materials and generic JSON parts (Pillow + numpy)."""
from pathlib import Path
import json
import numpy as np
from PIL import Image, PngImagePlugin

ROOT = Path(__file__).resolve().parents[1]
N = 512
y, x = np.mgrid[0:N, 0:N]


def noise(rng, strength=1):
    a = rng.normal(size=(N, N))
    f = np.fft.fft2(a)
    yy, xx = np.meshgrid(np.fft.fftfreq(N), np.fft.fftfreq(N), indexing="ij")
    f *= np.exp(-(xx*xx + yy*yy)*strength)
    a = np.fft.ifft2(f).real
    return a / max(a.std(), .001)


def save_map(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    info = PngImagePlugin.PngInfo()
    info.add_text("Source", "Original procedural material, FBP 3D Canvas; no third-party images")
    Image.fromarray(np.clip(data, 0, 255).astype("uint8")).save(path, pnginfo=info, optimize=True)


def json_file(path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n")


catalog = {}
definitions = [
    ("wood_oak", "オークの木床", "wood", [190, 157, 109], [1200, 1800], .65),
    ("wood_walnut", "ウォールナット", "wood", [108, 76, 50], [1200, 1800], .58),
    ("wall_plaster", "白い塗り壁", "wall", [234, 231, 222], [1000, 1000], .92),
    ("tile_stone", "石調タイル", "tile", [192, 190, 180], [1200, 1200], .78),
    ("fabric_linen", "リネン調の布", "fabric", [169, 174, 157], [500, 500], .95),
]
for seed, (asset_id, title, category, base, size, roughness) in enumerate(definitions):
    rng = np.random.default_rng(630 + seed)
    fine, broad = noise(rng, 3), noise(rng, 400)
    if category == "wood":
        # Six planks per tile, staggered joins, growth grain and subtle board colour variation.
        plank = x * 6 // N
        wave = np.sin(2*np.pi*y/N)*1.6 + broad*.7
        grain = np.sin(2*np.pi*x*55/N + wave) + .5*np.sin(2*np.pi*x*113/N + wave*2)
        offsets = np.array([0, 213, 76, 349, 124, 273])[plank]
        join = np.minimum((y+offsets) % N, N-(y+offsets) % N) < 1.5
        edge = (x * 6 % N) < 5
        seams = edge | join
        variation = grain*3.6 + fine*1.2 + broad*2 + np.array([0, 8, -5, 3, -2, 5])[plank]
        height = .45 + grain*.018 + fine*.005 - seams*.16
        variation = np.where(seams, variation-42, variation)
    elif category == "tile":
        lines = (x % 256 < 4) | (y % 256 < 4)
        variation = broad*2.0 + fine*1.8 - lines*30
        height = .5 + fine*.006 - lines*.10
    elif category == "fabric":
        warp = np.sin(x*np.pi/2) + np.sin(y*np.pi/2)
        variation = warp*5 + fine*1.3 + broad*1.5
        height = .5 + warp*.018 + fine*.004
    else:
        variation = fine*1.8 + broad*.7
        height = .5 + fine*.009
    color = np.array(base)[None, None, :] + variation[..., None]
    dx = (np.roll(height, -1, 1) - np.roll(height, 1, 1))*1.4
    dy = (np.roll(height, -1, 0) - np.roll(height, 1, 0))*1.4
    normal = np.stack([-dx, -dy, np.ones_like(dx)], axis=-1)
    normal /= np.linalg.norm(normal, axis=-1, keepdims=True)
    maps = {key: f"{category}/{asset_id}_{key}.png" for key in ["color", "normal", "roughness"]}
    save_map(ROOT/"texture"/maps["color"], color)
    save_map(ROOT/"texture"/maps["normal"], (normal*.5+.5)*255)
    save_map(ROOT/"texture"/maps["roughness"], np.repeat(np.clip(roughness*255+fine*4, 0, 255)[..., None], 3, axis=2))
    catalog[asset_id] = {"title": title, "size_mm": size, "maps": maps,
                         "material": {"color": "#ffffff", "roughness": 1, "metalness": 0},
                         "source": "Original procedural material", "license": "Framework project license"}
json_file(ROOT/"texture/catalog.json", catalog)


def box(name, size, pos, material, **options):
    return {"id": name, "type": "box", "size": size, "position": pos, "material": material, **options}


oak = {"texture": "wood_oak"}
white = {"color": "#eeede7", "roughness": .6}
metal = {"color": "#899093", "roughness": .3, "metalness": .75}
fabric = {"texture": "fabric_linen"}
parts = {}
door = [box("frame_top", [1000, 70, 150], [0, 2135, 0], white),
        box("frame_left", [65, 2100, 150], [-467, 1050, 0], white),
        box("frame_right", [65, 2100, 150], [467, 1050, 0], white),
        {"id": "hinge", "type": "group", "position": [-435, 0, 0], "rotation": [0, -55, 0]},
        box("leaf", [870, 2060, 42], [435, 1030, 0], oak, parent="hinge"),
        box("handle", [105, 22, 28], [765, 1000, 42], metal, parent="hinge")]
window = [box("glass", [1640, 1110, 10], [0, 650, 0], {"color": "#c7e3ec", "opacity": .3, "roughness": .15}, cast_shadow=False),
          box("bottom", [1800, 65, 160], [0, 32, 0], white), box("top", [1800, 65, 160], [0, 1267, 0], white),
          box("left", [65, 1300, 160], [-867, 650, 0], white), box("right", [65, 1300, 160], [867, 650, 0], white),
          box("center", [50, 1180, 85], [0, 650, 0], white)]
table = [box("top", [1400, 45, 800], [0, 735, 0], oak)]
chair = [box("seat", [470, 60, 470], [0, 455, 0], fabric),
         box("back", [470, 390, 55], [0, 685, -210], fabric)]
for n, (xx, zz) in enumerate([(-1, -1), (-1, 1), (1, -1), (1, 1)]):
    table.append(box("leg"+str(n), [60, 710, 60], [xx*610, 355, zz*310], oak))
    chair.append(box("leg"+str(n), [32, 430, 32], [xx*190, 215, zz*190], metal))
cabinet = [box("body", [1400, 800, 430], [0, 400, 0], oak),
           box("left_door", [685, 760, 25], [-350, 400, 228], white),
           box("right_door", [685, 760, 25], [350, 400, 228], white),
           box("handle_left", [18, 130, 30], [-35, 440, 255], metal),
           box("handle_right", [18, 130, 30], [35, 440, 255], metal)]
for asset_id, title, size, objects in [
    ("door_single", "片開きドア（開いた状態）", [1000, 2170, 150], door),
    ("window_double", "2枚窓", [1800, 1300, 160], window),
    ("table_oak", "オークのテーブル", [1400, 758, 800], table),
    ("chair_fabric", "布張り椅子", [470, 880, 470], chair),
    ("cabinet_low", "ローカビネット", [1400, 800, 470], cabinet),
]:
    json_file(ROOT/f"parts_json/{asset_id}.json", {"schema_version": 1, "objects": objects})
    parts[asset_id] = {"title": title, "file": asset_id+".json", "size_mm": size,
                       "origin": "floor center", "front": "+Z", "source": "Original generic geometry"}
json_file(ROOT/"parts_json/catalog.json", parts)
