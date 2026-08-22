# Checklist de Seguridad y Cumplimiento

## Vulnerabilidades Detectadas & Acciones
* [CRÍTICO] **Secretos en Código:** Se detectaron números de teléfono y correos en `NotificationService.php`.
    * *Acción:* Mover a variables de entorno inmediatamente.
* [MEDIO] **Configuración PHP:** Asegurar que `display_errors` esté en `Off` en producción (controlado en `ErrorHandler.php`, pero verificar `php.ini`).
* [BAJO] **Rate Limiting:** No hay protección contra fuerza bruta en el login más allá de un bloqueo básico en DB.
    * *Acción:* Implementar limitación por IP en `AuthController`.
* [INFO] **`AUTO_CONFIRM_EMAIL`:** flag en `config.php` (default `false` en `config.php.example`) usado por `EmailReconciliationService::conciliarOrden()`. Si se activa en `true`, los pagos se confirman automáticamente por email sin revisión humana de un admin.
    * *Acción:* Mantener en `false` en producción salvo decisión explícita del negocio. Si se activa, documentar aquí la fecha y el motivo, y reforzar el monitoreo de conciliaciones automáticas.

## Recomendaciones
1.  **Headers de Seguridad:**
    * El archivo `init.php` ya implementa CSP y HSTS. Verificar que `SESSION_DOMAIN` esté configurado correctamente para evitar robo de cookies.
2.  **Validación de Archivos:**
    * `FileHandlerService` usa `finfo_file` para validar MIME types. Esto es correcto. Asegurar que no se permitan extensiones ejecutables (.php, .phtml) en la carpeta de uploads a nivel de servidor web (Apache/Nginx config).
3.  **Logs:**
    * Asegurar que la tabla `logs` se purgue periódicamente o se rote, ya que puede crecer indefinidamente con datos sensibles en la columna `Detalles`.