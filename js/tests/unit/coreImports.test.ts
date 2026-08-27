import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Every core module this extension imports must live in core's *main* bundle.
 *
 * Flarum splits core across lazily-loaded chunks. Importing a module that
 * lives in one of them yields `undefined` at boot, and the extension dies with
 * a message that names neither the module nor the import — the failure is
 * invisible in source and in tests, and only appears in a browser.
 *
 * These assertions read the compiled output on both sides, which is the only
 * place the question can actually be answered. They need `npm run build` and
 * an installed `flarum/core` to have run first; where either is missing the
 * suite says so rather than passing quietly.
 */

const MODULE_REFERENCE = /flarum\.reg\.get\("core","([^"]+)"\)/g;

/**
 * The extension root. This file is at `js/tests/unit`, and the suite runs as
 * an ES module, where `__dirname` does not exist.
 */
const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');

function read(path: string): string | null {
  try {
    return readFileSync(resolve(ROOT, path), 'utf8');
  } catch {
    return null;
  }
}

function coreModulesUsedBy(bundle: string): string[] {
  return [...new Set([...bundle.matchAll(MODULE_REFERENCE)].map((match) => match[1]))].sort();
}

/**
 * Core's bundle names a module in two quite different ways: `reg.add(…)`
 * registers one that is loaded and ready, while `addChunkModule(…)` merely
 * records which lazy chunk one *would* come from. Only the first means the
 * module can be imported at boot, so the registration is matched rather than
 * the name — searching for the bare name finds the chunk record too, and
 * reports every lazily-loaded module as safe.
 */
function isInMainBundle(coreBundle: string, module: string): boolean {
  return coreBundle.includes(`reg.add("core","${module}"`);
}

describe.each([
  ['forum', 'js/dist/forum.js', 'vendor/flarum/core/js/dist/forum.js'],
  ['admin', 'js/dist/admin.js', 'vendor/flarum/core/js/dist/admin.js'],
])('%s bundle', (_frontend, ourPath, corePath) => {
  const ours = read(ourPath);
  const core = read(corePath);

  it('has been built', () => {
    expect(ours).not.toBeNull();
  });

  it("can see core's compiled bundle", () => {
    expect(core).not.toBeNull();
  });

  it('imports at least something from core', () => {
    // Guards the assertion below against passing because the regex stopped
    // matching rather than because every import is sound.
    expect(coreModulesUsedBy(ours!).length).toBeGreaterThan(5);
  });

  it('imports nothing that core loads lazily', () => {
    const missing = coreModulesUsedBy(ours!).filter((module) => !isInMainBundle(core!, module));

    expect(missing).toEqual([]);
  });
});
