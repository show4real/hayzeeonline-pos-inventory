<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCreditorsPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * The hasTable() guard is not boilerplate. This table exists in the
     * database but this migration is not recorded as having run — it was
     * created out of band — so an unguarded Schema::create() fails with
     * "1050 Table 'creditors_payments' already exists".
     *
     * That matters because docker/deploy.sh runs `migrate --force` on every
     * release and rolls the whole deploy back if it fails. Without this guard
     * no deploy could ever succeed, and the failure would look like a
     * deployment problem rather than a schema one.
     *
     * The guard keeps the migration correct for a fresh database as well,
     * which is why it is fixed here instead of by inserting a row into the
     * migrations table on the server.
     */
    public function up()
    {
        if (Schema::hasTable('creditors_payments')) {
            return;
        }

        Schema::create('creditors_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('creditor_id');
            $table->bigInteger('amount');
            $table->bigInteger('amount_paid');
            $table->bigInteger('balance');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('creditors_payments');
    }
}
