<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Encryption\Encrypter;

class ProductionReadiness
{
    public function inspect(): array
    {
        $checks = [];
        $this->add($checks, 'Production environment', app()->environment('production'), 'APP_ENV is '.app()->environment().'.');
        $this->add($checks, 'Debug disabled', ! (bool) config('app.debug'), config('app.debug') ? 'APP_DEBUG is enabled.' : 'Debug output is disabled.');

        $url = (string) config('app.url');
        $this->add($checks, 'HTTPS application URL', strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https', $url ?: 'APP_URL is not set.');

        $database = config('database.default');
        $databaseReady = in_array($database, ['mysql', 'mariadb'], true);
        $databaseDetail = 'Configured connection: '.$database.'.';
        try {
            DB::connection()->select('select 1');
            $databaseDetail .= ' Database connection succeeded.';
        } catch (\Throwable $exception) {
            $databaseReady = false;
            $databaseDetail .= ' Database connection failed: '.$exception::class.'.';
        }
        $this->add($checks, 'Production database', $databaseReady, $databaseDetail);

        $mailer = (string) config('mail.default');
        $mailReady = in_array($mailer, ['smtp', 'ses', 'postmark', 'resend'], true);
        if ($mailer === 'smtp') {
            $mailReady = $mailReady
                && filled(config('mail.mailers.smtp.host'))
                && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false;
        } elseif ($mailer === 'ses') {
            $mailReady = $mailReady && filled(config('services.ses.key')) && filled(config('services.ses.region'));
        } elseif ($mailer === 'postmark') {
            $mailReady = $mailReady && filled(config('services.postmark.token'));
        } elseif ($mailer === 'resend') {
            $mailReady = $mailReady && filled(config('services.resend.key'));
        }
        $this->add($checks, 'Email delivery', $mailReady, 'Configured mailer: '.$mailer.'.');

        $queue = (string) config('queue.default');
        $this->add($checks, 'Asynchronous queue', ! in_array($queue, ['sync', 'null'], true), 'Configured queue: '.$queue.'. Confirm a worker is supervised on the host.');

        $key = (string) config('app.key');
        $parsedKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $keyReady = is_string($parsedKey) && Encrypter::supported($parsedKey, config('app.cipher'));
        $this->add($checks, 'Application encryption key', $keyReady, $keyReady ? 'A valid key is configured; keep it private and stable.' : 'APP_KEY is missing or invalid for the configured cipher.');

        $root = realpath(config('filesystems.disks.local.root')) ?: config('filesystems.disks.local.root');
        $publicRoot = realpath(public_path()) ?: public_path();
        $storageWritable = is_dir($root) ? is_writable($root) : is_writable(dirname($root));
        $privateStorage = $storageWritable && ! str_starts_with(strtolower($root), strtolower($publicRoot));
        $this->add($checks, 'Private room-photo storage', $privateStorage, $privateStorage ? 'The local private disk is writable and outside the public web root.' : 'The local private disk must be persistent, writable, and outside the public web root.');

        return ['ready' => collect($checks)->every(fn ($check) => $check['ok']), 'checks' => $checks];
    }

    private function add(array &$checks, string $name, bool $ok, string $detail): void
    {
        $checks[] = compact('name', 'ok', 'detail');
    }
}
