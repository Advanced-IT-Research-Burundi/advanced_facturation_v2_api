<?php

namespace App\Console\Commands;

use App\Models\Depense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateJustificationFilesToDatabase extends Command
{
    protected $signature = 'depenses:migrate-files';

    protected $description = 'Migrate existing justification files from disk storage into the database';

    public function handle(): int
    {
        $depenses = Depense::whereNotNull('justification_file')
            ->whereNull('justification_data')
            ->get();

        if ($depenses->isEmpty()) {
            $this->info('Aucun fichier à migrer.');

            return self::SUCCESS;
        }

        $this->info("Fichiers à migrer : {$depenses->count()}");

        $migrated = 0;
        $failed = 0;

        foreach ($depenses as $depense) {
            $path = $depense->justification_file;

            if (Storage::disk('public')->exists($path)) {
                $content = Storage::disk('public')->get($path);
                $mime = Storage::disk('public')->mimeType($path);

                $depense->update([
                    'justification_data' => base64_encode($content),
                    'justification_mime' => $mime,
                ]);

                $migrated++;
                $this->line("  ✓ Depense #{$depense->id} — {$path}");
            } else {
                $failed++;
                $this->warn("  ✗ Depense #{$depense->id} — fichier introuvable : {$path}");
            }
        }

        $this->newLine();
        $this->info("Terminé : {$migrated} migré(s), {$failed} échoué(s).");

        return self::SUCCESS;
    }
}
