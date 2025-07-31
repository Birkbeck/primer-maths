<?php

/**
 * Example form configuration with spam protection enabled
 * 
 * This demonstrates how to configure spam protection for a form.
 * Copy this configuration and modify it for your specific needs.
 */

return [
    // Email configuration
    'email' => [
        'enabled' => true,
        'to' => 'contact@example.com',
        'from_email' => 'noreply@example.com',
        'from_name' => 'Contact Form',
        'subject' => 'New Contact Form Submission',
        'template' => 'contact',
        'driver' => 'smtp',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'your-smtp-username',
        'smtp_password' => 'your-smtp-password',
        'smtp_encryption' => 'tls'
    ],

    // Spam protection configuration
    'spam_protection' => [
        'enabled' => true,
        'provider' => 'recaptcha', // Options: 'recaptcha', 'hcaptcha', 'turnstile'

        // Provider-specific settings (optional - will use global config if not specified)
        'providers' => [
            'recaptcha' => [
                'secret_key' => 'your-recaptcha-secret-key',
                'site_key' => 'your-recaptcha-site-key',
                'score_threshold' => 0.5, // For reCAPTCHA v3
                'timeout' => 5
            ],
            'hcaptcha' => [
                'secret_key' => 'your-hcaptcha-secret-key',
                'site_key' => 'your-hcaptcha-site-key',
                'timeout' => 5
            ],
            'turnstile' => [
                'secret_key' => 'your-turnstile-secret-key',
                'site_key' => 'your-turnstile-site-key',
                'timeout' => 5
            ]
        ]
    ],

    // Form validation rules
    'validation' => [
        'rules' => [
            'name' => 'required|string|max:100',
            'email' => 'required|email',
            'message' => 'required|string|max:1000'
        ]
    ],

    // Webhook configuration (optional)
    'webhook' => [
        'enabled' => false,
        'url' => 'https://your-webhook-endpoint.com/contact',
        'method' => 'POST',
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer your-webhook-token'
        ]
    ]
];
