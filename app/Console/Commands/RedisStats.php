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
            $testKeys = ['testkey1', 'testkey2', 'testkey3'];
            foreach ($testKeys as $key) {
                $redis->set($key, 'ok', 'EX', 60); // TTL 60 detik
            }

            // Akses key beberapa kali supaya hits/misses tercatat
            foreach ($testKeys as $key) {
                $redis->get($key); // pertama -> miss
                $redis->get($key); // kedua -> hit
            }

            // Ambil statistik Redis
            $stats = $redis->info('stats');

            $this->info("\n📊 Statistik Redis saat ini:");
            $this->line("Hits   : " . ($stats['keyspace_hits'] ?? 0));
            $this->line("Misses : " . ($stats['keyspace_misses'] ?? 0));

        } catch (\Exception $e) {
            $this->error("❌ Redis tidak aktif: " . $e->getMessage());
        }
    }
}