<?php

namespace APP\plugins\generic\acceptanceLetter\classes\migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AcceptanceLetterSchemaMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table for customized acceptance letter templates per journal context
        if (!Schema::hasTable('acceptance_templates')) {
            Schema::create('acceptance_templates', function (Blueprint $table) {
                $table->bigIncrements('template_id');
                $table->bigInteger('context_id');
                $table->string('name', 255);
                $table->string('page_size', 20)->default('A4'); // A4, Letter
                $table->string('orientation', 20)->default('portrait'); // portrait, landscape
                $table->string('locale', 14)->default('en');
                $table->longText('header_html')->nullable();
                $table->longText('body_html');
                $table->longText('footer_html')->nullable();
                $table->string('logo_path', 255)->nullable();
                $table->string('signature_path', 255)->nullable();
                $table->string('stamp_path', 255)->nullable();
                $table->string('background_path', 255)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['context_id'], 'acceptance_templates_context_id');
            });
        }

        // Table for tracking issued acceptance letters with validation tokens
        if (!Schema::hasTable('acceptance_issued_letters')) {
            Schema::create('acceptance_issued_letters', function (Blueprint $table) {
                $table->bigIncrements('issue_id');
                $table->bigInteger('context_id');
                $table->bigInteger('submission_id');
                $table->bigInteger('template_id')->nullable();
                $table->string('certificate_number', 64)->unique();
                $table->string('verification_token', 64)->unique(); // SHA-256 hash or UUID
                $table->bigInteger('issued_by_user_id');
                $table->dateTime('issued_at');
                $table->string('file_path', 255)->nullable(); // stored PDF copy
                $table->timestamps();

                $table->index(['context_id', 'submission_id'], 'acceptance_issued_context_sub');
                $table->index(['verification_token'], 'acceptance_issued_token');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acceptance_issued_letters');
        Schema::dropIfExists('acceptance_templates');
    }
}
