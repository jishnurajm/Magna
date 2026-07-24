<?php

declare(strict_types=1);

namespace Magna\Content\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Magna\Content\SchemaRegistry;

/**
 * Re-encrypt (or encrypt for the first time) all values for a given
 * encrypted schema field.
 *
 * Use this after setting "encrypted": true on a field that already has
 * plaintext data in the database. The command reads every row in chunks,
 * tests whether the value is already a valid Laravel ciphertext, and
 * encrypts any plaintext values it finds.
 *
 * Usage:
 *   php artisan magna:schema:encrypt --type=article --field=private_notes
 *   php artisan magna:schema:encrypt --type=article --field=private_notes --chunk=500
 */
class SchemaEncryptCommand extends Command
{
    protected $signature = 'magna:schema:encrypt
        {--type= : The content type handle (e.g. article)}
        {--field= : The field handle to encrypt}
        {--chunk=200 : Rows to process per DB query}';

    protected $description = 'Encrypt existing plaintext values for a schema field marked encrypted=true';

    public function handle(SchemaRegistry $registry): int
    {
        $typeHandle = $this->option('type');
        $fieldHandle = $this->option('field');

        if (! is_string($typeHandle) || $typeHandle === '') {
            $this->error('--type is required.');

            return self::FAILURE;
        }

        if (! is_string($fieldHandle) || $fieldHandle === '') {
            $this->error('--field is required.');

            return self::FAILURE;
        }

        $type = $registry->get($typeHandle);
        if ($type === null) {
            $this->error("Unknown content type: \"{$typeHandle}\".");

            return self::FAILURE;
        }

        $field = $type->getField($fieldHandle);
        if ($field === null) {
            $this->error("Field \"{$fieldHandle}\" not found on type \"{$typeHandle}\".");

            return self::FAILURE;
        }

        if (! $field->encrypted) {
            $this->error("Field \"{$fieldHandle}\" on \"{$typeHandle}\" is not marked encrypted=true in the schema.");

            return self::FAILURE;
        }

        $table = $type->tableName();
        $chunk = max(1, (int) $this->option('chunk'));

        $total = DB::table($table)->whereNotNull($fieldHandle)->count();
        if ($total === 0) {
            $this->info("No non-null values found for {$typeHandle}.{$fieldHandle}.");

            return self::SUCCESS;
        }

        $this->info("Scanning {$total} rows in {$table}.{$fieldHandle} (chunk={$chunk})…");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $encrypted = 0;
        $skipped = 0;

        DB::table($table)->whereNotNull($fieldHandle)->orderBy('id')->chunk($chunk, function ($rows) use ($table, $fieldHandle, $bar, &$encrypted, &$skipped): void {
            // Wrap each chunk in a transaction: reduces N auto-committed writes to 1.
            DB::transaction(function () use ($rows, $table, $fieldHandle, $bar, &$encrypted, &$skipped): void {
                foreach ($rows as $row) {
                    /** @var mixed $raw */
                    $raw = $row->{$fieldHandle};

                    if (! is_string($raw)) {
                        $bar->advance();
                        $skipped++;

                        continue;
                    }

                    // Detect Laravel ciphertext by envelope structure rather than by
                    // attempting decryption. Decryption-based detection silently
                    // double-encrypts values after an APP_KEY rotation (old ciphertext
                    // throws DecryptException and is mistaken for plaintext).
                    if ($this->looksLikeEncrypted($raw)) {
                        $bar->advance();
                        $skipped++;

                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([$fieldHandle => Crypt::encryptString($raw)]);

                    $bar->advance();
                    $encrypted++;
                }
            });
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Encrypted: {$encrypted}, already encrypted (skipped): {$skipped}.");

        return self::SUCCESS;
    }

    private function looksLikeEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, strict: true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) && isset($payload['iv'], $payload['value']);
    }
}
