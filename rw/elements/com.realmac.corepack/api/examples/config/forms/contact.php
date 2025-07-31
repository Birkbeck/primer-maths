<?php

/**
 * Example Contact Form Configuration
 * 
 * This file demonstrates how to configure a contact form with:
 * - Custom validation rules and messages
 * - Email settings with specialized templates
 * - Webhook integration with headers
 * - Custom response messages
 * 
 * Usage:
 * Copy this file to your desired location and modify as needed.
 * Reference it in form submissions using the config_path parameter.
 */

return [
    'validation' => [
        'rules' => [
            'name' => 'required|string|max:150',
            'email' => 'required|email',
            'company' => 'string|max:100',
            'phone' => 'string|max:20',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:3000',
        ],
        'messages' => [
            'name.required' => 'Please provide your name',
            'name.max' => 'Name must be less than 150 characters',
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'company.max' => 'Company name must be less than 100 characters',
            'phone.max' => 'Phone number must be less than 20 characters',
            'subject.required' => 'Please provide a subject',
            'subject.max' => 'Subject must be less than 200 characters',
            'message.required' => 'Please include a message',
            'message.max' => 'Message must be less than 3000 characters',
        ],
    ],

    'email' => [
        'enabled' => true,
        'to' => 'contact@example.com',
        'cc' => [], // Optional: add CC recipients
        'bcc' => [], // Optional: add BCC recipients
        'subject' => 'New Contact Form Submission',
        'template' => 'contact_professional', // Custom template name
        'reply_to' => true, // Use sender's email as reply-to
    ],

    'webhook' => [
        'enabled' => false,
        'url' => 'https://crm.example.com/api/leads',
        'method' => 'POST',
        'format' => 'json', // json or form
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer your-api-token-here',
            'X-Source' => 'website-contact-form',
            'Content-Type' => 'application/json',
        ],
        'retry_attempts' => 3,
        'retry_delay' => 1, // seconds
    ],

    'response' => [
        'success' => [
            'message' => 'Thank you for your message! We will contact you within 24 hours.',
            'redirect' => '', // Optional: redirect URL after success
        ],
        'error' => [
            'message' => 'Sorry, there was an error sending your message. Please try again.',
            'redirect' => '', // Optional: redirect URL after error
        ],
    ],

    'security' => [
        'rate_limit' => [
            'enabled' => true,
            'max_attempts' => 5,
            'window' => 3600, // 1 hour in seconds
        ],
        'honeypot' => [
            'enabled' => true,
            'field_name' => 'website', // Field that should remain empty
        ],
    ],

    'notifications' => [
        'admin_notification' => [
            'enabled' => true,
            'to' => 'admin@example.com',
            'subject' => 'New Contact Form Submission Received',
        ],
        'auto_reply' => [
            'enabled' => true,
            'subject' => 'Thank you for contacting us',
            'template' => 'contact_auto_reply',
        ],
    ],
];
