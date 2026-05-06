from __future__ import annotations

import os
from pathlib import Path

# ==========================================================
# RUTAS
# ==========================================================

BASE_DIR = Path(__file__).resolve().parent.parent

PDF_DIR = BASE_DIR / "pdf"
TXT_DIR = BASE_DIR / "txt"
TXT_LIMPIO_DIR = BASE_DIR / "txt_limpio"
OUT_DIR = BASE_DIR / "out"
SUMMARIES_DIR = BASE_DIR / "summaries"

CONFIG_PATH = BASE_DIR / "config_kitcherry.json"

# ==========================================================
# OLLAMA
# ==========================================================

OLLAMA_URL = os.environ.get("OLLAMA_URL", "http://localhost:11434/api/generate")
OLLAMA_MODEL = os.environ.get("OLLAMA_MODEL", "llama3:latest")
EJECUTAR_OLLAMA = os.environ.get("KITCHERRY_EJECUTAR_OLLAMA", "1") != "0"

# ==========================================================
# ALÉRGENOS Y PALABRAS CLAVE
# ==========================================================

ALERGENOS_ORDEN = [
    "Gluten",
    "Crustáceos",
    "Huevo",
    "Pescado",
    "Cacahuetes",
    "Soja",
    "Leche",
    "Frutos secos",
    "Apio",
    "Mostaza",
    "Sésamo",
    "Sulfitos",
    "Altramuces",
    "Moluscos",
]

ALERGENOS_KEYWORDS = {
    "Gluten": [
        "gluten", "trigo", "harina de trigo", "harina", "pan ", "pan,", "pan.",
        "pan brioche", "brioche", "pan rallado", "pasta", "galleta", "cebada", "cerveza", "pita",
    ],
    "Crustáceos": [
        "crustaceo", "crustaceos", "gamba", "gambas", "langostino", "langostinos",
        "cigala", "cigalas", "bogavante", "cangrejo",
    ],
    "Huevo": [
        "huevo", "huevos", "yema", "mayonesa", "mahonesa", "alioli", "pasta fresca de huevo",
    ],
    "Pescado": [
        "pescado", "caldo de pescado", "lubina", "atun", "atún", "bonito", "merluza",
        "bacalao", "salmón", "salmon", "anchoa",
    ],
    "Cacahuetes": ["cacahuete", "cacahuetes", "mani", "maní"],
    "Soja": ["soja", "salsa de soja"],
    "Leche": [
        "leche", "lacteo", "lácteo", "lacteos", "lácteos", "queso", "nata", "mantequilla",
        "parmesano", "bechamel", "crema de leche", "queso crema",
    ],
    "Frutos secos": [
        "frutos secos", "nuez", "nueces", "almendra", "almendras", "piñon", "piñón",
        "piñones", "avellana", "avellanas", "pistacho", "pistachos",
    ],
    "Apio": ["apio"],
    "Mostaza": ["mostaza"],
    "Sésamo": ["sesamo", "sésamo", "tahini"],
    "Sulfitos": ["sulfito", "sulfitos", "vino", "vino blanco", "vino tinto", "cava", "vinagre"],
    "Altramuces": ["altramuz", "altramuces"],
    "Moluscos": [
        "molusco", "moluscos", "mejillon", "mejillón", "mejillones", "calamar", "calamares",
        "sepia", "almeja", "almejas", "pulpo",
    ],
}

CATEGORIAS_VALIDAS = [
    "Entrantes", "Platos principales", "Postres", "Bebidas", "Primeros", "Segundos",
    "Ensaladas", "Carnes", "Pescados", "Arroces", "Tapas",
]

LINEAS_IGNORABLES_EXACTAS = {
    "plato", "precio", "descripcion", "descripción", "ingredientes",
    "descripcion / ingredientes principales", "descripción / ingredientes principales",
}

# ==========================================================
# NEGOCIO
# ==========================================================

def inferir_negocio_desde_carpeta() -> str:
    nombre = BASE_DIR.name.lower()
    if "huerta" in nombre or "mar" in nombre:
        return "Restaurante La Huerta del Mar"
    if "pochi" in nombre:
        return "Casa Pochi"
    return "Restaurante de prueba"
