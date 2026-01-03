<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
  exit;
}

// Honeypot anti-bots (si viene lleno => bot)
$honeypot = trim($_POST['website'] ?? '');
if ($honeypot !== '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Solicitud inválida']);
  exit;
}

// Campos
$nombre   = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email    = trim($_POST['email'] ?? '');
$servicio = trim($_POST['servicio'] ?? '');
$mensaje  = trim($_POST['mensaje'] ?? '');

if ($nombre === '' || $apellido === '' || $telefono === '' || $email === '' || $servicio === '' || $mensaje === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Faltan campos obligatorios.']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Correo inválido.']);
  exit;
}

// Evita header injection
$email_safe = str_replace(["\r", "\n"], '', $email);

// ===============================
// MODO LOCALHOST (sin SMTP)
// ===============================
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$isLocal = in_array($serverName, ['localhost', '127.0.0.1'], true);

if ($isLocal) {
  // Si quieres, aquí puedes guardar en un log local
  // file_put_contents(__DIR__ . '/contacto_local.log', print_r($_POST, true), FILE_APPEND);

  echo json_encode([
    'ok' => true,
    'message' => 'Mensaje recibido (modo local). En producción sí se enviará por correo.'
  ]);
  exit;
}

// ===============================
// PRODUCCIÓN (SMTP con PHPMailer)
// ===============================
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$smtpHost = getenv('SMTP_HOST') ?: '';
$smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
$smtpUser = getenv('SMTP_USER') ?: '';
$smtpPass = getenv('SMTP_PASS') ?: '';
$smtpFrom = getenv('SMTP_FROM') ?: $smtpUser; // ej: atencion@cipmexico.com.mx
$smtpName = getenv('SMTP_NAME') ?: 'CIP Financial Group';

$toEmail  = getenv('MAIL_TO') ?: 'atencion@cipmexico.com.mx';
$toName   = getenv('MAIL_TO_NAME') ?: 'Atención CIP';

if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '') {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Faltan variables SMTP (SMTP_HOST/SMTP_USER/SMTP_PASS).'
  ]);
  exit;
}

$subject = "Contacto CIP - " . mb_strtoupper($servicio, 'UTF-8');

$body =
"Nuevo mensaje desde CIP Financial Group\n\n" .
"Nombre: $nombre $apellido\n" .
"Teléfono: $telefono\n" .
"Correo: $email\n" .
"Servicio: $servicio\n\n" .
"Mensaje:\n$mensaje\n";

try {
  $mail = new PHPMailer(true);
  $mail->CharSet = 'UTF-8';

  $mail->isSMTP();
  $mail->Host       = $smtpHost;
  $mail->SMTPAuth   = true;
  $mail->Username   = $smtpUser;
  $mail->Password   = $smtpPass;
  $mail->Port       = $smtpPort;

  // 587 -> TLS
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

  // Remitente (el correo real de tu empresa / app)
  $mail->setFrom($smtpFrom, $smtpName);

  // Destino (atención)
  $mail->addAddress($toEmail, $toName);

  // Para que al "Responder" se vaya al correo del usuario
  $mail->addReplyTo($email_safe, $nombre . ' ' . $apellido);

  $mail->Subject = $subject;
  $mail->Body    = $body;

  $mail->send();

  echo json_encode(['ok' => true, 'message' => 'Mensaje enviado correctamente']);
  exit;

} catch (Exception $e) {
  http_response_code(500);
echo json_encode([
  'ok' => false,
  'debug_smtp_host' => $smtpHost,
  'debug_smtp_port' => $smtpPort
], JSON_UNESCAPED_UNICODE);
exit;
}
