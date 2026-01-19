<?php
namespace App\Services;

use Exception;

class FileHandlerService
{
    private string $baseUploadPath;
    private string $publicTempUrlBase;
    private string $publicTempDir;

    public function __construct()
    {
        $this->baseUploadPath = realpath(__DIR__ . '/../../../uploads');
        if ($this->baseUploadPath === false || !is_dir($this->baseUploadPath)) {
            @mkdir(__DIR__ . '/../../../uploads', 0755, true);
            $this->baseUploadPath = realpath(__DIR__ . '/../../../uploads');
            if ($this->baseUploadPath === false) {
                error_log("Error crítico: El directorio base de uploads no existe y no se pudo crear: " . __DIR__ . '/../../../uploads');
                throw new Exception("Error interno del servidor [FH01].", 500);
            }
        }

        $this->publicTempDir = realpath(__DIR__ . '/../../../public_html/temp_orders');
        if ($this->publicTempDir === false || !is_dir($this->publicTempDir)) {
            if (!@mkdir(__DIR__ . '/../../../public_html/temp_orders', 0755, true)) {
                error_log("Error crítico: No se pudo crear el directorio público temporal: " . __DIR__ . '/../../../public_html/temp_orders');
                throw new Exception("Error interno del servidor [FH02].", 500);
            }
            $this->publicTempDir = realpath(__DIR__ . '/../../../public_html/temp_orders');
        }
        $this->publicTempUrlBase = rtrim(BASE_URL, '/') . '/temp_orders/';

        $this->ensureDirectoryIsWritable($this->baseUploadPath . DIRECTORY_SEPARATOR . 'receipts');
        $this->ensureDirectoryIsWritable($this->baseUploadPath . DIRECTORY_SEPARATOR . 'proof_of_sending');
        $this->ensureDirectoryIsWritable($this->baseUploadPath . DIRECTORY_SEPARATOR . 'verifications');
        $this->ensureDirectoryIsWritable($this->baseUploadPath . DIRECTORY_SEPARATOR . 'profile_pics');
        $this->ensureDirectoryIsWritable($this->publicTempDir);
    }

