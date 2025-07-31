# Spam Protection System

This document explains how to configure and use the spam protection system for form submissions.

## Overview

The spam protection system provides flexible, per-form protection against spam submissions using popular CAPTCHA providers:

-   **reCAPTCHA** (Google) - Supports both v2 and v3
-   **hCAPTCHA** - Privacy-focused alternative
-   **Turnstile** (Cloudflare) - Fast and privacy-friendly

## Features

-   **Per-form configuration** - Each form can have different spam protection settings
-   **Multiple providers** - Support for reCAPTCHA, hCAPTCHA, and Turnstile
-   **Flexible integration** - Middleware-based (automatic) or service-based (manual)
-   **Configurable thresholds** - Set score thresholds for reCAPTCHA v3
-   **Comprehensive logging** - All verification attempts are logged
-   **Graceful fallbacks** - Forms work even if spam protection is misconfigured

## Configuration

### Global Configuration

Add spam protection configuration to your `config.php`:

```php
'spam_protection' => [
    'enabled' => false, // Global default - can be overridden per form
    'default_provider' => 'recaptcha',
    'providers' => [
        'recaptcha' => [
            'secret_key' => 'your-recaptcha-secret-key',
            'site_key' => 'your-recaptcha-site-key',   // Optional, for client-side reference
            'score_threshold' => 0.5, // For reCAPTCHA v3 (0.0 to 1.0)
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
]
```

### Per-Form Configuration

Each form can override the global settings by adding a `spam_protection` section to its configuration:

```php
// In your form configuration file (e.g., contact-form.php)
return [
    'email' => [
        'enabled' => true,
        'to' => 'contact@example.com',
        // ... other email settings
    ],
    'spam_protection' => [
        'enabled' => true,
        'provider' => 'hcaptcha', // Override global default
        'providers' => [
            'hcaptcha' => [
                'secret_key' => 'form-specific-secret-key',
                'timeout' => 10 // Override global timeout
            ]
        ]
    ]
];
```

## Usage

### Automatic Protection (Middleware)

The spam protection middleware is automatically applied to form submission routes:

-   `POST /email` - Email form submissions
-   `POST /webhook` - Webhook form submissions

No additional code is required - the middleware handles verification automatically.

### Manual Protection (Service)

You can also verify spam protection manually in your controllers:

```php
use App\Services\SpamProtectionService;

class CustomController extends BaseController
{
    private SpamProtectionService $spamProtectionService;

    public function submit(Request $request, Response $response): Response
    {
        // Load form configuration
        $formConfig = $this->loadFormConfig($request);

        // Verify spam protection
        $result = $this->spamProtectionService->verify($request, $formConfig);

        if ($result->isFailure()) {
            return $this->errorResponse($response, $result->getError(), 400);
        }

        // Process form submission...
    }
}
```

## Client-Side Integration

### reCAPTCHA v2

```html
<script src="https://www.google.com/recaptcha/api.js"></script>
<form>
    <!-- Your form fields -->
    <div class="g-recaptcha" data-sitekey="your-site-key"></div>
    <button type="submit">Submit</button>
</form>
```

### reCAPTCHA v3

```html
<script src="https://www.google.com/recaptcha/api.js?render=your-site-key"></script>
<script>
    grecaptcha.ready(function () {
        document
            .getElementById("submit-form")
            .addEventListener("submit", function (e) {
                e.preventDefault();
                grecaptcha
                    .execute("your-site-key", { action: "submit" })
                    .then(function (token) {
                        document.getElementById("recaptcha-token").value =
                            token;
                        e.target.submit();
                    });
            });
    });
</script>
<form id="submit-form">
    <!-- Your form fields -->
    <input type="hidden" name="recaptcha_token" id="recaptcha-token" />
    <button type="submit">Submit</button>
</form>
```

### hCAPTCHA

```html
<script src="https://js.hcaptcha.com/1/api.js"></script>
<form>
    <!-- Your form fields -->
    <div class="h-captcha" data-sitekey="your-site-key"></div>
    <button type="submit">Submit</button>
</form>
```

### Turnstile

```html
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>
<form>
    <!-- Your form fields -->
    <div class="cf-turnstile" data-sitekey="your-site-key"></div>
    <button type="submit">Submit</button>
</form>
```

