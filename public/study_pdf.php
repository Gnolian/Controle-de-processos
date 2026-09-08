<?php

use App\Repositories\StudyRepository;
use App\Services\StudyPdfService;

require __DIR__ . '/../app/bootstrap.php';

require_study_access();

$id = max(0, (int) ($_GET['id'] ?? 0));
$study = (new StudyRepository())->find($id);
$path = $study ? (new StudyPdfService())->path($study['pdf_file'] ?? null) : null;

if (!$study || $path === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PDF não encontrado.';
    exit;
}

$fileName = trim((string) ($study['pdf_original_name'] ?? '')) ?: 'estudo.pdf';
$asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fileName) ?: 'estudo.pdf';

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;
