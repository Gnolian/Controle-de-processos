<?php

declare(strict_types=1);

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/controle/index.php'));
$detectedBasePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

if ($detectedBasePath === '/' || $detectedBasePath === '.') {
    $detectedBasePath = '';
}

$configuredBasePath = getenv('APP_BASE_PATH');
$basePath = is_string($configuredBasePath) && trim($configuredBasePath) !== ''
    ? '/' . trim($configuredBasePath, '/')
    : $detectedBasePath;

return [
    'app_name' => 'Controle de Processos',
    'base_path' => $basePath,
    'security' => [
        'default_user_password' => (string) (getenv('DEFAULT_USER_PASSWORD') ?: 'admin123'),
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'controle_processos',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'dropdowns' => [
        'workflow' => ['A iniciar', 'Em andamento', 'Concluido', 'N/A'],
        'status' => ['Aberto', 'Arquivado (concluido)', 'Arquivar'],
        'roles' => ['servidor', 'coordenador', 'admin'],
    ],
    'excel_columns' => [
        'Numero do Processo' => 'process_number',
        'Número do Processo' => 'process_number',
        'DATA DA ATUALIZACAO' => 'updated_at',
        'DATA DA ATUALIZAÇÃO' => 'updated_at',
        'Responsavel pela Resposta' => 'response_owner',
        'Responsável pela Resposta' => 'response_owner',
        'Prazo (em dias)' => 'deadline_days',
        'Descricao Geral' => 'general_description',
        'Descrição Geral' => 'general_description',
        'Descricao Detalhada' => 'detailed_description',
        'Descrição Detalhada' => 'detailed_description',
        'Comentarios/anotacoes' => 'notes',
        'Comentários/anotações' => 'notes',
        'Orgao Solicitante' => 'requesting_agency',
        'Órgão Solicitante' => 'requesting_agency',
        'Data de assinatura (Oficio GAB)' => 'gab_signature_date',
        'Data de assinatura (Ofício GAB)' => 'gab_signature_date',
        'Prazo Interno (OFICIO GAB/SNBA)' => 'internal_deadline_gab',
        'Prazo Interno (OFÍCIO GAB/SNBA)' => 'internal_deadline_gab',
        'Prazo Interno AJUSTADO' => 'adjusted_internal_deadline',
        'Prazo Externo/MDS' => 'external_deadline_mds',
        'Responsavel pela Revisao' => 'review_owner',
        'Responsável pela Revisão' => 'review_owner',
        'Resposta' => 'response_status',
        'Revisao Andrea' => 'andrea_review_status',
        'Revisão Andrea' => 'andrea_review_status',
        'Assinado' => 'signed_status',
        'Enviado Gab' => 'sent_gab_status',
        'Data envio GAB' => 'gab_sent_date',
        'STATUS' => 'status',
        'Bloco interno' => 'internal_block',
    ],
];
