<?php

namespace Tests\Unit;

use App\Services\PublicUrlFetcher;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicUrlFetcherTest extends TestCase
{
    public static function unsafeUrls(): array
    {
        return array_map(fn ($url) => [$url], ['http://127.0.0.1/', 'http://10.0.0.1/',
            'http://172.16.0.1/', 'http://192.168.1.1/', 'http://169.254.169.254/',
            'http://0.0.0.0/', 'http://224.0.0.1/', 'http://[64:ff9b::a00:1]/',
            'http://[::1]/', 'http://[fc00::1]/', 'http://[fe80::1]/',
            'http://[::ffff:127.0.0.1]/', 'file:///etc/passwd', 'ftp://example.com/',
            'http://user:pass@example.com/', 'https://example.com:8443/', "https://example.com/\nfoo"]);
    }

    #[DataProvider('unsafeUrls')]
    public function test_rejects_non_public_destinations(string $url): void
    {
        $fetcher = $this->fetcher();
        $this->expectException(ValidationException::class);
        try {
            $fetcher->fetch($url);
        } finally {
            $this->assertSame([], $fetcher->requests);
        }
    }

    public function test_blocks_mixed_public_and_private_dns_answers(): void
    {
        $fetcher = $this->fetcher();
        $fetcher->ips = ['8.8.8.8', '10.0.0.1'];
        $this->expectException(ValidationException::class);
        $fetcher->fetch('https://example.com');
    }

    public function test_redirect_to_internal_address_is_never_requested(): void
    {
        $fetcher = $this->fetcher();
        $fetcher->responses = [['status' => 302, 'location' => 'http://169.254.169.254/', 'body' => '']];
        try {
            $fetcher->fetch('https://example.com');
            $this->fail('Expected rejection');
        } catch (ValidationException) {
            $this->assertCount(1, $fetcher->requests);
        }
    }

    public function test_relative_redirect_and_pinned_ip(): void
    {
        $fetcher = $this->fetcher();
        $fetcher->responses = [['status' => 302, 'location' => '/article', 'body' => ''],
            ['status' => 200, 'body' => 'source']];
        $this->assertSame('source', $fetcher->fetch('https://example.com/start'));
        $this->assertSame(['https://example.com/article', 'example.com', 443, '8.8.8.8'], $fetcher->requests[1]);
    }

    public function test_redirect_dns_is_revalidated_and_redirect_loops_are_bounded(): void
    {
        $fetcher = $this->fetcher();
        $fetcher->responses = array_fill(0, 4, ['status' => 302, 'location' => '/again', 'body' => '']);
        $this->expectException(ValidationException::class);
        try {
            $fetcher->fetch('https://example.com');
        } finally {
            $this->assertCount(4, $fetcher->requests);
            $this->assertSame(4, $fetcher->resolutions);
        }
    }

    private function fetcher(): PublicUrlFetcher
    {
        return new class extends PublicUrlFetcher
        {
            public array $ips = ['8.8.8.8'];

            public array $requests = [];

            public array $responses = [];

            public int $resolutions = 0;

            protected function resolve(string $host): array
            {
                $this->resolutions++;

                return $this->ips;
            }

            protected function download(string $url, string $host, int $port, string $ip, int $timeoutMs, int $maxBytes): array
            {
                $this->requests[] = [$url, $host, $port, $ip];

                return array_shift($this->responses) ?? ['status' => 200, 'body' => 'source'];
            }
        };
    }
}