    private function ensureDirectoryIsWritable(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                error_log("Error al crear directorio: {$dir}");
                throw new Exception("Error interno del servidor [FH03].", 500);
            }
        }
        if (!is_writable($dir)) {
            error_log("Error de permisos en directorio: {$dir}");
            throw new Exception("Error interno del servidor [FH04].", 500);
        }
    }

    public function savePdfTemporarily(string $pdfContent, int $transactionId): string
    {
        $filename = 'orden_' . $transactionId . '.pdf';
        $filePath = $this->publicTempDir . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($filePath, $pdfContent) === false) {
            error_log("No se pudo guardar el archivo PDF temporal en: " . $filePath);
            throw new Exception("No se pudo generar el archivo de la orden.", 500);
        }
        return $this->publicTempUrlBase . $filename . '?t=' . time();
    }

    public function saveVerificationFile(array $fileData, int $userId, string $prefix): string
    {
        $targetDir = $this->baseUploadPath . DIRECTORY_SEPARATOR . 'verifications';
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 10 * 1024 * 1024;
        
        $savedFilename = $this->handleUpload($fileData, $targetDir, $prefix . '_' . $userId, $allowedTypes, $maxSize, 70);

        return 'verifications' . DIRECTORY_SEPARATOR . $savedFilename;
    }

    public function saveUserProfileImage(array $fileData, int $userId): string
    {
        return $this->saveProfilePicture($fileData, $userId);
    }

    public function saveProfilePicture(array $fileData, int $userId): string
    {
        $targetDir = $this->baseUploadPath . DIRECTORY_SEPARATOR . 'profile_pics';
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; 
        $filenamePrefix = 'user_profile_' . $userId;
        $savedFilename = $this->handleUpload($fileData, $targetDir, $filenamePrefix, $allowedTypes, $maxSize, 70);

        return 'profile_pics' . DIRECTORY_SEPARATOR . $savedFilename;
    }

    public function saveReceiptFile(array $fileData, int $transactionId): string
    {
        $targetDir = $this->baseUploadPath . DIRECTORY_SEPARATOR . 'receipts';
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'image/webp'];
        $maxSize = 10 * 1024 * 1024;
        $filenamePrefix = 'tx_recibo_' . $transactionId;
        $savedFilename = $this->handleUpload($fileData, $targetDir, $filenamePrefix, $allowedTypes, $maxSize, 70);

        return 'receipts' . DIRECTORY_SEPARATOR . $savedFilename;
    }

    public function saveAdminProofFile(array $fileData, int $transactionId): string
    {
        $targetDir = $this->baseUploadPath . DIRECTORY_SEPARATOR . 'proof_of_sending';
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize = 10 * 1024 * 1024;
        $filenamePrefix = 'tx_envio_' . $transactionId;
        $savedFilename = $this->handleUpload($fileData, $targetDir, $filenamePrefix, $allowedTypes, $maxSize, 75);

        return 'proof_of_sending' . DIRECTORY_SEPARATOR . $savedFilename;
    }
    public function saveVerificationDoc(array $fileData, int $userId, string $side): string
    {
        return $this->saveVerificationFile($fileData, $userId, 'doc_' . $side);
    }

    public function getAbsolutePath(string $relativePath): string
    {
        $cleanPath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        $prefixToRemove = 'uploads' . DIRECTORY_SEPARATOR;

        if (strpos($cleanPath, $prefixToRemove) === 0) {
            $cleanPath = substr($cleanPath, strlen($prefixToRemove));
        }

        return $this->baseUploadPath . DIRECTORY_SEPARATOR . $cleanPath;
    }

    private function handleUpload(array $fileData, string $targetDirectory, string $filenamePrefix, array $allowedMimeTypes, int $maxFileSize, ?int $quality = null): string
    {
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($fileData['error']), 400);
        }
        if ($fileData['size'] > $maxFileSize) {
            throw new Exception("El archivo es demasiado grande (máx " . ($maxFileSize / 1024 / 1024) . "MB).", 400);
        }
        if (empty($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
            throw new Exception("Archivo subido inválido.", 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($finfo, $fileData['tmp_name']);
        finfo_close($finfo);

        if (!in_array($fileType, $allowedMimeTypes)) {
            throw new Exception("Formato no permitido. Aceptados: " . implode(', ', $allowedMimeTypes), 400);
        }

        $isImage = in_array($fileType, ['image/jpeg', 'image/png', 'image/webp']);
        if ($isImage && $quality !== null) {
            $extension = 'jpg';
        } else {
            $extensionMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/pdf' => 'pdf',
                'image/webp' => 'webp'
            ];
            $extension = $extensionMap[$fileType] ?? 'tmp';
        }

        $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filenamePrefix);
        $newFilename = $safeFilename . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $targetDirectory . DIRECTORY_SEPARATOR . $newFilename;

        // --- BLOQUE DE COMPRESIÓN ---
        if ($isImage && $quality !== null) {
            try {
                $sourceImage = null;
                switch ($fileType) {
                    case 'image/jpeg': $sourceImage = imagecreatefromjpeg($fileData['tmp_name']); break;
                    case 'image/png':  $sourceImage = imagecreatefrompng($fileData['tmp_name']); break;
                    case 'image/webp': $sourceImage = imagecreatefromwebp($fileData['tmp_name']); break;
                }

                if ($sourceImage) {
                    $width = imagesx($sourceImage);
                    $height = imagesy($sourceImage);
                    $maxWidth = 2000;
                    
                    if ($width > $maxWidth) {
                        $newWidth = $maxWidth;
                        $newHeight = floor($height * ($maxWidth / $width));
                        $tmpImage = imagecreatetruecolor($newWidth, $newHeight);
                        imagecopyresampled($tmpImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                        imagedestroy($sourceImage);
                        $sourceImage = $tmpImage;
                    }
                    imageinterlace($sourceImage, true);
                    if (imagejpeg($sourceImage, $destination, $quality)) {
                        imagedestroy($sourceImage);
                        return $newFilename;
                    }
                    imagedestroy($sourceImage);
                }
            } catch (Exception $e) {
                error_log("Error al comprimir imagen, intentando guardado normal: " . $e->getMessage());
            }
        }

        if (!move_uploaded_file($fileData['tmp_name'], $destination)) {
            error_log("Error al mover archivo subido: {$fileData['tmp_name']} a {$destination}");
            throw new Exception("No se pudo guardar el archivo. Inténtalo de nuevo.", 500);
        }

        return $newFilename;
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo subido excede el tamaño máximo permitido.';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo se subió solo parcialmente.';
            case UPLOAD_ERR_NO_FILE:
                return 'No se subió ningún archivo.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta la carpeta temporal del servidor.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Error al escribir el archivo en el disco.';
            case UPLOAD_ERR_EXTENSION:
                return 'Una extensión de PHP detuvo la subida del archivo.';
            default:
                return 'Error desconocido durante la subida del archivo.';
        }
    }
    
    public function deleteOrderPdf(int $transactionId): void
    {
        $filename = 'orden_' . $transactionId . '.pdf';
        $filePath = $this->publicTempDir . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($filePath)) {
            if (!@unlink($filePath)) {
                error_log("No se pudo eliminar el PDF temporal: " . $filePath);
            }
        }
    }
}