<?php
/** Public website and guest authentication; write paths use Yii CSRF validation. */
declare(strict_types=1);
namespace frontend\controllers;

use common\models\LoginForm;
use common\models\MessageForm;
use common\models\Website;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class SiteController extends Controller
{
    public function behaviors(): array
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['logout' => ['POST'], 'stats' => ['GET'], 'health' => ['GET'], 'download' => ['GET']]]];
    }
    public function actions(): array { return ['error' => ['class' => 'yii\\web\\ErrorAction']]; }
    public function actionIndex(): string
    {
        return $this->render('index', ['team' => Website::team(), 'members' => Website::members(), 'projects' => Website::publicProjects(), 'updates' => array_slice(Website::publicUpdates(), 0, 3), 'stats' => Website::stats()]);
    }
    public function actionMembers(): string { return $this->render('members', ['members' => Website::members()]); }
    public function actionProjects(): string { return $this->render('projects', ['projects' => Website::publicProjects()]); }
    public function actionProject(int $id): string
    {
        $project = Website::one('projects', $id);
        if (!(bool) $project['published']) { throw new NotFoundHttpException('项目尚未公开。'); }
        return $this->render('project', ['project' => $project, 'members' => Website::projectMembers($id), 'updates' => array_values(array_filter(Website::publicUpdates(), static fn(array $row): bool => (int) $row['project_id'] === $id)), 'milestones' => Website::query('milestones')->where(['project_id' => $id])->orderBy('due_on')->all()]);
    }
    public function actionUpdates(): string { return $this->render('updates', ['updates' => Website::publicUpdates()]); }
    public function actionMessage(): string|Response
    {
        $form = new MessageForm();
        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            // A session cooldown limits accidental duplicate submissions without collecting IP data.
            $last = (int) Yii::$app->session->get('message_last_sent', 0);
            if (time() - $last < 15) {
                $form->addError('body', '留言已收到，请稍等 15 秒后再提交。');
            } else {
                Yii::$app->db->createCommand()->insert('messages', ['name' => $form->name, 'email' => $form->email, 'body' => $form->body, 'status' => 'pending'])->execute();
                Yii::$app->session->set('message_last_sent', time());
                Yii::$app->session->setFlash('success', '留言已送达，审核通过后会公开显示。感谢你的声音！');
                return $this->refresh();
            }
        }
        $messages = Website::query('messages')->select(['id', 'name', 'body', 'created_at'])->where(['status' => 'approved'])->orderBy(['id' => SORT_DESC])->limit(100)->all();
        foreach ($messages as &$message) { $message['replies'] = Website::replies((int) $message['id']); }
        return $this->render('message', ['messages' => $messages, 'form' => $form]);
    }
    public function actionSubmissions(): string
    {
        return $this->render('submissions', ['submissions' => Website::query('submissions')->orderBy('sort_order')->all()]);
    }
    public function actionDownload(int $id): Response
    {
        $record = Website::one('submissions', $id);
        $base = realpath(dirname(__DIR__, 2) . '/data/team');
        $path = $base ? realpath($base . DIRECTORY_SEPARATOR . $record['file_name']) : false;
        if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new NotFoundHttpException('文档尚未生成，请先完成交付文档构建。');
        }
        return Yii::$app->response->sendFile($path, basename($path), ['inline' => false]);
    }
    public function actionLogin(): string|Response
    {
        if (!Yii::$app->user->isGuest) { return $this->redirect('/admin/'); }
        $form = new LoginForm();
        if ($form->load(Yii::$app->request->post()) && $form->login()) { return $this->redirect('/admin/'); }
        $form->password = '';
        return $this->render('login', ['model' => $form]);
    }
    public function actionLogout(): Response { Yii::$app->user->logout(); return $this->redirect('/'); }
    public function actionStats(): Response { return $this->asJson(Website::stats()); }
    public function actionHealth(): Response
    {
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        try {
            if (!Website::query('teams')->where(['id' => 1])->exists()) { throw new \RuntimeException('Installation missing.'); }
            return $this->asJson(['status' => 'ok', 'application' => '星河工坊']);
        } catch (\Throwable $error) {
            Yii::error($error->getMessage());
            Yii::$app->response->statusCode = 503;
            return $this->asJson(['status' => 'unavailable']);
        }
    }
}
