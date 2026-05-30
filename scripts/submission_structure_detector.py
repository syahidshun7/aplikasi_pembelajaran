#!/usr/bin/env python3

import argparse
import json
import re
import sys
from pathlib import Path
from typing import Any

DOCUMENT_PATTERNS = {"numbered_list", "qa_format", "essay_block", "mixed"}
ANSWER_STATUSES = {"filled", "empty", "unclear"}

NUMBERED_BOUNDARY_PATTERNS: list[tuple[str, re.Pattern[str]]] = [
    ("numbered", re.compile(r"^\s*(?P<number>\d{1,3})[\.)]\s*(?P<body>.*)$")),
    ("soal", re.compile(r"^\s*Soal\s+(?P<number>\d{1,3})\s*[:\.)\-]?\s*(?P<body>.*)$", re.IGNORECASE)),
    ("q_number", re.compile(r"^\s*Q\s*(?P<number>\d{1,3})\s*[:\.)\-]?\s*(?P<body>.*)$", re.IGNORECASE)),
]

QUESTION_LABEL_PATTERN = re.compile(
    r"^\s*(?:Pertanyaan|Question)\s*(?P<number>\d{1,3})?\s*[:\.)\-]\s*(?P<body>.*)$",
    re.IGNORECASE,
)
ANSWER_LABEL_PATTERN = re.compile(r"^\s*(?:(?:Jawaban|Jawab|Answer|Ans)|A)\s*[:\.)\-]\s*(?P<body>.*)$", re.IGNORECASE)
INLINE_ANSWER_LABEL_PATTERN = re.compile(r"\b(?:Jawaban|Jawab|Answer|Ans)\s*:\s*", re.IGNORECASE)
QUESTION_UUID_PATTERN = re.compile(r"^\s*question_uuid\s*:\s*(?P<uuid>.+?)\s*$", re.IGNORECASE)

QUESTION_CUE_PATTERN = re.compile(
    r"\b(?:apa|apakah|jelaskan|sebutkan|mengapa|kenapa|bagaimana|hitung|tuliskan|uraikan|"
    r"what|is|are|explain|describe|why|how|calculate|state|mention)\b",
    re.IGNORECASE,
)

INSTRUCTION_PATTERNS: list[re.Pattern[str]] = [
    re.compile(r"^\s*(?:nama|name|kelas|class|nis|nim|tanggal|date)\s*:", re.IGNORECASE),
    re.compile(r"^\s*(?:instruksi|petunjuk|directions?|instructions?)\s*[:\-]", re.IGNORECASE),
    re.compile(r"^\s*(?:kerjakan|jawablah|pilihlah|bacalah)\b.*", re.IGNORECASE),
    re.compile(r"^\s*(?:answer|complete|choose)\b.*\b(?:questions?|all)\b.*", re.IGNORECASE),
]

EMPTY_ANSWER_VALUES = {
    "",
    "-",
    "--",
    "---",
    "—",
    "...",
    "…",
    "….",
    "n/a",
    "na",
    "tidak tahu",
    "tidak tau",
    "belum tahu",
    "kosong",
    "no answer",
}


def normalize_lines(text: str) -> list[str]:
    return [line.rstrip() for line in text.replace("\r\n", "\n").replace("\r", "\n").split("\n")]


def compact_text(lines: list[str]) -> str:
    output: list[str] = []
    previous_blank = False
    for line in lines:
        stripped = line.strip()
        if stripped == "":
            if output and not previous_blank:
                output.append("")
            previous_blank = True
            continue
        output.append(stripped)
        previous_blank = False
    while output and output[-1] == "":
        output.pop()
    return "\n".join(output).strip()


def match_numbered_boundary(line: str) -> dict[str, Any] | None:
    for style, pattern in NUMBERED_BOUNDARY_PATTERNS:
        match = pattern.match(line)
        if not match:
            continue
        return {
            "style": style,
            "number": int(match.group("number")),
            "body": match.group("body").strip(),
        }
    return None


def match_question_label(line: str) -> dict[str, Any] | None:
    match = QUESTION_LABEL_PATTERN.match(line)
    if not match:
        return None
    raw_number = (match.group("number") or "").strip()
    return {
        "number": int(raw_number) if raw_number.isdigit() else None,
        "body": match.group("body").strip(),
    }


