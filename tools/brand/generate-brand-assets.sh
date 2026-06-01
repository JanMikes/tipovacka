#!/usr/bin/env bash
#
# Regenerate the Wtips favicon / app-icon / OG image set from the brand SVGs.
#
# Source of truth:
#   assets/images/logo/logo-mark.svg   — gradient "W" mark  (favicons, app icons)
#   assets/images/logo/logo-wtips.svg  — full "Wtips" wordmark (OG image)
#
# Output (all into public/): favicon.svg, favicon-96x96.png, favicon.ico,
#   apple-touch-icon.png, web-app-manifest-192x192.png, web-app-manifest-512x512.png,
#   og-default.png (1200x630), og-default-square.png (1200x1200).
#
# Requirements: macOS `sips` (renders SVG incl. gradients reliably — ImageMagick's
# built-in MSVG renderer mangles the gradient, and librsvg isn't installed) +
# ImageMagick 7 `magick` (compositing / masking / ICO / text).
#
# Re-run after editing either logo SVG. Not part of CI (macOS-only tooling).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MARK="$ROOT/assets/images/logo/logo-mark.svg"
WORDMARK="$ROOT/assets/images/logo/logo-wtips.svg"
OUT="$ROOT/public"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# Brand canvas colours (match assets/styles/app.css --grad-canvas / --color-navy-850).
BG_IN="#1c2a47"    # canvas centre
BG_OUT="#0a111e"   # canvas edge
GLOW="#2e618c"     # accent top glow (screened over the canvas)
TAGLINE="Tipovací soutěže bez sázek — jen pro radost."
FONT="Avenir-Book"; magick -list font 2>/dev/null | grep -q "Font: $FONT" || FONT="Arial"

imul() { awk "BEGIN{printf \"%d\", $1}"; }   # integer result of an arithmetic expr

echo "→ rasterising source SVGs via sips"
# High-res trimmed gradient W (transparent) — downscaled per icon for crispness.
sips -s format png --resampleWidth 1200 "$MARK" --out "$TMP/mark.png" >/dev/null 2>&1
magick "$TMP/mark.png" -background none -trim +repage "$TMP/W.png"
# High-res trimmed wordmark (white + accent first stroke).
sips -s format png --resampleWidth 1600 "$WORDMARK" --out "$TMP/wm.png" >/dev/null 2>&1
magick "$TMP/wm.png" -background none -trim +repage "$TMP/wordmark.png"

# dark_canvas <w> <h> <out>  — radial brand gradient.
dark_canvas() { magick -size "${1}x${2}" radial-gradient:"$BG_IN"-"$BG_OUT" "$3"; }

# icon <size> <w_fraction> <rounded:0|1> <out>
icon() {
  local size="$1" frac="$2" rounded="$3" out="$4"
  local wpx; wpx=$(imul "$size * $frac")
  dark_canvas "$size" "$size" "$TMP/c.png"
  magick "$TMP/W.png" -resize "${wpx}x" "$TMP/w_scaled.png"
  magick "$TMP/c.png" "$TMP/w_scaled.png" -gravity center -composite "$TMP/badge.png"
  if [[ "$rounded" == "1" ]]; then
    local r; r=$(imul "$size * 0.22")
    magick -size "${size}x${size}" xc:none -fill white \
      -draw "roundrectangle 0,0,$((size-1)),$((size-1)),$r,$r" "$TMP/mask.png"
    magick "$TMP/badge.png" "$TMP/mask.png" -alpha set -compose DstIn -composite -depth 8 -strip "$out"
  else
    magick "$TMP/badge.png" -depth 8 -strip "$out"   # full-bleed: the OS masks app icons itself
  fi
}

echo "→ favicons (rounded dark badge — browser tabs, not OS-masked)"
icon 96  0.64 1 "$OUT/favicon-96x96.png"
icon 256 0.64 1 "$TMP/favicon-256.png"
magick "$TMP/favicon-256.png" -strip -define icon:auto-resize=16,32,48,64 "$OUT/favicon.ico"

echo "→ app icons (full-bleed — iOS/Android apply their own squircle mask)"
icon 180 0.60 0 "$OUT/apple-touch-icon.png"
cp "$OUT/apple-touch-icon.png" "$OUT/apple-touch-icon-precomposed.png"  # legacy iOS auto-probes this name
icon 192 0.60 0 "$OUT/web-app-manifest-192x192.png"
icon 512 0.60 0 "$OUT/web-app-manifest-512x512.png"

