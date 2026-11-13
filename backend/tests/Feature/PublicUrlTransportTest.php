<?php

namespace Tests\Feature;

use App\Services\PublicUrlFetcher;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicUrlTransportTest extends TestCase
{
    public function test_transport_pins_dns_bounds_the_body_and_does_not_follow_redirects(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        $port = (int) substr(strrchr($address, ':'), 1);
        fclose($socket);
        $server = proc_open([PHP_BINARY, '-S', $address, base_path('tests/Support/http-fixture.php')],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        $this->assertIsResource($server);
        try {
            for ($attempt = 0; $attempt < 50; $attempt++) {
                $ready = @stream_socket_client('tcp://'.$address, $errno, $error, 0.05);
                if ($ready) {
                    fclose($ready);
                    break;
                }
                usleep(10000);
            }
            $transport = new class extends PublicUrlFetcher
            {
                // Test the transport independently of the destination gate; the
                // public fetch entrypoint must always reject this loopback fixture.
                public function requestFixture(int $port, string $path, int $limit): array
                {
                    return $this->download("http://source.invalid:{$port}{$path}", 'source.invalid', $port, '127.0.0.1', 1000, $limit);
                }
            };
            $this->assertSame(8192, strlen($transport->requestFixture($port, '/', 8192)['body']));
            $this->assertSame(302, $transport->requestFixture($port, '/redirect', 8192)['status']);
            $this->expectException(ValidationException::class);
            $transport->requestFixture($port, '/', 4096);
        } finally {
            proc_terminate($server);
            proc_close($server);
        }
    }
}
