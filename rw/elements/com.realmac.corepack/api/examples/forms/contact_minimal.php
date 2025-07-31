<?php

/**
 * Minimal Contact Form Configuration for Testing
 * Email disabled to test form processing only
 */

return [
    'form' => [
        'validation' => [
            'rules' => [
                'name' => 'required|string|max:150',
            ],
            'messages' => [
                'name.required' => 'Please provide your name',
                'name.max' => 'Name must be less than 150 characters',
            ],
        ],

        'email' => [
            'enabled' => false, // Disabled for testing
        ],

        'webhook' => [
            'enabled' => false, // Disabled for testing
        ],

        'response' => [
            'success' => [
                'message' => 'Test: Form processed successfully (no email sent)',
            ],
        ],
    ],
];
