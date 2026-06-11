<?php


declare(strict_types=1);

namespace common\services\keycrm;

use Throwable;
use Yii;
use yii\helpers\Json;

final class KeyCrmSyncRunLogger
{
    private const TABLE_RUN = '{{%keycrm_sync_run}}';
    private const TABLE_STATE = '{{%keycrm_sync_state}}';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public function queued(string $syncType, string $uniqueKey, array $params = []): int
    {
        $now = time();
        $nowMs = $this->nowMs();

        Yii::$app->db->createCommand()->insert(self::TABLE_RUN, [
            'sync_type' => $syncType,
            'status' => self::STATUS_QUEUED,
            'unique_key' => $uniqueKey !== '' ? $uniqueKey : null,
            'queue_job_id' => null,
            'params_json' => $params !== [] ? $this->encode($params) : null,
            'stats_json' => null,
            'error_message' => null,
            'error_trace' => null,
            'queued_at' => $now,
            'started_at' => null,
            'finished_at' => null,
            'queued_at_ms' => $nowMs,
            'started_at_ms' => null,
            'finished_at_ms' => null,
            'duration_ms' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();

        $runId = (int)Yii::$app->db->getLastInsertID();

        $this->upsertState($syncType, [
            'status' => self::STATUS_QUEUED,
            'active_run_id' => $runId,
            'last_run_id' => $runId,
            'last_unique_key' => $uniqueKey !== '' ? $uniqueKey : null,
            'last_queued_at' => $now,
            'last_error_message' => null,
            'updated_at' => $now,
        ]);

        return $runId;
    }

    public function attachQueueJobId(int $runId, int|string|null $queueJobId): void
    {
        if ($queueJobId === null) {
            return;
        }

        $run = $this->findRun($runId);

        if ($run === null) {
            return;
        }

        $now = time();

        Yii::$app->db->createCommand()->update(self::TABLE_RUN, [
            'queue_job_id' => (string)$queueJobId,
            'updated_at' => $now,
        ], ['id' => $runId])->execute();

        $this->upsertState((string)$run['sync_type'], [
            'last_queue_job_id' => (string)$queueJobId,
            'updated_at' => $now,
        ]);
    }

    public function running(int $runId): void
    {
        $run = $this->findRun($runId);

        if ($run === null) {
            return;
        }

        $now = time();
        $nowMs = $this->nowMs();

        Yii::$app->db->createCommand()->update(self::TABLE_RUN, [
            'status' => self::STATUS_RUNNING,
            'started_at' => $now,
            'started_at_ms' => $nowMs,
            'updated_at' => $now,
        ], ['id' => $runId])->execute();

        $this->upsertState((string)$run['sync_type'], [
            'status' => self::STATUS_RUNNING,
            'active_run_id' => $runId,
            'last_run_id' => $runId,
            'last_started_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function success(int $runId, array $stats = []): void
    {
        $run = $this->findRun($runId);

        if ($run === null) {
            return;
        }

        $now = time();
        $nowMs = $this->nowMs();
        $durationMs = $this->durationMs($run, $nowMs);
        $statsJson = $stats !== [] ? $this->encode($stats) : null;

        Yii::$app->db->createCommand()->update(self::TABLE_RUN, [
            'status' => self::STATUS_SUCCESS,
            'stats_json' => $statsJson,
            'error_message' => null,
            'error_trace' => null,
            'finished_at' => $now,
            'finished_at_ms' => $nowMs,
            'duration_ms' => $durationMs,
            'updated_at' => $now,
        ], ['id' => $runId])->execute();

        $this->upsertState((string)$run['sync_type'], [
            'status' => self::STATUS_SUCCESS,
            'active_run_id' => null,
            'last_run_id' => $runId,
            'last_finished_at' => $now,
            'last_success_at' => $now,
            'last_duration_ms' => $durationMs,
            'last_stats_json' => $statsJson,
            'last_error_message' => null,
            'updated_at' => $now,
        ]);
    }

    public function failed(int $runId, Throwable $e, array $stats = []): void
    {
        $run = $this->findRun($runId);

        if ($run === null) {
            return;
        }

        $now = time();
        $nowMs = $this->nowMs();
        $durationMs = $this->durationMs($run, $nowMs);
        $statsJson = $stats !== [] ? $this->encode($stats) : null;

        Yii::$app->db->createCommand()->update(self::TABLE_RUN, [
            'status' => self::STATUS_FAILED,
            'stats_json' => $statsJson,
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString(),
            'finished_at' => $now,
            'finished_at_ms' => $nowMs,
            'duration_ms' => $durationMs,
            'updated_at' => $now,
        ], ['id' => $runId])->execute();

        $this->upsertState((string)$run['sync_type'], [
            'status' => self::STATUS_FAILED,
            'active_run_id' => null,
            'last_run_id' => $runId,
            'last_finished_at' => $now,
            'last_error_at' => $now,
            'last_duration_ms' => $durationMs,
            'last_stats_json' => $statsJson,
            'last_error_message' => $e->getMessage(),
            'updated_at' => $now,
        ]);
    }

    public function failedBeforeQueue(int $runId, Throwable $e): void
    {
        $this->failed($runId, $e);
    }

    public function skipped(string $syncType, string $uniqueKey, string $reason, array $params = []): void
    {
        $now = time();
        $nowMs = $this->nowMs();

        Yii::$app->db->createCommand()->insert(self::TABLE_RUN, [
            'sync_type' => $syncType,
            'status' => self::STATUS_SKIPPED,
            'unique_key' => $uniqueKey !== '' ? $uniqueKey : null,
            'queue_job_id' => null,
            'params_json' => $params !== [] ? $this->encode($params) : null,
            'stats_json' => null,
            'error_message' => $reason,
            'error_trace' => null,
            'queued_at' => $now,
            'started_at' => null,
            'finished_at' => $now,
            'queued_at_ms' => $nowMs,
            'started_at_ms' => null,
            'finished_at_ms' => $nowMs,
            'duration_ms' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();

        $runId = (int)Yii::$app->db->getLastInsertID();

        $this->upsertState($syncType, [
            'status' => self::STATUS_SKIPPED,
            'last_run_id' => $runId,
            'last_unique_key' => $uniqueKey !== '' ? $uniqueKey : null,
            'last_finished_at' => $now,
            'last_error_message' => $reason,
            'updated_at' => $now,
        ]);
    }

    public function hasActiveRun(string $syncType, ?int $maxAgeSeconds = 3600): bool
    {
        $query = Yii::$app->db
            ->createCommand(
                <<<SQL
SELECT COUNT(*)
FROM {{%keycrm_sync_run}}
WHERE [[sync_type]] = :syncType
  AND [[status]] IN (:queued, :running)
SQL
            )
            ->bindValue(':syncType', $syncType)
            ->bindValue(':queued', self::STATUS_QUEUED)
            ->bindValue(':running', self::STATUS_RUNNING);

        if ($maxAgeSeconds !== null && $maxAgeSeconds > 0) {
            $query->setSql(
                <<<SQL
SELECT COUNT(*)
FROM {{%keycrm_sync_run}}
WHERE [[sync_type]] = :syncType
  AND [[status]] IN (:queued, :running)
  AND [[created_at]] >= :minCreatedAt
SQL
            );
            $query->bindValue(':minCreatedAt', time() - $maxAgeSeconds);
        }

        return (int)$query->queryScalar() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRun(int $runId): ?array
    {
        $run = Yii::$app->db
            ->createCommand('SELECT * FROM ' . self::TABLE_RUN . ' WHERE [[id]] = :id')
            ->bindValue(':id', $runId)
            ->queryOne();

        return is_array($run) ? $run : null;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function upsertState(string $syncType, array $values): void
    {
        $now = time();

        $insert = array_merge([
            'sync_type' => $syncType,
            'status' => $values['status'] ?? self::STATUS_RUNNING,
            'created_at' => $now,
            'updated_at' => $now,
        ], $values);

        $update = $values;
        unset($update['sync_type'], $update['created_at']);

        Yii::$app->db
            ->createCommand()
            ->upsert(self::TABLE_STATE, $insert, $update)
            ->execute();
    }

    /**
     * @param array<string, mixed> $run
     */
    private function durationMs(array $run, int $finishedAtMs): int
    {
        $startedAtMs = isset($run['started_at_ms']) && (int)$run['started_at_ms'] > 0
            ? (int)$run['started_at_ms']
            : (int)$run['queued_at_ms'];

        return max($finishedAtMs - $startedAtMs, 0);
    }

    private function encode(array $data): string
    {
        return Json::encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function nowMs(): int
    {
        return (int)floor(microtime(true) * 1000);
    }
}