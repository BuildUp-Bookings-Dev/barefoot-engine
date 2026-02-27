import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '..');

const REQUIRED_SECTIONS = ['Added', 'Changed', 'Fixed', 'Security'];
const VERSION_LABEL_REGEX = /^\d+\.\d+\.\d+$/;
const DATE_REGEX = /^\d{4}-\d{2}-\d{2}$/;
const LEVEL_TWO_HEADING_REGEX = /^## \[([^\]]+)\](?: - (\d{4}-\d{2}-\d{2}))?$/;

function normalizeLineEndings(content) {
  return content.replace(/\r\n/g, '\n');
}

function splitLines(content) {
  return normalizeLineEndings(content).split('\n');
}

function trimEmptyLines(lines) {
  let start = 0;
  let end = lines.length - 1;

  while (start <= end && lines[start].trim() === '') {
    start += 1;
  }

  while (end >= start && lines[end].trim() === '') {
    end -= 1;
  }

  return lines.slice(start, end + 1);
}

function trimLeadingEmptyLines(lines) {
  let start = 0;
  while (start < lines.length && lines[start].trim() === '') {
    start += 1;
  }

  return lines.slice(start);
}

function trimTrailingEmptyLines(lines) {
  let end = lines.length - 1;
  while (end >= 0 && lines[end].trim() === '') {
    end -= 1;
  }

  return lines.slice(0, end + 1);
}

function getHeadings(lines) {
  const headings = [];

  for (let i = 0; i < lines.length; i += 1) {
    const line = lines[i];
    if (!line.startsWith('## ')) {
      continue;
    }

    const match = line.match(LEVEL_TWO_HEADING_REGEX);
    if (!match) {
      throw new Error(`Invalid heading format at line ${i + 1}: "${line}"`);
    }

    headings.push({
      index: i,
      line,
      label: match[1],
      date: typeof match[2] === 'string' ? match[2] : '',
    });
  }

  if (headings.length === 0) {
    throw new Error('No changelog sections found. Add "## [Unreleased]" and version sections.');
  }

  return headings;
}

function getBlock(lines, headings, headingPosition) {
  const start = headings[headingPosition].index + 1;
  const end = headingPosition + 1 < headings.length ? headings[headingPosition + 1].index : lines.length;
  return lines.slice(start, end);
}

function validateSectionsInBlock(blockLines, label) {
  const blockText = blockLines.join('\n');

  for (const section of REQUIRED_SECTIONS) {
    const matcher = new RegExp(`^### ${section}$`, 'm');
    if (!matcher.test(blockText)) {
      throw new Error(`Section "${label}" is missing required heading: "### ${section}".`);
    }
  }
}

function hasMeaningfulNotes(blockLines) {
  const meaningful = blockLines
    .map((line) => line.trim())
    .filter((line) => line !== '')
    .filter((line) => !/^### (Added|Changed|Fixed|Security)$/.test(line))
    .filter((line) => !/^-\s*(?:\.\.\.|_?no changes yet_?|_?nothing yet_?)$/i.test(line))
    .filter((line) => !/^<!--.*-->$/.test(line));

  return meaningful.length > 0;
}

export function resolveChangelogPath(rootDir = ROOT) {
  const candidates = [
    path.join(rootDir, 'CHANGELOG.md'),
    path.join(rootDir, 'changelog.md'),
  ];

  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }

  throw new Error('CHANGELOG.md (or changelog.md) not found.');
}

export function readChangelog(changelogPath) {
  return normalizeLineEndings(fs.readFileSync(changelogPath, 'utf8'));
}

export function writeChangelog(changelogPath, content) {
  const normalized = normalizeLineEndings(content).replace(/\s*$/, '');
  fs.writeFileSync(changelogPath, `${normalized}\n`);
}

