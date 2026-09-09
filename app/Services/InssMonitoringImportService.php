<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InssMonitoringRepository;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;
use ZipArchive;

class InssMonitoringImportService
{
    private const HEADER_MAP = [
        'id' => 'external_id',
        'processo_sei' => 'sei_process',
        'n_do_oficio_enviado' => 'sent_office_number',
        'no_do_oficio_enviado' => 'sent_office_number',
        'numero_do_oficio_enviado' => 'sent_office_number',
        'n_oficio_enviado' => 'sent_office_number',
        'no_oficio_enviado' => 'sent_office_number',
        'numero_do_oficio' => 'sent_office_number',
        'sei_do_oficio_enviado' => 'sent_office_sei',
        'sei_oficio_enviado' => 'sent_office_sei',
        'sei_do_oficio' => 'sent_office_sei',
        'data_do_oficio' => 'office_date',
        'data_oficio' => 'office_date',
        'data_de_envio_ao_inss' => 'sent_to_inss_date',
        'unidade_destinataria_inss' => 'inss_recipient_unit',
        'unidade_destinataria_do_inss' => 'inss_recipient_unit',
        'unidade_destinataria' => 'inss_recipient_unit',
        'assunto' => 'subject',
        'tipo_de_demanda' => 'demand_type',
        'origem_da_demanda' => 'demand_origin',
        'beneficiario_interessado' => 'beneficiary',
        'beneficiarios_interessados' => 'beneficiary',
        'beneficiario' => 'beneficiary',
        'beneficiarios' => 'beneficiary',
        'interessado' => 'beneficiary',
        'cpf' => 'cpf',
        'nb' => 'benefit_number',
        'situacao_do_prazo' => 'deadline_status',
        'status' => 'status',
        'data_da_resposta_inss' => 'inss_response_date',
        'n_oficio_resposta_inss' => 'response_office_number',
        'no_oficio_resposta_inss' => 'response_office_number',
        'numero_oficio_resposta_inss' => 'response_office_number',
        'sei_da_resposta' => 'response_sei',
        'resposta_conclusiva' => 'conclusive_response',
        'necessita_nova_cobranca' => 'needs_follow_up',
        'data_da_cobranca' => 'follow_up_date',
        'quantidade_de_cobrancas' => 'follow_up_count',
        'responsavel_pelo_acompanhamento' => 'owner',
        'prioridade' => 'priority',
        'observacao' => 'notes',
        'observacoes' => 'notes',
        'observacoes_complementares' => 'notes',
        'link_sei' => 'sei_link',
    ];

    public function import(string $path, string $originalName, int $userId): int
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $rows = match ($extension) {
            'xlsx' => $this->readXlsx($path),
            'csv' => $this->readCsv($path),
            default => throw new RuntimeException('Formato não suportado. Envie um arquivo XLSX ou CSV.'),
        };

        if (count($rows) < 2) {
            throw new RuntimeException('A planilha não possui registros para importar.');
        }

        $header = array_shift($rows);
        $columnMap = [];
        foreach ($header['values'] as $index => $label) {
            $normalized = $this->normalizeHeader((string) $label);
            if (isset(self::HEADER_MAP[$normalized])) {
                $columnMap[(int) $index] = self::HEADER_MAP[$normalized];
            }
        }
        if (!in_array('sei_process', $columnMap, true)) {
            throw new RuntimeException('A coluna "Processo SEI" não foi encontrada na planilha.');
        }

        $repository = new InssMonitoringRepository();
        $columns = array_merge(InssMonitoringRepository::BASE_COLUMNS, InssMonitoringRepository::TRACKING_COLUMNS);
        $imported = 0;
        \db()->beginTransaction();

