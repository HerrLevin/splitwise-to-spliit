<?php

namespace App\Importer;

class SpliitUrlParser
{
    private readonly string $urlOrId; // example: https://spliit.app/groups/-0L39hnhLaPs7PgpmW1Xx/expenses/create
    private const string SPLIIT_DEFAULT_URL = 'https://spliit.app';

    public function __construct(string $urlOrId)
    {
        $this->urlOrId = trim($urlOrId);
    }

    public function getBaseUrl(): string
    {
        // Check if input is a valid URL
        if (filter_var($this->urlOrId, FILTER_VALIDATE_URL)) {
            $parsedUrl = parse_url($this->urlOrId);
            if ($parsedUrl === false || !isset($parsedUrl['host'])) {
                return self::SPLIIT_DEFAULT_URL;
            }

            // Reconstruct the base URL
            $scheme = $parsedUrl['scheme'] ?? 'https';
            $host = $parsedUrl['host'];
            $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
            return "{$scheme}://{$host}{$port}";
        }

        // If not a URL, return default Spliit URL
        return self::SPLIIT_DEFAULT_URL;
    }

    public function getGroupId(): ?string
    {
        // Check if input is a valid URL
        if (!filter_var($this->urlOrId, FILTER_VALIDATE_URL)) {
            // If input is not a URL, assume it's a direct group ID
            return $this->urlOrId;
        }

        $parsedUrl = parse_url($this->urlOrId);
        if ($parsedUrl !== false || isset($parsedUrl['path'])) {
            // Extract the group ID from the path
            $pathSegments = explode('/', trim($parsedUrl['path'], '/'));
            foreach ($pathSegments as $key => $segment) {
                if ($segment === 'groups' && isset($pathSegments[$key + 1])) {
                    return $pathSegments[$key + 1];
                }
            }
        }


        return null; // No group ID found in the URL
    }

}
