<?php

declare(strict_types=1);

namespace App\Repositories;

class IntegrationSettingsRepository
{
    public function all(): array
    {
        $rows = \db()->query('SELECT setting_key, setting_value FROM integration_settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = \db()->prepare('SELECT setting_value FROM integration_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : (string) $value;
    }

    public function save(array $settings): void
    {
        $stmt = \db()->prepare('INSERT INTO integration_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value === '' ? null : $value]);
        }
    }

    public function configured(): bool
    {
        $settings = $this->all();
        foreach (['tenant_id', 'client_id', 'client_secret', 'drive_id', 'item_id', 'table_name', 'refresh_token'] as $key) {
            if (empty($settings[$key])) {
                return false;
            }
        }

        return true;
    }
}

