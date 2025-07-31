<?php return array (
  'APP_ENV' => 'production',
  'APP_DEBUG' => false,
  'email' => 
  array (
    'driver' => 'smtp',
    'from_email' => 'production@example.com',
    'from_name' => 'Production Website',
    'smtp_host' => 'smtp.production.com',
    'smtp_port' => 587,
    'smtp_username' => 'production_user',
    'smtp_password' => 'production_password',
    'smtp_encryption' => 'tls',
  ),
  'forms' => 
  array (
    'contact' => 
    array (
      'validation' => 
      array (
        'rules' => 
        array (
          'name' => 'required|string|max:100',
          'email' => 'required|email',
          'message' => 'required|string|max:2000',
        ),
      ),
      'email' => 
      array (
        'enabled' => true,
        'to' => 'contact@production.com',
        'subject' => 'Production Contact Form',
        'template' => 'professional',
      ),
      'webhook' => 
      array (
        'enabled' => true,
        'url' => 'https://api.production.com/webhooks/contact',
        'method' => 'POST',
        'format' => 'json',
      ),
    ),
    'newsletter' => 
    array (
      'validation' => 
      array (
        'rules' => 
        array (
          'email' => 'required|email',
        ),
      ),
      'email' => 
      array (
        'enabled' => false,
      ),
      'webhook' => 
      array (
        'enabled' => true,
        'url' => 'https://api.production.com/webhooks/newsletter',
        'method' => 'POST',
        'format' => 'json',
      ),
    ),
  ),
);