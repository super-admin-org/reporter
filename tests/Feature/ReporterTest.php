<?php

namespace SuperAdmin\Admin\Reporter\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use SuperAdmin\Admin\Reporter\ExceptionModel;
use SuperAdmin\Admin\Reporter\Reporter;
use SuperAdmin\Admin\Reporter\Tests\TestCase;
use SuperAdmin\Admin\Reporter\Tracer\Frame;
use SuperAdmin\Admin\Reporter\Tracer\Parser;

class ReporterTest extends TestCase
{
    protected function reportFrom(Request $request, \Throwable $exception): ExceptionModel
    {
        (new Reporter($request))->reportException($exception);

        return ExceptionModel::query()->latest('id')->firstOrFail();
    }

    protected function row(array $attributes = []): ExceptionModel
    {
        return ExceptionModel::query()->create(array_merge([
            'type'    => RuntimeException::class,
            'code'    => '0',
            'message' => 'Something failed',
            'file'    => '/app/Http/Controllers/OrderController.php',
            'line'    => 42,
            'trace'   => '#0 {main}',
            'method'  => 'GET',
            'path'    => 'orders',
            'query'   => '[]',
            'body'    => '',
            'cookies' => '[]',
            'headers' => '[]',
            'ip'      => '["127.0.0.1"]',
        ], $attributes));
    }

    public function test_secrets_are_not_stored(): void
    {
        $request = Request::create('/login', 'POST', [
            'email'    => 'ada@example.com',
            'password' => 'hunter2',
            'profile'  => ['api_key' => 'sk_live_123', 'name' => 'Ada'],
        ], ['laravel_session' => 'session-id-abc'], [], [
            'HTTP_AUTHORIZATION' => 'Bearer secret-token',
            'HTTP_X_CUSTOM'      => 'kept',
        ]);

        $row = $this->reportFrom($request, new RuntimeException('Login failed'));

        $stored = $row->query.$row->body.$row->cookies.$row->headers;

        foreach (['hunter2', 'sk_live_123', 'session-id-abc', 'secret-token'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }

        $query = json_decode($row->query, true);
        $this->assertSame('ada@example.com', $query['email']);
        $this->assertSame('Ada', $query['profile']['name']);
        $this->assertSame(Reporter::REDACTED, $query['password']);
        $this->assertArrayHasKey('laravel_session', json_decode($row->cookies, true));
        $this->assertSame(['kept'], json_decode($row->headers, true)['x-custom']);
    }

    public function test_json_bodies_are_redacted(): void
    {
        $request = Request::create('/api/pay', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'amount'      => 10,
            'card_number' => '4242424242424242',
        ]));

        $row = $this->reportFrom($request, new RuntimeException('Payment failed'));

        $this->assertStringNotContainsString('4242424242424242', $row->body);
        $this->assertSame(10, json_decode($row->body, true)['amount']);
    }

    public function test_long_values_are_cut_to_fit_their_columns(): void
    {
        $request = Request::create('/'.str_repeat('a', 400));

        $row = $this->reportFrom($request, new RuntimeException(str_repeat('SQLSTATE long message ', 40)));

        $this->assertLessThanOrEqual(255, mb_strlen($row->path));
        $this->assertGreaterThan(255, mb_strlen($row->message));
    }

    public function test_list_escapes_messages_and_paths(): void
    {
        $this->row(['message' => '<script>alert("message")</script>', 'path' => 'x/<img src=x onerror=alert(1)>']);

        $this->get('admin/exceptions')
            ->assertOk()
            ->assertDontSee('<script>alert("message")</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_list_handles_any_http_method(): void
    {
        $this->row(['method' => 'HEAD']);
        $this->row(['method' => 'PROPFIND']);

        $this->get('admin/exceptions')
            ->assertOk()
            ->assertSee('badge bg-secondary me-2">HEAD', false)
            ->assertSee('PROPFIND');
    }

    public function test_list_shows_how_often_a_problem_happened(): void
    {
        $this->row();
        $this->row();
        $this->row(['line' => 7]);

        $this->get('admin/exceptions')
            ->assertOk()
            ->assertSee('×2')
            ->assertSee('×1');
    }

    public function test_detail_page_shows_the_trace_and_loads_the_highlighter(): void
    {
        $row = $this->row(['trace' => "#0 /app/Http/Kernel.php(10): App\\Http\\Kernel->handle()\n#1 {main}"]);

        // The list first: Admin::js() is static, so within one test the detail page's assets would leak into later requests.
        $this->get('admin/exceptions')
            ->assertOk()
            ->assertDontSee('super-admin-reporter/prism/prism.js', false);

        $this->get('admin/exceptions/'.$row->id)
            ->assertOk()
            ->assertSee('Something failed')
            ->assertSee('super-admin-reporter/prism/prism.js', false);
    }

    public function test_windows_trace_frames_are_parsed(): void
    {
        $frame = new Frame('#3 D:\\Work\\app\\Http\\Kernel.php(42): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))');
        $attributes = (new \ReflectionProperty($frame, 'attributes'))->getValue($frame);

        $this->assertSame('D:\\Work\\app\\Http\\Kernel.php', $attributes['file']);
        $this->assertSame(42, $attributes['line']);
        $this->assertSame('then', $attributes['method']);
    }

    public function test_parser_is_iterable(): void
    {
        $parser = new Parser("#0 /a.php(1): f()\n#1 {main}");
        $parser->parse();

        $this->assertCount(2, iterator_to_array($parser));
    }

    public function test_prune_command_deletes_old_rows(): void
    {
        $old = $this->row();
        $old->created_at = now()->subDays(40);
        $old->save();
        $recent = $this->row();

        $this->artisan('reporter:prune', ['--days' => 30])->assertExitCode(0);

        $this->assertNull(ExceptionModel::query()->find($old->id));
        $this->assertNotNull(ExceptionModel::query()->find($recent->id));
    }

    public function test_reporting_never_throws(): void
    {
        config(['admin.extensions.reporter.table' => 'missing_table']);

        Reporter::report(new RuntimeException(Str::random()));

        $this->assertTrue(true);
    }
}
