<?php
namespace App\Controllers;

use App\Repositories\TasasImagenRepository;
use App\Services\FileHandlerService;
use Exception;

class TasasImagenController extends BaseController
{
    private TasasImagenRepository $tasasImagenRepo;
    private FileHandlerService $fileHandler;

    public function __construct(TasasImagenRepository $tasasImagenRepo, FileHandlerService $fileHandler)
    {
        $this->tasasImagenRepo = $tasasImagenRepo;
        $this->fileHandler = $fileHandler;
    }

    // --- PÚBLICO (sin login) ---

    public function getTasaImagenPublica(): void
    {
        $tipoFuente = strtolower(trim((string)($_GET['tipo'] ?? '')));
        if (!$this->tasasImagenRepo->isTipoValido($tipoFuente)) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Tipo de fuente inválido.'], 400);
            return;
        }

        $row = $this->tasasImagenRepo->findByTipo($tipoFuente);
        if (!$row || empty($row['RutaImagen'])) {
            $this->sendJsonResponse(['success' => true, 'disponible' => false, 'url' => null, 'fechaActualizacion' => null]);
            return;
        }

        $this->sendJsonResponse([
            'success' => true,
            'disponible' => true,
            'url' => $this->buildStreamUrl($tipoFuente, $row['FechaActualizacion']),
            'fechaActualizacion' => $row['FechaActualizacion'],
        ]);
    }

    // --- ADMIN ---

    public function getTasasImagenAdmin(): void
    {
        $this->ensureAdmin();
        $rows = $this->tasasImagenRepo->getAll();
        $data = array_map(function (array $row) {
            $tipoFuente = $row['TipoFuente'];
            return [
                'tipoFuente' => $tipoFuente,
                'rutaImagen' => $row['RutaImagen'],
                'url' => !empty($row['RutaImagen']) ? $this->buildStreamUrl($tipoFuente, $row['FechaActualizacion']) : null,
                'fechaActualizacion' => $row['FechaActualizacion'],
                'actualizadoPor' => $row['ActualizadoPor'],
            ];
        }, $rows);

        $this->sendJsonResponse(['success' => true, 'tasasImagen' => $data]);
    }

    public function saveTasaImagen(): void
    {
        try {
            $this->ensureAdmin();
            $adminId = (int)($_SESSION['user_id'] ?? 0);

            $tipoFuente = strtolower(trim((string)($_POST['tipoFuente'] ?? '')));
            if (!$this->tasasImagenRepo->isTipoValido($tipoFuente)) {
                throw new Exception('Tipo de fuente inválido. Debe ser "whatsapp" o "web".', 400);
            }

            if (empty($_FILES['imagen']) || $_FILES['imagen']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new Exception('Debes seleccionar una imagen para subir.', 400);
            }

            $existing = $this->tasasImagenRepo->findByTipo($tipoFuente);
            $nuevaRuta = $this->fileHandler->saveTasaImagen($_FILES['imagen'], $tipoFuente, $adminId);

            if (!empty($existing['RutaImagen'])) {
                $this->fileHandler->deleteTasaImagen($existing['RutaImagen']);
            }

            $this->tasasImagenRepo->upsertImagen($tipoFuente, $nuevaRuta, $adminId);

            $this->sendJsonResponse(['success' => true, 'message' => 'Imagen de tasa actualizada correctamente.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $code);
        }
    }

    // --- Helpers ---

    private function buildStreamUrl(string $tipoFuente, ?string $fechaActualizacion): string
    {
        $version = $fechaActualizacion ? strtotime($fechaActualizacion) : time();
        return rtrim(BASE_URL, '/') . '/tasas_imagen_stream.php?tipo=' . urlencode($tipoFuente) . '&v=' . $version;
    }
}
