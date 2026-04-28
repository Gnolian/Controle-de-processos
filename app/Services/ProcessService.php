<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\ProcessRepository;
use RuntimeException;

class ProcessService
{
    private ProcessRepository $processes;
    private AuditLogRepository $audit;

    public function __construct(?ProcessRepository $processes = null, ?AuditLogRepository $audit = null)
    {
        $this->processes = $processes ?? new ProcessRepository();
        $this->audit = $audit ?? new AuditLogRepository();
    }

    public function save(array $data, array $user, ?int $id = null): int
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
                throw new RuntimeException('Processo não encontrado.');
            }

            $existing = $this->processes->findByNumber((string) $payload['process_number']);
            if ($existing && (int) $existing['id'] !== $id) {
                throw new RuntimeException('Ja existe outro processo com este numero.');
            }

            $this->processes->update($id, $payload);
            $this->audit->recordProcessChanges($id, (int) $user['id'], $before, $payload);
        }

        return $id;
    }

    public function delete(int $id, array $user): void
    {
        $process = $this->processes->find($id, $user);
        if (!$process) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $this->audit->record($id, (int) $user['id'], 'processo', $process['process_number'], 'excluido', 'sistema interno');
        $this->processes->delete($id);
    }

    private function validate(array $payload): void
    {
        if ($payload['process_number'] === '') {
            throw new RuntimeException('Informe o numero do processo.');
        }

        foreach (['response_owner', 'requesting_agency', 'general_description', 'review_owner'] as $field) {
            if (($payload[$field] ?? '') === '') {
                throw new RuntimeException('Preencha todos os campos obrigatorios.');
            }
        }

        if ($payload['deadline_days'] === null) {
            throw new RuntimeException('Informe o prazo em dias.');
        }

        if ($payload['deadline_type'] === 'data') {
            foreach (['gab_signature_date', 'internal_deadline_gab', 'adjusted_internal_deadline', 'external_deadline_mds', 'gab_sent_date'] as $field) {
                if (($payload[$field] ?? null) === null) {
                    throw new RuntimeException('Preencha todos os campos de prazos e revisão ou selecione Tempo Hábil.');
                }
            }
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
