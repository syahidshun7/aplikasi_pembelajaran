#!/usr/bin/env python3

import argparse
import json
import math
import re
import sys
from pathlib import Path

def normalize_text(value):
    return re.sub(r'\s+', ' ', str(value or '').strip())


def to_int(value, default=0):
    if isinstance(value, bool):
        return default
    if isinstance(value, int):
        return value
    if isinstance(value, float):
        if math.isnan(value):
            return default
        return int(round(value))
    text = normalize_text(value)
    if text == '':
        return default
    try:
        return int(round(float(text)))
    except Exception:
        return default


def clamp_score(score, score_range):
    if not isinstance(score_range, list) or len(score_range) != 2:
        score_range = [0, 100]

    minimum = to_int(score_range[0], 0)
    maximum = to_int(score_range[1], 100)
    minimum = max(0, min(100, minimum))
    maximum = max(0, min(100, maximum))

    if minimum > maximum:
        minimum, maximum = maximum, minimum

    normalized = max(minimum, min(maximum, to_int(score, minimum)))
    return normalized, minimum, maximum


def normalize_criteria_scores(raw_rows):
    rows = []
    if not isinstance(raw_rows, list):
        return rows

    for row in raw_rows:
        if not isinstance(row, dict):
            continue
        name = normalize_text(row.get('name'))
        if name == '':
            continue
        score = max(0, min(100, to_int(row.get('score'), 0)))
        reason = normalize_text(row.get('reason'))
        rows.append({'name': name, 'score': score, 'reason': reason})

    return rows


def normalize_rubric_criteria(raw_rows):
    criteria = {}
    if not isinstance(raw_rows, list):
        return criteria

    for row in raw_rows:
        if not isinstance(row, dict):
            continue
        name = normalize_text(row.get('name'))
        if name == '':
            continue
        weight = max(0, min(100, to_int(row.get('weight'), 0)))
        criteria[name.lower()] = weight

    return criteria


def detect_feedback_quality(feedback):
    lowered = normalize_text(feedback).lower()
    if lowered == '':
        return 'low'

    toxic_terms = {
        'bodoh',
        'tolol',
        'stupid',
        'idiot',
        'goblok',
        'anjing',
        'bangsat',
    }
    if any(term in lowered for term in toxic_terms):
        return 'low'

    generic_low = {
        'saya tidak tahu',
        'i do not know',
        'tidak tahu',
        'no comment',
    }
    if lowered in generic_low:
        return 'low'

    if len(lowered) < 18:
        return 'normal'

    strong_phrases = [
        'sudah sangat baik',
        'cukup benar',
        'masih kurang',
        'belum sesuai',
        'perlu penguatan',
        'good answer',
        'needs improvement',
    ]
    if any(phrase in lowered for phrase in strong_phrases):
        return 'high'

    return 'normal'


def semantic_feedback_consistency(score, feedback):
    lowered = normalize_text(feedback).lower()
    if lowered == '':
        return False

    positive_markers = [
        'sangat baik',
        'sudah baik',
        'bagus',
        'tepat',
        'benar',
        'lengkap',
        'excellent',
        'great',
    ]
    negative_markers = [
        'kurang',
        'belum',
        'tidak relevan',
        'lemah',
        'salah',
        'poor',
        'incorrect',
        'empty',
    ]

    has_positive = any(marker in lowered for marker in positive_markers)
    has_negative = any(marker in lowered for marker in negative_markers)

    if score <= 35 and has_positive and not has_negative:
        return False
    if score >= 80 and has_negative and not has_positive:
        return False

    return True


def confidence_from_signals(validated, warnings, quality, semantic_consistency, retry_count):
    confidence = 0.93 if validated else 0.35

    for warning in warnings:
        if warning in {'missing_required_field', 'invalid_json_payload'}:
            confidence -= 0.22
        elif warning in {'score_out_of_range', 'invalid_score_type'}:
            confidence -= 0.16
        elif warning in {'criteria_inconsistency_detected', 'criteria_over_weight_detected'}:
            confidence -= 0.18
        elif warning == 'semantic_feedback_mismatch':
            confidence -= 0.2
        elif warning == 'feedback_quality_low':
            confidence -= 0.14
        else:
            confidence -= 0.06

    if quality == 'high':
        confidence += 0.03
    elif quality == 'low':
        confidence -= 0.08

    if not semantic_consistency:
        confidence -= 0.05

    confidence -= min(0.24, retry_count * 0.08)
    return round(max(0.05, min(0.99, confidence)), 2)


