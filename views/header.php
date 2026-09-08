<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Painéis DGBA') ?> | <?= e(config('app_name')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php $stylesPath = __DIR__ . '/../public/assets/styles.css'; ?>
    <link rel="stylesheet" href="<?= url('assets/styles.css?v=' . (is_file($stylesPath) ? filemtime($stylesPath) : time())) ?>">
</head>
<body class="<?= e($bodyClass ?? 'app-body') ?>">
