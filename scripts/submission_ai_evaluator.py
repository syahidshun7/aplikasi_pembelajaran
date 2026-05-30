#!/usr/bin/env python3

import argparse
import json
import os
import re
import sys
import urllib.request
import urllib.error
from pathlib import Path

EMPTY_ANSWER_VALUES = {
    "",
    "-",
    "...",
    "n/a",
    "na",
    "tidak tahu",
    "tidak tau",
    "belum tahu",
    "no answer",
}
GENERIC_MARKERS = {
    "intinya",
    "pokoknya",
    "dan lain lain",
    "dll",
    "secara umum",
    "kurang lebih",
}
STOPWORDS = {
    "yang", "dan", "atau", "dengan", "untuk", "pada", "dalam", "dari", "ke", "di",
    "itu", "ini", "the", "and", "or", "with", "from", "for", "that", "this",
}
DEFAULT_CRITERIA = [
    {"name": "Akurasi Konsep", "weight": 35},
    {"name": "Kelengkapan Jawaban", "weight": 30},
    {"name": "Alur Penjelasan", "weight": 20},
    {"name": "Konteks Teknis", "weight": 15},
]


def normalize_text(value):
    return re.sub(r"\s+", " ", str(value or "").strip())


def tokenize(text):
    return re.findall(r"[a-zA-Z0-9']+", normalize_text(text).lower())


def clamp(value, minimum, maximum):
    return max(minimum, min(maximum, value))


def to_int(value, default=0):
    try:
        if isinstance(value, bool):
            return default
        return int(round(float(value)))
    except Exception:
        return default


def to_float(value, default=0.0):
    try:
        if isinstance(value, bool):
            return default
        return float(value)
    except Exception:
        return default


def normalize_score_range(raw_range):
    raw_range = raw_range if isinstance(raw_range, list) and len(raw_range) == 2 else [0, 100]
    minimum = clamp(to_int(raw_range[0], 0), 0, 100)
    maximum = clamp(to_int(raw_range[1], 100), 0, 100)
    return [minimum, maximum] if minimum <= maximum else [maximum, minimum]


def clamp_score(score, score_range):
    minimum, maximum = normalize_score_range(score_range)
    return clamp(int(round(score)), minimum, maximum)


def normalize_criteria(raw_rows):
    rows = []
    if isinstance(raw_rows, list):
        for row in raw_rows:
            if not isinstance(row, dict):
                continue
            name = normalize_text(row.get("name"))
            if name == "":
                continue
            weight = max(0, to_int(row.get("weight"), 0))
            rows.append({"name": name, "weight": weight})

    if not rows:
        return [dict(item) for item in DEFAULT_CRITERIA]

    total = sum(int(row["weight"]) for row in rows)
    if total <= 0:
        return [dict(item) for item in DEFAULT_CRITERIA]

    normalized_weights = [int(round((row["weight"] / total) * 100)) for row in rows]
    normalized_weights[0] += 100 - sum(normalized_weights)

    normalized = []
    for index, row in enumerate(rows):
        normalized.append({"name": row["name"], "weight": max(0, min(100, int(normalized_weights[index])))})
    return normalized


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
        weight = max(0.0, to_float(row.get("weight"), 0.0))
        rows.append({"concept": concept, "weight": weight})

    rows = rows[:7]
    if not rows:
        return []

    total = sum(float(row["weight"]) for row in rows)
    if total <= 0:
        even_weight = round(1 / len(rows), 3)
        normalized = [{"concept": row["concept"], "weight": even_weight} for row in rows]
    else:
        normalized = [{"concept": row["concept"], "weight": round(float(row["weight"]) / total, 3)} for row in rows]

    delta = round(1.0 - sum(float(row["weight"]) for row in normalized), 3)
    normalized[0]["weight"] = round(max(0.0, min(1.0, float(normalized[0]["weight"]) + delta)), 3)
    return normalized


