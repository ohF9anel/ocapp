<?php
require_once 'My/DateUtilities.php';

class ParentController extends Zend_Controller_Action
{
    protected $session = null;
    protected $parent = null;
    protected $activeConference = null;
    protected $conferenceAvailable = false;
    protected $allocatedMinutes = 2;
    protected $bookTitular = false, $bookTeacher = false;

    /**
     * This function is executed each time a ParentController action is processed, but the result of execution may deviate by visiting other actions.
     */
    public function init()
    {
        //initiate session
        $this->session = new Zend_Session_Namespace();

        //do not execute init function when allocateslot or exceedleasetime action is requested
        if ($this->getRequest()->getActionName() == 'allocateslot' || $this->getRequest()->getActionName() == 'exceedleasetime') {
            return;
        }     
        
        //check if user is visiting from another controller
        if ($this->session->authAs != 'Parent' && $this->session->authAs != null) {
            $this->session->unsetAll();
            $this->_helper->_redirector('index', 'Index');
        }
        
        //check if user is not authentication in another controller, redirect if so
        if ($this->session->authAs != 'Parent' && $this->session->authAs != null) {
            $this->_helper->_redirector('index', 'Index');
        }

        //check if user is authenticated and authenticate if necessary by redirecting to authenticate action      
        if ($this->getRequest()->getActionName() != 'authenticate' && $this->session->authAs != 'Parent') {
            $this->_helper->_redirector('authenticate', 'Parent');
        }
        
        //load the authenticated user
        if ($this->session->id != null) {
            $this->parent = Application_Model_DbTable_Parents::getParentByParentId($this->session->id);
            $this->view->user = $this->parent->getSalutation();
            
            //if user doesn't visit the book action: clear all temporary appointments of this user
            if ($this->getRequest()->getActionName() != 'book') {
                Application_Model_DbTable_Appointments::clearTemporaryAppointmentsByParentId($this->parent->getParentId());
            }
            
            //load all available conferences for this user and select active one
            $conferences = $this->parent->getConferences();
            if (sizeof($conferences) != 0) {
                $this->activeConference = ($this->getRequest()->getParam('conference') != null ? $this->getRequest()->getParam('conference') : ($this->session->activeConference != null ? $this->session->activeConference : $conferences[0]['conferenceId']));
                $this->session->activeConference = $this->activeConference;
                $this->view->activeConference = $this->activeConference;
                $this->conferenceAvailable = true;
                $this->view->conferencesAvailable = $this->conferenceAvailable;
                $this->view->conferences = $conferences;
                
                //check if reservations can be maded
                $conference = Application_Model_DbTable_Conferences::getConferenceById($this->activeConference);
                if (strtotime($conference->getStartSubscription1()) < time() && strtotime($conference->getDeadlineSubscription1()) >= strtotime(date('Y-m-d'))) {
                    $this->bookTitular = true;
                }
                if (strtotime($conference->getStartSubscription2()) < time() && strtotime($conference->getDeadlineSubscription2()) >= strtotime(date('Y-m-d'))) {
                    $this->bookTeacher = true;
                }
            }            
            else {
                if ($this->getRequest()->getActionName() != 'index' && $this->getRequest()->getActionName() != 'logout') {
                    $this->_helper->_redirector('index', 'Parent');;
                }
            }
            
            
            $this->view->displayLogout = true;
            $this->view->urlLogout = $this->view->url(array('controller' => 'Parent', 'action' => 'logout'));
        }
        
        $this->view->controller = 'Parent';
        $this->view->headLink()->prependStylesheet($this->view->baseUrl().'/css/parent.css', 'screen');
        $this->view->headLink()->prependStylesheet($this->view->baseUrl().'/css/print.css', 'print');
    }
    
    
    
    /*
     * ACTIONS
     */
    
