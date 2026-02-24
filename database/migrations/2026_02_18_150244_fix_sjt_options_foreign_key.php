<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sjt_options', function (Blueprint $table) {
            // 1. Hapus Foreign Key lama yang 'kaku' (restrict)
            // Pastikan nama constraint sesuai dengan yang ada di file migration awal Anda
            $table->dropForeign('fk_sjt_options_competency');

            // 2. Buat Foreign Key baru dengan fitur 'CASCADE' (ikut berubah)
            $table->foreign('competency_target', 'fk_sjt_options_competency')
                  ->references('competency_code')
                  ->on('competency_descriptions')
                  ->onUpdate('cascade') // <--- INI KUNCINYA
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sjt_options', function (Blueprint $table) {
            // Kembalikan ke settingan awal jika di-rollback
            $table->dropForeign('fk_sjt_options_competency');

            $table->foreign('competency_target', 'fk_sjt_options_competency')
                  ->references('competency_code')
                  ->on('competency_descriptions')
                  ->onUpdate('restrict')
                  ->onDelete('restrict');
        });
    }
};
