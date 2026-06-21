<?php

use yii\db\Migration;
use yii\db\Query;

class m260326_120750_backfill_payment_from_payment_log extends Migration
{
    private string $paymentTable = '{{%payment}}';
    private string $paymentLogTable = '{{%payment_log}}';
    private string $orderTable = '{{%order}}';

    public function safeUp(): void
    {
        $this->assertRequiredSchema();

        $query = (new Query())
            ->select([
                'pl.id',
                'pl.order_id',
                'pl.payment_id',
                'pl.provider',
                'pl.external_id',
                'pl.status',
                'pl.amount',
                'pl.currency',
                'pl.payment_method',
                'pl.error_message',
                'pl.created_at',
                'pl.updated_at',
                'o.customer_id',
            ])
            ->from(['pl' => $this->paymentLogTable])
            ->leftJoin(['o' => $this->orderTable], 'o.id = pl.order_id')
            ->where(['pl.payment_id' => null])
            ->orderBy(['pl.id' => SORT_ASC]);

        foreach ($query->each(100, $this->db) as $row) {
            $status = $this->normalizeStatus($row['status']);
            $provider = !empty($row['provider']) ? (string)$row['provider'] : 'legacy';

            $createdAt = (int)$row['created_at'];
            $updatedAt = (int)$row['updated_at'];

            $paidAt = $status === 'paid' ? $updatedAt : null;
            $failedAt = in_array($status, ['failed', 'cancelled'], true) ? $updatedAt : null;

            $this->insert($this->paymentTable, [
                'order_id' => (int)$row['order_id'],
                'customer_id' => $row['customer_id'] !== null ? (int)$row['customer_id'] : null,
                'provider' => $provider,
                'status' => $status,
                'amount' => $row['amount'],
                'currency' => !empty($row['currency']) ? (string)$row['currency'] : 'UAH',
                'payment_method' => $row['payment_method'] !== null ? (string)$row['payment_method'] : null,
                'external_id' => $row['external_id'] !== null ? (string)$row['external_id'] : null,
                'external_order_id' => null,
                'idempotency_key' => null,
                'redirect_url' => null,
                'error_message' => $row['error_message'] !== null ? (string)$row['error_message'] : null,
                'paid_at' => $paidAt,
                'failed_at' => $failedAt,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $paymentId = (int)$this->db->getLastInsertID();

            $this->update(
                $this->paymentLogTable,
                ['payment_id' => $paymentId],
                [
                    'and',
                    ['id' => (int)$row['id']],
                    ['payment_id' => null],
                ]
            );
        }
    }

    private function assertRequiredSchema(): void
    {
        $paymentSchema = $this->db->schema->getTableSchema($this->paymentTable, true);
        $paymentLogSchema = $this->db->schema->getTableSchema($this->paymentLogTable, true);
        $orderSchema = $this->db->schema->getTableSchema($this->orderTable, true);

        if ($paymentSchema === null) {
            throw new RuntimeException('Таблица payment не найдена. Сначала примени create_payment_table.');
        }

        if ($paymentLogSchema === null) {
            throw new RuntimeException('Таблица payment_log не найдена.');
        }

        if ($orderSchema === null) {
            throw new RuntimeException('Таблица order не найдена.');
        }

        if (!isset($paymentLogSchema->columns['payment_id'])) {
            throw new RuntimeException('В payment_log отсутствует колонка payment_id. Сначала примени alter_payment_log_table_add_payment_link.');
        }

        if (!isset($paymentLogSchema->columns['provider'])) {
            throw new RuntimeException('В payment_log отсутствует колонка provider. Сначала примени alter_payment_log_table_add_payment_link.');
        }
    }

    private function normalizeStatus(?string $status): string
    {
        $status = trim((string)$status);

        if ($status === '') {
            return 'new';
        }

        $map = [
            'new' => 'new',
            'created' => 'new',
            'pending' => 'pending',
            'processing' => 'pending',
            'authorized' => 'authorized',
            'paid' => 'paid',
            'success' => 'paid',
            'completed' => 'paid',
            'failed' => 'failed',
            'error' => 'failed',
            'declined' => 'failed',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'refunded' => 'refunded',
        ];

        $normalized = mb_strtolower($status);

        return $map[$normalized] ?? 'pending';
    }

    public function safeDown(): void
    {
        $schema = $this->db->schema->getTableSchema($this->paymentLogTable, true);
        if ($schema === null || !isset($schema->columns['payment_id'])) {
            return;
        }

        $legacyPaymentIds = (new Query())
            ->select('id')
            ->from($this->paymentTable)
            ->where(['provider' => 'legacy'])
            ->column($this->db);

        if (empty($legacyPaymentIds)) {
            return;
        }

        $this->update(
            $this->paymentLogTable,
            ['payment_id' => null],
            ['payment_id' => $legacyPaymentIds]
        );

        $this->delete($this->paymentTable, ['id' => $legacyPaymentIds]);
    }
}