import re
import json
from datetime import datetime
from email.header import decode_header

# ==========================================================
# HELPERS GENERALES
# ==========================================================
def now_str() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def unique_list(values):
    out = []
    seen = set()

    for v in values:
        if v is None:
            continue

        txt = str(v).strip()

        if not txt:
            continue

        low = txt.lower()

        if low not in seen:
            seen.add(low)
            out.append(txt)

    return out


def normalize_single_paragraph(text: str) -> str:
    return " ".join((text or "").split()).strip()


def parse_json_with_fallback(raw: str, default: dict) -> dict:
    try:
        return json.loads(raw)
    except Exception:
        m = re.search(r"\{.*\}", raw or "", flags=re.S)

        if m:
            try:
                return json.loads(m.group(0))
            except Exception:
                return default

        return default


def decode_mime_header(value: str) -> str:
    if not value:
        return ""

    parts = decode_header(value)
    out = []

    for part, enc in parts:
        if isinstance(part, bytes):
            out.append(part.decode(enc or "utf-8", errors="ignore"))
        else:
            out.append(part)

    return "".join(out)


def normalize_reply_subject(subject: str) -> str:
    subject = (subject or "").strip()

    if not subject:
        return "Re: Consulta"

    if subject.lower().startswith("re:"):
        return subject

    return f"Re: {subject}"
