<?php

namespace Tests\Feature;

use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_weather_is_cached_and_malformed_forecast_rows_are_ignored(): void
    {
        Cache::flush();
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 24,
                    'relative_humidity_2m' => 60,
                    'weather_code' => 0,
                    'wind_speed_10m' => 8,
                ],
                'daily' => [
                    'time' => ['not-a-date', '2026-10-09'],
                    'weather_code' => [0, 0],
                    'temperature_2m_max' => [29, null],
                    'temperature_2m_min' => [20, 19],
                    'precipitation_probability_max' => [10, 0],
                ],
            ]),
        ]);

        $weatherService = app(WeatherService::class);
        $first = $weatherService->current();
        $second = $weatherService->current();

        $this->assertNotNull($first);
        $this->assertSame('Day', $first['forecast'][0]['day']);
        $this->assertCount(1, $first['forecast']);
        $this->assertFalse($second['stale']);
        Http::assertSentCount(1);
    }

    public function test_city_not_found_is_explained_while_default_weather_remains_available(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response(['results' => []]),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 24,
                    'relative_humidity_2m' => 60,
                    'weather_code' => 0,
                    'wind_speed_10m' => 8,
                ],
            ]),
        ]);

        $weather = app(WeatherService::class)->current('Not A Real City');

        $this->assertSame(config('services.weather.city'), $weather['city']);
        $this->assertStringContainsString('could not be found', $weather['search_warning']);
    }

    public function test_last_successful_weather_is_returned_when_the_provider_fails(): void
    {
        Cache::flush();
        Http::fake([
            'api.open-meteo.com/*' => Http::sequence()
                ->push(['current' => [
                    'temperature_2m' => 24,
                    'relative_humidity_2m' => 60,
                    'weather_code' => 0,
                    'wind_speed_10m' => 8,
                ]])
                ->push([], 503)
                ->push([], 503)
                ->push([], 503),
        ]);

        $service = app(WeatherService::class);
        $this->assertNotNull($service->current());

        Carbon::setTestNow(now()->addMinutes(11));
        $stale = $service->current();
        Carbon::setTestNow();

        $this->assertNotNull($stale);
        $this->assertTrue($stale['stale']);
        $this->assertSame(24, $stale['temperature']);
    }
}
