<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Decision #118: "last edited by / at" on the e-mail template editor (the editor's name
| is kept as text so it survives the member being removed).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->string('updated_by_name', 191)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->dropColumn('updated_by_name');
        });
    }
};
