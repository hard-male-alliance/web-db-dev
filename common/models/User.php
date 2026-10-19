<?php
/** Database-backed identity; disabled accounts lose authorization on every request. */
declare(strict_types=1);
namespace common\models;

use Yii;
use yii\db\Query;
use yii\web\IdentityInterface;

final class User extends \yii\base\BaseObject implements IdentityInterface
{
    public function __construct(public array $record) {}
    public static function findIdentity($id): ?self
    {
        $row = (new Query())->from('users')->where(['id' => $id, 'enabled' => 1])->one();
        return $row ? new self($row) : null;
    }
    public static function findIdentityByAccessToken($token, $type = null): ?self { return null; }
    public static function byUsername(string $username): ?self
    {
        $row = (new Query())->from('users')->where(['username' => $username, 'enabled' => 1])->one();
        return $row ? new self($row) : null;
    }
    public function getId(): int { return (int) $this->record['id']; }
    public function getAuthKey(): string { return hash('sha256', $this->record['password_hash']); }
    public function validateAuthKey($authKey): bool { return hash_equals($this->getAuthKey(), (string) $authKey); }
    public function isAdmin(): bool { return $this->record['role'] === 'admin'; }
    public function getUsername(): string { return $this->record['username']; }
    public function getMemberId(): ?int { return isset($this->record['member_id']) ? (int) $this->record['member_id'] : null; }
}
