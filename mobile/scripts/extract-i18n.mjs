// Collects every t('key', 'English text') call in src/ into src/i18n/en.json.
// Run: npm run i18n   then upload en.json in Admin → Languages → English
// (or: php artisan medalbumin:ui-import en ../mobile/src/i18n/en.json --prune)
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('../src', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
const files = [];
(function walk(dir) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) walk(p);
    else if (/\.(tsx?|jsx?)$/.test(name)) files.push(p);
  }
})(root);

// t('a.b', 'Text') or t("a.b", "Text") — the English text must be a plain string literal.
const re = /\bt\(\s*(['"])([a-zA-Z0-9_.\-]+)\1\s*,\s*(['"`])((?:\\.|(?!\3).)*)\3/g;
const flat = {};
const conflicts = [];
for (const file of files) {
  // Skip comment lines so examples in docs are not collected.
  const src = readFileSync(file, 'utf8')
    .split('\n')
    .filter((l) => !/^\s*(\/\/|\*|\/\*)/.test(l))
    .join('\n');
  for (const m of src.matchAll(re)) {
    const key = m[2];
    const text = m[4].replace(/\\(['"`\\])/g, '$1').replace(/\\n/g, '\n');
    if (flat[key] !== undefined && flat[key] !== text) conflicts.push(`${key}: "${flat[key]}" vs "${text}"`);
    flat[key] = text;
  }
}

const nested = {};
for (const key of Object.keys(flat).sort()) {
  const parts = key.split('.');
  let node = nested;
  parts.slice(0, -1).forEach((p) => (node = node[p] ??= {}));
  node[parts.at(-1)] = flat[key];
}
writeFileSync(join(root, 'i18n', 'en.json'), JSON.stringify(nested, null, 2) + '\n');
console.log(`en.json: ${Object.keys(flat).length} texts`);
if (conflicts.length) {
  console.warn('Same key with different English text:\n  ' + conflicts.join('\n  '));
  process.exitCode = 1;
}
