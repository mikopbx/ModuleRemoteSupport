<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Modules\ModuleRemoteSupport\Lib\RemoteSupportConfig;
use Modules\ModuleRemoteSupport\Lib\SupportContactsClient;

require_once __DIR__ . '/bootstrap.php';

/**
 * @param array<int, mixed> $queue
 * @param array<int, array<string, mixed>> $history
 */
function contactsClient(array $queue, array &$history = []): SupportContactsClient
{
    $mock = new MockHandler($queue);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    return new SupportContactsClient(new Client(['handler' => $stack]));
}

$validPayload = json_encode(
    [
        'version' => 1,
        'channels' => [
            [
                'type' => 'phone',
                'label' => '+7 495 229-30-42',
                'uri' => 'tel:+74952293042',
            ],
            [
                'type' => 'telegram',
                'label' => 'Telegram',
                'uri' => 'https://t.me/miko_support',
            ],
        ],
    ],
    JSON_THROW_ON_ERROR,
);

$history = [];
$contacts = contactsClient([new Response(200, [], $validPayload)], $history)->fetch();
contractAssertSame(2, count($contacts), 'valid response returns two contacts');
contractAssertSame('phone', $contacts[0]->type, 'phone type is preserved');
contractAssertSame('tel:+74952293042', $contacts[0]->uri, 'phone URI is preserved');
contractAssertSame('telegram', $contacts[1]->type, 'Telegram type is preserved');
contractAssertSame(
    RemoteSupportConfig::CONTACTS_URL,
    (string)$history[0]['request']->getUri(),
    'request uses only the fixed production URL',
);
contractAssertSame('GET', $history[0]['request']->getMethod(), 'request uses GET');

$options = $history[0]['options'];
contractAssertSame(true, $options['verify'] ?? null, 'TLS verification is enabled');
contractAssertSame(false, $options['allow_redirects'] ?? null, 'redirects are disabled');
contractAssertSame(2.0, $options['connect_timeout'] ?? null, 'connect timeout is fixed');
contractAssertSame(3.0, $options['timeout'] ?? null, 'total timeout is fixed');
contractAssertSame(false, $options['http_errors'] ?? null, 'HTTP errors are inspected');
contractAssertSame([], $history[0]['request']->getHeader('X-PBX-Identity'), 'no PBX metadata header');
contractAssertSame('', $history[0]['request']->getUri()->getQuery(), 'request has no query metadata');

$invalidQueues = [
    'unavailable server' => [
        new ConnectException(
            'offline',
            new Request('GET', RemoteSupportConfig::CONTACTS_URL),
        ),
    ],
    'non-200 response' => [new Response(503, [], '{}')],
    'redirect response' => [new Response(302, ['Location' => 'https://example.com/'], '')],
    'oversized response' => [new Response(200, [], str_repeat('x', 65_537))],
    'invalid JSON' => [new Response(200, [], '{')],
    'unknown version' => [
        new Response(200, [], '{"version":2,"channels":[]}'),
    ],
    'more than eight channels' => [
        new Response(
            200,
            [],
            json_encode(
                [
                    'version' => 1,
                    'channels' => array_fill(
                        0,
                        9,
                        ['type' => 'phone', 'label' => 'Phone', 'uri' => 'tel:+10000000000'],
                    ),
                ],
                JSON_THROW_ON_ERROR,
            ),
        ),
    ],
    'unknown channel type' => [
        new Response(
            200,
            [],
            '{"version":1,"channels":[{"type":"email","label":"Mail","uri":"https://example.com"}]}',
        ),
    ],
    'javascript URI' => [
        new Response(
            200,
            [],
            '{"version":1,"channels":[{"type":"telegram","label":"Telegram","uri":"javascript:alert(1)"}]}',
        ),
    ],
    'data URI' => [
        new Response(
            200,
            [],
            '{"version":1,"channels":[{"type":"telegram","label":"Telegram","uri":"data:text/html,test"}]}',
        ),
    ],
    'plain HTTP' => [
        new Response(
            200,
            [],
            '{"version":1,"channels":[{"type":"telegram","label":"Telegram","uri":"http://t.me/test"}]}',
        ),
    ],
    'embedded HTML label' => [
        new Response(
            200,
            [],
            '{"version":1,"channels":[{"type":"phone","label":"<b>Phone</b>","uri":"tel:+10000000000"}]}',
        ),
    ],
];

foreach ($invalidQueues as $case => $queue) {
    contractAssertSame([], contactsClient($queue)->fetch(), $case . ' must return an empty list');
}

echo "Contacts client contract: OK\n";
