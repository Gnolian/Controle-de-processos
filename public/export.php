<?php

use App\Repositories\ProcessRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'owner' => trim((string) ($_GET['owner'] ?? '')),
    'requesting_agency' => trim((string) ($_GET['requesting_agency'] ?? '')),
    'response_status' => trim((string) ($_GET['response_status'] ?? '')),
    'andrea_review_status' => trim((string) ($_GET['andrea_review_status'] ?? '')),
    'deadline' => trim((string) ($_GET['deadline'] ?? '')),
];
$repo = new ProcessRepository();
$rows = $repo->allForExport($filters, $user);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="controle-processos-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ProcessRepository::EXPORT_HEADERS, ';');
foreach ($rows as $row) {
    $line = [];
    foreach (ProcessRepository::COLUMNS as $column) {
        $line[] = $row[$column] ?? '';
    }
    fputcsv($out, $line, ';');
}
fclose($out);

