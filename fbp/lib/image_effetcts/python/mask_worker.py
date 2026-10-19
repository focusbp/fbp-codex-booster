"""BMP -> inferred binary mask PNG. Standard Python only; no pip/network/generative editing."""
import hashlib
import json
import platform
import struct
import sys
import zlib
from pathlib import Path

from segmentation import automatic_mask, ASSET_SHA256


def read_bmp(path):
    data = Path(path).read_bytes()
    if len(data) < 54 or data[:2] != b'BM':
        raise ValueError('BMP required')
    offset = struct.unpack_from('<I', data, 10)[0]
    w, h, planes, bits, compression = struct.unpack_from('<iiHHI', data, 18)
    if w < 1 or h == 0 or w * abs(h) > 24000000 or planes != 1 or bits != 24 or compression != 0:
        raise ValueError('Uncompressed bounded 24-bit BMP required')
    stride = (w * 3 + 3) & ~3
    if offset < 54 or offset + stride * abs(h) > len(data):
        raise ValueError('Truncated BMP')
    rgb = bytearray(w * abs(h) * 3)
    for y in range(abs(h)):
        start = offset + (y if h < 0 else h - 1 - y) * stride
        row = data[start:start + w * 3]
        pos = y * w * 3
        rgb[pos:pos + w * 3:3] = row[2::3]
        rgb[pos + 1:pos + w * 3:3] = row[1::3]
        rgb[pos + 2:pos + w * 3:3] = row[0::3]
    return w, abs(h), rgb


def write_mask(path, w, h, mask):
    def chunk(kind, data):
        return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data) & 0xffffffff)
    scan = bytearray()
    for y in range(h):
        scan.append(0)
        scan.extend(255 if p else 0 for p in mask[y * w:(y + 1) * w])
    Path(path).write_bytes(b'\x89PNG\r\n\x1a\n'
        + chunk(b'IHDR', struct.pack('>IIBBBBB', w, h, 8, 0, 0, 0, 0))
        + chunk(b'IDAT', zlib.compress(scan, 6)) + chunk(b'IEND', b''))


def main():
    try:
        if len(sys.argv) != 4:
            raise ValueError('Arguments: input.bmp output.png tolerance')
        w, h, rgb = read_bmp(sys.argv[1])
        tolerance = int(sys.argv[3])
        if not 0 <= tolerance <= 100:
            raise ValueError('Invalid tolerance')
    except (OSError, ValueError, struct.error):
        print(json.dumps({'error': 'Invalid mask input', 'kind': 'invalid_input'}))
        return 1
    try:
        mask = automatic_mask(w, h, rgb, tolerance)
    except Exception:
        print(json.dumps({'error': 'Local segmentation unavailable', 'kind': 'segmentation_unavailable'}))
        return 1
    coverage = sum(mask) / (w * h)
    if coverage < .001 or coverage > .98:
        print(json.dumps({'error': 'Subject mask requires review', 'kind': 'mask_uncertain', 'coverage': coverage}))
        return 2
    write_mask(sys.argv[2], w, h, mask)
    print(json.dumps({'width': w, 'height': h, 'coverage': round(coverage, 6),
        'mask_method': 'u2netp-local-edge-refined', 'model_sha256': ASSET_SHA256['u2netp.onnx'],
        'mask_sha256': hashlib.sha256(Path(sys.argv[2]).read_bytes()).hexdigest(),
        'python_version': platform.python_version(), 'architecture': platform.machine(),
        'visual_review_required': True}))
    return 0


if __name__ == '__main__':
    sys.exit(main())
