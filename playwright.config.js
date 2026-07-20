// Config de Playwright para el job e2e-smoke del pipeline (ver .github/workflows/deploy-production.yml).
// El servidor PHP ya lo levanta el propio workflow antes de correr los tests
// (webServer no se usa acá porque necesitamos migrar/seedear la BD primero).
module.exports = {
    testDir: './tests/e2e',
    timeout: 30000,
    retries: 1,
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://localhost:8899',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    reporter: [['list'], ['html', { open: 'never' }]],
};
