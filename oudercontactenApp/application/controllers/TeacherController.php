<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '../openid/extension/AttributeExchange.php';
require_once 'My/DateUtilities.php';

class TeacherController extends Zend_Controller_Action
{
    protected $session = null;
    protected $staff = null;
    protected $activeConference = null;
    protected $conferenceAvailable = false;

    /**
     * This function is executed each time an TeacherController action is processed, but the result of execution may deviate by visiting other actions.
     */
    public function init()
    {
        $this->session = new Zend_Session_Namespace();
        
        //check if user is visiting from another controller
        if ($this->session->authAs != 'Teacher' && $this->session->authAs != null) {
            $this->session->unsetAll();
            $this->_helper->_redirector('index', 'Index');
        }
        
        //check if user is authenticated and authenticate if necessary
        if ($this->getRequest()->getActionName() != 'authenticate' && $this->session->authAs != 'Teacher') {
            $this->_helper->_redirector('authenticate', 'Teacher');
        }
        
        $titularConference = false; $teacherConference = false;
        //load authenticated user
        if ($this->session->id != null) {
            $this->staff = Application_Model_DbTable_Staff::getStaffById($this->session->id);
            $this->view->user = $this->staff->getFirstname() . ' ' . $this->staff->getName();
            
            //load active conference
            $conferences = Application_Model_DbTable_Conferences::getAllConferences();
            if (sizeof($conferences) > 0) {
                $this->activeConference = ($this->getRequest()->getParam('conference') != null ? $this->getRequest()->getParam('conference') : ($this->session->activeConference != null ? $this->session->activeConference : $conferences[0]['conferenceId']));
                $this->session->activeConference = $this->activeConference;
                $this->view->activeConference = $this->activeConference;
                $this->view->conferencesAvailable = true;
                $this->conferenceAvailable = true;
                $this->view->conferences = $conferences;
            } else {
                //$this->_helper->_redirector('index', 'Teacher');
            }
            
            //check if staff has to be present on conference
            $titularConference = Application_Model_DbTable_Groups::isTitularOnConference($this->staff->getStaffId(), $this->activeConference);
            $titularConference = ($titularConference || Application_Model_DbTable_Responsibles::isResponsibleOnConference($this->staff->getStaffId(), $this->activeConference) ? true : false);
            $teacherConference = Application_Model_DbTable_Courses::isTeacherOnConference($this->staff->getStaffId(), $this->activeConference);
            if (!$titularConference && !$teacherConference && $this->getRequest()->getActionName() != 'index' && $this->getRequest()->getActionName() != 'logout') {
                $this->_helper->_redirector('index', 'Teacher');
            }            

            $this->view->displayLogout = true;
            $this->view->urlLogout = $this->view->url(array('controller' => 'Teacher', 'action' => 'logout'));
            $this->view->staffId = $this->staff->getStaffId();
        }
         
        $this->view->controller = 'Teacher';
        $this->view->accessebilityLevel = $this->session->accessebilityLevel;
        $this->view->titularConference = $titularConference;
        $this->view->teacherConference = $teacherConference;
        $this->view->headLink()->prependStylesheet($this->view->baseUrl().'/css/teacher.css');
    }

    
    
    /*
     * ACTIONS 
     */
    
    /**
     * Appointment action
     * Displays all appointments of a teacher depending on which appointments he want's to see (titular/teacher appointments)
     * and offers functionality to select present days 
     */
    public function appointmentAction()
    {
        $conference = Application_Model_DbTable_Conferences::getConferenceById($this->activeConference);
        $activeId = $this->getRequest()->getParam('activeId', null);
        
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            if (isset($formData['btnCancel'])) {
                $this->_helper->redirector('index', 'Teacher');
            }
            
            if (isset($formData['btnSave'])) {      //save changes
                foreach ($formData['days'] as $day) {
                    $conferenceDay = Application_Model_DbTable_ConferenceDays::getConferenceDayById($day);
                    if ($conferenceDay->getIsPrimary() || array_key_exists('present' . $day, $formData)) {
                        //add new present days
                        $allOk = true;
                        $start = strtotime($conferenceDay->getStartTime());
                        $end = strtotime($conferenceDay->getEndTime());                        
                        $presentDay = new Application_Model_DbTable_PresentDays($day, $this->staff->getStaffId(), Date('H:i:s', $start), Date('H:i:s', $end));
                        $presentDay->save();
                        $this->view->saved = true;
                    }
                    else {
                        //delete existing  present day
                        $roomAllocation = new Application_Model_DbTable_RoomAllocations(null, $this->staff->getStaffId(), $day);
                        $roomAllocation->deleteRoomAllocation();
                        $presentDay = new Application_Model_DbTable_PresentDays($day, $this->staff->getStaffId(), null, null);
                        $presentDay->deletePresentDay();
                        Application_Model_DbTable_Appointments::deleteAppointments($day, $this->staff->getStaffId());
                        $this->view->saved = true;
                    }
                }
            }
        }

        //load agenda items
        $tiid = $this->getRequest()->getParam('tiid', null);
        $teid = $this->getRequest()->getParam('teid', null);
        $days = array();

        if ($tiid != null) {     //titular conference
            $days = $this->staff->getTeacherAppointments($this->activeConference, 'type1');
            $this->view->titular = true;
            $this->view->timeslotLength = $conference->getTimeLength1() * 60;
            $this->view->settingsEnabled = (strtotime($conference->getStartSubscription1()) > time() ? true : false);
            $this->view->titularActive = true;
        }
        else if ($teid != null) {    //teacher conference
            $days = $this->staff->getTeacherAppointments($this->activeConference, 'type2');
            $this->view->timeslotLength = $conference->getTimeLength2() * 60;
            $this->view->settingsEnabled = (strtotime($conference->getStartSubscription2()) > time() ? true : false);
            $this->view->teacherActive = true;
        }
        else {  //no conference selected
            $this->_helper->redirector('index', 'Teacher');
        }

        
        $this->view->days = $days;
        $this->view->name = $this->staff->getFirstname() . ' ' . $this->staff->getName();
        $this->view->activeId = ($activeId != null ? $activeId : $days[0]->getDayId());
        $this->view->headLink()->prependStylesheet($this->view->baseUrl().'/css/print.css', 'print');
    }
    
    
    /**
     * AppointmentDelete action
     * Deletes breaks from teacher calendar
     */
    public function appointmentDeleteAction()
    {
        //data check
        $appointmentId = $this->getRequest()->getParam('appointmentId');
        $tiid = $this->getRequest()->getParam('tiid', null);
        $teid = $this->getRequest()->getParam('teid', null);
        $conferenceDayId = $this->getRequest()->getParam('activeId', null);
        
        if ($appointmentId == null) {       //check if appointmentId is set
            $this->_helper->redirector('appointment', 'Teacher', null, array(($tiid != null ? 'tiid' : 'teid') => $this->staff->getStaffId(), 'activeId' => $conferenceDayId));
        }
        
        //delete break
        $appointment = new Application_Model_DbTable_Appointments($appointmentId, $this->staff->getStaffId());
        $appointment->deleteAppointment();
        $this->_helper->redirector('appointment', 'Teacher', null, array(($tiid != null ? 'tiid' : 'teid') => $this->staff->getStaffId(), 'activeId' => $conferenceDayId));
    }

    
    /**
     * AppointmentNew action
     * Adds breaks to teacher calendar
     */
    public function appointmentNewAction()
    {
        $conferenceDayId = null; $tiid = null; $teid = null; $time = null; $break = null;
        
        //data check
        $conferenceDayId = $this->getRequest()->getParam('dayId');
        $this->session->activeId = $conferenceDayId;
        $tiid = $this->getRequest()->getParam('tiid');
        $teid = $this->getRequest()->getParam('teid');
        $time = $this->getRequest()->getParam('time');
        $break = $this->getRequest()->getParam('break');
        
        if ($time == null || $conferenceDayId == null || $time == null || $break != 'true') {
            $this->_helper->redirector('appointment', 'Teacher', null, ($tiid != null ? array('tiid' => $this->staff->getStaffId()) : array('teid' => $this->staff->getStaffId())));
        }
        
        //add break
        $appointment = new Application_Model_DbTable_Appointments(null, $this->staff->getStaffId(), null, null, null, $time, $conferenceDayId, $this->activeConference);
        $appointment->save();
        $this->_helper->redirector('appointment', 'Teacher', null, array(($tiid != null ? 'tiid' : 'teid') => $this->staff->getStaffId(), 'activeId' => $conferenceDayId));
    }
    
    
    /**
     * Authentication action
     * Provides authentication to teachers using their Google accounts 
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
                $properties = $ext->getProperties();
                $openId = $properties['email'];
                try {
                    $staff = Application_Model_DbTable_Staff::Authenticate($openId);
                }
                catch (Exception $e) {
                    switch ($e->getCode()) {
                        case 101: $this->view->userErr = true;
                            break;
                    }
                    
                    $allOk = false;
                }
                
                if ($allOk) {
                    $this->session->authAs = 'Teacher';
                    $this->session->id = $staff->getStaffId();
                    $this->_helper->_redirector('index', 'Teacher');
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
     * Index action
     * Default action which displays general information about the selected conference 
     */
    public function indexAction()
    {
        if ($this->conferenceAvailable) {
            $conference = Application_Model_DbTable_Conferences::getConferenceById($this->activeConference);
            $days = Application_Model_DbTable_ConferenceDays::getConferenceDaysOfConference($conference->getConferenceId());
            foreach ($days as $day) {
                if (strtolower($day->getType()) == 'type1') {
                    if (strlen($this->view->days1) == 0) {
                        $this->view->days1 = DateUtilities::weekday(strtotime($day->getDate()), true) . ' ' . date('d', strtotime($day->getDate())) . ' ' . DateUtilities::month(strtotime($day->getDate()));
                    }
                    else {
                        $this->view->days1 .= ' & ' . DateUtilities::weekday(strtotime($day->getDate()), true) . ' ' . date('d', strtotime($day->getDate())) . ' ' . DateUtilities::month(strtotime($day->getDate()));
                    }
                }
                else {
                    if (strlen($this->view->days2) == 0) {
                        $this->view->days2 = DateUtilities::weekday(strtotime($day->getDate()), true) . ' ' . date('d', strtotime($day->getDate())) . ' ' . DateUtilities::month(strtotime($day->getDate()));
                    }
                    else {
                        $this->view->days2 .= ' & ' . DateUtilities::weekday(strtotime($day->getDate()), true) . ' ' . date('d', strtotime($day->getDate())) . ' ' . DateUtilities::month(strtotime($day->getDate()));
                    }
                }
            }        

            $this->view->conferenceName = $conference->getName();
            $this->view->start1 = DateUtilities::weekday(strtotime($conference->getStartSubscription1()), true) . ' ' . date('d', strtotime($conference->getStartSubscription1())) . ' ' . DateUtilities::month(strtotime($conference->getStartSubscription1()));
            $this->view->start2 = DateUtilities::weekday(strtotime($conference->getStartSubscription2()), true) . ' ' . date('d', strtotime($conference->getStartSubscription2())) . ' ' . DateUtilities::month(strtotime($conference->getStartSubscription2()));
            $this->view->end1 = DateUtilities::weekday(strtotime($conference->getDeadlineSubscription1()), true) . ' ' . date('d', strtotime($conference->getDeadlineSubscription1())) . ' ' . DateUtilities::month(strtotime($conference->getDeadlineSubscription1()));
            $this->view->end2 = DateUtilities::weekday(strtotime($conference->getDeadlineSubscription2()), true) . ' ' . date('d', strtotime($conference->getDeadlineSubscription2())) . ' ' . DateUtilities::month(strtotime($conference->getDeadlineSubscription2()));
        }
        
        $this->view->homeActive = true;            
        $this->view->action = 'index';
        
        if ($this->getRequest()->getParam('info') == 'more') {
            $this->view->moreInfo = true;
        }
    }
    
    
    /**
     * Lougout action
     * Provides logout functionality to teachers 
     */
    public function logoutAction()
    {
        $this->session->unsetAll();
        $this->_helper->_redirector('index', 'Index');
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