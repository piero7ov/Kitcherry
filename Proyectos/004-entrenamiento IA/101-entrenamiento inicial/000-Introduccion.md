````markdown
# Entrenamiento de una IA local en Windows

1. Entrenar / afinar el modelo (**fine-tune**)
2. Fusionar el modelo
3. Inferir con el modelo entrenado

## Importante

Es recomendable crear un entorno virtual (`venv`) para instalar las librerías del proyecto sin afectar al resto del sistema.

## Creamos entorno virtual

```powershell
python -m venv venv
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
````

También puede usarse:

```powershell
py -m venv venv
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
```

## Activamos entorno virtual

En Windows, desde PowerShell:

```powershell
.\venv\Scripts\activate
```

Si usas CMD:

```cmd
venv\Scripts\activate.bat
```

## Instalamos librerías necesarias

```powershell
pip install torch
pip install datasets
pip install peft
pip install transformers
```

## RESUMEN:

```
py -m venv venv
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\venv\Scripts\activate
python -m pip install --upgrade pip
pip install torch datasets peft transformers accelerate
```

Bloque 2:

1. Sistemas de respuesta automática a preguntas frecuentes sobre el establecimiento
2. Chatbots de atención al cliente para hostelería
    Conectados a web
    Conectados a sistemas de mensajería