/**
 * build-icon-subset.mjs — regenerate the trimmed Tabler icon webfont.
 *
 * The upstream @tabler/icons-webfont ships 5,147 glyphs (446KB woff2) plus a
 * 255KB stylesheet of per-icon rules, and loads on every page. This app can
 * only ever render the icons its own source references — the admin icon picker
 * is a closed <select>, not a free-text field — so shipping the whole family is
 * ~400KB of dead weight on the critical path.
 *
 * This scans the repo for `ti-*` class names, keeps only those glyphs, and
 * writes a subset font + stylesheet into resources/. The OUTPUT IS COMMITTED,
 * so a buyer never runs this: it is a maintenance step, not a build step.
 *
 * Run it after adding an icon that was not previously used anywhere:
 *
 *     npm install --no-save subset-font
 *     node scripts/build-icon-subset.mjs
 *
 * An icon referenced only from data (a hand-edited DB row, a third-party addon
 * that ships its own icon names) will NOT be picked up by the scan and renders
 * as a blank box. Add such names to ALWAYS_INCLUDE below.
 */

import { execFileSync } from 'node:child_process'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

import subsetFont from 'subset-font'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const pkg = resolve(root, 'node_modules/@tabler/icons-webfont/dist')

// Directories scanned for `ti-<name>` occurrences.
const SCAN_DIRS = ['resources', 'addons', 'database', 'app', 'config']
// `sql` is load-bearing: database/data/data.sql is the baseline the installer
// imports, and it carries icon names for tools, categories and homepage sections
// that appear in no source file. Without it those glyphs are absent from the
// subset and render as blank boxes on a fresh install — ti-confetti,
// ti-hand-click and ti-heart-handshake were exactly that.
const SCAN_EXTS = ['vue', 'ts', 'js', 'php', 'css', 'blade.php', 'sql']

// Icons that the scan cannot see (rendered from admin-authored data rather than
// a literal in source). Keep this list short and explain each entry.
const ALWAYS_INCLUDE = []

// Upstream's filled family names its glyphs WITHOUT a suffix (.ti-heart), while
// this app addresses them as `ti-heart-filled`. So any scanned `ti-X-filled`
// whose X exists in the filled family is routed to that font; everything else
// falls through to the regular family.
const FILLED_SUFFIX = '-filled'

