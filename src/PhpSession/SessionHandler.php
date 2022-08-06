<?php

namespace servd\AssetStorage\PhpSession;

class SessionHandler extends \yii\redis\Session
{
    public function readSession($id)
    {
        $data = parent::readSession($id);
        
        //Touch session to reset its ttl
        $this->redis->executeCommand('EXPIRE', [$this->calculateKey($id), $this->getTimeout()]);

        return $data;
    }
}
