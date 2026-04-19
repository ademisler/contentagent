#!/usr/bin/env node
/* eslint-disable */
const fs = require('fs');
const path = require('path');
const { PNG } = require('pngjs');

const OUT_DIR = '/workspace/assets/placeholders';

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function hexToRgb(hex) {
  const m = hex.replace('#', '');
  const bigint = parseInt(m, 16);
  return {
    r: (bigint >> 16) & 255,
    g: (bigint >> 8) & 255,
    b: bigint & 255,
  };
}

function drawBackground(png, color) {
  const { r, g, b } = hexToRgb(color);
  for (let y = 0; y < png.height; y++) {
    for (let x = 0; x < png.width; x++) {
      const idx = (png.width * y + x) << 2;
      png.data[idx] = r;
      png.data[idx + 1] = g;
      png.data[idx + 2] = b;
      png.data[idx + 3] = 255;
    }
  }
}

function drawAccentStripes(png, color, spacing) {
  const { r, g, b } = hexToRgb(color);
  for (let y = 0; y < png.height; y++) {
    for (let x = 0; x < png.width; x++) {
      // simple diagonal pattern
      if (((x + y) % spacing) === 0) {
        const idx = (png.width * y + x) << 2;
        png.data[idx] = r;
        png.data[idx + 1] = g;
        png.data[idx + 2] = b;
        png.data[idx + 3] = 255;
      }
    }
  }
}

function generatePng(filename, width, height, base = '#0a3d62', accent = '#3dc1d3') {
  const png = new PNG({ width, height });
  drawBackground(png, base);
  drawAccentStripes(png, accent, Math.max(6, Math.floor(Math.min(width, height) / 40)));
  const outPath = path.join(OUT_DIR, filename);
  return new Promise((resolve, reject) => {
    png.pack().pipe(fs.createWriteStream(outPath))
      .on('finish', () => resolve(outPath))
      .on('error', reject);
  });
}

(async function main() {
  ensureDir(OUT_DIR);
  const tasks = [];
  // Icons
  tasks.push(generatePng('icon-128x128.png', 128, 128));
  tasks.push(generatePng('icon-256x256.png', 256, 256));
  // Banners
  tasks.push(generatePng('banner-772x250.png', 772, 250));
  tasks.push(generatePng('banner-1544x500.png', 1544, 500));
  // Screenshots (generic size)
  for (let i = 1; i <= 5; i++) {
    tasks.push(generatePng(`screenshot-${i}.png`, 1280, 800, '#1e272e', '#34ace0'));
  }
  const results = await Promise.all(tasks);
  console.log('Generated assets:\n' + results.map(p => ' - ' + p).join('\n'));
})();