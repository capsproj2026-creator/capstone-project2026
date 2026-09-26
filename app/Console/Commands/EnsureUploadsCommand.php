<?php

namespace App\Console\Commands;

use App\Support\UploadsRoot;
use Illuminate\Console\Command;

/**
 * Create the external (or default) upload folders and refresh public/storage link.
 */
class EnsureUploadsCommand extends Command
{
    protected $signature = 'iscvms:ensure-uploads';

    protected $description = 'Create ISCVMS upload directories (external root if configured) and refresh storage:link';

    public function handle(): int
    {
        UploadsRoot::ensureDirectories();

        $root = UploadsRoot::path();
        $this->info('Uploads root: '.$root);
        $this->line('  private: '.UploadsRoot::privatePath());
        $this->line('  public:  '.UploadsRoot::publicPath());
        $this->line('  external: '.(UploadsRoot::isExternal() ? 'yes' : 'no (using storage/app)'));

        $link = public_path('storage');
        $target = UploadsRoot::publicPath();

        if (! $this->removePublicStorageLink($link)) {
            $this->error('public/storage still exists and could not be removed safely.');
            $this->line('If it is an old junction, run in an elevated PowerShell:');
            $this->line('  cmd /c rmdir "'.str_replace('"', '', $link).'"');
            $this->line('Then re-run: php artisan iscvms:ensure-uploads');

            return self::FAILURE;
        }

        if (! $this->createPublicStorageLink($link, $target)) {
            $this->warn('Could not create public/storage link.');
            $this->line('Try as Administrator: php artisan storage:link');

            return self::FAILURE;
        }

        $this->info('Linked public/storage → '.$target);
        $this->info('Upload directories ready. New registrations/evidence will use this root.');

        return self::SUCCESS;
    }

    /**
     * Remove public/storage symlink or Windows junction (not a filled real folder).
     */
    private function removePublicStorageLink(string $link): bool
    {
        if (! file_exists($link) && ! is_link($link)) {
            return true;
        }

        // Windows junctions: is_dir=true and is_link=false — must use rmdir.
        if (PHP_OS_FAMILY === 'Windows') {
            $path = str_replace('"', '', $link);
            exec('cmd /c rmdir "'.$path.'" 2>nul', $out, $code);
            if (! file_exists($link)) {
                return true;
            }
            // Empty real directory leftover
            if (is_dir($link) && ! is_link($link)) {
                $entries = @scandir($link) ?: [];
                if (count($entries) <= 2) {
                    @rmdir($link);
                }
            }

            return ! file_exists($link);
        }

        if (is_link($link)) {
            return @unlink($link);
        }

        if (is_dir($link)) {
            $entries = @scandir($link) ?: [];
            if (count($entries) <= 2) {
                return @rmdir($link);
            }
        }

        return ! file_exists($link);
    }

    private function createPublicStorageLink(string $link, string $target): bool
    {
        if (! is_dir($target)) {
            @mkdir($target, 0775, true);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $linkPath = str_replace('"', '', $link);
            $targetPath = str_replace('"', '', $target);
            exec('cmd /c mklink /J "'.$linkPath.'" "'.$targetPath.'"', $out, $code);
            if (file_exists($link)) {
                return true;
            }
        }

        $this->call('storage:link');

        return file_exists($link);
    }
}
