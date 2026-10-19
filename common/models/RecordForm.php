<?php
/** Explicit editable field contracts for the administration forms. */
declare(strict_types=1);
namespace common\models;

use yii\base\DynamicModel;

final class RecordForm extends DynamicModel
{
    public static function forKind(string $kind, array $values = []): self
    {
        $fields = match ($kind) {
            'team' => ['name', 'tagline', 'description', 'email', 'location'],
            'profile' => ['name', 'title', 'bio', 'email'],
            'member' => ['name', 'title', 'bio', 'email', 'avatar_color', 'sort_order'],
            'project' => ['title', 'summary', 'description', 'status', 'published', 'progress', 'started_on', 'member_ids'],
            'update' => ['title', 'body', 'project_id', 'published'],
        };
        $model = new self(array_fill_keys($fields, ''));
        $model->setAttributes(array_intersect_key($values, array_flip($fields)), false);
        foreach ($fields as $field) {
            if (in_array($field, ['published', 'progress', 'sort_order', 'project_id', 'member_ids'], true)) { continue; }
            $model->addRule($field, 'trim');
        }
        $required = match ($kind) { 'team', 'profile', 'member' => ['name'], 'project' => ['title', 'summary', 'description', 'status'], 'update' => ['title', 'body'] };
        $model->addRule($required, 'required');
        $limits = ['name' => 100, 'title' => in_array($kind, ['profile', 'member'], true) ? 120 : 200, 'tagline' => 255, 'summary' => 500, 'location' => 120];
        foreach (array_intersect($fields, array_keys($limits)) as $field) { $model->addRule($field, 'string', ['max' => $limits[$field]]); }
        foreach (array_intersect($fields, ['description', 'bio', 'body']) as $field) {
            $model->addRule($field, 'string', ['max' => 20000]);
            // MySQL TEXT is byte-bounded; emoji can consume four bytes per character.
            $model->addRule($field, function (string $attribute) use ($model): void {
                if (is_string($model->$attribute) && strlen($model->$attribute) > 65535) {
                    $model->addError($attribute, '内容的 UTF-8 编码不能超过 65535 字节。');
                }
            });
        }
        if ($model->hasAttribute('email')) { $model->addRule('email', 'email')->addRule('email', 'string', ['max' => 254]); }
        if ($kind === 'project') {
            $model->addRule('progress', 'required');
            $model->addRule('status', 'in', ['range' => ['planning', 'active', 'completed']]);
            $model->addRule('progress', 'integer', ['min' => 0, 'max' => 100]);
            $model->addRule('published', 'default', ['value' => 0]);
            $model->addRule('published', 'boolean');
            $model->addRule('started_on', 'date', ['format' => 'php:Y-m-d']);
            $model->addRule('member_ids', 'default', ['value' => []]);
            $model->addRule('member_ids', 'each', ['rule' => ['integer', 'min' => 1]]);
        }
        if ($kind === 'update') { $model->addRule('project_id', 'integer', ['min' => 1]); $model->addRule('published', 'default', ['value' => 0]); $model->addRule('published', 'boolean'); }
        if ($kind === 'member') { $model->addRule('sort_order', 'default', ['value' => 0]); $model->addRule('sort_order', 'integer', ['min' => 0, 'max' => 9999]); $model->addRule('avatar_color', 'default', ['value' => '#5965e0']); $model->addRule('avatar_color', 'match', ['pattern' => '/^#[0-9a-fA-F]{6}$/D']); }
        return $model;
    }
    public function attributeLabels(): array
    {
        return ['name' => '名称', 'title' => '标题 / 角色', 'tagline' => '一句话介绍', 'description' => '详细介绍', 'bio' => '个人简介', 'email' => '联系邮箱', 'location' => '所在地点', 'summary' => '项目摘要', 'body' => '正文', 'status' => '项目状态', 'published' => '公开发布', 'progress' => '进度（0–100）', 'started_on' => '开始日期', 'member_ids' => '项目成员', 'project_id' => '关联项目', 'avatar_color' => '头像颜色', 'sort_order' => '排序'];
    }
}
