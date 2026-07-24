<?php
function sendFreeSMS($number, $carrier, $message) {
    // Common carrier email-to-SMS gateways
    $carriers = [
        'att' => 'txt.att.net',
        'tmobile' => 'tmomail.net',
        'verizon' => 'vtext.com',
        'sprint' => 'messaging.sprintpcs.com'
    ];
    
    if (isset($carriers[strtolower($carrier)])) {
        $to = $number . '@' . $carriers[strtolower($carrier)];
        $subject = '';
        $headers = 'From: your_email@example.com';
        
        return mail($to, $subject, $message, $headers);
    }
    
    return false;
}

// Usage example
$sent = sendFreeSMS('1234567890', 'verizon', 'This is a test SMS message');
if ($sent) {
    echo "SMS sent successfully!";
} else {
    echo "Failed to send SMS.";
}
?>