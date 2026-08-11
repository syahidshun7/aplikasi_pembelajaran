#!/usr/bin/env python3

import argparse
import csv
import html
import importlib.util
import json
import re
import subprocess
import sys
import tempfile
import zipfile
from pathlib import Path
from typing import Any
from xml.etree import ElementTree


IMAGE_EXTENSIONS = {"jpg", "jpeg", "png", "webp", "gif", "bmp", "tif", "tiff"}


def normalize_text(text: str) -> str:
    text = text.replace("\r\n", "\n").replace("\r", "\n").replace("\x00", "")
    text = "".join(character for character in text if character == "\n" or character == "\t" or ord(character) >= 32)
    text = re.sub(r"[ \t]+$", "", text, flags=re.MULTILINE)
    text = re.sub(r"-\n(?=\w)", "", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text.strip()


def average_confidence(confidences: list[float]) -> float | None:
    valid_confidences = [confidence for confidence in confidences if isinstance(confidence, (int, float)) and confidence >= 0]
    if not valid_confidences:
        return None
    return round(sum(valid_confidences) / len(valid_confidences), 2)


def unique_warnings(warnings: list[str]) -> list[str]:
    seen: set[str] = set()
    result: list[str] = []
    for warning in warnings:
        warning = str(warning).strip()
        if warning and warning not in seen:
            result.append(warning)
            seen.add(warning)
    return result


def build_result(
    payload: dict[str, Any],
    detected_content_type: str,
    extraction_method: str,
    raw_text: str,
    page_count: int,
    ocr_used: bool,
    ocr_confidence: float | None,
    extraction_status: str,
    warnings: list[str],
) -> dict[str, Any]:
    raw_text = normalize_text(raw_text)
    max_chars = max(1000, int(payload.get("max_chars") or 200000))
    if len(raw_text) > max_chars:
        raw_text = raw_text[:max_chars]
        warnings.append("raw_text_truncated")

    status = "success" if extraction_status == "success" and raw_text else "failed"
    if not raw_text and "no_readable_text" not in warnings:
        warnings.append("no_readable_text")

    return {
        "submission_id": str(payload.get("submission_id") or ""),
        "detected_content_type": detected_content_type,
        "extraction_method": extraction_method,
        "raw_text": raw_text,
        "page_count": max(0, int(page_count or 0)),
        "ocr_used": bool(ocr_used),
        "ocr_confidence": ocr_confidence if ocr_used else None,
        "extraction_status": status,
        "warnings": unique_warnings(warnings),
    }


def database_text_from_payload(payload: dict[str, Any]) -> str:
    parts: list[str] = []
    content = str(payload.get("content") or "")
    if content.strip() and content.strip() != "[TASK_BANK_RAW_SUBMISSION]":
        parts.append(content)

    task_answers = payload.get("task_answers") or {}
    if isinstance(task_answers, dict) and task_answers:
        lines = ["[TASK_BANK_ANSWERS]"]
        for question_uuid, answer in task_answers.items():
            lines.append(f"question_uuid: {question_uuid}")
            if isinstance(answer, (dict, list)):
                lines.append(json.dumps(answer, ensure_ascii=False, separators=(",", ":")))
            else:
                lines.append(str(answer))
            lines.append("")
        parts.append("\n".join(lines).strip())

    return normalize_text("\n\n---\n\n".join(parts))


def read_txt(file_path: Path) -> dict[str, Any]:
    for encoding in ("utf-8-sig", "utf-8", "latin-1"):
        try:
            return {
                "detected_content_type": "txt",
                "extraction_method": "txt_reader",
                "raw_text": normalize_text(file_path.read_text(encoding=encoding)),
                "page_count": 1,
                "ocr_used": False,
                "ocr_confidence": None,
                "extraction_status": "success",
                "warnings": [],
            }
        except UnicodeDecodeError:
            continue
    return failure("txt", "txt_reader", ["txt_read_failed"])


def read_docx(file_path: Path) -> dict[str, Any]:
    try:
        with zipfile.ZipFile(file_path) as docx_zip:
            document_xml = docx_zip.read("word/document.xml")
    except KeyError:
        return failure("docx", "docx_parser", ["docx_document_xml_missing"])
    except Exception:
        return failure("docx", "docx_parser", ["docx_open_failed"])

    try:
        root = ElementTree.fromstring(document_xml)
    except ElementTree.ParseError:
        return failure("docx", "docx_parser", ["docx_xml_parse_failed"])

    namespace = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
    paragraphs: list[str] = []
    for paragraph in root.findall(".//w:p", namespace):
        segments: list[str] = []
        for node in paragraph.iter():
            tag = node.tag.split("}")[-1]
            if tag == "t" and node.text:
                segments.append(node.text)
            elif tag == "tab":
                segments.append("\t")
            elif tag == "br":
                segments.append("\n")
        paragraph_text = normalize_text(html.unescape("".join(segments)))
        if paragraph_text:
            paragraphs.append(paragraph_text)

    raw_text = normalize_text("\n".join(paragraphs))
    if not raw_text:
        return failure("docx", "docx_parser", ["no_readable_text"])

    return success("docx", "docx_parser", raw_text, 1, False, None, [])


def read_pdf_text_with_pypdf(file_path: Path) -> tuple[str, int, list[str]]:
    if importlib.util.find_spec("pypdf") is None:
        return "", 0, ["python_pdf_text_library_missing"]

    try:
        from pypdf import PdfReader

        reader = PdfReader(str(file_path))
        page_count = len(reader.pages)
        page_texts = [page.extract_text() or "" for page in reader.pages]
        return normalize_text("\n\n".join(page_texts)), page_count, []
    except Exception:
        return "", 0, ["pdf_text_extract_failed"]


def read_pdf_text_with_pdfplumber(file_path: Path) -> tuple[str, int, list[str]]:
    if importlib.util.find_spec("pdfplumber") is None:
        return "", 0, ["python_pdfplumber_missing"]

    try:
        import pdfplumber

        with pdfplumber.open(str(file_path)) as pdf_file:
            page_count = len(pdf_file.pages)
            page_texts = [page.extract_text() or "" for page in pdf_file.pages]
        return normalize_text("\n\n".join(page_texts)), page_count, []
    except Exception:
        return "", 0, ["pdfplumber_extract_failed"]


def pdf_has_significant_images(file_path: Path) -> bool:
    """Detect if PDF contains embedded images that may hold content."""
    try:
        from pypdf import PdfReader
        reader = PdfReader(str(file_path))
        for page in reader.pages:
            resources = page.get("/Resources")
            if resources and "/XObject" in resources:
                xobjects = resources["/XObject"].get_object()
                for obj_key in xobjects:
                    xobj = xobjects[obj_key].get_object()
                    if xobj.get("/Subtype") == "/Image":
                        width = int(xobj.get("/Width", 0))
                        height = int(xobj.get("/Height", 0))
                        if width >= 200 and height >= 100:
                            return True
    except Exception:
        pass
    return False


def read_pdf(file_path: Path, payload: dict[str, Any]) -> dict[str, Any]:
    warnings: list[str] = []
    raw_text, page_count, pdf_warnings = read_pdf_text_with_pypdf(file_path)
    warnings.extend(pdf_warnings)

    if not raw_text:
        raw_text, page_count_pdfplumber, pdfplumber_warnings = read_pdf_text_with_pdfplumber(file_path)
        warnings.extend(pdfplumber_warnings)
        page_count = max(page_count, page_count_pdfplumber)

    if raw_text:
        # Check if PDF has images that might contain additional content
        text_per_page = len(raw_text) / max(1, page_count)
        has_images = pdf_has_significant_images(file_path)

        if has_images and text_per_page < 200:
            # Mixed PDF: little text + images → OCR and merge
            warnings.append("mixed_pdf_detected")
            ocr_result = read_scan_pdf_with_ocr(file_path, payload, page_count)
            ocr_text = ocr_result.get("raw_text", "")
            if ocr_text and len(ocr_text) > len(raw_text):
                merged = normalize_text(raw_text + "\n\n" + ocr_text)
                return success("mixed_pdf", "pdf_text+OCR", merged, max(1, page_count), True, ocr_result.get("ocr_confidence"), unique_warnings(warnings + list(ocr_result.get("warnings") or [])))

        return success("pdf_text", "pdf_text", raw_text, max(1, page_count), False, None, warnings)

    warnings.append("pdf_text_empty")
    ocr_result = read_scan_pdf_with_ocr(file_path, payload, page_count)
    ocr_result["warnings"] = unique_warnings(warnings + list(ocr_result.get("warnings") or []))
    return ocr_result


def read_scan_pdf_with_ocr(file_path: Path, payload: dict[str, Any], known_page_count: int) -> dict[str, Any]:
    pdftoppm_binary = str(payload.get("pdftoppm_binary") or "pdftoppm")
    timeout_seconds = max(10, int(payload.get("ocr_timeout_seconds") or 60))

    with tempfile.TemporaryDirectory(prefix="submission-extraction-") as temp_directory:
        output_prefix = str(Path(temp_directory) / "page")
        try:
            subprocess.run(
                [pdftoppm_binary, "-png", "-r", "200", str(file_path), output_prefix],
                check=True,
                capture_output=True,
                text=True,
                timeout=timeout_seconds,
            )
        except FileNotFoundError:
            return failure("scan_pdf", "OCR", ["pdf_to_image_tool_unavailable"], True, 0.0, known_page_count)
        except subprocess.TimeoutExpired:
            return failure("scan_pdf", "OCR", ["pdf_to_image_timeout"], True, 0.0, known_page_count)
        except subprocess.CalledProcessError:
            return failure("scan_pdf", "OCR", ["pdf_to_image_failed"], True, 0.0, known_page_count)

        image_paths = sorted(Path(temp_directory).glob("page*.png"), key=lambda path: path.name)
        if not image_paths:
            return failure("scan_pdf", "OCR", ["pdf_to_image_empty_output"], True, 0.0, known_page_count)

        page_texts: list[str] = []
        confidences: list[float] = []
        warnings: list[str] = []
        for page_index, image_path in enumerate(image_paths, start=1):
            ocr_result = run_ocr_for_image(image_path, payload)
            if ocr_result["text"]:
                page_texts.append(f"[PAGE {page_index}]\n{ocr_result['text']}")
            else:
                warnings.append("empty_page_detected")
            if ocr_result["confidence"] is not None:
                confidences.append(float(ocr_result["confidence"]))
            warnings.extend(ocr_result["warnings"])

    raw_text = normalize_text("\n\n".join(page_texts))
    ocr_confidence = average_confidence(confidences)
    if ocr_confidence is not None and ocr_confidence < 0.7:
        warnings.append("low_ocr_confidence")
    return {
        "detected_content_type": "scan_pdf",
        "extraction_method": "OCR",
        "raw_text": raw_text,
        "page_count": max(known_page_count, len(image_paths)),
        "ocr_used": True,
        "ocr_confidence": ocr_confidence,
        "extraction_status": "success" if raw_text else "failed",
        "warnings": unique_warnings(warnings),
    }


def read_image(file_path: Path, payload: dict[str, Any]) -> dict[str, Any]:
    ocr_result = run_ocr_for_image(file_path, payload)
    return {
        "detected_content_type": "image",
        "extraction_method": "OCR",
        "raw_text": ocr_result["text"],
        "page_count": 1,
        "ocr_used": True,
        "ocr_confidence": ocr_result["confidence"],
        "extraction_status": "success" if ocr_result["text"] else "failed",
        "warnings": unique_warnings(ocr_result["warnings"]),
    }


def run_ocr_for_image(file_path: Path, payload: dict[str, Any]) -> dict[str, Any]:
    tesseract_binary = str(payload.get("tesseract_binary") or "tesseract")
    tesseract_lang = str(payload.get("tesseract_lang") or "ind+eng")
    timeout_seconds = max(10, int(payload.get("ocr_timeout_seconds") or 60))
    try:
        process = subprocess.run(
            [tesseract_binary, str(file_path), "stdout", "-l", tesseract_lang, "--psm", "6", "tsv"],
            check=True,
            capture_output=True,
            text=True,
            timeout=timeout_seconds,
            encoding="utf-8",
            errors="replace",
        )
    except FileNotFoundError:
        return {"text": "", "confidence": 0.0, "warnings": ["ocr_tool_unavailable"]}
    except subprocess.TimeoutExpired:
        return {"text": "", "confidence": 0.0, "warnings": ["ocr_timeout"]}
    except subprocess.CalledProcessError:
        return {"text": "", "confidence": 0.0, "warnings": ["ocr_failed"]}

    parsed = parse_tesseract_tsv(process.stdout)
    warnings: list[str] = []
    if not parsed["text"]:
        warnings.append("empty_page_detected")
    if parsed["confidence"] is not None and parsed["confidence"] < 0.7:
        warnings.append("low_ocr_confidence")
    return {"text": parsed["text"], "confidence": parsed["confidence"], "warnings": warnings}


def parse_tesseract_tsv(tsv_text: str) -> dict[str, Any]:
    rows = list(csv.DictReader(tsv_text.splitlines(), delimiter="\t"))
    line_parts: dict[str, list[str]] = {}
    confidences: list[float] = []
    for row in rows:
        word = str(row.get("text") or "").strip()
        if not word:
            continue
        line_key = ":".join(str(row.get(key) or "1") for key in ["page_num", "block_num", "par_num", "line_num"])
        line_parts.setdefault(line_key, []).append(word)
        try:
            confidence = float(row.get("conf") or -1)
        except ValueError:
            confidence = -1
        if confidence >= 0:
            confidences.append(confidence / 100)

    lines = [" ".join(parts) for parts in line_parts.values()]
    return {"text": normalize_text("\n".join(lines)), "confidence": average_confidence(confidences)}


def extract_file(file_path: Path, extension: str, payload: dict[str, Any]) -> dict[str, Any]:
    if not file_path.exists():
        return failure("txt", "txt_reader", ["file_not_found"])
    if extension == "pdf":
        return read_pdf(file_path, payload)
    if extension == "docx":
        return read_docx(file_path)
    if extension == "txt":
        return read_txt(file_path)
    if extension in IMAGE_EXTENSIONS:
        return read_image(file_path, payload)
    return failure("txt", "txt_reader", ["unsupported_file_type"])


def success(
    detected_content_type: str,
    extraction_method: str,
    raw_text: str,
    page_count: int,
    ocr_used: bool,
    ocr_confidence: float | None,
    warnings: list[str],
) -> dict[str, Any]:
    return {
        "detected_content_type": detected_content_type,
        "extraction_method": extraction_method,
        "raw_text": normalize_text(raw_text),
        "page_count": max(1, int(page_count or 1)),
        "ocr_used": ocr_used,
        "ocr_confidence": ocr_confidence,
        "extraction_status": "success",
        "warnings": unique_warnings(warnings),
    }


def failure(
    detected_content_type: str,
    extraction_method: str,
    warnings: list[str],
    ocr_used: bool = False,
    ocr_confidence: float | None = None,
    page_count: int = 0,
) -> dict[str, Any]:
    return {
        "detected_content_type": detected_content_type,
        "extraction_method": extraction_method,
        "raw_text": "",
        "page_count": max(0, int(page_count or 0)),
        "ocr_used": ocr_used,
        "ocr_confidence": ocr_confidence,
        "extraction_status": "failed",
        "warnings": unique_warnings(warnings),
    }


def extract_submission(payload: dict[str, Any]) -> dict[str, Any]:
    warnings: list[str] = []
    database_text = database_text_from_payload(payload)
    file_path_value = str(payload.get("file_path") or "").strip()
    extension = str(payload.get("file_extension") or "").strip().lower().lstrip(".")

    if not file_path_value:
        return build_result(payload, "txt", "txt_reader", database_text, 1 if database_text else 0, False, None, "success" if database_text else "failed", warnings)

    file_result = extract_file(Path(file_path_value), extension, payload)
    warnings.extend(file_result.get("warnings") or [])

    segments = []
    if database_text:
        segments.append(database_text)
    if file_result.get("raw_text"):
        segments.append(str(file_result["raw_text"]))
    if len(segments) > 1:
        warnings.append("multiple_sources_combined")
    if file_result.get("extraction_status") == "failed" and database_text:
        warnings.append("file_extraction_failed")

    raw_text = normalize_text("\n\n---\n\n".join(segments))
    status = "success" if raw_text and (file_result.get("extraction_status") == "success" or database_text) else "failed"

    return build_result(
        payload,
        str(file_result.get("detected_content_type") or "txt"),
        str(file_result.get("extraction_method") or "txt_reader"),
        raw_text,
        max(int(file_result.get("page_count") or 0), 1 if database_text else 0),
        bool(file_result.get("ocr_used") or False),
        file_result.get("ocr_confidence"),
        status,
        warnings,
    )


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    args = parser.parse_args()

    try:
        payload = json.loads(Path(args.input).read_text(encoding="utf-8"))
        result = extract_submission(payload)
    except Exception as exception:
        result = build_result(
            {"submission_id": ""},
            "txt",
            "txt_reader",
            "",
            0,
            False,
            None,
            "failed",
            ["python_extraction_exception", exception.__class__.__name__],
        )

    sys.stdout.write(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
