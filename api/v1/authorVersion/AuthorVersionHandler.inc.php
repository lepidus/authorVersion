<?php

import('lib.pkp.classes.handler.APIHandler');

class AuthorVersionHandler extends APIHandler
{
    public function __construct()
    {
        $this->_handlerPath = 'authorVersion';
        $roles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR];
        $this->_endpoints = array(
            'POST' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/submitVersion',
                    'handler' => array($this, 'submitVersion'),
                    'roles' => $roles
                ),
            ),
        );
        parent::__construct();
    }

    public function submitVersion($slimRequest, $response, $args)
    {
        return $response->withStatus(200);
    }
}