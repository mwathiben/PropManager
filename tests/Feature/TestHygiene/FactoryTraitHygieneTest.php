<?php

declare(strict_types=1);

namespace Tests\Feature\TestHygiene;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Tests\TestCase;
use Throwable;

/**
 * Static guard against the silent factory<->model trap surfaced by the
 * debt audit: a model can have a factory file in database/factories while
 * the model itself omits the HasFactory trait, so Model::factory() throws
 * BadMethodCallException at runtime — discovered only when someone writes a
 * test. Conversely, a model can declare HasFactory while no factory exists,
 * so Model::factory() fatals with "class not found".
 *
 * Both directions are asserted here so the gap cannot reopen.
 */
class FactoryTraitHygieneTest extends TestCase
{
    public function test_every_factory_targets_a_model_using_the_hasfactory_trait(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(database_path('factories')) as $relative => $path) {
            $factoryClass = 'Database\\Factories\\'.$relative;

            if (! class_exists($factoryClass)) {
                $offenders[] = "database/factories/{$relative}.php -> class {$factoryClass} is not autoloadable (filename/class mismatch?)";

                continue;
            }

            $modelClass = (new $factoryClass)->modelName();

            if (! class_exists($modelClass)) {
                $offenders[] = "{$factoryClass} targets {$modelClass}, which does not exist";

                continue;
            }

            if (! in_array(HasFactory::class, class_uses_recursive($modelClass), true)) {
                $offenders[] = "{$modelClass} has a factory ({$factoryClass}) but is missing `use HasFactory;`";
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Each model with a factory must `use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;`,\n".
            "otherwise Model::factory() throws BadMethodCallException at runtime:\n".implode("\n", $offenders),
        );
    }

    public function test_every_model_using_the_hasfactory_trait_has_a_resolvable_factory(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(app_path('Models')) as $relative => $path) {
            $modelClass = 'App\\Models\\'.$relative;

            if (! class_exists($modelClass)) {
                continue;
            }

            $reflection = new ReflectionClass($modelClass);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            if (! in_array(HasFactory::class, class_uses_recursive($modelClass), true)) {
                continue;
            }

            try {
                $modelClass::factory();
            } catch (Throwable $e) {
                $offenders[] = "{$modelClass} declares HasFactory but has no resolvable factory: {$e->getMessage()}";
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Each model declaring HasFactory must have a factory in database/factories/ (or override newFactory()),\n".
            "otherwise Model::factory() fatals with a missing-class error:\n".implode("\n", $offenders),
        );
    }

    /**
     * Map every *.php file under a directory to its PSR-4 sub-path
     * (e.g. "Sub/Foo.php" => "Sub\\Foo"), keyed by that sub-path.
     *
     * @return array<string, string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(['/', '\\'], '\\', $file->getRelativePathname());
            $relative = substr($relative, 0, -strlen('.php'));

            $files[$relative] = $file->getPathname();
        }

        return $files;
    }
}
