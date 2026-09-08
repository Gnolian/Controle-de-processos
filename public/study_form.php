<?php

use App\Repositories\StudyRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_study_access();
$repository = new StudyRepository();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$study = $id ? $repository->find($id) : null;

if ($id && !$study) {
    flash('Estudo não encontrado.', 'danger');
    redirect('studies.php');
}

$values = array_merge(array_fill_keys(StudyRepository::IMPORT_COLUMNS, ''), $study ?: []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $savedId = $repository->save($_POST, $id, (int) $user['id']);
        flash($id ? 'Estudo atualizado com sucesso.' : 'Estudo adicionado com sucesso.');
        redirect('studies.php?' . http_build_query([
            'q' => trim((string) ($_POST['title'] ?? '')),
            'highlight' => $savedId,
        ]) . '#study-' . $savedId);
    } catch (Throwable $exception) {
        flash($exception->getMessage(), 'danger');
        $values = array_merge($values, $_POST);
    }
}

$renderInput = static function (
    string $name,
    string $label,
    array $formValues,
    string $type = 'text',
    bool $required = false,
    string $placeholder = ''
): void {
    ?>
    <div class="col">
        <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <input
            class="form-control"
            id="<?= e($name) ?>"
            name="<?= e($name) ?>"
            type="<?= e($type) ?>"
            value="<?= e((string) ($formValues[$name] ?? '')) ?>"
            <?= $required ? 'required' : '' ?>
            <?= $placeholder !== '' ? 'placeholder="' . e($placeholder) . '"' : '' ?>
        >
    </div>
    <?php
};

$renderTextarea = static function (string $name, string $label, array $formValues, int $rows = 4): void {
    ?>
    <div class="col-12">
        <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <textarea class="form-control" id="<?= e($name) ?>" name="<?= e($name) ?>" rows="<?= $rows ?>"><?= e((string) ($formValues[$name] ?? '')) ?></textarea>
    </div>
    <?php
};

$pageTitle = $id ? 'Editar estudo' : 'Adicionar estudo';
$activeNav = 'studies';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row">
        <div>
            <p class="section-kicker"><?= $id ? 'Atualização do acervo' : 'Novo registro' ?></p>
            <h1><?= e($pageTitle) ?></h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('studies.php') ?>"><i class="bi bi-arrow-left"></i> Voltar</a>
    </section>

    <form method="post" class="app-card form-card study-form-card">
        <?= csrf_field() ?>

        <div class="form-section">
            <h2>Identificação e acesso</h2>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <div class="col-md-8">
                    <label class="form-label" for="title">Título</label>
                    <input class="form-control" id="title" name="title" value="<?= e((string) $values['title']) ?>" required>
                </div>
                <?php $renderInput('publication_year', 'Ano de publicação', $values, 'number', false); ?>
                <div class="col-12">
                    <label class="form-label" for="access_link">Link de acesso</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                        <input class="form-control" id="access_link" name="access_link" inputmode="url" value="<?= e((string) $values['access_link']) ?>" placeholder="https://exemplo.gov.br/estudo">
                    </div>
                    <small class="text-muted">Confira o endereço completo. Se o protocolo não for informado, o sistema adicionará https://.</small>
                </div>
                <?php $renderInput('author', 'Autor ou instituição autora', $values); ?>
                <?php $renderInput('publication_source', 'Meio de publicação', $values); ?>
                <?php $renderInput('publication_type', 'Tipo de publicação', $values); ?>
                <?php $renderInput('knowledge_area', 'Área do conhecimento', $values); ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Conteúdo do estudo</h2>
            <div class="row g-3">
                <?php $renderTextarea('summary', 'Resumo', $values, 6); ?>
                <?php $renderTextarea('methodology', 'Metodologia', $values, 3); ?>
                <div class="col-md-6">
                    <label class="form-label" for="study_type">Natureza do estudo</label>
                    <input class="form-control" id="study_type" name="study_type" value="<?= e((string) $values['study_type']) ?>">
                </div>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <?php $renderInput('collection_technique_' . $i, 'Técnica de coleta ' . $i, $values); ?>
                <?php endfor; ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Palavras-chave</h2>
            <div class="row row-cols-1 row-cols-md-3 g-3">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php $renderInput('keyword_' . $i, 'Palavra-chave ' . $i, $values); ?>
                <?php endfor; ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Escopo geográfico</h2>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <?php $renderInput('country', 'País', $values); ?>
                <?php $renderInput('region', 'Região', $values); ?>
                <?php $renderInput('state', 'Estado', $values); ?>
                <?php $renderInput('city', 'Cidade', $values); ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Evidências</h2>
            <div class="row g-3">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php $renderTextarea('evidence_' . $i, 'Evidência ' . $i, $values, 3); ?>
                <?php endfor; ?>
            </div>
        </div>

        <div class="form-actions sticky-actions">
            <a class="btn btn-outline-secondary" href="<?= url('studies.php') ?>">Cancelar</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Salvar estudo</button>
        </div>
    </form>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
