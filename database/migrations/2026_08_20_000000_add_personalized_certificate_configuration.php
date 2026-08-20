<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const APPLICATION_CERTIFICATE_UNIQUE = 'certificates_research_application_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable('application_certificate_recipients')) {
            Schema::create('application_certificate_recipients', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('research_application_id');
                $table->string('recipient_name', 180);
                $table->string('normalized_name', 180);
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->timestamps();

                $table->unique(
                    ['research_application_id', 'normalized_name'],
                    'application_certificate_recipient_name_unique',
                );
                $table->index(
                    ['research_application_id', 'sort_order'],
                    'application_certificate_recipient_order_index',
                );
                $table->foreign('research_application_id', 'acr_application_fk')
                    ->references('id')
                    ->on('research_applications')
                    ->cascadeOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'certificate_valid_until')) {
                $table->date('certificate_valid_until')->nullable()->after('certificate_signatory_name');
            }
            if (! Schema::hasColumn('users', 'certificate_qr_path')) {
                $table->string('certificate_qr_path')->nullable()->after('certificate_signature_uploaded_at');
                $table->char('certificate_qr_sha256', 64)->nullable()->after('certificate_qr_path');
                $table->unsignedInteger('certificate_qr_width')->nullable()->after('certificate_qr_sha256');
                $table->unsignedInteger('certificate_qr_height')->nullable()->after('certificate_qr_width');
                $table->timestamp('certificate_qr_uploaded_at')->nullable()->after('certificate_qr_height');
            }
        });

        Schema::table('certificates', function (Blueprint $table): void {
            if (! Schema::hasColumn('certificates', 'application_certificate_recipient_id')) {
                $table->foreignId('application_certificate_recipient_id')
                    ->nullable()
                    ->after('research_application_id');
                $table->foreign('application_certificate_recipient_id', 'certificates_recipient_fk')
                    ->references('id')
                    ->on('application_certificate_recipients')
                    ->restrictOnDelete();
                $table->string('recipient_name', 180)->nullable()->after('applicant_user_id');
            }
        });

        Schema::table('certificate_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('certificate_versions', 'signatory_name_snapshot')) {
                $table->string('signatory_name_snapshot', 120)->nullable()->after('valid_until');
                $table->char('signature_sha256_snapshot', 64)->nullable()->after('signatory_name_snapshot');
                $table->string('qr_code_path')->nullable()->after('signature_sha256_snapshot');
                $table->char('qr_code_sha256', 64)->nullable()->after('qr_code_path');
                $table->unsignedInteger('qr_code_width')->nullable()->after('qr_code_sha256');
                $table->unsignedInteger('qr_code_height')->nullable()->after('qr_code_width');
            }
        });

        $this->backfillRecipients();

        Schema::table('certificates', function (Blueprint $table): void {
            if (Schema::hasIndex('certificates', self::APPLICATION_CERTIFICATE_UNIQUE)) {
                $table->dropUnique(self::APPLICATION_CERTIFICATE_UNIQUE);
            }
            if (! Schema::hasIndex('certificates', 'certificates_research_application_index')) {
                $table->index('research_application_id', 'certificates_research_application_index');
            }
            if (! Schema::hasIndex('certificates', 'certificates_recipient_unique')) {
                $table->unique('application_certificate_recipient_id', 'certificates_recipient_unique');
            }
        });
    }

    public function down(): void
    {
        if (DB::table('certificates')
            ->select('research_application_id')
            ->groupBy('research_application_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException(
                'Personalized certificates exist; the one-certificate-per-application constraint cannot be restored safely.',
            );
        }

        Schema::table('certificates', function (Blueprint $table): void {
            if (Schema::hasIndex('certificates', 'certificates_recipient_unique')) {
                $table->dropUnique('certificates_recipient_unique');
            }
            if (Schema::hasIndex('certificates', 'certificates_research_application_index')) {
                $table->dropIndex('certificates_research_application_index');
            }
            if (! Schema::hasIndex('certificates', self::APPLICATION_CERTIFICATE_UNIQUE)) {
                $table->unique('research_application_id', self::APPLICATION_CERTIFICATE_UNIQUE);
            }
            if (Schema::hasColumn('certificates', 'application_certificate_recipient_id')) {
                $table->dropForeign('certificates_recipient_fk');
                $table->dropColumn('application_certificate_recipient_id');
                $table->dropColumn('recipient_name');
            }
        });

        Schema::table('certificate_versions', function (Blueprint $table): void {
            $columns = collect([
                'signatory_name_snapshot',
                'signature_sha256_snapshot',
                'qr_code_path',
                'qr_code_sha256',
                'qr_code_width',
                'qr_code_height',
            ])->filter(fn (string $column): bool => Schema::hasColumn('certificate_versions', $column))->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $columns = collect([
                'certificate_valid_until',
                'certificate_qr_path',
                'certificate_qr_sha256',
                'certificate_qr_width',
                'certificate_qr_height',
                'certificate_qr_uploaded_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('users', $column))->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('application_certificate_recipients');
    }

    private function backfillRecipients(): void
    {
        DB::table('research_applications')
            ->leftJoin('users', 'users.id', '=', 'research_applications.applicant_user_id')
            ->select([
                'research_applications.id as application_id',
                'research_applications.applicant_user_id',
                'users.name as applicant_name',
            ])
            ->orderBy('research_applications.id')
            ->chunkById(100, function ($applications): void {
                foreach ($applications as $application) {
                    $name = Str::squish((string) ($application->applicant_name ?: 'Applicant'));
                    $normalized = mb_strtolower($name);
                    DB::table('application_certificate_recipients')->updateOrInsert([
                        'research_application_id' => $application->application_id,
                        'normalized_name' => $normalized,
                    ], [
                        'recipient_name' => $name,
                        'sort_order' => 1,
                        'updated_at' => now(),
                    ]);
                    $recipientId = DB::table('application_certificate_recipients')
                        ->where('research_application_id', $application->application_id)
                        ->where('normalized_name', $normalized)
                        ->value('id');

                    DB::table('certificates')
                        ->where('research_application_id', $application->application_id)
                        ->whereNull('application_certificate_recipient_id')
                        ->update([
                            'application_certificate_recipient_id' => $recipientId,
                            'recipient_name' => $name,
                        ]);
                }
            }, 'research_applications.id', 'application_id');
    }
};