    /**
     * Appointment action
     * Displays all appointments of a parent
     */
    public function appointmentAction()
    {
        $this->view->appointmentActive = true;
        
        //loading appointments
        $titularAppointments = $this->parent->getAppointmentsByConferenceWithInfo($this->activeConference, 'Type1');
        $teacherAppointments = $this->parent->getAppointmentsByConferenceWithInfo($this->activeConference, 'Type2');
        if (sizeof($titularAppointments) != 0) {
            $this->view->titularAppointmentsAvailable = true;
            $this->view->titularAppointments = $titularAppointments;
        }
        if (sizeof($teacherAppointments) != 0) {
            $this->view->teacherAppointmentsAvailable = true;
            $this->view->teacherAppointments = $teacherAppointments;
        }
        
        //determine notifications (appointments editable or not?)
        $conference = Application_Model_DbTable_Conferences::getConferenceById($this->activeConference);
        $this->view->bookTitular = $this->bookTitular;
        $this->view->bookTeacher = $this->bookTeacher;
        $this->view->titularEditable = (strtotime($conference->getDeadlineSubscription1()) >= strtotime(date('Y-m-d')));
        $this->view->teacherEditable = (strtotime($conference->getDeadlineSubscription2()) >= strtotime(date('Y-m-d')));
        $this->view->titularAppointmentsAvailable = (strtotime($conference->getDeadlineSubscription1()) <= strtotime(date('Y-m-d')) ? true : $this->view->titularAppointmentsAvailable);
        $this->view->teacherAppointmentsAvailable = (strtotime($conference->getDeadlineSubscription2()) <= strtotime(date('Y-m-d')) ? true : $this->view->teacherAppointmentsAvailable);
        $this->view->titularDeadline = DateUtilities::weekday(strtotime($conference->getDeadlineSubscription1())) . ' ' . date('d', strtotime($conference->getDeadlineSubscription1())) . ' ' . DateUtilities::month(strtotime($conference->getDeadlineSubscription1()));
        $this->view->teacherDeadline = DateUtilities::weekday(strtotime($conference->getDeadlineSubscription2())) . ' ' . date('d', strtotime($conference->getDeadlineSubscription2())) . ' ' . DateUtilities::month(strtotime($conference->getDeadlineSubscription2()));
        $this->view->start1 = DateUtilities::weekday(strtotime($conference->getStartSubscription1())) . ' ' . date('d', strtotime($conference->getStartSubscription1())) . ' ' . DateUtilities::month(strtotime($conference->getStartSubscription1()));
        $this->view->start2 = DateUtilities::weekday(strtotime($conference->getStartSubscription2())) . ' ' . date('d', strtotime($conference->getStartSubscription2())) . ' ' . DateUtilities::month(strtotime($conference->getStartSubscription2()));
    }
    
    
    /**
     * Authentication action
     * Provides authentication to parents
     */
    public function authenticateAction()
    {
        //proceed login if form is submitted
        if ($this->getRequest()->isPost()) {
            $loginResult = $this->proceedLogin();

            if ($loginResult) {
                $this->_helper->_redirector('index', 'Parent');
            }
        }
        

        $this->view->noSidebar = true;
        $this->view->displayLogout = false;
        $this->view->urlSelf = $this->view->url((array('controller' => 'Parent', 'action' => 'authenticate')));
    }


