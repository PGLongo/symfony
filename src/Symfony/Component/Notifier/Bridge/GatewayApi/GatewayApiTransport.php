<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GatewayApi;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Piergiuseppe Longo <piergiuseppe.longo@gmail.com>
 * @experimental in 5.2
 */
final class GatewayApiTransport extends AbstractTransport
{
    protected const HOST = 'gatewayapi.com';

    private $authToken;
    private $from;

    public function __construct(string $authToken, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->authToken = $authToken;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('gatewayapi://%s?from=%s', $this->getEndpoint(), $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, get_debug_type($message)));
        }

        $endpoint = sprintf('https://%s/rest/mtsms', $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => [$this->authToken, ''],
            'json' => [
                'sender' => $this->from,
                'recipients' => [['msisdn' => $message->getPhone()]],
                'message' => $message->getSubject(),
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if (200 !== $statusCode) {
            throw new TransportException(sprintf('Unable to send the SMS: error %d.', $statusCode), $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
