<?php

namespace App\Console\Commands;

use App\Models\FoodComposition;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;

class ImportFoodsCommand extends Command
{
    protected $signature = 'nutrisaas:import-foods
        {--fresh : Delete existing imported global foods before importing}';

    protected $description = 'Import global food composition datasets into the master database.';

    private const DATASETS = [
        [
            'source' => 'incap',
            'path' => '../docs/data/incap.csv',
            'code' => 'codigo_incap',
            'name' => 'alimento',
            'category' => 'categoria',
            'serving' => 'porcion',
            'serving_size' => 'peso_g',
            'energy' => 'energia_kcal',
            'protein' => 'proteina_g',
            'fat' => 'grasa_g',
            'carbohydrate' => 'carbohidratos_g',
            'fiber' => 'fibra_g',
            'calcium' => 'calcio_mg',
            'iron' => 'hierro_mg',
            'sodium' => 'sodio_mg',
        ],
        [
            'source' => 'tca_ecuador_2021_usfq',
            'path' => '../docs/data/tca_ecuador_2021_usfq.csv',
            'code' => 'id',
            'name' => 'alimento',
            'category' => 'grupo',
            'serving' => 'porcion_base',
            'serving_size' => 'cantidad_base',
            'energy' => 'energia_kcal',
            'protein' => 'proteina_g',
            'fat' => 'grasa_total_g',
            'carbohydrate' => 'carbohidratos_g',
            'fiber' => 'fibra_g',
            'calcium' => 'calcio_mg',
            'iron' => 'hierro_mg',
            'sodium' => 'sodio_mg',
        ],
    ];

    public function handle(): int
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        if ($this->option('fresh')) {
            FoodComposition::query()->delete();
        }

        foreach (self::DATASETS as $dataset) {
            $path = base_path($dataset['path']);

            if (! is_file($path)) {
                $this->error("Dataset not found: {$path}");
                $errors++;
                continue;
            }

            $this->info("Importing {$dataset['source']}...");

            foreach ($this->readCsv($path) as $line => $row) {
                try {
                    $payload = $this->mapRow($dataset, $row);

                    if ($payload['name'] === '') {
                        $skipped++;
                        continue;
                    }

                    $food = $this->findExistingFood($payload);
                    $food->fill($payload);
                    $food->save();

                    $food->wasRecentlyCreated ? $created++ : $updated++;
                } catch (Throwable $exception) {
                    $errors++;
                    $this->warn("Line {$line} skipped: {$exception->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info("Created: {$created}");
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function readCsv(string $path): iterable
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV: {$path}");
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                return;
            }

            $headers = array_map(
                fn (?string $header): string => trim((string) $header),
                $headers
            );

            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                $values = array_pad($values, count($headers), null);

                yield $line => array_combine($headers, array_slice($values, 0, count($headers)));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<string, string> $dataset
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $dataset, array $row): array
    {
        $serving = $this->parseServing(
            $this->value($row, $dataset['serving']),
            $this->number($this->value($row, $dataset['serving_size']))
        );

        $mappedKeys = [
            $dataset['code'],
            $dataset['name'],
            $dataset['category'],
            $dataset['serving'],
            $dataset['serving_size'],
            $dataset['energy'],
            $dataset['protein'],
            $dataset['fat'],
            $dataset['carbohydrate'],
            $dataset['fiber'],
            $dataset['calcium'],
            $dataset['iron'],
            $dataset['sodium'],
        ];

        return [
            'source' => $dataset['source'],
            'code' => $this->nullableString($this->value($row, $dataset['code'])),
            'name' => $this->value($row, $dataset['name']),
            'category' => $this->nullableString($this->value($row, $dataset['category'])),
            'serving_size' => $serving['size'],
            'unit' => $serving['unit'],
            'energy_kcal' => $this->number($this->value($row, $dataset['energy'])),
            'protein_g' => $this->number($this->value($row, $dataset['protein'])),
            'fat_g' => $this->number($this->value($row, $dataset['fat'])),
            'carbohydrate_g' => $this->number($this->value($row, $dataset['carbohydrate'])),
            'fiber_g' => $this->number($this->value($row, $dataset['fiber'])),
            'calcium_mg' => $this->number($this->value($row, $dataset['calcium'])),
            'iron_mg' => $this->number($this->value($row, $dataset['iron'])),
            'sodium_mg' => $this->number($this->value($row, $dataset['sodium'])),
            'raw_data' => Arr::except($row, $mappedKeys),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function findExistingFood(array $payload): FoodComposition
    {
        $query = FoodComposition::query()->where('source', $payload['source']);

        if ($payload['code']) {
            $query->where(function ($query) use ($payload) {
                $query->where('code', $payload['code'])
                    ->orWhere('name', $payload['name']);
            });
        } else {
            $query->where('name', $payload['name']);
        }

        return $query->first() ?? new FoodComposition();
    }

    private function parseServing(string $serving, ?float $fallbackSize): array
    {
        if (preg_match('/^\s*([\d.,]+)\s*([[:alpha:]]+)\s*$/u', $serving, $matches)) {
            return [
                'size' => $this->number($matches[1]),
                'unit' => strtolower($matches[2]),
            ];
        }

        return [
            'size' => $fallbackSize,
            'unit' => $this->nullableString($serving),
        ];
    }

    private function value(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }

    private function nullableString(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function number(string $value): ?float
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(' ', '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
