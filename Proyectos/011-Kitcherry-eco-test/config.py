"""
Configuración general de Kitcherry EcoTest.

Las potencias son estimaciones didácticas. No representan una medición real
con un medidor eléctrico, pero permiten comparar procesos ligeros y pesados.
"""

# Potencia aproximada usada para estimar consumo energético.
# Fórmula: Wh = (vatios * segundos) / 3600
POTENCIA_ESTIMADA = {
    "clasificacion_reglas": 15,
    "clasificacion_ia_local": 65,
    "consulta_json": 12,
    "consulta_sqlite": 14,
}

# Número de repeticiones para que las diferencias de tiempo se vean mejor.
# La IA local se deja en 1 repetición porque cada consulta llama realmente a Ollama.
REPETICIONES = {
    "clasificacion_reglas": 400,
    "clasificacion_ia_local": 1,
    "consulta_json": 500,
    "consulta_sqlite": 500,
}

# Configuración de Ollama para la prueba con IA local.
# Antes de ejecutar el proyecto, Ollama debe estar abierto y el modelo descargado.
OLLAMA_URL = "http://localhost:11434/api/generate"
OLLAMA_MODEL = "llama3:latest"
OLLAMA_TIMEOUT = 120

# Categorías permitidas para clasificar consultas de Kitcherry.
CATEGORIAS_VALIDAS = [
    "reserva",
    "alergenos",
    "horarios",
    "carta",
    "stock",
    "contacto",
    "incidencia",
    "comercial",
    "otros",
]

# Umbrales orientativos para clasificar el consumo estimado.
UMBRAL_BAJO_WH = 0.001
UMBRAL_MEDIO_WH = 0.01