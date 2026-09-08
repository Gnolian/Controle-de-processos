<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StudyRepository;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;
use ZipArchive;

class StudyImportService
{
    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const HEADER_MAP = [
        'area_conhecimento' => 'knowledge_area',
        'tecnica_coleta' => 'collection_technique_1',
        'tecnica_coleta2' => 'collection_technique_2',
        'tecnica_coleta3' => 'collection_technique_3',
        'metodologia' => 'methodology',
        'tipo_estudo' => 'study_type',
        'pais_escopo_geografico' => 'country',
        'regiao_escopo_geografico' => 'region',
        'estado_escopo_geografico' => 'state',
        'cidade_escopo_geografico2' => 'city',
        'cidade_escopo_geografico' => 'city',
        'tipo' => 'publication_type',
        'ano' => 'publication_year',
        'autor' => 'author',
        'titulo' => 'title',
        'meio_publicacao' => 'publication_source',
        'evidencia_1' => 'evidence_1',
        'evidencia_2' => 'evidence_2',
        'evidencia_3' => 'evidence_3',
        'evidencia_4' => 'evidence_4',
        'evidencia_5' => 'evidence_5',
        'palavra_chave_1' => 'keyword_1',
        'palavra_chave_2' => 'keyword_2',
        'palavra_chave_3' => 'keyword_3',
        'palavra_chave_4' => 'keyword_4',
        'palavra_chave_5' => 'keyword_5',
        'resumo' => 'summary',
        'link_de_acesso' => 'access_link',
    ];

    public function import(string $path, string $originalName, ?int $userId = null): int
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $rows = match ($extension) {
            'xlsx' => $this->readXlsx($path),
            'csv' => $this->readCsv($path),
            default => throw new RuntimeException('Formato não suportado. Envie um arquivo XLSX ou CSV.'),
        };

        if (count($rows) < 2) {
            throw new RuntimeException('A planilha não possui estudos para importar.');
        }

        $headerRow = array_shift($rows);
        $columnMap = [];
        foreach ($headerRow['values'] as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            if (isset(self::HEADER_MAP[$normalized])) {
                $columnMap[(int) $index] = self::HEADER_MAP[$normalized];
            }
        }

        if (!in_array('title', $columnMap, true)) {
            throw new RuntimeException('A coluna "titulo" não foi encontrada na planilha.');
        }

        $repository = new StudyRepository();
        $imported = 0;
        \db()->beginTransaction();

        try {
            foreach ($rows as $row) {
                $data = array_fill_keys(StudyRepository::IMPORT_COLUMNS, null);
                foreach ($columnMap as $index => $column) {
                    $data[$column] = $this->cleanValue((string) ($row['values'][$index] ?? ''));
                }

                if (($data['title'] ?? '') === '') {
                    continue;
                }

                $year = filter_var($data['publication_year'], FILTER_VALIDATE_INT);
                $data['publication_year'] = $year !== false && $year >= 1800 && $year <= 2200 ? $year : null;
                $sourceIdentity = ($data['access_link'] ?? '') !== ''
                    ? mb_strtolower((string) $data['access_link'], 'UTF-8')
                    : implode('|', [
                        mb_strtolower((string) $data['title'], 'UTF-8'),
                        mb_strtolower((string) ($data['author'] ?? ''), 'UTF-8'),
                        (string) ($data['publication_year'] ?? ''),
                    ]);

                $searchParts = [];
                foreach (StudyRepository::IMPORT_COLUMNS as $column) {
                    if (($data[$column] ?? '') !== '') {
                        $searchParts[] = (string) $data[$column];
                    }
                }

                $data['source_key'] = hash('sha256', $sourceIdentity);
                $data['source_row'] = (int) $row['row'];
                $data['search_text'] = preg_replace('/\s+/u', ' ', implode(' ', $searchParts)) ?: '';
                $repository->upsert($data, $userId);
                $imported++;
            }

            \db()->commit();
        } catch (Throwable $exception) {
            if (\db()->inTransaction()) {
                \db()->rollBack();
            }
            throw $exception;
        }

        if ($imported === 0) {
            throw new RuntimeException('Nenhum estudo válido foi encontrado para importação.');
        }

