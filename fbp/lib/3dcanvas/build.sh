#!/usr/bin/env bash
set -euo pipefail
canvas_root="$(cd "$(dirname "$0")" && pwd)"
npx --yes esbuild@0.25.0 "$canvas_root/runtime.js" --bundle --minify --format=esm --target=es2020 --outfile="$canvas_root/../../js/3dcanvas-runtime.js"
cp "$canvas_root/vendor/LICENSE.txt" "$canvas_root/../../js/3dcanvas-LICENSE.txt"
