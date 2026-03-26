<?php

namespace common\models\customer;

use Yii;
use common\models\BaseModel;
use yii\web\IdentityInterface;

/**
 * CustomerModel
 *
 * @property int $id
 * @property int|null $keycrm_customer_id
 * @property string $hash
 * @property string $email
 * @property string|null $phone
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $password_hash
 * @property string $auth_key
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 */
class CustomerModel extends BaseModel implements IdentityInterface
{
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 10;
    public const STATUS_BLOCKED = 9;

    public static function tableName(): string
    {
        return '{{%customer}}';
    }

    public static function find(): CustomerQuery
    {
        return new CustomerQuery(static::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['hash', 'email', 'auth_key', 'status'], 'required'],

            [['status', 'created_at', 'updated_at'], 'integer'],
            [['keycrm_customer_id'], 'integer'],

            [['email'], 'trim'],
            [['email'], 'filter', 'filter' => static fn($value) => mb_strtolower(trim((string)$value))],
            [['email'], 'email'],

            [['hash'], 'string', 'max' => 64],
            [['email', 'password_hash'], 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 30],
            [['first_name', 'last_name'], 'string', 'max' => 100],
            [['auth_key'], 'string', 'max' => 32],

            [['hash'], 'unique'],
            [['email'], 'unique'],
            [['keycrm_customer_id'], 'unique'],

            [['status'], 'in', 'range' => [
                self::STATUS_INACTIVE,
                self::STATUS_ACTIVE,
                self::STATUS_BLOCKED,
            ]],
        ]);
    }

    public function attributeLabels(): array
    {
        return [
            'id' => Yii::t('frontend', 'ID'),
            'keycrm_customer_id' => Yii::t('frontend', 'KeyCRM Customer ID'),
            'hash' => Yii::t('frontend', 'Hash'),
            'email' => Yii::t('frontend', 'Email'),
            'phone' => Yii::t('frontend', 'Phone'),
            'first_name' => Yii::t('frontend', 'First name'),
            'last_name' => Yii::t('frontend', 'Last name'),
            'password_hash' => Yii::t('frontend', 'Password hash'),
            'auth_key' => Yii::t('frontend', 'Auth key'),
            'status' => Yii::t('frontend', 'Status'),
            'created_at' => Yii::t('frontend', 'Created at'),
            'updated_at' => Yii::t('frontend', 'Updated at'),
        ];
    }

    public static function findIdentity($id): ?IdentityInterface
    {
        return static::find()
            ->active()
            ->andWhere(['id' => (int)$id])
            ->one();
    }

    public static function findIdentityByAccessToken($token, $type = null): ?IdentityInterface
    {
        return null;
    }

    /**
     * По умолчанию ищет только активного customer.
     * Для backend / служебных сценариев можно передать false.
     */
    public static function findByEmail(string $email, bool $isActiveOnly = true): ?self
    {
        $query = static::find()
            ->andWhere(['email' => mb_strtolower(trim($email))]);

        if ($isActiveOnly) {
            $query->active();
        }

        return $query->one();
    }

    /**
     * По умолчанию ищет только активного customer.
     * Для backend / служебных сценариев можно передать false.
     */
    public static function findByHash(string $hash, bool $isActiveOnly = true): ?self
    {
        $query = static::find()
            ->andWhere(['hash' => trim($hash)]);

        if ($isActiveOnly) {
            $query->active();
        }

        return $query->one();
    }

    public function getId(): int
    {
        return (int)$this->id;
    }

    public function getAuthKey(): string
    {
        return (string)$this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === (string)$authKey;
    }

    public function validatePassword(string $password): bool
    {
        if (empty($this->password_hash)) {
            return false;
        }

        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    public function getFullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ])));
    }

    public function getDisplayName(): string
    {
        return $this->getFullName() ?: $this->email;
    }

    public function isActive(): bool
    {
        return (int)$this->status === self::STATUS_ACTIVE;
    }

    public function isBlocked(): bool
    {
        return (int)$this->status === self::STATUS_BLOCKED;
    }

    public function isInactive(): bool
    {
        return (int)$this->status === self::STATUS_INACTIVE;
    }

    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        if ($this->email !== null) {
            $this->email = mb_strtolower(trim($this->email));
        }

        if ($this->isNewRecord) {
            if (empty($this->auth_key)) {
                $this->generateAuthKey();
            }

            if (empty($this->hash)) {
                $this->hash = $this->generateUniqueHash();
            }

            if ($this->status === null) {
                $this->status = self::STATUS_ACTIVE;
            }
        }

        return true;
    }

    protected function generateUniqueHash(int $length = 32): string
    {
        do {
            $hash = Yii::$app->security->generateRandomString($length);
        } while (static::find()->andWhere(['hash' => $hash])->exists());

        return $hash;
    }
}