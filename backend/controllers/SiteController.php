<?php
/** Role-scoped administration; audit records commit atomically with their writes. */
declare(strict_types=1);
namespace backend\controllers;

use common\models\RecordForm;
use common\models\Website;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

final class SiteController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => ['class' => AccessControl::class, 'except' => ['error'], 'rules' => [['allow' => true, 'roles' => ['@']]]],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['moderate' => ['POST'], 'logout' => ['POST']]],
        ];
    }
    public function actions(): array { return ['error' => ['class' => 'yii\\web\\ErrorAction']]; }
    private function admin(): void
    {
        if (!Yii::$app->user->identity->isAdmin()) { throw new ForbiddenHttpException('此操作仅限管理员。'); }
    }
    private function canProject(int $id): bool
    {
        return Yii::$app->user->identity->isAdmin() || Website::query('project_members')->where(['project_id' => $id, 'member_id' => Yii::$app->user->identity->memberId])->exists();
    }
    private function save(string $table, array $values, ?int $id): int
    {
        if ($id === null) {
            Yii::$app->db->createCommand()->insert($table, $values)->execute();
            $id = (int) Yii::$app->db->lastInsertID;
        } else {
            Yii::$app->db->createCommand()->update($table, $values, ['id' => $id])->execute();
        }
        Website::audit('save', $table, $id);
        return $id;
    }
    public function actionIndex(): string
    {
        return $this->render('index', ['stats' => Website::stats(), 'recentMessages' => Yii::$app->user->identity->isAdmin() ? Website::query('messages')->orderBy(['id' => SORT_DESC])->limit(5)->all() : []]);
    }
    public function actionTeam(): string|Response
    {
        $this->admin();
        $model = RecordForm::forKind('team', Website::team());
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            Yii::$app->db->transaction(fn() => $this->save('teams', $model->attributes, 1));
            Yii::$app->session->setFlash('success', '团队信息已更新。');
            return $this->refresh();
        }
        return $this->render('team', ['model' => $model]);
    }
    public function actionProfile(): string|Response
    {
        $id = Yii::$app->user->identity->memberId;
        if (!$id) { throw new ForbiddenHttpException('此账号未关联成员档案。'); }
        $model = RecordForm::forKind('profile', Website::one('members', $id));
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            Yii::$app->db->transaction(fn() => $this->save('members', $model->attributes, $id));
            Yii::$app->session->setFlash('success', '个人档案已更新，前台立即可见。');
            return $this->refresh();
        }
        return $this->render('profile', ['model' => $model]);
    }
    public function actionMembers(): string { $this->admin(); return $this->render('members', ['rows' => Website::members()]); }
    public function actionMember(?int $id = null): string|Response
    {
        $this->admin();
        $model = RecordForm::forKind('member', $id ? Website::one('members', $id) : ['avatar_color' => '#5965e0', 'sort_order' => 0]);
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            Yii::$app->db->transaction(fn() => $this->save('members', $model->attributes, $id));
            Yii::$app->session->setFlash('success', '成员档案已保存。');
            return $this->redirect(['members']);
        }
        return $this->render('member', ['model' => $model]);
    }
    public function actionProjects(): string
    {
        $query = Website::query('projects')->orderBy(['id' => SORT_DESC]);
        if (!Yii::$app->user->identity->isAdmin()) {
            $ids = Website::query('project_members')->select('project_id')->where(['member_id' => Yii::$app->user->identity->memberId])->column();
            $query->where(['id' => $ids]);
        }
        return $this->render('projects', ['rows' => $query->all()]);
    }
    public function actionProject(?int $id = null): string|Response
    {
        if ($id !== null && !$this->canProject($id)) { throw new ForbiddenHttpException('你只能编辑自己参与的项目。'); }
        $values = $id ? Website::one('projects', $id) : ['status' => 'planning', 'published' => 0, 'progress' => 0, 'member_ids' => []];
        $values['member_ids'] = $id ? Website::query('project_members')->select('member_id')->where(['project_id' => $id])->column() : [];
        $model = RecordForm::forKind('project', $values);
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $members = Yii::$app->user->identity->isAdmin() ? array_unique(array_map('intval', (array) $model->member_ids)) : ($id ? $values['member_ids'] : [Yii::$app->user->identity->memberId]);
            if (count($members) !== (int) Website::query('members')->where(['id' => $members])->count()) { $model->addError('member_ids', '所选成员不存在。'); }
            if (!$model->hasErrors()) {
                Yii::$app->db->transaction(function () use ($model, $members, $id): void {
                    $attributes = $model->attributes;
                    unset($attributes['member_ids']);
                    $attributes['started_on'] = $attributes['started_on'] ?: null;
                    $attributes['team_id'] = 1;
                    $savedId = $this->save('projects', $attributes, $id);
                    // Retained memberships keep their domain role; editing a title must not erase it.
                    $existing = Website::query('project_members')->select('member_id')->where(['project_id' => $savedId])->column();
                    foreach (array_diff($existing, $members) as $memberId) { Yii::$app->db->createCommand()->delete('project_members', ['project_id' => $savedId, 'member_id' => $memberId])->execute(); }
                    foreach (array_diff($members, $existing) as $memberId) { Yii::$app->db->createCommand()->insert('project_members', ['project_id' => $savedId, 'member_id' => $memberId, 'role' => '贡献者'])->execute(); }
                });
                Yii::$app->session->setFlash('success', '项目已保存。公开状态决定前台是否展示。');
                return $this->redirect(['projects']);
            }
        }
        return $this->render('project', ['model' => $model, 'members' => Website::members()]);
    }
    public function actionUpdates(): string
    {
        $query = Website::query('updates')->orderBy(['id' => SORT_DESC]);
        if (!Yii::$app->user->identity->isAdmin()) { $query->where(['author_id' => Yii::$app->user->id]); }
        return $this->render('updates', ['rows' => $query->all()]);
    }
    public function actionUpdate(?int $id = null): string|Response
    {
        $values = $id ? Website::one('updates', $id) : ['published' => 0];
        if ($id && !Yii::$app->user->identity->isAdmin() && (int) $values['author_id'] !== (int) Yii::$app->user->id) { throw new ForbiddenHttpException('你只能编辑自己的动态。'); }
        $model = RecordForm::forKind('update', $values);
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $projectId = $model->project_id ? (int) $model->project_id : null;
            if ($projectId !== null && (!Website::query('projects')->where(['id' => $projectId])->exists() || !$this->canProject($projectId))) { $model->addError('project_id', '请选择你参与的项目。'); }
            if (!$model->hasErrors()) {
                Yii::$app->db->transaction(function () use ($model, $projectId, $values, $id): void {
                    $attributes = $model->attributes;
                    $attributes['project_id'] = $projectId;
                    $attributes['author_id'] = $values['author_id'] ?? Yii::$app->user->id;
                    $attributes['published_at'] = $model->published ? ($values['published_at'] ?? date('Y-m-d H:i:s')) : null;
                    $this->save('updates', $attributes, $id);
                });
                Yii::$app->session->setFlash('success', '团队动态已保存。');
                return $this->redirect(['updates']);
            }
        }
        $query = Website::query('projects')->orderBy(['id' => SORT_DESC]);
        if (!Yii::$app->user->identity->isAdmin()) {
            $query->where(['id' => Website::query('project_members')->select('project_id')->where(['member_id' => Yii::$app->user->identity->memberId])]);
        }
        $projects = $query->all();
        return $this->render('update', ['model' => $model, 'projects' => $projects]);
    }
    public function actionMessages(): string
    {
        $this->admin();
        $rows = Website::query('messages')->orderBy(['id' => SORT_DESC])->limit(200)->all();
        foreach ($rows as &$row) { $row['replies'] = Website::replies((int) $row['id']); }
        return $this->render('messages', ['rows' => $rows]);
    }
    public function actionModerate(int $id): Response
    {
        $this->admin();
        Website::one('messages', $id);
        $status = Yii::$app->request->post('status');
        $reply = Yii::$app->request->post('reply', '');
        if (!in_array($status, ['pending', 'approved', 'rejected'], true) || !is_string($reply) || mb_strlen($reply) > 2000) { throw new \yii\web\BadRequestHttpException('审核状态或回复不合法。'); }
        Yii::$app->db->transaction(function () use ($id, $status, $reply): void {
            $this->save('messages', ['status' => $status], $id);
            if (trim($reply) !== '') { Yii::$app->db->createCommand()->insert('message_replies', ['message_id' => $id, 'author_id' => Yii::$app->user->id, 'body' => trim($reply)])->execute(); }
        });
        Yii::$app->session->setFlash('success', '审核与回复已保存。');
        return $this->redirect(['messages']);
    }
    public function actionLogout(): Response { Yii::$app->user->logout(); return $this->redirect('/'); }
}
