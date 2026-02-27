import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '..');
const TMP_DIR = path.join(ROOT, '.build');
const DIST_DIR = path.join(ROOT, 'dist');
const PLUGIN_SLUG = 'barefoot-engine';

const packageJson = JSON.parse(fs.readFileSync(path.join(ROOT, 'package.json'), 'utf8'));
const version = packageJson.version;
const zipName = `${PLUGIN_SLUG}-v${version}.zip`;
const zipPath = path.join(DIST_DIR, zipName);
const stageDir = path.join(TMP_DIR, PLUGIN_SLUG);
const requiredRuntimeFiles = [
  path.join(ROOT, 'barefoot-engine.php'),
  path.join(ROOT, 'libraries', 'vendor', 'autoload.php'),
  path.join(ROOT, 'assets', 'dist', '.vite', 'manifest.json'),
];
const requiredZipEntries = [
  `${PLUGIN_SLUG}/barefoot-engine.php`,
  `${PLUGIN_SLUG}/libraries/vendor/autoload.php`,
  `${PLUGIN_SLUG}/assets/dist/.vite/manifest.json`,
];

function run(command, cwd = ROOT, stdio = 'inherit') {
  return execSync(command, { cwd, stdio });
}

function ensureRuntimePrerequisites() {
  for (const filePath of requiredRuntimeFiles) {
    if (!fs.existsSync(filePath)) {
      throw new Error(
        `Missing required runtime file for package: ${filePath}\n` +
          'Run composer install and npm run build before packaging.'
      );
    }
  }
}

function validateZipContents() {
  const entries = run(`unzip -Z1 ${JSON.stringify(zipPath)}`, ROOT, ['ignore', 'pipe', 'ignore'])
    .toString('utf8')
    .split('\n')
    .map((entry) => entry.trim())
    .filter((entry) => entry !== '');

  for (const requiredEntry of requiredZipEntries) {
    if (!entries.includes(requiredEntry)) {
      throw new Error(`Packaged zip is missing required file: ${requiredEntry}`);
    }
  }
}

const distIgnorePath = path.join(ROOT, '.distignore');
const excludeRules = fs.existsSync(distIgnorePath)
  ? fs
      .readFileSync(distIgnorePath, 'utf8')
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line.length > 0 && !line.startsWith('#'))
  : [];

const excludes = excludeRules.map((rule) => `--exclude=${JSON.stringify(rule)}`).join(' ');

fs.rmSync(TMP_DIR, { recursive: true, force: true });
fs.rmSync(DIST_DIR, { recursive: true, force: true });
fs.mkdirSync(stageDir, { recursive: true });
fs.mkdirSync(DIST_DIR, { recursive: true });
ensureRuntimePrerequisites();

run(`rsync -a ${excludes} ./ ${JSON.stringify(`${stageDir}/`)}`);

run(`zip -rq ${JSON.stringify(zipPath)} ${JSON.stringify(PLUGIN_SLUG)}`, TMP_DIR);
validateZipContents();

fs.rmSync(TMP_DIR, { recursive: true, force: true });

process.stdout.write(`${zipPath}\n`);
