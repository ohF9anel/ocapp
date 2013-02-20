<?php

class IndexController extends Zend_Controller_Action
{

    public function init()
    {
        $session = new Zend_Session_Namespace();
        if (isset($session->function)) {
            switch ($session->function) {
                case 'Parent': $this->_helper->_redirector('index', 'Parent');
                    break;
                case 'Teacher': $this->_helper->_redirector('index', 'Teacher');
                    break;
                case 'Admin': $this->_helper->_redirector('index', 'Admin');
                    break;
            }
        }
        
        $this->view->controller = 'Index';
        $this->view->noSidebar = true;
    }

    public function indexAction()
    {
        
    }

}