<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    /**
     * Mirrors konora-frontend/src/app/core/data/sample-public-events.ts (titles, categories, venues, pricing).
     * `sample_image` is the filename under public/samples/.
     *
     * @return list<array{title: string, category: string, venue_name: string, venue_address: string, price_ngn: int|null, sample_image: string, trending?: bool}>
     */
    private static function eventDefinitions(): array
    {
        return [
            ['title' => 'Lagos Jazz & Soul Festival', 'category' => 'music', 'venue_name' => 'Eko Convention Centre', 'venue_address' => 'Eko Convention Centre, Victoria Island, Lagos', 'price_ngn' => 15_000, 'sample_image' => '1-4966464.jpg', 'trending' => true],
            ['title' => 'Lagos Tech Summit 2024', 'category' => 'tech', 'venue_name' => 'Landmark Event Centre', 'venue_address' => 'Landmark Event Centre, Oniru, Lagos', 'price_ngn' => null, 'sample_image' => 'images (1).jpeg'],
            ['title' => 'Taste of Lagos Food Fair', 'category' => 'food', 'venue_name' => 'Muri Okunola Park', 'venue_address' => 'Muri Okunola Park, Victoria Island, Lagos', 'price_ngn' => 8_500, 'sample_image' => 'images (2).jpeg'],
            ['title' => 'Vibe on the Beach: Mainland Edition', 'category' => 'music', 'venue_name' => 'Landmark Beach', 'venue_address' => 'Landmark Beach, Victoria Island, Lagos', 'price_ngn' => 15_000, 'sample_image' => '1-4966464.jpg'],
            ['title' => 'Product Design Lagos Meetup', 'category' => 'tech', 'venue_name' => 'CcHub Yaba', 'venue_address' => '294 Herbert Macaulay Way, Yaba, Lagos', 'price_ngn' => 5_000, 'sample_image' => 'images (1).jpeg'],
            ['title' => 'Lagos Contemporary Art Night', 'category' => 'art', 'venue_name' => 'National Theatre', 'venue_address' => 'Iganmu, Lagos', 'price_ngn' => 12_000, 'sample_image' => 'images (2).jpeg'],
            ['title' => 'Laugh Out Lagos: December Special', 'category' => 'comedy', 'venue_name' => 'Muson Centre', 'venue_address' => 'Onikan, Lagos Island, Lagos', 'price_ngn' => 10_000, 'sample_image' => '1-4966464.jpg'],
            ['title' => 'Lagos City Half Marathon', 'category' => 'sports', 'venue_name' => 'Lekki-Epe Expressway', 'venue_address' => 'Lekki-Epe Expressway, Lagos', 'price_ngn' => 20_000, 'sample_image' => 'images (1).jpeg'],
            ['title' => 'Founders Breakfast Club Abuja', 'category' => 'business', 'venue_name' => 'Transcorp Hilton', 'venue_address' => '1 Aguiyi Ironsi Street, Maitama, Abuja', 'price_ngn' => 25_000, 'sample_image' => 'images (2).jpeg'],
            ['title' => 'Abuja Fashion Week Preview', 'category' => 'fashion', 'venue_name' => 'Abuja International Conference Centre', 'venue_address' => 'Herbert Macaulay Way, Central Area, Abuja', 'price_ngn' => 18_000, 'sample_image' => '1-4966464.jpg'],
            ['title' => 'Abuja Street Food Festival', 'category' => 'food', 'venue_name' => 'Millennium Park', 'venue_address' => '3 Usuma Street, Maitama, Abuja', 'price_ngn' => 7_000, 'sample_image' => 'images (1).jpeg'],
            ['title' => 'AI in Africa Summit', 'category' => 'tech', 'venue_name' => 'Eko Hotel & Suites', 'venue_address' => 'Adetokunbo Ademola Street, Victoria Island, Lagos', 'price_ngn' => 45_000, 'sample_image' => 'images (2).jpeg'],
        ];
    }

    /**
     * Resolve `konora-frontend/public/samples` when the repo layout differs (sibling, nested, or custom env).
     */
    public static function resolveSamplesDirectory(): ?string
    {
        $candidates = array_values(array_filter([
            env('KONORA_FRONTEND_SAMPLES_PATH'),
            dirname(base_path()).DIRECTORY_SEPARATOR.'konora-frontend'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'samples',
            base_path('..'.DIRECTORY_SEPARATOR.'konora-frontend'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'samples'),
            base_path('..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'konora-frontend'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'samples'),
        ]));

        foreach ($candidates as $dir) {
            if (File::isDirectory($dir)) {
                return $dir;
            }
        }

        return null;
    }

    public function run(): void
    {
        $organizers = User::query()->where('role', 'organizer')->orderBy('id')->get();
        if ($organizers->isEmpty()) {
            $this->command?->warn('No organizers found. Run OrganizerSeeder first.');

            return;
        }

        $samplesDir = self::resolveSamplesDirectory();
        if ($samplesDir === null) {
            $this->command?->warn(
                'Samples directory not found. Set KONORA_FRONTEND_SAMPLES_PATH in .env to the absolute path of konora-frontend/public/samples, '.
                'or keep konora-frontend next to konora-backend. Events will be created without banners.'
            );
        }

        $definitions = self::eventDefinitions();
        $baseDate = Carbon::now()->addWeeks(2)->startOfDay();
        $anyBannerCopied = false;

        foreach ($definitions as $index => $def) {
            $organizer = $organizers[$index % $organizers->count()];
            $eventDate = (clone $baseDate)->addDays($index * 5);

            $bannerPath = $samplesDir ? $this->copySampleBanner($samplesDir, $def['sample_image']) : null;
            if ($bannerPath !== null) {
                $anyBannerCopied = true;
            }

            $event = Event::query()->updateOrCreate(
                [
                    'user_id' => $organizer->id,
                    'title' => $def['title'],
                ],
                [
                    'description' => $this->blurb($def['title'], $def['venue_name']),
                    'category' => $def['category'],
                    'custom_category' => null,
                    'date' => $eventDate->toDateString(),
                    'start_time' => '18:00:00',
                    'end_time' => '22:30:00',
                    'venue_name' => $def['venue_name'],
                    'venue_address' => $def['venue_address'],
                    'banner_path' => $bannerPath,
                    'visibility' => 'public',
                    'status' => 'published',
                ]
            );

            $this->syncTiers($event, $def['price_ngn'], $index);
        }

        if ($anyBannerCopied) {
            $this->ensurePublicStorageLink();
        }
    }

    /**
     * Banner URLs use /storage/... — the public/storage symlink must exist or files 404.
     */
    private function ensurePublicStorageLink(): void
    {
        try {
            Artisan::call('storage:link', ['--force' => true]);
            if (Artisan::output() !== '') {
                $this->command?->line(trim(Artisan::output()));
            }
        } catch (\Throwable $e) {
            $this->command?->warn('Could not run php artisan storage:link — banner URLs may 404 until you run it manually: '.$e->getMessage());
        }
    }

    private function blurb(string $title, string $venue): string
    {
        return "Join us for {$title} at {$venue}. Tickets are limited — book early on Konora.";
    }

    /**
     * Copy a file from konora-frontend/public/samples into storage/app/public/banners.
     */
    private function copySampleBanner(string $samplesDir, string $basename): ?string
    {
        $src = $samplesDir.DIRECTORY_SEPARATOR.$basename;
        if (! File::isFile($src)) {
            $this->command?->warn("Sample image missing (skipped): {$src}");

            return null;
        }

        $ext = pathinfo($basename, PATHINFO_EXTENSION) ?: 'jpg';
        $slug = Str::slug(pathinfo($basename, PATHINFO_FILENAME));
        if ($slug === '') {
            $slug = 'banner';
        }
        $safe = $slug.'_'.Str::lower(Str::random(6)).'.'.$ext;
        $relative = 'banners/'.$safe;

        Storage::disk('public')->makeDirectory('banners');
        Storage::disk('public')->put($relative, File::get($src));

        return $relative;
    }

    private function syncTiers(Event $event, ?int $priceNgn, int $index): void
    {
        $event->ticketTiers()->delete();

        if ($priceNgn === null) {
            $event->ticketTiers()->create([
                'name' => 'General',
                'custom_name' => null,
                'description' => 'General admission — free entry.',
                'price' => '0.01',
                'capacity' => 5_000,
                'sold_count' => min(120, 5_000),
                'sales_start' => now()->subDays(14),
                'sales_end' => $event->date.' 23:59:59',
            ]);

            return;
        }

        $base = round($priceNgn, 2);

        $event->ticketTiers()->createMany([
            [
                'name' => 'Early Bird',
                'custom_name' => null,
                'description' => 'Limited early pricing.',
                'price' => max(0.01, round($base * 0.85, 2)),
                'capacity' => 200,
                'sold_count' => min(80 + ($index % 40), 200),
                'sales_start' => now()->subDays(30),
                'sales_end' => now()->addDays(60),
            ],
            [
                'name' => 'General',
                'custom_name' => null,
                'description' => 'Standard entry.',
                'price' => $base,
                'capacity' => 800,
                'sold_count' => min(150 + ($index % 100), 800),
                'sales_start' => now()->subDays(14),
                'sales_end' => $event->date.' 23:59:59',
            ],
            [
                'name' => 'VIP',
                'custom_name' => null,
                'description' => 'Premium seating and lounge access.',
                'price' => round($base * 1.6, 2),
                'capacity' => 80,
                'sold_count' => min(10 + ($index % 25), 80),
                'sales_start' => now()->subDays(14),
                'sales_end' => $event->date.' 23:59:59',
            ],
        ]);
    }
}
