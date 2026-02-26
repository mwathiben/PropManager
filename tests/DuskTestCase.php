<?php

namespace Tests;

use App\Models\Building;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    protected function driver(): RemoteWebDriver
    {
        $userDataDir = sys_get_temp_dir().'/dusk-chrome-'.getmypid();

        $options = (new ChromeOptions)->addArguments(collect([
            '--window-size=1920,1080',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-web-security',
            '--disable-features=VizDisplayCompositor',
            '--disable-extensions',
            '--user-data-dir='.$userDataDir,
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    protected function hasHeadlessDisabled(): bool
    {
        return isset($_SERVER['DUSK_HEADLESS_DISABLED']) ||
               isset($_ENV['DUSK_HEADLESS_DISABLED']);
    }

    protected function createLandlordWithProperty(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        $building = Building::create([
            'property_id' => $property->id,
            'name' => 'Block A',
            'total_floors' => 2,
            'units_per_floor' => 4,
            'landlord_id' => $landlord->id,
            'building_type' => 'residential_apartment',
        ]);

        $units = collect();
        for ($floor = 1; $floor <= 2; $floor++) {
            for ($num = 1; $num <= 4; $num++) {
                $units->push(Unit::create([
                    'building_id' => $building->id,
                    'unit_number' => "A{$floor}0{$num}",
                    'floor_number' => $floor,
                    'status' => 'vacant',
                    'target_rent' => 25000,
                    'landlord_id' => $landlord->id,
                ]));
            }
        }

        return compact('landlord', 'property', 'building', 'units');
    }
}
