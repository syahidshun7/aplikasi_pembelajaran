#!/usr/bin/env python3

import argparse
import json
import re
import sys
from pathlib import Path
from typing import Any

LANGUAGE_VALUES = {"id", "en", "other"}
SUBJECT_VALUES = {
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
}
QUESTION_TYPE_VALUES = {
    "definition",
    "explanation",
    "reasoning",
    "comparison",
    "calculation",
    "implementation",
    "analysis",
}
DIFFICULTY_VALUES = {"low", "medium", "high"}
EVALUATION_STRATEGY_VALUES = {
    "exact_match",
    "semantic_similarity",
    "deep_semantic_evaluation",
    "ai_rubric_evaluation",
    "rule_based_evaluation",
}
ANSWER_LENGTH_VALUES = {"empty", "short", "medium", "long"}
ANSWER_QUALITY_VALUES = {"normal", "low_confidence", "spam_like", "repetitive"}

ID_STOPWORDS = {
    "yang", "dan", "atau", "adalah", "dengan", "untuk", "dari", "pada", "dalam", "ke", "apa", "itu", "ini",
    "mengapa", "kenapa", "bagaimana", "jelaskan", "sebutkan", "uraikan", "bandingkan", "anda", "cara", "agar",
    "konteks", "sistem", "khususnya", "berdasarkan", "dengan", "tanpa", "secara",
}
EN_STOPWORDS = {
    "the", "and", "or", "is", "are", "with", "for", "from", "what", "why", "how", "explain", "describe",
    "compare", "analyze", "implement", "using", "within", "based", "system", "specific",
}
GENERIC_CONCEPT_WORDS = {
    "question", "answer", "soal", "jawaban", "concept", "konsep", "analysis", "analisis",
    "reasoning", "compare", "comparison", "calculation", "implementation", "explanation", "explain", "jelaskan",
    "bagaimana", "cara", "agar", "anda", "menggunakan", "gunakan", "khususnya", "konteks",
}

SUBJECT_PATTERNS: dict[str, list[str]] = {
    "database": [
        r"\bdatabase\b", r"\bdb\b", r"\bskema\b", r"\bschema\b", r"\btable\b", r"\btabel\b",
        r"\bindex\b", r"\bforeign key\b", r"\bquery\b", r"\bmigration\b", r"\bsoft deletes?\b",
    ],
    "laravel": [
        r"\blaravel\b", r"\beloquent\b", r"\bmiddleware\b", r"\bartisan\b", r"\bwhereDate\b", r"\bvalidator\b",
    ],
    "backend": [
        r"\bbackend\b", r"\bapi\b", r"\bendpoint\b", r"\bcontroller\b", r"\bmodel\b", r"\bservice\b",
        r"\brequest\b", r"\bresponse\b", r"\bauth\b", r"\bvalidation\b",
    ],
    "programming": [
        r"\bphp\b", r"\bcarbon\b", r"\bfunction\b", r"\bclass\b", r"\bmethod\b", r"\bregex\b",
        r"\bcode\b", r"\bsyntax\b", r"\blogic\b", r"\bstrtotime\b",
    ],
    "web_development": [
        r"\bweb\b", r"\bfrontend\b", r"\bbackend\b", r"\bmiddleware\b", r"\baccess\b", r"\broute\b",
        r"\babsensi\b", r"\battendance\b", r"\bjam kerja\b", r"\bworking hours\b",
    ],
    "software_engineering": [
        r"\barchitecture\b", r"\bdesain\b", r"\bdesign\b", r"\bscalability\b", r"\bmaintainability\b",
        r"\btesting\b", r"\brefactor\b", r"\baudit\b",
    ],
    "technology": [
        r"\binternet\b", r"\bjaringan\b", r"\bnetwork\b", r"\bserver\b", r"\bsoftware\b", r"\bhardware\b",
    ],
    "mathematics": [
        r"\bhitung\b", r"\bcalculate\b", r"\bpersamaan\b", r"\bequation\b", r"\bpecahan\b", r"\bfraction\b",
        r"\baljabar\b", r"\balgebra\b", r"\bgeometri\b", r"\bgeometry\b",
    ],
    "biology": [
        r"\bfotosintesis\b", r"\bphotosynthesis\b", r"\bsel\b", r"\bcell\b", r"\bdna\b", r"\bgenetik\b",
        r"\bekosistem\b", r"\becosystem\b",
    ],
    "chemistry": [
        r"\bkimia\b", r"\bchemistry\b", r"\batom\b", r"\bmolekul\b", r"\breaction\b", r"\breaksi\b",
        r"\basam\b", r"\bbasa\b", r"\bph\b", r"\bstoikiometri\b",
    ],
    "physics": [
        r"\bfisika\b", r"\bphysics\b", r"\bgaya\b", r"\bforce\b", r"\benergi\b", r"\bvelocity\b",
        r"\bacceleration\b", r"\bmomentum\b", r"\bgelombang\b",
    ],
    "language": [
        r"\bbahasa\b", r"\blanguage\b", r"\bgrammar\b", r"\bkalimat\b", r"\bparagraf\b",
        r"\bsinonim\b", r"\bantonim\b", r"\btranslation\b",
    ],
    "history": [
        r"\bsejarah\b", r"\bhistory\b", r"\brevolusi\b", r"\bkolonial\b", r"\bkerajaan\b", r"\bperang\b",
    ],
    "economics": [
        r"\bekonomi\b", r"\beconomics\b", r"\bdemand\b", r"\bsupply\b", r"\binflasi\b", r"\bmarket\b",
        r"\bgdp\b", r"\bprofit\b",
    ],
}

