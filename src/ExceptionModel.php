<?php

namespace SuperAdmin\Admin\Reporter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use SuperAdmin\Admin\Traits\DefaultDatetimeFormat;

class ExceptionModel extends Model
{
    use DefaultDatetimeFormat;

    /**
     * Badge colour per HTTP method (names from the shared class vocabulary).
     *
     * @var array<string, string>
     */
    public static $methodColor = [
        'GET'     => 'success',
        'POST'    => 'warning',
        'PUT'     => 'info',
        'DELETE'  => 'danger',
        'PATCH'   => 'primary',
        'OPTIONS' => 'secondary',
        'HEAD'    => 'secondary',
    ];

    protected $fillable = [
        'type',
        'code',
        'message',
        'file',
        'line',
        'trace',
        'method',
        'path',
        'query',
        'body',
        'cookies',
        'headers',
        'ip',
    ];

    /**
     * Settings constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(config('admin.database.connection') ?: config('database.default'));

        $this->setTable(config('admin.extensions.reporter.table', 'laravel_exceptions'));
    }

    public static function methodColor(?string $method): string
    {
        return Arr::get(static::$methodColor, strtoupper((string) $method), 'secondary');
    }

    /**
     * Rows of the same problem: same exception class thrown at the same file and line.
     */
    public function scopeSameProblemAs(Builder $query, self $exception): Builder
    {
        return $query->where('type', $exception->type)
            ->where('file', $exception->file)
            ->where('line', $exception->line);
    }

    /**
     * How many times this problem was recorded.
     */
    public function occurrences(): int
    {
        return static::query()->sameProblemAs($this)->count();
    }

    /**
     * Delete rows older than the given number of days.
     */
    public static function pruneOlderThan(int $days): int
    {
        return static::query()->where('created_at', '<', now()->subDays($days))->delete();
    }
}
