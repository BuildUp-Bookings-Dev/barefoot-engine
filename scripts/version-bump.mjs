import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '..');
const bumpArg = process.argv[2] || 'patch';

const packageJsonPath = path.join(ROOT, 'package.json');
const pluginFilePath = path.join(ROOT, 'barefoot-engine.php');
const composerFilePath = path.join(ROOT, 'composer.json');
const readmePath = path.join(ROOT, 'readme.txt');

const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
const currentVersion = packageJson.version;

function bumpVersion(version, bump) {
  if (/^\d+\.\d+\.\d+$/.test(bump)) {
    return bump;
  }

  const [major, minor, patch] = version.split('.').map(Number);

  if (bump === 'major') {
    return `${major + 1}.0.0`;
  }

  if (bump === 'minor') {
    return `${major}.${minor + 1}.0`;
  }

  return `${major}.${minor}.${patch + 1}`;
}

const nextVersion = bumpVersion(currentVersion, bumpArg);

packageJson.version = nextVersion;
fs.writeFileSync(packageJsonPath, `${JSON.stringify(packageJson, null, 2)}\n`);

const composerJson = JSON.parse(fs.readFileSync(composerFilePath, 'utf8'));
composerJson.version = nextVersion;
fs.writeFileSync(composerFilePath, `${JSON.stringify(composerJson, null, 2)}\n`);

const pluginFile = fs
  .readFileSync(pluginFilePath, 'utf8')
  .replace(/Version:\s+\d+\.\d+\.\d+/u, `Version:     ${nextVersion}`)
  .replace(
    /define\('BAREFOOT_ENGINE_VERSION', '\d+\.\d+\.\d+'\);/u,
    `define('BAREFOOT_ENGINE_VERSION', '${nextVersion}');`
  );
fs.writeFileSync(pluginFilePath, pluginFile);

const readme = fs
  .readFileSync(readmePath, 'utf8')
  .replace(/Stable tag:\s+\d+\.\d+\.\d+/u, `Stable tag: ${nextVersion}`);
fs.writeFileSync(readmePath, readme);

process.stdout.write(`${nextVersion}\n`);
