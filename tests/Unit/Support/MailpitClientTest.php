<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\Exceptions\MailpitConnectionException;
use Tests\Support\MailpitClient;
use Tests\TestCase;

class MailpitClientTest extends TestCase
{
    private MailpitClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new MailpitClient('http://localhost:8025/api/v1');
    }

    public function test_messages_returns_message_list(): void
    {
        Http::fake([
            'localhost:8025/api/v1/messages' => Http::response([
                'total' => 2,
                'messages' => [
                    ['ID' => 'msg1', 'Subject' => 'First'],
                    ['ID' => 'msg2', 'Subject' => 'Second'],
                ],
            ], 200),
        ]);

        $messages = $this->client->messages();

        $this->assertCount(2, $messages);
        $this->assertSame('msg1', $messages[0]['ID']);
        $this->assertSame('msg2', $messages[1]['ID']);
    }

    public function test_messages_returns_empty_when_no_messages(): void
    {
        Http::fake([
            'localhost:8025/api/v1/messages' => Http::response([
                'total' => 0,
                'messages' => [],
            ], 200),
        ]);

        $messages = $this->client->messages();

        $this->assertEmpty($messages);
    }

    public function test_messages_throws_on_connection_failure(): void
    {
        Http::fake([
            'localhost:8025/*' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $this->expectException(MailpitConnectionException::class);
        $this->expectExceptionMessage('Failed to connect to Mailpit');

        $this->client->messages();
    }

    public function test_get_latest_message_returns_first(): void
    {
        Http::fake([
            'localhost:8025/api/v1/messages' => Http::response([
                'total' => 2,
                'messages' => [
                    ['ID' => 'newest', 'Subject' => 'Newest'],
                    ['ID' => 'older', 'Subject' => 'Older'],
                ],
            ], 200),
        ]);

        $message = $this->client->getLatestMessage();

        $this->assertNotNull($message);
        $this->assertSame('newest', $message['ID']);
    }

    public function test_get_latest_message_returns_null_when_empty(): void
    {
        Http::fake([
            'localhost:8025/api/v1/messages' => Http::response([
                'total' => 0,
                'messages' => [],
            ], 200),
        ]);

        $this->assertNull($this->client->getLatestMessage());
    }

    public function test_get_message_html_returns_html_body(): void
    {
        Http::fake([
            'localhost:8025/api/v1/message/msg1' => Http::response([
                'ID' => 'msg1',
                'HTML' => '<html><body><h1>Test Email</h1></body></html>',
                'Text' => 'Test Email',
            ], 200),
        ]);

        $html = $this->client->getMessageHtml('msg1');

        $this->assertStringContainsString('Test Email', $html);
        $this->assertStringContainsString('<h1>', $html);
    }

    public function test_get_message_html_returns_empty_for_plain_text(): void
    {
        Http::fake([
            'localhost:8025/api/v1/message/msg1' => Http::response([
                'ID' => 'msg1',
                'HTML' => '',
                'Text' => 'Plain text only',
            ], 200),
        ]);

        $html = $this->client->getMessageHtml('msg1');

        $this->assertSame('', $html);
    }

    public function test_get_message_headers_returns_header_arrays(): void
    {
        Http::fake([
            'localhost:8025/api/v1/message/msg1/headers' => Http::response([
                'Content-Type' => ['text/html; charset=utf-8'],
                'From' => ['Test <test@example.com>'],
                'Subject' => ['Test Subject'],
                'List-Unsubscribe' => ['<https://example.com/unsub>'],
            ], 200),
        ]);

        $headers = $this->client->getMessageHeaders('msg1');

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertIsArray($headers['Content-Type']);
        $this->assertSame(['text/html; charset=utf-8'], $headers['Content-Type']);
        $this->assertArrayHasKey('List-Unsubscribe', $headers);
    }

    public function test_search_by_recipient_sends_correct_query(): void
    {
        Http::fake([
            'localhost:8025/api/v1/search*' => Http::response([
                'total' => 1,
                'messages' => [
                    ['ID' => 'msg1', 'Subject' => 'Found'],
                ],
            ], 200),
        ]);

        $this->client->searchByRecipient('tenant@example.com');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'query=to%3Atenant%40example.com')
                || str_contains($request->url(), 'query=to:tenant@example.com');
        });
    }

    public function test_search_by_recipient_returns_matching(): void
    {
        Http::fake([
            'localhost:8025/api/v1/search*' => Http::response([
                'total' => 1,
                'messages' => [
                    ['ID' => 'msg1', 'Subject' => 'Payment Received'],
                ],
            ], 200),
        ]);

        $messages = $this->client->searchByRecipient('tenant@example.com');

        $this->assertCount(1, $messages);
        $this->assertSame('msg1', $messages[0]['ID']);
    }

    public function test_search_by_subject_sends_correct_query(): void
    {
        Http::fake([
            'localhost:8025/api/v1/search*' => Http::response([
                'total' => 0,
                'messages' => [],
            ], 200),
        ]);

        $this->client->searchBySubject('Payment Received');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'query=subject');
        });
    }

    public function test_delete_all_sends_delete_request(): void
    {
        Http::fake([
            'localhost:8025/api/v1/messages' => Http::response('ok', 200),
        ]);

        $this->client->deleteAll();

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/messages');
        });
    }

    public function test_get_message_links_extracts_hrefs(): void
    {
        $html = '<html><body>'
            .'<a href="https://example.com/pay">Pay Now</a>'
            .'<a href="https://example.com/unsubscribe?signature=abc123">Unsubscribe</a>'
            .'<a href="mailto:support@example.com">Contact</a>'
            .'</body></html>';

        Http::fake([
            'localhost:8025/api/v1/message/msg1' => Http::response([
                'ID' => 'msg1',
                'HTML' => $html,
            ], 200),
        ]);

        $links = $this->client->getMessageLinks('msg1');

        $this->assertCount(3, $links);
        $this->assertContains('https://example.com/pay', $links);
        $this->assertContains('https://example.com/unsubscribe?signature=abc123', $links);
        $this->assertContains('mailto:support@example.com', $links);
    }

    public function test_get_message_links_returns_empty_for_no_links(): void
    {
        Http::fake([
            'localhost:8025/api/v1/message/msg1' => Http::response([
                'ID' => 'msg1',
                'HTML' => '<html><body><p>No links here</p></body></html>',
            ], 200),
        ]);

        $this->assertEmpty($this->client->getMessageLinks('msg1'));
    }

    public function test_get_message_links_returns_empty_for_empty_html(): void
    {
        Http::fake([
            'localhost:8025/api/v1/message/msg1' => Http::response([
                'ID' => 'msg1',
                'HTML' => '',
            ], 200),
        ]);

        $this->assertEmpty($this->client->getMessageLinks('msg1'));
    }

    public function test_wait_for_message_returns_when_found(): void
    {
        Http::fake([
            'localhost:8025/api/v1/search*' => Http::response([
                'total' => 1,
                'messages' => [
                    [
                        'ID' => 'msg1',
                        'Subject' => 'Found',
                        'To' => [['Name' => '', 'Address' => 'tenant@example.com']],
                    ],
                ],
            ], 200),
        ]);

        $message = $this->client->waitForMessage('tenant@example.com', timeoutSeconds: 2);

        $this->assertNotNull($message);
        $this->assertSame('msg1', $message['ID']);
    }

    public function test_wait_for_message_returns_null_on_timeout(): void
    {
        Http::fake([
            'localhost:8025/api/v1/search*' => Http::response([
                'total' => 0,
                'messages' => [],
            ], 200),
        ]);

        $message = $this->client->waitForMessage('nobody@example.com', timeoutSeconds: 1);

        $this->assertNull($message);
    }
}
