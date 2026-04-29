(venv) PS C:\xampp\htdocs\DAMPieroOlivares\Primero\Proyecto intermodular\203-Proyectos de Kitcherry\004-entrenamiento IA> python "003-entrenamiento inicial.py"
============================================================
🚀 ENTRENAMIENTO LoRA - KITCHERRY
============================================================
📄 Dataset: data/002-kitcherry_chatbot.jsonl
🧠 Modelo base: Qwen/Qwen2.5-0.5B-Instruct
📁 Salida: outputs/lora-kitcherry
============================================================
Generating train split: 55 examples [00:00, 6494.92 examples/s]
✅ Dataset cargado con 55 ejemplos.
📚 Ejemplos de entrenamiento: 46
🧪 Ejemplos de prueba: 9
config.json: 100%|████████████████████████████████████████████████████████████████████| 659/659 [00:00<00:00, 3.72MB/s]
C:\xampp\htdocs\DAMPieroOlivares\Primero\Proyecto intermodular\203-Proyectos de Kitcherry\004-entrenamiento IA\venv\Lib\site-packages\huggingface_hub\file_download.py:138: UserWarning: `huggingface_hub` cache-system uses symlinks by default to efficiently store duplicated files but your machine does not support them in C:\Users\USUARIO\.cache\huggingface\hub\models--Qwen--Qwen2.5-0.5B-Instruct. Caching files will still work but in a degraded version that might require more space on your disk. This warning can be disabled by setting the `HF_HUB_DISABLE_SYMLINKS_WARNING` environment variable. For more details, see https://huggingface.co/docs/huggingface_hub/how-to-cache#limitations.
To support symlinks on Windows, you either need to activate Developer Mode or to run Python as an administrator. In order to activate developer mode, see this article: https://docs.microsoft.com/en-us/windows/apps/get-started/enable-your-device-for-development
  warnings.warn(message)