export function validateStructure(content) {
  const lines = splitLines(content);
  const headings = getHeadings(lines);

  if (!/^# Changelog$/m.test(content)) {
    throw new Error('Missing top-level "# Changelog" heading.');
  }

  const unreleasedHeadings = headings.filter((heading) => heading.label === 'Unreleased');
  if (unreleasedHeadings.length !== 1) {
    throw new Error('Exactly one "## [Unreleased]" section is required.');
  }

  for (let i = 0; i < headings.length; i += 1) {
    const heading = headings[i];

    if (heading.label === 'Unreleased') {
      if (heading.date !== '') {
        throw new Error('"## [Unreleased]" must not include a date.');
      }
    } else {
      if (!VERSION_LABEL_REGEX.test(heading.label)) {
        throw new Error(`Invalid version label "${heading.label}". Use x.y.z format.`);
      }

      if (!DATE_REGEX.test(heading.date)) {
        throw new Error(`Version ${heading.label} must include date in YYYY-MM-DD format.`);
      }
    }

    const block = getBlock(lines, headings, i);
    validateSectionsInBlock(block, heading.label);
  }

  return {
    headings,
    unreleasedIndex: headings.findIndex((heading) => heading.label === 'Unreleased'),
  };
}

export function promoteUnreleasedToVersion(content, version, dateISO) {
  if (!VERSION_LABEL_REGEX.test(version)) {
    throw new Error(`Invalid version "${version}". Use x.y.z.`);
  }

  if (!DATE_REGEX.test(dateISO)) {
    throw new Error(`Invalid date "${dateISO}". Use YYYY-MM-DD.`);
  }

  const lines = splitLines(content);
  const { headings, unreleasedIndex } = validateStructure(content);
  const unreleasedBlock = getBlock(lines, headings, unreleasedIndex);
  const unreleasedBody = trimEmptyLines(unreleasedBlock);

  if (!hasMeaningfulNotes(unreleasedBody)) {
    throw new Error('The [Unreleased] section has no meaningful notes to release.');
  }

  const unreleasedStart = headings[unreleasedIndex].index;
  const unreleasedEnd = unreleasedIndex + 1 < headings.length ? headings[unreleasedIndex + 1].index : lines.length;

  const before = trimTrailingEmptyLines(lines.slice(0, unreleasedStart));
  const after = trimLeadingEmptyLines(lines.slice(unreleasedEnd));

  const scaffold = [
    '## [Unreleased]',
    '',
    '### Added',
    '',
    '### Changed',
    '',
    '### Fixed',
    '',
    '### Security',
  ];

  const versionHeading = `## [${version}] - ${dateISO}`;

  const result = [
    ...before,
    '',
    ...scaffold,
    '',
    versionHeading,
    '',
    ...unreleasedBody,
  ];

  if (after.length > 0) {
    result.push('', ...after);
  }

  return `${result.join('\n').replace(/\s*$/, '')}\n`;
}

export function extractVersionNotes(content, version) {
  if (!VERSION_LABEL_REGEX.test(version)) {
    throw new Error(`Invalid version "${version}". Use x.y.z.`);
  }

  const lines = splitLines(content);
  const { headings } = validateStructure(content);
  const versionIndex = headings.findIndex((heading) => heading.label === version);

  if (versionIndex === -1) {
    throw new Error(`Version block not found in changelog: ${version}`);
  }

  const block = trimEmptyLines(getBlock(lines, headings, versionIndex));
  if (!hasMeaningfulNotes(block)) {
    throw new Error(`Version ${version} notes are empty.`);
  }

  return `${block.join('\n').replace(/\s*$/, '')}\n`;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const command = process.argv[2] || 'check';

  if (command !== 'check') {
    throw new Error(`Unknown command "${command}". Use: node ./scripts/changelog.mjs check`);
  }

  const changelogPath = resolveChangelogPath(ROOT);
  const content = readChangelog(changelogPath);
  validateStructure(content);
  process.stdout.write(`Changelog is valid: ${changelogPath}\n`);
}
