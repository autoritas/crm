// refresh-cookie.js
//
// Abre Chromium headless con stealth, logea en PLACSP como Operador Economico,
// extrae las cookies de sesion y las escribe en el fichero que usa el
// provider PHP (PLACSP_COOKIE_FILE).
//
// - `playwright-extra` + `puppeteer-extra-plugin-stealth` desactivan las
//   huellas mas obvias de navegador automatizado (navigator.webdriver,
//   navigator.plugins, window.chrome, etc.).
// - Simula typing humano con `page.type({delay})` en vez de fill() instantaneo.
// - Hace screenshot antes y despues del login para depurar (se guardan en
//   storage/logs/placsp-*.png). En fallo tambien graba el HTML.
// - Si el login NO entra, deja la cookie anterior intacta.
//
// Uso:
//   node refresh-cookie.js
//
// Cron (cada 45 min) via refresh.sh.

import { chromium } from 'playwright-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

chromium.use(StealthPlugin());

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = path.resolve(__dirname, '../..');

const HOME_URL             = 'https://contrataciondelestado.es/wps/portal/plataforma/empresas';
const SUCCESS_URL_CONTAINS = '/wps/myportal/';
const NAV_TIMEOUT_MS       = 45_000;

// Lee `.env` de Laravel.
async function loadLaravelEnv(envPath) {
    try {
        const content = await fs.readFile(envPath, 'utf8');
        const env = {};
        for (const line of content.split(/\r?\n/)) {
            const m = line.match(/^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*?)\s*$/);
            if (!m) continue;
            let [, key, raw] = m;
            if (!raw.startsWith('"') && !raw.startsWith("'")) {
                const hashIdx = raw.indexOf(' #');
                if (hashIdx >= 0) raw = raw.slice(0, hashIdx).trim();
            }
            if ((raw.startsWith('"') && raw.endsWith('"')) ||
                (raw.startsWith("'") && raw.endsWith("'"))) {
                raw = raw.slice(1, -1);
            }
            env[key] = raw;
        }
        return env;
    } catch {
        return {};
    }
}

function ts() {
    return new Date().toISOString().replace('T', ' ').replace(/\..+/, '');
}
function log(...a)    { console.log(`[${ts()}]`, ...a); }
function errlog(...a) { console.error(`[${ts()}]`, ...a); }

async function main() {
    const env = await loadLaravelEnv(path.join(PROJECT_ROOT, '.env'));
    const USER       = process.env.PLACSP_USER     ?? env.PLACSP_USER;
    const PASS       = process.env.PLACSP_PASSWORD ?? env.PLACSP_PASSWORD;
    const COOKIE_OUT = process.env.PLACSP_COOKIE_FILE
        ?? env.PLACSP_COOKIE_FILE
        ?? path.join(PROJECT_ROOT, 'storage/app/certs/placsp.cookie');

    if (!USER || !PASS) {
        errlog('Faltan PLACSP_USER / PLACSP_PASSWORD en .env');
        process.exit(1);
    }
    log(`user=${USER} cookie_out=${COOKIE_OUT} pass_len=${PASS.length}`);

    // channel:'chrome' fuerza a Playwright a usar el Google Chrome instalado
    // en el sistema (no el chromium-headless-shell). Chrome real tiene la
    // huella TLS / JA3 identica a la de un usuario normal, lo que evita que
    // anti-bots que fingerprinting a ese nivel lo rechacen.
    const browser = await chromium.launch({
        headless: true,
        channel: 'chrome',
        args: [
            '--disable-blink-features=AutomationControlled',
            '--no-sandbox',
            '--disable-dev-shm-usage',
        ],
    });

    const ctx = await browser.newContext({
        userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        locale: 'es-ES',
        viewport: { width: 1280, height: 900 },
        extraHTTPHeaders: {
            'Accept-Language': 'es-ES,es;q=0.9',
        },
    });

    const page = await ctx.newPage();

    const shotDir = path.join(PROJECT_ROOT, 'storage/logs');
    await fs.mkdir(shotDir, { recursive: true });

    try {
        log(`abriendo ${HOME_URL}`);
        await page.goto(HOME_URL, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT_MS });

        await page.waitForSelector('#input_userID_empresas', { timeout: NAV_TIMEOUT_MS });
        await page.waitForSelector('#input_password',        { timeout: NAV_TIMEOUT_MS });

        // Simula typing humano con delays entre pulsaciones
        log('tecleando usuario');
        await page.click('#input_userID_empresas');
        await page.type('#input_userID_empresas', USER, { delay: 80 });

        log('tecleando password');
        await page.click('#input_password');
        await page.type('#input_password', PASS, { delay: 80 });

        // Mini pausa antes de click (como haria un humano)
        await page.waitForTimeout(500);

        await page.screenshot({ path: path.join(shotDir, 'placsp-pre-login.png'), fullPage: false });

        log('click en Entrar');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT_MS }).catch(() => null),
            page.click('button[type="submit"][name$="__login"]'),
        ]);

        await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => null);

        const finalUrl = page.url();
        log(`final_url=${finalUrl}`);

        await page.screenshot({ path: path.join(shotDir, 'placsp-post-login.png'), fullPage: false });

        if (!finalUrl.includes(SUCCESS_URL_CONTAINS)) {
            const body = await page.content();
            const debugPath = path.join(
                shotDir,
                `placsp-playwright-${new Date().toISOString().replace(/[^0-9]/g, '').slice(0, 14)}.html`
            );
            await fs.writeFile(debugPath, body, 'utf8');
            errlog(`login no redirigio a ${SUCCESS_URL_CONTAINS}. Cookie anterior NO sobreescrita.`);
            errlog(`HTML en ${debugPath}`);
            errlog(`Screenshots en ${shotDir}/placsp-{pre,post}-login.png`);
            process.exit(2);
        }

        const cookies = await ctx.cookies();
        const domainCookies = cookies.filter(
            c => c.domain === 'contrataciondelestado.es'
              || c.domain === '.contrataciondelestado.es'
        );

        if (domainCookies.length === 0) {
            errlog('0 cookies extraidas para contrataciondelestado.es');
            process.exit(3);
        }

        const cookieStr = domainCookies
            .map(c => `${c.name}=${c.value}`)
            .join('; ');

        if (cookieStr.length < 50) {
            errlog(`cookies muy cortas (${cookieStr.length} chars)`);
            process.exit(3);
        }

        await fs.mkdir(path.dirname(COOKIE_OUT), { recursive: true });
        await fs.writeFile(COOKIE_OUT, cookieStr + '\n', { mode: 0o640 });
        log(`OK: ${domainCookies.length} cookies escritas en ${COOKIE_OUT} (${cookieStr.length} chars).`);
        process.exit(0);

    } catch (err) {
        errlog('excepcion:', err.message || err);
        process.exit(4);
    } finally {
        await browser.close().catch(() => null);
    }
}

main();
