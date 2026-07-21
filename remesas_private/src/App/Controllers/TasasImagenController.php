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

        $rows = $this->tasasImagenRepo->getAllByTipo($tipoFuente);
        $imagenes = array_map(function (array $row) {
            return [
                'id' => (int) $row['Id'],
                'titulo' => $row['Titulo'],
                'descripcion' => $row['Descripcion'],
                'url' => $this->buildStreamUrl((int) $row['Id'], $row['FechaActualizacion']),
                'fechaActualizacion' => $row['FechaActualizacion'],
            ];
        }, $rows);

        $this->sendJsonResponse(['success' => true, 'disponible' => count($imagenes) > 0, 'imagenes' => $imagenes]);
    }

    // --- ADMIN ---

    public function getTasasImagenAdmin(): void
    {
        $this->ensureAdmin();
        $rows = $this->tasasImagenRepo->getAll();
        $data = array_map(function (array $row) {
            return [
                'id' => (int) $row['Id'],
                'tipoFuente' => $row['TipoFuente'],
                'titulo' => $row['Titulo'],
                'descripcion' => $row['Descripcion'],
                'url' => $this->buildStreamUrl((int) $row['Id'], $row['FechaActualizacion']),
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

            $titulo = trim((string)($_POST['titulo'] ?? '')) ?: null;
            $descripcion = trim((string)($_POST['descripcion'] ?? '')) ?: null;

            $nuevaRuta = $this->fileHandler->saveTasaImagen($_FILES['imagen'], $tipoFuente, $adminId);
            $this->tasasImagenRepo->insertImagen($tipoFuente, $nuevaRuta, $titulo, $descripcion, $adminId);

            $this->sendJsonResponse(['success' => true, 'message' => 'Imagen de tasa subida correctamente.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $code);
        }
    }

    public function deleteTasaImagen(): void
    {
        try {
            $this->ensureAdmin();

            $id = (int)($_POST['id'] ?? 0);
            $row = $id > 0 ? $this->tasasImagenRepo->findById($id) : null;
            if (!$row) {
                throw new Exception('Imagen no encontrada.', 404);
            }

            if (!empty($row['RutaImagen'])) {
                $this->fileHandler->deleteTasaImagen($row['RutaImagen']);
            }
            $this->tasasImagenRepo->deleteById($id);

            $this->sendJsonResponse(['success' => true, 'message' => 'Imagen eliminada correctamente.']);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $code);
        }
    }

    // --- Helpers ---

    private function buildStreamUrl(int $id, ?string $fechaActualizacion): string
    {
        $version = $fechaActualizacion ? strtotime($fechaActualizacion) : time();
        return rtrim(BASE_URL, '/') . '/tasas_imagen_stream.php?id=' . $id . '&v=' . $version;
    }
}
