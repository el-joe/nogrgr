<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Test fixture for ProvisioningTest (QA-P2-006): sorts between real tenant migrations,
| creates two tables and then fails while `testing.flaky_tenant_migration` is true —
| the "DDL already committed, migration not recorded" state of a mid-run crash.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flaky_provisioning_a', static function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('flaky_provisioning_b', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('a_id')->constrained('flaky_provisioning_a');
        });

        if (config('testing.flaky_tenant_migration') === true) {
            throw new RuntimeException('Simulated crash in the middle of a tenant migration');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flaky_provisioning_b');
        Schema::dropIfExists('flaky_provisioning_a');
    }
};
