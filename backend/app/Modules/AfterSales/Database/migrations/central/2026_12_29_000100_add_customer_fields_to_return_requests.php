<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Return form fields of the old project (SF-ECO-030): how the item comes back, extra
| notes and, for an exchange, the replacement option the customer chose.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table): void {
            $table->string('return_method', 24)->nullable()->after('description');
            $table->text('customer_note')->nullable()->after('return_method');
            $table->unsignedBigInteger('replacement_variant_id')->nullable()->after('customer_note');
            $table->string('replacement_label', 255)->nullable()->after('replacement_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table): void {
            $table->dropColumn(['return_method', 'customer_note', 'replacement_variant_id', 'replacement_label']);
        });
    }
};
