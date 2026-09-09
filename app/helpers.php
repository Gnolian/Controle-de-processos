<?php

declare(strict_types=1);

use App\Repositories\UserRepository;

function config(?string $key = null, mixed $default = null): mixed
{
    global $config;

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = config('database');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $time = strtotime($date);
    return $time ? date('d/m/Y', $time) : $date;
}

function url(string $path = ''): string
{
    return rtrim(config('base_path'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return (new UserRepository())->findActive((int) $_SESSION['user_id']);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }

    return $user;
}

function can_manage(array $user): bool
{
    return in_array($user['role'], ['admin', 'coordenador'], true) && !is_audit_only($user);
}

function is_audit_only(array $user): bool
{
    return !empty($user['audit_only']);
}

function can_access_process_area(array $user): bool
{
    return !is_audit_only($user);
}

function can_access_audits(array $user): bool
{
    return can_manage($user) || !empty($user['audit_access']) || is_audit_only($user);
}

function can_access_studies(array $user): bool
{
    return !is_audit_only($user);
}

function can_access_inss_monitoring(array $user): bool
{
    return !is_audit_only($user);
}

function user_home_path(array $user): string
{
    if (can_access_audits($user)) {
        return 'audits.php';
    }

    return can_access_studies($user) ? 'studies.php' : 'login.php';
}

function can_edit_audits(array $user): bool
{
    return can_access_audits($user) && !is_audit_only($user);
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        flash('Você não tem permissão para acessar esta tela.', 'danger');
        redirect(user_home_path($user));
    }
    if (is_audit_only($user)) {
        flash('Este usuário tem acesso apenas ao painel de auditorias.', 'warning');
        redirect('audits.php');
    }

    return $user;
}

function require_audit_access(): array
{
    $user = require_login();
    if (!can_access_audits($user)) {
        flash('Seu usuário não possui acesso ao painel de auditorias.', 'danger');
        redirect(can_access_studies($user) ? 'studies.php' : 'login.php');
    }

    return $user;
}

function require_audit_edit_access(): array
{
    $user = require_audit_access();
    if (!can_edit_audits($user)) {
        flash('Este usuário possui acesso apenas para consulta do painel de auditorias.', 'warning');
        redirect('audits.php');
    }

    return $user;
}

function require_process_access(): array
{
    $user = require_login();
    flash('O módulo de processos foi desativado. Utilize os painéis disponíveis.', 'info');
    redirect(user_home_path($user));
}

function require_study_access(): array
{
    $user = require_login();
    if (!can_access_studies($user)) {
        flash('Este usuário possui acesso somente ao painel de auditorias.', 'warning');
        redirect('audits.php');
    }

    return $user;
}

function require_inss_monitoring_access(): array
{
    $user = require_login();
    if (!can_access_inss_monitoring($user)) {
        flash('Este usuário possui acesso somente ao painel de auditorias.', 'warning');
        redirect('audits.php');
    }

    return $user;
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function selected(string $actual, string $expected): string
{
    return $actual === $expected ? 'selected' : '';
}

function checked(bool $value): string
{
    return $value ? 'checked' : '';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!$token || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
    }
}

function table_exists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    $cache[$table] = (int) $stmt->fetchColumn() > 0;

    return $cache[$table];
}

function column_exists(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    $cache[$key] = (int) $stmt->fetchColumn() > 0;

    return $cache[$key];
}

function audits_module_ready(): bool
{
    return table_exists('audits') && table_exists('audit_items');
}

function audits_schema_ready(): bool
{
    return audits_module_ready()
        && column_exists('audits', 'deadline_label')
        && column_exists('audits', 'monitoring1_start_date')
        && column_exists('audit_items', 'status_geral');
}
