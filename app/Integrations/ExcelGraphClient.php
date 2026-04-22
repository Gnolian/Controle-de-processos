<?php

declare(strict_types=1);

namespace App\Integrations;

use RuntimeException;

class ExcelGraphClient
{
    private string $accessToken;
    private array $settings;

    public function __construct(string $accessToken, array $settings)
    {
        $this->accessToken = $accessToken;
        $this->settings = $settings;
    }

    public function listColumns(): array
    {
        return $this->request('GET', $this->basePath() . '/columns');
    }

    public function listRows(): array
    {
        return $this->request('GET', $this->basePath() . '/rows?$top=5000');
    }

    public function addRow(array $values): array
    {
        return $this->request('POST', $this->basePath() . '/rows/add', [
            'index' => null,
            'values' => [$values],
        ]);
    }

    public function updateRow(int $rowIndex, array $values): array
    {
        return $this->request('PATCH', $this->basePath() . '/rows/' . $rowIndex, [
            'values' => [$values],
        ]);
    }

    private function basePath(): string
    {
        if (empty($this->settings['drive_id']) || empty($this->settings['item_id']) || empty($this->settings['table_name'])) {
            throw new RuntimeException('Configure drive_id, item_id e table_name da planilha.');
        }

        return '/drives/' . rawurlencode($this->settings['drive_id'])
            . '/items/' . rawurlencode($this->settings['item_id'])
            . '/workbook/tables/' . rawurlencode($this->settings['table_name']);
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensao PHP cURL precisa estar habilitada no XAMPP.');
        }

        $ch = curl_init('https://graph.microsoft.com/v1.0' . $path);
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $status >= 400) {
            throw new RuntimeException('Falha Microsoft Graph (' . $status . '): ' . ($error ?: $raw));
        }

        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }
}