SUBJECT_PRIORITY = [
    "database",
    "laravel",
    "backend",
    "programming",
    "web_development",
    "software_engineering",
    "technology",
    "mathematics",
    "biology",
    "chemistry",
    "physics",
    "language",
    "history",
    "economics",
]

TECHNICAL_CONCEPT_PATTERNS: list[tuple[str, str]] = [
    (r"\bdatabase schema\b|\bskema database\b", "database schema"),
    (r"\battendances?\b|\babsensi\b", "attendance table"),
    (r"\bcheck[\s_-]?in\b", "check in"),
    (r"\bcheck[\s_-]?out\b", "check out"),
    (r"\bquery\b", "query efficiency"),
    (r"\bindex\b", "indexing"),
    (r"\bforeign key\b", "foreign key"),
    (r"\bunique\b", "unique constraint"),
    (r"\bvalidation\b|\bvalidasi\b", "input validation"),
    (r"\bdouble check[\s_-]?in\b|\bduplicate\b", "duplicate prevention"),
    (r"\bcarbon\b", "carbon"),
    (r"\bdate\(\)\b|\bphp date\b|\bfungsi date\b", "php date function"),
    (r"\bdurasi\b|\bduration\b", "duration calculation"),
    (r"\btimezone\b", "timezone handling"),
    (r"\bmiddleware\b", "middleware"),
    (r"\baccess\b|\bakses\b", "access control"),
    (r"\bjam kerja\b|\bworking hours\b", "working hours"),
    (r"\bsoft deletes?\b", "soft deletes"),
    (r"\baudit\b", "audit trail"),
    (r"\blaravel\b", "laravel"),
]

TAG_PATTERNS: list[tuple[str, str]] = [
    (r"\blaravel\b", "laravel"),
    (r"\bdatabase\b|\bskema\b|\bschema\b|\btabel\b|\btable\b", "database"),
    (r"\battendances?\b|\babsensi\b", "attendance_system"),
    (r"\bvalidation\b|\bvalidasi\b", "validation"),
    (r"\bmiddleware\b", "middleware"),
    (r"\bsoft deletes?\b", "soft_deletes"),
    (r"\bcheck[\s_-]?in\b", "check_in"),
    (r"\bcheck[\s_-]?out\b", "check_out"),
    (r"\bquery\b", "query_optimization"),
    (r"\bcarbon\b", "carbon"),
    (r"\bdate\(\)\b|\bfungsi date\b", "php_date"),
    (r"\bdurasi\b|\bduration\b", "duration_calculation"),
    (r"\baccess\b|\bakses\b", "access_control"),
    (r"\bjam kerja\b|\bworking hours\b", "working_hours"),
]

