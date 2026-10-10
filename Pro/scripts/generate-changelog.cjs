#!/usr/bin/env node
/* eslint-disable */
const fs = require('fs');
const path = require('path');

const ROOT = '/workspace/plugin';
const CHANGELOG = path.join(ROOT, 'CHANGELOG.md');

function readChangelog() {
  return fs.existsSync(CHANGELOG) ? fs.readFileSync(CHANGELOG, 'utf8') : '# Changelog\n\n';
}

function insertVersionBlock(content, version, date, entries) {
  const header = `## ${version} - ${date}\n`;
  const body = entries.map((e) => `- ${e}`).join('\n') + '\n';
  // Remove existing block for this version if present
  const re = new RegExp(`^##\\s+${version}\\s+-[\\s\\S]*?(?=^##\\s+|\\\n$)`, 'm');
  content = content.replace(re, '');
  // Ensure top-level title exists
  if (!content.startsWith('# Changelog')) {
    content = '# Changelog\n\n' + content;
  }
  // Insert at top after title
  const parts = content.split('\n');
  const insertIdx = parts.findIndex((l) => l.trim().startsWith('## '));
  const top = parts[0].startsWith('# Changelog') ? parts.shift() : '# Changelog';
  const rest = parts.join('\n');
  const next = `${top}\n\n${header}${body}\n${rest.trim() ? rest + '\n' : ''}`;
  return next;
}

function main() {
  const version = process.argv[2];
  const date = process.argv[3] || new Date().toISOString().slice(0, 10);
  const entriesArg = process.argv.slice(4).join(' ');
  if (!version || !entriesArg) {
    console.error('Usage: generate-changelog.cjs <version> [YYYY-MM-DD] <entry1;entry2;...>');
    process.exit(1);
  }
  const entries = entriesArg.split(';').map((s) => s.trim()).filter(Boolean);
  let content = readChangelog();
  content = insertVersionBlock(content, version, date, entries);
  fs.writeFileSync(CHANGELOG, content, 'utf8');
  console.log(`CHANGELOG.md updated for ${version}`);
}

main();