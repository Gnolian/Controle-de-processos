<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class StudyPdfService
{
    public const MAX_SIZE = 30 * 1024 * 1024;

    public function store(array $upload): ?array
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($error));
        }

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $originalName = trim(basename(str_replace('\\', '/', (string) ($upload['name'] ?? 'estudo.pdf'))));
        $size = (int) ($upload['size'] ?? 0);

        if ($size <= 0 || $size > self::MAX_SIZE) {
            throw new RuntimeException('O PDF deve ter no máximo 30 MB.');
        }
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new RuntimeException('Selecione um arquivo no formato PDF.');
        }
        if (!is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('Não foi possível validar o arquivo enviado.');
        }

        $handle = fopen($temporaryPath, 'rb');
        $signature = $handle !== false ? (string) fread($handle, 5) : '';
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($signature !== '%PDF-') {
            throw new RuntimeException('O arquivo enviado não possui uma estrutura PDF válida.');
        }

        $directory = $this->storageDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar a pasta de PDFs dos estudos.');
        }

        $storedName = bin2hex(random_bytes(24)) . '.pdf';
        if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $storedName)) {
            throw new RuntimeException('Não foi possível salvar o PDF do estudo.');
        }

        return [
            'file' => $storedName,
            'name' => mb_substr($originalName !== '' ? $originalName : 'estudo.pdf', 0, 255, 'UTF-8'),
            'size' => $size,
        ];
    }

    public function path(?string $storedName): ?string
    {
        $storedName = (string) $storedName;
        if (!preg_match('/^[a-f0-9]{48}\.pdf$/', $storedName)) {
            return null;
        }

        $path = $this->storageDirectory() . DIRECTORY_SEPARATOR . $storedName;

        return is_file($path) ? $path : null;
    }

    public function delete(?string $storedName): void
    {
        $path = $this->path($storedName);
        if ($path !== null) {
            @unlink($path);
        }
    }

    private function storageDirectory(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'studies';
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O PDF ultrapassa o limite de upload configurado no servidor.',
            UPLOAD_ERR_PARTIAL => 'O envio do PDF foi interrompido. Tente novamente.',
            default => 'Não foi possível receber o PDF. Tente novamente.',
        };
    }
}
