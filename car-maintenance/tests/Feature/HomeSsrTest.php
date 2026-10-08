<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class HomeSsrTest extends TestCase
{
    use RefreshDatabase;

    private ?Process $renderer = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(base_path('bootstrap/ssr/ssr.js'))) {
            $this->markTestSkipped('Run npm run build before running the SSR integration tests.');
        }

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        $port = substr($address, strrpos($address, ':') + 1);
        fclose($socket);

        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.throw_on_error' => true,
            'inertia.ssr.url' => 'http://127.0.0.1:'.$port,
        ]);
        Vite::useHotFile(storage_path('framework/testing-ssr.hot'));

        $this->renderer = new Process(['node', 'bootstrap/ssr/ssr.js'], base_path(), ['SSR_PORT' => $port]);
        $this->renderer->start();
        $this->renderer->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'Inertia SSR server started.'));
    }

    protected function tearDown(): void
    {
        $this->renderer?->stop();

        parent::tearDown();
    }

    public function test_homepage_content_and_metadata_are_rendered_in_the_initial_html(): void
    {
        $response = $this->get(route('home'))->assertOk();
        $html = $this->document($response->getContent());

        $this->assertSame(1, $html->query('//head/title')->length);
        $this->assertSame(
            'Free Car Maintenance Tracker & Oil Change Reminders - Motologic',
            $html->evaluate('string(//head/title)')
        );
        $this->assertSame(
            'Motologic tracks mileage and oil changes, predicts your next service before the warning light, and recommends the right engine oil for your car — free.',
            $html->evaluate('string(//head/meta[@name="description"]/@content)')
        );
        $this->assertSame(
            'https://motologic.tech/',
            $html->evaluate('string(//head/link[@rel="canonical"]/@href)')
        );
        $this->assertSame(
            'Motologic — Free Car Maintenance Tracker & Oil Change Reminders',
            $html->evaluate('string(//head/meta[@property="og:title"]/@content)')
        );
        $this->assertSame(
            'https://motologic.tech/images/motologic-hero.png',
            $html->evaluate('string(//head/meta[@property="og:image"]/@content)')
        );
        $this->assertSame(
            'summary_large_image',
            $html->evaluate('string(//head/meta[@name="twitter:card"]/@content)')
        );

        $schema = $html->evaluate('string(//head/script[@id="page-schema"])');
        $this->assertStringContainsString('"@type":"SoftwareApplication"', $schema);
        $this->assertStringContainsString('"@type":"WebSite"', $schema);

        $this->assertStringContainsString('Drive', $html->evaluate('string(//h1)'));
        $this->assertStringContainsString('ready.', $html->evaluate('string(//h1)'));
        $this->assertStringContainsString(
            'Free car maintenance tracker',
            $html->evaluate('string(//main)')
        );
        $this->assertStringContainsString(
            'predicts your next service before the warning light',
            $html->evaluate('string(//main)')
        );
        $this->assertSame('true', $html->evaluate('string(//div[@id="app"]/@data-server-rendered)'));
    }

    public function test_homepage_falls_back_when_the_renderer_is_unavailable(): void
    {
        $this->renderer->stop();
        config(['inertia.ssr.throw_on_error' => false]);

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-server-rendered="true"', false);

        $html = $this->document($response->getContent());
        $this->assertStringContainsString(
            'Motologic tracks mileage and oil changes',
            $html->evaluate('string(//head/meta[@name="description"]/@content)')
        );
    }

    public function test_homepage_renders_when_server_rendering_is_disabled(): void
    {
        config(['inertia.ssr.enabled' => false]);

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-server-rendered="true"', false);

        $html = $this->document($response->getContent());
        $this->assertStringContainsString(
            'Motologic tracks mileage and oil changes',
            $html->evaluate('string(//head/meta[@name="description"]/@content)')
        );
    }

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
