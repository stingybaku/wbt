<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Validation\ValidationException;

/** Fetch untrusted source URLs without allowing access to the server's network. */
class PublicUrlFetcher
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const MAX_REDIRECTS = 3;

    public function fetch(string $url, int $maxBytes = self::MAX_BYTES): string
    {
        $deadline = microtime(true) + 15;
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            [$host, $port, $ip] = $this->destination($url);
            $remaining = (int) (($deadline - microtime(true)) * 1000);
            if ($remaining <= 0) {
                $this->reject('Source download timed out.');
            }
            $response = $this->download($url, $host, $port, $ip, $remaining, min($maxBytes, 20 * 1024 * 1024));
            if (in_array($response['status'], [301, 302, 303, 307, 308], true)) {
                if ($redirects === self::MAX_REDIRECTS || empty($response['location'])) {
                    $this->reject('Too many or invalid source redirects.');
                }
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($response['location']));

                continue;
            }
            if ($response['status'] < 200 || $response['status'] >= 300) {
                $this->reject('Could not download the source URL.');
            }

            return $response['body'];
        }
        $this->reject('Could not download the source URL.');
    }

    private function destination(string $url): array
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->reject('Enter a valid public HTTP or HTTPS URL.');
        }
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])
            || $port !== ($scheme === 'https' ? 443 : 80) || $host === '') {
            $this->reject('Only public HTTP/HTTPS URLs on standard ports are supported.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if (! $addresses) {
            $this->reject('The source hostname could not be resolved.');
        }
        foreach ($addresses as $ip) {
            $packed = @inet_pton($ip);
            // Exclude multicast and IPv6 translation/tunnel ranges too. PHP's
            // GLOBAL_RANGE flag alone allows multicast and well-known NAT64.
            $unicast = $packed !== false && (strlen($packed) === 4
                ? ord($packed[0]) < 224
                : (ord($packed[0]) & 0xE0) === 0x20);
            if (! $unicast || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
                $this->reject('The source URL must resolve only to public internet addresses.');
            }
        }

        return [$host, $port, $addresses[0]];
    }

    protected function resolve(string $host): array
    {
        $addresses = [];
        // Resolve CNAMEs explicitly with a bounded chain; reject unresolved names.
        for ($depth = 0; $depth < 8; $depth++) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA | DNS_CNAME) ?: [];
            $alias = null;
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $addresses[] = $record['ip'];
                }
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
                if (isset($record['target'])) {
                    $alias = $record['target'];
                }
            }
            if (! $alias) {
                break;
            }
            $host = $alias;
        }

        return array_values(array_unique($addresses));
    }

    protected function download(string $url, string $host, int $port, string $ip, int $timeoutMs, int $maxBytes): array
    {
        $body = '';
        $location = null;
        $tooLarge = false;
        $curl = curl_init($url);
        $address = str_contains($ip, ':') ? "[{$ip}]" : $ip;
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_PROXY => '',
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => min(5000, $timeoutMs),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // Preserve Host/SNI while connecting ONLY to the address validated above.
            CURLOPT_RESOLVE => filter_var($host, FILTER_VALIDATE_IP) ? [] : ["{$host}:{$port}:{$address}"],
            CURLOPT_USERAGENT => 'WBTForge/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,text/plain'],
            CURLOPT_HEADERFUNCTION => function ($handle, string $header) use (&$location): int {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;

                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
        ]);
        try {
            $ok = curl_exec($curl);
            if ($tooLarge) {
                $this->reject('The source response exceeds the download size limit.');
            }
            if ($ok === false) {
                $this->reject('Could not download the source URL.');
            }

            return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'location' => $location, 'body' => $body];
        } finally {
            curl_close($curl);
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['url' => $message]);
    }
}