def normalize_semantic_tags(raw_tags):
    if not isinstance(raw_tags, list):
        return []
    tags = []
    seen = set()
    for tag in raw_tags:
        normalized = re.sub(r"[^a-z0-9]+", "_", normalize_text(tag).lower()).strip("_")
        if normalized == "" or normalized in seen:
            continue
        seen.add(normalized)
        tags.append(normalized)
        if len(tags) >= 8:
            break
    return tags


def is_empty_answer(answer):
    return normalize_text(answer).lower() in EMPTY_ANSWER_VALUES


def compute_token_overlap(student_tokens, reference_tokens):
    if not reference_tokens:
        return 0.0
    student_set = set(student_tokens) - STOPWORDS
    reference_set = set(reference_tokens) - STOPWORDS
    if not reference_set:
        return 0.5
    overlap = student_set & reference_set
    return len(overlap) / len(reference_set)


def compute_generic_penalty(student_tokens):
    lowered = " ".join(student_tokens)
    count = sum(1 for marker in GENERIC_MARKERS if marker in lowered)
    return min(0.15, count * 0.05)


def compute_concept_coverage(student_tokens, expected_concepts):
    if not expected_concepts:
        return 0.0
    student_text = " ".join(student_tokens)
    covered_weight = 0.0
    for entry in expected_concepts:
        concept = entry.get("concept", "")
        weight = to_float(entry.get("weight"), 0.0)
        concept_tokens = concept.split()
        if all(tok in student_text for tok in concept_tokens):
            covered_weight += weight
    return min(1.0, covered_weight)


def build_ai_prompt(item, criteria, expected_concepts, submission_context=None):
    question = normalize_text(item.get("question"))
    student_answer = normalize_text(item.get("student_answer"))
    reference_answer = normalize_text(item.get("reference_answer"))
    subject = normalize_text(item.get("subject") or "general")

    criteria_text = ", ".join(f'{c["name"]} (bobot {c["weight"]}%)' for c in criteria)
    concepts_text = ", ".join(f'{c["concept"]} ({c["weight"]:.0%})' for c in expected_concepts) if expected_concepts else "tidak ada"

    context_text = ""
    if isinstance(submission_context, dict):
        total = submission_context.get("total_items", 0)
        answered = submission_context.get("answered_items", 0)
        lang = submission_context.get("language", "unknown")
        context_text = f"KONTEKS SUBMISSION: {answered}/{total} soal dijawab, bahasa: {lang}\n"

    return (
        f"Kamu penilai jawaban siswa. Nilai jawaban berikut berdasarkan kriteria rubrik.\n\n"
        f"{context_text}"
        f"MATA PELAJARAN: {subject}\n"
        f"SOAL: {question}\n"
        f"JAWABAN SISWA: {student_answer}\n"
        f"JAWABAN REFERENSI: {reference_answer or 'tidak tersedia'}\n"
        f"KRITERIA RUBRIK: {criteria_text}\n"
        f"KONSEP KUNCI YANG DIHARAPKAN: {concepts_text}\n\n"
        f"Balas HANYA JSON valid (tanpa markdown) dengan format:\n"
        f'{{"score":0-100,"criteria_scores":[{{"name":"...","score":0-100,"reason":"..."}}],'
        f'"strengths":["..."],"weaknesses":["..."],"feedback":"...","evaluation_confidence":0.0-1.0}}'
    )


