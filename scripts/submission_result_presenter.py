#!/usr/bin/env python3

import argparse
import json
import re
import sys
from pathlib import Path


def normalize_text(value):
    return re.sub(r'\\s+', ' ', str(value or '').strip())


def clamp_score(value):
    try:
        score = int(round(float(value)))
    except Exception:
        score = 0
    return max(0, min(100, score))


def clamp_confidence(value):
    try:
        confidence = float(value)
    except Exception:
        confidence = 0.0
    return round(max(0.0, min(1.0, confidence)), 2)


def normalize_string_list(value):
    if not isinstance(value, list):
        return []

    output = []
    seen = set()
    for row in value:
        text = normalize_text(row)
        if text == '':
            continue
        key = text.lower()
        if key in seen:
            continue
        seen.add(key)
        output.append(text)

    return output


def beautify_feedback(feedback):
    base = normalize_text(feedback)
    if base == '':
        return 'Ringkasan feedback belum tersedia.'

    parts = [segment.strip() for segment in re.split(r'(?<=[.!?])\\s+', base) if segment.strip()]
    if not parts:
        parts = [base]

    deduped = []
    seen = set()
    for part in parts:
        key = part.lower()
        if key in seen:
            continue
        seen.add(key)
        deduped.append(part)

    compact = ' '.join(deduped[:2]).strip()
    if compact and compact[-1] not in {'.', '!', '?'}:
        compact += '.'
    return compact


def score_label(score):
    if score >= 85:
        return 'Excellent'
    if score >= 70:
        return 'Good'
    if score >= 50:
        return 'Fair'
    return 'Poor'


def confidence_level(confidence):
    if confidence >= 0.9:
        return 'high'
    if confidence >= 0.7:
        return 'medium'
    return 'low'


def infer_strengths(score, feedback_summary, source_strengths):
    strengths = normalize_string_list(source_strengths)
    if strengths:
        return strengths[:3]

    if score >= 85:
        return ['Jawaban menunjukkan penguasaan materi yang baik.']
    if score >= 70:
        return ['Jawaban sudah cukup sesuai dengan pertanyaan.']
    if 'benar' in feedback_summary.lower():
        return ['Konsep dasar sudah mulai terlihat.']
    return ['Terdapat upaya menjawab sesuai konteks soal.']


def infer_improvements(score, feedback_summary, source_weaknesses):
    improvements = normalize_string_list(source_weaknesses)
    if improvements:
        return improvements[:3]

    lowered = feedback_summary.lower()
    if 'detail' in lowered:
        return ['Tambahkan detail agar penjelasan lebih lengkap.']
    if score < 50:
        return ['Perlu menyesuaikan jawaban dengan konsep inti soal.']
    if score < 70:
        return ['Perdalam alasan atau contoh agar jawaban lebih kuat.']
    return ['Tambahkan elaborasi tambahan untuk meningkatkan kualitas jawaban.']


def normalize_criteria_breakdown(value):
    if not isinstance(value, list):
        return []

    output = []
    for row in value:
        if not isinstance(row, dict):
            continue
        name = normalize_text(row.get('name'))
        if name == '':
            continue
        output.append({
            'name': name,
            'score': clamp_score(row.get('score', 0)),
        })
    return output


def build_learning_tags(raw):
    tags = normalize_string_list(raw.get('tags'))
    if tags:
        return tags[:5]

    generated = []
    subject = normalize_text(raw.get('subject') or '').lower()
    question_type = normalize_text(raw.get('question_type') or '').lower()

    if subject not in {'', 'other'}:
        generated.append(subject)
    if question_type not in {'', 'other'}:
        generated.append(question_type)

    question = normalize_text(raw.get('question'))
    lowered_question = question.lower()
    keyword_map = {
        'internet': 'internet',
        'algoritma': 'algorithm',
        'fotosintesis': 'photosynthesis',
        'pecahan': 'fractions',
    }
    for keyword, tag in keyword_map.items():
        if keyword in lowered_question and tag not in generated:
            generated.append(tag)

    return generated[:5]


def detect_difficulty(raw, score):
    complexity = normalize_text(raw.get('complexity') or '').lower()
    if complexity in {'low', 'medium', 'high'}:
        return complexity

    question_type = normalize_text(raw.get('question_type') or '').lower()
    if question_type in {'reasoning', 'comparison', 'essay'} and score < 70:
        return 'high'
    if score >= 85:
        return 'low'
    return 'medium'


