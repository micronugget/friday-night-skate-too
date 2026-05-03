#!/usr/bin/env node
/**
 * Capture Lighthouse reports for /archive/3 (mobile + desktop).
 *
 * Usage (inside DDEV):
 *   node tests/performance/capture-lighthouse.js [label]
 *
 * label defaults to "pre-sub1" — use "post-sub1" or "post-sub3-sub4" for
 * subsequent capture points.
 *
 * Output: tests/performance/archive-baseline-{label}-{date}-{mobile|desktop}.json
 */

'use strict';

const lighthouse = require('lighthouse');
const chromeLauncher = require('chrome-launcher');
const fs = require('fs');
const path = require('path');

const TARGET_URL = process.env.LIGHTHOUSE_URL || 'https://fridaynightskate2.ddev.site/archive/3';
const LABEL = process.argv[2] || 'pre-sub1';
const DATE = new Date().toISOString().slice(0, 10);
const OUT_DIR = path.resolve(__dirname);

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

async function run() {
  for (const cfg of CONFIGS) {
    console.log(`\n=== Lighthouse ${cfg.name} for ${TARGET_URL} ===`);

    const chrome = await chromeLauncher.launch({
      chromeFlags: ['--headless', '--no-sandbox', '--disable-gpu', '--ignore-certificate-errors'],
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

      const outFile = path.join(
        OUT_DIR,
        `archive-baseline-${LABEL}-${DATE}-${cfg.name}.json`
      );
      fs.writeFileSync(outFile, JSON.stringify(report, null, 2));

      const score = report.categories.performance.score * 100;
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
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
