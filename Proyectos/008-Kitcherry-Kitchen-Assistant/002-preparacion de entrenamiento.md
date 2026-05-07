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

## CON GPU EN EL VENV

pip uninstall torch torchvision torchaudio -y

pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu128

py -c "import torch; print('Torch:', torch.__version__); print('CUDA:', torch.cuda.is_available()); print('CUDA version torch:', torch.version.cuda); print('GPU:', torch.cuda.get_device_name(0) if torch.cuda.is_available() else 'Sin GPU CUDA')"

y le das al scriupt dentro del entorno