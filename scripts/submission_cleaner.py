#!/usr/bin/env python3

import argparse
import json
import re
import sys
import unicodedata
from pathlib import Path
from typing import Any


OCR_CORRECTIONS: list[tuple[re.Pattern[str], str]] = [
    (re.compile(r"\blnternet\b"), "Internet"),
    (re.compile(r"\blndonesia\b"), "Indonesia"),
    (re.compile(r"\bf0tosintesis\b", re.IGNORECASE), "fotosintesis"),
    (re.compile(r"\balg0ritma\b", re.IGNORECASE), "algoritma"),
    (re.compile(r"\bpe\s+mbelajaran\b", re.IGNORECASE), "pembelajaran"),
    (re.compile(r"\bma\s+hasiswa\b", re.IGNORECASE), "mahasiswa"),
    (re.compile(r"\bkom\s+puter\b", re.IGNORECASE), "komputer"),
]

GARBAGE_PATTERNS: list[re.Pattern[str]] = [
    re.compile(r"^\s*page\s+\d+\s+(?:of|/)\s+\d+\s*$", re.IGNORECASE),
    re.compile(r"^\s*halaman\s+\d+\s+(?:dari|/)\s+\d+\s*$", re.IGNORECASE),
    re.compile(r"^\s*generated\s+by\s+pdf.*$", re.IGNORECASE),
    re.compile(r"^\s*generated\s+by\s+.*$", re.IGNORECASE),
    re.compile(r"^\s*scanned\s+(?:on|at)\s+.*$", re.IGNORECASE),
    re.compile(r"^\s*scan\s+timestamp\s*[:\-].*$", re.IGNORECASE),
    re.compile(r"^\s*\[PAGE\s+\d+\]\s*$", re.IGNORECASE),
]

QUESTION_OR_SECTION_PATTERN = re.compile(
    r"^\s*(?:\d{1,3}[\.)]|[A-Z][\.)]|[-*•]|Q\d{1,3}\b|Soal\s+\d{1,3}\b|Pertanyaan\s+\d{1,3}\b|question_uuid\s*:|\[TASK_BANK_ANSWERS\])",
    re.IGNORECASE,
)

ID_STOPWORDS = {
    "yang", "dan", "atau", "adalah", "dengan", "untuk", "dari", "pada", "internet", "jaringan",
    "komputer", "belajar", "pembelajaran", "siswa", "jawaban", "soal", "jelaskan", "apa", "bagaimana",
}
EN_STOPWORDS = {
    "the", "and", "or", "is", "are", "with", "for", "from", "internet", "network", "computer",
    "learning", "student", "answer", "question", "explain", "what", "how", "why",
}


def remove_invisible_noise(text: str) -> tuple[str, int]:
    removed = 0
    output: list[str] = []
    for character in text:
        category = unicodedata.category(character)
        if character in {"\t", "\r", "\x00"}:
            output.append(" " if character == "\t" else "\n" if character == "\r" else "")
            removed += 1
            continue
        if category in {"Cc", "Cf"} and character != "\n":
            removed += 1
            continue
        output.append(character)
    return "".join(output), removed


def normalize_unicode(text: str) -> str:
    replacements = {
        "“": '"',
        "”": '"',
        "„": '"',
        "‘": "'",
        "’": "'",
        "–": "-",
        "—": "-",
        "−": "-",
        "…": "...",
        "\u00a0": " ",
    }
    for source, target in replacements.items():
        text = text.replace(source, target)
    return unicodedata.normalize("NFKC", text)


def normalize_spacing(text: str) -> str:
    lines = []
    for line in text.split("\n"):
        line = re.sub(r"[ \f\v]+", " ", line)
        lines.append(line.strip())
    return "\n".join(lines).strip()


def remove_garbage_lines(text: str) -> tuple[str, int]:
    removed = 0
    kept_lines: list[str] = []
    for line in text.split("\n"):
        stripped = line.strip()
        if stripped and any(pattern.match(stripped) for pattern in GARBAGE_PATTERNS):
            removed += 1
            continue
        kept_lines.append(line)
    return "\n".join(kept_lines), removed


def apply_ocr_corrections(text: str) -> tuple[str, int]:
    count = 0
    for pattern, replacement in OCR_CORRECTIONS:
        text, changes = pattern.subn(replacement, text)
        count += changes
    return text, count


def should_keep_line_break(previous_line: str, next_line: str) -> bool:
    if not previous_line.strip() or not next_line.strip():
        return True
    if QUESTION_OR_SECTION_PATTERN.match(next_line):
        return True
    if re.match(r"^\s*(?:question_uuid\s*:|\[TASK_BANK_ANSWERS\])", previous_line, re.IGNORECASE):
        return True
    if previous_line.rstrip().endswith((".", "?", "!", ":")):
        return True
    if len(previous_line.strip()) <= 3:
        return True
    return False