def match_answer_label(line: str) -> str | None:
    match = ANSWER_LABEL_PATTERN.match(line)
    if not match:
        return None
    return match.group("body").strip()


def is_instruction_line(line: str) -> bool:
    stripped = line.strip()
    return bool(stripped and any(pattern.match(stripped) for pattern in INSTRUCTION_PATTERNS))


def looks_like_question(text: str) -> bool:
    stripped = text.strip()
    return stripped.endswith("?") or bool(QUESTION_CUE_PATTERN.search(stripped))


def answer_status(answer: str) -> tuple[str, bool]:
    normalized = re.sub(r"\s+", " ", answer.strip().lower())
    is_empty = normalized in EMPTY_ANSWER_VALUES
    return ("empty" if is_empty else "filled"), is_empty


def build_item(question_number: int | None, question: str | None, answer: str) -> dict[str, Any]:
    status, is_empty = answer_status(answer)
    return {
        "question_number": question_number,
        "question": question.strip() if isinstance(question, str) and question.strip() else None,
        "answer": answer.strip(),
        "answer_status": status,
        "is_empty": is_empty,
    }


def split_inline_answer_label(text: str) -> tuple[str, str] | None:
    match = INLINE_ANSWER_LABEL_PATTERN.search(text)
    if not match:
        return None
    return text[: match.start()].strip(), text[match.end() :].strip()


def split_question_mark(text: str) -> tuple[str, str] | None:
    index = text.find("?")
    if index < 0:
        return None
    question = text[: index + 1].strip()
    answer = text[index + 1 :].strip()
    return question, answer


def split_instructions(lines: list[str]) -> tuple[list[str], list[str]]:
    instructions: list[str] = []
    evaluation_lines: list[str] = []
    evaluation_started = False

    for line in lines:
        stripped = line.strip()
        starts_evaluation = bool(
            match_numbered_boundary(line)
            or match_question_label(line)
            or match_answer_label(line) is not None
            or QUESTION_UUID_PATTERN.match(line)
            or stripped == "[TASK_BANK_ANSWERS]"
        )

        if not evaluation_started and stripped == "":
            continue

        if not evaluation_started and is_instruction_line(line):
            instructions.append(stripped)
            continue

        if starts_evaluation:
            evaluation_started = True

        evaluation_lines.append(line)

    return evaluation_lines, instructions


def parse_task_bank_blocks(lines: list[str], task_questions: dict[str, dict[str, Any]]) -> tuple[list[dict[str, Any]], list[str]]:
    warnings: list[str] = []
    items: list[dict[str, Any]] = []
    current_uuid: str | None = None
    current_answer_lines: list[str] = []

    def flush() -> None:
        nonlocal current_uuid, current_answer_lines
        if current_uuid is None:
            current_answer_lines = []
            return
        question_meta = task_questions.get(current_uuid, {})
        question = str(question_meta.get("question") or "").strip() or None
        question_number = question_meta.get("question_number")
        if question is None:
            warnings.append("missing_question_detected")
        items.append(build_item(question_number if isinstance(question_number, int) else None, question, compact_text(current_answer_lines)))
        current_uuid = None
        current_answer_lines = []

    for line in lines:
        stripped = line.strip()
        if stripped == "[TASK_BANK_ANSWERS]":
            continue
        match = QUESTION_UUID_PATTERN.match(line)
        if match:
            flush()
            current_uuid = match.group("uuid").strip()
            current_answer_lines = []
            continue
        if current_uuid is not None:
            current_answer_lines.append(line)
        elif stripped:
            warnings.append("mixed_layout_detected")

    flush()
    return items, warnings


