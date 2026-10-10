#!/usr/bin/env node
/* eslint-disable */
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const PLUGIN_DIR = '/workspace/plugin';
const OUT_DIR = '/workspace/release';

function run(cmd, cwd = '/workspace') {
  console.log(`$ ${cmd}`);
  execSync(cmd, { stdio: 'inherit', cwd });
}

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function build() {
  run('npm run build:wp', PLUGIN_DIR);
  run('npm run make-pot', PLUGIN_DIR);
}

function zipMinimal(version) {
  ensureDir(OUT_DIR);
  const stage = path.join(OUT_DIR, 'ai-content-agent');
  if (fs.existsSync(stage)) {
    run(`rm -rf ${stage}`);
  }
  ensureDir(stage);
  // Copy minimal runtime files
  const cp = (src, dest) => run(`cp -r ${src} ${dest}`);
  cp(path.join(PLUGIN_DIR, 'ai-content-agent.php'), stage);
  cp(path.join(PLUGIN_DIR, 'includes'), stage);
  ensureDir(path.join(stage, 'admin'));
  cp(path.join(PLUGIN_DIR, 'admin/assets'), path.join(stage, 'admin'));
  // include duplicate JS entry for compatibility (admin/js/index.js)
  cp(path.join(PLUGIN_DIR, 'admin/js'), path.join(stage, 'admin'));
  // include images for WP menu icon
  cp(path.join(PLUGIN_DIR, 'admin/images'), path.join(stage, 'admin'));
  // languages .pot is not required in plugin zip, skipping to reduce size
  cp(path.join(PLUGIN_DIR, 'uninstall.php'), stage);
  cp(path.join(PLUGIN_DIR, 'license.txt'), stage);
  cp(path.join(PLUGIN_DIR, 'readme.txt'), stage);
  cp(path.join(PLUGIN_DIR, 'languages'), stage);
  cp(path.join(PLUGIN_DIR, 'migrations'), stage);
  cp(path.join(PLUGIN_DIR, 'index.css'), stage);
  const zipName = `ai-content-agent-${version}-min.zip`;
  const zipPath = path.join(OUT_DIR, zipName);
  // Remove existing zip to avoid stale entries
  if (fs.existsSync(zipPath)) {
    fs.unlinkSync(zipPath);
  }
  // Use -FS to synchronize archive with file system (remove stale entries)
  run(`cd ${OUT_DIR} && zip -r -9 -FS ${zipName} ai-content-agent`);
  console.log(`Created ${zipPath}`);
}

function main() {
  const version = process.argv[2] || '1.0.0';
  const changelogEntries = process.argv.slice(3).join(' ');
  build();
  if (changelogEntries) {
    run(`node /workspace/scripts/generate-changelog.cjs ${version} ${new Date().toISOString().slice(0,10)} "${changelogEntries}"`);
  }
  zipMinimal(version);
}

main();