tokenizer_config.json: 7.30kB [00:00, 22.2MB/s]
Warning: You are sending unauthenticated requests to the HF Hub. Please set a HF_TOKEN to enable higher rate limits and faster downloads.
vocab.json: 2.78MB [00:00, 17.9MB/s]
merges.txt: 1.67MB [00:00, 20.7MB/s]
tokenizer.json: 7.03MB [00:00, 32.5MB/s]
🧠 Cargando modelo base: Qwen/Qwen2.5-0.5B-Instruct
[transformers] `torch_dtype` is deprecated! Use `dtype` instead!
model.safetensors: 100%|████████████████████████████████████████████████████████████| 988M/988M [01:18<00:00, 12.7MB/s]
Loading weights: 100%|██████████████████████████████████████████████████████████████| 290/290 [00:02<00:00, 110.29it/s]
generation_config.json: 100%|██████████████████████████████████████████████████████████| 242/242 [00:00<00:00, 854kB/s]
✅ LoRA aplicado correctamente.
trainable params: 4,399,104 || all params: 498,431,872 || trainable%: 0.8826
Map: 100%|█████████████████████████████████████████████████████████████████████| 46/46 [00:00<00:00, 389.08 examples/s]
Map: 100%|███████████████████████████████████████████████████████████████████████| 9/9 [00:00<00:00, 443.39 examples/s]
Map: 100%|█████████████████████████████████████████████████████████████████████| 46/46 [00:00<00:00, 807.18 examples/s]
Map: 100%|███████████████████████████████████████████████████████████████████████| 9/9 [00:00<00:00, 513.41 examples/s]
[transformers] warmup_ratio is deprecated and will be removed in v5.2. Use `warmup_steps` instead.
============================================================
🚂 Comenzando entrenamiento en CPU...
============================================================
{'loss': '3.069', 'grad_norm': '3.582', 'learning_rate': '0.0001', 'epoch': '0.8696'}
{'loss': '2.295', 'grad_norm': '2.785', 'learning_rate': '9.265e-05', 'epoch': '1.696'}
{'loss': '1.7', 'grad_norm': '2.88', 'learning_rate': '8.529e-05', 'epoch': '2.522'}
{'loss': '1.121', 'grad_norm': '2.428', 'learning_rate': '7.794e-05', 'epoch': '3.348'}
{'loss': '0.8022', 'grad_norm': '1.691', 'learning_rate': '7.059e-05', 'epoch': '4.174'}
{'loss': '0.5449', 'grad_norm': '0.8837', 'learning_rate': '5.588e-05', 'epoch': '5.87'}
{'loss': '0.4964', 'grad_norm': '0.7511', 'learning_rate': '4.853e-05', 'epoch': '6.696'}
{'loss': '0.4777', 'grad_norm': '0.7821', 'learning_rate': '4.118e-05', 'epoch': '7.522'}
{'loss': '0.4153', 'grad_norm': '0.8225', 'learning_rate': '3.382e-05', 'epoch': '8.348'}
{'loss': '0.4251', 'grad_norm': '0.7983', 'learning_rate': '2.647e-05', 'epoch': '9.174'}
{'loss': '0.3893', 'grad_norm': '0.9339', 'learning_rate': '1.912e-05', 'epoch': '10'}
{'loss': '0.3876', 'grad_norm': '0.7474', 'learning_rate': '1.176e-05', 'epoch': '10.87'}
{'loss': '0.3751', 'grad_norm': '0.8936', 'learning_rate': '4.412e-06', 'epoch': '11.7'}
{'train_runtime': '2401', 'train_samples_per_second': '0.23', 'train_steps_per_second': '0.03', 'train_loss': '0.9205', 'epoch': '12'}
100%|██████████████████████████████████████████████████████████████████████████████████| 72/72 [40:00<00:00, 33.34s/it]
============================================================
🏁 Entrenamiento terminado.
============================================================
🧪 Evaluando modelo...
100%|████████████████████████████████████████████████████████████████████████████████████| 2/2 [00:01<00:00,  1.71it/s]
📊 Métricas de evaluación:
{'eval_loss': 0.5694842338562012, 'eval_runtime': 9.8971, 'eval_samples_per_second': 0.909, 'eval_steps_per_second': 0.202, 'epoch': 12.0}
💾 Guardando adaptador LoRA en: outputs/lora-kitcherry
============================================================
✅ Adaptador LoRA guardado correctamente.
⏱️ Duración total: 0:41:42.979243
📊 Métricas de entrenamiento:
{'train_runtime': 2400.5157, 'train_samples_per_second': 0.23, 'train_steps_per_second': 0.03, 'total_flos': 199683733570560.0, 'train_loss': 0.9205192633801036, 'epoch': 12.0}
============================================================
> python "004-fusionar.py"
============================================================
🔗 FUSIÓN LoRA - KITCHERRY
============================================================
🧠 Modelo base: Qwen/Qwen2.5-0.5B-Instruct
📁 Adaptador LoRA: C:\xampp\htdocs\DAMPieroOlivares\Primero\Proyecto intermodular\203-Proyectos de Kitcherry\004-entrenamiento IA\outputs\lora-kitcherry
📁 Salida fusionada: C:\xampp\htdocs\DAMPieroOlivares\Primero\Proyecto intermodular\203-Proyectos de Kitcherry\004-entrenamiento IA\outputs\modelo-kitcherry-fusionado
⚙️ CUDA disponible: False
⚙️ dtype: torch.float32
🧠 Cargando modelo base...
Warning: You are sending unauthenticated requests to the HF Hub. Please set a HF_TOKEN to enable higher rate limits and faster downloads.
[transformers] `torch_dtype` is deprecated! Use `dtype` instead!
Loading weights: 100%|██████████████████████████████████████████████████████████████| 290/290 [00:00<00:00, 428.20it/s]
🔤 Cargando tokenizer...
📦 Cargando adaptador LoRA...
🔗 Fusionando adaptador con modelo base...
💾 Guardando modelo fusionado...
Writing model shards: 100%|██████████████████████████████████████████████████████████████| 1/1 [00:02<00:00,  2.12s/it]
============================================================
✅ Modelo fusionado correctamente.
📁 Guardado en: C:\xampp\htdocs\DAMPieroOlivares\Primero\Proyecto intermodular\203-Proyectos de Kitcherry\004-entrenamiento IA\outputs\modelo-kitcherry-fusionado
============================================================
(venv) PS C:\xampp\htdocs\DAMPieroOlivares\Primero\Proyecto intermodular\203-Proyectos de Kitcherry\004-entrenamiento IA> python "005-inferencia.py" "¿Qué es Kitcherry?"
🧠 Cargando modelo fusionado Kitcherry...
📁 Modelo: C:\xampp\htdocs\DAMPieroOlivares\Primero\Proyecto intermodular\203-Proyectos de Kitcherry\004-entrenamiento IA\outputs\modelo-kitcherry-fusionado
⚙️ CUDA disponible: False
[transformers] `torch_dtype` is deprecated! Use `dtype` instead!
Loading weights: 100%|█████████████████████████████████████████████████████████████| 290/290 [00:00<00:00, 7015.87it/s]

