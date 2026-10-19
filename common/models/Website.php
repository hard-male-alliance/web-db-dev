<?php
/** Read models and transaction helpers shared across the two Yii applications. */
declare(strict_types=1);
namespace common\models;

use Yii;
use yii\db\Query;
use yii\web\NotFoundHttpException;

final class Website
{
    public static function query(string $table): Query { return (new Query())->from($table); }
    public static function one(string $table, int $id): array
    {
        $row = self::query($table)->where(['id' => $id])->one();
        if (!$row) { throw new NotFoundHttpException('找不到这条记录。'); }
        return $row;
    }
    public static function team(): array { return self::one('teams', 1); }
    public static function members(): array
    {
        $rows = self::query('members')->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all();
        foreach ($rows as &$row) {
            $row['skills'] = (new Query())->select(['s.name', 'ms.level'])->from(['ms' => 'member_skills'])->innerJoin(['s' => 'skills'], 's.id=ms.skill_id')->where(['ms.member_id' => $row['id']])->all();
        }
        return $rows;
    }
    public static function projectMembers(int $id): array
    {
        return (new Query())->select(['m.*', 'pm.role'])->from(['pm' => 'project_members'])->innerJoin(['m' => 'members'], 'm.id=pm.member_id')->where(['pm.project_id' => $id])->all();
    }
    public static function publicProjects(): array
    {
        $rows = self::query('projects')->where(['published' => 1])->orderBy(['id' => SORT_DESC])->all();
        foreach ($rows as &$row) { $row['members'] = self::projectMembers((int) $row['id']); }
        return $rows;
    }
    public static function publicUpdates(): array
    {
        return (new Query())->select(['u.*', 'p.title AS project_title', 'a.username AS author_name'])->from(['u' => 'updates'])->leftJoin(['p' => 'projects'], 'p.id=u.project_id')->leftJoin(['a' => 'users'], 'a.id=u.author_id')->where(['u.published' => 1])->andWhere(['or', ['u.project_id' => null], ['p.published' => 1]])->orderBy(['u.published_at' => SORT_DESC, 'u.id' => SORT_DESC])->all();
    }
    public static function replies(int $id): array
    {
        return (new Query())->select(['r.*', 'u.username AS author_name'])->from(['r' => 'message_replies'])->innerJoin(['u' => 'users'], 'u.id=r.author_id')->where(['r.message_id' => $id])->orderBy('r.id')->all();
    }
    public static function stats(): array
    {
        $counts = [];
        foreach (['members', 'projects', 'updates', 'messages'] as $table) {
            $query = self::query($table);
            if ($table === 'projects') { $query->where(['published' => 1]); }
            if ($table === 'updates') { $counts[$table] = count(self::publicUpdates()); continue; }
            if ($table === 'messages') { $query->where(['status' => 'approved']); }
            $counts[$table] = (int) $query->count();
        }
        $labels = ['planning' => '规划中', 'active' => '进行中', 'completed' => '已完成'];
        $counts['project_statuses'] = [];
        foreach ($labels as $status => $label) {
            $counts['project_statuses'][] = ['label' => $label, 'count' => (int) self::query('projects')->where(['published' => 1, 'status' => $status])->count()];
        }
        return $counts;
    }
    public static function audit(string $action, string $type, int $id): void
    {
        Yii::$app->db->createCommand()->insert('audit_logs', ['user_id' => Yii::$app->user->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id])->execute();
    }
}
