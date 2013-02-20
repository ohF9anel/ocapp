<?php

class ErrorController extends Zend_Controller_Action
{

    public function errorAction()
    {
        $session = new Zend_Session_Namespace();
        $session->unsetAll();
        $message = '';
        
        $errors = $this->_getParam('error_handler');
        if (!$errors || !$errors instanceof ArrayObject) {
            $this->view->message = 'Unknown exception';
            return;
        } else {  
            switch ($errors->type) {
                case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
                case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
                case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                    $message = 'Page not found';
                    break;
                default:
                    $message = 'Application error';
                    break;
            }
        }
        
        $logMessage = '[' . date('D M d H:i:s Y') . '] [Unknown@' . $_SERVER['REMOTE_ADDR'] . '] ';
        $logMessage .= $message . ': ' . $errors->exception->getMessage() . "\r\n";
        $logMessage .= $errors->exception->getTraceAsString();
        
        $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/error.log';
        $fileHandle = fopen($file, 'a');
        fwrite($fileHandle, $logMessage . "\r\n");
        fclose($fileHandle);
    }
}