<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-store order number counter (decision #138). It starts after the highest ORD- number
 * already issued, so existing numbers stay valid and are never handed out again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_sequences', function (Blueprint $table): void {
            $table->string('name', 32)->primary();
            $table->unsignedBigInteger('last_number');
        });

        $last = 10000;

        foreach (DB::table('orders')->where('number', 'like', 'ORD-%')->select('number')->cursor() as $row) {
            if (preg_match('/^ORD-(\d+)$/', (string) $row->number, $match) === 1) {
                $last = max($last, (int) $match[1]);
            }
        }

        DB::table('order_sequences')->insert(['name' => 'order', 'last_number' => $last]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sequences');
    }
};