Respuesta:
Kitcherry es una empresa de herramientas de software para hostelería desarrollada por PieroDev. Se enfoca en mejorar la organización, la comunicación y la atención al cliente en restaurantes, bares y cafeterías.
(venv) PS C:\xampp\htdocs\DAMPieroOlivares\Primero\Proyecto intermodular\203-Proyectos de Kitcherry\004-entrenamiento IA> python "005-inferencia.py"
🧠 Cargando modelo fusionado Kitcherry...
📁 Modelo: C:\xampp\htdocs\DAMPieroOlivares\Primero\Proyecto intermodular\203-Proyectos de Kitcherry\004-entrenamiento IA\outputs\modelo-kitcherry-fusionado
⚙️ CUDA disponible: False
[transformers] `torch_dtype` is deprecated! Use `dtype` instead!
Loading weights: 100%|█████████████████████████████████████████████████████████████| 290/290 [00:00<00:00, 7988.00it/s]

✅ Chat Kitcherry iniciado.
Escribe 'salir' para terminar.

Tú: ¿Kitcherry es un restaurante?

Kitcherry:
No, Kitcherry no se refiere a un restaurante. Es más probable que se refiera al proyecto de software desarrollado por PieroDev para ayudar a pequeños negocios hosteleros a organizar mejor su negocio.

Tú: ¿Qué servicios ofrece Kitcherry?

Kitcherry:
Kitcherry ofrece herramientas de software para gestionar reservas, reservas de comida, chatbot para responder consultas frecuentes, sistemas de anuncios, herramientas de comunicación interna y herramientas de análisis de datos para mejorar la atención al cliente.

Tú: ¿Cuál es el público objetivo de Kitcherry?

Kitcherry:
El público objetivo de Kitcherry es a empresarios, gerentes o directivos de pequeños negocios hosteleros que quieren modernizarse, ahorrar tiempo y mejorar la organización del negocio.

Tú: ¿Kitcherry sustituye al personal humano?

Kitcherry:
No. Kitcherry no busca remplazar al personal humano. Su objetivo es ahorrar tiempo, automatizar tareas repetitivas, mejorar la organización y facilitar mejor las tareas del cliente.

Tú: ¿Cuánto cuesta Kitcherry?

Kitcherry:
El costo puede variar dependiendo del proyecto, la complejidad y los recursos disponibles. En general, se recomienda buscar información detallada o hablar con un experto antes de decidir si Kitcherry es la mejor opción para tu negocio.

Tú: ¿Dónde están las oficinas de Kitcherry?

Kitcherry:
Las oficinas de Kitcherry se encuentran en Barcelona, España.

Tú: ¿Kitcherry garantiza aumentar las ventas?

Kitcherry:
No. Kitcherry no garantiza que la venta aumente. Su objetivo es ayudar a pequeños negocios hosteleros a gestionar mejor su negocio, reducir errores, mejorar la organización y ahorrar tiempo.

Tú: ¿Kitcherry tiene app móvil oficial?

Kitcherry:
No, Kitcherry no tiene un app oficial. Su objetivo principal es ofrecer herramientas digitales útiles para gestionar la atención al cliente, reservas, organización de eventos y otras tareas relacionadas con la cocina y la bebida del restaurante.

Tú: Explícame Kitcherry de forma sencilla

Kitcherry:
Kitcherry es una empresa que ayuda a pequeños negocios de hostelería a organizar mejor sus tareas diarias. Es como un pequeño equipo especializado que te ayuda a responder preguntas frecuentes, organizar reservas, gestionar reservas internas y mucho más.

Tú: Dime en una frase qué hace Kitcherry

Kitcherry:
Kitcherry ayuda a pequeños negocios hosteleros a organizar mejor sus procesos, gestionar mejor la atención al cliente y ahorrar tiempo trabajando con herramientas avanzadas.

Tú: ¿Es Kitcherry una herramienta para restaurantes?

Kitcherry:
No, Kitcherry no se enfoca exclusivamente en restaurantes. Es una herramienta que puede aplicarse a cualquier negocio relacionado con la atención al cliente, la organización del espacio y la gestión de reservas.

Tú: ¿Cómo ayuda Kitcherry a un restaurante pequeño?

Kitcherry:
Kitcherry puede ayudar al restaurante pequeño a organizar mejor la atención al cliente, gestionar reservas, automatizar tareas repetitivas, mejorar la organización del negocio y facilitar la comunicación interna.

Tú: ¿Por qué Kitcherry usa inteligencia artificial?

Kitcherry:
Kitcherry utiliza inteligencia artificial porque puede analizar datos del negocio, responder consultas frecuentes, ayudar a planificar mejor la atención al cliente y mejorar la organización interna.

Tú:
