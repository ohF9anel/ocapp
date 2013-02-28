<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '../openid/extension/AttributeExchange.php';
require_once 'My/DateUtilities.php';

class AdminController extends Zend_Controller_Action
{
    protected $session = null;
    protected $staff = null;
    protected $activeConference = null;
    protected $conferenceAvailable = false;

    /**
     * This function is executed each time an AdminController action is processed, 
     * but the result of execution may deviate by visiting other actions.
     */
    public function init()
    {
        $this->session = new Zend_Session_Namespace();
        
        //check if user is visiting from another controller
        if ($this->session->authAs != 'Admin' && $this->session->authAs != null) {
            $this->session->unsetAll();
            $this->_helper->_redirector('index', 'Index');
        }

        //check if user is authenticated and authenticate if necessary
        if ($this->getRequest()->getActionName() != 'authenticate' && 
                $this->session->authAs != 'Admin') {
            $this->_helper->_redirector('authenticate', 'Admin');
        }
        
        //check if user is not visiting administer
        if ($this->getRequest()->getActionName() != 'administer') {
            unset($this->session->staffRights);
        }
        
        //check if user is working with titulars or teachers and save in session
        if ($this->getRequest()->getParam('tiid') != null) {
            unset($this->session->teacher);
        }
        if ($this->getRequest()->getParam('teid') != null) {
            $this->session->teacher = true;
        }
        
        //load authenticated user
        if ($this->session->id != null) {
            $this->staff = Application_Model_DbTable_Staff::getStaffById($this->session->id);
            $this->view->user = $this->staff->getFirstname() . ' ' . $this->staff->getName();
            
            //check if user is authorized
            $this->session->accessebilityLevel = Application_Model_DbTable_Rights::getAccessebilityLevelByStaffId($this->staff->getStaffId());
            if ($this->session->accessebilityLevel == 0) {
                $this->session->unsetAll();
                $this->_helper->_redirector('logout', 'Admin');
            }
            
            //load active conference
            $conferences = Application_Model_DbTable_Conferences::getAllConferences();
            if (sizeof($conferences) > 0) {
                $this->activeConference = ($this->getRequest()->getParam('conference') != null ? $this->getRequest()->getParam('conference') : ($this->session->activeConference != null ? $this->session->activeConference : $conferences[0]['conferenceId']));
                $this->session->activeConference = $this->activeConference;
                $this->view->activeConference = $this->activeConference;
                $this->view->conferencesAvailable = true;
                $this->conferenceAvailable = true;
                $this->view->conferences = $conferences;
            }
            else {
                $this->activeConference = null;
                unset($this->session->activeConference);
            }
            

            $this->view->displayLogout = true;
            $this->view->urlLogout = $this->view->url(array('controller' => 'Admin', 'action' => 'logout'));
            $this->view->rights = ($this->session->accessebilityLevel == 1 ? true : false);
        }
        
        
        $this->view->controller = 'Admin';
        $this->view->accessebilityLevel = $this->session->accessebilityLevel;
        $this->view->headLink()->prependStylesheet($this->view->baseUrl().'/css/admin.css');
    }

    
    
    /*
     * ACTIONS
     */
    
    /**
     * Administer action
     * Provides functionality to allow an administrator to assign 
     * administration rights to other staff members
     */
    public function administerAction()
    {
        //form submit
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            if (isset($formData['btnCancel'])) {    //cancel
                $this->_helper->redirector('index', 'Admin');
            }
            
            if (isset($formData['btnSave'])) {  //submit changes
                foreach ($this->session->staffRights as $staffRight) {
                    $accessebilityLevel = isset($formData['staff' . $staffRight->getStaffId()]) ? $formData['staff' . $staffRight->getStaffId()] : 0;
                    if ($staffRight->getAccessebilityLevel() != $accessebilityLevel) {  //when accessebilityLevel was changed -> save change
                        $staffRight->setAccessebilityLevel($accessebilityLevel);
                        $staffRight->save();
                        //log
                        $logMessage = '[' . date('D M d H:i:s Y') . '] [' 
                                . $this->staff->getFirstname() . ' ' 
                                . $this->staff->getName() . '@' 
                                . $_SERVER['REMOTE_ADDR'] 
                                . '] Changed accessebility level of : ' 
                                . $staffRight->getFirstname() . ' ' 
                                . $staffRight->getName() . '->' 
                                . $staffRight->getAccessebilityLevel();
                        $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/dataupdate.log';
                        $fileHandle = fopen($file, 'a');
                        fwrite($fileHandle, $logMessage . "\r\n");
                        fclose($fileHandle);
                    }
                }
            }
        }
        
