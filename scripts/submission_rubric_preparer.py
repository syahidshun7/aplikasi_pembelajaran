#!/usr/bin/env python3

import argparse
import json
import re
import sys
from pathlib import Path

SUBJECTS = {
    "technology",
    "database",
    "backend",
    "laravel",
    "programming",
    "web_development",
    "software_engineering",
    "mathematics",
    "biology",
    "chemistry",
    "physics",
    "language",
    "history",
    "economics",
    "other",
}
Q_TYPES = {
    "definition",
    "explanation",
    "reasoning",
    "comparison",
    "calculation",
    "implementation",
    "analysis",
    # Backward compatibility
    "essay",
    "multiple_choice",
}
STRATEGIES = {
    "exact_match",
    "semantic_similarity",
    "deep_semantic_evaluation",
    "ai_rubric_evaluation",
    "rule_based_evaluation",
    # Backward compatibility
    "rule_engine",
}


def normalize_text(value):
    return re.sub(r"\s+", " ", str(value or "").strip())


def slug_tag(value):
    normalized = re.sub(r"[^a-z0-9]+", "_", normalize_text(value).lower()).strip("_")
    return normalized[:40]


def normalize_number(value):
    if isinstance(value, bool):
        return None
    if isinstance(value, int) and value > 0:
        return value
    text = normalize_text(value)
    return int(text) if text.isdigit() and int(text) > 0 else None


def normalize_expected_concepts(raw_rows):
    if not isinstance(raw_rows, list):
        return []

    rows = []
    for row in raw_rows:
        if not isinstance(row, dict):
            continue
        concept = normalize_text(row.get("concept")).lower()
        if concept == "":
            continue
        try:
            weight = max(0.0, float(row.get("weight") or 0.0))
        except Exception:
            weight = 0.0
        rows.append({"concept": concept, "weight": weight})

    rows = rows[:7]
    if not rows:
        return []

    total_weight = sum(float(row["weight"]) for row in rows)
    if total_weight <= 0:
        even_weight = round(1 / len(rows), 3)
        normalized_rows = [{"concept": row["concept"], "weight": even_weight} for row in rows]
    else:
        normalized_rows = [
            {"concept": row["concept"], "weight": round(float(row["weight"]) / total_weight, 3)}
            for row in rows
        ]

    delta = round(1.0 - sum(float(row["weight"]) for row in normalized_rows), 3)
    normalized_rows[0]["weight"] = round(max(0.0, float(normalized_rows[0]["weight"]) + delta), 3)
    return normalized_rows


def normalize_semantic_tags(raw_tags, expected_concepts):
    if isinstance(raw_tags, list):
        tags = [slug_tag(item) for item in raw_tags if normalize_text(item) != ""]
    else:
        tags = []

    if not tags:
        tags = [slug_tag(row.get("concept", "")) for row in expected_concepts]

    normalized = []
    seen = set()
    for tag in tags:
        if tag == "" or tag in seen:
            continue
        seen.add(tag)
        normalized.append(tag)
        if len(normalized) >= 8:
            break

    return normalized


def normalize_criteria(raw_criteria):
    if not isinstance(raw_criteria, list):
        return [], ["incomplete_criteria"]

    merged = {}
    for row in raw_criteria:
        if not isinstance(row, dict):
            continue
        name = normalize_text(row.get("name"))
        if name == "":
            continue
        try:
            weight = max(0.0, float(row.get("weight") or 0))
        except Exception:
            weight = 0.0
        key = name.lower()
        merged[key] = {"name": name, "weight": merged.get(key, {}).get("weight", 0.0) + weight}

    rows = list(merged.values())
    if not rows:
        return [], ["incomplete_criteria"]

    total = sum(float(row["weight"]) for row in rows)
    if total <= 0:
        base = round(100 / len(rows))
        result = [{"name": row["name"], "weight": base} for row in rows]
        result[0]["weight"] += 100 - sum(int(item["weight"]) for item in result)
        return result, ["incomplete_criteria"]

    weights = [int(round((float(row["weight"]) / total) * 100)) for row in rows]
    weights[0] += 100 - sum(weights)
    return [{"name": rows[i]["name"], "weight": max(0, int(weights[i]))} for i in range(len(rows))], []


def normalize_rubric(payload):
    rubric = payload.get("rubric") if isinstance(payload.get("rubric"), dict) else {}
    rubric_id = rubric.get("rubric_id")
    if isinstance(rubric_id, str):
        rubric_id = normalize_text(rubric_id) or None
    if not isinstance(rubric_id, (int, str)):
        rubric_id = None
    criteria, criteria_warnings = normalize_criteria(rubric.get("criteria"))
    warnings = list(criteria_warnings)
    if rubric_id is None:
        warnings.extend(["missing_selected_rubric", "low_rubric_confidence"])
    return {"rubric_id": rubric_id, "criteria": criteria}, sorted(set(warnings))


