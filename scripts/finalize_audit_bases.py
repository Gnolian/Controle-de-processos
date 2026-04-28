from __future__ import annotations

import csv
import re
from pathlib import Path


BASE_DIR = Path(r"C:\Users\Willian\.vscode\controle_processos")
SOURCE_AUDITS = BASE_DIR / "database" / "local_imports" / "auditorias_tratadas.csv"
SOURCE_ITEMS = BASE_DIR / "database" / "local_imports" / "auditorias_itens_tratados.csv"
FINAL_AUDITS = BASE_DIR / "database" / "local_imports" / "auditorias_tratadas_final.csv"
FINAL_ITEMS = BASE_DIR / "database" / "local_imports" / "auditorias_itens_tratados_final.csv"


AUDIT_FIELD_NORMALIZERS = {
    "audit_code": lambda value: value.strip(),
    "audit_nup": lambda value: normalize_nup(value),
    "process_status": lambda value: "Em andamento" if value.strip() else "",
    "requesting_body": lambda value: normalize_controlled_value(value, {"TCU": "TCU", "CGU": "CGU"}),
    "classification": lambda value: normalize_controlled_value(value, {"ESPECIFICO": "Específico", "GERAL": "Geral"}),
    "audit_phase": lambda value: normalize_controlled_value(
        value,
        {
            "INICIALDILIGENCIA": "INICIAL/DILIGÊNCIA",
            "ELABORACAODERELATORIOFINAL": "ELABORAÇÃO DE RELATÓRIO FINAL",
            "1MONITORAMENTO": "1º MONITORAMENTO",
            "2MONITORAMENTO": "2º MONITORAMENTO",
            "3MONITORAMENTO": "3º MONITORAMENTO",
            "4MONITORAMENTO": "4º MONITORAMENTO",
        },
    ),
    "current_owner": lambda value: normalize_controlled_value(
        value,
        {
            "ORGAODECONTROLE": "ÓRGÃO DE CONTROLE",
            "DGBA": "DGBA",
        },
    ),
    "has_diligence": lambda value: normalize_controlled_value(
        value,
        {"SIM": "SIM", "NAO": "NÃO", "NÃO": "NÃO", "NA": "N/A", "N/A": "N/A"},
    ),
    "stage2_status": lambda value: normalize_controlled_value(
        value,
        {"CONCLUIDA": "CONCLUÍDA", "CONCLUÍDA": "CONCLUÍDA", "EMANDAMENTO": "EM ANDAMENTO"},
    ),
    "stage3_status": lambda value: normalize_controlled_value(
        value,
        {"CONCLUIDA": "CONCLUÍDA", "CONCLUÍDA": "CONCLUÍDA", "EMANDAMENTO": "EM ANDAMENTO"},
    ),
}


ITEM_FIELD_NORMALIZERS = {
    "audit_code": lambda value: value.strip(),
    "item_kind": lambda value: normalize_controlled_value(
        value,
        {
            "DETERMINACAO": "DETERMINAÇÃO",
            "DETERMINAÇÃO": "DETERMINAÇÃO",
            "RECOMENDACAO": "RECOMENDAÇÃO",
            "RECOMENDAÇÃO": "RECOMENDAÇÃO",
            "CIENCIA": "CIÊNCIA",
            "CIÊNCIA": "CIÊNCIA",
        },
    ),
    "control_body_status": lambda value: normalize_controlled_value(
        value,
        {
            "IMPLEMENTADA": "Implementada",
            "EMIMPLEMENTACAO": "Em implementação",
            "IMPLEMENTADAPARCIALMENTE": "Implementada parcialmente",
            "PERDADEOBJETO": "Perda de objeto",
            "AGUARDANDOANALISEDOORGAO": "Aguardando análise do órgão",
        },
    ),
    "dgba_status": lambda value: normalize_controlled_value(
        value,
        {
            "CONCLUIDA": "Concluída",
            "CONCLUÍDA": "Concluída",
            "EMIMPLEMENTACAO": "Em implementação",
            "IMPLEMENTADA": "Implementada",
            "NADAAFAZER": "Nada a fazer",
            "NAOINICIADA": "Não iniciada",
        },
    ),
    "stage3_status": lambda value: normalize_controlled_value(
        value,
        {
            "CONCLUIDA": "CONCLUÍDA",
            "CONCLUÍDA": "CONCLUÍDA",
            "EMANDAMENTO": "EM ANDAMENTO",
        },
    ),
    "monitor_1_response": lambda value: normalize_free_text(value),
    "monitor_2_response": lambda value: normalize_free_text(value),
    "monitor_3_response": lambda value: normalize_free_text(value),
    "monitor_4_response": lambda value: normalize_free_text(value),
}


