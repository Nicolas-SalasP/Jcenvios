<?php
namespace App\Controllers;

use App\Repositories\NormasRepository;
use Exception;

class NormasController extends BaseController
{
    private NormasRepository $normasRepo;

    /**
     * Tags HTML permitidos en el contenido de Normas. Cualquier otro tag
     * (script, style, iframe, form, etc.) es eliminado por completo junto
     * con su contenido de atributos no permitidos.
     */
    private const TAGS_PERMITIDOS = '<p><br><strong><b><em><i><ul><ol><li><h1><h2><h3><h4><a><blockquote><span>';

    public function __construct(NormasRepository $normasRepo)
    {
        $this->normasRepo = $normasRepo;
    }

    // --- PÚBLICO (sin login) ---

    public function getNormasPublicas(): void
    {
        $row = $this->normasRepo->get();

        $this->sendJsonResponse([
            'success' => true,
            'contenido' => $row['Contenido'] ?? '',
            'fechaActualizacion' => $row['FechaActualizacion'] ?? null,
        ]);
    }

    // --- ADMIN ---

    public function getNormasAdmin(): void
    {
        $this->ensureAdmin();
        $row = $this->normasRepo->get();

        $this->sendJsonResponse([
            'success' => true,
            'contenido' => $row['Contenido'] ?? '',
            'fechaActualizacion' => $row['FechaActualizacion'] ?? null,
            'actualizadoPor' => $row['ActualizadoPor'] ?? null,
        ]);
    }

    public function saveNormas(): void
    {
        try {
            $this->ensureAdmin();
            $adminId = (int)($_SESSION['user_id'] ?? 0);

            $contenidoCrudo = (string)($_POST['contenido'] ?? '');
            if (trim($contenidoCrudo) === '') {
                throw new Exception('El contenido de las Normas no puede estar vacío.', 400);
            }
            if (mb_strlen($contenidoCrudo) > 200000) {
                throw new Exception('El contenido de las Normas es demasiado extenso.', 400);
            }

            $contenidoSanitizado = $this->sanitizarHtml($contenidoCrudo);

            $this->normasRepo->save($contenidoSanitizado, $adminId);

            $this->sendJsonResponse(['success' => true, 'message' => 'Normas actualizadas correctamente.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $code);
        }
    }

    // --- Helpers ---

    /**
     * Sanitiza el HTML entregado por el Admin: elimina cualquier tag fuera
     * de la whitelist con strip_tags() y luego valida/limpia los atributos
     * href de los <a> restantes para evitar esquemas peligrosos (javascript:, etc.).
     */
    private function sanitizarHtml(string $html): string
    {
        $limpio = strip_tags($html, self::TAGS_PERMITIDOS);

        // Limpia atributos de los <a>: sólo permite href http(s)/mailto y agrega
        // rel/target seguros. Elimina cualquier otro atributo (onclick, style, etc.).
        $limpio = preg_replace_callback('/<a\s+[^>]*>/i', function (array $m) {
            if (preg_match('/href\s*=\s*"([^"]*)"|href\s*=\s*\'([^\']*)\'/i', $m[0], $hrefMatch)) {
                $href = $hrefMatch[1] !== '' ? $hrefMatch[1] : ($hrefMatch[2] ?? '');
            } else {
                $href = '';
            }

            $href = trim($href);
            $esquemaValido = (bool)preg_match('~^(https?://|mailto:|/|#)~i', $href);
            if ($href === '' || !$esquemaValido) {
                return '<a>';
            }

            $hrefSeguro = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
            return '<a href="' . $hrefSeguro . '" target="_blank" rel="noopener noreferrer">';
        }, $limpio);

        return $limpio;
    }
}
