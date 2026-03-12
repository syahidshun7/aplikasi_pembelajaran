<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RedisStats extends Command
{
    // Nama command yang akan dipanggil
    protected $signature = 'redis:stats';

    // Deskripsi command
    protected $description = 'Cek Redis aktif, tambahkan key test, dan tampilkan hits/misses';

    public function handle()
    {
        $redis = Redis::connection();

        try {
            // Cek apakah Redis aktif
            $redis->ping();
            $this->info("✅ Redis aktif");

            // Buat beberapa key test
            $testKeys = ['redis_stats:testkey1', 'redis_stats:testkey2', 'redis_stats:testkey3'];
            foreach ($testKeys as $key) {
                $redis->set($key, 'ok', 'EX', 60); // TTL 60 detik
            }

            // Paksa 1 miss dan beberapa hit supaya stats bergerak (keyspace_hits / keyspace_misses)
            $redis->get('redis_stats:missing_key');
            foreach ($testKeys as $key) {
                $redis->get($key);
                $redis->get($key);
            }

            // Ambil statistik Redis
            // Predis mengembalikan array per-section: ['Stats' => ['keyspace_hits' => ...]]
            // PhpRedis biasanya flat: ['keyspace_hits' => ...]
            $info = $redis->info('stats');
            $stats = $info['Stats'] ?? $info['stats'] ?? $info;

            $this->info("\n📊 Statistik Redis saat ini:");
            $this->line("Hits   : " . ($stats['keyspace_hits'] ?? 0));
            $this->line("Misses : " . ($stats['keyspace_misses'] ?? 0));

        } catch (\Exception $e) {
            $this->error("❌ Redis tidak aktif: " . $e->getMessage());
        }
    }
}
