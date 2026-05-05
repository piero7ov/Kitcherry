import json
import hashlib
import re
import email.message
from config import TRAZABILIDAD_PATH
from utils import (
    now_str, 
    unique_list, 
    normalize_single_paragraph, 
    decode_mime_header
)

# ==========================================================
# TRAZABILIDAD REAL POR MESSAGE-ID
# ==========================================================
UID_ALIASES_KEY = "_uid_aliases"


def load_trazabilidad() -> dict:
    try:
        with open(TRAZABILIDAD_PATH, "r", encoding="utf-8") as f:
            data = json.load(f)

        if not isinstance(data, dict):
            return {UID_ALIASES_KEY: {}}

        if UID_ALIASES_KEY not in data or not isinstance(data.get(UID_ALIASES_KEY), dict):
            data[UID_ALIASES_KEY] = {}

        return data

    except Exception:
        return {UID_ALIASES_KEY: {}}


def save_trazabilidad(data: dict) -> None:
    if UID_ALIASES_KEY not in data or not isinstance(data.get(UID_ALIASES_KEY), dict):
        data[UID_ALIASES_KEY] = {}

    with open(TRAZABILIDAD_PATH, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)


def normalize_message_id(value: str) -> str:
    value = decode_mime_header(value or "")
    value = value.strip().strip("<>").lower()
    value = re.sub(r"\s+", "", value)
    return value


def build_tracking_id(msg: email.message.Message, uid: str, body_text: str = "") -> str:
    message_id = normalize_message_id(
        msg.get("Message-ID")
        or msg.get("Message-Id")
        or msg.get("Message-id")
        or ""
    )

    if message_id:
        return f"msgid:{message_id}"

    fingerprint = "|".join([
        decode_mime_header(msg.get("From", "")),
        decode_mime_header(msg.get("Subject", "")),
        decode_mime_header(msg.get("Date", "")),
        normalize_single_paragraph(body_text)[:700]
    ])

    digest = hashlib.sha256(
        fingerprint.encode("utf-8", errors="ignore")
    ).hexdigest()[:32]

    return f"hash:{digest}"


def create_empty_tracking(tracking_id: str, uid_actual: str | None = None) -> dict:
    created = now_str()

    return {
        "estado": "pendiente",
        "creado_en": created,
        "borrador_generado_en": None,
        "respondido_en": None,
        "archivado_en": None,
        "enviado_a_papelera_en": None,
        "respuesta_enviada": "",
        "tracking_id": tracking_id,
        "uid_actual": uid_actual or "",
        "uid_history": unique_list([uid_actual] if uid_actual else []),
        "historial": [
            {
                "accion": "correo_detectado",
                "fecha": created
            }
        ]
    }


def normalize_tracking_record(record: dict, tracking_id: str, uid_actual: str | None = None) -> dict:
    if not isinstance(record, dict):
        record = {}

    created = record.get("creado_en") or now_str()

    record.setdefault("estado", "pendiente")
    record.setdefault("creado_en", created)
    record.setdefault("borrador_generado_en", None)
    record.setdefault("respondido_en", None)
    record.setdefault("archivado_en", None)
    record.setdefault("enviado_a_papelera_en", None)
    record.setdefault("respuesta_enviada", "")
    record.setdefault("historial", [])

    if not isinstance(record["historial"], list):
        record["historial"] = []

    if not record["historial"]:
        record["historial"].append({
            "accion": "correo_detectado",
            "fecha": created
        })

    record["tracking_id"] = tracking_id

    if uid_actual:
        record["uid_actual"] = uid_actual

        history = record.get("uid_history", [])

        if not isinstance(history, list):
            history = []

        history.append(uid_actual)
        record["uid_history"] = unique_list(history)

    else:
        record.setdefault("uid_actual", "")
        record.setdefault("uid_history", [])

    return record


def resolve_tracking_key(identifier: str, data: dict | None = None) -> str:
    identifier = str(identifier or "").strip()

    if not identifier:
        return ""

    if data is None:
        data = load_trazabilidad()

    aliases = data.get(UID_ALIASES_KEY, {})

    if identifier in aliases:
        return aliases[identifier]

    return identifier


