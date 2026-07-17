<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            // Application fee in Zambian Kwacha, set by each institution
            // from their Settings page. Different institutions charge
            // different amounts.
            $table->decimal('application_fee', 8, 2)->default(150.00);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // The fee split — recorded explicitly on every transaction:
            // amount = platform_fee + institution_amount.
            // platform_fee = 5% of amount (ZamAdmit's commission);
            // institution_amount is forwarded to the institution.
            $table->decimal('amount', 8, 2);
            $table->decimal('platform_fee', 8, 2);
            $table->decimal('institution_amount', 8, 2);

            $table->enum('provider', ['mtn', 'airtel', 'zamtel']);
            $table->string('phone', 20);

            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('reference', 20)->unique();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['application_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('application_fee');
        });
    }
};