<?php

declare(strict_types=1);

return [
    'app_name' => 'Controle de Processos',
    'base_path' => '/controle_processos/public',
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
];

