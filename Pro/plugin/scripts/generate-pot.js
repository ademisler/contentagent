#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const OUTPUT = path.join(ROOT, 'languages', 'ai-content-agent.pot');
const EXCLUDES = ['node_modules', 'vendor', 'dist', 'releases'];
const DOMAIN = 'ai-content-agent';

/** Recursively walk directory */
function* walk(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    if (EXCLUDES.includes(entry.name)) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      yield* walk(full);
    } else if (entry.isFile() && entry.name.endsWith('.php')) {
      yield full;
    }
  }
}

/** Extract translatable strings from PHP source */
function extractFromPhp(filePath) {
  const content = fs.readFileSync(filePath, 'utf8');
  const lines = content.split(/\r?\n/);
  const entries = [];

  const patterns = [
    // __( 'string', 'domain' ) or __( "string", 'domain' )
    { fn: '__', re: /__\(\s*(["'])([\s\S]*?)\1\s*(?:,\s*(["'])([^"']*?)\3\s*)?\)/g },
    // _e( 'string', 'domain' )
    { fn: '_e', re: /_e\(\s*(["'])([\s\S]*?)\1\s*(?:,\s*(["'])([^"']*?)\3\s*)?\)/g },
    // esc_html__( 'string', 'domain' )
    { fn: 'esc_html__', re: /esc_html__\(\s*(["'])([\s\S]*?)\1\s*(?:,\s*(["'])([^"']*?)\3\s*)?\)/g },
    // esc_attr__( 'string', 'domain' )
    { fn: 'esc_attr__', re: /esc_attr__\(\s*(["'])([\s\S]*?)\1\s*(?:,\s*(["'])([^"']*?)\3\s*)?\)/g },
    // _x( 'string', 'context', 'domain' )
    { fn: '_x', re: /_x\(\s*(["'])([\s\S]*?)\1\s*,\s*(["'])([\s\S]*?)\3\s*(?:,\s*(["'])([^"']*?)\5\s*)?\)/g },
  ];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    for (const { fn, re } of patterns) {
      re.lastIndex = 0;
      let m;
      while ((m = re.exec(line)) !== null) {
        // For __, _e, esc_*__: m[2] is string, m[4] is domain (optional)
        // For _x: m[2] is string, m[4] is context, m[6] is domain (optional)
        if (fn === '_x') {
          const msgid = m[2];
          const context = m[4];
          const domain = (m[6] || '').trim();
          entries.push({ filePath, line: i + 1, fn, msgid, msgctxt: context, domain });
        } else {
          const msgid = m[2];
          const domain = (m[4] || '').trim();
          entries.push({ filePath, line: i + 1, fn, msgid, domain });
        }
      }
    }
  }

  return entries;
}

function escapePot(str) {
  return str
    .replace(/\\/g, '\\\\')
    .replace(/\"/g, '\\"')
    .replace(/\n/g, '\\n');
}

function buildPot(entries) {
  const header = [
    'msgid ""',
    'msgstr ""',
    '"Project-Id-Version: AI Content Agent 1.0.0\\n"',
    '"Report-Msgid-Bugs-To: \\n"',
    `"POT-Creation-Date: ${new Date().toISOString()}\\n"`,
    '"MIME-Version: 1.0\\n"',
    '"Content-Type: text/plain; charset=UTF-8\\n"',
    '"Content-Transfer-Encoding: 8bit\\n"',
    `"X-Domain: ${DOMAIN}\\n"`,
    '',
  ].join('\n');

  // Filter by domain if provided in call, or if absent assume ours
  const filtered = entries.filter(e => !e.domain || e.domain === DOMAIN);

  // Deduplicate by msgid+msgctxt
  const map = new Map();
  for (const e of filtered) {
    const key = e.msgctxt ? `${e.msgctxt}\u0000${e.msgid}` : e.msgid;
    if (!map.has(key)) map.set(key, []);
    map.get(key).push(e);
  }

  const body = [];
  for (const [key, locs] of map.entries()) {
    // References
    const refs = locs.map(l => `#: ${path.relative(ROOT, l.filePath)}:${l.line}`).join('\n');
    body.push(refs);
    const sample = locs[0];
    if (sample.msgctxt) {
      body.push(`msgctxt "${escapePot(sample.msgctxt)}"`);
    }
    body.push(`msgid "${escapePot(sample.msgid)}"`);
    body.push('msgstr ""');
    body.push('');
  }

  return header + '\n' + body.join('\n');
}

function main() {
  const all = [];
  for (const file of walk(ROOT)) {
    all.push(...extractFromPhp(file));
  }
  const pot = buildPot(all);
  fs.mkdirSync(path.join(ROOT, 'languages'), { recursive: true });
  fs.writeFileSync(OUTPUT, pot, 'utf8');
  console.log(`Generated POT with ${all.length} raw entries → ${OUTPUT}`);
}

main();