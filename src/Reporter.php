<?php

namespace SuperAdmin\Admin\Reporter;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SuperAdmin\Admin\Extension;
use Throwable;

class Reporter extends Extension
{
    use BootExtension;

    /**
     * Migrations run by `php artisan migrate`; super-admin also lists pending ones in `admin:upgrade`.
     *
     * @var string
     */
    public $migrations = __DIR__.'/../database/migrations';

    /**
     * Value stored instead of a redacted secret.
     */
    public const REDACTED = '[redacted]';

    /**
     * Request fields that are never stored (merged with `admin.extensions.reporter.redact`).
     *
     * @var array<int, string>
     */
    public static $redactFields = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        '_token', 'token', 'access_token', 'refresh_token', 'api_key', 'secret',
        'client_secret', 'card_number', 'cvv', 'cvc',
    ];

    /**
     * Headers that are never stored.
     *
     * @var array<int, string>
     */
    public static $redactHeaders = [
        'authorization', 'proxy-authorization', 'cookie', 'set-cookie',
        'x-csrf-token', 'x-xsrf-token', 'x-api-key', 'php-auth-pw',
    ];

    /**
     * Longest value kept for the short columns and for the long ones.
     */
    protected const SHORT_LIMIT = 255;

    protected const LONG_LIMIT = 60000;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public static function report(Throwable $exception): void
    {
        $reporter = new static(request());

        $reporter->reportException($exception);
    }

    public function reportException(Throwable $exception): void
    {
        $data = [
            // Request info.
            'method'  => $this->request->getMethod(),
            'ip'      => $this->request->getClientIps(),
            'path'    => $this->request->path(),
            'query'   => $this->redact(Arr::except($this->request->all(), ['_pjax', '_method', '_previous_'])),
            'body'    => $this->redactBody($this->request->getContent()),
            'cookies' => $this->redactCookies($this->request->cookies->all()),
            'headers' => $this->redactHeaders($this->request->headers->all()),

            // Exception info.
            'type'    => get_class($exception),
            'code'    => $exception->getCode(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
            'message' => $exception->getMessage(),
            'trace'   => $exception->getTraceAsString(),
        ];

        try {
            $this->store($this->fit($this->stringify($data)));
        } catch (Throwable $e) {
            // Never let reporting break the request that is already failing.
        }
    }

    /**
     * Convert all items to string.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    public function stringify($data): array
    {
        return array_map(function ($item) {
            return is_array($item) ? json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $item;
        }, $data);
    }

    /**
     * Cut values to the size of their columns, so long messages do not fail to insert.
     *
     * @param array<string, string> $data
     *
     * @return array<string, string>
     */
    public function fit(array $data): array
    {
        foreach (['type', 'code', 'file', 'path', 'method', 'ip'] as $column) {
            $data[$column] = Str::limit($data[$column], static::SHORT_LIMIT - 3);
        }

        foreach (['message', 'trace', 'query', 'body', 'cookies', 'headers'] as $column) {
            $data[$column] = Str::limit($data[$column], static::LONG_LIMIT);
        }

        return $data;
    }

    /**
     * Store exception info to db.
     *
     * @param array<string, string> $data
     */
    public function store(array $data): bool
    {
        try {
            ExceptionModel::query()->create($data);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Replace secret fields at any depth.
     *
     * @param array<string|int, mixed> $input
     *
     * @return array<string|int, mixed>
     */
    public function redact(array $input): array
    {
        $fields = array_map('strtolower', array_merge(static::$redactFields, (array) static::config('redact', [])));

        foreach ($input as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $fields, true)) {
                $input[$key] = static::REDACTED;
            } elseif (is_array($value)) {
                $input[$key] = $this->redact($value);
            }
        }

        return $input;
    }

    /**
     * Redact a raw request body when it is JSON or form encoded; otherwise keep it as is.
     */
    public function redactBody(string $body): string
    {
        if ($body === '') {
            return '';
        }

        $json = json_decode($body, true);

        if (is_array($json)) {
            return (string) json_encode($this->redact($json), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (Str::contains((string) $this->request->header('content-type'), 'application/x-www-form-urlencoded')) {
            parse_str($body, $form);

            return http_build_query($this->redact($form));
        }

        return $body;
    }

    /**
     * Keep cookie names only, unless `store_cookies` is enabled.
     *
     * @param array<string, mixed> $cookies
     *
     * @return array<string, mixed>
     */
    public function redactCookies(array $cookies): array
    {
        if (static::config('store_cookies', false)) {
            return $this->redact($cookies);
        }

        return array_map(function () {
            return static::REDACTED;
        }, $cookies);
    }

    /**
     * @param array<string, array<int, string|null>> $headers
     *
     * @return array<string, array<int, string|null>|string>
     */
    public function redactHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), static::$redactHeaders, true)) {
                $headers[$name] = static::REDACTED;
            }
        }

        return $headers;
    }
}
