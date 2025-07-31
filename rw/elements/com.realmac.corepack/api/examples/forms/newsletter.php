<?php

/**
 * Example Newsletter Signup Configuration
 * 
 * This file demonstrates a simple newsletter signup form with:
 * - Minimal validation (email only)
 * - Webhook integration for email marketing
 * - Simple success message
 */

return [
    'form' => [
        'validation' => [
            'rules' => [
                'email' => 'required|email',
                'name' => 'string|max:100', // Optional name field
            ],
            'messages' => [
                'email.required' => 'Email address is required',
                'email.email' => 'Please provide a valid email address',
                'name.max' => 'Name must be less than 100 characters',
            ],
        ],

        'email' => [
            'enabled' => false, // Usually disabled for newsletters, using webhook instead
        ],

        'webhook' => [
            'enabled' => true,
            'url' => 'https://api.mailchimp.com/3.0/lists/your-list-id/members',
            'method' => 'POST',
            'format' => 'json',
            'headers' => [
                'Authorization' => 'Basic your-mailchimp-api-key',
                'Content-Type' => 'application/json',
            ],
            'data_mapping' => [
                'email_address' => 'email',
                'status' => 'subscribed',
                'merge_fields' => [
                    'FNAME' => 'name',
                ],
            ],
        ],

        'response' => [
            'success' => [
                'message' => 'Thank you for subscribing to our newsletter!',
            ],
            'error' => [
                'message' => 'Sorry, there was an error with your subscription. Please try again.',
            ],
        ],
    ],
];
