<?php

namespace servd\AssetStorage\PhpSession;

class SessionHandler extends \yii\redis\Session
{
    public function has($key): bool
    {
        // don't open the session if the headers were already sent
        if (!$this->getIsActive() && headers_sent()) {
            return isset($_SESSION[$key]);
        }

        return parent::has($key);
    }

    public function readSession($id)
    {
        $data = parent::readSession($id);
        
        //Touch session to reset its ttl
        $this->redis->executeCommand('EXPIRE', [$this->calculateKey($id), $this->getTimeout()]);

        return $data;
    }
}
