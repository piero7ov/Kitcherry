<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sintetizador de Voz - Voces Disponibles</title>
    <style>
        body { font-family: sans-serif; padding: 20px; line-height: 1.6; background-color: #f4f4f9; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; shadow: 0 2px 5px rgba(0,0,0,0.1); }
        select, textarea, button { width: 100%; padding: 10px; margin-top: 10px; border-radius: 4px; border: 1px solid #ccc; }
        button { background-color: #007bff; color: white; cursor: pointer; border: none; font-weight: bold; }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>

<div class="container">
    <h2>Explorador de Voces (TTS)</h2>
    <p>Escribe algo y selecciona una voz para escucharla.</p>
    
    <textarea id="texto" rows="4">¡Hola! Esta es una prueba de las voces disponibles en tu navegador.</textarea>
    
    <label for="listaVoces">Selecciona una voz:</label>
    <select id="listaVoces">
        <option value="">Cargando voces...</option>
    </select>

    <button id="btnEscuchar">Escuchar Voz</button>
</div>

<script>
    const synth = window.speechSynthesis;
    const listaVoces = document.getElementById('listaVoces');
    const btnEscuchar = document.getElementById('btnEscuchar');
    const inputTexto = document.getElementById('texto');

    let voces = [];

    // Función para poblar la lista de voces
    function cargarVoces() {
        // Obtiene las voces disponibles en el dispositivo
        voces = synth.getVoices();
        
        listaVoces.innerHTML = '';
        voces.forEach((voz, i) => {
            const opcion = document.createElement('option');
            opcion.textContent = `${voz.name} (${voz.lang})`;
            
            if (voz.default) {
                opcion.textContent += ' -- PREDETERMINADA';
            }

            opcion.setAttribute('data-lang', voz.lang);
            opcion.setAttribute('data-name', voz.name);
            opcion.value = i;
            listaVoces.appendChild(opcion);
        });
    }

    // El evento 'onvoiceschanged' es necesario porque las voces se cargan de forma asíncrona
    if (speechSynthesis.onvoiceschanged !== undefined) {
        speechSynthesis.onvoiceschanged = cargarVoces;
    }

    // Ejecución inicial por si las voces ya están cargadas
    cargarVoces();

    btnEscuchar.addEventListener('click', () => {
        if (synth.speaking) {
            console.error('Ya se está reproduciendo un audio.');
            return;
        }

        if (inputTexto.value !== '') {
            const mensaje = new SpeechSynthesisUtterance(inputTexto.value);
            
            // Asignamos la voz seleccionada
            mensaje.voice = voces[listaVoces.value];
            
            // Opcional: Ajustar tono y velocidad
            mensaje.pitch = 1;
            mensaje.rate = 1;

            synth.speak(mensaje);
        }
    });
</script>

</body>
</html>
