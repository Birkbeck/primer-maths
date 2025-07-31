<?php

/**
 * Example Form Configuration with Logging Control
 * 
 * This example shows how to configure logging for individual forms.
 * Each form can have its own logging settings that override global settings.
 */

return [
    // Global email configuration
    'email' => [
        'driver' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'your-email@example.com',
        'password' => 'your-password',
        'encryption' => 'tls',
        'from_email' => 'noreply@example.com',
        'from_name' => 'Your Website'
    ],

    // Form-specific configurations
    'forms' => [

        // Contact form with detailed logging enabled
        'contact_form' => [
            'email' => [
                'enabled' => true,
                'to' => 'contact@example.com',
                'subject' => 'New Contact Form Submission',
                'template' => 'contact'
            ],
            'webhook' => [
                'enabled' => true,
                'url' => 'https://hooks.zapier.com/hooks/catch/your-webhook-url/'
            ],
            'validation' => [
                'name' => 'required|min:2',
                'email' => 'required|email',
                'message' => 'required|min:10'
            ],
            // Logging configuration for this specific form
            'logging' => [
                'enabled' => true,           // Enable/disable logging for this form
                'level' => 'debug',          // Log level: debug, info, warning, error, critical
                'file' => 'contact_form.log', // Custom log file (optional)
                'rotation' => [
                    'enabled' => true,       // Enable log rotation (default: true)
                    'max_files' => 5,        // Keep 5 rotated files (default: 5)
                    'max_size' => '10MB'     // Rotate when file exceeds 10MB (default: 10MB)
                ]
            ]
        ],

        // Newsletter signup with minimal logging
        'newsletter_signup' => [
            'email' => [
                'enabled' => true,
                'to' => 'newsletter@example.com',
                'subject' => 'New Newsletter Signup',
                'template' => 'newsletter'
            ],
            'webhook' => [
                'enabled' => true,
                'url' => 'https://api.mailchimp.com/3.0/lists/your-list-id/members'
            ],
            'validation' => [
                'email' => 'required|email'
            ],
            // Minimal logging for newsletter signups
            'logging' => [
                'enabled' => true,
                'level' => 'info',           // Only log info level and above
                'file' => 'newsletter.log',
                'rotation' => [
                    'enabled' => true,
                    'max_files' => 3,        // Keep fewer files for newsletter
                    'max_size' => '5MB'      // Smaller rotation size
                ]
            ]
        ],

        // Support form with logging disabled
        'support_form' => [
            'email' => [
                'enabled' => true,
                'to' => 'support@example.com',
                'subject' => 'Support Request',
                'template' => 'support'
            ],
            'webhook' => [
                'enabled' => false
            ],
            'validation' => [
                'name' => 'required|min:2',
                'email' => 'required|email',
                'subject' => 'required|min:5',
                'message' => 'required|min:20'
            ],
            // Logging disabled for privacy-sensitive support requests
            'logging' => [
                'enabled' => false  // No logging for this form
            ]
        ],

        // Development form with custom log file path
        'dev_test_form' => [
            'email' => [
                'enabled' => false
            ],
            'webhook' => [
                'enabled' => true,
                'url' => 'https://webhook.site/your-unique-url'
            ],
            'validation' => [],
            // Custom log file path for development
            'logging' => [
                'enabled' => true,
                'level' => 'debug',
                'file' => '/tmp/dev_form_debug.log',  // Absolute path
                'rotation' => [
                    'enabled' => true,
                    'max_files' => 10,       // Keep more files for development
                    'max_size' => '50MB'     // Larger size for development debugging
                ]
            ]
        ]
    ]
];
