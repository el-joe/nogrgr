// Ad-hoc step runner: node tests/e2e/themes/run.mjs <steps-module.mjs> [mode-name]
// The steps module default-exports async ({ page, context, mode, pw, BASE, shot, log }) => {}.
// Session state is kept in $QA_STATE (storage state JSON) so steps can continue a signed-in session.
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import * as lib from './lib.mjs';

const [stepsFile, modeName = '1440-en-light'] = process.argv.slice(2);
const mode = lib.MODES.find((m) => m.name === modeName) ?? lib.MODES[0];
const statePath = process.env.QA_STATE;
const pw = process.env.QA_PW_FILE ? fs.readFileSync(process.env.QA_PW_FILE, 'utf8').trim() : '';
const browser = await lib.launch();
const extra = statePath && fs.existsSync(statePath) ? { storageState: statePath } : {};
const context = await lib.newContext(browser, mode, extra);
const page = await context.newPage();
const bag = lib.collect(page);
const tag = process.env.QA_TAG || 'flow';
const shot = async (name, full = false) => page.screenshot({ path: lib.shotPath(tag, `${name}-${mode.name}`), fullPage: full });
const log = (...a) => console.log(...a);
let failed = false;
try {
    const mod = await import(pathToFileURL(path.resolve(stepsFile)).href);
    await mod.default({ page, context, mode, pw, BASE: lib.BASE, CENTRAL: lib.CENTRAL, shot, log, lib, bag });
} catch (e) {
    failed = true;
    console.log('STEP FAILED:', String(e.stack || e).split('\n').slice(0, 4).join(' | '));
    await page.screenshot({ path: lib.shotPath(tag, `failure-${mode.name}`) }).catch(() => {});
}
if (statePath) await context.storageState({ path: statePath });
const noise = [...bag.pageErrors, ...bag.console, ...bag.http, ...bag.failed];
if (noise.length) console.log('NOISE:', JSON.stringify([...new Set(noise)].slice(0, 12), null, 1));
await browser.close();
process.exit(failed ? 1 : 0);