## Token Field Names

The system automatically looks for tokens in these fields (in order of preference):

### reCAPTCHA

-   `g-recaptcha-response` (v2 default)
-   `recaptcha_token` (v3 custom)
-   `captcha_token` (generic)

### hCAPTCHA

-   `h-captcha-response` (default)
-   `hcaptcha_token` (custom)
-   `captcha_token` (generic)

### Turnstile

-   `cf-turnstile-response` (default)
-   `turnstile_token` (custom)
-   `captcha_token` (generic)

## Error Handling

### Common Error Codes

-   `missing_token` - No CAPTCHA token provided
-   `invalid_token` - CAPTCHA token is invalid
-   `provider_error` - Error communicating with CAPTCHA service
-   `provider_not_found` - Specified provider doesn't exist
-   `provider_not_configured` - Provider is missing required configuration
-   `score_too_low` - reCAPTCHA v3 score below threshold

### Example Error Response

```json
{
    "success": false,
    "error": "reCAPTCHA verification failed",
    "error_code": "invalid_token",
    "timestamp": "2024-01-15T10:30:00Z"
}
```

## Testing

### Development Mode

In development, you can disable spam protection globally:

```php
'spam_protection' => [
    'enabled' => false, // Disable globally for development
    // ... other settings
]
```

### Test Keys

Most providers offer test keys for development:

**reCAPTCHA Test Keys:**

-   Site key: `6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI`
-   Secret key: `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe`

**hCAPTCHA Test Keys:**

-   Site key: `10000000-ffff-ffff-ffff-000000000001`
-   Secret key: `0x0000000000000000000000000000000000000000`

## Troubleshooting

### Common Issues

1. **"Missing CAPTCHA token"**

    - Ensure the CAPTCHA widget is properly loaded
    - Check that the form field names match expected token fields
    - Verify the CAPTCHA is completed before form submission

2. **"CAPTCHA verification failed"**

    - Check your secret key is correct
    - Ensure the site key matches the secret key
    - Verify the domain is authorized for your keys

3. **"Provider not configured"**

    - Ensure the secret key is set in configuration
    - Check that the provider name matches exactly

4. **"Score too low" (reCAPTCHA v3)**
    - Lower the score threshold in configuration
    - Implement user-friendly fallback (e.g., show v2 CAPTCHA)

### Debug Mode

Enable debug information by setting `APP_ENV=development` in your environment. This will include additional metadata in error responses.

### Logging

All spam protection attempts are logged with details:

```
[INFO] Spam protection verification passed
[WARNING] Spam protection verification failed
[ERROR] Spam protection provider error
```

## Security Considerations

1. **Keep secret keys secure** - Never expose secret keys in client-side code
2. **Use HTTPS** - Always use HTTPS for production sites
3. **Validate on server** - Never rely solely on client-side validation
4. **Monitor logs** - Watch for unusual patterns in verification failures
5. **Rate limiting** - Consider implementing rate limiting for additional protection

## Performance

-   **Caching** - Provider instances are cached for performance
-   **Timeouts** - Configurable timeouts prevent slow responses
-   **Async** - Consider implementing async verification for high-traffic sites

## Advanced Configuration

### Custom Providers

You can create custom spam protection providers by implementing the `SpamProtectionProviderInterface`:

```php
use App\Contracts\SpamProtectionProviderInterface;
use App\ValueObjects\SpamProtectionResult;

class CustomProvider implements SpamProtectionProviderInterface
{
    public function verify(Request $request, array $config): SpamProtectionResult
    {
        // Your verification logic
    }

    // ... implement other interface methods
}

// Register in service container
$spamProtectionService->registerProvider('custom', new CustomProvider());
```

### Form-Specific Middleware

Apply spam protection to specific routes:

```php
// In your routes
$app->post('/special-form', [SpecialController::class, 'submit'])
    ->add($spamProtectionMiddlewareFactory->withProvider('turnstile'));
```

## Support

For issues or questions about the spam protection system, please check:

1. This documentation
2. Configuration examples
3. Error logs
4. Provider documentation (reCAPTCHA, hCAPTCHA, Turnstile)
