<?php
/**
 *Db class for staff table 
 */
class Application_Model_DbTable_Staff extends Zend_Db_Table_Abstract
{
    protected $staffId, $name, $firstname;
    protected $_name = 'staff';
    
    
    
    public function __construct($staffId = null, $name = null, $firstname = null, $config = array()) {
        parent::__construct($config);
        $this->staffId = $staffId;
        $this->name = $name;
        $this->firstname = $firstname;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getFirstname() {
        return $this->firstname;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getStaffId() {
        return $this->staffId;
    }
    
    

    /**
     * Function returns array of all appointments of this teacher based on a specified conferenceDay
     * @param int $conferenceDayId conferenceDayId to identify conferenceDay
     * @return array of \Application_Model_DbTable_Appointments 
     */
    public function getAppointments($conferenceDayId) {
        $conferenceDayId = (int) $conferenceDayId;
        
        $select = $this->select();
        $select->from('appointments', '*')
                ->where('staffId = ? AND conferenceDayId = ? AND temporary IS NULL')
                ->bind(array($this->staffId, $conferenceDayId))
                ->order('appointment')
                ->setIntegrityCheck(false);
        $result = $this->fetchAll($select);
        
        $appointments = array();
        foreach ($result as $row) {
            $appointments[] = new Application_Model_DbTable_Appointments($row['appointmentId'], $row['staffId'], $row['pupilId'], $row['parentId'], $row['courseId'], $row['appointment'], $row['conferenceDayId'], $row['conferenceId'], $row['selfPlanned'], $row['confirmed']);
        }

        return $appointments;
    }
    
    
    /**
     * Function return appointment object of pupil with a teacher, based on courseId when appointment is of type 2
     * @param int $pupilId pupilId to identify pupil
     * @param int $parentId parentId to identify parent
     * @param int $conferenceId conferenceId to identify conference
     * @param int $courseId courseId to identify course
     * @return \Application_Model_DbTable_Appointments 
     */
    public function getAppointmentOfPupil($pupilId, $parentId, $conferenceId, $courseId = null) {
        $pupilId = (int) $pupilId;
        $courseId = ($courseId != null ? (int) $courseId : null);
        
        $select = $this->select();
        $select->from('appointments', '*')
                ->setIntegrityCheck(false);
        if ($courseId == null) {
            $select->where('staffId = ? AND pupilId = ? AND courseId IS NULL AND parentId = ? AND conferenceId = ? AND temporary IS NULL')
                ->bind(array($this->staffId, $pupilId, $parentId, $conferenceId));
        }
        else {
            $select->where('staffId = ? AND pupilId = ? AND courseId = ? AND parentId = ? AND conferenceId = ? AND temporary IS NULL')
                ->bind(array($this->staffId, $pupilId, $courseId, $parentId, $conferenceId));
        }
        $result = $this->fetchRow($select);
        
        if(sizeof($result) == 0) {
            return null;
        }
        return new Application_Model_DbTable_Appointments($result['appointmentId'], $result['staffId'], $result['pupilId'], $result['parentId'], $result['courseId'], $result['appointment'], $result['conferenceDayId'], $result['conferenceId'], $result['selfPlanned'], $result['confirmed']);
    }
    
    
    /**
     * Function returns an array of all available timeslots of each conferenceday in a seperated array, combinned in one array with conferenceDayId as key
     * @param int $conferenceId conferenceId to identify conference
     * @param int $parentId parentId to identify parent
     * @return array with available timeslots organised by conferenceDay
     */
    public function getAvailableTimes($conferenceId, $parentId) {
        $conferenceId = (int)$conferenceId;
        $parentId = ($parentId != null ? (int)$parentId : null);
        $conference = Application_Model_DbTable_Conferences::getConferenceById($conferenceId);
        
        $availableTimes = array();
        $unavailableTimes = $this->getUnAvailableTimes($conferenceId, $parentId);
        foreach ($this->getConferenceDays($conferenceId) as $conferenceDay) {
            $start = $conferenceDay->getStartTime();
            $end = $conferenceDay->getEndTime();
            $timeslotLength = (strtolower($conferenceDay->getType()) == 'type1' ? $conference->getTimeLength1() : $conference->getTimeLength2());
            
            
            for ($time = $start; $time < $end; $time = date('H:i:s', strtotime($time) + ($timeslotLength * 60))) {
                if (!in_array($time, $unavailableTimes[$conferenceDay->getConferenceDayId()])) {
                    $availableTimes[$conferenceDay->getConferenceDayId()][] = $time;
                }
            }
        }
        
        return $availableTimes;
    }
    
    
    /**
     * Function fills data object candidate
     * @param int $conferenceId conferenceId to identify conference
     * @param string $type idicate conference type (type1 | type2)
     * @return \Application_Model_Candidate 
     */
    public function getCandidates($conferenceId, $type) {
        $conferenceId = (int) $conferenceId;
        $type = strtolower($type);
        
        $select = $this->select();
        if ($type == 'type1') {            
            $select->from('participants', array())
                ->joinInner('groups', 'participants.groupId = groups.groupId', array())
                ->joinInner('pupils', 'groups.groupId = pupils.groupId', array('pupilId as pupilId', 'firstname', 'name'))
                ->joinInner('childrelations', 'pupils.pupilId = childrelations.childId', array())
                ->joinInner('parents', 'childrelations.parentId = parents.parentId', array('parentId as parentId', 'salutation'))
                ->where('titularId = ? AND participants.conferenceId = ?')
                ->bind(array($this->staffId, $conferenceId))
                ->setIntegrityCheck(false);
        }
        else {
            $select->from('participants', array())
                ->joinInner('groups_have_courses', 'participants.groupId = groups_have_courses.groupId', array())
                ->joinInner('courses', 'groups_have_courses.courseId = courses.courseId', array('courseId', 'course'))
                ->joinInner('pupils', 'groups_have_courses.groupId = pupils.groupId', array('pupilId as pupilId', 'firstname', 'name'))
                ->joinInner('childrelations', 'pupils.pupilId = childrelations.childId', array())
                ->joinInner('parents', 'childrelations.parentId = parents.parentId', array('parentId AS parentId', 'salutation'))
                ->where('teacherId = ? AND participants.conferenceId = ?')
                ->bind(array($this->staffId, $conferenceId))
                ->setIntegrityCheck(false);
        }
        $results = $this->fetchAll($select);
        
        $candidates = array();
        if ($type == 'type1') {
            foreach ($results as $result) {
                $candidates[] = new Application_Model_Candidate($result['pupilId'], $result['firstname'], $result['name'], $result['parentId'], $result['salutation']);
            }
        }
        else {
            foreach ($results as $result) {
                $candidates[] = new Application_Model_Candidate($result['pupilId'], $result['firstname'], $result['name'], $result['parentId'], $result['salutation'], $result['courseId'], $result['course']);
            }
        }
        
        return $candidates;
    }
    
    
    /**
     * Function returns an array of all conferenceDays of this teacher based on a specified conference
     * @param int $conferenceId conferenceId to identify conference
     * @return array of \Application_Model_DbTable_ConferenceDays
     */
    public function getConferenceDays($conferenceId) {
        $conferenceId = (int) $conferenceId;
        
        return Application_Model_DbTable_ConferenceDays::getConferenceDaysOfTeacher($conferenceId, $this->staffId);
    }
    
    
    /**
     * Function which returns allocated room of staffmember on specified conferenceDay
     * @param int  $conferenceDayId conferenceDayId to identify conferenceDay
     * @return string room name
     * @throws Exception Er131 No Roomallocation maded
     */
    public function getRoomByConferenceDay($conferenceDayId) {
        $conferenceDayId = (int) $conferenceDayId;
        
        $select = $this->select();
        $select->from('staff', array())
                ->joinInner('roomallocations', 'staff.staffId = roomallocations.staffId', array())
                ->joinInner('rooms', 'roomallocations.roomId = rooms.roomId', array('rooms.name as name'))
                ->setIntegrityCheck(false);
        $result = $this->fetchAll($select);
        
        if (sizeof($result) != 1) {
            throw new Exception('Er131 No Roomallocation maded');
        }
        
        return $result[0]['name'];
    }
    
    
    /**
     * Function returns array of all allocated timeslots of each conferenceday in a seperated array, combinned in one array with conferenceDayId as key
     * @param int $conferenceId conferenceId to identify conference
     * @param int $parentId parentId to identify parnet
     * @return array with unavailable timeslots organised by conferenceDay
     */
    public function getUnAvailableTimes($conferenceId, $parentId = null) {
        $conferenceId = (int)$conferenceId;
        $parentId = ($parentId != null ? (int)$parentId : null);
        $conference = Application_Model_DbTable_Conferences::getConferenceById($conferenceId);
        
        Application_Model_DbTable_Appointments::clearTemporaryAppointments();
        
        $unavailableTimeslots = array();
        foreach($conference->getConferenceDays() as $conferenceDay) {
            $unavailableTimeslots[$conferenceDay->getConferenceDayId()] = array();
        }
        
        $select = $this->select();
        $select->from('appointments', '*')
                ->joinInner('conferencedays', 'appointments.conferenceDayId = conferencedays.conferenceDayId', array())
                ->setIntegritycheck(false);
        
        if ($parentId != null) {
            $select->where('appointments.staffId = ? AND conferencedays.conference = ? AND (parentId != ? OR parentId IS NULL)')
                ->bind(array($this->staffId, $conferenceId, $parentId));
        }
        else {
            $select->where('appointments.staffId = ? AND conferencedays.conference = ?')
                ->bind(array($this->staffId, $conferenceId));
        }
        $appointments = $this->fetchAll($select);
        foreach ($appointments as $appointment) {
            $unavailableTimeslots[$appointment['conferenceDayId']][] = $appointment['appointment'];
        }
        
        foreach ($unavailableTimeslots as $unavailabletimeslot) {
            sort($unavailabletimeslot);
        }
        
        return $unavailableTimeslots;
    }
    
    
    /**
     * Function checks if staffmember is available
     * @param type $appointment time
     * @param int $conferenceDayId conferenceDayId to identify conferenceDay
     * @return boolean whether or not staffmember is available
     */
    public function isAvailableOn($appointment, $conferenceDayId) {
        Application_Model_DbTable_Appointments::clearTemporaryAppointments();
        
        $select = $this->select();
        $select->from('appointments', 'appointment')
                ->where('staffId = ? AND conferenceDayId = ?')
                ->bind(array($this->staffId, $conferenceDayId))
                ->setIntegrityCheck(false);
        $result = $this->fetchAll($select);
        
        foreach ($result as $row) {
            if(date('H:i',  strtotime($row['appointment'])) == $appointment) {
                return false;
            }
        }
        
        return true;
    }
    
    
    /**
     * Function to create an appointment for this teacher
     * @param int $pupilId pupilId to identify pupil
     * @param int $parentId parentId to identify parent
     * @param string $appointment time for appointment
     * @param int $conferenceDayId conferenceDayId to identify conferenceDay
     * @param int $courseId courseId to identify course
     * @return boolean whether or not the appointment is maded 
     */
    public function makeAppointment($pupilId, $parentId, $appointment, $conferenceDayId, $courseId = null) {
        $pupilId = (int) $pupilId;
        $parentId = (int) $parentId;
        $appointment = $appointment;
        $conferenceDayId = (int) $conferenceDayId;
        $courseId = (int) $courseId;
        
        $success = false;
        $this->_db->beginTransaction();
        if ($this->isAvailableOn($appointment, $conferenceDayId)) {
            $appointment = new Application_Model_DbTable_Appointments(null, $this->staffId, $pupilId, $parentId, ($courseId == 0 ? null : $courseId), $appointment, $conferenceDayId, Application_Model_DbTable_ConferenceDays::getConferenceDayById($conferenceDayId)->getConference(), true, false);
            $success = $appointment->save();
        }
        $this->_db->commit();
        
        return $success;
    }
    
    
    /**
     * Function returns an array of all available conferenceDays of a staff member
     * @param int $conferenceId conferenceId to identify conference
     * @return array of \Application_Model_DbTable_ConferenceDays 
     */
    public function getPresentDays($conferenceId) {
        $conferenceId = (int) $conferenceId;
        
        return Application_Model_DbTable_ConferenceDays::getConferenceDaysOfTeacher($conferenceId, $this->staffId);
    }
    
    
    /**
     * Function fills TeacherAppointment data object
     * @param int $conferenceId conferenceId to identify conference
     * @param string $type conference type (type1 | type2)
     * @return \Application_Model_TeacherAppointments 
     */
    public function getTeacherAppointments($conferenceId, $type) {
        $conferenceId = (int) $conferenceId;
        $type = (string) strtolower($type);
        
        $conference = Application_Model_DbTable_Conferences::getConferenceById($conferenceId);
        $appointmentDays = array();
        $conferenceDays = $conference->getConferenceDays();
        
        foreach ($conferenceDays as $conferenceDay) {
            if (strtolower($conferenceDay->getType()) != $type) {
                continue;
            }
            
            $start = null; $end = null; $day = null; $dayId = null; $room = null; $roomId= null; $present = null; $obligated = null; $appointments = null;
            $day = $conferenceDay->getDate();
            $dayId = $conferenceDay->getConferenceDayId();
            $obligated = $conferenceDay->getIsPrimary();
            $availableRooms = Application_Model_DbTable_Rooms::getAllAvailableRooms($conferenceDay->getConferenceDayId(), $this->staffId);

            $select = $this->select();
            $select->from('presentdays', array('earlyStart', 'lateEnd'))
                    ->joinLeft('roomallocations', 'presentdays.conferenceDayId = roomallocations.conferenceDayId AND presentdays.staffId = roomallocations.staffId', array())
                    ->joinLeft('rooms', 'roomallocations.roomId = rooms.roomId', array('roomId', 'name AS room'))
                    ->where('presentdays.conferenceDayId = ? AND presentdays.staffId = ?')
                    ->bind(array($conferenceDay->getConferenceDayId(), $this->staffId))
                    ->setIntegrityCheck(false);
            
            $result = $this->fetchRow($select);

            if ($result == null) {      //teacher not present
                $present = false;
                $start = $conferenceDay->getStartTime();
                $end = $conferenceDay->getEndTime();
            }
            else {      //teacher present
                $present = true;
                $start = ($result['earlyStart'] != null ? $result['earlyStart'] : $conferenceDay->getStartTime());
                $end = ($result['lateEnd'] != null ? $result['lateEnd'] : $conferenceDay->getEndTime());
                $room = $result['room'];
                $roomId = $result['roomId'];
                $appointments = $this->getAppointments($dayId);
            }

            $appointmentDays[] = new Application_Model_TeacherAppointments($start, $end, $day, $dayId, $room, $roomId, $present, $obligated, $appointments, $availableRooms);
        }
        
        return $appointmentDays;
    }
    
    
    
    /**
     * Function used to authenticate Staff, based on it's openId
     * @param string $openId
     * @return Application_Model_DbTable_Staff
     * @throws Exception Er101 Unexisting account
     */
    public static function Authenticate($openId) {
        $openId = (string) $openId;
        
        $staffmember = new Application_Model_DbTable_Staff();
        $select = $staffmember->select();
        $select->from('staff', 'staffId')
                ->where('openId = ?')
                ->bind(array($openId));
        $result = $staffmember->fetchAll($select);
        
        if (sizeof($result) < 1) {
            throw new Exception('Er101 Unexisting account', 101);
        }
        
        return Application_Model_DbTable_Staff::getStaffById($result[0]['staffId']);
    }
    
    
    /**
     * Function counts the amount of records in this table
     * @return int amount of records
     */
    public static function countEntries() {
        $staffmember = new Application_Model_DbTable_Staff();
        $select = $staffmember->select();
        $select->from('staff', 'count(*) As amount')
                ->setIntegrityCheck(false);
        $result = $staffmember->fetchRow($select);
        
        return $result['amount'];
    }
    
    
    /**
     * Function deletes all data in staff table except for the given staffId
     * @param int $staffId staffmember you do not want to delete
     */
    public static function deleteAllExcept($staffId) {
        $staffmember = new Application_Model_DbTable_Staff();        
        $staffmember->delete('staffId != ' . $staffmember->_db->quote($staffId, 'INT'));
    }
    
    
    /**
     * Function returns full name of staff member, based on its id
     * @param int $staffId staffId to identify staffmember
     * @return string full name of staffmember
     */
    public static function getFullNameById($staffId) {
        $staffId = (int) $staffId;
        
        $staffmember = new Application_Model_DbTable_Staff();
        $select = $staffmember->select();
        $select->from('staff', array('firstname', 'name'))
                ->where('staffId = ?')
                ->bind(array($staffId));
        $result = $staffmember->_fetch($select);
        
        if (sizeof($result) != 1) {
            throw new Exception('Er100 Id not found');
        }
        
        return $result[0]['firstname'] . ' ' . $result[0]['name'];
    }
    
    
    /**
     * Function returns staff object, based on it's id
     * @param int $staffId staffId to identify staffmember
     * @return \Application_Model_DbTable_Staff
     * @throws Exception Er100 Id not found
     */
    public static function getStaffById($staffId) {
        $staffId = (int) $staffId;
        
        $staffmember = new Application_Model_DbTable_Staff();
        $select = $staffmember->select();
        $select->from('staff', '*')
                ->where('staffId = ?')
                ->bind($staffId);
        $result = $staffmember->fetchAll($select);
        
        if (sizeof($result) != 1) {
            throw new Exception('Er100 Id not found');
        }
        
        $staffmember->staffId = $result[0]['staffId'];
        $staffmember->name = $result[0]['name'];
        $staffmember->firstname = $result[0]['firstname'];
        return $staffmember;
    }
    
    
    /**
     * Function updates staff table based on a data array
     * @param array $staff array of arrays with following structure array(('name', 'firstname', 'openId'))
     * @throws Exception Er151 Update failed
     */
    public static function updateStaff($staff) {
        $staffmember = new Application_Model_DbTable_Staff();
        
        $succes = true;
        $information = '';
        $staffmember->_db->beginTransaction();
        try {
            foreach ($staff as $staffData) {
                $information = $staff['ipenId'];
                
                $select = $staffmember->select();
                $select->from('staff', 'count(*) AS amount')
                        ->where('openId = ?')
                        ->bind(array($staffData['openId']));
                $result = $staffmember->fetchRow($select);
                
                if ($result['amount'] == 0) {
                    $staffmember->insert($staffData);
                } else {
                    $staffmember->update($staffData, 'openId = ' . $staffmember->_db->quote($staffData['openId'], 'VARCHAR'));
                }
            }
        } catch (Exception $e) {
            $succes = false;
        }
        
        if ($succes) {
            $staffmember->_db->commit();
        } else {
            $staffmember->_db->rollBack();
            throw new Exception('update of ' . $information . ' failed', 151);
        }
    }
}