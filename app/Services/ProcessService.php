<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\SyncRepository;
use RuntimeException;

class ProcessService
{
    private ProcessRepository $processes;
    private AuditLogRepository $audit;
    private SyncRepository $sync;

    public function __construct(?ProcessRepository $processes = null, ?AuditLogRepository $audit = null, ?SyncRepository $sync = null)
    {
        $this->processes = $processes ?? new ProcessRepository();
        $this->audit = $audit ?? new AuditLogRepository();
        $this->sync = $sync ?? new SyncRepository();
    }

    public function save(array $data, array $user, ?int $id = null, bool $syncNow = true): int
    {
        $payload = $this->processes->buildPayload($data);
        $this->validate($payload);

        if ($id === null) {
            if ($this->processes->findByNumber((string) $payload['process_number'])) {
                throw new RuntimeException('Ja existe um processo com este numero.');
            }

            $id = $this->processes->create($payload, $user);
            $this->audit->recordProcessChanges($id, (int) $user['id'], [], $payload);
        } else {
            $before = $this->processes->find($id, $user);
            if (!$before) {
                throw new RuntimeException('Processo nao encontrado.');
            }

            $existing = $this->processes->findByNumber((string) $payload['process_number']);
            if ($existing && (int) $existing['id'] !== $id) {
                throw new RuntimeException('Ja existe outro processo com este numero.');
            }

            $this->processes->update($id, $payload);
            $this->audit->recordProcessChanges($id, (int) $user['id'], $before, $payload);
        }

        $this->sync->markPending($id);
        if ($syncNow) {
            (new SyncService())->syncProcess($id, $user);
        }

        return $id;
    }

    public function delete(int $id, array $user): void
    {
        $process = $this->processes->find($id, $user);
        if (!$process) {
            throw new RuntimeException('Processo nao encontrado.');
        }

        $this->audit->record($id, (int) $user['id'], 'processo', $process['process_number'], 'excluido', 'sistema interno');
        $this->processes->delete($id);
    }

    private function validate(array $payload): void
    {
        if ($payload['process_number'] === '') {
            throw new RuntimeException('Informe o numero do processo.');
        }

        foreach (['response_status', 'andrea_review_status', 'signed_status', 'sent_gab_status'] as $field) {
            if (!in_array($payload[$field], \config('dropdowns.workflow'), true)) {
                throw new RuntimeException('Valor invalido em lista controlada.');
            }
        }

        if (!in_array($payload['status'], \config('dropdowns.status'), true)) {
            throw new RuntimeException('Status invalido.');
        }
    }
}
