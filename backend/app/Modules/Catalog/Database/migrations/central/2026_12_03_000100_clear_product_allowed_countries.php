<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Decision #115: every central product is sold in every country. The Livewire-era
 * country availability (`products.allowed_country_ids`) is no longer read or written;
 * existing restrictions are cleared (NULL = everywhere, the old "all" value) so no
 * product stays hidden in a country. The nullable column is kept, unused, so older
 * code paths deployed side by side and old backups still load; nothing else changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->whereNotNull('allowed_country_ids')->update(['allowed_country_ids' => null]);
    }

    public function down(): void
    {
        // Cleared restrictions cannot be restored (they were removed on purpose).
    }
};