/** name -> codepoint string, parsed from the upstream stylesheet. */
function readGlyphTable(cssPath) {
    const css = readFileSync(cssPath, 'utf8')
    const table = new Map()

    for (const m of css.matchAll(/\.ti-([a-z0-9-]+):before\s*\{\s*content:\s*"\\([0-9a-f]+)"/gi)) {
        table.set(m[1], String.fromCodePoint(parseInt(m[2], 16)))
    }

    return table
}

function scanUsedNames() {
    // `--include=GLOB`, not `--include GLOB`: passed as two argv entries the glob
    // does not bind to the option and grep silently narrows what it walks, which
    // quietly drops icons from the subset (they then render as blank boxes).
    const includeArgs = SCAN_EXTS.map((ext) => `--include=*.${ext}`)
    let out = ''

    try {
        out = execFileSync(
            'grep',
            ['-rhoE', 'ti-[a-z0-9-]+', ...includeArgs, ...SCAN_DIRS],
            { cwd: root, encoding: 'utf8', maxBuffer: 1 << 28 }
        )
    } catch (error) {
        // grep exits 1 when it matches nothing, which would mean a broken scan.
        if (error.status !== 1) throw error
        throw new Error('icon scan produced no matches — check SCAN_DIRS')
    }

    return new Set(out.split(/\r?\n/).map((s) => s.trim().replace(/^ti-/, '')).filter(Boolean))
}

const glyphs = readGlyphTable(resolve(pkg, 'tabler-icons.css'))
const filledGlyphs = readGlyphTable(resolve(pkg, 'tabler-icons-filled.css'))
const scanned = scanUsedNames()

const requested = [...new Set([...scanned, ...ALWAYS_INCLUDE])].sort()

/** `ti-heart-filled` -> the filled family's `heart` glyph, when it has one. */
const filledNameFor = (name) =>
    name.endsWith(FILLED_SUFFIX) ? name.slice(0, -FILLED_SUFFIX.length) : null

const wantedFilled = requested.filter((name) => filledGlyphs.has(filledNameFor(name) ?? '\0'))
const wanted = requested.filter((name) => glyphs.has(name) && !wantedFilled.includes(name))

const missing = ALWAYS_INCLUDE.filter(
    (name) => !glyphs.has(name) && !filledGlyphs.has(filledNameFor(name) ?? '\0')
)
if (missing.length) {
    throw new Error(`ALWAYS_INCLUDE names are not real glyphs: ${missing.join(', ')}`)
}

const outFonts = resolve(root, 'resources/fonts')
mkdirSync(outFonts, { recursive: true })

// ── Regular family ───────────────────────────────────────────────────────────
const regularChars = wanted.map((name) => glyphs.get(name)).join('')
const regularSubset = await subsetFont(readFileSync(resolve(pkg, 'fonts/tabler-icons.woff2')), regularChars, {
    targetFormat: 'woff2',
})
writeFileSync(resolve(outFonts, 'tabler-icons-subset.woff2'), regularSubset)

// ── Filled family ────────────────────────────────────────────────────────────
const filledChars = wantedFilled.map((name) => filledGlyphs.get(filledNameFor(name))).join('')
const filledSubset = await subsetFont(readFileSync(resolve(pkg, 'fonts/tabler-icons-filled.woff2')), filledChars, {
    targetFormat: 'woff2',
})
writeFileSync(resolve(outFonts, 'tabler-icons-filled-subset.woff2'), filledSubset)

// ── Stylesheet ───────────────────────────────────────────────────────────────
function escapeCss(char) {
    return `\\${char.codePointAt(0).toString(16)}`
}

const rules = wanted
    .map((name) => `.ti-${name}:before{content:"${escapeCss(glyphs.get(name))}"}`)
    .join('\n')

// The filled glyphs live in a different family, so each needs the font-family
// override alongside its content — the `.ti` base class points at the regular
// family and these elements carry `ti` too.
const filledRules = wantedFilled
    .map(
        (name) =>
            `.ti-${name}{font-family:"tabler-icons-filled"!important}\n` +
            `.ti-${name}:before{content:"${escapeCss(filledGlyphs.get(filledNameFor(name)))}"!important}`
    )
    .join('\n')

const css = `/* GENERATED by scripts/build-icon-subset.mjs — do not edit by hand.
 * Tabler Icons 3.44.0 (https://tabler.io), subset to the ${wanted.length} glyphs
 * this app references, out of ${glyphs.size} in the full family.
 * License: https://github.com/tabler/tabler-icons/blob/master/LICENSE
 */
@font-face {
  font-family: "tabler-icons";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url("../fonts/tabler-icons-subset.woff2") format("woff2");
}
@font-face {
  font-family: "tabler-icons-filled";
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url("../fonts/tabler-icons-filled-subset.woff2") format("woff2");
}
.ti {
  font-family: "tabler-icons" !important;
  speak: none;
  font-style: normal;
  font-weight: normal;
  font-variant: normal;
  text-transform: none;
  line-height: 1;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
${rules}
${filledRules}
`

// Self-check: every scanned name that is a real glyph upstream must have ended up
// with a rule. A gap here is the failure mode that matters — the page still builds
// and the icon just renders as an empty box — so fail the run instead.
const covered = new Set([...css.matchAll(/\.ti-([a-z0-9-]+):before/g)].map((m) => m[1]))
const uncovered = requested
    .filter((name) => glyphs.has(name) || filledGlyphs.has(filledNameFor(name) ?? '\0'))
    .filter((name) => !covered.has(name))

if (uncovered.length) {
    throw new Error(
        `${uncovered.length} referenced icon(s) missing from the subset: ${uncovered.join(', ')}`
    )
}

writeFileSync(resolve(root, 'resources/css/tabler-icons-subset.css'), css)

const kb = (b) => `${Math.round(b / 1024)}KB`
console.log(`glyphs kept        : ${wanted.length} / ${glyphs.size}`)
console.log(`filled glyphs kept : ${wantedFilled.length} (${wantedFilled.join(', ')})`)
console.log(`regular woff2      : ${kb(regularSubset.length)}  (was 446KB)`)
console.log(`filled woff2       : ${kb(filledSubset.length)}  (was 70KB)`)
console.log(`stylesheet         : ${kb(Buffer.byteLength(css))}  (was 250KB)`)
