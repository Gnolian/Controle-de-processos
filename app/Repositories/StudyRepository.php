<?php

declare(strict_types=1);

namespace App\Repositories;

class StudyRepository
{
    public const IMPORT_COLUMNS = [
        'knowledge_area',
        'collection_technique_1',
        'collection_technique_2',
        'collection_technique_3',
        'methodology',
        'study_type',
        'country',
        'region',
        'state',
        'city',
        'publication_type',
        'publication_year',
        'author',
        'title',
        'publication_source',
        'evidence_1',
        'evidence_2',
        'evidence_3',
        'evidence_4',
        'evidence_5',
        'keyword_1',
        'keyword_2',
        'keyword_3',
        'keyword_4',
        'keyword_5',
        'summary',
        'access_link',
    ];

    public function __construct()
    {
        $this->ensureTable();
    }

    public function countAll(): int
    {
        return (int) \db()->query('SELECT COUNT(*) FROM studies')->fetchColumn();
    }

    public function search(string $query, int $page = 1, int $perPage = 12): array
    {
        $query = trim($query);
        $terms = $query === ''
            ? []
            : array_slice(array_values(array_filter(preg_split('/\s+/u', $query) ?: [])), 0, 12);

        $where = [];
        $params = [];
        foreach ($terms as $term) {
            $where[] = 'search_text LIKE ?';
            $params[] = '%' . $term . '%';
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $countStatement = \db()->prepare('SELECT COUNT(*) FROM studies' . $whereSql);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $perPage = max(1, min(50, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $orderSql = 'publication_year IS NULL, publication_year DESC, title ASC';
        $listParams = $params;
        if ($query !== '') {
            $match = '%' . $query . '%';
            $orderSql = "CASE
                WHEN title LIKE ? THEN 0
                WHEN CONCAT_WS(' ', keyword_1, keyword_2, keyword_3, keyword_4, keyword_5) LIKE ? THEN 1
                WHEN summary LIKE ? THEN 2
                ELSE 3
            END, " . $orderSql;
            array_push($listParams, $match, $match, $match);
        }

        $statement = \db()->prepare(
            'SELECT * FROM studies' . $whereSql . ' ORDER BY ' . $orderSql
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $statement->execute($listParams);

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public function upsert(array $data, ?int $userId = null): void
    {
        $columns = array_merge(
            ['source_key', 'source_row'],
            self::IMPORT_COLUMNS,
            ['search_text', 'imported_by']
        );
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $updates = array_map(
            static fn (string $column): string => $column . ' = VALUES(' . $column . ')',
            array_filter($columns, static fn (string $column): bool => $column !== 'source_key')
        );
        $updates[] = 'updated_at = CURRENT_TIMESTAMP';

        $values = [];
        foreach ($columns as $column) {
            $values[] = $column === 'imported_by' ? $userId : ($data[$column] ?? null);
        }

        $statement = \db()->prepare(
            'INSERT INTO studies (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ') '
            . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)
        );
        $statement->execute($values);
    }

    private function ensureTable(): void
    {
        \db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS studies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_key CHAR(64) NOT NULL UNIQUE,
    source_row INT UNSIGNED NULL,
    knowledge_area VARCHAR(180) NULL,
    collection_technique_1 VARCHAR(255) NULL,
    collection_technique_2 VARCHAR(255) NULL,
    collection_technique_3 VARCHAR(255) NULL,
    methodology TEXT NULL,
    study_type VARCHAR(255) NULL,
    country VARCHAR(120) NULL,
    region VARCHAR(160) NULL,
    state VARCHAR(160) NULL,
    city VARCHAR(160) NULL,
    publication_type VARCHAR(160) NULL,
    publication_year SMALLINT UNSIGNED NULL,
    author TEXT NULL,
    title TEXT NOT NULL,
    publication_source TEXT NULL,
    evidence_1 LONGTEXT NULL,
    evidence_2 LONGTEXT NULL,
    evidence_3 LONGTEXT NULL,
    evidence_4 LONGTEXT NULL,
    evidence_5 LONGTEXT NULL,
    keyword_1 VARCHAR(255) NULL,
    keyword_2 VARCHAR(255) NULL,
    keyword_3 VARCHAR(255) NULL,
    keyword_4 VARCHAR(255) NULL,
    keyword_5 VARCHAR(255) NULL,
    summary LONGTEXT NULL,
    access_link TEXT NULL,
    search_text LONGTEXT NOT NULL,
    imported_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_studies_year (publication_year),
    INDEX idx_studies_area (knowledge_area),
    INDEX idx_studies_type (publication_type),
    CONSTRAINT fk_studies_user FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
SQL);
    }
}
