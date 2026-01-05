<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

ini_set('display_errors', '0'); // en producción 0; si quieres ver en local pon 1
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
  exit;
}

function clean(string $v): string {
  return trim(preg_replace('/\s+/', ' ', $v));
}

$nombre   = clean($_POST['nombre']   ?? '');
$apellido = clean($_POST['apellido'] ?? '');
$telefono = clean($_POST['telefono'] ?? '');
$email    = clean($_POST['email']    ?? '');
$servicio = clean($_POST['servicio'] ?? '');
$mensaje  = trim($_POST['mensaje']   ?? '');

if ($nombre === '' || $apellido === '' || $telefono === '' || $email === '' || $servicio === '' || $mensaje === '') {
  http_response_code(422);
  echo json_encode(['ok' => false, 'message' => 'Faltan campos obligatorios.']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'message' => 'Correo inválido.']);
  exit;
}

/**
 * TEMPORAL: si sigues con mail(), esto va a fallar en localhost.
 * Aquí te dejo el mensaje armado y el mail() por si quieres mantenerlo,
 * pero lo correcto es PHPMailer + SMTP (abajo te lo dejo).
 */
$to = 'atencion@cipmexico.com.mx';
$subject = 'Nuevo contacto desde CIP Financial Group';
$body =
"Nuevo mensaje desde el formulario:\n\n".
"Nombre: $nombre $apellido\n".
"Teléfono: $telefono\n".
"Correo: $email\n".
"Servicio: $servicio\n\n".
"Mensaje:\n$mensaje\n";

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-type: text/plain; charset=UTF-8';
// OJO: en mail() NO conviene usar From con el correo del usuario (lo bloquean). Usa tu dominio.
$headers[] = 'From: CIP Financial Group <no-reply@cipmexico.com.mx>';
$headers[] = 'Reply-To: '.$email;

$sent = @mail($to, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'No se pudo enviar el correo (mail() falló). En localhost es normal: necesitas SMTP (PHPMailer).']);
  exit;
}

echo json_encode(['ok' => true, 'message' => 'Mensaje enviado correctamente.']);
