<?php

use App\Repositories\StudyRepository;

require __DIR__ . '/../app/bootstrap.php';

$user = require_study_access();
$query = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$highlightId = max(0, (int) ($_GET['highlight'] ?? 0));
$repository = new StudyRepository();
$result = $repository->search($query, $page, 12);
$collectionTotal = $repository->countAll();

$safeLink = static function (?string $link): ?string {
    $link = trim((string) $link);
    if ($link === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $link)) {
        $link = 'https://' . ltrim($link, '/');
    }
    if (filter_var($link, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $link : null;
};

$pageUrl = static function (int $targetPage) use ($query): string {
    return url('studies.php?' . http_build_query(['q' => $query, 'page' => $targetPage]));
};

$pageTitle = 'Banco de estudos';
$activeNav = 'studies';

require __DIR__ . '/../views/header.php';
require __DIR__ . '/../views/nav.php';
?>

<main class="content-shell studies-page">
    <?php require __DIR__ . '/../views/flash.php'; ?>

    <section class="page-title-row studies-title-row">
        <div>
            <p class="section-kicker">Conhecimento e evidências</p>
            <h1>Banco de estudos</h1>
            <p class="text-muted mb-0">Consulte publicações, evidências e referências reunidas pela DGBA.</p>
        </div>
        <div class="studies-title-actions">
            <a class="btn btn-primary" href="<?= url('study_form.php') ?>">
                <i class="bi bi-plus-lg"></i> Adicionar estudo
            </a>
            <a class="btn btn-outline-primary" href="<?= url('study_import.php') ?>">
                <i class="bi bi-cloud-upload"></i> Importar planilha
            </a>
        </div>
    </section>

    <section class="app-card study-search-panel">
        <form method="get" class="study-search-form" role="search">
            <label class="form-label mb-0" for="study-search">
                Pesquisar no acervo
                <span class="study-search-input mt-2">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input
                        class="form-control form-control-lg"
                        id="study-search"
                        name="q"
                        value="<?= e($query) ?>"
                        placeholder="Título, tema, palavra-chave, autor, evidência ou frase"
                        autocomplete="off"
                    >
                </span>
            </label>
            <div class="study-search-actions">
                <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-search"></i> Pesquisar</button>
                <?php if ($query !== ''): ?>
                    <a class="btn btn-outline-secondary btn-lg" href="<?= url('studies.php') ?>">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section id="study-results" class="study-results-section">
        <div class="study-results-head">
            <div>
                <p class="section-kicker mb-1"><?= $query !== '' ? 'Resultado da pesquisa' : 'Acervo disponível' ?></p>
                <h2><?= (int) $result['total'] ?> <?= (int) $result['total'] === 1 ? 'estudo encontrado' : 'estudos encontrados' ?></h2>
            </div>
            <span class="study-collection-total"><?= (int) $collectionTotal ?> no acervo</span>
        </div>

        <?php if (!$result['items']): ?>
            <div class="app-card empty-state study-empty-state">
                <i class="bi bi-journal-x" aria-hidden="true"></i>
                <h3>Nenhum estudo encontrado</h3>
                <p>Revise os termos pesquisados ou use palavras mais amplas.</p>
            </div>
        <?php else: ?>
            <div class="study-results-list">
                <?php foreach ($result['items'] as $study): ?>
                    <?php
                    $keywords = array_values(array_filter([
                        $study['keyword_1'],
                        $study['keyword_2'],
                        $study['keyword_3'],
                        $study['keyword_4'],
                        $study['keyword_5'],
                    ]));
                    $location = implode(', ', array_values(array_filter([
                        $study['city'],
                        $study['state'],
                        $study['region'],
                        $study['country'],
                    ])));
                    $evidences = array_values(array_filter([
                        $study['evidence_1'],
                        $study['evidence_2'],
                        $study['evidence_3'],
                        $study['evidence_4'],
                        $study['evidence_5'],
                    ]));
                    $techniques = array_values(array_filter([
                        $study['collection_technique_1'],
                        $study['collection_technique_2'],
                        $study['collection_technique_3'],
                    ]));
                    $link = $safeLink($study['access_link']);
                    $hasPdf = !empty($study['pdf_file']);
                    ?>
                    <article id="study-<?= (int) $study['id'] ?>" class="study-result-card <?= $highlightId === (int) $study['id'] ? 'study-result-card--highlight' : '' ?>">
                        <div class="study-result-main">
                            <div class="study-result-copy">
                                <div class="study-result-badges">
                                    <?php if ($study['publication_year']): ?><span><?= (int) $study['publication_year'] ?></span><?php endif; ?>
                                    <?php if ($study['publication_type']): ?><span><?= e($study['publication_type']) ?></span><?php endif; ?>
                                    <?php if ($study['knowledge_area']): ?><span><?= e($study['knowledge_area']) ?></span><?php endif; ?>
                                </div>
                                <h3><?= e($study['title']) ?></h3>
                                <?php if ($study['author']): ?><p class="study-author"><?= e($study['author']) ?></p><?php endif; ?>

                                <div class="study-meta">
                                    <?php if ($study['publication_source']): ?>
                                        <span><i class="bi bi-building"></i><?= e($study['publication_source']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($location !== ''): ?>
                                        <span><i class="bi bi-geo-alt"></i><?= e($location) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($study['summary']): ?>
                                    <p class="study-summary"><?= e($study['summary']) ?></p>
                                <?php endif; ?>

                                <?php if ($keywords): ?>
                                    <div class="study-keywords" aria-label="Palavras-chave">
                                        <?php foreach ($keywords as $keyword): ?><span><?= e($keyword) ?></span><?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="study-result-actions">
                                <?php if ($hasPdf): ?>
                                    <a class="btn btn-primary study-access-link" href="<?= url('study_pdf.php?id=' . (int) $study['id']) ?>" target="_blank" rel="noopener">
                                        <i class="bi bi-file-earmark-pdf"></i> Abrir PDF
                                    </a>
                                <?php endif; ?>
                                <?php if ($link): ?>
                                    <a class="btn <?= $hasPdf ? 'btn-outline-primary' : 'btn-primary' ?> study-access-link" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="bi bi-box-arrow-up-right"></i> Abrir link
                                    </a>
                                <?php elseif (!$hasPdf): ?>
                                    <span class="study-link-warning"><i class="bi bi-exclamation-circle"></i> Link não informado ou inválido</span>
                                <?php endif; ?>
                                <a class="btn btn-outline-primary" href="<?= url('study_form.php?id=' . (int) $study['id']) ?>">
                                    <i class="bi bi-pencil-square"></i> Editar
                                </a>
                            </div>
                        </div>

                        <?php if ($study['summary'] || $evidences): ?>
                            <details class="study-details">
                                <summary>Ver resumo e evidências</summary>
                                <div class="study-details-content">
                                    <?php if ($study['summary']): ?>
                                        <section>
                                            <h4>Resumo</h4>
                                            <p><?= nl2br(e($study['summary'])) ?></p>
                                        </section>
                                    <?php endif; ?>
                                    <?php if ($evidences): ?>
                                        <section>
                                            <h4>Evidências</h4>
                                            <ol>
                                                <?php foreach ($evidences as $evidence): ?><li><?= e($evidence) ?></li><?php endforeach; ?>
                                            </ol>
                                        </section>
                                    <?php endif; ?>
                                    <?php if ($study['methodology'] || $study['study_type'] || $techniques): ?>
                                        <section>
                                            <h4>Método do estudo</h4>
                                            <dl class="study-facts">
                                                <?php if ($study['study_type']): ?><div><dt>Natureza</dt><dd><?= e($study['study_type']) ?></dd></div><?php endif; ?>
                                                <?php if ($study['methodology']): ?><div><dt>Metodologia</dt><dd><?= e($study['methodology']) ?></dd></div><?php endif; ?>
                                                <?php if ($techniques): ?><div><dt>Técnicas de coleta</dt><dd><?= e(implode(', ', $techniques)) ?></dd></div><?php endif; ?>
                                            </dl>
                                        </section>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ((int) $result['pages'] > 1): ?>
                <nav class="study-pagination" aria-label="Paginação dos estudos">
                    <?php if ((int) $result['page'] > 1): ?>
                        <a class="page-link-mini" href="<?= $pageUrl((int) $result['page'] - 1) ?>" aria-label="Página anterior"><i class="bi bi-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= (int) $result['pages']; $i++): ?>
                        <a class="page-link-mini <?= $i === (int) $result['page'] ? 'active' : '' ?>" href="<?= $pageUrl($i) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ((int) $result['page'] < (int) $result['pages']): ?>
                        <a class="page-link-mini" href="<?= $pageUrl((int) $result['page'] + 1) ?>" aria-label="Próxima página"><i class="bi bi-chevron-right"></i></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../views/app_end.php'; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