def parse_qa_blocks(lines: list[str]) -> tuple[list[dict[str, Any]], list[str]]:
    warnings: list[str] = []
    items: list[dict[str, Any]] = []
    question_lines: list[str] = []
    answer_lines: list[str] = []
    question_number: int | None = None
    mode: str | None = None

    def flush() -> None:
        nonlocal question_lines, answer_lines, question_number, mode
        if not question_lines and not answer_lines:
            return
        question = compact_text(question_lines) or None
        if question is None:
            warnings.append("missing_question_detected")
        items.append(build_item(question_number, question, compact_text(answer_lines)))
        question_lines = []
        answer_lines = []
        question_number = None
        mode = None

    for line in lines:
        question_match = match_question_label(line)
        if question_match:
            flush()
            question_number = question_match["number"]
            question_lines = [question_match["body"]] if question_match["body"] else []
            answer_lines = []
            mode = "question"
            continue

        answer_body = match_answer_label(line)
        if answer_body is not None:
            if mode is None:
                question_lines = []
            answer_lines = [answer_body] if answer_body else []
            mode = "answer"
            continue

        if mode == "question":
            question_lines.append(line)
        elif mode == "answer":
            answer_lines.append(line)
        elif line.strip():
            warnings.append("mixed_layout_detected")

    flush()
    return items, warnings


def parse_numbered_block(block: dict[str, Any]) -> tuple[dict[str, Any], list[str]]:
    warnings: list[str] = []
    question_number = block.get("number") if isinstance(block.get("number"), int) else None
    lines = [line for line in block.get("lines", []) if isinstance(line, str)]
    non_blank_lines = [line.strip() for line in lines if line.strip()]

    if not non_blank_lines:
        return build_item(question_number, None, ""), ["missing_question_detected"]

    for index, line in enumerate(non_blank_lines):
        answer_body = match_answer_label(line)
        if answer_body is None:
            continue
        question = compact_text(non_blank_lines[:index]) or None
        answer = compact_text(([answer_body] if answer_body else []) + non_blank_lines[index + 1 :])
        if question is None:
            warnings.append("missing_question_detected")
        return build_item(question_number, question, answer), warnings

    block_text = compact_text(non_blank_lines)
    inline_split = split_inline_answer_label(block_text)
    if inline_split:
        question, answer = inline_split
        if question == "":
            warnings.append("missing_question_detected")
        return build_item(question_number, question or None, answer), warnings

    if len(non_blank_lines) == 1:
        question_answer_split = split_question_mark(non_blank_lines[0])
        if question_answer_split and question_answer_split[1] != "":
            return build_item(question_number, question_answer_split[0], question_answer_split[1]), warnings
        if looks_like_question(non_blank_lines[0]):
            return build_item(question_number, non_blank_lines[0], ""), warnings
        warnings.append("missing_question_detected")
        return build_item(question_number, None, non_blank_lines[0]), warnings

    first_line = non_blank_lines[0]
    rest_lines = non_blank_lines[1:]
    question_answer_split = split_question_mark(first_line)
    if question_answer_split and question_answer_split[1] != "":
        answer = compact_text([question_answer_split[1]] + rest_lines)
        return build_item(question_number, question_answer_split[0], answer), warnings

    if not looks_like_question(first_line):
        warnings.append("ambiguous_question_boundary")
    return build_item(question_number, first_line, compact_text(rest_lines)), warnings


def parse_numbered_blocks(lines: list[str]) -> tuple[list[dict[str, Any]], list[str], set[str]]:
    warnings: list[str] = []
    styles: set[str] = set()
    blocks: list[dict[str, Any]] = []
    current_block: dict[str, Any] | None = None

    for line in lines:
        boundary = match_numbered_boundary(line)
        if boundary:
            styles.add(str(boundary["style"]))
            if current_block is not None:
                blocks.append(current_block)
            current_block = {
                "number": boundary["number"],
                "lines": [boundary["body"]] if boundary["body"] else [],
            }
            continue

        if current_block is not None:
            current_block["lines"].append(line)
        elif line.strip():
            warnings.append("mixed_layout_detected")

    if current_block is not None:
        blocks.append(current_block)

    items: list[dict[str, Any]] = []
    for block in blocks:
        item, block_warnings = parse_numbered_block(block)
        items.append(item)
        warnings.extend(block_warnings)

    return items, warnings, styles


def parse_essay_block(lines: list[str]) -> tuple[list[dict[str, Any]], list[str]]:
    text = compact_text([line for line in lines if not is_instruction_line(line)])
    if text == "":
        return [], ["missing_question_detected"]
    return [build_item(None, None, text)], []


