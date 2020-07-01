<?php

namespace servd\AssetStorage\models;

use craft\base\Model;

class Settings extends Model
{
    public $injectCors = false;

    public function rules()
    {
        return [
            //[['injectCors'], 'required'],
            // ...
        ];
    }
}
