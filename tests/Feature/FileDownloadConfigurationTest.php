<?php declare(strict_types=1);

namespace Programado\Komando\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Programado\Komando\Tests\TestCase;

final class FileDownloadConfigurationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('komando.files.download.path', 'downloads/{file}');
        $app['config']->set('komando.files.download.middleware', ['web']);
    }

    public function test_the_download_path_and_middleware_are_configurable(): void
    {
        $route = Route::getRoutes()->getByName('komando.files.download');

        $this->assertNotNull($route);
        $this->assertSame('downloads/{file}', $route->uri());
        $this->assertSame(['web'], $route->middleware());
    }
}
