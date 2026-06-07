# Despliegue a Producción (cPanel) — Guía de configuración

El despliegue es automático vía **GitHub Actions** (`.github/workflows/deploy-production.yml`):
se dispara al hacer **push a `main`**, **espera tu aprobación manual** y luego sincroniza
los archivos a cPanel por **SSH/rsync** e instala dependencias con Composer.

## Cómo funciona

```
push a main  ─►  GitHub Actions  ─►  ⏸ espera aprobación (environment "production")
                                   ─►  rsync public_html/      →  ~/public_html/
                                   ─►  rsync remesas_private/  →  ~/remesas_private/
                                   ─►  ssh: composer install --no-dev
```

- **No usa `--delete`**: nunca borra archivos que existan solo en el servidor
  (`.htaccess`, `robots.txt`, `sitemap.xml`, `.well-known/`, `cgi-bin/`, etc.).
- `config.php`, `vendor/`, `uploads/` y `sessions/` están en `.gitignore`, así que **no
  viajan en el deploy** y los de producción quedan intactos.
- Las **migraciones de BD NO se aplican solas** (ver abajo).

## Configuración única (una sola vez)

### 1) Generar un par de claves SSH para el deploy
En tu PC (o en cualquier consola):
```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f deploy_key -N ""
```
Genera `deploy_key` (privada) y `deploy_key.pub` (pública).

### 2) Autorizar la clave pública en cPanel
cPanel → **SSH Access** → **Manage SSH Keys** → **Import Key**:
- Pega el contenido de `deploy_key.pub` (como clave pública).
- Luego **Manage → Authorize** esa clave.

### 3) Cargar los secretos en GitHub
Repo en GitHub → **Settings → Secrets and variables → Actions → New repository secret**:

| Secret | Valor |
|--------|-------|
| `SSH_HOST` | host/IP del servidor cPanel (ej. `server123.hosting.com` o la IP) |
| `SSH_USER` | tu usuario de cPanel |
| `SSH_PORT` | puerto SSH (si no es 22; si es 22 puedes omitirlo) |
| `SSH_PRIVATE_KEY` | **todo** el contenido del archivo `deploy_key` (la privada) |

> Borra el archivo `deploy_key` de tu disco después de copiarlo a GitHub.

### 4) Crear el environment con aprobación manual
Repo → **Settings → Environments → New environment** → nombre **`production`**:
- Marca **Required reviewers** y agrégate a ti mismo (1 revisor).
- (Opcional) Restringe el environment a la rama `main`.

Con esto, cada deploy queda **pausado esperando que pulses "Approve"** en la pestaña Actions.

## Uso diario

1. `git push origin main`
2. Ve a la pestaña **Actions** del repo → el deploy quedará en **"Waiting"**.
3. Revisa y pulsa **Review deployments → Approve and deploy**.
4. Al terminar, los archivos están en producción y `composer install` ya corrió.

También puedes lanzarlo a mano: Actions → *Deploy a Producción* → **Run workflow**.

## Migraciones de base de datos (manual, a propósito)

Cuando un cambio incluya una migración nueva en `migrations/`, aplícala tú mismo
(por seguridad, no se ejecutan en el deploy):

```bash
# por SSH:
mysql -u TU_USUARIO_BD -p TU_BD < migrations/003_drop_unused_user_columns.sql
```
o impórtala desde **phpMyAdmin**. Las migraciones del proyecto son idempotentes
(usan `IF EXISTS` / `INFORMATION_SCHEMA`), así que reaplicarlas no rompe nada.

## Notas

- Si `composer` no está en el PATH del servidor, sube un `composer.phar` a `~/remesas_private/`
  (el workflow ya intenta `php composer.phar` como alternativa) o pide a tu hosting la ruta de Composer.
- La primera vez conviene hacer un deploy manual (`workflow_dispatch`) y revisar el log.
- Si en el futuro quieres que el deploy también elimine archivos borrados del repo,
  se puede añadir `--delete` con exclusiones, pero hay que validar bien la lista de exclusiones
  para no tocar datos de producción.