EMPTY_ANSWER_VALUES = {
    "",
    "-",
    "--",
    "---",
    "...",
    "…",
    "n/a",
    "na",
    "tidak tahu",
    "tidak tau",
    "belum tahu",
    "no answer",
}


def tokenize(text: str) -> list[str]:
    return re.findall(r"[a-zA-ZÀ-ÿ0-9']+", text.lower())


def normalize_spaces(text: str) -> str:
    return re.sub(r"\s+", " ", text.strip())


def slug_tag(value: str) -> str:
    value = re.sub(r"[^a-z0-9]+", "_", value.lower()).strip("_")
    return value[:40]


def detect_language(text: str) -> tuple[str, float]:
    words = tokenize(text)
    if not words:
        return "other", 0.3

    id_score = sum(1 for word in words if word in ID_STOPWORDS)
    en_score = sum(1 for word in words if word in EN_STOPWORDS)
    if id_score == 0 and en_score == 0:
        return "other", 0.4

    total = max(1, id_score + en_score)
    if id_score >= en_score:
        return "id", min(0.97, 0.6 + (id_score - en_score) / total)
    return "en", min(0.97, 0.6 + (en_score - id_score) / total)


def detect_subject(text: str, question: str) -> tuple[str, float, list[str]]:
    lowered = text.lower()
    scores: dict[str, int] = {subject: 0 for subject in SUBJECT_VALUES}

    for subject, patterns in SUBJECT_PATTERNS.items():
        score = 0
        for pattern in patterns:
            matches = re.findall(pattern, lowered, re.IGNORECASE)
            if matches:
                score += 2 if " " in pattern or "\\b" in pattern else 1
        scores[subject] = score

    max_score = max(scores.values()) if scores else 0
    if max_score <= 0:
        q = question.lower()
        if re.search(r"\b(hitung|calculate|equation|persamaan|pecahan|angka)\b", q):
            return "mathematics", 0.56, []
        if re.search(r"\b(fotosintesis|photosynthesis|dna|sel|cell)\b", q):
            return "biology", 0.56, []
        if re.search(r"\b(laravel|php|database|api|middleware|validation|kode|code)\b", q):
            return "programming", 0.58, []
        return "software_engineering", 0.52, []

    candidates = [subject for subject, score in scores.items() if score == max_score]
    candidates_sorted = sorted(candidates, key=lambda subject: SUBJECT_PRIORITY.index(subject))
    chosen = candidates_sorted[0]
    confidence = min(0.98, 0.62 + (max_score / max(3, sum(scores.values()) or 1)))
    return chosen, confidence, []


def is_mcq_pattern(question: str) -> bool:
    option_markers = len(re.findall(r"(?:^|\s)[A-E][\.)]\s", question, re.IGNORECASE))
    lowered = question.lower()
    return option_markers >= 2 or bool(re.search(r"\b(true|false|pilih|choose)\b", lowered))


def detect_question_type(question: str) -> tuple[str, float, list[str]]:
    q = question.lower().strip()

    if q and (
        re.search(r"\b(hitung|calculate|berapa|jumlahkan|kurangkan|kali|bagi|tentukan hasil)\b", q)
        or re.search(r"[0-9]\s*[-+*/=]\s*[0-9]", q)
    ):
        return "calculation", 0.92, []

    if q and re.search(r"\b(bandingkan|perbedaan|compare|versus|vs)\b", q):
        return "comparison", 0.89, []

    if q and re.search(r"\b(implementasi|implementation|rancang|susun|build|develop|design|tulis kode|code)\b", q):
        return "implementation", 0.88, []

    if q and re.search(r"\b(analisis|analysis|evaluate|evaluasi|kritisi|dampak|assess)\b", q):
        return "analysis", 0.88, []

    if q and re.search(r"\b(apa itu|what is|definisi|define)\b", q):
        return "definition", 0.9, []

    if q and re.search(r"\b(mengapa|kenapa|why|bagaimana|how|alasan)\b", q):
        return "reasoning", 0.85, []

    if q and re.search(r"\b(jelaskan|explain|describe|uraikan|paparkan)\b", q):
        return "explanation", 0.83, []

    if q and is_mcq_pattern(question):
        return "definition", 0.72, ["mcq_pattern_detected"]

    return "analysis", 0.56, ["ambiguous_question_type_detection"]


