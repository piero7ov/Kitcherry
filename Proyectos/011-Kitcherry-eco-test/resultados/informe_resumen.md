# Informe resumen - Kitcherry EcoTest

Fecha de ejecución: 12/05/2026 22:52

## Objetivo

El objetivo de esta prueba es comparar distintas formas de resolver procesos digitales dentro de Kitcherry y comprobar que no todas consumen los mismos recursos. La prueba permite justificar que la inteligencia artificial debe utilizarse de forma responsable, reservándola para tareas donde realmente aporte valor.

## Resultados obtenidos

| Proceso | Tiempo (s) | Potencia estimada (W) | Consumo estimado (Wh) | Consumo | Recomendación |
|---|---:|---:|---:|---|---|
| Clasificación con reglas | 0.00729 | 15 | 3.037e-05 | Bajo | Recomendado para tareas frecuentes por su bajo consumo estimado. |
| Clasificación con IA local | 35.61652 | 65 | 0.64307605 | Alto | Usar solo cuando aporte valor real; para casos simples conviene una solución más ligera. |
| Consulta de stock en JSON | 0.080156 | 12 | 0.00026719 | Bajo | Recomendado para tareas frecuentes por su bajo consumo estimado. |
| Consulta de stock en SQLite | 0.243873 | 14 | 0.00094839 | Bajo | Recomendado para tareas frecuentes por su bajo consumo estimado. |

## Conclusión

Los resultados muestran que las soluciones simples, como la clasificación por reglas o la consulta de datos estructurados, suelen ser más ligeras que una solución basada en inteligencia artificial local o en procesos más pesados. Por este motivo, en Kitcherry se plantea un uso responsable de la tecnología: aplicar IA cuando sea útil y mantener soluciones sencillas cuando sean suficientes. Esta decisión mejora el rendimiento, reduce carga en el servidor y contribuye a una implantación más sostenible.
