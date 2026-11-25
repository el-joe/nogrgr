<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Settlements list / "mark settlement paid" (ADM-050a / 051, decision #72): a settlement
| reference (ST-…, shared by the orders one manual payment covers), the method of a manual
| payment (bank transfer, cash, payout offset…), its transaction reference, note, proof
| file (private `central` disk) and who recorded it.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_settlements', function (Blueprint $table): void {
            $table->string('reference', 20)->nullable()->after('id')->index();
            $table->string('method', 24)->nullable()->after('gateway');
            $table->string('transaction_reference', 120)->nullable()->after('method');
            $table->text('note')->nullable()->after('transaction_reference');
            $table->string('attachment_path', 500)->nullable()->after('note');
            $table->string('attachment_name', 255)->nullable()->after('attachment_path');
            $table->string('recorded_by_name', 120)->nullable()->after('paid_by_id');
        });

        DB::table('order_settlements')->whereNull('reference')->orderBy('id')->select('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('order_settlements')->where('id', $row->id)->update(['reference' => 'ST-'.(3000 + (int) $row->id)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_settlements', function (Blueprint $table): void {
            $table->dropIndex(['reference']);
            $table->dropColumn(['reference', 'method', 'transaction_reference', 'note', 'attachment_path', 'attachment_name', 'recorded_by_name']);
        });
    }
};
