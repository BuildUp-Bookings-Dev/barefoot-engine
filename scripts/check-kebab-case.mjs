import { readdirSync, statSync } from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const scanRoots = [
  'bootstrap',
  'support',
  'modules',
  'widgets',
  'public',
  'settings',
  'views',
  path.join('assets', 'src'),
  'docs',
  'scripts',
];

const ignoredDirectories = new Set([
  '.git',
  '.build',
  '.playwright-cli',
  'node_modules',
  'dist',
]);

const allowedRootFiles = new Set([
  'barefoot-engine.php',
  'uninstall.php',
  'README.md',
  'CHANGELOG.md',
  'readme.txt',
  'composer.json',
  'composer.lock',
  'package.json',
  'package-lock.json',
  'vite.config.js',
  'postcss.config.cjs',
  'phpcs.xml.dist',
]);

const isIgnoredPath = (relativePath) => {
  const normalized = relativePath.replaceAll(path.sep, '/');

  return (
    normalized.startsWith('libraries/vendor/') ||
    normalized.startsWith('assets/dist/') ||
    normalized === 'libraries/vendor' ||
    normalized === 'assets/dist'
  );
};

const hasInvalidSegment = (segment, isRootFile = false) => {
  if (segment === '' || segment === '.' || segment === '..') {
    return false;
  }

  if (isRootFile && allowedRootFiles.has(segment)) {
    return false;
  }

  return /[A-Z_]/.test(segment);
};

const violations = [];

const scanPath = (relativePath) => {
  if (isIgnoredPath(relativePath)) {
    return;
  }

  const absolutePath = path.join(root, relativePath);
  const stats = statSync(absolutePath);
  const segments = relativePath.split(path.sep);

  segments.forEach((segment, index) => {
    const isRootFile = stats.isFile() && index === 0;
    if (hasInvalidSegment(segment, isRootFile)) {
      violations.push(relativePath.replaceAll(path.sep, '/'));
    }
  });

  if (!stats.isDirectory()) {
    return;
  }

  for (const entry of readdirSync(absolutePath, { withFileTypes: true })) {
    if (entry.isDirectory() && ignoredDirectories.has(entry.name)) {
      continue;
    }

    scanPath(path.join(relativePath, entry.name));
  }
};

for (const scanRoot of scanRoots) {
  const absolute = path.join(root, scanRoot);

  try {
    if (!statSync(absolute).isDirectory()) {
      continue;
    }
  } catch {
    continue;
  }

  scanPath(scanRoot);
}

const uniqueViolations = [...new Set(violations)].sort();

if (uniqueViolations.length > 0) {
  console.error('Found non-kebab-case plugin-owned paths:');
  uniqueViolations.forEach((entry) => {
    console.error(`- ${entry}`);
  });
  process.exit(1);
}

console.log('Plugin-owned paths use kebab-case.');