def detect_difficulty(question: str, question_type: str, subject: str) -> str:
    q = question.lower().strip()
    words = tokenize(question)
    word_count = len(words)

    technical_depth = 0
    technical_depth += 1 if re.search(r"\b(validasi|validation|middleware|index|foreign key|query|migration|timezone|audit|soft deletes?)\b", q) else 0
    technical_depth += 1 if re.search(r"\b(rancang|design|implement|analisis|evaluate|bandingkan|compare)\b", q) else 0
    technical_depth += 1 if subject in {"database", "backend", "laravel", "programming", "web_development", "software_engineering"} else 0

    if question_type in {"analysis", "implementation", "reasoning"} and technical_depth >= 2:
        return "high"
    if question_type == "comparison" and technical_depth >= 2:
        return "high"
    if question_type == "calculation":
        return "low" if word_count <= 12 else "medium"
    if question_type == "definition":
        return "low" if word_count <= 12 else "medium"
    if question_type == "explanation":
        return "medium" if technical_depth <= 1 else "high"

    if word_count >= 18 or technical_depth >= 3:
        return "high"
    if word_count <= 8:
        return "low"
    return "medium"


def detect_answer_length(answer: str) -> str:
    normalized = normalize_spaces(answer).lower()
    if normalized in EMPTY_ANSWER_VALUES:
        return "empty"

    count = len(tokenize(answer))
    if count == 0:
        return "empty"
    if count <= 6:
        return "short"
    if count <= 30:
        return "medium"
    return "long"


def detect_answer_quality(answer: str, answer_length: str, question_type: str, difficulty: str) -> str:
    normalized = normalize_spaces(answer)
    if answer_length == "empty":
        return "low_confidence"

    lowered = normalized.lower()
    if re.search(r"(.)\1{7,}", lowered):
        return "spam_like"

    words = tokenize(lowered)
    if len(words) >= 10:
        unique_ratio = len(set(words)) / len(words)
        if unique_ratio < 0.4:
            return "repetitive"

    if question_type in {"explanation", "comparison", "reasoning", "analysis", "implementation"} and answer_length == "short":
        return "low_confidence"

    if difficulty == "high" and answer_length in {"short", "empty"}:
        return "low_confidence"

    return "normal"


def choose_evaluation_strategy(question: str, question_type: str, difficulty: str) -> str:
    q = question.lower()

    if is_mcq_pattern(question):
        return "exact_match"

    if question_type == "calculation":
        return "rule_based_evaluation"

    explicit_code_or_validation = bool(
        re.search(r"\b(validasi|validation|regex|unique|constraint|query|schema|index|foreign key|middleware|soft deletes?|carbon|strtotime|php|laravel)\b", q)
        or re.search(r"\$[a-zA-Z_]|->|::", question)
    )
    if explicit_code_or_validation:
        return "rule_based_evaluation"

    if question_type == "definition" and len(tokenize(question)) <= 12:
        return "semantic_similarity"

    if question_type in {"reasoning", "comparison", "analysis"}:
        return "deep_semantic_evaluation"

    if question_type in {"implementation", "explanation"}:
        return "ai_rubric_evaluation"

    return "semantic_similarity"


def extract_expected_concepts(question: str) -> list[str]:
    lowered = question.lower()
    concepts: list[str] = []

    for pattern, concept in TECHNICAL_CONCEPT_PATTERNS:
        if re.search(pattern, lowered, re.IGNORECASE):
            concepts.append(concept)

    quoted_terms = re.findall(r"['\"]([a-zA-Z0-9_\-\s]{2,40})['\"]", question)
    for term in quoted_terms:
        cleaned = normalize_spaces(term.lower())
        if cleaned and cleaned not in GENERIC_CONCEPT_WORDS:
            if cleaned.endswith("s") and cleaned in {"attendances", "employees"}:
                cleaned = cleaned[:-1]
            concepts.append(cleaned)

    words = [word for word in tokenize(question) if len(word) >= 3 and word not in ID_STOPWORDS and word not in EN_STOPWORDS and word not in GENERIC_CONCEPT_WORDS and not word.isdigit()]
    technical_vocab = {
        "database", "schema", "table", "index", "query", "laravel", "middleware", "validation", "carbon",
        "duration", "timezone", "attendance", "check", "in", "out", "soft", "delete", "audit", "constraint",
        "php", "date", "backend", "api", "access", "working", "hours", "duplicate",
    }

    for index in range(len(words) - 1):
        first = words[index]
        second = words[index + 1]
        if first in technical_vocab or second in technical_vocab:
            phrase = f"{first} {second}"
            if phrase not in concepts:
                concepts.append(phrase)

    normalized: list[str] = []
    seen: set[str] = set()
    for concept in concepts:
        value = normalize_spaces(concept.lower())
        if value in {"", "anda", "agar", "efisien", "merancang", "cara", "bagaimana"}:
            continue
        if len(value) > 32:
            continue
        if value in seen:
            continue
        seen.add(value)
        normalized.append(value)
        if len(normalized) >= 7:
            break

    if not normalized:
        fallback = [word for word in words if word in technical_vocab]
        normalized = fallback[:3] if fallback else ["technical_concept"]

    return normalized[:7]


