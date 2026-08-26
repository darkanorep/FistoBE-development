<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the indexes recommended during the review of:
 *   - Cheque::addCheque()
 *   - CheckController::multipleCheque()
 *   - CheckController::chequeIndex()
 *
 * Pure infrastructure change — does not alter any query logic or results.
 * Each index is guarded so the migration is safe to run even if a given
 * column doesn't exist on your current schema (skips it rather than
 * failing the whole migration), and safe to re-run/rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- cheques table --------------------------------------------
        Schema::table('cheques', function (Blueprint $table) {
            // Hot path: Cheque::where('transaction_id', ...)->whereNull('reason_id')->delete()
            // runs on every addCheque() call.
            if ($this->columnsExist('cheques', ['transaction_id', 'reason_id'])
                && !$this->indexExists('cheques', 'cheques_transaction_id_reason_id_index')) {
                $table->index(['transaction_id', 'reason_id'], 'cheques_transaction_id_reason_id_index');
            }

            // FK lookups used throughout addCheque() / multipleCheque().
            if ($this->columnsExist('cheques', ['bank_id'])
                && !$this->indexExists('cheques', 'cheques_bank_id_index')) {
                $table->index('bank_id', 'cheques_bank_id_index');
            }

            // Correlated subquery target for whereHas("cheques.cheques", ...)
            // date-range filter in chequeIndex().
            if ($this->columnsExist('cheques', ['cheque_date'])
                && !$this->indexExists('cheques', 'cheques_cheque_date_index')) {
                $table->index('cheque_date', 'cheques_cheque_date_index');
            }

            // Composite covering the correlated whereHas('cheques', ...) status
            // lookups from chequeIndex() (e.g. status = 'cheque-cheque').
            if ($this->columnsExist('cheques', ['transaction_id', 'status'])
                && !$this->indexExists('cheques', 'cheques_transaction_id_status_index')) {
                $table->index(['transaction_id', 'status'], 'cheques_transaction_id_status_index');
            }
        });

        // --- transactions table -----------------------------------------
        Schema::table('transactions', function (Blueprint $table) {
            // Every branch of chequeIndex() filters on status; combined with
            // the final ->latest('updated_at') this composite covers both
            // the WHERE and the ORDER BY for the most common access pattern.
            if ($this->columnsExist('transactions', ['status', 'updated_at'])
                && !$this->indexExists('transactions', 'transactions_status_updated_at_index')) {
                $table->index(['status', 'updated_at'], 'transactions_status_updated_at_index');
            }

            if ($this->columnsExist('transactions', ['is_confidential'])
                && !$this->indexExists('transactions', 'transactions_is_confidential_index')) {
                $table->index('is_confidential', 'transactions_is_confidential_index');
            }

            if ($this->columnsExist('transactions', ['is_mc'])
                && !$this->indexExists('transactions', 'transactions_is_mc_index')) {
                $table->index('is_mc', 'transactions_is_mc_index');
            }

            if ($this->columnsExist('transactions', ['supplier_id'])
                && !$this->indexExists('transactions', 'transactions_supplier_id_index')) {
                $table->index('supplier_id', 'transactions_supplier_id_index');
            }

            if ($this->columnsExist('transactions', ['company_id'])
                && !$this->indexExists('transactions', 'transactions_company_id_index')) {
                $table->index('company_id', 'transactions_company_id_index');
            }

            if ($this->columnsExist('transactions', ['document_id'])
                && !$this->indexExists('transactions', 'transactions_document_id_index')) {
                $table->index('document_id', 'transactions_document_id_index');
            }

            if ($this->columnsExist('transactions', ['assigned_id'])
                && !$this->indexExists('transactions', 'transactions_assigned_id_index')) {
                $table->index('assigned_id', 'transactions_assigned_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'cheques', 'cheques_transaction_id_reason_id_index');
            $this->dropIndexIfExists($table, 'cheques', 'cheques_bank_id_index');
            $this->dropIndexIfExists($table, 'cheques', 'cheques_cheque_date_index');
            $this->dropIndexIfExists($table, 'cheques', 'cheques_transaction_id_status_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'transactions', 'transactions_status_updated_at_index');
            $this->dropIndexIfExists($table, 'transactions', 'transactions_is_confidential_index');
            $this->dropIndexIfExists($table, 'transactions', 'transactions_is_mc_index');
            $this->dropIndexIfExists($table, 'transactions', 'transactions_supplier_id_index');
            $this->dropIndexIfExists($table, 'transactions', 'transactions_company_id_index');
            $this->dropIndexIfExists($table, 'transactions', 'transactions_document_id_index');
            $this->dropIndexIfExists($table, 'transactions', 'transactions_assigned_id_index');
        });
    }

    /**
     * All requested columns exist on the table.
     */
    private function columnsExist(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Raw index-existence check via information_schema — avoids requiring
     * doctrine/dbal (Schema::hasIndex() needs it in some Laravel versions).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            "SELECT COUNT(1) AS cnt
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?",
            [$database, $table, $indexName]
        );

        return ($result[0]->cnt ?? 0) > 0;
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            $table->dropIndex($indexName);
        }
    }
};