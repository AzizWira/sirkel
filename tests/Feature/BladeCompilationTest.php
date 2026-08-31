<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class BladeCompilationTest extends TestCase
{
    #[Test]
    public function every_blade_view_compiles_to_valid_php(): void
    {
        if (! function_exists('exec')) {
            $this->markTestSkipped('PHP exec() tidak tersedia; Blade QA parse dilewati pada environment ini.');
        }

        $root = resource_path('views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $checked = 0;

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            $compiled = Blade::compileString($source);
            $temp = tempnam(sys_get_temp_dir(), 'sirkel_blade_');
            file_put_contents($temp, $compiled);

            $output = [];
            $exitCode = 0;
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1', $output, $exitCode);
            @unlink($temp);

            $this->assertSame(
                0,
                $exitCode,
                "Blade parse gagal: {$file->getPathname()}\n".implode("\n", $output)
            );

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'Tidak ada Blade view yang diperiksa.');
    }
}
