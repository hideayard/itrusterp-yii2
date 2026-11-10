<?php

namespace app\models;

use Yii;
use yii\db\Query;
use app\components\BaseModel;

/**
 * Port dari Webkul\Project\Models\Timesheet (tabel analytic_records).
 *
 * Meniru hook boot() sumber: setiap create/update/delete menghitung ulang
 * total_hours_spent / effective_hours / overtime / remaining_hours / progress
 * pada task terkait beserta task induknya.
 */
class Timesheet extends BaseModel
{
    public const TYPE_PROJECTS = 'projects';

    public static function tableName(): string
    {
        return 'analytic_records';
    }

    public static function isCompanyScoped(): bool
    {
        return true;
    }

    public function getProject()
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getTask()
    {
        return $this->hasOne(Task::class, ['id' => 'task_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getPartner()
    {
        return $this->hasOne(Partner::class, ['id' => 'partner_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'creator_id']);
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert && !$this->type) {
            $this->type = self::TYPE_PROJECTS;
        }
        if ($insert && !$this->creator_id) {
            $this->creator_id = Yii::$app->access?->currentUserId();
        }
        if ($this->project_id === null && $this->task_id !== null) {
            $task = Task::findUnscoped()->where(['id' => $this->task_id])->one();
            if ($task !== null) {
                $this->project_id = $task->project_id;
                $this->partner_id ??= $task->partner_id;
            }
        }

        return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        $taskIds = [];
        if (isset($changedAttributes['task_id'])) {
            $taskIds[] = $changedAttributes['task_id'];
        }
        if ($this->task_id !== null) {
            $taskIds[] = $this->task_id;
        }
        foreach (array_unique($taskIds) as $taskId) {
            self::recomputeTask($taskId);
        }
    }

    public function afterDelete()
    {
        parent::afterDelete();

        self::recomputeTask($this->task_id);
    }

    public static function recomputeTask($taskId): void
    {
        if (!$taskId) {
            return;
        }
        $task = (new Query())->from('projects_tasks')->where(['id' => (int) $taskId])->one();
        if ($task === null) {
            return;
        }

        $own = self::sumForTask((int) $taskId);
        $subTaskIds = self::subTaskIds((int) $taskId);
        $subSum = $subTaskIds === [] ? 0.0 : self::sumForTasks($subTaskIds);

        $hoursSpent = round($own + $subSum, 4);
        self::updateHours((int) $taskId, $hoursSpent, round($own, 4), (float) $task['allocated_hours']);

        $parentId = $task['parent_id'] ?? null;
        if (!$parentId) {
            return;
        }
        $parent = (new Query())->from('projects_tasks')->where(['id' => (int) $parentId])->one();
        if ($parent === null) {
            return;
        }

        $parentOwn = self::sumForTask((int) $parentId);
        $parentSubIds = self::subTaskIds((int) $parentId);
        $parentSubSum = $parentSubIds === [] ? 0.0 : self::sumForTasks($parentSubIds);
        $parentHoursSpent = round($parentOwn + $parentSubSum, 4);

        $subtaskEffective = 0.0;
        if ($parentSubIds !== []) {
            $subtaskEffective = (float) (new Query())->from('projects_tasks')
                ->where(['id' => $parentSubIds])->sum('effective_hours');
        }

        self::updateHours((int) $parentId, $parentHoursSpent, round($parentOwn, 4), (float) $parent['allocated_hours'], $subtaskEffective);
    }

    private static function updateHours(int $taskId, float $hoursSpent, float $effective, float $allocated, ?float $subtaskEffective = null): void
    {
        $data = [
            'total_hours_spent' => $hoursSpent,
            'effective_hours' => $effective,
            'overtime' => $hoursSpent > $allocated ? round($hoursSpent - $allocated, 4) : 0,
            'remaining_hours' => round($allocated - $hoursSpent, 4),
            'progress' => $allocated ? round(($hoursSpent / $allocated) * 100, 4) : 0,
        ];
        if ($subtaskEffective !== null) {
            $data['subtask_effective_hours'] = round($subtaskEffective, 4);
        }

        Yii::$app->db->createCommand()->update('projects_tasks', $data, ['id' => $taskId])->execute();
    }

    private static function sumForTask(int $taskId): float
    {
        return (float) (new Query())->from('analytic_records')
            ->where(['task_id' => $taskId])->sum('unit_amount');
    }

    private static function sumForTasks(array $taskIds): float
    {
        return (float) (new Query())->from('analytic_records')
            ->where(['task_id' => array_map('intval', $taskIds)])->sum('unit_amount');
    }

    private static function subTaskIds(int $taskId): array
    {
        return array_map('intval', (new Query())->from('projects_tasks')
            ->select('id')->where(['parent_id' => $taskId])->column());
    }

    public static function apiFields(): array
    {
        return ['id', 'type', 'name', 'date', 'amount', 'unit_amount', 'user_id', 'partner_id', 'company_id', 'creator_id', 'project_id', 'task_id', 'created_at', 'updated_at'];
    }

    public static function apiCasts(): array
    {
        return [
            'amount' => 'float',
            'unit_amount' => 'float',
            'date' => 'iso',
            'created_at' => 'iso',
            'updated_at' => 'iso',
        ];
    }

    public static function apiRelations(): array
    {
        return [
            'project' => 'project',
            'task' => 'task',
            'user' => 'user',
            'partner' => 'partner',
            'company' => 'company',
            'creator' => 'creator',
        ];
    }

    public static function apiRules(): array
    {
        return [
            'type' => ['string', 'max:32'],
            'name' => ['string', 'max:255'],
            'date' => ['required', 'date'],
            'user_id' => ['required', 'integer', 'exists:users'],
            'unit_amount' => ['required', 'number', 'min_num:0'],
            'amount' => ['number'],
            'project_id' => ['integer', 'exists:projects_projects'],
            'task_id' => ['integer', 'exists:projects_tasks'],
            'partner_id' => ['integer', 'exists:partners_partners'],
            'company_id' => ['integer', 'exists:companies', 'company_allowed'],
        ];
    }
}
