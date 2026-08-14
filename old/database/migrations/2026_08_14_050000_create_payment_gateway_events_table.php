<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'payment_gateway_events',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique(
                    'pge_uuid_uq'
                );

                $table->string('gateway', 100)
                    ->index();

                $table->string('event_key', 190);

                $table->foreignId(
                    'payment_transaction_id'
                )->nullable();

                $table->string(
                    'event_type',
                    20
                )->index();

                $table->string(
                    'authority',
                    255
                )->nullable();

                $table->char(
                    'payload_hash',
                    64
                );

                $table->json(
                    'request_payload'
                )->nullable();

                $table->boolean(
                    'signature_valid'
                )->nullable();

                $table->string(
                    'source_ip',
                    45
                )->nullable();

                $table->string(
                    'user_agent',
                    500
                )->nullable();

                $table->string(
                    'status',
                    20
                )
                    ->default('received')
                    ->index();

                $table->unsignedInteger(
                    'attempts'
                )->default(0);

                $table->text(
                    'error_message'
                )->nullable();

                $table->timestamp(
                    'received_at'
                );

                $table->timestamp(
                    'processed_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    ['gateway', 'event_key'],
                    'pge_gateway_event_uq'
                );

                $table->index(
                    ['gateway', 'authority'],
                    'pge_gateway_authority_idx'
                );

                $table->foreign(
                    'payment_transaction_id',
                    'pge_payment_tx_fk'
                )
                    ->references('id')
                    ->on('payment_transactions')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payment_gateway_events'
        );
    }
};
