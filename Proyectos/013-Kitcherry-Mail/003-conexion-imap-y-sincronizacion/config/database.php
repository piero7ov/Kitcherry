<?php
// ==========================================================
// KITCHERRY MAIL
// Configuración de SQLite y funciones de acceso a datos.
// ==========================================================

declare(strict_types=1);

const DB_PATH = __DIR__ . '/../database/kitcherry_mail.sqlite';

/**
 * Devuelve una conexión PDO a SQLite.
 */
function obtenerConexion(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $directorio = dirname(DB_PATH);

    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    crearTablas($pdo);
    crearPlantillasIniciales($pdo);

    return $pdo;
}

/**
 * Crea las tablas principales del proyecto.
 */
function crearTablas(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            imap_host TEXT NOT NULL,
            imap_port INTEGER NOT NULL,
            imap_encryption TEXT NOT NULL,
            smtp_host TEXT NOT NULL,
            smtp_port INTEGER NOT NULL,
            smtp_encryption TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS emails (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            imap_uid TEXT,
            message_id TEXT,
            in_reply_to TEXT,
            references_header TEXT,
            thread_key TEXT,
            folder TEXT NOT NULL DEFAULT 'inbox',
            sender_name TEXT NOT NULL,
            sender_email TEXT NOT NULL,
            recipient_email TEXT NOT NULL,
            subject TEXT NOT NULL,
            summary TEXT NOT NULL,
            body_html TEXT NOT NULL,
            email_date TEXT NOT NULL,
            display_date TEXT NOT NULL,
            is_read INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'pendiente',
            priority TEXT NOT NULL DEFAULT 'media',
            type TEXT NOT NULL DEFAULT 'cliente',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
            UNIQUE(account_id, folder, imap_uid)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email_id INTEGER NOT NULL,
            note TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            body_html TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
            UNIQUE(account_id, setting_key)
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_emails_account_folder ON emails(account_id, folder)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_emails_account_status ON emails(account_id, status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_emails_thread ON emails(thread_key)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notes_email ON notes(email_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_history_email ON history(email_id)");
}

/**
 * Crea plantillas iniciales.
 */
function crearPlantillasIniciales(PDO $pdo): void
{
    $total = (int) $pdo->query("SELECT COUNT(*) FROM templates")->fetchColumn();

    if ($total > 0) {
        return;
    }

    $plantillas = [
        [
            'code' => 'recibido',
            'title' => 'Confirmación de recepción',
            'body_html' => '
                <p>Hola,</p>
                <p>Gracias por contactar con nosotros. Hemos recibido tu mensaje correctamente y lo revisaremos lo antes posible.</p>
                <p>Un saludo,<br>Equipo Kitcherry</p>
            '
        ],
        [
            'code' => 'info',
            'title' => 'Solicitud de más información',
            'body_html' => '
                <p>Hola,</p>
                <p>Gracias por tu mensaje. Para poder ayudarte mejor, necesitaríamos que nos facilites algunos datos adicionales.</p>
                <p>Un saludo,<br>Equipo Kitcherry</p>
            '
        ],
        [
            'code' => 'gracias',
            'title' => 'Agradecimiento',
            'body_html' => '
                <p>Hola,</p>
                <p>Muchas gracias por escribirnos. Quedamos atentos a cualquier otra consulta.</p>
                <p>Un saludo,<br>Equipo Kitcherry</p>
            '
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO templates (code, title, body_html)
        VALUES (:code, :title, :body_html)
    ");

    foreach ($plantillas as $plantilla) {
        $stmt->execute([
            ':code' => $plantilla['code'],
            ':title' => $plantilla['title'],
            ':body_html' => trim($plantilla['body_html'])
        ]);
    }
}

/**
 * Crea o actualiza una cuenta.
 * La contraseña no se guarda en SQLite.
 */
function guardarCuenta(PDO $pdo, array $datos): int
{
    $email = trim((string) $datos['email']);

    $stmtBuscar = $pdo->prepare("
        SELECT id
        FROM accounts
        WHERE email = :email
        LIMIT 1
    ");

    $stmtBuscar->execute([
        ':email' => $email
    ]);

    $cuenta = $stmtBuscar->fetch();

    if ($cuenta) {
        $stmtActualizar = $pdo->prepare("
            UPDATE accounts
            SET imap_host = :imap_host,
                imap_port = :imap_port,
                imap_encryption = :imap_encryption,
                smtp_host = :smtp_host,
                smtp_port = :smtp_port,
                smtp_encryption = :smtp_encryption,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmtActualizar->execute([
            ':imap_host' => trim((string) $datos['imap_host']),
            ':imap_port' => (int) $datos['imap_port'],
            ':imap_encryption' => trim((string) $datos['imap_encryption']),
            ':smtp_host' => trim((string) $datos['smtp_host']),
            ':smtp_port' => (int) $datos['smtp_port'],
            ':smtp_encryption' => trim((string) $datos['smtp_encryption']),
            ':id' => (int) $cuenta['id']
        ]);

        return (int) $cuenta['id'];
    }

    $stmtInsertar = $pdo->prepare("
        INSERT INTO accounts (
            email,
            imap_host,
            imap_port,
            imap_encryption,
            smtp_host,
            smtp_port,
            smtp_encryption
        ) VALUES (
            :email,
            :imap_host,
            :imap_port,
            :imap_encryption,
            :smtp_host,
            :smtp_port,
            :smtp_encryption
        )
    ");

    $stmtInsertar->execute([
        ':email' => $email,
        ':imap_host' => trim((string) $datos['imap_host']),
        ':imap_port' => (int) $datos['imap_port'],
        ':imap_encryption' => trim((string) $datos['imap_encryption']),
        ':smtp_host' => trim((string) $datos['smtp_host']),
        ':smtp_port' => (int) $datos['smtp_port'],
        ':smtp_encryption' => trim((string) $datos['smtp_encryption'])
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Obtiene una cuenta por ID.
 */
function obtenerCuentaPorId(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM accounts
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $cuenta = $stmt->fetch();

    return $cuenta ?: null;
}

/**
 * Guarda o actualiza un correo sincronizado desde IMAP.
 */
function guardarCorreoSincronizado(PDO $pdo, int $accountId, array $correo): string
{
    $stmtBuscar = $pdo->prepare("
        SELECT id
        FROM emails
        WHERE account_id = :account_id
          AND folder = :folder
          AND imap_uid = :imap_uid
        LIMIT 1
    ");

    $stmtBuscar->execute([
        ':account_id' => $accountId,
        ':folder' => $correo['folder'],
        ':imap_uid' => $correo['imap_uid']
    ]);

    $existente = $stmtBuscar->fetch();

    if ($existente) {
        $stmtActualizar = $pdo->prepare("
            UPDATE emails
            SET message_id = :message_id,
                in_reply_to = :in_reply_to,
                references_header = :references_header,
                thread_key = :thread_key,
                sender_name = :sender_name,
                sender_email = :sender_email,
                recipient_email = :recipient_email,
                subject = :subject,
                summary = :summary,
                body_html = :body_html,
                email_date = :email_date,
                display_date = :display_date,
                is_read = :is_read,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND account_id = :account_id
        ");

        $stmtActualizar->execute([
            ':message_id' => $correo['message_id'],
            ':in_reply_to' => $correo['in_reply_to'],
            ':references_header' => $correo['references_header'],
            ':thread_key' => $correo['thread_key'],
            ':sender_name' => $correo['sender_name'],
            ':sender_email' => $correo['sender_email'],
            ':recipient_email' => $correo['recipient_email'],
            ':subject' => $correo['subject'],
            ':summary' => $correo['summary'],
            ':body_html' => $correo['body_html'],
            ':email_date' => $correo['email_date'],
            ':display_date' => $correo['display_date'],
            ':is_read' => $correo['is_read'],
            ':id' => (int) $existente['id'],
            ':account_id' => $accountId
        ]);

        return 'updated';
    }

    $stmtInsertar = $pdo->prepare("
        INSERT INTO emails (
            account_id,
            imap_uid,
            message_id,
            in_reply_to,
            references_header,
            thread_key,
            folder,
            sender_name,
            sender_email,
            recipient_email,
            subject,
            summary,
            body_html,
            email_date,
            display_date,
            is_read,
            status,
            priority,
            type
        ) VALUES (
            :account_id,
            :imap_uid,
            :message_id,
            :in_reply_to,
            :references_header,
            :thread_key,
            :folder,
            :sender_name,
            :sender_email,
            :recipient_email,
            :subject,
            :summary,
            :body_html,
            :email_date,
            :display_date,
            :is_read,
            :status,
            :priority,
            :type
        )
    ");

    $stmtInsertar->execute([
        ':account_id' => $accountId,
        ':imap_uid' => $correo['imap_uid'],
        ':message_id' => $correo['message_id'],
        ':in_reply_to' => $correo['in_reply_to'],
        ':references_header' => $correo['references_header'],
        ':thread_key' => $correo['thread_key'],
        ':folder' => $correo['folder'],
        ':sender_name' => $correo['sender_name'],
        ':sender_email' => $correo['sender_email'],
        ':recipient_email' => $correo['recipient_email'],
        ':subject' => $correo['subject'],
        ':summary' => $correo['summary'],
        ':body_html' => $correo['body_html'],
        ':email_date' => $correo['email_date'],
        ':display_date' => $correo['display_date'],
        ':is_read' => $correo['is_read'],
        ':status' => $correo['status'],
        ':priority' => $correo['priority'],
        ':type' => $correo['type']
    ]);

    $emailId = (int) $pdo->lastInsertId();

    registrarHistorialCorreo($pdo, $emailId, 'Correo sincronizado desde IMAP.');

    return 'inserted';
}

/**
 * Devuelve correos en formato compatible con JS.
 */
function obtenerCorreosParaVista(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM emails
        WHERE account_id = :account_id
        ORDER BY datetime(email_date) DESC, id DESC
    ");

    $stmt->execute([
        ':account_id' => $accountId
    ]);

    $correos = $stmt->fetchAll();

    $notas = obtenerNotasAgrupadas($pdo);
    $historial = obtenerHistorialAgrupado($pdo);

    $resultado = [];

    foreach ($correos as $correo) {
        $id = (int) $correo['id'];

        $resultado[] = [
            'id' => $id,
            'carpeta' => $correo['folder'],
            'remitente' => $correo['sender_name'],
            'email' => $correo['sender_email'],
            'destinatario' => $correo['recipient_email'],
            'asunto' => $correo['subject'],
            'fecha' => $correo['display_date'],
            'vistaFecha' => $correo['display_date'],
            'resumen' => $correo['summary'],
            'cuerpo' => $correo['body_html'],
            'leido' => (bool) $correo['is_read'],
            'estado' => $correo['status'],
            'prioridad' => $correo['priority'],
            'tipo' => $correo['type'],
            'notas' => $notas[$id] ?? [],
            'historial' => $historial[$id] ?? []
        ];
    }

    return $resultado;
}

/**
 * Agrupa notas por correo.
 */
function obtenerNotasAgrupadas(PDO $pdo): array
{
    $filas = $pdo->query("
        SELECT email_id, note
        FROM notes
        ORDER BY id DESC
    ")->fetchAll();

    $notas = [];

    foreach ($filas as $fila) {
        $emailId = (int) $fila['email_id'];
        $notas[$emailId][] = $fila['note'];
    }

    return $notas;
}

/**
 * Agrupa historial por correo.
 */
function obtenerHistorialAgrupado(PDO $pdo): array
{
    $filas = $pdo->query("
        SELECT email_id, action
        FROM history
        ORDER BY id DESC
    ")->fetchAll();

    $historial = [];

    foreach ($filas as $fila) {
        $emailId = (int) $fila['email_id'];
        $historial[$emailId][] = $fila['action'];
    }

    return $historial;
}

/**
 * Devuelve plantillas como array para JS.
 */
function obtenerPlantillasParaVista(PDO $pdo): array
{
    $filas = $pdo->query("
        SELECT code, body_html
        FROM templates
        ORDER BY id ASC
    ")->fetchAll();

    $plantillas = [];

    foreach ($filas as $fila) {
        $plantillas[$fila['code']] = $fila['body_html'];
    }

    return $plantillas;
}

/**
 * Registra historial en un correo.
 */
function registrarHistorialCorreo(PDO $pdo, int $emailId, string $accion): void
{
    $stmt = $pdo->prepare("
        INSERT INTO history (email_id, action)
        VALUES (:email_id, :action)
    ");

    $stmt->execute([
        ':email_id' => $emailId,
        ':action' => $accion
    ]);
}

/**
 * Crea resumen desde HTML.
 */
function crearResumenDesdeHtml(string $html, int $limite = 100): string
{
    $texto = trim(strip_tags($html));
    $texto = preg_replace('/\s+/', ' ', $texto);

    if ($texto === '') {
        return 'Mensaje sin contenido';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($texto, 'UTF-8') > $limite) {
            return mb_substr($texto, 0, $limite, 'UTF-8') . '...';
        }

        return $texto;
    }

    if (strlen($texto) > $limite) {
        return substr($texto, 0, $limite) . '...';
    }

    return $texto;
}