def call_ai_api(prompt):
    api_key = os.environ.get("GEMINI_API_KEY", "").strip()
    base_url = os.environ.get("GEMINI_BASE_URL", "https://generativelanguage.googleapis.com/v1beta/openai").strip()
    model = os.environ.get("GEMINI_MODEL", "gemini-2.0-flash-lite").strip()

    if not api_key:
        return None

    endpoint = base_url.rstrip("/") + "/chat/completions"
    payload = json.dumps({
        "model": model,
        "messages": [
            {"role": "system", "content": "Kamu penilai jawaban siswa. Balas HANYA JSON valid tanpa markdown."},
            {"role": "user", "content": prompt},
        ],
        "temperature": 0.1,
        "response_format": {"type": "json_object"},
    }).encode("utf-8")

    req = urllib.request.Request(endpoint, data=payload, method="POST", headers={
        "Content-Type": "application/json",
        "Authorization": f"Bearer {api_key}",
    })

    try:
        with urllib.request.urlopen(req, timeout=25) as resp:
            body = json.loads(resp.read().decode("utf-8"))
            content = body.get("choices", [{}])[0].get("message", {}).get("content", "")
            return json.loads(content) if content.strip() else None
    except Exception:
        return None


def parse_ai_response(ai_result, criteria, score_range):
    if not isinstance(ai_result, dict):
        return None

    score = clamp_score(to_int(ai_result.get("score"), -1), score_range)
    if score < 0:
        return None

    criteria_scores = []
    raw_cs = ai_result.get("criteria_scores") if isinstance(ai_result.get("criteria_scores"), list) else []
    for row in raw_cs:
        if not isinstance(row, dict):
            continue
        name = normalize_text(row.get("name"))
        if name == "":
            continue
        criteria_scores.append({
            "name": name,
            "score": max(0, min(100, to_int(row.get("score"), 0))),
            "reason": normalize_text(row.get("reason")),
        })

    feedback = normalize_text(ai_result.get("feedback"))
    if feedback == "":
        return None

    strengths = [normalize_text(s) for s in (ai_result.get("strengths") or []) if normalize_text(s)]
    weaknesses = [normalize_text(w) for w in (ai_result.get("weaknesses") or []) if normalize_text(w)]
    confidence = max(0.3, min(0.99, to_float(ai_result.get("evaluation_confidence"), 0.8)))

    return {
        "score": score,
        "criteria_scores": criteria_scores if criteria_scores else [{"name": c["name"], "score": score, "reason": feedback} for c in criteria],
        "strengths": strengths[:5],
        "weaknesses": weaknesses[:5],
        "feedback": feedback,
        "evaluation_confidence": round(confidence, 2),
    }


