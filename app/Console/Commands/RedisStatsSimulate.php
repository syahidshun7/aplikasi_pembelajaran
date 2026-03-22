<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RedisStatsSimulate extends Command
{
    protected $signature = 'redis:simulate-stats {loops=5}';
    protected $description = 'Simulasi hits/misses Redis dengan loop akses key test beberapa kali';

    public function handle()
    {
        $redis = Redis::connection();

        try {
            // Cek Redis aktif
            $redis->ping();
            $this->info("✅ Redis aktif");

            // Buat beberapa key test
            $testKeys = ['redis_stats:simkey1','redis_stats:simkey2','redis_stats:simkey3'];
            foreach ($testKeys as $key) {
                $redis->set($key, 'ok', 'EX', 300); // TTL 5 menit
            }

            $loops = (int)$this->argument('loops');

            // Akses key test beberapa kali untuk mencatat hits/misses
            for ($i = 0; $i < $loops; $i++) {
                $redis->get('redis_stats:missing_key_'.$i);
                foreach ($testKeys as $key) {
                    $redis->get($key); // akses pertama -> miss
                    $redis->get($key); // akses kedua -> hit
                }
            }

            // Ambil statistik Redis
            $info = $redis->info('stats');
            $stats = $info['Stats'] ?? $info['stats'] ?? $info;

            $this->info("\n📊 Statistik Redis setelah simulasi:");
            $this->line("Hits   : " . ($stats['keyspace_hits'] ?? 0));
            $this->line("Misses : " . ($stats['keyspace_misses'] ?? 0));

        } catch (\Exception $e) {
            $this->error("❌ Redis tidak aktif: " . $e->getMessage());
        }
    }
}
