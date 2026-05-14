# Kitcherry EcoTest

Mini proyecto para el apartado **2.10. Desarrollo sostenible** del proyecto Kitcherry.

La idea es comparar distintas formas de resolver tareas digitales dentro de Kitcherry y ver que no todas consumen los mismos recursos.

El proyecto mide:

- Tiempo de ejecución.
- Número de elementos procesados.
- Consumo energético estimado de forma didáctica.
- Recomendación sobre si conviene usar una solución ligera o una solución más pesada.

> Importante: el consumo energético no es una medición eléctrica real. Es una estimación sencilla basada en tiempo de ejecución y una potencia aproximada. Sirve para demostrar la preocupación por la eficiencia energética en el desarrollo del software.

## Pruebas incluidas

1. **Clasificación con reglas**  
   Clasifica consultas de clientes usando palabras clave.

2. **Clasificación con IA local**  
   Clasifica consultas usando un modelo local mediante Ollama. Esta prueba es más realista que una simulación, pero también consume más recursos y tarda más.

3. **Consulta de stock en JSON**  
   Lee productos desde un archivo JSON.

4. **Consulta de stock en SQLite**  
   Lee productos desde una base de datos SQLite local.

## Requisitos

Para las pruebas normales solo hace falta Python.

Para la prueba con IA local necesitas:

1. Tener Ollama instalado.
2. Tener Ollama abierto.
3. Tener descargado el modelo indicado en `config.py`.

Por defecto se usa:

```text
llama3:latest