        $this->session->staffRights = Application_Model_DbTable_Rights::getAllStaffRights();
        $this->view->staffRights = $this->session->staffRights;
        $this->view->administerActive = true;
    }
    
    
    /**
     * Appointment action
     * Provides functionality to display all staff members participating in the selected conference 
     */
    public function appointmentAction()
    {
        //determine which tab has to be active
        $activeTab = $this->session->teacher == true ? 'te' : 'ti'; 
        
        
        $titulars = array();
        foreach (Application_Model_DbTable_Conferences::getTitularsOfConference($this->activeConference) as $info) {
            $titulars[] = array($info['staffId'], $info['firstname'] . ' ' . $info['name'], $info['groupName'], $info['room'] != 0);
        }
        foreach (Application_Model_DbTable_Conferences::getResponsiblesOfConference($this->activeConference) as $info) {
            $titulars[] = array($info['staffId'], $info['firstname'] . ' ' . $info['name'], $info['function'], $info['room'] != 0);
        }
        
        $teachers = array();
        foreach (Application_Model_DbTable_Conferences::getTeachersOfConference($this->activeConference) as $info) {
            $teachers[] = array($info['staffId'], $info['firstname'] . ' ' . $info['name'], $info['room'] != 0);
        }
        
        
        $conference = Application_Model_DbTable_Conferences::getConferenceById($this->activeConference);
        $this->view->conferenceName = $conference->getName();
        
        
        $this->view->titulars = $titulars;
        $this->view->teachers = $teachers;
        $this->view->activeTab = $activeTab;
        $this->view->appointmentActive = true;
    }
    
    
    /**
     * AppointmentDelete action
     * Provides functionality to delete an appointment from the teacher's calendar (parent appointment or break)
     */
    public function appointmentDeleteAction()
    {
        //data check
        $appointmentId = $this->getRequest()->getParam('appointmentId');
        $tiid = $this->getRequest()->getParam('tiid', null);
        $teid = $this->getRequest()->getParam('teid', null);
        $conferenceDayId = $this->getRequest()->getParam('activeId', null);
        
        if ($appointmentId == null) {
            $this->_helper->redirector('appointment-edit', 'Admin', null, array(($tiid != null ? 'tiid' : 'teid') => $this->session->staffId, 'activeId' => $conferenceDayId));
        }
        
        $appointment = Application_Model_DbTable_Appointments::getAppointmentById($appointmentId);
        $appointment->deleteAppointment();
        
        if ($appointment->getParentId() != null) {
            $parent = Application_Model_DbTable_Parents::getParentByParentId($appointment->getParentId());


            //send confirmation
            if ($parent->getMail() != null) {
                try {
                    $appointments = $parent->getAppointments();
                    $text = $parent->getSalutation() . ',' . "\r\n\r\n";
                    $text .= 'Volgende gegevens werden geregistreerd:' . "\r\n";
                    foreach ($appointments as $appointment) {
                        $child = Application_Model_DbTable_Pupils::getPupilById($appointment->getPupilId());
                        $conferenceDay = Application_Model_DbTable_ConferenceDays::getConferenceDayById($appointment->getConferenceDayId());
                        $staff = Application_Model_DbTable_Staff::getStaffById($appointment->getStaffId());
                        if ($appointment->getCourseId() == null) {
                            $text .= 'Op ' . date('d-m-Y', strtotime($conferenceDay->getDate())) . ' om ' . date('H:i', strtotime($appointment->getAppointment())) . ': ' . $child->getFirstname() . ' ' . $child->getName() . ' - ' . $staff->getFirstname() . ' ' . $staff->getName() . "\r\n";
                        }
                        else {
                            $course = Application_Model_DbTable_Courses::getCourseById($appointment->getCourseId());
                            $text .= 'Op ' . date('d-m-Y', strtotime($conferenceDay->getDate())) . ' om ' . date('H:i', strtotime($appointment->getAppointment())) . ': ' . $child->getFirstname() . ' ' . $child->getName() . ' - ' . $staff->getFirstname() . ' ' . $staff->getName() . ' - ' . $course->getCourse() . "\r\n";
                        }
                    }
                    $text .= "\r\n\r\n" . 'Met vriendelijke groeten' . "\r\n" . 'DBZ';

                    $transport = new Zend_Mail_Transport_Smtp('uit.telenet.be');
                    Zend_Mail::setDefaultFrom('info@dbz.be', 'DBZ');
                    $mail = new Zend_Mail();
                    $mail->addTo($parent->getMail(), $parent->getSalutation());
                    $mail->setSubject('Bevestiging afspraken oudercontact');
                    $mail->setBodyText($text);
                    $mail->send($transport);
                }
                catch(Exception $e) {
                    $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/error.log';
                    $message = '[' . date('D M d H:i:s Y') . '] [' . $this->staff->getFirstname() . ' ' . $this->staff->getName() . '@' . $_SERVER['REMOTE_ADDR'] . '] ' . 'Error while sending confirmation mail to ' . $parent->getMail();
                    $fileHandle = fopen($file, 'a');
                    fwrite($fileHandle, $message . "\r\n");
                    fclose($fileHandle);
                }
                
            }
        }
                    
        $this->_helper->redirector('appointment-edit', 'Admin', null, array(($tiid != null ? 'tiid' : 'teid') => $this->session->staffId, 'activeId' => $conferenceDayId));
    }
    
    
    /**
     * AppointmentEdit action
     * Provides functionality to view the selected teacher's calendar and change appointment settings for 
     * this specified teacher
     */
    public function appointmentEditAction()
    {
        $conference = Application_Model_DbTable_Conferences::getConferenceById($this->activeConference);
        $activeId = $this->getRequest()->getParam('activeId', null);
        
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            if (isset($formData['btnCancel'])) {    //cancel
                $this->_helper->redirector('appointment', 'Admin');
            }
            if (isset($formData['btnSave'])) {  //save settings
                foreach ($formData['days'] as $day) {
                    $conferenceDay = Application_Model_DbTable_ConferenceDays::getConferenceDayById($day);
                    if ($conferenceDay->getIsPrimary() || array_key_exists('present' . $day, $formData)) {  //set as presentDay
                        //formcheck
                        $allOk = true;
                        $slots = ($this->getRequest()->getParam('tiid', null) != null ? $conference->getTimeLength1() : $conference->getTimeLength2()) * 60;
                        $room = (isset($formData['room' . $day]) ? $formData['room' . $day] : null);
                        $start = strtotime(isset($formData['start' . $day]) ? $formData['start' . $day] : $conferenceDay->getStartTime());
                        $end = strtotime(isset($formData['end' . $day]) ? $formData['end' . $day] : $conferenceDay->getEndTime());                        
                        
                        $res = strtotime($conferenceDay->getStartTime());
                        if ($start < strtotime('00:00:00') || $start > $end || ($start - $res) % $slots != 0) {
                            $allOk = false;
                            $this->view->startErr = true;
                        }
                        if ($end > strtotime('23:59:59') || $end < $start || ($end - $start) % $slots != 0) {
                            $allOk = false;
                            $this->view->endErr = true;
                        }
                        
                        if ($allOk) {   //proceed form
                            if ($room > 0) { //save roomallocation
                                $roomAllocation = new Application_Model_DbTable_RoomAllocations($room, $this->session->staffId, $day);
                                $roomAllocation->save();
                            } else {    //delete roomallocation
                                $roomAllocation = new Application_Model_DbTable_RoomAllocations(null, $this->session->staffId, $day);
                                $roomAllocation->deleteRoomAllocation();
                            }
                            $presentDay = new Application_Model_DbTable_PresentDays($day, $this->session->staffId, Date('H:i:s', $start), Date('H:i:s', $end));
                            $presentDay->save();
                            $this->view->saved = true;
                        }
                    }
                    else {      //unset as presentDay (delete necessary data)
                        $roomAllocation = new Application_Model_DbTable_RoomAllocations(null, $this->session->staffId, $day);
                        $roomAllocation->deleteRoomAllocation();
                        $presentDay = new Application_Model_DbTable_PresentDays($day, $this->session->staffId, null, null);
                        $presentDay->deletePresentDay();
                        Application_Model_DbTable_Appointments::deleteAppointments($day, $this->session->staffId);
                        $this->view->saved = true;
                    }
                }
            }
        }

        //load calendar items
        $tiid = $this->getRequest()->getParam('tiid', null);
        $teid = $this->getRequest()->getParam('teid', null);
        $status = $this->getRequest()->getParam('status', null);
        $days = array();
        $staff = null;

        if ($tiid != null) {     //titular conference
            $staff = Application_Model_DbTable_Staff::getStaffById($tiid);
            $days = $staff->getTeacherAppointments($this->activeConference, 'type1');
            $this->view->titular = true;
            $this->view->timeslotLength = $conference->getTimeLength1() * 60;
            $this->view->settingsEnabled = (strtotime($conference->getStartSubscription1()) > time() ? true : false);
            $this->session->staffId = $tiid;
        }
        else if ($teid != null) {    //teacher conference
            $staff = Application_Model_DbTable_Staff::getStaffById($teid);
            $days = $staff->getTeacherAppointments($this->activeConference, 'type2');
            $this->view->timeslotLength = $conference->getTimeLength2() * 60;
            $this->view->settingsEnabled = (strtotime($conference->getStartSubscription2()) > time() ? true : false);
            $this->session->staffId = $teid;
        }
        else {  //no conference selected
            $this->_helper->redirector('appointment', 'Admin');
        }

        
        $this->view->status = $status;
        $this->view->days = $days;
        $this->view->name = $staff->getFirstname() . ' ' . $staff->getName();
        $this->view->staffId = $this->session->staffId;
        $this->view->activeId = ($activeId != null ? $activeId : $days[0]->getDayId());
        $this->view->appointmentActive = true;
        $this->view->headLink()->prependStylesheet($this->view->baseUrl().'/css/print.css', 'print');
    }

    
    /**
     * ApointmentNem action
     * Provides functionality to add an appointment to selected teacher's calendar (parent appointment or break)
     * 
     */
    public function appointmentNewAction()
    {
        $staffId = null; $conferenceDayId = null; $tiid = null; $teid = null; $time = null; $break = null;
        $candidates = null;
        
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            $titular = (isset($formData['type']) && $formData['type'] == 'type2' ? false : true);
            
            if (isset($formData['btnCancel'])) {    //cancel
                $this->_helper->redirector('appointment-edit', 'Admin', null, array(($titular ? 'tiid' : 'teid') => $this->session->staffId, 'activeId' => $this->session->activeId));
            }
            if (isset($formData['btnSave'])) {  //create appointment
                $type = (isset($formData['type']) ? strtolower($formData['type']) : null);
                $conferenceDayId = (isset($formData['dayId']) ? $formData['dayId'] : null);
                $time = (isset($formData['time']) ? $formData['time'] : null);
                $candidateInfo = explode('p', (isset($formData['candidate']) ? $formData['candidate'] : null));
                $pupilId = null; $parentId = null; $courseId = null;
                $staffId = $this->session->staffId;
                if (sizeof($candidateInfo) >= 2) {
                    $pupilId = $candidateInfo[0];
                    $parentId = $candidateInfo[1];
                    if (sizeof($candidateInfo) > 2) {
                        $courseId = $candidateInfo[2];
                    }
                }

                //form check
                $allOk = true;
                if ($conferenceDayId == null || $time == null ) {
                    $this->view->generalErr = true;
                    $allOk = false;
                }
                if ($pupilId == null || $parentId == null) {
                    $this->view->candidateErr = true;
                    $allOk = false;
                }
                
                if ($allOk) {
                    $appointment = null;
                    
                    if ($type == 'type2') { //teacher appointment
                        $appointment = new Application_Model_DbTable_Appointments(null, $staffId, $pupilId, $parentId, $courseId, $time, $conferenceDayId, $this->activeConference);
                    }
                    else {  //titular appointment
                        $appointment = new Application_Model_DbTable_Appointments(null, $staffId, $pupilId, $parentId, null, $time, $conferenceDayId, $this->activeConference);
                    }
                    
                    $status = "succes";
                    try {    
                        $appointment->save();
                    }
                    catch(Exception $e) {
                        switch($e->getCode()) {
                            case 133:
                                $status = "error";
                                break;
                            case 134:
                                $status = "errorP";
                                break;
                        }
                    }
                    
                    //sending confimation
                    $parent = Application_Model_DbTable_Parents::getParentByParentId($appointment->getParentId());
                    if ($parent->getMail() != null) {
                        try {
                            $appointments = $parent->getAppointments();
                            $text = $parent->getSalutation() . ',' . "\r\n\r\n";
                            $text .= 'Volgende gegevens werden geregistreerd:' . "\r\n";
                            foreach ($appointments as $appointment) {
                                $child = Application_Model_DbTable_Pupils::getPupilById($appointment->getPupilId());
                                $conferenceDay = Application_Model_DbTable_ConferenceDays::getConferenceDayById($appointment->getConferenceDayId());
                                $staff = Application_Model_DbTable_Staff::getStaffById($appointment->getStaffId());
                                if ($appointment->getCourseId() == null) {
                                    $text .= 'Op ' . date('d-m-Y', strtotime($conferenceDay->getDate())) . ' om ' . date('H:i', strtotime($appointment->getAppointment())) . ': ' . $child->getFirstname() . ' ' . $child->getName() . ' - ' . $staff->getFirstname() . ' ' . $staff->getName() . "\r\n";
                                }
                                else {
                                    $course = Application_Model_DbTable_Courses::getCourseById($appointment->getCourseId());
                                    $text .= 'Op ' . date('d-m-Y', strtotime($conferenceDay->getDate())) . ' om ' . date('H:i', strtotime($appointment->getAppointment())) . ': ' . $child->getFirstname() . ' ' . $child->getName() . ' - ' . $staff->getFirstname() . ' ' . $staff->getName() . ' - ' . $course->getCourse() . "\r\n";
                                }
                            }
                            $text .= "\r\n\r\n" . 'Met vriendelijke groeten' . "\r\n" . 'DBZ';

                            $transport = new Zend_Mail_Transport_Smtp('uit.telenet.be');
                            Zend_Mail::setDefaultFrom('info@dbz.be', 'DBZ');
                            $mail = new Zend_Mail();
                            $mail->addTo($parent->getMail(), $parent->getSalutation());
                            $mail->setSubject('Bevestiging afspraken oudercontact');
                            $mail->setBodyText($text);
                            $mail->send($transport);
                        }
                        catch (Exception $e) {
                            $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/error.log';
                            $message = '[' . date('D M d H:i:s Y') . '] [' . $this->staff->getFirstname() . ' ' . $this->staff->getName() . '@' . $_SERVER['REMOTE_ADDR'] . '] ' . 'Error while sending confirmation mail to ' . $parent->getMail();
                            $fileHandle = fopen($file, 'a');
                            fwrite($fileHandle, $message . "\r\n");
                            fclose($fileHandle);
                        }
                    }
            
                    $this->_helper->redirector('appointment-edit', 'Admin', null, array(($titular ? 'tiid' : 'teid') => $this->session->staffId, 'activeId' => $conferenceDayId, 'status' => $status));
                }
            }
        }
        else {
            //load get data
            $staffId = $this->getRequest()->getParam('staffId');
            $conferenceDayId = $this->getRequest()->getParam('dayId');
            $this->session->activeId = $conferenceDayId;
            $tiid = $this->getRequest()->getParam('tiid');
            $teid = $this->getRequest()->getParam('teid');
            $time = $this->getRequest()->getParam('time');
            $break = $this->getRequest()->getParam('break');
            $titular = ($tiid != null);
        }
        
        if ($time == null || $conferenceDayId == null || $time == null || $staffId == null) {   //not all required information is provided
            $this->_helper->redirector('appointment-edit', 'Admin', null, ($tiid != null ? array('tiid' => $tiid) : array('teid' => $teid)));
        }
        
        if ($break != null) {   //add break to selected teacher's calendar
            $appointment = new Application_Model_DbTable_Appointments(null, $staffId, null, null, null, $time, $conferenceDayId, $this->activeConference);
            $status = "succes";
            try {    
                $appointment->save();
            }
            catch(Exception $e) {
                switch($e->getCode()) {
                    case 133:
                        $status = "error";
                        break;
                }
            }
            
            $this->_helper->redirector('appointment-edit', 'Admin', null, array(($tiid != null ? 'tiid' : 'teid') => $this->session->staffId, 'activeId' => $conferenceDayId, 'status' => $status));
        }
        
        //load extra information
        $day = Application_Model_DbTable_ConferenceDays::getConferenceDayById($conferenceDayId);
        $staff = Application_Model_DbTable_Staff::getStaffById($staffId);
        
        
        $this->view->titular = $titular;
        $this->view->name = $staff->getFirstname() . ' ' . $staff->getName();
        $this->view->dayId = $conferenceDayId;
        $this->view->day = $day->getDate();
        $this->view->time = $time;
        $candidates = $staff->getCandidates($this->activeConference, $titular ? 'type1' : 'type2');
        $this->view->candidates = $candidates;
        $this->view->staffId = $this->session->staffId;
        $this->view->appointmentActive = true;
    }
    
    
    /**
     * Authenticate action
     * Provides authentication to administrators using their Google accounts 
     */
    public function authenticateAction()
    {
        // get an instace of Zend_Auth
        $auth = Zend_Auth::getInstance();

        // $openid_mode will be set after first query to the openid provider
        $openid_mode = $this->getRequest()->getParam('openid_mode', null);

        //check if it's Google who sends an answer or if it's a user who opens this page 
        if ($openid_mode && !$this->getRequest()->isPost()) {     //it's Google
            $adapter = $this->_getOpenIdAdapter(null);

            // specify which information to retreive from provider (no garantee that the provider will tell you this information)
            $toFetch = array('email' => 1);
            $ext = $this->_getOpenIdExt('ax', $toFetch);

            if ($ext) {
                $ext->parseResponse($_GET);
                $adapter->setExtensions($ext);
            }

            $result = $auth->authenticate($adapter);

            if ($result->isValid()) {
                $allOk = true;
                $staff = null;
                $rights = null;
                $properties = $ext->getProperties();
                $openId = $properties['email'];
                try {
                    $staff = Application_Model_DbTable_Staff::Authenticate($openId);
                    $rights = Application_Model_DbTable_Rights::Authorize($staff->getStaffId());
                }
                catch (Exception $e) {
                    switch ($e->getCode()) {
                        case 101: $this->view->userErr = true;
                            break;
                        case 126: $this->view->rightsErr = true;
                            break;
                    }
                    
                    $allOk = false;
                }
                
                if ($allOk) {
                    $this->session->authAs = 'Admin';
                    $this->session->id = $staff->getStaffId();
                    $this->session->accessebilityLevel = $rights->getAccessebilityLevel();
                    $this->_helper->_redirector('index', 'Admin');
                }
            } else {
                $this->view->errFail = true;
            }
        }
        else {      //it's a user
            if ($this->getRequest()->isPost()) {
                $formData = $this->getRequest()->getPost();
                
                if (isset($formData['btnCancel'])) {
                    $this->session->unsetAll();
                    $this->_helper->redirector('index', 'Index');
                }
                else if (isset($formData['btnLogin'])) {
                    $adapter = $this->_getOpenIdAdapter('https://www.google.com/accounts/o8/id');

                    // specify which information to retreive from provider (no garantee that the provider will tell you this information)
                    $toFetch = array('email' => 1);
                    $ext = $this->_getOpenIdExt('ax', $toFetch);

                    //set extension and redirect user to provider for sign on
                    $adapter->setExtensions($ext);
                    $result = $auth->authenticate($adapter);

                    // the following is only executed when a failure occurs
                    $this->view->errFail = true;
                }
            }
        }
        
        $this->view->noSidebar = true;
    }
    
    
    /**
     * DataUpdate action
     * Provides functionality to update data in database  
     */
    public function dataUpdateAction()
    {
        $update = $this->getRequest()->getParam('update', 'default');
        $succes = $this->getRequest()->getParam('succes', 'false');
        
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            if (isset($formData['btnCancel'])) {    //cancel
                $this->_helper->redirector('data-update', 'Admin');
            }
            if (isset($formData['btnDel'])) {   //delete data
                $logMessage = '[' . date('D M d H:i:s Y') . '] [' . $this->staff->getFirstname() . ' ' . $this->staff->getName() . '@' . $_SERVER['REMOTE_ADDR'] . '] ';
                $succes = 'true';
                
                switch ($update) {
                    case 1:     //delete staff
                        try {
                            Application_Model_DbTable_RoomAllocations::deleteAll();
                            Application_Model_DbTable_Appointments::deleteAll();
                            Application_Model_DbTable_PresentDays::deleteAll();
                            Application_Model_DbTable_Childrelations::deleteAll();
                            Application_Model_DbTable_Pupils::deleteAll();
                            Application_Model_DbTable_GroupsHaveCourses::deleteAll();
                            Application_Model_DbTable_Courses::deleteAll();
                            Application_Model_DbTable_Groups::deleteAll();
                            Application_Model_DbTable_Responsibles::deleteAll();
                            Application_Model_DbTable_Rights::deleteAllExcept($this->staff->getStaffId());
                            Application_Model_DbTable_Staff::deleteAllExcept($this->staff->getStaffId());
                        }
                        catch (Exception $e){
                            $succes = 'error';
                            $message = 'ERROR while deleting staff data';
                        }
                        break;
                    case 2:     //delete pupils
                        try {
                            Application_Model_DbTable_Appointments::deleteAll();
                            Application_Model_DbTable_Childrelations::deleteAll();
                            Application_Model_DbTable_Pupils::deleteAll();
                        }
                        catch (Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while deleting pupil data';
                        }
                        break;
                    case 3:     //delete parents
                        try {
                            Application_Model_DbTable_Appointments::deleteAll();
                            Application_Model_DbTable_Childrelations::deleteAll();
                            Application_Model_DbTable_Parents::deleteAll();
                        }
                        catch (Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while deleting parent data';
                        }
                        break;
                    case 4:     //delete groups
                        try {
                            Application_Model_DbTable_Participants::deleteAll();
                            Application_Model_DbTable_Childrelations::deleteAll();
                            Application_Model_DbTable_Pupils::deleteAll();
                            Application_Model_DbTable_GroupsHaveCourses::deleteAll();
                            Application_Model_DbTable_Groups::deleteAll();
                        }
                        catch (Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while deleting group data';
                        }
                        break;
                    case 5:     //delete courses
                        try {
                            Application_Model_DbTable_Appointments::deleteAll();
                            Application_Model_DbTable_GroupsHaveCourses::deleteAll();
                            Application_Model_DbTable_Courses::deleteAll();
                        }
                        catch (Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while deleting course data';
                        }
                        break;
                    case 6:
                        try {   //delete rooms
                            Application_Model_DbTable_RoomAllocations::deleteAll();
                            Application_Model_DbTable_Rooms::deleteAll();
                        }
                        catch (Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while deleting rooms';
                        }
                        break;
                    case 7:     //delete parent-child relations
                        try {
                            Application_Model_DbTable_Childrelations::deleteAll();
                        }
                        catch (Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while deleting parent-child relations'; 
                        }
                        break;
                    case 8:     //delete course allocations
                        try {
                            Application_Model_DbTable_GroupsHaveCourses::deleteAll();
                        }
                        catch(Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while deleting course allocations';
                        }
                        break;
                    case 9:     //delete responsibles
                        try {
                            Application_Model_DbTable_Responsibles::deleteAll();
                        }
                        catch (Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while deleting responsibles';
                        }
                        break;
                }
                
                $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/dataupdate.log';
                if ($succes == 'error') {
                    $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/error.log';
                }
                
                $fileHandle = fopen($file, 'a');
                fwrite($fileHandle, $logMessage . "\r\n");
                fclose($fileHandle);
                $this->_helper->redirector('data-update', 'Admin', null, array('succes' => $succes));
            }
            else if (isset($formData['btnSave'])) {  //submit update
                $logMessage = '[' . date('D M d H:i:s Y') . '] [' . $this->staff->getFirstname() . ' ' . $this->staff->getName() . '@' . $_SERVER['REMOTE_ADDR'] . '] ';
                $succes = 'true';
                
                switch ($update) {
                    case 1:     //update staff
                        $staff = array();

                        if (($handle = fopen($_FILES['staff']['tmp_name'], 'r')) !== FALSE) {
                            while (($data = fgetcsv($handle, 100, ';')) !== FALSE) {
                                if ($data[0][0] == '#') {
                                    continue;
                                }
                                $staff[] = array('name' => $data[0], 'firstname' => $data[1], 'openId' => $data[2]);
                            }
                        }

                        $message = 'Update of staff data';
                        try {
                            Application_Model_DbTable_Staff::updateStaff($staff);
                        } 
                        catch(Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while updating staff data: ' . $e->getMessage();
                        }
                        $logMessage .= $message;
                        break;
                    case 2:     //update pupils
                        $pupils = array();

                        if (($handle = fopen($_FILES['pupils']['tmp_name'], 'r')) !== FALSE) {
                            while (($data = fgetcsv($handle, 100, ';')) !== FALSE) {
                                if ($data[0][0] == '#') {
                                    continue;
                                }
                                $pupils[] = array('uuid' => $data[0], 'name' => $data[1], 'firstname' => $data[2], 'groupName' => $data[3]);
                            }
                        }

                        $message = 'Update of pupil data';
                        try {
                            Application_Model_DbTable_Pupils::updatePupils($pupils);
                        } 
                        catch(Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while updating pupil data: ' . $e->getMessage();
                        }
                        $logMessage .= $message;
                        break;
                    case 3:     //update parents
                        $parents = array();
                        
                        if (($handle = fopen($_FILES['parents']['tmp_name'], 'r')) !== FALSE) {
                            while (($data = fgetcsv($handle, 100, ';')) !== FALSE) {
                                if ($data[0][0] == '#') {
                                    continue;
                                }
                                $parents[] = array('salutation' => $data[0], 'login' => $data[1], 'password' => $data[2]);
                            }
                        }

                        $message = 'Update of parent data';
                        try {
                            Application_Model_DbTable_Parents::updateParents($parents);
                        } 
                        catch(Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while updating parent data: ' . $e->getMessage();
                        }
                        $logMessage .= $message;
                        break;
                    case 4:     //update groups
                        $groups = array();
                        
                        if (($handle = fopen($_FILES['groups']['tmp_name'], 'r')) !== FALSE) {
                            while (($data = fgetcsv($handle, 100, ';')) !== FALSE) {
                                if ($data[0][0] == '#') {
                                    continue;
                                }
                                $remark = array_key_exists(3, $data) ? $data[3] : null;
                                $groups[] = array('yearId' => $data[0], 'name' => $data[1], 'titular' => $data[2], 'remark' => $remark);
                            }
                        }
                        
                        $message = 'Update of group data';
                        try {
                            Application_Model_DbTable_Groups::updateGroups($groups);
                        }
                        catch (Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while updating group data: ' . $e->getMessage();
                        }
                        $logMessage .= $message;
                        break;
                    case 5:     //update courses
                        $courses = array();
                        
                        if (($handle = fopen($_FILES['courses']['tmp_name'], 'r')) !== FALSE) {
                            while (($data = fgetcsv($handle, 100, ';')) !== FALSE) {
                                if ($data[0][0] == '#') {
                                    continue;
                                }
                                $remark = array_key_exists(3, $data) ? $data[3] : null;
                                $courses[] = array('teacher' => $data[0], 'course' => $data[1], 'uuid' => $data[2], 'remark' => $remark);
                            }
                        }

                        $message = 'Update of course data';
                        try {
                            Application_Model_DbTable_Courses::updateCourses($courses);
                        } 
                        catch(Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while updating course data: ' . $e->getMessage();
                        }
                        $logMessage .= $message;
                        break;
                    case 6:     //save rooms
                        $rooms = array();

                        if (($handle = fopen($_FILES['rooms']['tmp_name'], 'r')) !== FALSE) {
                            while (($data = fgetcsv($handle, 100, ';')) !== FALSE) {
                                if ($data[0][0] == '#') {
                                    continue;
                                }
                                $rooms[] = $data[0];
                            }
                        }

                        $message = 'Update of rooms';
                        try {
                            Application_Model_DbTable_Rooms::UpdateRooms($rooms);
                        } 
                        catch(Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while updating rooms: ' . $e->getMessage();
                        }
                        $logMessage .= $message;
                        break;
                    case 7:     //save parent-child relations
                        $childrelations = array();
                        
                        if (($handle = fopen($_FILES['relations']['tmp_name'], 'r')) !== FALSE) {
                            while (($data = fgetcsv($handle, 100, ';')) !== FALSE) {
                                if ($data[0][0] == '#') {
                                    continue;
                                }
                                $childrelations[] = array('login' => $data[0], 'uuid' => $data[1]);
                            }
                        }

                        $message = 'Update of parent-child relations';
                        try {
                            Application_Model_DbTable_Childrelations::updateChildrelations($childrelations);
                        } 
                        catch(Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while updating parent-child relations: ' . $e->getMessage();
                        }
                        $logMessage .= $message;
                        break;
                    case 8:     //save course allocations
                        $allocations = array();
                        
                        if (($handle = fopen($_FILES['allocations']['tmp_name'], 'r')) !== FALSE) {
                            while (($data = fgetcsv($handle, 100, ';')) !== FALSE) {
                                if ($data[0][0] == '#') {
                                    continue;
                                }
                                $allocations[] = array('name' => $data[0], 'uuid' => $data[1]);
                            }
                        }

                        $message = 'Update of course allocations';
                        try {
                            Application_Model_DbTable_GroupsHaveCourses::updateGroupsHaveCourses($allocations);
                        } 
                        catch(Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while updating course allocations: ' . $e->getMessage();
                        }
                        $logMessage .= $message;
                        break;
                    case 9:     //update responsibles
                        $responsibles = array();
                        
                        if (($handle = fopen($_FILES['responsibles']['tmp_name'], 'r')) !== FALSE) {
                            while (($data = fgetcsv($handle, 100, ';')) !== FALSE) {
                                if ($data[0][0] == '#') {
                                    continue;
                                }
                                $remark = array_key_exists(3, $data) ? $data[3] : null;
                                $responsibles[] = array('responsible' => $data[0], 'yearId' => $data[1], 'function' => $data[2], 'remark' => $remark);
                            }
                        }

                        $message = 'Update of responsibles';
                        try {
                            Application_Model_DbTable_Responsibles::updateResponsibles($responsibles);
                        } 
                        catch(Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while updating responsibles: ' . $e->getMessage();
                        }
                        $logMessage .= $message; 
                        break;
                    case 10:    //delete all data
                        $message = 'Delete all data from application';
                        try {
                            Application_Model_DbTable_RoomAllocations::deleteAll();
                            Application_Model_DbTable_Rooms::deleteAll();
                            Application_Model_DbTable_Appointments::deleteAll();
                            Application_Model_DbTable_Participants::deleteAll();
                            Application_Model_DbTable_PresentDays::deleteAll();
                            Application_Model_DbTable_ConferenceDays::deleteAll();
                            Application_Model_DbTable_Conferences::deleteAll();
                            Application_Model_DbTable_Childrelations::deleteAll();
                            Application_Model_DbTable_Parents::deleteAll();
                            Application_Model_DbTable_Pupils::deleteAll();
                            Application_Model_DbTable_GroupsHaveCourses::deleteAll();
                            Application_Model_DbTable_Courses::deleteAll();
                            Application_Model_DbTable_Groups::deleteAll();
                            Application_Model_DbTable_Responsibles::deleteAll();
                            Application_Model_DbTable_Rights::deleteAllExcept($this->staff->getStaffId());
                            Application_Model_DbTable_Staff::deleteAllExcept($this->staff->getStaffId());
                        }
                        catch (Exception $e) {
                            $succes = 'error';
                            $message = 'ERROR while deleting all data from application';
                        }
                        $logMessage .= $message;
                        break;
                }
                $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/dataupdate.log';
                if ($succes == 'error') {
                    $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/error.log';
                }
                
                $fileHandle = fopen($file, 'a');
                fwrite($fileHandle, $logMessage . "\r\n");
                fclose($fileHandle);
                $this->_helper->redirector('data-update', 'Admin', null, array('succes' => $succes));
            }
        }
        else {
            $this->view->amount1 = Application_Model_DbTable_Staff::countEntries();
            $this->view->amount2 = Application_Model_DbTable_Pupils::countEntries();
            $this->view->amount3 = Application_Model_DbTable_Parents::countEntries();
            $this->view->amount4 = Application_Model_DbTable_Groups::countEntries();
            $this->view->amount5 = Application_Model_DbTable_Courses::countEntries();
            $this->view->amount6 = Application_Model_DbTable_Rooms::countEntries();
            $this->view->amount7 = Application_Model_DbTable_Childrelations::countEntries();
            $this->view->amount8 = Application_Model_DbTable_GroupsHaveCourses::countEntries();
            $this->view->amount9 = Application_Model_DbTable_Responsibles::countEntries();
        }
        
        switch ($update) {
            case 1:     //update staff
                $this->view->updateAction = 'Update personeelslijst';
                $this->view->process = true;
                break;
            case 2:     //update pupils
                $this->view->updateAction = 'Update leerlingenlijst';
                $this->view->process = true;
                break;
            case 3:     //update parents
                $this->view->updateAction = 'Update ouderlijst';
                $this->view->process = true;
                break;
            case 4:     //update groups
                $this->view->updateAction = 'Update klassenlijst';
                $this->view->process = true;
                break;
            case 5:     //update courses
                $this->view->updateAction = 'Update vakkenlijst';
                $this->view->process = true;
                break;
            case 6:     //update rooms
                $this->view->updateAction = 'Update lokalenlijst';
                $this->view->process = true;
                break;
            case 7:     //update childrelations
                $this->view->updateAction = 'Update ouder-kindrelaties';
                $this->view->process = true;
                break;
            case 8:     //update groups_have_courses
                $this->view->updateAction = 'Update lestoewijzingen';
                $this->view->process = true;
                break;
            case 9:     //update responsibles
                $this->view->updateAction = 'Update begeleiderslijst';
                $this->view->process = true;
                break;
            case 10:    //delete all data
                $this->view->updateAction = 'Verwijder alle data';
                $this->view->process = true;
                break;
        }
        
        $this->view->succes = $succes;
        $this->view->update = $update;
        $this->view->updateActive = true;
    }
    
    
    /**
     * Index action
     * Provides functionality to change general settings of selected conference
     */
    public function indexAction()
    {
        if ($this->conferenceAvailable) {
            $conference = Application_Model_DbTable_Conferences::getConferenceById($this->activeConference);
            $days = Application_Model_DbTable_ConferenceDays::getConferenceDaysOfConference($conference->getConferenceId());

            $days1 = array(); $days2 = array();
            foreach ($days as $day) {
                if (strtolower($day->getType()) == 'type1') {
                    $days1[] = $day;
                } else {
                    $days2[] = $day;
                }
            }

            if ($this->getRequest()->isPost()) {
                $formData = $this->getRequest()->getPost();
                if (isset($formData['btnDelete'])) {    //delete conference
                    Application_Model_DbTable_RoomAllocations::deleteConference($conference->getConferenceId());
                    Application_Model_DbTable_Appointments::deleteConference($conference->getConferenceId());
                    Application_Model_DbTable_Participants::deleteConference($conference->getConferenceId());
                    Application_Model_DbTable_PresentDays::deleteConference($conference->getConferenceId());
                    Application_Model_DbTable_ConferenceDays::deleteConference($conference->getConferenceId());
                    $conference->deleteConference();
                    
                    //log
                    $logMessage = '[' . date('D M d H:i:s Y') . '] [' . $this->staff->getFirstname() . ' ' . $this->staff->getName() . '@' . $_SERVER['REMOTE_ADDR'] . '] Deleted conference: ' . $conference->getName();
                    $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/dataupdate.log';
                    $fileHandle = fopen($file, 'a');
                    fwrite($fileHandle, $logMessage . "\r\n");
                    fclose($fileHandle);
                    
                    unset($this->session->activeConference);
                    $this->_helper->_redirector('index', 'Admin');
                }
                else if (isset($formData['btnSave'])) { //save changes
                    $allOk = true;

                    $dataPattern = '/^[0-9][0-9]-[0-9][0-9]-[0-9][0-9][0-9][0-9]$/';
                    $timePattern = '/^[0-9][0-9]:[0-9][0-9]$/';
                    $name = (isset($formData['txtName']) ? $formData['txtName'] : null);
                    $start1 = (isset($formData['txtStart1']) ? $formData['txtStart1'] : null);
                    $start2 = (isset($formData['txtStart2']) ? $formData['txtStart2'] : null);
                    $deadline1 = (isset($formData['txtDeadline1']) ? $formData['txtDeadline1'] : null);
                    $deadline2 = (isset($formData['txtDeadline2']) ? $formData['txtDeadline2'] : null);
                    $timeslotLength1 = (int) (isset($formData['txtTimeslotLength1']) ? $formData['txtTimeslotLength1'] : null);
                    $timeslotLength2 = (int) (isset($formData['txtTimeslotLength2']) ? $formData['txtTimeslotLength2'] : null);
                    $meantime = (int) (isset($formData['txtMeantime']) ? $formData['txtMeantime'] : null);

                    $start1Old = $conference->getStartSubscription1();
                    $start2Old = $conference->getStartSubscription2();
                    if (strtotime($start1Old) < time()) {
                        $timeslotLength1 = $conference->getTimeLength1();
                    }
                    if (strtotime($start2Old) < time()) {
                        $timeslotLength2 = $conference->getTimeLength2();
                    }
                    
                    //form check
                    if ($name == null || $name == '') {
                        $allOk = false;
                        $this->view->errName = true;
                    } else {
                        $conference->setName($name);
                    }
                    if ($start1 == null || preg_match($dataPattern, $start1) == 0) {
                        $allOk = false;
                        $this->view->errStart1 = true;
                    } else {
                        $conference->setStartSubscription1($start1);
                        $start1Old = (strtotime($start1Old) < time() ? $start1Old : $start1);
                    }
                    if ($start2 == null || preg_match($dataPattern, $start2) == 0) {
                        $allOk = false;
                        $this->view->errStart2 = true;
                    } else {
                        $conference->setStartSubscription2($start2);
                        $start2Old = (strtotime($start2Old) < time() ? $start2Old : $start2);
                    }
                    if ($deadline1 == null || preg_match($dataPattern, $deadline1) == 0) {
                        $allOk = false;
                        $this->view->errDeadline1 = true;
                    } else {
                        $conference->setDeadlineSubscription1($deadline1);
                    }
                    if ($deadline2 == null || preg_match($dataPattern, $deadline2) == 0) {
                        $allOk = false;
                        $this->view->errDeadline2 = true;
                    } else {
                        $conference->setDeadlineSubscription2($deadline2);
                    }
                    if ($timeslotLength1 == null || $timeslotLength1 <= 0) {
                        $allOk = false;
                        $this->view->errTimeslotLength1 = true;
                    } else {
                        $conference->setTimeLength1($timeslotLength1);
                    }
                    if ($timeslotLength2 == null || $timeslotLength2 <= 0) {
                        $allOk = false;
                        $this->view->errTimeslotLength2 = true;
                    } else {
                        $conference->setTimeLength2($timeslotLength2);
                    }
                    if ($meantime == null || $meantime <= 0) {
                        $allOk = false;
                        $this->view->errMeantime = true;
                    } else {
                        $conference->setMinimalMeantime($meantime);
                    }

                    if (strtotime($start1Old) > time()) {
                        for ($i = 0; $i < sizeof($days1); $i++) {   //loop through titular conference days
                            $day1 = $days1[$i];
                            $day = isset($formData['txtDay' . $day1->getConferenceDayId()]) ? $formData['txtDay' . $day1->getConferenceDayId()] : '';
                            $obligated = isset($formData['chbObligated' . $day1->getConferenceDayId()]) ? true : false;
                            $start = isset($formData['txtStarttime' . $day1->getConferenceDayId()]) ? $formData['txtStarttime' . $day1->getConferenceDayId()] : '';
                            $end = isset($formData['txtEndtime' . $day1->getConferenceDayId()]) ? $formData['txtEndtime' . $day1->getConferenceDayId()] : '';

                            //formcheck
                            if ($day == null || preg_match($dataPattern, $day) == 0) {
                                $allOk = false;
                                $this->view->errDay = true;
                            } else {
                                $day1->setDate($day);
                            }
                            if ($start == null || preg_match($timePattern, $start) == 0) {
                                $allOk = false;
                                $this->view->errStart = true;
                            } else {
                                $day1->setStartTime($start);
                            }
                            if ($end == null || preg_match($timePattern, $end) == 0) {
                                $allOk = false;
                                $this->view->errEnd = true;
                            } else {
                                $day1->setEndTime($end);
                            }

                            $day1->setIsPrimary($obligated);
                            $day1->setType('Type1');
                            $days1[$i] = $day1;
                        }
                    }
                    if (strtotime($start2Old) > time()) {
                        for ($j = 0; $j < sizeof($days2); $j++) {   //loop through teacher conference days
                            $day2 = $days2[$j];
                            $day = isset($formData['txtDay' . $day2->getConferenceDayId()]) ? $formData['txtDay' . $day2->getConferenceDayId()] : '';
                            $obligated = isset($formData['chbObligated' . $day2->getConferenceDayId()]) ? true : false;
                            $start = isset($formData['txtStarttime' . $day2->getConferenceDayId()]) ? $formData['txtStarttime' . $day2->getConferenceDayId()] : '';
                            $end = isset($formData['txtEndtime' . $day2->getConferenceDayId()]) ? $formData['txtEndtime' . $day2->getConferenceDayId()] : '';

                            //formcheck
                            if ($day == null || preg_match($dataPattern, $day) == 0) {
                                $allOk = false;
                                $this->view->errDay = true;
                            } else {
                                $day2->setDate($day);
                            }
                            if ($start == null || preg_match($timePattern, $start) == 0) {
                                $allOk = false;
                                $this->view->errStart = true;
                            } else {
                                $day2->setStartTime($start);
                            }
                            if ($end == null || preg_match($timePattern, $end) == 0) {
                                $allOk = false;
                                $this->view->errEnd = true;
                            } else {
                                $day2->setEndTime($end);
                            }

                            $day2->setIsPrimary($obligated);
                            $day2->setType('Type2');
                            $days2[$j] = $day2;
                        }
                    }
                    
                    if ($allOk) {
                        $conference->save();
                        if (strtotime($start1Old) > time()) {
                            foreach ($days1 as $day1) {
                                $day1->save();
                            }
                        }
                        if (strtotime($start2Old) > time()) {
                            foreach ($days2 as $day2) {
                                $day2->save();
                            }
                        }

                        Application_Model_DbTable_PresentDays::setObligatedPresence($conference->getConferenceId());
                        $this->view->succes = true;

                        //log
                        $logMessage = '[' . date('D M d H:i:s Y') . '] [' . $this->staff->getFirstname() . ' ' . $this->staff->getName() . '@' . $_SERVER['REMOTE_ADDR'] . '] Changed settings of conference: ' . $conference->getName();
                        $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/dataupdate.log';
                        $fileHandle = fopen($file, 'a');
                        fwrite($fileHandle, $logMessage . "\r\n");
                        fclose($fileHandle);
                    }
                }
            }

            $this->view->titularDisabled = strtotime($conference->getStartSubscription1()) < time();
            $this->view->teacherDisabled = strtotime($conference->getStartSubscription2()) < time();
            
            $this->view->conference = $conference;
            $this->view->days1 = $days1;
            $this->view->days2 = $days2;        
        }
        
        
        $this->view->homeActive = true;
    }

    /**
     * Logout action
     * Provides logout functionality to administrators
     */
    public function logoutAction()
    {
        $this->session->unsetAll();
        $this->_helper->_redirector('index', 'Index');
    }
    
    
    /**
     * New action
     * Provides functionality to add a new conference
     */
    public function newAction()
    {
        $step = 1;
        
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            if (isset($formData['btnCancel'])) {    //cancel
                $this->_helper->redirector('index', 'Admin');
            }
            
            if (isset($formData['btnSave'])) {
                $step = $formData['step'];
                
                switch ($step) {    //submit
                    case 1:     //STEP 1
                        $dataPattern = '/^[0-9][0-9]-[0-9][0-9]-[0-9][0-9][0-9][0-9]$/';
                        $name = (isset($formData['txtName']) ? $formData['txtName'] : null);
                        $start1 = (isset($formData['txtStart1']) ? $formData['txtStart1'] : null);
                        $start2 = (isset($formData['txtStart2']) ? $formData['txtStart2'] : null);
                        $deadline1 = (isset($formData['txtDeadline1']) ? $formData['txtDeadline1'] : null);
                        $deadline2 = (isset($formData['txtDeadline2']) ? $formData['txtDeadline2'] : null);
                        $days1 = (int) (isset($formData['txtDays1']) ? $formData['txtDays1'] : null);
                        $days2 = (int) (isset($formData['txtDays2']) ? $formData['txtDays2'] : null);
                        $timeslotLength1 = (int) (isset($formData['txtTimeslotLength1']) ? $formData['txtTimeslotLength1'] : null);
                        $timeslotLength2 = (int) (isset($formData['txtTimeslotLength2']) ? $formData['txtTimeslotLength2'] : null);
                        $meantime = (int) (isset($formData['txtMeantime']) ? $formData['txtMeantime'] : null);
                        
                        $this->view->valName = $name;
                        $this->view->valStart1 = $start1;
                        $this->view->valStart2 = $start2;
                        $this->view->valDeadline1 = $deadline1;
                        $this->view->valDeadline2 = $deadline2;
                        $this->view->valDays1 = $days1;
                        $this->view->valDays2 = $days2;
                        $this->view->valTimeslotLength1 = $timeslotLength1;
                        $this->view->valTimeslotLength2 = $timeslotLength2;
                        $this->view->valMeantime = $meantime;
                        
                        //form check
                        $allOk = true;
                        if ($name == null || $name == '') {
                            $allOk = false;
                            $this->view->errName = true;
                            $this->view->valName = '';
                        }
                        if ($start1 == null || preg_match($dataPattern, $start1) == 0) {
                            $allOk = false;
                            $this->view->errStart1 = true;
                            $this->view->valStart1 = '';
                        }
                        if ($start2 == null || preg_match($dataPattern, $start2) == 0) {
                            $allOk = false;
                            $this->view->errStart2 = true;
                            $this->view->valStart2 = '';
                        }
                        if ($deadline1 == null || preg_match($dataPattern, $deadline1) == 0) {
                            $allOk = false;
                            $this->view->errDeadline1 = true;
                            $this->view->valDeadline1 = '';
                        }
                        if ($deadline2 == null || preg_match($dataPattern, $deadline2) == 0) {
                            $allOk = false;
                            $this->view->errDeadline2 = true;
                            $this->view->valDeadline2 = '';
                        }
                        if ($days1 == null || $days1 <= 0) {
                            $allOk = false;
                            $this->view->errDays1 = true;
                            $this->view->valDays1 = '';
                        }
                        if ($days2 == null || $days2 <= 0) {
                            $allOk = false;
                            $this->view->errDays2 = true;
                            $this->view->valDays2 = '';
                        }
                        if ($timeslotLength1 == null || $timeslotLength1 <= 0) {
                            $allOk = false;
                            $this->view->errTimeslotLength1 = true;
                            $this->view->valTimeslotLength1 = '';
                        }
                        if ($timeslotLength2 == null || $timeslotLength2 <= 0) {
                            $allOk = false;
                            $this->view->errTimeslotLength2 = true;
                            $this->view->valTimeslotLength2 = '';
                        }
                        if ($meantime == null || $meantime <= 0) {
                            $allOk = false;
                            $this->view->errMeantime = true;
                            $this->view->valMeantime = '';
                        }
                        
                        if ($allOk) {
                            //save settings temporary in session
                            $this->session->conference = new Application_Model_DbTable_Conferences(null, $name, $deadline1, $deadline2, $start1, $start2, null, $timeslotLength1, $timeslotLength2, $meantime);
                            
                            //prepare display step 2
                            $days1Arr = array(); $days2Arr = array();
                            for ($i = 0; $i < $days1; $i++) {
                                $days1Arr[] = new Application_Model_DbTable_ConferenceDays($i + 1);
                            }
                            for ($j = 0; $j < $days2; $j++) {
                                $days2Arr[] = new Application_Model_DbTable_ConferenceDays($j + 1 + $days1);
                            }
                            
                            $this->session->days1 = $days1Arr;
                            $this->session->days2 = $days2Arr;
                            $this->view->days1 = $days1Arr;
                            $this->view->days2 = $days2Arr;
                            $step++;
                        }
                        break;
                    case 2:     //STEP 2
                        $dataPattern = '/^[0-9][0-9]-[0-9][0-9]-[0-9][0-9][0-9][0-9]$/';
                        $timePattern = '/^[0-9][0-9]:[0-9][0-9]$/';
                        $allOk = true;
                        
                        for ($i = 0; $i < sizeof($this->session->days1); $i++) {    //loop titular conferenceDays
                            $day1 = $this->session->days1[$i];
                            $day = isset($formData['txtDay' . $day1->getConferenceDayId()]) ? $formData['txtDay' . $day1->getConferenceDayId()] : '';
                            $obligated = isset($formData['chbObligated' . $day1->getConferenceDayId()]) ? true : false;
                            $start = isset($formData['txtStart' . $day1->getConferenceDayId()]) ? $formData['txtStart' . $day1->getConferenceDayId()] : '';
                            $end = isset($formData['txtEnd' . $day1->getConferenceDayId()]) ? $formData['txtEnd' . $day1->getConferenceDayId()] : '';

                            //formcheck
                            if ($day == null || preg_match($dataPattern, $day) == 0) {
                                $allOk = false;
                                $this->view->errDay = true;
                            } else {
                                $day1->setDate($day);
                            }
                            if ($start == null || preg_match($timePattern, $start) == 0) {
                                $allOk = false;
                                $this->view->errStart = true;
                            } else {
                                $day1->setStartTime($start);
                            }
                            if ($end == null || preg_match($timePattern, $end) == 0) {
                                $allOk = false;
                                $this->view->errEnd = true;
                            } else {
                                $day1->setEndTime($end);
                            }
                            
                            $day1->setIsPrimary($obligated);
                            $day1->setType('Type1');
                            
                            //save temporary in session
                            $this->session->days1[$i] = $day1;
                        }
                        for ($j = 0; $j < sizeof($this->session->days2); $j++) {    //loop teacher conferenceDays
                            $day2 = $this->session->days2[$j];
                            $day = isset($formData['txtDay' . $day2->getConferenceDayId()]) ? $formData['txtDay' . $day2->getConferenceDayId()] : '';
                            $obligated = isset($formData['chbObligated' . $day2->getConferenceDayId()]) ? true : false;
                            $start = isset($formData['txtStart' . $day2->getConferenceDayId()]) ? $formData['txtStart' . $day2->getConferenceDayId()] : '';
                            $end = isset($formData['txtEnd' . $day2->getConferenceDayId()]) ? $formData['txtEnd' . $day2->getConferenceDayId()] : '';
                            
                            //formcheck
                            if ($day == null || preg_match($dataPattern, $day) == 0) {
                                $allOk = false;
                                $this->view->errDay = true;
                            } else {
                                $day2->setDate($day);
                            }
                            if ($start == null || preg_match($timePattern, $start) == 0) {
                                $allOk = false;
                                $this->view->errStart = true;
                            } else {
                                $day2->setStartTime($start);
                            }
                            if ($end == null || preg_match($timePattern, $end) == 0) {
                                $allOk = false;
                                $this->view->errEnd = true;
                            } else {
                                $day2->setEndTime($end);
                            }
                            
                            $day2->setIsPrimary($obligated);
                            $day2->setType('Type2');
                            
                            //save temporary in session
                            $this->session->days2[$j] = $day2;
                        }
                        
                        if ($allOk) {
                            $this->view->groups = Application_Model_DbTable_Groups::getAllGroups();
                            $step++;
                        }
                        else {
                            $this->view->days1 = $this->session->days1;
                            $this->view->days2 = $this->session->days2;
                        }
                        break;
                    case 3:     //STEP 3
                        //saving conference
                        $conferenceId = $this->session->conference->save();

                        //saving conference days
                        foreach ($this->session->days1 as $day) {
                            $day->setConference($conferenceId);
                            $day->setConferenceDayId(null);
                            $day->save();
                        }
                        foreach ($this->session->days2 as $day) {
                            $day->setConference($conferenceId);
                            $day->setConferenceDayId(null);
                            $day->save();
                        }

                        //saving participants
                        $participants = (isset($formData['participants']) ? $formData['participants'] : array());
                        foreach($participants as $id) {
                            $participant = new Application_Model_DbTable_Participants($conferenceId, $id);
                            $participant->save();
                        }

                        //set obligated present days
                        Application_Model_DbTable_PresentDays::setObligatedPresence($conferenceId);

                        //log
                        $logMessage = '[' . date('D M d H:i:s Y') . '] [' . $this->staff->getFirstname() . ' ' . $this->staff->getName() . '@' . $_SERVER['REMOTE_ADDR'] . '] Added new conference: ' . $this->session->conference->getName();
                        $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/dataupdate.log';
                        $fileHandle = fopen($file, 'a');
                        fwrite($fileHandle, $logMessage . "\r\n");
                        fclose($fileHandle);
                        
                        unset($this->session->conference);
                        unset($this->session->days1);
                        unset($this->session->days2);
                        $this->session->activeConference = $conferenceId;
                                                
                        $this->_helper->redirector('index', 'Admin');
                        break;
                }
            }
        }
        
        $this->view->newActive = true;
        $this->view->step = $step;
        $this->view->valStart1a = $this->view->valStart1 == null ? Date('d-m-Y') : $this->view->valStart1;
        $this->view->valStart2a = $this->view->valStart2 == null ? Date('d-m-Y') : $this->view->valStart2;
        $this->view->valDeadline1a = $this->view->valDeadline1 == null ? Date('d-m-Y') : $this->view->valDeadline1;
        $this->view->valDeadline2a = $this->view->valDeadline2 == null ? Date('d-m-Y') : $this->view->valDeadline2;
    }

    
    
    /*
     * HELPER FUNCTIONS
     */
    
    /**
     * Get Zend_Auth_Adapter_OpenId adapter
     *
     * @param string $openid_identifier
     * @return Zend_Auth_Adapter_OpenId
     */
    protected function _getOpenIdAdapter($openid_identifier = null)
    {
        $adapter = new Zend_Auth_Adapter_OpenId($openid_identifier);
        $dir = APPLICATION_PATH . '/../tmp';

        if (!file_exists($dir)) {
            if (!mkdir($dir)) {
                throw new Zend_Exception("Cannot create $dir to store tmp auth data.");
            }
        }
        $adapter->setStorage(new Zend_OpenId_Consumer_Storage_File($dir));

        return $adapter;
    }

    /**
     * Get Zend_OpenId_Extension. Sreg or Ax. 
     *
     * @param string $extType Possible values: 'sreg' or 'ax'
     * @param array $propertiesToRequest
     * @return Zend_OpenId_Extension|null
     */
    protected function _getOpenIdExt($extType, array $propertiesToRequest)
    {

        $ext = null;

        if ('ax' == $extType) {
            $ext = new My_OpenId_Extension_AttributeExchange($propertiesToRequest);
        } elseif ('sreg' == $extType) {
            $ext = new Zend_OpenId_Extension_Sreg($propertiesToRequest);
        }

        return $ext;
    }
}