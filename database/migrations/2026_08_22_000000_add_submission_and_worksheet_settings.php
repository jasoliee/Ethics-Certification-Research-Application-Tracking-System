<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_documents', function (Blueprint $table): void {
            $table->timestamp('formally_submitted_at')->nullable()->after('uploaded_at')->index();
        });

        // Existing records predate the explicit boundary marker. Preserve every file
        // attached to an already-submitted application as historical evidence.
        DB::table('application_documents')
            ->whereIn('research_application_id', DB::table('research_applications')->whereNotNull('submitted_at')->select('id'))
            ->update(['formally_submitted_at' => DB::raw('uploaded_at')]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('worksheet_signatory_name', 120)->nullable()->after('certificate_qr_uploaded_at');
            $table->string('worksheet_signature_path')->nullable()->after('worksheet_signatory_name');
            $table->char('worksheet_signature_sha256', 64)->nullable()->after('worksheet_signature_path');
            $table->unsignedInteger('worksheet_signature_width')->nullable()->after('worksheet_signature_sha256');
            $table->unsignedInteger('worksheet_signature_height')->nullable()->after('worksheet_signature_width');
            $table->timestamp('worksheet_signature_uploaded_at')->nullable()->after('worksheet_signature_height');
        });

        Schema::create('workflow_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('research_application_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('workflow', 50);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['user_id', 'research_application_id', 'workflow'], 'workflow_drafts_owner_unique');
            $table->index(['research_application_id', 'workflow'], 'workflow_drafts_application_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_drafts');

        Schema::table('application_documents', function (Blueprint $table): void {
            $table->dropIndex(['formally_submitted_at']);
            $table->dropColumn('formally_submitted_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'worksheet_signatory_name',
                'worksheet_signature_path',
                'worksheet_signature_sha256',
                'worksheet_signature_width',
                'worksheet_signature_height',
                'worksheet_signature_uploaded_at',
            ]);
        });
    }
};
