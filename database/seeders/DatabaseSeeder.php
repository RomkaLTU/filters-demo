<?php

namespace Database\Seeders;

use App\Enums\BooleanOperator;
use App\Enums\Country;
use App\Enums\Field;
use App\Enums\Operator;
use App\Enums\Status;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The demo seeder: `php artisan migrate --seed` yields the full reviewer state.
 *
 * A fixed RNG seed plus a hand-authored anchor set make the dataset reproduce
 * exactly, so the README may cite precise counts.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** The deterministic RNG seed shared by every faker draw below. */
    private const int RNG_SEED = 20260722;

    /** Total users after seeding, anchors and demo accounts included. */
    private const int TOTAL_USERS = 10_000;

    /** Rows inserted per bulk chunk. */
    private const int CHUNK = 1_000;

    /** Fixed created_at window for bulk users (absolute, so it never drifts). */
    private const string BULK_FROM = '2023-07-01';

    private const string BULK_TO = '2025-07-01';

    /**
     * Seed the application's database. Idempotent: skips when the demo
     * accounts already exist, so it is safe to run on every deploy.
     */
    public function run(): void
    {
        if (User::where('email', 'demo@example.com')->exists()) {
            return;
        }

        fake()->seed(self::RNG_SEED);

        [$demo, $colleague] = $this->seedDemoAccounts();
        $this->seedAnchorUsers();
        $this->seedBulkUsers(self::TOTAL_USERS - User::count());

        [$activeUsers, $europeans] = $this->seedSavedFilters($demo, $colleague);
        $this->warmRecents($demo, $activeUsers, $europeans);
    }

    /**
     * The two reviewer seats, both with password `password`.
     *
     * @return array{0: User, 1: User}
     */
    private function seedDemoAccounts(): array
    {
        $demo = User::factory()->active()->create([
            'name' => 'Demo Reviewer',
            'email' => 'demo@example.com',
            'country' => Country::US->value,
        ]);

        $colleague = User::factory()->active()->create([
            'name' => 'Colleague Chen',
            'email' => 'colleague@example.com',
            'country' => Country::GB->value,
        ]);

        return [$demo, $colleague];
    }

    /**
     * The hand-authored rows README examples and screenshots cite: every status,
     * the DE/FR showpiece matches, the exclusive-bound boundary pair, a
     * null-country row, and several names containing "ann".
     */
    private function seedAnchorUsers(): void
    {
        $anchors = [
            ['Ann Fenwick', 'ann.fenwick@example.com', Status::Active, 'DE', '2025-03-14 09:00:00'],
            ['Joanna Reyes', 'joanna.reyes@example.com', Status::Pending, 'FR', '2025-02-10 11:30:00'],
            ['Annette Cole', 'annette.cole@example.com', Status::Suspended, 'DE', '2025-05-20 14:05:00'],
            ['Hannah Lindqvist', 'hannah.lindqvist@example.com', Status::Active, 'SE', '2024-11-02 08:15:00'],
            ['Susanna Moreau', 'susanna.moreau@example.com', Status::Deactivated, 'FR', '2025-01-10 16:45:00'],
            ['Bruno Keller', 'bruno.keller@example.com', Status::Pending, 'DE', '2025-04-05 10:20:00'],
            ['Camille Fournier', 'camille.fournier@example.com', Status::Suspended, 'FR', '2025-06-12 13:00:00'],
            ['Ingrid Bohm', 'ingrid.bohm@example.com', Status::Pending, 'DE', '2025-01-01 12:00:00'],
            ['Lukas Vogel', 'lukas.vogel@example.com', Status::Pending, 'DE', '2025-01-02 09:00:00'],
            ['Nadia Okonkwo', 'nadia.okonkwo@example.com', Status::Active, null, '2025-02-15 07:40:00'],
            ['Grace Miller', 'grace.miller@example.com', Status::Active, 'US', '2024-08-01 12:00:00'],
            ['Oliver Bennett', 'oliver.bennett@example.com', Status::Active, 'GB', '2024-09-15 15:30:00'],
            ['Sofia Rossi', 'sofia.rossi@example.com', Status::Pending, 'IT', '2025-03-01 09:10:00'],
            ['Diego Alvarez', 'diego.alvarez@example.com', Status::Deactivated, 'ES', '2023-12-01 18:00:00'],
        ];

        foreach ($anchors as [$name, $email, $status, $country, $createdAt]) {
            User::factory()->create([
                'name' => $name,
                'email' => $email,
                'status' => $status,
                'country' => $country,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    /**
     * Faker bulk under the fixed seed: ~70% active, countries spread over the
     * 26-list with a small null slice, created_at across a fixed 2-year window.
     * One shared pre-hashed password and chunked inserts keep this in seconds.
     */
    private function seedBulkUsers(int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $password = bcrypt('password');
        $countries = Country::cases();
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $name = fake()->name();
            $created = fake()->dateTimeBetween(self::BULK_FROM, self::BULK_TO)->format('Y-m-d H:i:s');

            $rows[] = [
                'name' => $name,
                'email' => sprintf('%s.%d@example.com', Str::slug($name, '.'), $i),
                'email_verified_at' => '2024-01-01 00:00:00',
                'password' => $password,
                'status' => $this->weightedStatus(),
                'country' => fake()->numberBetween(1, 100) <= 2
                    ? null
                    : fake()->randomElement($countries)->value,
                'created_at' => $created,
                'updated_at' => $created,
            ];

            if (count($rows) === self::CHUNK) {
                User::insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            User::insert($rows);
        }
    }

    /**
     * The distribution: ~70% active, the rest across the other three statuses.
     */
    private function weightedStatus(): string
    {
        $roll = fake()->numberBetween(1, 100);

        return match (true) {
            $roll <= 70 => Status::Active->value,
            $roll <= 82 => Status::Pending->value,
            $roll <= 92 => Status::Suspended->value,
            default => Status::Deactivated->value,
        };
    }

    /**
     * The seven-filter roster across the two seats. Returns the two filters the
     * recents pivot is pre-warmed with.
     *
     * @return array{0: SavedFilter, 1: SavedFilter}
     */
    private function seedSavedFilters(User $demo, User $colleague): array
    {
        $activeUsers = SavedFilter::factory()->ownedBy($demo)
            ->condition(Field::Status, Operator::Equals, Status::Active->value)
            ->create(['name' => 'Active users']);

        SavedFilter::factory()->ownedBy($demo)->nested()
            ->create(['name' => 'Signups needing attention']);

        SavedFilter::factory()->ownedBy($demo)
            ->condition(Field::Name, Operator::Contains, 'ann')
            ->create(['name' => "Names containing 'ann'"]);

        SavedFilter::factory()->ownedBy($demo)
            ->withDefinition($this->staleEnvelope())
            ->create(['name' => 'Q1 legacy segment']);

        $europeans = SavedFilter::factory()->ownedBy($colleague)->shared()
            ->withDefinition($this->europeansEnvelope())
            ->create(['name' => 'Recently joined Europeans']);

        SavedFilter::factory()->ownedBy($colleague)->shared()
            ->condition(Field::Status, Operator::Equals, Status::Suspended->value)
            ->create(['name' => 'Suspended accounts']);

        SavedFilter::factory()->ownedBy($colleague)
            ->condition(Field::Country, Operator::Equals, Country::US->value)
            ->create(['name' => "Colleague's private notes"]);

        return [$activeUsers, $europeans];
    }

    /**
     * Pre-warm demo@'s recents to 2-of-3 with fixed timestamps, so the section
     * shows on first login and the reviewer's first apply pushes a third entry.
     */
    private function warmRecents(User $demo, SavedFilter $activeUsers, SavedFilter $europeans): void
    {
        $demo->recentFilters()->attach([
            $activeUsers->id => ['last_used_at' => '2026-07-21 10:15:00'],
            $europeans->id => ['last_used_at' => '2026-07-21 16:40:00'],
        ]);
    }

    /**
     * A valid shared definition: (country in six European codes) AND created
     * after a fixed date.
     *
     * @return array{version: int, root: array<string, mixed>}
     */
    private function europeansEnvelope(): array
    {
        return $this->envelope($this->group(BooleanOperator::And, [
            $this->group(BooleanOperator::Or, [
                $this->condition(Field::Country, Operator::Equals, 'DE'),
                $this->condition(Field::Country, Operator::Equals, 'FR'),
                $this->condition(Field::Country, Operator::Equals, 'GB'),
                $this->condition(Field::Country, Operator::Equals, 'ES'),
                $this->condition(Field::Country, Operator::Equals, 'IT'),
                $this->condition(Field::Country, Operator::Equals, 'NL'),
            ]),
            $this->condition(Field::CreatedAt, Operator::GreaterThan, '2024-07-01'),
        ]));
    }

    /**
     * A stored definition that passes the JSONB object check but fails the DTO
     * boundary — the owner-side stale refuse path. "age" is not a known field.
     *
     * @return array{version: int, root: array<string, mixed>}
     */
    private function staleEnvelope(): array
    {
        return $this->envelope($this->group(BooleanOperator::And, [
            ['type' => 'condition', 'field' => 'age', 'operator' => 'greater_than', 'value' => '30'],
        ]));
    }

    /**
     * @param  array<string, mixed>  $root
     * @return array{version: int, root: array<string, mixed>}
     */
    private function envelope(array $root): array
    {
        return ['version' => 1, 'root' => $root];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function group(BooleanOperator $boolean, array $children): array
    {
        return ['type' => 'group', 'boolean' => $boolean->value, 'children' => $children];
    }

    /**
     * @return array<string, mixed>
     */
    private function condition(Field $field, Operator $operator, string $value): array
    {
        return ['type' => 'condition', 'field' => $field->value, 'operator' => $operator->value, 'value' => $value];
    }
}
