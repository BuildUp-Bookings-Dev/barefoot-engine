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

execSync(`rsync -a ${excludes} ./ ${JSON.stringify(`${stageDir}/`)}`, {
  cwd: ROOT,
  stdio: 'inherit',
});

execSync(`zip -rq ${JSON.stringify(zipPath)} ${JSON.stringify(PLUGIN_SLUG)}`, {
  cwd: TMP_DIR,
  stdio: 'inherit',
});

fs.rmSync(TMP_DIR, { recursive: true, force: true });

process.stdout.write(`${zipPath}\n`);
