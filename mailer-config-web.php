<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Auto-detect PHPMailer "src" folder under public_html.
 * The file structure must contain PHPMailer.php, SMTP.php, Exception.php.
 */
$candidates = [
    __DIR__ . '/src',                          // public_html/src
    __DIR__ . '/PHPMailer/src',               // public_html/PHPMailer/src
    __DIR__ . '/phpmailer/src',               // public_html/phpmailer/src
    __DIR__ . '/vendor/phpmailer/phpmailer/src', // public_html/vendor/phpmailer/phpmailer/src
];

$base = null;
foreach ($candidates as $c) {
    if (is_dir($c) && file_exists($c . '/PHPMailer.php')) {
        $base = $c;
        break;
    }
}

if ($base === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "PHPMailer not found.\n";
    echo "Please place the PHPMailer 'src' folder in ONE of these paths under public_html:\n";
    foreach ($candidates as $p) {
        echo " - " . str_replace(__DIR__ . '/', '', $p) . "\n";
    }
    exit;
}

require_once $base . '/Exception.php';
require_once $base . '/PHPMailer.php';
require_once $base . '/SMTP.php';

/** ====== EDIT THESE WITH YOUR REAL SMTP DETAILS ====== */
const MAIL_FROM = 'chefaicatering09@gmail.com';     // or no-reply@chefaiph.site
const MAIL_NAME = 'ChefAI';
const SMTP_HOST = 'smtp.gmail.com';                  // Hostinger: smtp.hostinger.com
const SMTP_PORT = 587;                               // 587 (STARTTLS) or 465 (SMTPS)
const SMTP_USER = 'chefaicatering09@gmail.com';      // full mailbox
const SMTP_PASS = 'ceuwbzqizxiqkovz';       // Gmail App Password / mailbox password
/** ==================================================== */

function make_mailer(): PHPMailer {
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = SMTP_HOST;
    $m->SMTPAuth   = true;
    $m->Username   = SMTP_USER;
    $m->Password   = SMTP_PASS;
    $m->SMTPSecure = (SMTP_PORT === 465)
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $m->Port       = SMTP_PORT;
    $m->setFrom(MAIL_FROM, MAIL_NAME);
    $m->isHTML(true);
    return $m;
}