def build_item(raw_item, selected_rubric, reference_answers, allowed_feedback_length):
    warnings = []
    question_number = normalize_number(raw_item.get("question_number"))
    question = normalize_text(raw_item.get("question"))
    student_answer = normalize_text(raw_item.get("student_answer") or raw_item.get("answer"))

    reference_answer = None
    if question_number is not None:
        lookup = normalize_text(reference_answers.get(str(question_number), ""))
        reference_answer = lookup if lookup else None
    if reference_answer is None:
        warnings.append("missing_reference_answer")

    subject = normalize_text(raw_item.get("subject") or "other").lower()
    if subject in {"general"}:
        subject = "software_engineering"
    if subject not in SUBJECTS:
        subject = "software_engineering"

    question_type = normalize_text(raw_item.get("question_type") or "analysis").lower()
    if question_type == "essay":
        question_type = "explanation"
    if question_type == "multiple_choice":
        question_type = "definition"
    if question_type not in Q_TYPES:
        question_type = "analysis"

    strategy = normalize_text(raw_item.get("evaluation_strategy") or "semantic_similarity").lower()
    if strategy == "rule_engine":
        strategy = "rule_based_evaluation"
    if strategy not in STRATEGIES:
        strategy = "semantic_similarity"

    difficulty = normalize_text(raw_item.get("difficulty") or raw_item.get("complexity") or "medium").lower()
    if difficulty not in {"low", "medium", "high"}:
        difficulty = "medium"

    expected_concepts = normalize_expected_concepts(raw_item.get("expected_concepts"))
    semantic_tags = normalize_semantic_tags(raw_item.get("semantic_tags") or raw_item.get("tags"), expected_concepts)

    constraints = {
        "score_range": [0, 100],
        "allowed_feedback_length": max(50, min(2000, int(allowed_feedback_length))),
        "strict_json_output": True,
        "no_extra_explanation": True,
    }

    payload_status = "ready"
    if question == "" and student_answer == "":
        payload_status = "failed"
        warnings.append("empty_semantic_item")
    elif not selected_rubric.get("criteria"):
        payload_status = "partial"
        warnings.append("incomplete_criteria")

    item = {
        "question_number": question_number,
        "question": question,
        "student_answer": student_answer,
        "reference_answer": reference_answer,
        "subject": subject,
        "question_type": question_type,
        "difficulty": difficulty,
        "evaluation_strategy": strategy,
        "expected_concepts": expected_concepts,
        "semantic_tags": semantic_tags,
        "selected_rubric": {
            "rubric_id": selected_rubric.get("rubric_id"),
            "criteria": selected_rubric.get("criteria", []),
        },
        "constraints": constraints,
        "evaluation_payload": {
            "question": question,
            "student_answer": student_answer,
            "reference_answer": reference_answer,
            "subject": subject,
            "question_type": question_type,
            "difficulty": difficulty,
            "evaluation_strategy": strategy,
            "expected_concepts": expected_concepts,
            "semantic_tags": semantic_tags,
            "rubric": {"criteria": selected_rubric.get("criteria", [])},
            "constraints": {
                "score_range": constraints["score_range"],
                "allowed_feedback_length": constraints["allowed_feedback_length"],
                "strict_json_output": True,
            },
        },
        "payload_status": payload_status,
    }
    return item, warnings


def prepare(payload):
    submission_id = str(payload.get("submission_id") or "")
    raw_items = payload.get("items") if isinstance(payload.get("items"), list) else []
    max_items = max(1, int(payload.get("max_items") or 500))
    allowed_feedback_length = max(50, int(payload.get("allowed_feedback_length") or 200))
    reference_answers = payload.get("reference_answers") if isinstance(payload.get("reference_answers"), dict) else {}
    reference_answers = {normalize_text(k): normalize_text(v) for k, v in reference_answers.items() if normalize_text(k) != ""}

    selected_rubric, warnings = normalize_rubric(payload)
    items = []
    for raw in raw_items[:max_items]:
        if not isinstance(raw, dict):
            warnings.append("invalid_semantic_item")
            continue
        item, item_warnings = build_item(raw, selected_rubric, reference_answers, allowed_feedback_length)
        items.append(item)
        warnings.extend(item_warnings)

    if len(raw_items) > max_items:
        warnings.append("rubric_items_truncated")
    if not items:
        warnings.append("missing_semantic_items")

    warning_set = sorted(set(warnings))
    if not items:
        status = "failed"
    else:
        payload_statuses = {str(item.get("payload_status") or "failed") for item in items}
        severe = {"missing_selected_rubric", "low_rubric_confidence", "incomplete_criteria", "invalid_semantic_item"}
        status = "success"
        if "failed" in payload_statuses or "partial" in payload_statuses or severe.intersection(warning_set):
            status = "partial"

    return {
        "submission_id": submission_id,
        "items": items,
        "warnings": warning_set,
        "rubric_preparation_status": status,
        "next_stage": "ai_evaluation",
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    args = parser.parse_args()

    try:
        payload = json.loads(Path(args.input).read_text(encoding="utf-8-sig"))
        result = prepare(payload)
    except Exception as exception:
        result = {
            "submission_id": "",
            "items": [],
            "warnings": ["python_rubric_preparation_exception", exception.__class__.__name__],
            "rubric_preparation_status": "failed",
            "next_stage": "ai_evaluation",
        }

    sys.stdout.write(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