def evaluate_item(item, submission_context=None):
    question = normalize_text(item.get("question"))
    student_answer = normalize_text(item.get("student_answer"))
    reference_answer = normalize_text(item.get("reference_answer"))
    constraints = item.get("constraints") if isinstance(item.get("constraints"), dict) else {}
    score_range = constraints.get("score_range", [0, 100])
    selected_rubric = item.get("selected_rubric") if isinstance(item.get("selected_rubric"), dict) else {}
    criteria = normalize_criteria(selected_rubric.get("criteria") if isinstance(selected_rubric.get("criteria"), list) else [])
    expected_concepts = normalize_expected_concepts(item.get("expected_concepts") if isinstance(item.get("expected_concepts"), list) else [])

    if is_empty_answer(student_answer):
        criteria_scores = [{"name": c["name"], "score": 0, "reason": "Jawaban kosong."} for c in criteria]
        return {
            "score": 0,
            "criteria_scores": criteria_scores,
            "strengths": [],
            "weaknesses": ["Jawaban belum diisi oleh siswa."],
            "feedback": "Jawaban belum diisi.",
            "evaluation_confidence": 0.95,
            "is_empty": True,
        }

    # Try AI API first
    prompt = build_ai_prompt(item, criteria, expected_concepts, submission_context)
    ai_result = call_ai_api(prompt)
    parsed = parse_ai_response(ai_result, criteria, score_range) if ai_result else None

    if parsed:
        parsed["is_empty"] = False
        return parsed

    # Fallback: rule-based scoring
    student_tokens = tokenize(student_answer)
    reference_tokens = tokenize(reference_answer) if reference_answer else []

    if reference_tokens:
        overlap = compute_token_overlap(student_tokens, reference_tokens)
    else:
        overlap = min(1.0, len(student_tokens) / 12) * 0.6

    concept_coverage = compute_concept_coverage(student_tokens, expected_concepts)

    penalty = compute_generic_penalty(student_tokens)
    length_factor = min(1.0, len(student_tokens) / max(1, len(reference_tokens) if reference_tokens else 8))

    if expected_concepts:
        raw_score = (overlap * 0.4 + concept_coverage * 0.3 + length_factor * 0.2 + 0.1) - penalty
    else:
        raw_score = (overlap * 0.6 + length_factor * 0.3 + 0.1) - penalty
    raw_score = max(0.05, min(1.0, raw_score))
    final_score = clamp_score(raw_score * 100, score_range)

    criteria_scores = []
    for c in criteria:
        weight = c["weight"]
        c_score = clamp(int(round(final_score * (0.85 + (weight / 500)))), 0, 100)
        reason = "Cukup sesuai." if c_score >= 50 else "Perlu perbaikan."
        criteria_scores.append({"name": c["name"], "score": c_score, "reason": reason})

    strengths = []
    weaknesses = []
    if final_score >= 70:
        strengths.append("Jawaban menunjukkan pemahaman konsep yang baik.")
    elif final_score >= 40:
        strengths.append("Terdapat upaya menjawab sesuai konteks.")
        weaknesses.append("Perlu pendalaman konsep lebih lanjut.")
    else:
        weaknesses.append("Jawaban belum sesuai dengan konsep yang diharapkan.")

    if reference_tokens and overlap < 0.3:
        weaknesses.append("Kurang mencakup kata kunci penting dari referensi.")

    feedback = "Jawaban sudah baik dan sesuai." if final_score >= 70 else (
        "Jawaban cukup namun perlu dilengkapi." if final_score >= 40 else
        "Jawaban perlu diperbaiki agar sesuai konsep."
    )

    confidence = round(0.7 + (overlap * 0.2) + (length_factor * 0.05), 2)
    confidence = max(0.3, min(0.95, confidence))

    return {
        "score": final_score,
        "criteria_scores": criteria_scores,
        "strengths": strengths,
        "weaknesses": weaknesses,
        "feedback": feedback,
        "evaluation_confidence": confidence,
        "is_empty": False,
    }


def evaluate(payload):
    submission_id = str(payload.get("submission_id") or "")
    raw_items = payload.get("items") if isinstance(payload.get("items"), list) else []
    max_items = max(1, to_int(payload.get("max_items"), 500))
    submission_context = payload.get("submission_context") if isinstance(payload.get("submission_context"), dict) else None

    items = []
    warnings = []
    has_empty = False

    for item in raw_items[:max_items]:
        if not isinstance(item, dict):
            warnings.append("invalid_item_payload")
            continue
        result = evaluate_item(item, submission_context)
        if result.get("is_empty"):
            has_empty = True
        result.pop("is_empty", None)
        items.append(result)

    if len(raw_items) > max_items:
        warnings.append("ai_evaluation_items_truncated")

    if not items:
        status = "failed"
        warnings.append("no_evaluable_items")
    elif has_empty:
        status = "partial"
    else:
        status = "success"

    return {
        "submission_id": submission_id,
        "items": items,
        "warnings": sorted(set(warnings)),
        "ai_evaluation_status": status,
        "next_stage": "evaluation_quality_review",
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    args = parser.parse_args()

    try:
        payload = json.loads(Path(args.input).read_text(encoding="utf-8-sig"))
        result = evaluate(payload)
    except Exception as exc:
        result = {
            "submission_id": "",
            "items": [],
            "warnings": ["python_ai_evaluator_exception", exc.__class__.__name__],
            "ai_evaluation_status": "failed",
            "next_stage": "evaluation_quality_review",
        }

    sys.stdout.write(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

