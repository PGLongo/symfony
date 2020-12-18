<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TelegramTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $transport = $this->createTransport();

        $this->assertSame('telegram://host.test?channel=testChannel', (string) $transport);
    }

    public function testToStringContainsNoChannelBecauseItsOptional()
    {
        $transport = $this->createTransport(null);

        $this->assertSame('telegram://host.test', (string) $transport);
    }

    public function testSupportsChatMessage()
    {
        $transport = $this->createTransport();

        $this->assertTrue($transport->supports(new ChatMessage('testChatMessage')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonChatMessageThrowsLogicException()
    {
        $transport = new TelegramTransport('testToken', 'testChannel', $this->createMock(HttpClientInterface::class));

        $this->expectException(UnsupportedMessageTypeException::class);

        $transport->send($this->createMock(MessageInterface::class));
    }

    public function testSendWithErrorResponseThrows()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testDescription.+testErrorCode/');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['description' => 'testDescription', 'error_code' => 'testErrorCode']));

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport('testChannel', $client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithOptions()
    {
        $channel = 'testChannel';

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $content = <<<JSON
            {
                "ok": true,
                "result": {
                    "message_id": 1,
                    "from": {
                        "id": 12345678,
                        "first_name": "YourBot",
                        "username": "YourBot"
                    },
                    "chat": {
                        "id": 1234567890,
                        "first_name": "John",
                        "last_name": "Doe",
                        "username": "JohnDoe",
                        "type": "private"
                    },
                    "date": 1459958199,
                    "text": "Hello from Bot!"
                }
            }
JSON;

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($content)
        ;

        $expectedBody = [
            'chat_id' => $channel,
            'text' => 'testMessage',
            'parse_mode' => 'MarkdownV2',
        ];

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertSame($expectedBody, json_decode($options['body'], true));

            return $response;
        });

        $transport = $this->createTransport($channel, $client);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertEquals(1, $sentMessage->getMessageId());
        $this->assertEquals('telegram://host.test?channel=testChannel', $sentMessage->getTransport());
    }

    public function testSendWithChannelOverride()
    {
        $channelOverride = 'channelOverride';

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $content = <<<JSON
            {
                "ok": true,
                "result": {
                    "message_id": 1,
                    "from": {
                        "id": 12345678,
                        "first_name": "YourBot",
                        "username": "YourBot"
                    },
                    "chat": {
                        "id": 1234567890,
                        "first_name": "John",
                        "last_name": "Doe",
                        "username": "JohnDoe",
                        "type": "private"
                    },
                    "date": 1459958199,
                    "text": "Hello from Bot!"
                }
            }
JSON;

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($content)
        ;

        $expectedBody = [
            'chat_id' => $channelOverride,
            'text' => 'testMessage',
            'parse_mode' => 'MarkdownV2',
        ];

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $expectedBody): ResponseInterface {
            $this->assertSame($expectedBody, json_decode($options['body'], true));

            return $response;
        });

        $transport = $this->createTransport('defaultChannel', $client);

        $messageOptions = new TelegramOptions();
        $messageOptions->chatId($channelOverride);

        $sentMessage = $transport->send(new ChatMessage('testMessage', $messageOptions));

        $this->assertEquals(1, $sentMessage->getMessageId());
        $this->assertEquals('telegram://host.test?channel=defaultChannel', $sentMessage->getTransport());
    }

    private function createTransport(?string $channel = 'testChannel', ?HttpClientInterface $client = null): TelegramTransport
    {
        return (new TelegramTransport('token', $channel, $client ?: $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }
}
