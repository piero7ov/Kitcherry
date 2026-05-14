"""
Funciones comunes para medir tiempo y estimar consumo energético.
"""

from time import perf_counter
from typing import Callable, Dict, Any


def estimar_consumo_wh(segundos: float, potencia_w: float) -> float:
    """
    Estima el consumo en Wh usando una fórmula sencilla.

    Wh = W * h
    Como el tiempo lo tenemos en segundos:
    Wh = (W * segundos) / 3600
    """
    return (potencia_w * segundos) / 3600


def medir_proceso(nombre: str, funcion: Callable[[], Dict[str, Any]], potencia_w: float) -> Dict[str, Any]:
    """
    Ejecuta una función, mide cuánto tarda y calcula consumo estimado.
    """
    inicio = perf_counter()
    datos = funcion()
    fin = perf_counter()

    segundos = fin - inicio
    consumo_wh = estimar_consumo_wh(segundos, potencia_w)

    return {
        "proceso": nombre,
        "tiempo_segundos": round(segundos, 6),
        "potencia_estimada_w": potencia_w,
        "consumo_estimado_wh": round(consumo_wh, 8),
        **datos,
    }