def detect_document_pattern(lines: list[str], styles: set[str], has_question_label: bool, has_answer_label: bool, has_task_bank: bool) -> str:
    numbered_count = sum(1 for line in lines if match_numbered_boundary(line))
    if has_task_bank:
        return "qa_format"
    if len(styles) > 1:
        return "mixed"
    if numbered_count > 0 and (has_question_label or has_answer_label):
        return "qa_format"
    if numbered_count > 0:
        return "numbered_list"
    if has_question_label or has_answer_label:
        return "qa_format"
    return "essay_block"


def normalize_task_questions(payload: dict[str, Any]) -> dict[str, dict[str, Any]]:
    raw_questions = payload.get("task_questions") or []
    if not isinstance(raw_questions, list):
        return {}

    output: dict[str, dict[str, Any]] = {}
    for index, question in enumerate(raw_questions, start=1):
        if not isinstance(question, dict):
            continue
        uuid = str(question.get("uuid") or "").strip()
        if uuid == "":
            continue
        raw_number = question.get("question_number")
        question_number = int(raw_number) if isinstance(raw_number, int) or str(raw_number).isdigit() else index
        output[uuid] = {
            "question_number": question_number,
            "question": str(question.get("question") or "").strip(),
        }
    return output


def detect_structure(payload: dict[str, Any]) -> dict[str, Any]:
    submission_id = str(payload.get("submission_id") or "")
    clean_text = str(payload.get("clean_text") or "")
    max_chars = max(1000, int(payload.get("max_chars") or 200000))
    text = clean_text[:max_chars]

    raw_lines = normalize_lines(text)
    lines, instruction_blocks = split_instructions(raw_lines)
    has_task_bank = any(line.strip() == "[TASK_BANK_ANSWERS]" or QUESTION_UUID_PATTERN.match(line) for line in lines)
    has_question_label = any(match_question_label(line) for line in lines)
    has_answer_label = any(match_answer_label(line) is not None for line in lines)
    pattern = detect_document_pattern(lines, {str(match_numbered_boundary(line)["style"]) for line in lines if match_numbered_boundary(line)}, has_question_label, has_answer_label, has_task_bank)

    warnings: list[str] = []
    if len(clean_text) > max_chars:
        warnings.append("structure_text_truncated")

    if has_task_bank:
        items, parser_warnings = parse_task_bank_blocks(lines, normalize_task_questions(payload))
        warnings.extend(parser_warnings)
    elif pattern == "qa_format" and not any(match_numbered_boundary(line) for line in lines):
        items, parser_warnings = parse_qa_blocks(lines)
        warnings.extend(parser_warnings)
    elif any(match_numbered_boundary(line) for line in lines):
        items, parser_warnings, styles = parse_numbered_blocks(lines)
        warnings.extend(parser_warnings)
        if len(styles) > 1 and "mixed_layout_detected" not in warnings:
            warnings.append("mixed_layout_detected")
    else:
        items, parser_warnings = parse_essay_block(lines)
        warnings.extend(parser_warnings)

    if not items:
        warnings.append("missing_question_detected")

    normalized_warnings = sorted(set(warnings))
    status = "success" if items else "failed"
    if status == "success" and any(warning in normalized_warnings for warning in ["ambiguous_question_boundary", "missing_question_detected", "mixed_layout_detected", "structure_text_truncated"]):
        status = "partial"

    return {
        "submission_id": submission_id,
        "document_pattern": pattern if pattern in DOCUMENT_PATTERNS else "mixed",
        "items": items,
        "instruction_blocks": instruction_blocks,
        "warnings": normalized_warnings,
        "structure_detection_status": status,
        "next_stage": "semantic_enrichment",
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    args = parser.parse_args()

    try:
        payload = json.loads(Path(args.input).read_text(encoding="utf-8-sig"))
        result = detect_structure(payload)
    except Exception as exception:
        result = {
            "submission_id": "",
            "document_pattern": "mixed",
            "items": [],
            "instruction_blocks": [],
            "warnings": ["python_structure_detection_exception", exception.__class__.__name__],
            "structure_detection_status": "failed",
            "next_stage": "semantic_enrichment",
        }

    sys.stdout.write(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
