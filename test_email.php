<?php
include 'mail_config.php';

$result = sendVerificationEmail('nanixd821@gmail.com', 'test-token-123');

echo "<pre>";
if ($result['success']) {
    echo "SUCCESS: Email sent! Check your inbox and spam folder.";
} else {
    echo "ERROR: " . $result['error'];
}
echo "</pre>";
?>