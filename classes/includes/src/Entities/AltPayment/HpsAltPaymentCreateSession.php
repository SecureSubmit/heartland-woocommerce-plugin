<?php

class HpsAltPaymentCreateSession extends HpsAuthorization
{
    public $sessionId   = null;
    public $redirectUrl = null;

    public static function fromDict($rsp, $txnType, $returnType = 'HpsAltPaymentCreateSession')
    {
        $createSession = $rsp->Transaction->$txnType;

        $session = parent::fromDict($rsp, $txnType, $returnType);
        $pairs = self::nvpToArray($createSession->Session);

        $session->sessionId = isset($pairs['SessionId']) ? $pairs['SessionId'] : null;
        $session->redirectUrl = isset($pairs['RedirectUrl']) ? $pairs['RedirectUrl'] : null;

        return $session;
    }

    protected static function nvpToArray($pairs)
    {
        $array = array();
        foreach ($pairs->NameValuePair as $pair) {
            $array[(string)$pair->Name] = (string)$pair->Value;
        }
        return $array;
    }
}
