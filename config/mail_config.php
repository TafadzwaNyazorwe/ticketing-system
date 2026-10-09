<?php
/**
 * =====================================================================
 * PURPLE HELPDESK :: SMTP / PHPMAILER CONFIGURATION
 * Fill these in with your real SMTP provider details
 * (Gmail SMTP, Outlook365, SendGrid, Mailgun, your company mail server, etc).
 * =====================================================================
 */

return [
    // Set to false to silently disable outgoing email (useful while developing locally)
    'enabled'      => true,

    'host'         => 'smtp.yourprovider.com',
    'port'         => 587,                 // 587 = TLS, 465 = SSL
    'encryption'   => 'tls',               // 'tls' or 'ssl'
    'smtp_auth'    => true,
    'username'     => 'notifications@yourcompany.com',
    'password'     => 'your-smtp-password-or-app-password',

    'from_email'   => 'notifications@yourcompany.com',
    'from_name'    => 'Purple Helpdesk',

    // BCC every outgoing notification to this address (optional, leave blank to disable)
    'bcc'          => '',

    // Turn on verbose SMTP debug output in error logs (0 = off, 2 = verbose)
    'debug_level'  => 0,
];