    /**
     * Book action
     * Provides functionality to parents which makes it possible to make reservations
     */
    public function bookAction()
    {
        //submit user input
        if ($this->getRequest()->IsPost()) {
            $formData = $this->getRequest()->getPost();
            
            switch ($this->session->step) {
                case 1:     //STEP 1
                    if (array_key_exists('next', $formData)) {      //button next is used by user 
                        $skipStep2 = true;
                        //check for which pupil which appointments has to be made in the next step
                        foreach ($this->session->dataBook as $pupilData) {
                            $teachers = $pupilData->getTeachers();

                            if ($pupilData->getType() == 'type1') {
                                foreach ($teachers as $teacher) {
                                    if (array_key_exists('s' . $pupilData->getPupilId(), $formData) && in_array($teacher->getStaffId(), $formData['s' . $pupilData->getPupilId()])) {
                                        $teacher->setToSee(true);
                                        $skipStep2 = false;
                                    }
                                    else {
                                        //if teacher is not selected but there was made an appointment, the existing appointment will be selected for deletion
                                        if ($teacher->getToSee()) {
                                            $teacher->setToSee(false);
                                        }
                                    }
                                }
                            }
                            else {
                                foreach ($teachers as $teacher) {
                                    if (array_key_exists('s' . $pupilData->getPupilId(), $formData) && in_array($teacher->getCourseId(), $formData['s' . $pupilData->getPupilId()])) {
                                        $teacher->setToSee(true);
                                        $skipStep2 = false;
                                    }
                                    else {
                                        //if teacher is not selected but there was made an appointment, the existing appointment will be selected for deletion
                                        $teacher->setToSee(false);
                                    }
                                }
                            }
                        }

                        $this->session->step += ($skipStep2 ? 2 : 1);
                    }
                    else {
                        $this->_helper->_redirector('index', 'Parent');
                    }
                    break;
                case 2:     //STEP 2
                    if (array_key_exists('next', $formData)) {
                        $formOk = true;
                        
                        Application_Model_DbTable_Appointments::extendLeaseTemporaryAppointments($this->parent->getParentId());
                        
                        //check which appointments are maded in step 2 and save if no errors against restrictions are maded
                        $appointments = array();
                        foreach ($this->session->dataBook as $pupilData) {
                            foreach ($pupilData->getTeachers() as $teacher) {
                                if (!$teacher->getToSee()) {
                                    continue;
                                }
                                
                                if (!array_key_exists(($this->session->type == 'type1' ? 't' . $teacher->getStaffId() : 'c' . $teacher->getCourseId()) . 'p' . $pupilData->getPupilId(), $formData)) {
                                    $formOk = false;
                                    $this->view->errFields = true;
                                }
                                else {
                                    $appointment = $formData[($this->session->type == 'type1' ? 't' . $teacher->getStaffId() : 'c' . $teacher->getCourseId()) . 'p' . $pupilData->getPupilId()];
                                    $appointment = explode('d', $appointment);
                                    $teacher->setAppointmentDay($appointment[1]);
                                    $teacher->setAppointmentSlot($appointment[0]);
                                    
                                    //check against restrictions
                                    if (!array_key_exists($appointment[1], $appointments)) {
                                        $appointments[$appointment[1]] = array();
                                    }
                                    else {
                                        if (in_array($appointment[0], $appointments[$appointment[1]])) {
                                            $this->view->errAppointment = true;
                                            $formOk = false;
                                        }
                                    }
                                    
                                    $appointments[$appointment[1]][] = $appointment[0];
                                }
                            }
                        }

                        if ($formOk) {
                            $this->session->step++;
                        }
                    }
                    else if (array_key_exists('prev', $formData)) {
                        $this->session->step--;
                    }
                    break;
                case 3:     //STEP 3
                    if (array_key_exists('confirm', $formData)) {
                        //get conferenceDays of first round
                        $days = Application_Model_DbTable_Conferences::getConferenceById($this->activeConference)->getConferenceDays();
                        
                        //save/change confirmed appointments
                        foreach ($this->session->dataBook as $pupilData) {
                            foreach ($pupilData->getTeachers() as $teacher) {
                                //check if appointments has to be deleted
                                if (!$teacher->getToSee()) {
                                    while (true) {
                                        $appointment = Application_Model_DbTable_Appointments::getAppointmentByStaffIdOnType($pupilData->getPupilId(), $this->parent->getParentId(), $teacher->getStaffId(), $this->activeConference, $this->session->type);
                                        if ($appointment == null) {
                                            break;
                                        }
                                        
                                        $appointment->deleteAppointment();
                                    }
                                }
                            }
                            
                            Application_Model_DbTable_Appointments::saveAllTemporary($pupilData->getPupilId(), $this->parent->getParentId(), $this->activeConference);
                        }
                        
                        //sending confimation
                        if ($this->parent->getMail() != null) {
                            try {
                                $appointments = $this->parent->getAppointments();
                                $text = $this->parent->getSalutation() . ',' . "\r\n\r\n";
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
                                $mail->addTo($this->parent->getMail(), $this->parent->getSalutation());
                                $mail->setSubject('Bevestiging afspraken oudercontact');
                                $mail->setBodyText($text);
                                $mail->send($transport);
                            }
                            catch(Exception $e) {
                                $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '../../log/error.log';
                                $message = '[' . date('D M d H:i:s Y') . '] [' . $this->parent->getSalutation() . '@' . $_SERVER['REMOTE_ADDR'] . '] ' . 'Error while sending confirmation mail to ' . $this->parent->getMail();
                                $fileHandle = fopen($file, 'a');
                                fwrite($fileHandle, $message . "\r\n");
                                fclose($fileHandle);
                            }
                        }
                        
                        unset($this->session->step);
                        unset($this->session->dataBook);
                        $this->_helper->_redirector('index', 'Parent', null, array('confirm' => 'ok'));
                    }
                    else if (array_key_exists('prev', $formData)) {
                        $this->session->step--;
                    }
                    else if (array_key_exists('prev2', $formData)) {
                        $this->session->step -= 2;
                    }
                    break;  
            }
        }
        else {      //no user input -> start new reservation (fill book step 1)
            //determine reservation type (type 1 or type 2)
            $this->session->type = ($this->getRequest()->getParam('book') != 'type1' && $this->getRequest()->getParam('book') != 'type2' ? 'type1' : $this->getRequest()->getParam('book'));

            //check if reservation is possible (start and deadline), if not redirect to index
            if ($this->session->type == 'type1' && !$this->bookTitular || $this->session->type == 'type2' && !$this->bookTeacher) {
                $this->_helper->_redirector('index', 'Parent');
            }
            
            //get children of this parent and collect information
            $children = array();
            $pupils = $this->parent->getChildren();
            foreach ($pupils as $pupil) {
                $group = Application_Model_DbTable_Groups::getGroupByGroupId($pupil->getGroupId());
                $pupilData = new Application_Model_PupilData($pupil->getFirstname(), $pupil->getName(), $pupil->getPupilId(), $group->getName(), $this->session->type);

                //get available persons based on reservation type and collect their information
                if ($this->session->type == 'type1') {  //type 1
                    $titular = $group->getTitularAsStaff();
                    $appointment = $titular->getAppointmentOfPupil($pupil->getPupilId(), $this->parent->getParentId(), $this->activeConference);
                    if ($appointment == null) $pupilData->addTeacher($titular->getFirstname(), $titular->getName(), $titular->getStaffId(), 'klassenleraar', $group->getRemark());
                    else $pupilData->addTeacher($titular->getFirstname(), $titular->getName(), $titular->getStaffId(), 'klassenleraar', $group->getRemark(), null, $appointment->getConferenceDayId(), $appointment->getAppointment(), false, true);

                    $responsibles = $group->getResponsibles();
                    foreach ($responsibles as $responsible) {
                        $staff = Application_Model_DbTable_Staff::getStaffById($responsible->getStaffId());
                        $appointment = $staff->getAppointmentOfPupil($pupil->getPupilId(), $this->parent->getParentId(), $this->activeConference);
                        if ($appointment == null) $pupilData->addTeacher($staff->getFirstname(), $staff->getName(), $staff->getStaffId(), $responsible->getFunction(), $responsible->getRemark(), null, null, null, true);
                        else $pupilData->addTeacher($staff->getFirstname(), $staff->getName(), $staff->getStaffId(), $responsible->getFunction(), $responsible->getRemark(), null, $appointment->getConferenceDayId(), $appointment->getAppointment(), true, true);
                    }
                }
                else {  //type 2
                    $courses = $group->getCourses();
                    foreach ($courses as $course) {
                        $staff = $course->getTeacherAsStaff();
                        $appointment = $staff->getAppointmentOfPupil($pupil->getPupilId(), $this->parent->getParentId(), $this->activeConference, $course->getCourseId());
                        if ($appointment == null) $pupilData->addTeacher($staff->getFirstname(), $staff->getName(), $staff->getStaffId(), $course->getCourse(), $course->getRemark(), $course->getCourseId());
                        else $pupilData->addTeacher($staff->getFirstname(), $staff->getName(), $staff->getStaffId(), $course->getCourse(), $course->getRemark(), $course->getCourseId(), $appointment->getConferenceDayId(), $appointment->getAppointment(), false, true);
                    }
                }
                $children[] = $pupilData;
            }
            
            $this->session->dataBook = $children;
            $this->session->step = 1;
        }
        
        
        //fill reservation step
        $this->view->step = $this->session->step;
        switch ($this->session->step) {
            case 1:     //STEP 1
                $this->view->children = $this->session->dataBook;
                $this->view->activeId = $this->session->dataBook[0]->getPupilId();
                break;
            case 2:     //STEP 2
                //load conference days and set active conference day
                $days = array();
                $activeDayId = null;
                $conference = Application_Model_DbTable_Conferences::getConferenceById($this->activeConference);
                $conferenceDays = $conference->getConferenceDays();
                foreach ($conferenceDays as $conferenceDay) {
                    if (strtolower($conferenceDay->getType()) == strtolower($this->session->type)) {
                        $days[$conferenceDay->getConferenceDayId()] = $conferenceDay->getDate();
                        
                        if ($activeDayId == null) {
                            $activeDayId = $conferenceDay->getConferenceDayId();
                        }
                    }  
                }
                
                //calculate the first start and last end time
                $firstStarts = array();
                $lastEnds = array();
                foreach ($conference->getConferenceDays() as $day) {
                    $firstStarts[$day->getConferenceDayId()] = $day->getStartTime();
                    $lastEnds[$day->getConferenceDayId()] = $day->getEndTime();
                }
                

                foreach ($this->session->dataBook as $pupil) {
                    foreach ($pupil->getTeachers() as $teacher) {                         
                        //load for each selected teacher the available timeslots
                        if ($teacher->getToSee() && $teacher->getAvailableSlots() == null) {
                            $staff = Application_Model_DbTable_Staff::getStaffById($teacher->getStaffId());
                            $availableSlots = $staff->getAvailableTimes($this->activeConference, $this->parent->getParentId());
                            $teacher->setAvailableSlots($availableSlots);

                            $appointment = $staff->getAppointmentOfPupil($pupil->getPupilId(), $this->parent->getParentId(), $this->activeConference, ($this->session->type == 'type1' ? null : $teacher->getcourseId()));
                            if ($appointment != null) {
                                $teacher->setAppointmentDay($appointment->getConferenceDayId());
                                $teacher->setAppointmentSlot($appointment->getAppointment());
                            }
                        }
                        
                        //calculate the first start and last end time
                        $slotLength = ($this->session->type == 'type1' ? $conference->getTimeLength1() : $conference->getTimeLength2());
                        foreach ($teacher->getAvailableSlots() as $day => $slots) {
                            if ($firstStarts[$day] > $slots[0]) {
                                $firstStarts[$day] = $slots[0];
                            }
                            if ($lastEnds[$day] < Date('H:i:s', strtotime($slots[sizeof($slots) - 1]) + $slotLength * 60)) {
                                $lastEnds[$day] = Date('H:i:s', strtotime($slots[sizeof($slots) - 1]) + $slotLength * 60);
                            }
                        }
                    }
                }
                
                //calculate all timeslots
                $start = $conference->getStartTime($this->session->type);
                $end = $conference->getEndTime($this->session->type);                
                $timeslots = array();
                $timeslotLength = ($this->session->type == 'type1' ? $conference->getTimeLength1() : $conference->getTimeLength2());
                for ($timeslot = $start; $timeslot <= $end; $timeslot = date('H:i:s', (strtotime($timeslot) + ($timeslotLength * 60)))) {
                    $timeslots[] = date('H:i', strtotime($timeslot));
                }
                
                
                $this->view->days = $days;
                $this->view->children = $this->session->dataBook;
                $this->view->timeslots = $timeslots;
                $this->view->activeId = $activeDayId;
                $this->view->meantime = $conference->getMinimalMeantime();
                $this->view->timeslot = ($this->session->type == 'type1' ? $conference->getTimeLength1() : $conference->getTimeLength2());
                $this->view->firstStarts = $firstStarts;
                $this->view->lastEnds = $lastEnds;
                break;
            case 3:     //STEP 3
                //collect all booked appointments of parent to give an overview
                $appointments = array();
                foreach ($this->session->dataBook as $pupilData) {
                    foreach ($pupilData->getTeachers() as $teacher) {
                        if ($teacher->getToSee()) {
                            $date = strtotime(Application_Model_DbTable_ConferenceDays::getDateByConferenceDay($teacher->getAppointmentDay()) . $teacher->getAppointmentSlot());
                            $appointments[$date] = $teacher->getFirstname() . ' ' . $teacher->getName() . ' - ' . $teacher->getFunction() . ' - ' . $pupilData->getFirstname();
                        }
                    }
                }
                
                
                ksort($appointments);
                $this->view->bookedAppointments = $appointments;
                break;
        }
    }


