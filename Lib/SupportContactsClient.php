<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class SupportContactsClient
{
    private const int RESPONSE_VERSION = 1;
    private const int MAX_CHANNELS = 8;
    private const int MAX_LABEL_BYTES = 160;

    public function __construct(
        private readonly ClientInterface $client = new Client(),
    ) {
    }

    /**
     * @return list<SupportContact>
     */
    public function fetch(): array
    {
        try {
            $response = $this->client->request(
                'GET',
                RemoteSupportConfig::CONTACTS_URL,
                [
                    'verify' => true,
                    'allow_redirects' => false,
                    'connect_timeout' => RemoteSupportConfig::CONTACT_CONNECT_TIMEOUT,
                    'timeout' => RemoteSupportConfig::CONTACT_TIMEOUT,
                    'http_errors' => false,
                ],
            );
            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $contents = $this->readBoundedBody($response);
            if ($contents === null) {
                return [];
            }

            return $this->decodeContacts($contents);
        } catch (Throwable) {
            return [];
        }
    }

    private function readBoundedBody(ResponseInterface $response): ?string
    {
        $contentLength = $response->getHeaderLine('Content-Length');
        if (
            $contentLength !== ''
            && ctype_digit($contentLength)
            && (int)$contentLength > RemoteSupportConfig::CONTACT_MAX_BYTES
        ) {
            return null;
        }

        $body = $response->getBody();
        $contents = '';
        while (!$body->eof()) {
            $remaining = RemoteSupportConfig::CONTACT_MAX_BYTES - strlen($contents);
            $chunk = $body->read(min(8_192, $remaining + 1));
            if ($chunk === '') {
                break;
            }

            $contents .= $chunk;
            if (strlen($contents) > RemoteSupportConfig::CONTACT_MAX_BYTES) {
                return null;
            }
        }

        return $contents;
    }

    /**
     * @return list<SupportContact>
     *
     * @throws JsonException
     */
    private function decodeContacts(string $contents): array
    {
        $document = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        if (
            !is_array($document)
            || ($document['version'] ?? null) !== self::RESPONSE_VERSION
            || !isset($document['channels'])
            || !is_array($document['channels'])
            || !array_is_list($document['channels'])
            || count($document['channels']) > self::MAX_CHANNELS
        ) {
            return [];
        }

        $contacts = [];
        foreach ($document['channels'] as $channel) {
            $contact = $this->validateChannel($channel);
            if ($contact === null) {
                return [];
            }

            $contacts[] = $contact;
        }

        return $contacts;
    }

    private function validateChannel(mixed $channel): ?SupportContact
    {
        if (!is_array($channel)) {
            return null;
        }

        $type = $channel['type'] ?? null;
        $label = $channel['label'] ?? null;
        $uri = $channel['uri'] ?? null;
        if (
            !is_string($type)
            || !is_string($label)
            || !is_string($uri)
            || !$this->isSafeLabel($label)
            || !$this->isAllowedUri($type, $uri)
        ) {
            return null;
        }

        return new SupportContact($type, trim($label), $uri);
    }

    private function isSafeLabel(string $label): bool
    {
        $label = trim($label);

        return $label !== ''
            && strlen($label) <= self::MAX_LABEL_BYTES
            && preg_match('/[\x00-\x1F\x7F<>]/u', $label) !== 1;
    }

    private function isAllowedUri(string $type, string $uri): bool
    {
        if (
            preg_match('/[\x00-\x20\x7F<>"\']/u', $uri) === 1
            || strlen($uri) > 2_048
        ) {
            return false;
        }

        return match ($type) {
            'phone' => preg_match('/\Atel:\+?[0-9(). -]{3,40}\z/D', $uri) === 1,
            'telegram' => $this->isHttpsUri($uri),
            default => false,
        };
    }

    private function isHttpsUri(string $uri): bool
    {
        if (filter_var($uri, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($uri);

        return is_array($parts)
            && strtolower((string)($parts['scheme'] ?? '')) === 'https'
            && (string)($parts['host'] ?? '') !== ''
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }
}
