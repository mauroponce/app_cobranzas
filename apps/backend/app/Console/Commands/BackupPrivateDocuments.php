<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupPrivateDocuments extends Command
{
    protected $signature = 'pilot:backup-documents {destination}';

    protected $description = 'Copy private R2 documents to a mounted pilot backup directory';

    public function handle(): int
    {
        $destination = rtrim((string) $this->argument('destination'), '/');
        $destinationSegments = explode('/', trim($destination, '/'));
        if (! str_starts_with($destination.'/', '/backups/')
            || str_contains($destination, '\\')
            || in_array('.', $destinationSegments, true)
            || in_array('..', $destinationSegments, true)) {
            $this->error('El destino debe estar dentro del volumen /backups.');

            return self::FAILURE;
        }

        if (! is_dir($destination) && ! mkdir($destination, 0700, true) && ! is_dir($destination)) {
            throw new RuntimeException('No se pudo crear el destino del respaldo.');
        }

        $disk = Storage::disk('r2');
        $manifest = [];
        foreach ($disk->allFiles() as $path) {
            $segments = preg_split('~/+~', str_replace('\\', '/', $path));
            if (str_starts_with($path, '/') || $segments === false || in_array('..', $segments, true)) {
                throw new RuntimeException('El almacenamiento devolvió una ruta de documento insegura.');
            }
            $target = $destination.'/files/'.$path;
            $directory = dirname($target);
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('No se pudo crear un directorio del respaldo.');
            }
            $source = $disk->readStream($path);
            $output = fopen($target, 'wb');
            if ($source === false || $output === false) {
                throw new RuntimeException('No se pudo abrir un documento para respaldarlo.');
            }
            stream_copy_to_stream($source, $output);
            fclose($source);
            fclose($output);
            chmod($target, 0600);
            $manifest[] = [
                'path' => $path,
                'bytes' => filesize($target),
                'sha256' => hash_file('sha256', $target),
            ];
        }

        file_put_contents(
            $destination.'/manifest.json',
            json_encode(['generated_at' => now()->toIso8601String(), 'objects' => $manifest], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
        chmod($destination.'/manifest.json', 0600);
        $this->info(count($manifest).' documento(s) respaldado(s).');

        return self::SUCCESS;
    }
}
