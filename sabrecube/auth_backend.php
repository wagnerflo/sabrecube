<?php

class sabrecube_auth extends Sabre\DAV\Auth\Backend\AbstractBasic {
    public function __construct($rcmail) {
        $this->rc = $rcmail;
    }

    function validateUserPass($username, $password) {
        $auth = $this->rc->plugins->exec_hook('authenticate', array(
            'host'        => $this->rc->autoselect_host(),
            'user'        => $username,
            'pass'        => $password,
            'cookiecheck' => false,
            'valid'       => true,
        ));

        return (
            $auth['valid'] &&
            !$auth['abort'] &&
            $this->rc->login($auth['user'], $auth['pass'],
                             $auth['host'], $auth['cookiecheck'])
        );
    }
}

?>
