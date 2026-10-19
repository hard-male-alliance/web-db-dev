<?php
/** Login validation with database-backed bounded failure throttling. */
declare(strict_types=1);
namespace common\models;

use Yii;
use yii\db\Query;
use yii\db\Expression;
use yii\base\Model;

final class LoginForm extends Model
{
    public $username = '';
    public $password = '';
    public function rules(): array
    {
        return [[['username', 'password'], 'required'], ['username', 'string', 'max' => 64], ['password', 'string', 'max' => 200]];
    }
    public function attributeLabels(): array { return ['username' => '用户名', 'password' => '密码']; }
    public function login(): bool
    {
        if (!$this->validate()) { return false; }
        $ip = hash('sha256', Yii::$app->request->userIP ?? 'unknown');
        $count = (new Query())->from('login_attempts')->where(['ip_hash' => $ip])->andWhere(['>=', 'created_at', new Expression('DATE_SUB(NOW(), INTERVAL 15 MINUTE)')])->count();
        if ((int) $count >= 10) {
            $this->addError('password', '尝试次数过多，请 15 分钟后再试。');
            return false;
        }
        $user = User::byUsername($this->username);
        if (!$user || !Yii::$app->security->validatePassword($this->password, $user->record['password_hash'])) {
            Yii::$app->db->createCommand()->insert('login_attempts', ['ip_hash' => $ip])->execute();
            $this->addError('password', '用户名或密码不正确。');
            return false;
        }
        Yii::$app->db->createCommand()->delete('login_attempts', ['ip_hash' => $ip])->execute();
        return Yii::$app->user->login($user, 0);
    }
}
