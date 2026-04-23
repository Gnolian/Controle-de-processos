from __future__ import annotations

import csv
from pathlib import Path


SOURCE = Path(r"C:\Users\Willian\Downloads\Painel_Auditorias_PowerBI-teste(DADOS_BASE).csv")
OUTPUT_DIR = Path(r"C:\Users\Willian\.vscode\controle_processos\database\local_imports")
AUDITS_OUT = OUTPUT_DIR / "auditorias_tratadas.csv"
ITEMS_OUT = OUTPUT_DIR / "auditorias_itens_tratados.csv"


def fix_text(value: str) -> str:
    value = (value or "").replace("\ufeff", "").strip()
    if not value:
        return ""

    try:
        converted = value.encode("latin1").decode("utf-8")
        value = converted
    except Exception:
        pass

    return " ".join(value.replace("\xa0", " ").split())


def parse_rows(path: Path) -> list[list[str]]:
    with path.open("r", encoding="latin1", newline="") as handle:
        return [[fix_text(cell) for cell in row] for row in csv.reader(handle, delimiter=";")]


def cell(row: list[str], index: int) -> str:
    return row[index].strip() if index < len(row) else ""


def normalize_kind(value: str) -> str:
    value = fix_text(value).upper()
    aliases = {
        "DETERMINAÇÃO": "DETERMINAÇÃO",
        "DETERMINACAO": "DETERMINAÇÃO",
        "RECOMENDAÇÃO": "RECOMENDAÇÃO",
        "RECOMENDACAO": "RECOMENDAÇÃO",
        "CIÊNCIA": "CIÊNCIA",
        "CIENCIA": "CIÊNCIA",
    }
    return aliases.get(value, "")


def build_outputs(rows: list[list[str]]) -> tuple[list[dict[str, str]], list[dict[str, str]]]:
    data_rows = rows[2:]
    current_audit_code = ""
    audits: list[dict[str, str]] = []
    items: list[dict[str, str]] = []

    for row in data_rows:
        if any(cell_value.strip() for cell_value in row) is False:
            continue

        if cell(row, 0):
            current_audit_code = cell(row, 0)
            audits.append(
                {
                    "audit_code": current_audit_code,
                    "audit_nup": cell(row, 1),
                    "audit_year": cell(row, 2),
                    "process_status": cell(row, 3),
                    "requesting_body": cell(row, 4),
                    "audit_type": cell(row, 5),
                    "objective": cell(row, 6),
                    "theme": cell(row, 7),
                    "classification": cell(row, 8),
                    "audit_phase": cell(row, 9),
                    "current_owner": cell(row, 10),
                    "start_date": cell(row, 11),
                    "has_diligence": cell(row, 12),
                    "last_response_sent_date": cell(row, 13),
                    "preliminary_document": cell(row, 14),
                    "stage2_deadline_days": cell(row, 15),
                    "comments_due_date": cell(row, 16),
                    "stage2_final_response": cell(row, 17),
                    "final_report": cell(row, 18),
                    "stage2_service_deadline_days": cell(row, 19),
                    "stage2_final_deadline": cell(row, 20),
                    "stage2_final_answer": cell(row, 21),
                    "stage2_status": cell(row, 22),
                    "accord_report": cell(row, 23),
                    "accord_report_date": cell(row, 24),
                    "stage3_status": cell(row, 32),
                    "control_point": cell(row, 60) or cell(row, 31),
                    "related_processes": cell(row, 61),
                    "notes": cell(row, 62) or cell(row, 87),
                }
            )

        item_kind = normalize_kind(cell(row, 25))
        if current_audit_code and item_kind:
            items.append(
                {
                    "audit_code": current_audit_code,
                    "item_kind": item_kind,
                    "item_code": cell(row, 25),
                    "item_description": cell(row, 26),
                    "compliance_deadline_days": cell(row, 27),
                    "compliance_start_date": cell(row, 28),
                    "control_body_status": cell(row, 29),
                    "dgba_status": cell(row, 30),
                    "item_control_point": cell(row, 31),
                    "stage3_status": cell(row, 32),
                    "monitor_1_start_date": cell(row, 33),
                    "monitor_1_deadline_days": cell(row, 34),
                    "monitor_1_final_deadline": cell(row, 35),
                    "monitor_1_response": cell(row, 36),
                    "monitor_2_start_date": cell(row, 37),
                    "monitor_2_deadline_days": cell(row, 38),
                    "monitor_2_final_deadline": cell(row, 39),
                    "monitor_2_response": cell(row, 40),
                    "monitor_3_start_date": cell(row, 43),
                    "monitor_3_deadline_days": cell(row, 44),
                    "monitor_3_final_deadline": cell(row, 45),
                    "monitor_3_response": cell(row, 46),
                    "monitor_4_start_date": cell(row, 49),
                    "monitor_4_deadline_days": cell(row, 50),
                    "monitor_4_final_deadline": cell(row, 51),
                    "monitor_4_response": cell(row, 52),
                }
            )

    return audits, items


def write_csv(path: Path, rows: list[dict[str, str]]) -> None:
    if not rows:
        return

    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=list(rows[0].keys()), delimiter=";")
        writer.writeheader()
        writer.writerows(rows)


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    rows = parse_rows(SOURCE)
    audits, items = build_outputs(rows)
    write_csv(AUDITS_OUT, audits)
    write_csv(ITEMS_OUT, items)
    print(AUDITS_OUT)
    print(f"audits={len(audits)}")
    print(ITEMS_OUT)
    print(f"items={len(items)}")


if __name__ == "__main__":
    main()