        return $imported;
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo CSV.');
        }

        try {
            $sample = (string) fgets($handle);
            rewind($handle);
            $delimiters = [',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t")];
            arsort($delimiters);
            $delimiter = (string) array_key_first($delimiters);
            $rows = [];
            $rowNumber = 0;

            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;
                if ($rowNumber === 1 && isset($values[0])) {
                    $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $values[0]) ?? (string) $values[0];
                }
                $rows[] = ['row' => $rowNumber, 'values' => $values];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function readXlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão ZIP do PHP precisa estar habilitada no XAMPP para importar arquivos XLSX.');
        }
        if (!class_exists(DOMDocument::class)) {
            throw new RuntimeException('A extensão DOM/XML do PHP precisa estar habilitada para importar arquivos XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir a planilha XLSX.');
        }

        try {
            $workbook = $this->xmlFromZip($zip, 'xl/workbook.xml');
            $workbookRelations = $this->xmlFromZip($zip, 'xl/_rels/workbook.xml.rels');
            $workbookXPath = new DOMXPath($workbook);
            $sheet = $workbookXPath->query('//*[local-name()="sheets"]/*[local-name()="sheet"]')->item(0);
            if (!$sheet instanceof DOMElement) {
                throw new RuntimeException('A planilha XLSX não possui uma aba de dados.');
            }

            $relationId = $sheet->getAttributeNS(self::RELATIONSHIP_NAMESPACE, 'id');
            $relationsXPath = new DOMXPath($workbookRelations);
            $target = '';
            foreach ($relationsXPath->query('//*[local-name()="Relationship"]') as $relation) {
                if ($relation instanceof DOMElement && $relation->getAttribute('Id') === $relationId) {
                    $target = $relation->getAttribute('Target');
                    break;
                }
            }
            if ($target === '') {
                throw new RuntimeException('Não foi possível localizar a aba de dados no arquivo XLSX.');
            }

            $sheetPath = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : (str_starts_with($target, 'xl/') ? $target : 'xl/' . $target);
            $sheetPath = $this->normalizeZipPath($sheetPath);
            $sheetXml = $this->xmlFromZip($zip, $sheetPath);
            $sharedStrings = $this->sharedStrings($zip);
            $hyperlinks = $this->hyperlinks($zip, $sheetPath, $sheetXml);
            $sheetXPath = new DOMXPath($sheetXml);
            $rows = [];

            foreach ($sheetXPath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $rowNode) {
                if (!$rowNode instanceof DOMElement) {
                    continue;
                }
                $rowNumber = (int) $rowNode->getAttribute('r');
                $values = [];

                foreach ($sheetXPath->query('./*[local-name()="c"]', $rowNode) as $cell) {
                    if (!$cell instanceof DOMElement) {
                        continue;
                    }
                    $reference = $cell->getAttribute('r');
                    $column = $this->columnIndex($reference);
                    $values[$column] = $hyperlinks[$reference] ?? $this->cellValue($cell, $sheetXPath, $sharedStrings);
                }

                if ($values !== []) {
                    ksort($values);
                    $rows[] = ['row' => $rowNumber, 'values' => $values];
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function xmlFromZip(ZipArchive $zip, string $path): DOMDocument
    {
        $content = $zip->getFromName($path);
        if ($content === false) {
            throw new RuntimeException('O arquivo XLSX está incompleto: ' . $path . '.');
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new RuntimeException('Não foi possível interpretar a estrutura XML da planilha.');
        }

        return $document;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $document = $this->xmlFromZip($zip, 'xl/sharedStrings.xml');
        $xpath = new DOMXPath($document);
        $strings = [];
        foreach ($xpath->query('//*[local-name()="si"]') as $item) {
            $parts = [];
            foreach ($xpath->query('.//*[local-name()="t"]', $item) as $text) {
                $parts[] = $text->nodeValue;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function hyperlinks(ZipArchive $zip, string $sheetPath, DOMDocument $sheet): array
    {
        $directory = str_replace('\\', '/', dirname($sheetPath));
        $relationsPath = $directory . '/_rels/' . basename($sheetPath) . '.rels';
        if ($zip->locateName($relationsPath) === false) {
            return [];
        }

        $relations = $this->xmlFromZip($zip, $relationsPath);
        $relationsXPath = new DOMXPath($relations);
        $targets = [];
        foreach ($relationsXPath->query('//*[local-name()="Relationship"]') as $relation) {
            if ($relation instanceof DOMElement) {
                $targets[$relation->getAttribute('Id')] = $relation->getAttribute('Target');
            }
        }

        $sheetXPath = new DOMXPath($sheet);
        $links = [];
        foreach ($sheetXPath->query('//*[local-name()="hyperlinks"]/*[local-name()="hyperlink"]') as $hyperlink) {
            if (!$hyperlink instanceof DOMElement) {
                continue;
            }
            $relationId = $hyperlink->getAttributeNS(self::RELATIONSHIP_NAMESPACE, 'id');
            $reference = $hyperlink->getAttribute('ref');
            if ($reference !== '' && isset($targets[$relationId])) {
                $links[$reference] = $targets[$relationId];
            }
        }

        return $links;
    }

    private function cellValue(DOMElement $cell, DOMXPath $xpath, array $sharedStrings): string
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($xpath->query('.//*[local-name()="t"]', $cell) as $text) {
                $parts[] = $text->nodeValue;
            }
            return implode('', $parts);
        }

        $valueNode = $xpath->query('./*[local-name()="v"]', $cell)->item(0);
        $value = $valueNode?->nodeValue ?? '';
        if ($type === 's' && ctype_digit($value)) {
            return (string) ($sharedStrings[(int) $value] ?? '');
        }

        return $value;
    }

    private function columnIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return 0;
        }

        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function normalizeZipPath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim(mb_strtolower($header, 'UTF-8'));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
        $normalized = strtolower($ascii !== false ? $ascii : $header);

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $normalized), '_');
    }

    private function cleanValue(string $value): string
    {
        return trim(str_replace("\0", '', $value));
    }
}
