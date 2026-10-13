// Controllo di accessibilità (WCAG 2.2 AA + best practice) con axe-core su tutte
// le pagine del sito generato, in tema chiaro e scuro, su desktop e telefono.
//
//   python3 build.py && (cd dist && python3 -m http.server 8000 &)
//   npm install --no-save playwright@1.47.2 axe-core@4.10.2
//   node scripts/verifica_accessibilita.mjs http://localhost:8000
//
// Esce con errore se trova anche una sola violazione.
import { chromium } from 'playwright';
import fs from 'fs';
import { createRequire } from 'module';

const base = (process.argv[2] || 'http://localhost:8000').replace(/\/$/, '');
const axe = fs.readFileSync(createRequire(import.meta.url).resolve('axe-core/axe.min.js'), 'utf8');
const sitemap = fs.readFileSync(new URL('../dist/sitemap.xml', import.meta.url), 'utf8');
const pagine = [...sitemap.matchAll(/<loc>https?:\/\/[^/]+(\/[^<]*)<\/loc>/g)].map(m => m[1]).concat('/404.html');

const browser = await chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {});
const trovate = new Map();
for (const tema of ['light', 'dark']) {
  for (const larghezza of [1280, 390]) {
    const p = await browser.newPage({ viewport: { width: larghezza, height: 900 }, colorScheme: tema });
    for (const u of pagine) {
      await p.goto(base + u);
      await p.addScriptTag({ content: axe });
      const violazioni = await p.evaluate(async () => (await axe.run(document, {
        runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'],
      })).violations.map(v => ({ id: v.id, help: v.help, elementi: v.nodes.map(n => n.target.join(' ')) })));
      for (const v of violazioni) {
        const voce = trovate.get(v.id) ?? { help: v.help, dove: new Set() };
        v.elementi.forEach(e => voce.dove.add(`${u} [${tema}, ${larghezza}px] ${e}`));
        trovate.set(v.id, voce);
      }
    }
    await p.close();
  }
}
await browser.close();

if (trovate.size === 0) {
  console.log(`OK: ${pagine.length} pagine senza violazioni WCAG 2.2 AA, in tema chiaro e scuro, desktop e telefono.`);
} else {
  for (const [id, v] of trovate) console.log(`- ${id}: ${v.help}\n    ${[...v.dove].slice(0, 5).join('\n    ')}`);
  process.exitCode = 1;
}
