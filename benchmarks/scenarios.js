/**
 * k6 benchmark scenarios for the Magna delivery API.
 *
 * Target environment: 4 vCPU / 8 GB, PostgreSQL 16, Redis, FrankenPHP.
 * Seed data: 100k entries (20 types × 5k), 50k media rows.
 *
 * Run:
 *   k6 run benchmarks/scenarios.js --out json=benchmarks/results.json
 *
 * To compare against a stored baseline:
 *   node benchmarks/compare.js benchmarks/baseline.json benchmarks/results.json
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const TOKEN = __ENV.DELIVERY_TOKEN || '';

/** Custom metric: p99 latency per scenario (matches spec budget table). */
const cacheHitLatency = new Trend('delivery_cache_hit_ms', true);
const cacheMissLatency = new Trend('delivery_cache_miss_ms', true);
const filteredListLatency = new Trend('delivery_filtered_list_ms', true);

export const options = {
    scenarios: {
        /**
         * Cache-hit scenario: warm the cache first (setup), then hammer the
         * same URL to measure serve-from-body-cache latency.
         * Budget: p99 < 10 ms.
         */
        cache_hit: {
            executor: 'constant-vus',
            vus: 50,
            duration: '30s',
            exec: 'cacheHit',
            tags: { scenario: 'cache_hit' },
        },
        /**
         * Cache-miss scenario: randomise the per_page cursor so each request
         * misses the body cache and hits the DB.
         * Budget: p99 < 50 ms (single entry), < 80 ms (filtered list).
         */
        cache_miss_single: {
            executor: 'constant-vus',
            vus: 20,
            duration: '30s',
            exec: 'cacheMissSingle',
            tags: { scenario: 'cache_miss_single' },
        },
        filtered_list: {
            executor: 'constant-vus',
            vus: 20,
            duration: '30s',
            exec: 'filteredList',
            tags: { scenario: 'filtered_list' },
        },
    },
    thresholds: {
        // These match docs/performance-spec.md §1 budgets.
        delivery_cache_hit_ms: ['p(99)<10'],
        delivery_cache_miss_ms: ['p(99)<50'],
        delivery_filtered_list_ms: ['p(99)<80'],
        http_req_failed: ['rate<0.01'],
    },
};

const headers = TOKEN !== ''
    ? { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' }
    : { Accept: 'application/json' };

/** Cache-hit: always fetch the same entry (warm on first iteration). */
export function cacheHit() {
    const res = http.get(`${BASE_URL}/api/v1/content/bench_type_0/bench-0-1`, { headers });
    check(res, { 'status 200': (r) => r.status === 200 });
    cacheHitLatency.add(res.timings.duration);
}

/** Cache-miss single: random entry id to avoid cache. */
export function cacheMissSingle() {
    const type = Math.floor(Math.random() * 20);
    const entry = Math.floor(Math.random() * 5000);
    const res = http.get(`${BASE_URL}/api/v1/content/bench_type_${type}/bench-${type}-${entry}`, { headers });
    check(res, { 'status 200 or 404': (r) => r.status === 200 || r.status === 404 });
    cacheMissLatency.add(res.timings.duration);
}

/** Filtered list: 20 items, cursor pagination, random type. */
export function filteredList() {
    const type = Math.floor(Math.random() * 20);
    const res = http.get(`${BASE_URL}/api/v1/content/bench_type_${type}?per_page=20&sort=-published_at&bust=${Math.random()}`, { headers });
    check(res, { 'status 200': (r) => r.status === 200 });
    filteredListLatency.add(res.timings.duration);
}
