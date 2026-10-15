<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WeatherService
{
    public function current(?string $city = null): ?array
    {
        $latitude = config('services.weather.latitude');
        $longitude = config('services.weather.longitude');
        $location = config('services.weather.city', 'Configured location');
        $searchWarning = null;

        if ($city !== null && trim($city) !== '') {
            $searchedLocation = $this->coordinatesFor(trim($city));

            if ($searchedLocation !== null) {
                $latitude = $searchedLocation['latitude'];
                $longitude = $searchedLocation['longitude'];
                $location = $searchedLocation['city'];
            } else {
                Log::warning('Weather city was not found, falling back to configured location.', [
                    'city' => trim($city),
                ]);
                $searchWarning = 'That city could not be found. Showing the configured location instead.';
            }
        }

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $cacheKey = 'weather.current.'.hash('sha256', $latitude.','.$longitude);
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['_cached_at']) && Carbon::parse($cached['_cached_at'])->isAfter(now()->subMinutes(10))) {
            unset($cached['_cached_at']);
            $cached['city'] = $location;
            $cached['stale'] = false;

            unset($cached['search_warning']);

            if ($searchWarning !== null) {
                $cached['search_warning'] = $searchWarning;
            }

            return $cached;
        }

        try {
            $response = Http::acceptJson()
                ->retry(2, 200)
                ->timeout((int) config('services.weather.timeout', 15))
                ->get(config('services.weather.endpoint'), [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,precipitation',
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
                    'forecast_days' => 5,
                    'timezone' => 'auto',
                ]);
        } catch (Throwable $exception) {
            Log::warning('Weather API connection failed.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->cachedWeather($cacheKey, $location, $searchWarning);
        }

        if (! $response->successful()) {
            Log::warning('Weather API returned an error.', [
                'status' => $response->status(),
            ]);

            return $this->cachedWeather($cacheKey, $location, $searchWarning);
        }

        $current = $response->json('current');

        if (! is_array($current) || ! isset(
            $current['temperature_2m'],
            $current['relative_humidity_2m'],
            $current['weather_code'],
            $current['wind_speed_10m']
        ) || ! is_numeric($current['temperature_2m'])
            || ! is_numeric($current['relative_humidity_2m'])
            || ! is_numeric($current['weather_code'])
            || ! is_numeric($current['wind_speed_10m'])) {
            Log::warning('Weather API returned incomplete data.');

            return $this->cachedWeather($cacheKey, $location, $searchWarning);
        }

        $forecast = $this->forecast($response->json('daily'));

        $weather = [
            'city' => $location,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'temperature' => $current['temperature_2m'],
            'temperature_unit' => $response->json('current_units.temperature_2m', '°C'),
            'condition' => $this->description((int) $current['weather_code']),
            'icon' => $this->icon((int) $current['weather_code']),
            'humidity' => $current['relative_humidity_2m'],
            'wind_speed' => $current['wind_speed_10m'],
            'wind_speed_unit' => $response->json('current_units.wind_speed_10m', 'km/h'),
            'rain' => $current['precipitation'] ?? 0,
            'forecast' => $forecast,
            'stale' => false,
        ];

        if ($searchWarning !== null) {
            $weather['search_warning'] = $searchWarning;
        }

        $cachedWeather = $weather;
        unset($cachedWeather['search_warning']);
        Cache::put($cacheKey, $cachedWeather + ['_cached_at' => now()->toIso8601String()], now()->addHours(6));

        return $weather;
    }

    private function coordinatesFor(string $city): ?array
    {
        try {
            $response = Http::acceptJson()
                ->retry(2, 200)
                ->timeout((int) config('services.weather.timeout', 15))
                ->get(config('services.weather.geocoding_endpoint'), [
                    'name' => $city,
                    'count' => 1,
                    'language' => 'en',
                    'format' => 'json',
                ]);
        } catch (Throwable $exception) {
            Log::warning('Weather location lookup failed.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $result = $response->json('results.0');

        if (! $response->successful() || ! is_array($result) || ! isset($result['latitude'], $result['longitude'], $result['name'])) {
            Log::warning('Weather location was not found.', ['city' => $city]);

            return null;
        }

        return [
            'city' => $result['name'],
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
        ];
    }

    private function cachedWeather(string $cacheKey, string $location, ?string $searchWarning): ?array
    {
        $cached = Cache::get($cacheKey);

        if (! is_array($cached) || ! isset($cached['_cached_at'])) {
            return null;
        }

        unset($cached['_cached_at']);
        unset($cached['search_warning']);
        $cached['city'] = $location;
        $cached['stale'] = true;

        if ($searchWarning !== null) {
            $cached['search_warning'] = $searchWarning;
        }

        return $cached;
    }

    private function forecast(mixed $daily): array
    {
        if (! is_array($daily)
            || ! is_array($daily['time'] ?? null)
            || ! is_array($daily['weather_code'] ?? null)
            || ! is_array($daily['temperature_2m_max'] ?? null)
            || ! is_array($daily['temperature_2m_min'] ?? null)
            || ! is_array($daily['precipitation_probability_max'] ?? null)) {
            return [];
        }

        $items = [];
        $count = min(count($daily['time']), 5);

        for ($i = 0; $i < $count; $i++) {
            $code = $daily['weather_code'][$i] ?? null;
            $date = $daily['time'][$i] ?? null;

            if (! is_numeric($code)
                || ! is_numeric($daily['temperature_2m_max'][$i] ?? null)
                || ! is_numeric($daily['temperature_2m_min'][$i] ?? null)) {
                continue;
            }

            $items[] = [
                'day' => is_string($date) && Carbon::hasFormat($date, 'Y-m-d') ? Carbon::parse($date)->translatedFormat('D') : 'Day',
                'condition' => $this->description((int) $code),
                'icon' => $this->icon((int) $code),
                'high' => $daily['temperature_2m_max'][$i],
                'low' => $daily['temperature_2m_min'][$i],
                'rain' => is_numeric($daily['precipitation_probability_max'][$i] ?? null) ? $daily['precipitation_probability_max'][$i] : 0,
            ];
        }

        return $items;
    }

    private function description(int $weatherCode): string
    {
        return match (true) {
            $weatherCode === 0 => 'Clear sky',
            in_array($weatherCode, [1, 2, 3], true) => 'Partly cloudy',
            in_array($weatherCode, [45, 48], true) => 'Foggy',
            in_array($weatherCode, [51, 53, 55, 56, 57], true) => 'Drizzle',
            in_array($weatherCode, [61, 63, 65, 66, 67], true) => 'Rain',
            in_array($weatherCode, [71, 73, 75, 77], true) => 'Snow',
            in_array($weatherCode, [80, 81, 82], true) => 'Rain showers',
            in_array($weatherCode, [85, 86], true) => 'Snow showers',
            $weatherCode === 95 => 'Thunderstorm risk',
            $weatherCode === 96 => 'Storm with hail',
            $weatherCode === 99 => 'Severe storm',
            default => 'Condition unavailable',
        };
    }

    private function icon(int $weatherCode): string
    {
        return match (true) {
            $weatherCode === 0 => '☀️',
            in_array($weatherCode, [1, 2, 3], true) => '⛅',
            in_array($weatherCode, [45, 48], true) => '🌫️',
            in_array($weatherCode, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true) => '🌧️',
            in_array($weatherCode, [71, 73, 75, 77, 85, 86], true) => '❄️',
            in_array($weatherCode, [95, 96, 99], true) => '⛈️',
            default => '🌤️',
        };
    }
}
