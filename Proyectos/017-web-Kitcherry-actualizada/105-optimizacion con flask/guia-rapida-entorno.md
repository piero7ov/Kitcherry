py -m venv venv

Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass

.\venv\Scripts\activate

python -m pip install --upgrade pip

pip install -r requirements.txt

python "servidor_flask\app.py"
