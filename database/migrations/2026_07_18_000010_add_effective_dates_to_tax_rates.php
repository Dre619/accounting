<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_UNIQUE = 'tax_rates_company_id_code_unique';
    private const NEW_UNIQUE = 'tax_rates_company_code_from_unique';

    public function up(): void
    {
        // 'turnover' joins vat/withholding so TOT rates live alongside other taxes.
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->enum('type', ['vat', 'withholding', 'turnover', 'other'])->default('vat')->change();
        });

        // Null effective_from = "since forever"; null effective_to = "still current".
        if (! Schema::hasColumn('tax_rates', 'effective_from')) {
            Schema::table('tax_rates', function (Blueprint $table) {
                $table->date('effective_from')->nullable()->after('rate');
            });
        }

        if (! Schema::hasColumn('tax_rates', 'effective_to')) {
            Schema::table('tax_rates', function (Blueprint $table) {
                $table->date('effective_to')->nullable()->after('effective_from');
            });
        }

        // A code may now repeat across rate versions, distinguished by start date.
        //
        // Order is critical on MySQL: the company_id foreign key needs an index
        // whose leftmost column is company_id. The old unique was serving that
        // purpose, so the replacement must exist BEFORE the old one is dropped —
        // otherwise MySQL raises errno 1553.
        if (! $this->hasIndex(self::NEW_UNIQUE)) {
            Schema::table('tax_rates', function (Blueprint $table) {
                $table->unique(['company_id', 'code', 'effective_from'], self::NEW_UNIQUE);
            });
        }

        if ($this->hasIndex(self::OLD_UNIQUE)) {
            Schema::table('tax_rates', function (Blueprint $table) {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        // Reverse order, same reasoning: restore the old index before dropping the new.
        if (! $this->hasIndex(self::OLD_UNIQUE)) {
            Schema::table('tax_rates', function (Blueprint $table) {
                $table->unique(['company_id', 'code'], self::OLD_UNIQUE);
            });
        }

        if ($this->hasIndex(self::NEW_UNIQUE)) {
            Schema::table('tax_rates', function (Blueprint $table) {
                $table->dropUnique(self::NEW_UNIQUE);
            });
        }

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropColumn(['effective_from', 'effective_to']);
            $table->enum('type', ['vat', 'withholding', 'other'])->default('vat')->change();
        });
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('tax_rates'))
            ->pluck('name')
            ->contains($name);
    }
};
