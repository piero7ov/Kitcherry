from flask import Flask, render_template, request, jsonify, send_file
import io

from config import OLLAMA_MODEL, N_ULTIMOS_POR_DEFECTO, MAX_CORREOS_CARGA
from utils import now_str
from db import (
    UID_ALIASES_KEY,
    load_trazabilidad, 
    save_trazabilidad, 
    create_empty_tracking, 
    normalize_tracking_record, 
    resolve_tracking_key, 
    update_tracking
)
from email_service import fetch_attachment_by_uid, enviar_respuesta_email
from ai_service import generar_borrador_respuesta
from pipeline import obtener_correos_procesados

app = Flask(__name__)

# ==========================================================
# RUTAS
# ==========================================================
@app.route("/")
def index():
    return render_template(
        "index.html",
        modelo=OLLAMA_MODEL,
        total_por_defecto=N_ULTIMOS_POR_DEFECTO,
        max_correos=MAX_CORREOS_CARGA
    )


@app.route("/api/correos")
def api_correos():
    try:
        n = int(request.args.get("n", N_ULTIMOS_POR_DEFECTO))
    except Exception:
        n = N_ULTIMOS_POR_DEFECTO

    n = max(1, min(MAX_CORREOS_CARGA, n))

    return jsonify({
        "ok": True,
        "n": n,
        "modelo": OLLAMA_MODEL,
        "datos": obtener_correos_procesados(n)
    })


@app.route("/api/generar_respuesta", methods=["POST"])
def api_generar_respuesta():
    payload = request.get_json(silent=True) or {}

    uid = str(payload.get("uid") or "").strip()
    tracking_id = str(payload.get("tracking_id") or "").strip()
    identifier = tracking_id or uid

    try:
        tracking = None

        if identifier:
            tracking = update_tracking(
                identifier,
                {
                    "borrador_generado_en": now_str()
                },
                action_name="borrador_generado"
            )

        return jsonify({
            "ok": True,
            "respuesta": generar_borrador_respuesta(payload),
            "tracking": tracking
        })

    except Exception as e:
        return jsonify({
            "ok": False,
            "error": str(e)
        }), 500


@app.route("/api/enviar_respuesta", methods=["POST"])
def api_enviar_respuesta():
    payload = request.get_json(silent=True) or {}

    uid = (payload.get("uid") or "").strip()
    tracking_id = (payload.get("tracking_id") or "").strip()
    identifier = tracking_id or uid

    destinatario = (payload.get("to") or "").strip()
    asunto = (payload.get("subject") or "").strip()
    cuerpo = (payload.get("body") or "").strip()

    try:
        enviar_respuesta_email(destinatario, asunto, cuerpo)

        tracking = None

        if identifier:
            tracking = update_tracking(
                identifier,
                {
                    "estado": "respondido",
                    "respondido_en": now_str(),
                    "respuesta_enviada": cuerpo
                },
                action_name="correo_enviado"
            )

        return jsonify({
            "ok": True,
            "message": "Correo enviado correctamente.",
            "tracking": tracking,
            "respuesta_enviada": cuerpo
        })

    except Exception as e:
        return jsonify({
            "ok": False,
            "error": str(e)
        }), 500


@app.route("/api/cambiar_estado", methods=["POST"])
def api_cambiar_estado():
    payload = request.get_json(silent=True) or {}

    uid = (payload.get("uid") or "").strip()
    tracking_id = (payload.get("tracking_id") or "").strip()
    identifier = tracking_id or uid

    estado = (payload.get("estado") or "").strip()

    if not identifier:
        return jsonify({
            "ok": False,
            "error": "Falta el identificador del correo."
        }), 400

    if estado not in {"pendiente", "respondido", "archivado", "papelera"}:
        return jsonify({
            "ok": False,
            "error": "Estado no válido."
        }), 400

    data = load_trazabilidad()
    tracking_key = resolve_tracking_key(identifier, data)

    if tracking_key not in data:
        data[tracking_key] = create_empty_tracking(tracking_key)

    tracking_actual = normalize_tracking_record(data[tracking_key], tracking_key)
    estado_anterior = tracking_actual.get("estado", "pendiente")

    updates = {
        "estado": estado
    }

    if estado == "archivado":
        updates["archivado_en"] = now_str()
        accion = "correo_archivado"

    elif estado == "papelera":
        updates["enviado_a_papelera_en"] = now_str()
        accion = "enviado_a_papelera"

    elif estado == "pendiente":
        accion = "recuperado_de_papelera" if estado_anterior == "papelera" else "marcado_como_pendiente"

    else:
        updates["respondido_en"] = tracking_actual.get("respondido_en") or now_str()
        accion = "marcado_como_respondido"

    try:
        tracking = update_tracking(
            identifier,
            updates,
            action_name=accion
        )

        return jsonify({
            "ok": True,
            "tracking": tracking
        })

    except Exception as e:
        return jsonify({
            "ok": False,
            "error": str(e)
        }), 500


@app.route("/api/vaciar_papelera", methods=["POST"])
def api_vaciar_papelera():
    try:
        data = load_trazabilidad()

        keys_to_delete = [
            key for key, item in data.items()
            if key != UID_ALIASES_KEY
            and isinstance(item, dict)
            and item.get("estado") == "papelera"
        ]

        for key in keys_to_delete:
            del data[key]

        aliases = data.get(UID_ALIASES_KEY, {})

        aliases_to_delete = [
            uid for uid, tracking_key in aliases.items()
            if tracking_key in keys_to_delete
        ]

        for uid in aliases_to_delete:
            del aliases[uid]

        data[UID_ALIASES_KEY] = aliases

        save_trazabilidad(data)

        return jsonify({
            "ok": True,
            "deleted": len(keys_to_delete)
        })

    except Exception as e:
        return jsonify({
            "ok": False,
            "error": str(e)
        }), 500


@app.route("/api/adjunto")
def api_adjunto():
    uid = (request.args.get("uid") or "").strip()
    index_raw = (request.args.get("index") or "").strip()

    if not uid:
        return jsonify({
            "ok": False,
            "error": "Falta el UID del correo."
        }), 400

    try:
        attachment_index = int(index_raw)

    except Exception:
        return jsonify({
            "ok": False,
            "error": "Índice de adjunto inválido."
        }), 400

    try:
        attachment = fetch_attachment_by_uid(uid, attachment_index)

        return send_file(
            io.BytesIO(attachment["data"]),
            mimetype=attachment["content_type"],
            as_attachment=True,
            download_name=attachment["filename"]
        )

    except Exception as e:
        return jsonify({
            "ok": False,
            "error": str(e)
        }), 404


if __name__ == "__main__":
    app.run(debug=True)