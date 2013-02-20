<?php
/**
 * Db class for conferences table 
 */
class Application_Model_DbTable_Conferences extends Zend_Db_Table_Abstract
{
    protected $conferenceId, $name, $deadlineSubscription1, $deadlineSubscription2, $startSubscription1, $startSubscription2, $deadlineDaySelection, $timeslotLength1, $timeslotLength2, $minimalMeantime;
    protected $_name = 'conferences';


    
    public function __construct($conferenceId = null, $name = null, $deadlineSubscription1 = null, $deadlineSubscription2 = null, $startSubscription1 = null, $startSubscription2 = null, $deadlineDaySelection = null, $timeslotLength1 = null, $timeslotLength2 = null, $minimalMeantime = null, $config = array()) {
        parent::__construct($config);
        $this->conferenceId = $conferenceId;
        $this->name = $name;
        $this->deadlineSubscription1 = $deadlineSubscription1;
        $this->deadlineSubscription2 = $deadlineSubscription2;
        $this->startSubscription1 = $startSubscription1;
        $this->startSubscription2 = $startSubscription2;
        $this->deadlineDaySelection = $deadlineDaySelection;
        $this->timeslotLength1 = $timeslotLength1;
        $this->timeslotLength2 = $timeslotLength2;
        $this->minimalMeantime = $minimalMeantime;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getConferenceId() {
        return $this->conferenceId;
    }
    
    public function getDeadlineDaySelection() {
        return $this->deadlineDaySelection;
    }
    
    public function getDeadlineSubscription1() {
        return $this->deadlineSubscription1;
    }
    
    public function getDeadlineSubscription2() {
        return $this->deadlineSubscription2;
    }
    
    public function getMinimalMeantime() {
        return $this->minimalMeantime;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getStartSubscription1() {
        return $this->startSubscription1;
    }
    
    public function getStartSubscription2() {
        return $this->startSubscription2;
    }
    
    public function getTimeLength1() {
        return $this->timeslotLength1;
    }
    
    public function getTimeLength2() {
        return $this->timeslotLength2;
    }
    
    public function setDeadlineSubscription1($deadlineSubscription1) {
        $deadlineSubscription1 = (string) $deadlineSubscription1;
        $this->deadlineSubscription1 = $deadlineSubscription1;
    }
    
    public function setDeadlineSubscription2($deadlineSubscription2) {
        $deadlineSubscription2 = (string) $deadlineSubscription2;
        $this->deadlineSubscription2 = $deadlineSubscription2;
    }
    
    public function setMinimalMeantime($minimalMeantime) {
        $minimalMeantime = (int) $minimalMeantime;
        $this->minimalMeantime = $minimalMeantime;
    }
    
    public function setName($name) {
        $name = (string) $name;
        $this->name = $name;
    }
    
    public function setStartSubscription1($startSubscription1) {
        $startSubscription1 = (string) $startSubscription1;
        $this->startSubscription1 = $startSubscription1;
    }
    
    public function setStartSubscription2($startSubscription2) {
        $startSubscription2 = (string) $startSubscription2;
        $this->startSubscription2 = $startSubscription2;
    }
    
    public function setTimeLength1($timeLength1) {
        $timeLength1 = (int) $timeLength1;
        $this->timeslotLength1 = $timeLength1;
    }
    
    public function setTimeLength2($timeLength2) {
        $timeLength2 = (int) $timeLength2;
        $this->timeslotLength2 = $timeLength2;
    }
    
    
    
    /**
     * Function deletes conference object
     * @throws Exception Er150 Null in non optional field
     */
    public function deleteConference() {
        if ($this->conferenceId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        $this->delete('conferenceId = ' . $this->_db->quote($this->conferenceId, 'INT'));
    }
    
    
    /**
     * Function returns an array of all conferenceDays of a specified conference
     * @return array of conferenceDay objects 
     */
    public function getConferenceDays() {
        return Application_Model_DbTable_ConferenceDays::getConferenceDaysOfConference($this->conferenceId);
    }
    
    
    /**
     * Function returns latest endtime of conference based on a given conference type
     * @param string $type indicates conference type (type1 | type2)
     * @return string latest endtime 
     */
    public function getEndTime($type) {
        $select = $this->select();
        $select->from('conferencedays', array('MAX(endTime) as end'))
                ->joinInner('presentdays', 'conferencedays.conferenceDayId = presentdays.conferenceDayId', 'MAX(lateEnd) as lateEnd')
                ->where(sprintf('conferencedays.type = "%s"', (strtolower($type) != 'type2' ? 'Type1' : 'Type2')))
                ->setIntegrityCheck(false);
        $result = $this->fetchRow($select);
        
        return ($result['end'] < $result['lateEnd'] ? $result['lateEnd'] : $result['end']);
    }
    
    
    /**
     * Function returns earliest starttime of conference based on a given conference type
     * @param string $type indicates conference type (type1 | type2)
     * @return string earliest starttime 
     */
    public function getStartTime($type) {
        $select = $this->select();
        $select->from('conferencedays', array('MIN(startTime) as start'))
                ->joinInner('presentdays', 'conferencedays.conferenceDayId = presentdays.conferenceDayId', 'MIN(earlyStart) as earlyStart')
                ->where(sprintf('conferencedays.type = "%s"', (strtolower($type) != 'type2' ? 'Type1' : 'Type2')))
                ->setIntegrityCheck(false);
        $result = $this->fetchRow($select);
        
        return ($result['start'] > $result['earlyStart'] ? $result['earlyStart'] : $result['start']);
    }
    
    
    /**
     * Function saves Conference object to database if all required fields have a value
     * @return int id of inserted/updated conference
     */
    public function save() {
        if ($this->name == null || $this->deadlineSubscription1 == null || $this->deadlineSubscription2 == null || $this->startSubscription1 == null || $this->startSubscription2 == null || $this->timeslotLength1 == null || $this->timeslotLength2 == null || $this->minimalMeantime == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        else if ($this->conferenceId != null) {
            $select = $this->select();
            $select->from('conferences', 'count(*) AS amount')
                    ->where('conferenceId = ?')
                    ->bind(array($this->conferenceId));
            $result = $this->fetchRow($select);
            
            if ($result['amount'] == 0) {
                throw new Exception('Er105 Unexisting Conference');
            }
            
            $this->update(array('name' => $this->name, 'deadlineSubscription1' => Date('Y-m-d', strtotime($this->deadlineSubscription1)), 'deadlineSubscription2' => Date('Y-m-d', strtotime($this->deadlineSubscription2)), 'startSubscription1' => Date('Y-m-d', strtotime($this->startSubscription1)), 'startSubscription2' => Date('Y-m-d', strtotime($this->startSubscription2)), 'timeslotLength1' => $this->timeslotLength1, 'timeslotLength2' => $this->timeslotLength2, 'minimalMeantime' => $this->minimalMeantime), 'conferenceId = ' . $this->_db->quote($this->conferenceId, 'INT'));
            return $this->conferenceId;
        }
        else {
            return $this->insert(array('name' => $this->name, 'deadlineSubscription1' => Date('Y-m-d', strtotime($this->deadlineSubscription1)), 'deadlineSubscription2' => Date('Y-m-d', strtotime($this->deadlineSubscription2)), 'startSubscription1' => Date('Y-m-d', strtotime($this->startSubscription1)), 'startSubscription2' => Date('Y-m-d', strtotime($this->startSubscription2)), 'timeslotLength1' => $this->timeslotLength1, 'timeslotLength2' => $this->timeslotLength2, 'minimalMeantime' => $this->minimalMeantime));
        }
    }
    
    
    
    /**
     * Function returns array of all conferences
     * @return array with params for each conference 
     */
    public static function getAllConferences() {
        $conference = new Application_Model_DbTable_Conferences();
        $select = $conference->select();
        $select->from('conferences', '*');
        $result = $conference->fetchAll($select);
        
        return $result;
    }
    
    
    /**
     * Function deletes all data from conference table
     */
    public static function deleteAll() {
        $conference = new Application_Model_DbTable_Conferences();
        $conference->delete('');
    }
    
    
    /**
     * Function returns conference object from database, based on its id
     * @param int $conferenceId conferenceId to identify conference
     * @return \Application_Model_DbTable_Conferences
     * @throws Exception Er105 Unexisting Conference
     */
    public static function getConferenceById($conferenceId) {
        $conferenceId = (int) $conferenceId;
        
        $conference = new Application_Model_DbTable_Conferences();
        $select = $conference->select();
        $select->from('conferences', '*')
                ->where('conferenceId = ?')
                ->bind(array($conferenceId));
        $result = $conference->fetchAll($select);
        
        if (sizeof($result) != 1) {
            throw new Exception('Er105 Unexisting Conference');
        }
        
        $conference->conferenceId = $result[0]['conferenceId'];
        $conference->name = $result[0]['name'];
        $conference->deadlineSubscription1 = $result[0]['deadlineSubscription1'];
        $conference->deadlineSubscription2 = $result[0]['deadlineSubscription2'];
        $conference->startSubscription1 = $result[0]['startSubscription1'];
        $conference->startSubscription2 = $result[0]['startSubscription2'];
        $conference->deadlineDaySelection = $result[0]['deadlineDaySelection'];
        $conference->timeslotLength1 = $result[0]['timeslotLength1'];
        $conference->timeslotLength2 = $result[0]['timeslotLength2'];
        $conference->minimalMeantime = $result[0]['minimalMeantime'];
        return $conference;
    }
    
    
    /**
     * Function returns array with information of all responsibles participating in the specified conference.
     * When value of 'room' is not null, their is a problem with roomallocations to this responsible
     * @param int $conferenceId to identify conference
     * @return array ['function', 'name', 'firstname', 'staffId', 'room'] 
     */
    public static function getResponsiblesOfConference($conferenceId) {
        $conferenceId = (int) $conferenceId;
        
        $conference = new Application_Model_DbTable_Conferences();
        $select = $conference->select();
        $select->from('participants', array())
                ->joinInner('groups', 'participants.groupId = groups.groupId', array())
                ->joinInner('responsibles', 'groups.yearId = responsibles.yearId', array('function AS function'))
                ->joinInner('staff', 'responsibles.staffId = staff.staffId', array('staff.staffId AS staffId', 'staff.name AS name', 'staff.firstname AS firstname'))
                ->joinInner('conferencedays', 'participants.conferenceId = conferencedays.conference', array())
                ->joinInner('presentdays', 'conferencedays.conferenceDayId = presentdays.conferenceDayId', array())
                ->joinLeft('roomallocations', 'staff.staffId = roomallocations.staffId AND presentdays.conferenceDayId = roomallocations.conferenceDayId', array('sum(roomId IS NULL) AS room'))
                ->order('responsibles.function')
                ->where('participants.conferenceId = ? AND conferencedays.type = "Type1"')
                ->bind(array($conferenceId))
                ->group('staff.staffId')
                ->setIntegrityCheck(false);
        $result = $conference->fetchAll($select);
        return $result;
    }
    
    
    /**
     * Function returns array with information of all teachers participating in the specified conference.
     * When value of 'room' is not null, their is a problem with roomallocations to this teacher
     * @param int $conferenceId to identify conference
     * @return array ['name', 'firstname', 'staffId', 'room'] 
     */
    public static function getTeachersOfConference($conferenceId) {
        $conferenceId = (int) $conferenceId;
        
        $conference = new Application_Model_DbTable_Conferences();
        $select = $conference->select();
        $select->from('participants', array())
                ->joinInner('groups_have_courses', 'participants.groupId = groups_have_courses.groupId', array())
                ->joinInner('courses', 'groups_have_courses.courseId = courses.courseId', array())
                ->joinInner('staff', 'courses.teacherId = staff.staffId', array('staff.name AS name', 'staff.firstname AS firstname', 'staff.staffId AS staffId'))
                ->joinInner('conferencedays', 'participants.conferenceId = conferencedays.conference', array())
                ->joinInner('presentdays', 'conferencedays.conferenceDayId = presentdays.conferenceDayId', array())
                ->joinLeft('roomallocations', 'staff.staffId = roomallocations.staffId AND presentdays.conferenceDayId = roomallocations.conferenceDayId', array('sum(roomId IS NULL) AS room'))
                ->where('participants.conferenceId = ? AND conferencedays.type = "Type2"')
                ->bind(array($conferenceId))
                ->group('staff.staffId')
                ->setIntegrityCheck(false);
        $result = $conference->fetchAll($select);
        
        return $result;
    }
    
    
    /**
     * Function returns array with information of all titulars participating in the specified conference.
     * When value of 'room' is not null, their is a problem with roomallocations to this titular
     * @param int $conferenceId to identify conference
     * @return array ['groupName', 'name', 'firstname', 'staffId', 'room'] 
     */
    public static function getTitularsOfConference($conferenceId) {
        $conferenceId = (int) $conferenceId;
        
        $groups = new Application_Model_DbTable_Groups();
        $select = $groups->select();
        $select->from('participants', array())
                ->joinInner('groups', 'participants.groupId = groups.groupId', array('groups.name AS groupName'))
                ->joinInner('staff', 'groups.titularId = staff.staffId', array('staff.staffId AS staffId' ,'staff.name AS name', 'staff.firstname AS firstname'))
                ->joinInner('conferencedays', 'participants.conferenceId = conferencedays.conference', array())
                ->joinInner('presentdays', 'conferencedays.conferenceDayId = presentdays.conferenceDayId', array())
                ->joinLeft('roomallocations', 'staff.staffId = roomallocations.staffId AND presentdays.conferenceDayId = roomallocations.conferenceDayId', array('sum(roomId IS NULL) AS room'))
                ->order('groups.name')
                ->where('participants.conferenceId = ? AND conferencedays.type = "Type1"')
                ->bind(array($conferenceId))
                ->group('staff.staffId')
                ->setIntegrityCheck(false);
        $result = $groups->fetchAll($select);
        return $result;
    } 
}