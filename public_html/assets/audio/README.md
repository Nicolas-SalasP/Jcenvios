# Audio de Notificaciones

## Por qué este README existe

`header.php` referencia `/assets/audio/notification.mp3` para el sonido de
notificaciones del panel de admin/operador. La carpeta no existía en el repo
original, por eso el `<audio>` quedaba sin fuente y nunca sonaba aunque el
botón estuviera activado y el JS estuviera bien.

## Qué hacer

Subí un archivo llamado **`notification.mp3`** acá. Sugerencias gratis y libres
de licencia:

- Mixkit: https://mixkit.co/free-sound-effects/notification/
- Pixabay: https://pixabay.com/sound-effects/search/notification/

Recomendaciones técnicas:

- Duración: 1–2 segundos (se reproduce cada vez que llega una orden nueva,
  algo más largo molesta).
- Formato: MP3 a 128 kbps mono (peso < 30 KB).
- Volumen: normalizado, no muy fuerte.

## Probarlo

1. Abrí el panel de admin con sesión iniciada.
2. Tocá la campanita arriba a la derecha — debería sonar al activarla
   (es la confirmación de que el archivo existe y el navegador puede tocarlo).
3. Si el sonido sigue sin venir, abrí DevTools → Network → buscá
   `notification.mp3` y verificá que devuelva 200, no 404.