echo "→ vector favicon.svg (self-contained dark rounded badge + gradient W)"
# W bbox in user space (viewBox -10 -10 180 95): measured x[24,127] y[3,76], centre (75.5,39.5).
cat > "$OUT/favicon.svg" <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" role="img" aria-label="Wtips">
  <defs>
    <linearGradient id="mg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#65adde"/>
      <stop offset="100%" stop-color="#2a6e9e"/>
    </linearGradient>
    <radialGradient id="bg" cx="50%" cy="32%" r="80%">
      <stop offset="0%" stop-color="#1c2a47"/>
      <stop offset="100%" stop-color="#0a111e"/>
    </radialGradient>
  </defs>
  <rect width="100" height="100" rx="22" fill="url(#bg)"/>
  <g transform="translate(50,50) scale(0.62) translate(-75.5,-39.5) translate(-750,-135)">
    <path fill="url(#mg)" d="M786.8,138.7c1.9,0,3.7.6,5.4,1.9,1.7,1.2,2.7,2.8,3.2,4.6l9.4,32.3,9.4-32.3c.6-1.8,1.7-3.4,3.3-4.6,1.7-1.2,3.4-1.9,5.4-1.9h5.4c1.9,0,3.7.6,5.4,1.9,1.7,1.2,2.8,2.8,3.3,4.6l9.4,32.3,9.4-32.3c.5-1.8,1.5-3.4,3.2-4.6,1.7-1.2,3.4-1.9,5.4-1.9h7.2c1.9,0,3.4.6,4.3,1.9,1,1.2,1.1,2.8.5,4.6l-18.6,58.6c-.6,1.8-1.8,3.4-3.4,4.6s-3.4,1.9-5.4,1.9h-5.2c-1.9,0-3.7-.6-5.4-1.9s-2.8-2.8-3.3-4.6l-9.2-30.7-9.5,30.7c-.5,1.8-1.5,3.4-3.2,4.6-1.7,1.2-3.4,1.9-5.4,1.9h-5.2c-2,0-3.8-.6-5.4-1.9-1.6-1.2-2.7-2.8-3.4-4.6l-18.6-58.6c-.6-1.8-.5-3.4.4-4.6.9-1.2,2.4-1.9,4.4-1.9h7.2Z"/>
  </g>
</svg>
SVG

echo "→ OG images (dark canvas + Wtips wordmark + tagline)"
# make_og <w> <h> <wordmark_w> <out>
make_og() {
  local w="$1" h="$2" wmw="$3" out="$4"
  dark_canvas "$w" "$h" "$TMP/og_base.png"
  # Soft accent glow, upper third, screened over the canvas (dark edges add nothing).
  local g; g=$(imul "$w * 0.72")
  magick -size "${g}x${g}" radial-gradient:"$GLOW"-"#05080f" "$TMP/glow.png"
  magick "$TMP/og_base.png" "$TMP/glow.png" -gravity north -geometry "+0-$(imul "$g/3")" \
    -compose Screen -composite "$TMP/og_glow.png"
  # Wordmark slightly above centre.
  magick "$TMP/wordmark.png" -resize "${wmw}x" "$TMP/wm_scaled.png"
  magick "$TMP/og_glow.png" "$TMP/wm_scaled.png" -gravity center -geometry "+0-$(imul "$h/14")" \
    -compose Over -composite "$TMP/og_wm.png"
  # Tagline below the wordmark.
  magick "$TMP/og_wm.png" -gravity center -font "$FONT" -pointsize "$(imul "$h/18")" \
    -fill "#9db2c8" -annotate "+0+$(imul "$h/6")" "$TAGLINE" -depth 8 -strip "$out"
}
make_og 1200 630  560 "$OUT/og-default.png"
make_og 1200 1200 600 "$OUT/og-default-square.png"

if command -v oxipng >/dev/null 2>&1; then
  echo "→ optimising PNGs losslessly (oxipng)"
  oxipng -o 4 --strip safe -q \
    "$OUT/favicon-96x96.png" "$OUT/apple-touch-icon.png" "$OUT/apple-touch-icon-precomposed.png" \
    "$OUT/web-app-manifest-192x192.png" "$OUT/web-app-manifest-512x512.png" \
    "$OUT/og-default.png" "$OUT/og-default-square.png" || true
fi

echo "✓ brand assets written to $OUT"
