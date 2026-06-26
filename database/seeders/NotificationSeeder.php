<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $passenger = User::where('role', 'passenger')->first();
        if ($passenger) {
            Notification::create([
                'user_id' => $passenger->id,
                'title' => 'Top Up Berhasil',
                'body' => 'Saldo BusFlow Wallet kamu telah berhasil ditambah sebesar Rp 150.000.',
                'is_read' => true,
                'created_at' => now()->subDays(2),
            ]);

            Notification::create([
                'user_id' => $passenger->id,
                'title' => 'Perjalanan Selesai',
                'body' => 'Terima kasih telah menggunakan layanan BusFlow di rute K1. Rp 3.500 telah dipotong dari saldo Anda.',
                'is_read' => false,
                'created_at' => now()->subHours(3)->addMinutes(10),
            ]);
            
            Notification::create([
                'user_id' => $passenger->id,
                'title' => 'Peringatan Rute Dialihkan',
                'body' => 'Terdapat pengalihan rute pada Koridor 1 (Blok M - Kota) mulai pukul 09:00 WIB akibat perbaikan jalan raya. Harap maklum.',
                'is_read' => false,
                'created_at' => now()->subMinutes(10),
            ]);
        }
    }
}