TEXT_REPLACEMENTS = [
    ("Beneficio", "Benefício"),
    ("Beneficios", "Benefícios"),
    ("beneficio", "benefício"),
    ("beneficios", "benefícios"),
    ("analise", "análise"),
    ("Analise", "Análise"),
    ("polícas", "políticas"),
    ("Polícas", "Políticas"),
    ("coletuvamente", "coletivamente"),
    ("auditória", "auditoria"),
    ("auditória", "auditoria"),
    ("respodneram", "responderam"),
    ("reletório", "relatório"),
    ("reletórios", "relatórios"),
    ("cumpimento", "cumprimento"),
    ("cilco", "ciclo"),
    ("collectuvamente", "coletivamente"),
    ("orgão", "órgão"),
    ("orgão", "órgão"),
    ("Orgão", "Órgão"),
    ("NOta-se", "Nota-se"),
    ("último documentos", "últimos documentos"),
    ("Aguardado", "Aguardando"),
    ("aguardado", "aguardando"),
    ("Essa auditória", "Essa auditoria"),
    ("Esse processo já chegou no Relatório final/Acórdão.", "Esse processo já chegou ao relatório final/acórdão."),
    ("Trata-se de um processo contínuo, onde", "Trata-se de um processo contínuo em que"),
    ("Cabe, lembrar", "Cabe lembrar"),
    ("desse modo", "desse modo"),
    ("reavaliação social e médica da pessoa com deficiência - portaria publicada", "reavaliação social e médica da pessoa com deficiência - portaria publicada"),
]


def normalize_controlled_value(value: str, mapping: dict[str, str]) -> str:
    text = clean_text(value)
    if not text:
        return ""

    token = normalize_token(text)
    return mapping.get(token, text)


def normalize_token(value: str) -> str:
    value = clean_text(value).upper()
    return re.sub(r"[^A-Z0-9]+", "", strip_accents(value))


def strip_accents(value: str) -> str:
    import unicodedata

    return "".join(
        char for char in unicodedata.normalize("NFKD", value)
        if not unicodedata.combining(char)
    )


def clean_text(value: str) -> str:
    value = (value or "").replace("\ufeff", "").replace("\xa0", " ").strip()
    return re.sub(r"\s+", " ", value)


def normalize_free_text(value: str) -> str:
    text = clean_text(value)
    if not text:
        return ""

    for source, target in TEXT_REPLACEMENTS:
        text = text.replace(source, target)

    text = re.sub(r"\s+([,.;:])", r"\1", text)
    text = re.sub(r"([,;:])([^\s])", r"\1 \2", text)
    text = re.sub(r"\.(?=[A-Za-zÁ-ÿ])", ". ", text)
    text = re.sub(r"(?<=\d)\.\s+(?=\d)", ".", text)
    text = re.sub(r"\s{2,}", " ", text).strip()
    return text


def normalize_nup(value: str) -> str:
    text = clean_text(value)
    text = re.sub(r"\.\s+", ".", text)
    text = re.sub(r"/\s+", "/", text)
    return text


def normalize_row(row: dict[str, str], normalizers: dict[str, callable]) -> dict[str, str]:
    normalized: dict[str, str] = {}
    for key, raw_value in row.items():
        value = clean_text(raw_value)
        if key in normalizers:
            normalized[key] = normalizers[key](value)
        else:
            normalized[key] = normalize_free_text(value)
    return normalized


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle, delimiter=";"))


def write_csv(path: Path, rows: list[dict[str, str]]) -> None:
    if not rows:
        return

    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=list(rows[0].keys()), delimiter=";")
        writer.writeheader()
        writer.writerows(rows)


def main() -> None:
    audits = [normalize_row(row, AUDIT_FIELD_NORMALIZERS) for row in read_csv(SOURCE_AUDITS)]
    items = [normalize_row(row, ITEM_FIELD_NORMALIZERS) for row in read_csv(SOURCE_ITEMS)]

    write_csv(FINAL_AUDITS, audits)
    write_csv(FINAL_ITEMS, items)

    print(FINAL_AUDITS)
    print(FINAL_ITEMS)


if __name__ == "__main__":
    main()
