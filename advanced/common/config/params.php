<?php
return [
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'user.passwordResetTokenExpire' => 3600,
    'user.passwordMinLength' => 13,
    'keycrm.rateLimitIntervalMs' => 2000,
    'keycrm.rateLimitLockTimeout' => 10,
    'novaPoshta.baseUrl' => 'https://api.novaposhta.ua/v2.0/json/',
    'novaPoshta.timeout' => 15,
    'novaPoshta.rateLimitIntervalMs' => 1000,
    'novaPoshta.rateLimitLockTimeout' => 10,

    'novaPoshta.maxAttempts' => 3,
    'novaPoshta.retryBaseDelayMs' => 500,
    'novaPoshta.retryMaxDelayMs' => 5000,
    'novaPoshta.retryJitterMs' => 250,
];
