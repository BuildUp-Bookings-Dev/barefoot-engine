import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import {
  extractVersionNotes,
  promoteUnreleasedToVersion,
  readChangelog,
  resolveChangelogPath,
  validateStructure,
  writeChangelog,
} from './changelog.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '..');
const bump = process.argv[2] || 'patch';
const BUILD_DIR = path.join(ROOT, '.build');

function run(command, options = {}) {
  execSync(command, {
    cwd: ROOT,
    stdio: 'inherit',
    ...options,
  });
}

function runCapture(command) {
  return execSync(command, {
    cwd: ROOT,
    stdio: ['ignore', 'pipe', 'ignore'],
  })
    .toString('utf8')
    .trim();
}

if (!fs.existsSync(path.join(ROOT, '.git'))) {
  throw new Error('Git repository is not initialized.');
}

run('gh auth status');

const remote = runCapture('git remote');
if (!remote.split('\n').includes('origin')) {
  throw new Error('Git remote "origin" is required before release.');
}

const currentBranch = runCapture('git branch --show-current');
if (currentBranch !== 'main') {
  throw new Error(`Release must run from "main". Current branch: "${currentBranch}".`);
}

const workingTree = runCapture('git status --porcelain');
if (workingTree !== '') {
  throw new Error('Working tree is not clean. Commit or stash changes before running release.');
}

const changelogPath = resolveChangelogPath(ROOT);
const initialChangelog = readChangelog(changelogPath);
validateStructure(initialChangelog);

run(`node ./scripts/version-bump.mjs ${bump}`);
const packageJson = JSON.parse(fs.readFileSync(path.join(ROOT, 'package.json'), 'utf8'));
const version = packageJson.version;
const tag = `v${version}`;
const releaseDate = new Date().toISOString().slice(0, 10);

const promotedChangelog = promoteUnreleasedToVersion(initialChangelog, version, releaseDate);
writeChangelog(changelogPath, promotedChangelog);
const releaseNotes = extractVersionNotes(promotedChangelog, version);

run('node ./scripts/changelog.mjs check');
run('composer install --no-dev --optimize-autoloader');

run('npm run build');
run('npm run package');

const zipPath = path.join(ROOT, 'dist', `barefoot-engine-v${version}.zip`);
if (!fs.existsSync(zipPath)) {
  throw new Error(`Package not found: ${zipPath}`);
}

fs.mkdirSync(BUILD_DIR, { recursive: true });
const notesPath = path.join(BUILD_DIR, `release-notes-${tag}.md`);
fs.writeFileSync(notesPath, releaseNotes);

run('git add -A');
run(`git commit -m "chore(release): ${tag}"`);
run(`git tag ${tag}`);
run('git push origin main');
run('git push origin --tags');
run(
  `gh release create ${tag} ${JSON.stringify(zipPath)} --title ${JSON.stringify(tag)} --notes-file ${JSON.stringify(notesPath)} --latest`
);