def normalize_line_breaks(text: str) -> tuple[str, int]:
    lines = text.split("\n")
    paragraphs: list[str] = []
    current = ""
    fixed = 0

    for line in lines:
        stripped = line.strip()
        if stripped == "":
            if current:
                paragraphs.append(current.strip())
                current = ""
            if paragraphs and paragraphs[-1] != "":
                paragraphs.append("")
            continue

        if not current:
            current = stripped
            continue

        if should_keep_line_break(current, stripped):
            paragraphs.append(current.strip())
            current = stripped
        else:
            current = f"{current.rstrip()} {stripped}"
            fixed += 1

    if current:
        paragraphs.append(current.strip())

    output = "\n".join(paragraphs)
    output = re.sub(r"\n{3,}", "\n\n", output)
    return output.strip(), fixed


def detect_language(text: str) -> str:
    words = re.findall(r"\b[\w']+\b", text.lower())
    if not words:
        return "unknown"
    id_score = sum(1 for word in words if word in ID_STOPWORDS)
    en_score = sum(1 for word in words if word in EN_STOPWORDS)
    if id_score == 0 and en_score == 0:
        return "unknown"
    return "id" if id_score >= en_score else "en"


def build_warnings(raw_text: str, clean_text: str, payload: dict[str, Any]) -> list[str]:
    warnings: list[str] = []
    extraction_warnings = payload.get("extraction_warnings") or []
    if isinstance(extraction_warnings, list):
        if "low_ocr_confidence" in extraction_warnings:
            warnings.append("low_confidence_cleanup")
        if "empty_page_detected" in extraction_warnings:
            warnings.append("broken_layout_detected")

    ocr_confidence = payload.get("ocr_confidence")
    if isinstance(ocr_confidence, (int, float)) and ocr_confidence < 0.5:
        warnings.append("low_confidence_cleanup")

    replacement_character_count = raw_text.count("�")
    if replacement_character_count >= 3:
        warnings.append("heavy_ocr_noise")

    line_count = max(1, len(raw_text.splitlines()))
    short_lines = sum(1 for line in raw_text.splitlines() if 0 < len(line.strip()) <= 3)
    if line_count >= 10 and short_lines / line_count > 0.35:
        warnings.append("broken_layout_detected")

    if not clean_text.strip():
        warnings.append("empty_clean_text")

    return sorted(set(warnings))


def clean_text(payload: dict[str, Any]) -> dict[str, Any]:
    submission_id = str(payload.get("submission_id") or "")
    raw_text = str(payload.get("raw_text") or "")
    max_chars = max(1000, int(payload.get("max_chars") or 200000))

    changes = {
        "noise_removed": 0,
        "ocr_corrections": 0,
        "line_break_fixed": 0,
        "garbage_removed": 0,
    }

    text = raw_text[:max_chars]
    text, noise_removed = remove_invisible_noise(text)
    changes["noise_removed"] += noise_removed
    text = normalize_unicode(text)
    text = normalize_spacing(text)
    text, garbage_removed = remove_garbage_lines(text)
    changes["garbage_removed"] += garbage_removed
    text, ocr_corrections = apply_ocr_corrections(text)
    changes["ocr_corrections"] += ocr_corrections
    text, line_break_fixed = normalize_line_breaks(text)
    changes["line_break_fixed"] += line_break_fixed
    text = normalize_spacing(text)
    text = re.sub(r"\n{3,}", "\n\n", text).strip()

    warnings = build_warnings(raw_text, text, payload)
    if len(raw_text) > max_chars:
        warnings.append("clean_text_truncated")

    status = "success" if text else "failed"
    if status == "success" and warnings:
        status = "partial"

    return {
        "submission_id": submission_id,
        "clean_text": text,
        "language": detect_language(text),
        "cleaning_status": status,
        "changes_summary": changes,
        "warnings": sorted(set(warnings)),
        "next_stage": "structure_detection",
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    args = parser.parse_args()

    try:
        payload = json.loads(Path(args.input).read_text(encoding="utf-8"))
        result = clean_text(payload)
    except Exception as exception:
        result = {
            "submission_id": "",
            "clean_text": "",
            "language": "unknown",
            "cleaning_status": "failed",
            "changes_summary": {
                "noise_removed": 0,
                "ocr_corrections": 0,
                "line_break_fixed": 0,
                "garbage_removed": 0,
            },
            "warnings": ["python_cleaning_exception", exception.__class__.__name__],
            "next_stage": "structure_detection",
        }

    sys.stdout.write(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
