// Smoke tests críticos del pipeline (job e2e-smoke). Corren contra un servidor
// PHP real + BD recién migrada y sembrada con database/ci_test_seed.sql
// (ver .github/workflows/deploy-production.yml).
const { test, expect } = require('@playwright/test');

const CLIENTE = { email: 'ci.cliente@example.test', password: 'Ci-TestPass123!' };
const ADMIN = { email: 'ci.admin@example.test', password: 'Ci-TestPass123!' };

async function login(page, creds) {
    // login.php tiene el form de login Y el de registro en el mismo DOM (tabs
    // de Bootstrap, ambos ocultos con CSS, no removidos) -> los locators por
    // rol/label matchean 2 elementos. Se usan los IDs del form de login.
    await page.goto('/login.php');
    await page.locator('#login-email').fill(creds.email);
    await page.locator('#login-password').fill(creds.password);
    await page.getByRole('button', { name: 'Ingresar' }).click();
}

test('la home carga', async ({ page }) => {
    await page.goto('/index.php');
    await expect(page).toHaveTitle(/JC Envios/);
});

test('la pagina de login carga con el formulario', async ({ page }) => {
    await page.goto('/login.php');
    await expect(page.locator('#login-email')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Ingresar' })).toBeVisible();
});

test('el cliente puede loguearse y llega al dashboard', async ({ page }) => {
    await login(page, CLIENTE);
    await expect(page).toHaveURL(/\/dashboard\//);
});

test('el admin puede loguearse y llega al panel de administracion', async ({ page }) => {
    await login(page, ADMIN);
    await expect(page).toHaveURL(/\/admin\//);
});

test('login falla con contrasena incorrecta', async ({ page }) => {
    await page.goto('/login.php');
    await page.locator('#login-email').fill(CLIENTE.email);
    await page.locator('#login-password').fill('password-incorrecta');
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page).toHaveURL(/login\.php/);
});

test('flujo critico: el cliente crea una orden de envio de punta a punta', async ({ page }) => {
    await login(page, CLIENTE);
    await expect(page).toHaveURL(/\/dashboard\//);

    // El CSRF token real de la sesión ya autenticada vive en el HTML de esta
    // página (ver footer.php) — se reusa para el POST de creación de orden,
    // igual que hace el propio front (interceptor global de fetch).
    const csrfToken = await page.evaluate(() => CSRF_TOKEN);

    const response = await page.request.post('/api/?accion=createTransaccion', {
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        data: {
            cuentaID: 900001,
            tasaID: 900001,
            montoOrigen: 10000,
            monedaOrigen: 'CLP',
            montoDestino: 0,
            monedaDestino: 'VES',
            formaDePago: 'Transferencia Bancaria',
            paisOrigenID: 1,
        },
    });

    const body = await response.json();
    expect(body.success).toBe(true);
    expect(body.transaccionID).toBeGreaterThan(0);
});
