#!/usr/bin/env node
/**
 * Compare two k6 JSON result files and fail if any tracked metric
 * regressed by more than THRESHOLD (default 10%).
 *
 * Usage:
 *   node benchmarks/compare.js benchmarks/baseline.json benchmarks/results.json
 *
 * To update the baseline after an intentional improvement:
 *   cp benchmarks/results.json benchmarks/baseline.json
 *   git add benchmarks/baseline.json
 *   git commit -m "perf: update benchmark baseline"
 */

const fs = require('fs');

const THRESHOLD = parseFloat(process.env.REGRESSION_THRESHOLD || '0.10');

const TRACKED = [
    'delivery_cache_hit_ms',
    'delivery_cache_miss_ms',
    'delivery_filtered_list_ms',
];

function loadMetrics(file) {
    const raw = fs.readFileSync(file, 'utf8');
    const metrics = {};
    for (const line of raw.split('\n')) {
        if (!line.trim()) continue;
        try {
            const obj = JSON.parse(line);
            if (obj.type === 'Metric' && TRACKED.includes(obj.data.name)) {
                metrics[obj.data.name] = obj.data;
            }
        } catch {}
    }
    return metrics;
}

const [, , baselineFile, resultsFile] = process.argv;
if (!baselineFile || !resultsFile) {
    console.error('Usage: compare.js <baseline.json> <results.json>');
    process.exit(1);
}

const baseline = loadMetrics(baselineFile);
const results = loadMetrics(resultsFile);

let failed = false;

for (const metric of TRACKED) {
    const base = baseline[metric];
    const curr = results[metric];
    if (!base || !curr) {
        console.warn(`WARN: metric "${metric}" missing from one of the files — skipping.`);
        continue;
    }
    const baseP99 = base.values?.['p(99)'];
    const currP99 = curr.values?.['p(99)'];
    if (baseP99 == null || currP99 == null) {
        console.warn(`WARN: p(99) not found for "${metric}" — skipping.`);
        continue;
    }
    if (baseP99 === 0) {
        // Unset baseline — first run. Accept the current value as the new baseline.
        console.log(`⚪  ${metric}: baseline not yet recorded (p99=0) — current=${currP99.toFixed(1)}ms  [first run, update baseline]`);
        continue;
    }
    const change = (currP99 - baseP99) / baseP99;
    const pct = (change * 100).toFixed(1);
    const symbol = change > THRESHOLD ? '❌' : change < -0.01 ? '✅' : '⚪';
    console.log(`${symbol}  ${metric}: baseline p99=${baseP99.toFixed(1)}ms  current=${currP99.toFixed(1)}ms  change=${pct}%`);
    if (change > THRESHOLD) {
        failed = true;
    }
}

if (failed) {
    console.error(`\nRegression detected (threshold: ${(THRESHOLD * 100).toFixed(0)}%). Update baseline or fix the regression.`);
    process.exit(1);
} else {
    console.log('\nNo regressions detected.');
}
