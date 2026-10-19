<?php
/** Public guestbook input, separate from moderation-owned persistence fields. */
declare(strict_types=1);
namespace common\models;

use yii\base\Model;

final class MessageForm extends Model
{
    public $name = '';
    public $email = '';
    public $body = '';
    public $website = '';
    public function rules(): array
    {
        return [[['name', 'email', 'body'], 'trim'], [['name', 'body'], 'required'], ['name', 'string', 'min' => 1, 'max' => 80], ['email', 'email'], ['email', 'string', 'max' => 254], ['body', 'string', 'min' => 3, 'max' => 2000], ['website', 'compare', 'compareValue' => '', 'message' => '无法提交此留言。']];
    }
    public function attributeLabels(): array { return ['name' => '称呼', 'email' => '邮箱（不公开，可选）', 'body' => '想说的话']; }
}
