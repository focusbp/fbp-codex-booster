"""Local mask inference only. Original RGB never enters a generative image model.

U2NETP normalization follows rembg's U2netpSession (MIT); see bundled notices.
No pip installation, model download, network call or home-directory cache at runtime.
"""
import ctypes
import hashlib
import math
from pathlib import Path

ROOT = Path(__file__).resolve().parent / 'segmentation'
ASSET_SHA256 = {'u2netp.onnx': '309c8469258dda742793dce0ebea8e6dd393174f89934733ecc8b14c76f4ddd8', 'libonnxruntime.so': '1147caa734d19f7549f90a659aa9ca444366eeb80a596900d19d242145a9f4c9', 'segment.so': '037e6331b5d744d445fd3bb4e85356c44e7c08e3d4040a42c77ac42788c63231'}


def automatic_mask(w, h, rgb, tolerance):
    if all(max(rgb[c::3]) - min(rgb[c::3]) <= 2 for c in range(3)):
        return bytearray(w * h)
    model = ROOT / 'u2netp.onnx'
    for name, expected in ASSET_SHA256.items():
        if hashlib.sha256((ROOT / name).read_bytes()).hexdigest() != expected:
            raise ValueError('Segmentation asset integrity check failed')
    lib = ctypes.CDLL(str(ROOT / 'segment.so'))
    fp = ctypes.POINTER(ctypes.c_float)
    bp = ctypes.POINTER(ctypes.c_ubyte)
    runtime = ctypes.CDLL(str(ROOT / 'libonnxruntime.so'))
    runtime.OrtGetApiBase.restype = ctypes.POINTER(ctypes.c_void_p)
    get_api = ctypes.CFUNCTYPE(ctypes.c_void_p, ctypes.c_uint32)(runtime.OrtGetApiBase()[0])
    api = get_api(18)
    if not api: raise ValueError('Unsupported local segmentation API')
    lib.iw_segment.argtypes = [ctypes.c_void_p, ctypes.c_char_p, fp, fp]
    lib.iw_segment.restype = ctypes.c_int
    lib.iw_refine.argtypes = [bp, ctypes.c_int, ctypes.c_int, bp, fp, ctypes.c_float, bp]
    lib.iw_refine.restype = None
    inp = (ctypes.c_float * (3 * 320 * 320))()
    prediction = (ctypes.c_float * (320 * 320))()
    guide = bytearray(320 * 320 * 3)
    # Bilinear resampling retains narrow/high-contrast detail in the inference input.
    for y in range(320):
        fy = max(0, (y + .5) * h / 320 - .5); y0 = int(fy); y1 = min(h - 1, y0 + 1); ay = fy - y0
        for x in range(320):
            fx = max(0, (x + .5) * w / 320 - .5); x0 = int(fx); x1 = min(w - 1, x0 + 1); ax = fx - x0
            for c in range(3):
                value = (rgb[(y0*w+x0)*3+c]*(1-ax)*(1-ay) + rgb[(y0*w+x1)*3+c]*ax*(1-ay)
                         + rgb[(y1*w+x0)*3+c]*(1-ax)*ay + rgb[(y1*w+x1)*3+c]*ax*ay)
                guide[(y*320+x)*3+c] = round(value)
    peak = max(guide) or 255
    for p in range(320 * 320):
        for c, (mean, std) in enumerate(zip((.485, .456, .406), (.229, .224, .225))):
            inp[c*320*320+p] = (guide[p*3+c]/peak-mean)/std
    if lib.iw_segment(api, str(model).encode(), inp, prediction):
        raise ValueError('Local segmentation runtime unavailable')
    lo, hi = min(prediction), max(prediction)
    if not math.isfinite(lo + hi) or hi - lo < .001:
        return bytearray(w * h)
    for p in range(320 * 320):
        prediction[p] = (prediction[p] - lo) / (hi - lo)
    mask = bytearray(w * h)
    lib.iw_refine((ctypes.c_ubyte * len(rgb)).from_buffer(rgb), w, h,
                  (ctypes.c_ubyte * len(guide)).from_buffer(guide), prediction,
                  max(.2, min(.8, .5 - (tolerance - 28) * .004)),
                  (ctypes.c_ubyte * len(mask)).from_buffer(mask))
    return mask