    /**
     * Index action
     * Default action which displays general information about conferences to parents 
     */
    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            if (isset($formData['btnCancel'])) {
                $this->_helper->_redirector('index', 'Parent');
            }
            else if (isset($formData['btnSave'])) {
                //form check
                $allOk = true;
                $mailPattern = '/^[\w+\.\+-_]+@(([\w\+-])+\.)+[a-z]{2,4}$/i';
                $mail = (isset($formData['txtMail']) ? $formData['txtMail'] : ' ');
                
                if ($mail != '' && preg_match($mailPattern, $mail) == 0) {
                    $allOk = false;
                }
                
                if ($allOk) {
                    if ($mail != '') {
                        $this->parent->addMail($mail);
                    }
                    else {
                        $this->parent->removeMail();
                    }
                }
            }
        }
        
        if ($this->conferenceAvailable) {
            //load active conference to collect information about it
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

            //check if their are already appointments maded
            $titularMake = true;
            $teacherMake = true;
            if (sizeof($this->parent->getAppointmentsByConferenceWithInfo($this->activeConference, 'Type1')) != 0) {
                $titularMake = false;
            }
            if (sizeof($this->parent->getAppointmentsByConferenceWithInfo($this->activeConference, 'Type2')) != 0) {
                $teacherMake = false;
            }


            $this->view->conferenceName = $conference->getName();
            $this->view->start1 = DateUtilities::weekday(strtotime($conference->getStartSubscription1()), true) . ' ' . date('d', strtotime($conference->getStartSubscription1())) . ' ' . DateUtilities::month(strtotime($conference->getStartSubscription1()));
            $this->view->start2 = DateUtilities::weekday(strtotime($conference->getStartSubscription2()), true) . ' ' . date('d', strtotime($conference->getStartSubscription2())) . ' ' . DateUtilities::month(strtotime($conference->getStartSubscription2()));
            $this->view->end1 = DateUtilities::weekday(strtotime($conference->getDeadlineSubscription1()), true) . ' ' . date('d', strtotime($conference->getDeadlineSubscription1())) . ' ' . DateUtilities::month(strtotime($conference->getDeadlineSubscription1()));
            $this->view->end2 = DateUtilities::weekday(strtotime($conference->getDeadlineSubscription2()), true) . ' ' . date('d', strtotime($conference->getDeadlineSubscription2())) . ' ' . DateUtilities::month(strtotime($conference->getDeadlineSubscription2()));
            $this->view->bookTitular = $this->bookTitular;
            $this->view->bookTeacher = $this->bookTeacher;
            $this->view->titularMake = $titularMake;
            $this->view->teacherMake = $teacherMake;
        }
        
        $this->view->homeActive = true;            
        $this->view->action = 'index';
        $this->view->mail = $this->parent->getMail();
        $this->view->confirm = ($this->getRequest()->getParam('confirm') == 'ok');
    }
    
    
    /**
     * Logout action
     * Provides logout functionality to parents
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
     * Function processes a login attempt
     */
    public function proceedLogin()
    {
        $formdata = $this->getRequest()->getPost();

        //check if login action was cancelled
        if (isset($formdata['btnCancel'])) {
            $this->session->unsetAll();
            $this->_helper->_redirector('index', 'Index');
        }
        else if (isset($formdata['btnLogin'])) {
            $login = $formdata['txtLogin'];
            $password = $formdata['txtPassword'];
            
            //formcheck
            $allOk = true;            
            if ($login == '') {
                $allOk = false;
            }            
            if ($password == '') {
                $allOk = false;
            }
            
            if (!$allOk) {
                $this->view->formErr = true;
            }            
            else {
                try {
                    //try to authenticate user with provided information
                    $parent = Application_Model_DbTable_Parents::Authenticate($login, $password);
                }
                catch (Exception $e) {
                    switch ($e->getCode()) {
                        case 101: $this->view->userErr = true;
                            $login = '';
                            break;
                        case 125: $this->view->passErr = true;
                            break;
                    }

                    $allOk = false;
                }
                
                if ($allOk) {
                    $this->session->authAs = 'Parent';
                    $this->session->id = $parent->getParentId();
                    return true;
                }
            }

            $this->view->valLogin = $login;            
            $this->session->unsetAll();
            return false;
        }
    }

    
    /**
     * Function called by JavaScript to allocate a timeslot when a parent has selected one 
     */
    public function allocateslotAction()
    {
        //action called by jQuery -> no layout or rendering of view
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(false);
        
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            if ($formData['type'] == 1) {       //allocate slot for titular conference
                $res = Application_Model_DbTable_Appointments::allocateTitularAppointment($formData['id'], $formData['pupilId'], $this->session->id, $formData['slot'], $formData['dayId']);
                if (Application_Model_DbTable_Appointments::allocateTitularAppointment($formData['id'], $formData['pupilId'], $this->session->id, $formData['slot'], $formData['dayId'])) {
                    echo 'OK';
                }
                else {
                    echo 'NOT OK' . $res;
                }
            }
            else {      //allocate slot for teacher conference
                if (Application_Model_DbTable_Appointments::allocateTeacherAppointment($formData['id'], $formData['pupilId'], $this->session->id, $formData['slot'], $formData['dayId'])) {
                    echo 'OK';
                }
                else {
                    echo 'NOT OK';
                }
            }
        }
        else {
            echo 'NOT OK';
        }
    }

    
    /**
     * Function called by JavaScript to inform server that the temporary reservated appointments can be allocated for an other period
     */
    public function exceedleasetimeAction()
    {
        //action called by jQuery => no layout or rendering of view
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(false);
        
        if ($this->getRequest()->isPost()) {
            Application_Model_DbTable_Appointments::extendLeaseTemporaryAppointments($this->session->id);
            echo 'OK';
        }
        else {
            echo 'NOT OK';
        }
    }
}