        try {
            foreach ($rows as $row) {
                $data = array_fill_keys($columns, null);
                foreach ($columnMap as $index => $column) {
                    $value = $this->cleanValue((string) ($row['values'][$index] ?? ''));
                    $data[$column] = $value !== '' ? $value : null;
                }

                if (empty($data['sei_process'])) {
                    continue;
                }
                foreach (['office_date', 'sent_to_inss_date', 'inss_response_date', 'follow_up_date'] as $dateColumn) {
                    $data[$dateColumn] = $this->parseDate($data[$dateColumn]);
                }
                foreach (['external_id', 'follow_up_count'] as $integerColumn) {
                    $data[$integerColumn] = $this->parseInteger($data[$integerColumn]);
                }
                $data['sei_link'] = $this->normalizeLink($data['sei_link']);

                $repository->upsertImported($data, (int) $row['row'], $userId);
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
            throw new RuntimeException('Nenhum registro válido foi encontrado para importação.');
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
        if (!class_exists(ZipArchive::class) || !class_exists(DOMDocument::class)) {
            throw new RuntimeException('As extensões ZIP e DOM/XML do PHP precisam estar habilitadas para importar XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir a planilha XLSX.');
        }

        try {
            $workbook = $this->xmlFromZip($zip, 'xl/workbook.xml');
            $relations = $this->xmlFromZip($zip, 'xl/_rels/workbook.xml.rels');
            $workbookXPath = new DOMXPath($workbook);
            $sheet = $workbookXPath->query('//*[local-name()="sheets"]/*[local-name()="sheet"]')->item(0);
            if (!$sheet instanceof DOMElement) {
                throw new RuntimeException('A planilha XLSX não possui uma aba de dados.');
            }

            $relationId = '';
            foreach ($sheet->attributes as $attribute) {
                if ($attribute->localName === 'id') {
                    $relationId = $attribute->nodeValue;
                    break;
                }
            }
            $relationsXPath = new DOMXPath($relations);
            $target = '';
            foreach ($relationsXPath->query('//*[local-name()="Relationship"]') as $relation) {
                if ($relation instanceof DOMElement && $relation->getAttribute('Id') === $relationId) {
                    $target = $relation->getAttribute('Target');
                    break;
                }
            }
            if ($target === '') {
                throw new RuntimeException('Não foi possível localizar a aba de dados no XLSX.');
            }

            $sheetPath = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : (str_starts_with($target, 'xl/') ? $target : 'xl/' . $target);
            $sheetXml = $this->xmlFromZip($zip, $this->normalizeZipPath($sheetPath));
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXPath = new DOMXPath($sheetXml);
            $rows = [];

            foreach ($sheetXPath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $rowNode) {
                if (!$rowNode instanceof DOMElement) {
                    continue;
                }
                $values = [];
                foreach ($sheetXPath->query('./*[local-name()="c"]', $rowNode) as $cell) {
                    if (!$cell instanceof DOMElement) {
                        continue;
                    }
                    $values[$this->columnIndex($cell->getAttribute('r'))] = $this->cellValue($cell, $sheetXPath, $sharedStrings);
                }
                if ($values !== []) {
                    $rows[] = ['row' => (int) $rowNode->getAttribute('r'), 'values' => $values];
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
            throw new RuntimeException('Não foi possível interpretar o XML da planilha.');
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
        $value = $xpath->query('./*[local-name()="v"]', $cell)->item(0)?->nodeValue ?? '';

        return $type === 's' && ctype_digit($value) ? (string) ($sharedStrings[(int) $value] ?? '') : $value;
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
            } else {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim(mb_strtolower($header, 'UTF-8'));
        $header = strtr($header, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'º' => 'o', 'ª' => 'a',
        ]);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($ascii !== false ? $ascii : $header)), '_');
    }

    private function cleanValue(string $value): string
    {
        $value = trim(str_replace("\0", '', $value));
        if ($value !== '' && !mb_check_encoding($value, 'UTF-8')) {
            $converted = iconv('Windows-1252', 'UTF-8//IGNORE', $value);
            $value = $converted !== false ? $converted : $value;
        }

        return $value;
    }

    private function parseDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (new \DateTimeImmutable('1899-12-30'))->modify('+' . (int) floor((float) $value) . ' days')->format('Y-m-d');
        }
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!d/m/Y H:i:s', '!Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function parseInteger(mixed $value): ?int
    {
        $value = trim((string) $value);
        $validated = filter_var($value, FILTER_VALIDATE_INT);

        return $value !== '' && $validated !== false ? (int) $validated : null;
    }

    private function normalizeLink(mixed $value): ?string
    {
        $link = trim((string) $value);
        if ($link === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $link)) {
            $link = 'https://' . ltrim($link, '/');
        }

        return filter_var($link, FILTER_VALIDATE_URL) !== false ? $link : null;
    }
}
