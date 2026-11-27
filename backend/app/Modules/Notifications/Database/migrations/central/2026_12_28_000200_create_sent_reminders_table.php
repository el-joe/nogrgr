<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Idempotency log of scheduled reminders (central, decision #124): one row per reminder
| kind + subject + period (e.g. renewal_reminder / subscription:12 / 2026-12-25:7d,
| settlement_due / tenant:4 / 2026-W48). The unique key makes a re-run or an overlapping
| scheduler send nothing twice.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_reminders', function (Blueprint $table): void {
            $table->id();
            $table->string('reminder', 48);
            $table->string('subject', 64);
            $table->string('period', 32);
            $table->timestamp('sent_at');
            $table->unique(['reminder', 'subject', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_reminders');
    }
};