def prepare_item(raw):
    warnings = []
    if not isinstance(raw, dict):
        return {
            'presentation_status': 'failed',
            'submission_status': 'evaluated',
            'mentor_view': {
                'final_score': 0,
                'score_label': 'Poor',
                'feedback_summary': 'Data evaluasi tidak valid.',
                'strengths': [],
                'improvements': ['Perlu evaluasi ulang data hasil AI.'],
                'criteria_breakdown': [],
            },
            'confidence_display': {
                'value': 0.0,
                'level': 'low',
                'requires_manual_review': True,
            },
            'analytics': {
                'difficulty_level': 'medium',
                'common_mistakes': ['invalid_item_payload'],
                'learning_tags': [],
            },
            'history_record': {
                'saved': False,
            },
            'export_options': ['pdf', 'excel', 'json'],
            'notification': {
                'enabled': True,
                'message': 'AI evaluation completed with issues. Manual review required.',
            },
            'warnings': ['invalid_presentation_item'],
        }, ['invalid_presentation_item']

    score = clamp_score(raw.get('final_score', raw.get('normalized_score', raw.get('score', 0))))
    score_name = score_label(score)

    feedback_summary = beautify_feedback(raw.get('final_feedback', raw.get('feedback', '')))
    criteria_breakdown = normalize_criteria_breakdown(raw.get('criteria_scores'))
    if not criteria_breakdown:
        warnings.append('missing_criteria_breakdown')

    strengths = infer_strengths(score, feedback_summary, raw.get('strengths'))
    improvements = infer_improvements(score, feedback_summary, raw.get('weaknesses'))

    confidence = clamp_confidence(raw.get('confidence', raw.get('evaluation_confidence', 0.0)))
    level = confidence_level(confidence)

    requires_manual_review = bool(raw.get('requires_manual_review', False)) or level == 'low'
    if requires_manual_review:
        warnings.append('manual_review_required')

    difficulty_level = detect_difficulty(raw, score)
    learning_tags = build_learning_tags(raw)
    common_mistakes = improvements[:3] if improvements else ['Perlu perbaikan kualitas jawaban.']

    notification_message = (
        'AI evaluation completed with manual review required.'
        if requires_manual_review
        else 'AI evaluation completed successfully.'
    )

    presentation_status = 'partial' if warnings else 'success'
    warning_set = sorted(set(warnings))

    output = {
        'question_number': raw.get('question_number'),
        'presentation_status': presentation_status,
        'submission_status': 'evaluated',
        'mentor_view': {
            'final_score': score,
            'score_label': score_name,
            'feedback_summary': feedback_summary,
            'strengths': strengths,
            'improvements': improvements,
            'criteria_breakdown': criteria_breakdown,
        },
        'confidence_display': {
            'value': confidence,
            'level': level,
            'requires_manual_review': requires_manual_review,
        },
        'analytics': {
            'difficulty_level': difficulty_level,
            'common_mistakes': common_mistakes,
            'learning_tags': learning_tags,
        },
        'history_record': {
            'saved': True,
            'retry_count': max(0, int(raw.get('retry_count', 0) or 0)),
            'confidence': confidence,
            'ai_version': normalize_text(raw.get('ai_version') or 'pipeline_v1') or 'pipeline_v1',
        },
        'export_options': ['pdf', 'excel', 'json'],
        'notification': {
            'enabled': True,
            'message': notification_message,
        },
        'warnings': warning_set,
    }

    return output, warning_set


def present(payload):
    submission_id = str(payload.get('submission_id') or '')
    raw_items = payload.get('items') if isinstance(payload.get('items'), list) else []
    max_items = max(1, clamp_score(payload.get('max_items', 500)))

    items = []
    warnings = []

    for raw in raw_items[:max_items]:
        item_result, item_warnings = prepare_item(raw)
        items.append(item_result)
        warnings.extend(item_warnings)

    if len(raw_items) > max_items:
        warnings.append('presentation_items_truncated')

    if not items:
        warnings.append('missing_validated_items')

    warning_set = sorted(set(warnings))

    if not items:
        status = 'failed'
    else:
        statuses = {str(item.get('presentation_status') or 'failed') for item in items}
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
        'result_presentation_status': status,
        'next_stage': 'mentor_verdict',
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--input', required=True)
    args = parser.parse_args()

    try:
        payload = json.loads(Path(args.input).read_text(encoding='utf-8-sig'))
        result = present(payload)
    except Exception as exception:
        result = {
            'submission_id': '',
            'items': [],
            'warnings': ['python_result_presentation_exception', exception.__class__.__name__],
            'result_presentation_status': 'failed',
            'next_stage': 'mentor_verdict',
        }

    sys.stdout.write(json.dumps(result, ensure_ascii=False, separators=(',', ':')))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
