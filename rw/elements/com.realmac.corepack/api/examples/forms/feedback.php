<?php

/**
 * Example Feedback Form Configuration
 * 
 * This file demonstrates a feedback form with:
 * - Rating field validation
 * - Optional email collection
 * - Email notification to support team
 */

return [
    'form' => [
        'validation' => [
            'rules' => [
                'rating' => 'required|integer|min:1|max:5',
                'feedback' => 'required|string|max:2000',
                'email' => 'email', // Optional email
                'name' => 'string|max:100', // Optional name
            ],
            'messages' => [
                'rating.required' => 'Please provide a rating',
                'rating.integer' => 'Rating must be a number',
                'rating.min' => 'Rating must be at least 1',
                'rating.max' => 'Rating cannot be more than 5',
                'feedback.required' => 'Please provide your feedback',
                'feedback.max' => 'Feedback must be less than 2000 characters',
                'email.email' => 'Please provide a valid email address',
                'name.max' => 'Name must be less than 100 characters',
            ],
        ],

        'email' => [
            'enabled' => true,
            'to' => 'support@example.com',
            'subject' => 'New Customer Feedback Received',
            'template' => 'feedback_notification',
        ],

        'webhook' => [
            'enabled' => false,
        ],

        'response' => [
            'success' => [
                'message' => 'Thank you for your feedback! We appreciate your input.',
            ],
            'error' => [
                'message' => 'Sorry, there was an error submitting your feedback. Please try again.',
            ],
        ],
    ],
];
