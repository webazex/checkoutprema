<?php

declare(strict_types=1);

namespace common\models\checkout;

use common\models\BaseModel;

final class CheckoutCartStateInput extends BaseModel
{
    public ?string $sessionKey = null;
    public ?string $sourceType = null;

    public function rules(): array
    {
        return [
            [['sessionKey', 'sourceType'], 'required'],
            [['sessionKey', 'sourceType'], 'string', 'max' => 255],
        ];
    }
}