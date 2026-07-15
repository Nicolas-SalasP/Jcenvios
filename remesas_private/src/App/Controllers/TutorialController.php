<?php
namespace App\Controllers;

use App\Repositories\TutorialRepository;
use App\Services\FileHandlerService;
use Exception;

class TutorialController extends BaseController
{
    private TutorialRepository $tutorialRepo;
    private FileHandlerService $fileHandler;

    private const ALLOWED_URL_HOSTS = [
        'youtube.com', 'www.youtube.com', 'youtu.be', 'm.youtube.com',
        'player.vimeo.com', 'vimeo.com', 'www.vimeo.com',
    ];

    public function __construct(TutorialRepository $tutorialRepo, FileHandlerService $fileHandler)
    {
        $this->tutorialRepo = $tutorialRepo;
        $this->fileHandler = $fileHandler;
    }

    // --- CLIENTE ---

    public function getTutorialesActivos(): void
    {
        $this->ensureLoggedIn();
        $rows = $this->tutorialRepo->getActiveForClient();
        $this->sendJsonResponse(['success' => true, 'tutoriales' => $rows]);
    }

    // --- ADMIN ---

    public function getTutorialesAdmin(): void
    {
        $this->ensureAdmin();
        $rows = $this->tutorialRepo->getAllForAdmin();
        $this->sendJsonResponse(['success' => true, 'tutoriales' => $rows]);
    }

    public function saveTutorial(): void
    {
        $adminId = 0;
        try {
            $this->ensureAdmin();
            $adminId = (int)($_SESSION['user_id'] ?? 0);

            $id = isset($_POST['tutorialId']) ? (int)$_POST['tutorialId'] : 0;
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $tipoFuente = ($_POST['tipoFuente'] ?? '') === 'archivo' ? 'archivo' : 'url';
            $urlExterna = trim((string)($_POST['urlExterna'] ?? ''));

            if ($titulo === '') {
                throw new Exception('El título es obligatorio.', 400);
            }

            $existing = $id > 0 ? $this->tutorialRepo->find($id) : null;
            if ($id > 0 && !$existing) {
                throw new Exception('Tutorial no encontrado.', 404);
            }

            $rutaArchivo = $existing['RutaArchivo'] ?? null;

            if ($tipoFuente === 'archivo') {
                if (!empty($_FILES['video']) && $_FILES['video']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newRuta = $this->fileHandler->saveTutorialVideo($_FILES['video'], $adminId);
                    // Si reemplaza un archivo previo, borrar el anterior.
                    if (!empty($existing['RutaArchivo']) && $existing['TipoFuente'] === 'archivo') {
                        $this->fileHandler->deleteTutorialVideo($existing['RutaArchivo']);
                    }
                    $rutaArchivo = $newRuta;
                } elseif (empty($rutaArchivo)) {
                    throw new Exception('Debes subir un archivo de video o elegir la opción de URL externa.', 400);
                }
                $urlExterna = null;
            } else {
                if ($urlExterna === '' || !$this->isAllowedVideoUrl($urlExterna)) {
                    throw new Exception('Ingresa una URL de video válida (YouTube o Vimeo).', 400);
                }
                // Si cambia de archivo a URL, se conserva el archivo físico solo si se desea; lo eliminamos
                // porque ya no estará referenciado por este registro.
                if (!empty($existing['RutaArchivo']) && $existing['TipoFuente'] === 'archivo') {
                    $this->fileHandler->deleteTutorialVideo($existing['RutaArchivo']);
                }
                $rutaArchivo = null;
            }

            if ($id > 0) {
                $orden = $existing['Orden'] ?? 0;
                $this->tutorialRepo->update($id, $titulo, $descripcion, $tipoFuente, $rutaArchivo, $urlExterna, (int)$orden);
                $this->sendJsonResponse(['success' => true, 'message' => 'Tutorial actualizado correctamente.']);
            } else {
                $orden = $this->tutorialRepo->getMaxOrden() + 1;
                $newId = $this->tutorialRepo->create($titulo, $descripcion, $tipoFuente, $rutaArchivo, $urlExterna, $orden, $adminId);
                $this->sendJsonResponse(['success' => true, 'message' => 'Tutorial creado correctamente.', 'tutorialId' => $newId]);
            }
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], $code);
        }
    }

    public function deleteTutorial(): void
    {
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            $this->sendJsonResponse(['success' => false, 'error' => 'ID inválido.'], 400);
            return;
        }

        $tutorial = $this->tutorialRepo->find($id);
        if (!$tutorial) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Tutorial no encontrado.'], 404);
            return;
        }

        if ($this->tutorialRepo->delete($id)) {
            if (!empty($tutorial['RutaArchivo']) && $tutorial['TipoFuente'] === 'archivo') {
                $this->fileHandler->deleteTutorialVideo($tutorial['RutaArchivo']);
            }
            $this->sendJsonResponse(['success' => true, 'message' => 'Tutorial eliminado.']);
        } else {
            $this->sendJsonResponse(['success' => false, 'error' => 'No se pudo eliminar el tutorial.'], 500);
        }
    }

    public function toggleTutorialStatus(): void
    {
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            $this->sendJsonResponse(['success' => false, 'error' => 'ID inválido.'], 400);
            return;
        }
        $ok = $this->tutorialRepo->toggleActivo($id);
        $this->sendJsonResponse(['success' => $ok]);
    }

    public function reorderTutoriales(): void
    {
        $this->ensureAdmin();
        $data = $this->getJsonInput();
        $order = $data['order'] ?? [];
        if (!is_array($order) || empty($order)) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Orden inválido.'], 400);
            return;
        }
        $posicion = 1;
        foreach ($order as $id) {
            $this->tutorialRepo->updateOrden((int)$id, $posicion);
            $posicion++;
        }
        $this->sendJsonResponse(['success' => true]);
    }

    // --- Helpers ---

    private function isAllowedVideoUrl(string $url): bool
    {
        $filtered = filter_var($url, FILTER_VALIDATE_URL);
        if ($filtered === false) {
            return false;
        }
        $scheme = parse_url($filtered, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower((string)parse_url($filtered, PHP_URL_HOST));
        return in_array($host, self::ALLOWED_URL_HOSTS, true);
    }
}