def merge_tracking_records(main_record: dict, legacy_record: dict) -> dict:
    if not isinstance(main_record, dict):
        main_record = {}

    if not isinstance(legacy_record, dict):
        legacy_record = {}

    main_estado = main_record.get("estado", "pendiente")
    legacy_estado = legacy_record.get("estado", "pendiente")

    prioridad_estado = {
        "pendiente": 1,
        "respondido": 2,
        "archivado": 3,
        "papelera": 4
    }

    if prioridad_estado.get(legacy_estado, 1) > prioridad_estado.get(main_estado, 1):
        main_record["estado"] = legacy_estado

    for field in [
        "creado_en",
        "borrador_generado_en",
        "respondido_en",
        "archivado_en",
        "enviado_a_papelera_en"
    ]:
        if not main_record.get(field) and legacy_record.get(field):
            main_record[field] = legacy_record[field]

    if not main_record.get("respuesta_enviada") and legacy_record.get("respuesta_enviada"):
        main_record["respuesta_enviada"] = legacy_record["respuesta_enviada"]

    main_history = main_record.get("historial", [])
    legacy_history = legacy_record.get("historial", [])

    if not isinstance(main_history, list):
        main_history = []

    if not isinstance(legacy_history, list):
        legacy_history = []

    main_record["historial"] = main_history + legacy_history

    return main_record


def ensure_tracking(tracking_id: str, uid_actual: str | None = None) -> dict:
    tracking_id = str(tracking_id or "").strip()
    uid_actual = str(uid_actual or "").strip()

    data = load_trazabilidad()
    aliases = data.setdefault(UID_ALIASES_KEY, {})

    if not tracking_id and uid_actual:
        tracking_id = uid_actual

    if not tracking_id:
        tracking_id = "sin_id"

    tracking_id = resolve_tracking_key(tracking_id, data)

    if uid_actual and tracking_id not in data and uid_actual in data and uid_actual != UID_ALIASES_KEY:
        data[tracking_id] = data.pop(uid_actual)

        data[tracking_id] = normalize_tracking_record(
            data[tracking_id],
            tracking_id,
            uid_actual
        )

        data[tracking_id]["historial"].append({
            "accion": "trazabilidad_migrada",
            "fecha": now_str(),
            "desde": uid_actual,
            "hacia": tracking_id
        })

    elif tracking_id not in data:
        data[tracking_id] = create_empty_tracking(tracking_id, uid_actual)

    else:
        if uid_actual and uid_actual in data and uid_actual != tracking_id and uid_actual != UID_ALIASES_KEY:
            data[tracking_id] = merge_tracking_records(data[tracking_id], data[uid_actual])
            del data[uid_actual]

            data[tracking_id]["historial"].append({
                "accion": "trazabilidad_migrada",
                "fecha": now_str(),
                "desde": uid_actual,
                "hacia": tracking_id
            })

        data[tracking_id] = normalize_tracking_record(
            data[tracking_id],
            tracking_id,
            uid_actual
        )

    if uid_actual:
        aliases[uid_actual] = tracking_id
        data[UID_ALIASES_KEY] = aliases

    save_trazabilidad(data)

    return data[tracking_id]


def update_tracking(
    identifier: str,
    updates: dict,
    action_name: str | None = None,
    action_extra: dict | None = None
) -> dict:
    data = load_trazabilidad()
    tracking_key = resolve_tracking_key(identifier, data)

    if not tracking_key:
        tracking_key = str(identifier or "").strip()

    if not tracking_key:
        tracking_key = "sin_id"

    if tracking_key not in data:
        data[tracking_key] = create_empty_tracking(tracking_key)

    data[tracking_key] = normalize_tracking_record(data[tracking_key], tracking_key)
    data[tracking_key].update(updates or {})

    if action_name:
        event = {
            "accion": action_name,
            "fecha": now_str()
        }

        if action_extra:
            event.update(action_extra)

        if not isinstance(data[tracking_key].get("historial"), list):
            data[tracking_key]["historial"] = []

        data[tracking_key]["historial"].append(event)

    save_trazabilidad(data)

    return data[tracking_key]


def dashboard_stats(items: list) -> dict:
    stats = {
        "total": 0,
        "pendiente": 0,
        "respondido": 0,
        "archivado": 0,
        "papelera": 0
    }

    for item in items:
        stats["total"] += 1
        estado = item.get("tracking", {}).get("estado", "pendiente")

        if estado in stats:
            stats[estado] += 1

    return stats