def weighted_concepts(concepts: list[str]) -> list[dict[str, Any]]:
    if not concepts:
        return []

    count = len(concepts)
    total = sum(range(1, count + 1))
    weights = [(count - index) / total for index in range(count)]
    rounded = [round(weight, 3) for weight in weights]

    delta = round(1.0 - sum(rounded), 3)
    rounded[0] = round(rounded[0] + delta, 3)

    return [
        {
            "concept": concepts[index],
            "weight": rounded[index],
        }
        for index in range(count)
    ]


def build_semantic_tags(subject: str, question: str, expected_concepts: list[dict[str, Any]]) -> list[str]:
    lowered = question.lower()
    tags: list[str] = [slug_tag(subject)]

    for pattern, tag in TAG_PATTERNS:
        if re.search(pattern, lowered, re.IGNORECASE):
            tags.append(tag)

    for row in expected_concepts:
        concept = normalize_spaces(str(row.get("concept") or ""))
        if concept == "":
            continue
        tags.append(slug_tag(concept))

    normalized: list[str] = []
    seen: set[str] = set()
    for tag in tags:
        if tag == "":
            continue
        if tag in seen:
            continue
        seen.add(tag)
        normalized.append(tag)
        if len(normalized) >= 8:
            break

    return normalized


def confidence_score(
    language: str,
    subject: str,
    question_type: str,
    difficulty: str,
    answer_quality: str,
    expected_concepts_count: int,
    local_warnings: list[str],
    subject_confidence: float,
    question_type_confidence: float,
    document_pattern: str = "mixed",
) -> float:
    score = 0.48
    score += 0.12 if language in {"id", "en"} else 0.03
    score += 0.14 if subject in {"database", "backend", "laravel", "programming", "web_development", "software_engineering", "technology"} else 0.08
    score += 0.09 if question_type in QUESTION_TYPE_VALUES else 0.04
    score += 0.06 if difficulty in {"medium", "high"} else 0.03

    if answer_quality == "normal":
        score += 0.1
    elif answer_quality == "low_confidence":
        score -= 0.1
    elif answer_quality == "repetitive":
        score -= 0.16
    elif answer_quality == "spam_like":
        score -= 0.24

    # document_pattern adjustment: structured formats are more reliable
    if document_pattern in {"qa_format", "numbered_list", "task_bank_uuid"}:
        score += 0.06
    elif document_pattern == "essay_block":
        score -= 0.04

    score += (subject_confidence - 0.5) * 0.15
    score += (question_type_confidence - 0.5) * 0.12
    score += min(0.08, expected_concepts_count * 0.015)
    score -= 0.06 * len(local_warnings)

    return round(max(0.05, min(0.99, score)), 2)


