#!/usr/bin/env node
/* eslint-disable */
const fs = require('fs');
const path = require('path');

const version = process.argv[2];
if (!version || !/^\d+\.\d+\.\d+$/.test(version)) {
  console.error('Usage: node scripts/bump-version.cjs X.Y.Z');
  process.exit(1);
}

const pluginMain = path.join(__dirname, '..', 'plugin', 'ai-content-agent.php');
let php = fs.readFileSync(pluginMain, 'utf8');
php = php.replace(/^(\s*\*\s*Version:\s*)([\d.]+)\s*$/m, `$1${version}`);
fs.writeFileSync(pluginMain, php);

const pkgPath = path.join(__dirname, '..', 'plugin', 'package.json');
const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
pkg.version = version;
fs.writeFileSync(pkgPath, JSON.stringify(pkg, null, 2) + '\n');

console.log(`Bumped versions to ${version}`);