def validate_item(raw_item, max_retries):
    warnings = []

    if not isinstance(raw_item, dict):
        return {
            'validated': False,
            'final_score': 0,
            'normalized_score': 0,
            'criteria_validation': {
                'consistent': False,
                'total_criteria_score': 0,
            },
            'feedback_validation': {
                'quality': 'low',
                'semantic_consistency': False,
            },
            'confidence': 0.05,
            'retry_count': min(max(0, int(max_retries)), 1),
            'requires_manual_review': True,
            'warnings': ['invalid_json_payload'],
            'final_feedback': 'Hasil evaluasi tidak valid.',
            'validation_status': 'failed',
        }, ['invalid_json_payload']

    required_fields = ['score', 'criteria_scores', 'feedback']
    missing = [field for field in required_fields if field not in raw_item]
    if missing:
        warnings.append('missing_required_field')

    constraints = raw_item.get('constraints') if isinstance(raw_item.get('constraints'), dict) else {}
    score_range = constraints.get('score_range', [0, 100])

    raw_score = raw_item.get('score', 0)
    parsed_score = to_int(raw_score, 0)
    if isinstance(raw_score, str) and normalize_text(raw_score) != '' and not re.match(r'^-?\d+(\.\d+)?$', normalize_text(raw_score)):
        warnings.append('invalid_score_type')

    normalized_score, score_min, score_max = clamp_score(parsed_score, score_range)
    if parsed_score < score_min or parsed_score > score_max:
        warnings.append('score_out_of_range')

    criteria_scores = normalize_criteria_scores(raw_item.get('criteria_scores'))
    if not criteria_scores:
        warnings.append('missing_required_field')

    rubric_criteria = normalize_rubric_criteria(raw_item.get('rubric_criteria'))
    total_criteria_score = sum(int(row.get('score', 0)) for row in criteria_scores)

    criteria_consistent = abs(total_criteria_score - normalized_score) <= 15
    if not criteria_consistent:
        warnings.append('criteria_inconsistency_detected')

    if rubric_criteria:
        for row in criteria_scores:
            key = normalize_text(row.get('name')).lower()
            if key in rubric_criteria and int(row.get('score', 0)) > int(rubric_criteria[key]):
                warnings.append('criteria_over_weight_detected')
                criteria_consistent = False
                break

    feedback = normalize_text(raw_item.get('feedback'))
    feedback_quality = detect_feedback_quality(feedback)
    if feedback_quality == 'low':
        warnings.append('feedback_quality_low')

    semantic_consistency = semantic_feedback_consistency(normalized_score, feedback)
    if not semantic_consistency:
        warnings.append('semantic_feedback_mismatch')

    severe_warning = any(
        warning in {
            'missing_required_field',
            'invalid_json_payload',
            'invalid_score_type',
            'score_out_of_range',
            'criteria_inconsistency_detected',
            'semantic_feedback_mismatch',
        }
        for warning in warnings
    )

    retry_count = 0
    if severe_warning:
        retry_count = 1
    if 'missing_required_field' in warnings and (feedback == '' or not criteria_scores):
        retry_count = min(max(0, int(max_retries)), 2)
    retry_count = min(max(0, int(max_retries)), retry_count)

    validated = 'invalid_json_payload' not in warnings
    confidence = confidence_from_signals(validated, warnings, feedback_quality, semantic_consistency, retry_count)

    requires_manual_review = (
        confidence < 0.6
        or retry_count >= 2
        or 'invalid_json_payload' in warnings
        or ('criteria_inconsistency_detected' in warnings and 'semantic_feedback_mismatch' in warnings)
    )

    final_feedback = feedback if feedback != '' else 'Hasil feedback evaluasi kosong dan perlu dicek ulang.'
    warning_set = sorted(set(warnings))

    if not validated:
        status = 'failed'
    elif requires_manual_review or warning_set:
        status = 'partial'
    else:
        status = 'success'

    item_result = {
        'validated': validated,
        'final_score': normalized_score,
        'normalized_score': normalized_score,
        'criteria_validation': {
            'consistent': criteria_consistent,
            'total_criteria_score': total_criteria_score,
        },
        'feedback_validation': {
            'quality': feedback_quality,
            'semantic_consistency': semantic_consistency,
        },
        'confidence': confidence,
        'retry_count': retry_count,
        'requires_manual_review': requires_manual_review,
        'warnings': warning_set,
        'final_feedback': final_feedback,
        'validation_status': status,
    }

    return item_result, warning_set


def validate(payload):
    submission_id = str(payload.get('submission_id') or '')
    raw_items = payload.get('items') if isinstance(payload.get('items'), list) else []
    max_items = max(1, to_int(payload.get('max_items'), 500))
    max_retries = max(0, min(3, to_int(payload.get('max_retries'), 2)))

    warnings = []
    items = []

    for raw in raw_items[:max_items]:
        result, item_warnings = validate_item(raw, max_retries)
        items.append(result)
        warnings.extend(item_warnings)

    if len(raw_items) > max_items:
        warnings.append('post_eval_items_truncated')

    if not items:
        warnings.append('missing_ai_evaluation_items')

    warning_set = sorted(set(warnings))

    if not items:
        status = 'failed'
    else:
        statuses = {str(item.get('validation_status') or 'failed') for item in items}
        if statuses == {'success'} and not warning_set:
            status = 'success'
        elif 'failed' in statuses:
            status = 'failed'
        else:
            status = 'partial'

    return {
        'submission_id': submission_id,
        'items': items,
        'warnings': warning_set,
        'post_evaluation_validation_status': status,
        'next_stage': 'result_finalization',
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--input', required=True)
    args = parser.parse_args()

    try:
        payload = json.loads(Path(args.input).read_text(encoding='utf-8-sig'))
        result = validate(payload)
    except Exception as exception:
        result = {
            'submission_id': '',
            'items': [],
            'warnings': ['python_post_evaluation_validation_exception', exception.__class__.__name__],
            'post_evaluation_validation_status': 'failed',
            'next_stage': 'result_finalization',
        }

    sys.stdout.write(json.dumps(result, ensure_ascii=False, separators=(',', ':')))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
