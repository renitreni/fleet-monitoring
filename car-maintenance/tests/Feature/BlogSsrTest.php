<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BlogSsrTest extends TestCase
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

    public function test_article_content_and_metadata_are_rendered_in_the_initial_html(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1));
        $post = BlogPost::factory()->published()->create([
            'title' => 'Oil <script>alert(1)</script> guide',
            'seo_title' => 'Low-mileage oil changes',
            'meta_description' => 'Oil advice for low-mileage cars.',
            'body_markdown' => '## Check the oil annually',
            'published_at' => '2026-09-24 18:00:00',
        ]);

        $response = $this->get(route('blog.show', $post->slug).'?utm_source=facebook')->assertOk();
        $html = $this->document($response->getContent());

        $this->assertSame(1, $html->query('//head/title')->length);
        $this->assertStringStartsWith('Low-mileage oil changes - ', $html->evaluate('string(//head/title)'));
        $this->assertSame($post->title, trim($html->evaluate('string(//article//h1)')));
        $this->assertSame(0, $html->query('//article//script')->length);
        $this->assertSame('Check the oil annually', $html->evaluate('string(//article//h2)'));
        $this->assertSame('Oil advice for low-mileage cars.', $html->evaluate('string(//head/meta[@name="description"]/@content)'));
        $this->assertSame(route('blog.show', $post->slug), $html->evaluate('string(//head/link[@rel="canonical"]/@href)'));
        $this->assertSame(route('blog.show', $post->slug), $html->evaluate('string(//head/meta[@property="og:url"]/@content)'));
        $this->assertSame('September 25, 2026', $html->evaluate('string(//article//time)'));
        $this->assertSame('true', $html->evaluate('string(//div[@id="app"]/@data-server-rendered)'));
    }

    public function test_blog_index_renders_published_links_and_excludes_drafts(): void
    {
        $published = BlogPost::factory()->published()->create();
        $draft = BlogPost::factory()->create();

        $response = $this->get(route('blog.index'))->assertOk();
        $html = $this->document($response->getContent());

        $this->assertGreaterThan(0, $html->query('//a[@href="'.route('blog.show', $published->slug).'"]')->length);
        $this->assertSame(0, $html->query('//a[@href="'.route('blog.show', $draft->slug).'"]')->length);
        $this->get(route('blog.show', $draft->slug))->assertNotFound();
    }

    public function test_authenticated_blog_renders(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('blog.index'))->assertOk();
        $this->assertSame('true', $this->document($response->getContent())->evaluate('string(//div[@id="app"]/@data-server-rendered)'));

    }

    public function test_other_pages_keep_client_rendering(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('data-server-rendered="true"', false);
    }

    public function test_blog_remains_available_when_server_rendering_is_disabled(): void
    {
        config(['inertia.ssr.enabled' => false]);
        $post = BlogPost::factory()->published()->create();

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertDontSee('data-server-rendered="true"', false);
    }

    public function test_blog_falls_back_when_the_renderer_is_unavailable(): void
    {
        $this->renderer->stop();
        config(['inertia.ssr.throw_on_error' => false]);
        $post = BlogPost::factory()->published()->create();

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertDontSee('data-server-rendered="true"', false);
    }

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
