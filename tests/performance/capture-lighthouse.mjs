#!/usr/bin/env node
/**
 * Capture Lighthouse reports for /archive/3 (mobile + desktop).
 *
 * Usage (from project root on host):
 *   CHROME_PATH=/usr/bin/google-chrome \
 *   NODE_PATH=tests/e2e/node_modules \
 *   node tests/performance/capture-lighthouse.mjs [label]
 *
 * label defaults to "pre-sub1" — use "post-sub1" or "post-sub3-sub4" for
 * subsequent capture points.
 *
 * Output: tests/performance/archive-baseline-{label}-{date}-{mobile|desktop}.json
 */

import { createRequire } from 'module';
import { writeFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// Resolve modules from the e2e node_modules directory.
const nodeModulesDir = resolve(__dirname, '../e2e/node_modules');
const require = createRequire(import.meta.url);

// Dynamic ESM imports for lighthouse (ESM-only package).
const { default: lighthouse } = await import(
  resolve(nodeModulesDir, 'lighthouse/core/index.js')
);
const chromeLauncher = await import(
  resolve(nodeModulesDir, 'chrome-launcher/dist/chrome-launcher.js')
);
const launch = chromeLauncher.launch ?? chromeLauncher.default?.launch;

const TARGET_URL =
  process.env.LIGHTHOUSE_URL ||
  'https://fridaynightskate2.ddev.site/archive/3';
const LABEL = process.argv[2] || 'pre-sub1';
const DATE = new Date().toISOString().slice(0, 10);
const OUT_DIR = resolve(__dirname);

const CONFIGS = [
  {
    name: 'mobile',
    formFactor: 'mobile',
    screenEmulation: {
      mobile: true,
      width: 375,
      height: 812,
      deviceScaleFactor: 3,
      disabled: false,
    },
    throttling: {
      rttMs: 150,
      throughputKbps: 1638.4,
      cpuSlowdownMultiplier: 4,
      requestLatencyMs: 562.5,
      downloadThroughputKbps: 1474.56,
      uploadThroughputKbps: 675,
    },
  },
  {
    name: 'desktop',
    formFactor: 'desktop',
    screenEmulation: {
      mobile: false,
      width: 1350,
      height: 940,
      deviceScaleFactor: 1,
      disabled: false,
    },
    throttling: {
      rttMs: 40,
      throughputKbps: 10240,
      cpuSlowdownMultiplier: 1,
      requestLatencyMs: 0,
      downloadThroughputKbps: 0,
      uploadThroughputKbps: 0,
    },
  },
];

for (const cfg of CONFIGS) {
  console.log(`\n=== Lighthouse ${cfg.name} for ${TARGET_URL} ===`);

  const chrome = await launch({
    chromePath: process.env.CHROME_PATH,
    chromeFlags: [
      '--headless',
      '--no-sandbox',
      '--disable-gpu',
      '--ignore-certificate-errors',
    ],
  });

  try {
    const options = {
      logLevel: 'error',
      output: 'json',
      port: chrome.port,
      formFactor: cfg.formFactor,
      screenEmulation: cfg.screenEmulation,
      throttling: cfg.throttling,
      onlyCategories: ['performance'],
    };

    const runnerResult = await lighthouse(TARGET_URL, options);
    const report = runnerResult.lhr;

    const outFile = resolve(
      OUT_DIR,
      `archive-baseline-${LABEL}-${DATE}-${cfg.name}.json`
    );
    writeFileSync(outFile, JSON.stringify(report, null, 2));

    const score = Math.round(report.categories.performance.score * 100);
    const fcp = report.audits['first-contentful-paint'].displayValue;
    const lcp = report.audits['largest-contentful-paint'].displayValue;
    const tbt = report.audits['total-blocking-time'].displayValue;
    const cls = report.audits['cumulative-layout-shift'].displayValue;

    console.log(`  Performance score : ${score}`);
    console.log(`  FCP               : ${fcp}`);
    console.log(`  LCP               : ${lcp}`);
    console.log(`  TBT               : ${tbt}`);
    console.log(`  CLS               : ${cls}`);
    console.log(`  Saved to          : ${outFile}`);
  } finally {
    await chrome.kill();
  }
}