def enrich_item(item: dict[str, Any], document_pattern: str = "mixed") -> tuple[dict[str, Any], list[str]]:
    question_number_raw = item.get("question_number")
    question_number = int(question_number_raw) if isinstance(question_number_raw, int) or str(question_number_raw).isdigit() else None
    question = normalize_spaces(str(item.get("question") or ""))
    answer = normalize_spaces(str(item.get("answer") or ""))

    combined_text = normalize_spaces(f"{question} {answer}")
    language, _ = detect_language(combined_text)
    subject, subject_confidence, subject_warnings = detect_subject(combined_text, question)
    question_type, question_type_confidence, qtype_warnings = detect_question_type(question)
    difficulty = detect_difficulty(question, question_type, subject)
    answer_length = detect_answer_length(answer)
    answer_quality = detect_answer_quality(answer, answer_length, question_type, difficulty)
    evaluation_strategy = choose_evaluation_strategy(question, question_type, difficulty)
    expected_concepts = weighted_concepts(extract_expected_concepts(question))
    semantic_tags = build_semantic_tags(subject, question, expected_concepts)

    local_warnings = subject_warnings + qtype_warnings
    if answer_quality in {"spam_like", "repetitive"}:
        local_warnings.append("suspicious_answer_pattern")
    if normalize_spaces(answer).lower() in EMPTY_ANSWER_VALUES:
        local_warnings.append("empty_answer_detected")

    confidence = confidence_score(
        language=language,
        subject=subject,
        question_type=question_type,
        difficulty=difficulty,
        answer_quality=answer_quality,
        expected_concepts_count=len(expected_concepts),
        local_warnings=local_warnings,
        subject_confidence=subject_confidence,
        question_type_confidence=question_type_confidence,
        document_pattern=document_pattern,
    )

    normalized_subject = subject if subject in SUBJECT_VALUES else "software_engineering"
    normalized_question_type = question_type if question_type in QUESTION_TYPE_VALUES else "analysis"
    normalized_difficulty = difficulty if difficulty in DIFFICULTY_VALUES else "medium"
    normalized_strategy = evaluation_strategy if evaluation_strategy in EVALUATION_STRATEGY_VALUES else "semantic_similarity"
    normalized_answer_length = answer_length if answer_length in ANSWER_LENGTH_VALUES else "empty"
    normalized_answer_quality = answer_quality if answer_quality in ANSWER_QUALITY_VALUES else "low_confidence"

    payload = {
        "question_number": question_number,
        "question": question,
        "answer": answer,
        "language": language if language in LANGUAGE_VALUES else "other",
        "subject": normalized_subject,
        "question_type": normalized_question_type,
        "difficulty": normalized_difficulty,
        "evaluation_strategy": normalized_strategy,
        "expected_concepts": expected_concepts,
        "semantic_tags": semantic_tags,
        "confidence": confidence,
        # Backward-compatible aliases for next stages.
        "complexity": normalized_difficulty,
        "tags": semantic_tags,
        "answer_length": normalized_answer_length,
        "answer_quality": normalized_answer_quality,
    }

    return payload, local_warnings


def enrich(payload: dict[str, Any]) -> dict[str, Any]:
    submission_id = str(payload.get("submission_id") or "")
    raw_items = payload.get("items")
    max_items = max(1, int(payload.get("max_items") or 500))
    document_pattern = str(payload.get("document_pattern") or "mixed")

    if not isinstance(raw_items, list):
        raw_items = []

    warnings: list[str] = []
    items: list[dict[str, Any]] = []

    for raw_item in raw_items[:max_items]:
        if not isinstance(raw_item, dict):
            warnings.append("invalid_structure_item")
            continue
        enriched, item_warnings = enrich_item(raw_item, document_pattern)
        items.append(enriched)
        warnings.extend(item_warnings)

    if len(raw_items) > max_items:
        warnings.append("semantic_items_truncated")

    if not items:
        warnings.append("missing_structure_items")

    normalized_warnings = sorted(set(warnings))
    status = "success" if items else "failed"
    if status == "success" and normalized_warnings:
        status = "partial"

    return {
        "submission_id": submission_id,
        "items": items,
        "warnings": normalized_warnings,
        "semantic_enrichment_status": status,
        "next_stage": "rubric_preparation",
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    args = parser.parse_args()

    try:
        payload = json.loads(Path(args.input).read_text(encoding="utf-8-sig"))
        result = enrich(payload)
    except Exception as exception:
        result = {
            "submission_id": "",
            "items": [],
            "warnings": ["python_semantic_enrichment_exception", exception.__class__.__name__],
            "semantic_enrichment_status": "failed",
            "next_stage": "rubric_preparation",
        }

    sys.stdout.write(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

