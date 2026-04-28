from __future__ import annotations

import csv
import re
from pathlib import Path


BASE_DIR = Path(r"C:\Users\Willian\.vscode\controle_processos")
AUDITS_PATH = BASE_DIR / "database" / "local_imports" / "auditorias_tratadas.csv"
ITEMS_PATH = BASE_DIR / "database" / "local_imports" / "auditorias_itens_tratados.csv"


AUDIT_FIELD_NORMALIZERS = {
    "audit_code": lambda value: value.strip(),
    "audit_nup": lambda value: normalize_nup(value),
    "process_status": lambda value: normalize_controlled_value(
        value,
        {
            "EMANDAMENTO": "Em andamento",
            "CONCLUIDA": "Concluída",
            "CONCLUIDO": "Concluído",
            "ARQUIVADA": "Arquivada",
            "ARQUIVADO": "Arquivado",
        },
    ),
    "requesting_body": lambda value: normalize_controlled_value(
        value,
        {
            "TCU": "TCU",
            "CGU": "CGU",
        },
    ),
    "classification": lambda value: normalize_controlled_value(
        value,
        {
            "ESPECIFICO": "Específico",
            "GERAL": "Geral",
        },
    ),
    "audit_phase": lambda value: normalize_controlled_value(
        value,
        {
            "INICIALDILIGENCIA": "INICIAL/DILIGÊNCIA",
            "MONITORAMENTOAINICIAR": "MONITORAMENTO À INICIAR",
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
        {
            "SIM": "SIM",
            "NAO": "NÃO",
            "N/A": "N/A",
            "NA": "N/A",
        },
    ),
    "flag_estimated": lambda value: normalize_controlled_value(
        value,
        {
            "SIM": "SIM",
            "NAO": "NÃO",
            "N/A": "N/A",
            "NA": "N/A",
        },
    ),
    "flag_stage2_diligence": lambda value: normalize_controlled_value(
        value,
        {
            "SIM": "SIM",
            "NAO": "NÃO",
            "N/A": "N/A",
            "NA": "N/A",
        },
    ),
    "stage2_status": lambda value: normalize_controlled_value(
        value,
        {
            "CONCLUIDA": "CONCLUÍDA",
            "EMANDAMENTO": "EM ANDAMENTO",
            "NAOINICIADO": "NÃO INICIADO",
            "SIM": "SIM",
            "NAO": "NÃO",
        },
    ),
    "stage3_status": lambda value: normalize_controlled_value(
        value,
        {
            "CONCLUIDA": "CONCLUÍDA",
            "EMANDAMENTO": "EM ANDAMENTO",
            "NAOINICIADO": "NÃO INICIADO",
        },
    ),
    "monitoring1_status": lambda value: normalize_controlled_value(
        value,
        {"SIM": "SIM", "NAO": "NÃO", "NAOINICIADO": "NÃO INICIADO"},
    ),
    "monitoring2_status": lambda value: normalize_controlled_value(
        value,
        {"SIM": "SIM", "NAO": "NÃO", "NAOINICIADO": "NÃO INICIADO"},
    ),
    "monitoring3_status": lambda value: normalize_controlled_value(
        value,
        {"SIM": "SIM", "NAO": "NÃO", "NAOINICIADO": "NÃO INICIADO"},
    ),
    "monitoring4_status": lambda value: normalize_controlled_value(
        value,
        {"SIM": "SIM", "NAO": "NÃO", "NAOINICIADO": "NÃO INICIADO"},
    ),
}


ITEM_FIELD_NORMALIZERS = {
    "audit_code": lambda value: value.strip(),
    "item_kind": lambda value: normalize_controlled_value(
        value,
        {
            "DETERMINACAO": "DETERMINAÇÃO",
            "RECOMENDACAO": "RECOMENDAÇÃO",
            "CIENCIA": "CIÊNCIA",
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
            "CONCLUIDA": "Concluída",
        },
    ),
    "dgba_status": lambda value: normalize_controlled_value(
        value,
        {
            "CONCLUIDA": "Concluída",
            "EMIMPLEMENTACAO": "Em implementação",
            "IMPLEMENTADA": "Implementada",
            "NADAAFAZER": "Nada a fazer",
            "NAOINICIADA": "Não iniciada",
            "IMPLEMENTADOPARCIALMENTE": "Implementado parcialmente",
            "IMPLEMENTADAPARCIALMENTE": "Implementada parcialmente",
        },
    ),
    "status_geral": lambda value: normalize_controlled_value(
        value,
        {
            "EMANDAMENTO": "EM ANDAMENTO",
            "CONCLUIDA": "CONCLUÍDA",
            "NAOINICIADO": "NÃO INICIADO",
        },
    ),
}


TEXT_REPLACEMENTS = [
    ("NÂO", "NÃO"),
    ("NÃO", "NÃO"),
    ("ORGÃO", "ÓRGÃO"),
    ("orgão", "órgão"),
    ("Beneficio", "Benefício"),
    ("beneficio", "benefício"),
    ("Beneficios", "Benefícios"),
    ("beneficios", "benefícios"),
    ("polícas", "políticas"),
    ("Polícas", "Políticas"),
    ("cumpimento", "cumprimento"),
    ("cilco", "ciclo"),
    ("respodneram", "responderam"),
    ("aguardado", "aguardando"),
    ("Aguardado", "Aguardando"),
    ("reletório", "relatório"),
    ("Reletório", "Relatório"),
    ("reletórios", "relatórios"),
    ("reletório", "relatório"),
    ("auditória", "auditoria"),
    ("Auditória", "Auditoria"),
    ("collectuvamente", "coletivamente"),
    ("coletuvamente", "coletivamente"),
    ("NOta-se", "Nota-se"),
    ("ultimo", "último"),
    ("Último documentos", "Últimos documentos"),
    ("último documentos", "últimos documentos"),
    ("Nosso última", "Nossa última"),
    ("em cumpimento", "em cumprimento"),
    ("em cumpimento e autorizou", "em cumprimento e autorizou"),
    ("A Coordenação enviou resposta em 27/02/2026 e a Resposta encaminhada pela Ofício", "A Coordenação enviou resposta em 27/02/2026, e a resposta foi encaminhada pelo Ofício"),
    ("Trata-se de um processo contínuo, onde", "Trata-se de um processo contínuo em que"),
    ("Cabe, lembrar", "Cabe lembrar"),
    ("já chegou no Relatório final/Acórdão", "já chegou ao relatório final/acórdão"),
    ("Não é uma auditoria específica da SNBA. Sugestão: encerrar o processo, sem prejuízo de reabertura caso tenha alguma demanda específica", "Não é uma auditoria específica da SNBA. Sugestão: encerrar o processo, sem prejuízo de reabertura, caso haja alguma demanda específica"),
]


def clean_text(value: str) -> str:
    value = (value or "").replace("\ufeff", "").replace("\xa0", " ").strip()
    return re.sub(r"\s+", " ", value)


def strip_accents(value: str) -> str:
    import unicodedata

    return "".join(
        char for char in unicodedata.normalize("NFKD", value)
        if not unicodedata.combining(char)
    )


def normalize_token(value: str) -> str:
    return re.sub(r"[^A-Z0-9]+", "", strip_accents(clean_text(value)).upper())


def normalize_controlled_value(value: str, mapping: dict[str, str]) -> str:
    text = clean_text(value)
    if not text:
        return ""

    return mapping.get(normalize_token(text), text)


def normalize_nup(value: str) -> str:
    text = clean_text(value)
    text = re.sub(r"\.\s+", ".", text)
    text = re.sub(r"/\s+", "/", text)
    return text


def normalize_free_text(value: str) -> str:
    text = clean_text(value)
    if not text:
        return ""

    for source, target in TEXT_REPLACEMENTS:
        text = text.replace(source, target)

    text = re.sub(r"\s+([,.;:])", r"\1", text)
    text = re.sub(r"([,;:])([^\s])", r"\1 \2", text)
    text = re.sub(r"\.(?=[A-Za-zÁ-ÿ])", ". ", text)
    text = re.sub(r"\s{2,}", " ", text).strip()
    return text


def normalize_row(row: dict[str, str], field_normalizers: dict[str, callable]) -> dict[str, str]:
    normalized: dict[str, str] = {}
    for key, raw_value in row.items():
        cleaned = clean_text(raw_value)
        if key in field_normalizers:
            normalized[key] = field_normalizers[key](cleaned)
        elif key == "":
            normalized[key] = cleaned
        else:
            normalized[key] = normalize_free_text(cleaned)
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
    audits = [normalize_row(row, AUDIT_FIELD_NORMALIZERS) for row in read_csv(AUDITS_PATH)]
    items = [normalize_row(row, ITEM_FIELD_NORMALIZERS) for row in read_csv(ITEMS_PATH)]

    write_csv(AUDITS_PATH, audits)
    write_csv(ITEMS_PATH, items)

    print(f"Arquivos atualizados: {AUDITS_PATH.name}, {ITEMS_PATH.name}")
    print(f"Auditorias: {len(audits)}")
    print(f"Itens: {len(items)}")


if __name__ == "__main__":
    main()
