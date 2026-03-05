<?php
/**
 * pagos/procesar_pago.php
 */

// ── Los use SIEMPRE van al inicio, fuera de cualquier if ──────────────────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php';

session_start();
header('Content-Type: application/json');

// Capturar cualquier error PHP y devolverlo como JSON en lugar de HTML
set_error_handler(function($errno, $errstr) {
    echo json_encode(['exito' => false, 'mensaje' => "PHP Error [$errno]: $errstr"]);
    exit;
});

$input  = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? '';

function responder(array $data): void {
    echo json_encode($data);
    exit;
}

function generarToken(): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $token = 'tok_gm_';
    for ($i = 0; $i < 10; $i++) {
        $token .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $token;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACCIÓN 1 — enviar_codigo
// ─────────────────────────────────────────────────────────────────────────────
if ($accion === 'enviar_codigo') {

    $correo = trim($input['correo'] ?? '');
    $codigo = trim($input['codigo'] ?? '');

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($codigo) !== 6) {
        responder(['exito' => false, 'mensaje' => 'Datos inválidos.']);
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gimori66@gmail.com';
        $mail->Password   = 'njobvdpzfwrjtrss';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('gimori66@gmail.com', 'GameMaster México');
        $mail->addAddress($correo);

        $mail->isHTML(true);
        $mail->Subject = 'Tu código de verificación — GameMaster México';
        $mail->Body    = "
        <div style='background:#030508;color:#e2e5f0;padding:40px;font-family:sans-serif;'>
            <div style='max-width:480px;margin:auto;background:#0c0f16;
                        border:1px solid #1e2336;border-radius:20px;overflow:hidden;'>
                <div style='background:#1d4ed8;padding:20px;text-align:center;'>
                    <h1 style='margin:0;color:white;font-size:20px;'>GAMEMASTER MEXICO</h1>
                    <p style='margin:4px 0 0;color:#bfdbfe;font-size:13px;'>Verificacion de identidad</p>
                </div>
                <div style='padding:32px;text-align:center;'>
                    <p style='color:#94a3b8;font-size:14px;margin-bottom:24px;'>
                        Ingresa este codigo en la tienda para autorizar tu compra:
                    </p>
                    <div style='background:#1e293b;border:2px solid #3b82f6;
                                border-radius:16px;padding:24px;display:inline-block;'>
                        <span style='font-size:42px;font-weight:900;letter-spacing:10px;
                                     color:#60a5fa;font-family:monospace;'>
                            {$codigo}
                        </span>
                    </div>
                    <p style='color:#64748b;font-size:12px;margin-top:20px;'>
                        Este codigo expira en <strong style='color:#f59e0b;'>10 minutos</strong>.<br>
                        Si no solicitaste esta compra, ignora este mensaje.
                    </p>
                </div>
                <div style='background:#0f172a;padding:16px;text-align:center;'>
                    <p style='color:#334155;font-size:11px;margin:0;'>GameMaster Mexico - Tienda Oficial</p>
                </div>
            </div>
        </div>";

        $mail->AltBody = "Tu codigo de verificacion GameMaster es: {$codigo}. Expira en 10 minutos.";
        $mail->send();
        responder(['exito' => true]);

    } catch (Exception $e) {
        responder([
            'exito'   => false,
            'mensaje' => $mail->ErrorInfo,
            'debug'   => $e->getMessage()
        ]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ACCIÓN 2 — finalizar_compra
// ─────────────────────────────────────────────────────────────────────────────
if ($accion === 'finalizar_compra') {

    $carrito = $input['carrito'] ?? [];
    $total   = floatval($input['total']  ?? 0);
    $correo  = trim($input['correo']     ?? '');

    if (empty($carrito) || $total <= 0 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responder(['exito' => false, 'mensaje' => 'Datos de compra invalidos.']);
    }

    $_SESSION['carrito'] = $carrito;
    $_SESSION['total']   = $total;
    $_SESSION['correo']  = $correo;
    $_SESSION['token']   = generarToken();

    // Guardar el token antes de que enviar_correo.php limpie la sesión
    $token_compra = $_SESSION['token'];

    try {
        require __DIR__ . '/../enviar_correo.php';
        responder(['exito' => true, 'token' => $token_compra]);
    } catch (\Throwable $e) {
        responder([
            'exito'          => true,
            'token'          => $token_compra,
            'correo_enviado' => false,
            'aviso'          => 'Compra registrada, pero no se pudo enviar el recibo: ' . $e->getMessage()
        ]);
    }
}

// Acción desconocida
responder(['exito' => false, 'mensaje' => 'Accion no reconocida: ' . $accion]);
