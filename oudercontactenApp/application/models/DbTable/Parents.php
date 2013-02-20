<?php
/**
 * Db class for parents table 
 */
class Application_Model_DbTable_Parents extends Zend_Db_Table_Abstract
{
    protected $parentId, $salutation, $mail;
    protected $_name = 'parents';

    
    
    public function __construct($parentId = null, $salutation = null, $mail = null, $config = array()) {
        parent::__construct($config);
        $this->parentId = $parentId;
        $this->salutation = $salutation;
        $this->mail = $mail;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getMail() {
        return $this->mail;
    }
    
    public function getParentId() {
        return $this->parentId;
    }
    
    public function getSalutation() {
        return $this->salutation;
    }
    
    
    
    /**
     * Function adds parents mail address
     * @param string $mail mail address of parent
     * @throws Exception Er150 Null in non optional field
     */
    public function addMail($mail) {
        $mail = (string) $mail;
        if ($this->parentId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        $this->update(array('mail' => $mail), 'parentId = ' . $this->_db->quote($this->parentId, 'INT'));
        $this->mail = $mail;
    }
    
    
    /**
     * Function returns array of all appointments from this parent
     * @return array of \Application_Model_DbTable_Appointments
     */
    public function getAppointments() {
        $select = $this->select();
        $select->from('appointments', '*')
                ->where('parentId = ? AND temporary IS NULL')
                ->bind(array($this->parentId))
                ->order(array('conferenceDayId', 'appointment'))
                ->setIntegrityCheck(false);
        $result = $this->fetchAll($select);
        
        $appointments = array();
        foreach ($result as $row) {
            $appointments[] = new Application_Model_DbTable_Appointments($row['appointmentId'], $row['staffId'], $row['pupilId'], $row['parentId'], $row['courseId'], $row['appointment'], $row['conferenceDayId'], $row['conferenceId'], $row['selfPlanned'], $row['confirmed']);
        }
        return $appointments;
    }
    
    
    /**
     * Function returns array with information about each available appointment, based on a specified conference and type
     * @param int $conferenceId conferenceId to identify conference
     * @param string $conferenceType indication of conference type (Type1 | Type2)
     * @return array ['cName', 'cFirstname', 'remark1', 'remark2', 'remark3', 'sName', 'sFirstname', 'data', 'room'] 
     */
    public function getAppointmentsByConferenceWithInfo($conferenceId, $conferenceType) {
        $conferenceId = (int) $conferenceId;
        $conferenceType = ($conferenceType != 'Type1' && $conferenceType != 'Type2' ? 'Type1' : $conferenceType);
        
        $select = $this->select();
        $select->from('appointments', 'appointment')
                ->joinInner('pupils', 'appointments.pupilId = pupils.pupilId', array('name as cName', 'firstname as cFirstname'))
                ->joinInner('groups', 'pupils.groupId = groups.groupId', array('remark AS remark1'))
                ->joinLeft('courses', 'appointments.courseId = courses.courseId', array('course', 'remark AS remark2'))
                ->joinInner('staff', 'appointments.staffId = staff.staffId', array('name as sName', 'firstname as sFirstname'))
                ->joinLeft('responsibles', 'staff.staffId = responsibles.staffId', array('function as function', 'remark AS remark3'))
                ->joinInner('conferencedays', 'appointments.conferenceDayId = conferencedays.conferenceDayId', 'date')
                ->joinLeft('roomallocations', 'appointments.conferenceDayId = roomallocations.conferenceDayId AND appointments.staffId = roomallocations.staffId', array())
                ->joinLeft('rooms', 'roomallocations.roomId = rooms.roomId', array('name as room'))
                ->distinct()
                ->where('appointments.parentId = ? AND conferencedays.conference = ? AND conferencedays.type = ? AND temporary IS NULL')
                ->bind(array($this->parentId, $conferenceId, $conferenceType))
                ->order(array('date', 'appointment'))
                ->setIntegrityCheck(false);
        $result = $this->fetchAll($select);
        return $result;
    }
    
    
    /**
     * Function returns array with information about each available appointment
     * @return array ['cName', 'cFirstname', 'course', 'sName', 'sFirstname', 'function', 'date', 'room'] 
     */
    public function getAppointmentsWithInfo() {
        $select = $this->select();
        $select->from('appointments', 'appointment')
                ->joinInner('pupils', 'appointments.pupilId = pupils.pupilId', array('name as cName', 'firstname as cFirstname'))
                ->joinInner('groups', 'pupils.groupId = pupils.groupId', array())
                ->joinLeft('courses', 'appointments.courseId = courses.courseId', array('course'))
                ->joinInner('staff', 'appointments.staffId = staff.staffId', array('name as sName', 'firstname as sFirstname'))
                ->joinLeft('responsibles', 'staff.staffId = responsibles.staffId', array('function as function'))
                ->joinInner('conferencedays', 'appointments.conferenceDayId = conferencedays.conferenceDayId', 'date')
                ->joinInner('roomallocations', 'appointments.conferenceDayId = roomallocations.conferenceDayId AND appointments.staffId = roomallocations.staffId', array())
                ->joinInner('rooms', 'roomallocations.roomId = rooms.roomId', array('name as room'))
                ->distinct()
                ->where('appointments.parentId = ? AND temporary IS NULL')
                ->bind(array($this->parentId))
                ->order(array('date', 'appointment'))
                ->setIntegrityCheck(false);
        $result = $this->fetchAll($select);
        
        return $result;
    }
    
    
    /**
     * Function returns array with all children as pupil objects
     * @return array of \Application_Model_DbTable_Pupils 
     */
    public function getChildren() {
        $select = $this->select();
        $select->setIntegrityCheck(false)
                ->from('childrelations', array())
                ->joinInner('pupils', 'childrelations.childId = pupils.pupilId')
                ->where('parentId = ?')
                ->bind(array($this->parentId));
        $result = $this->_fetch($select);
        
        $children = array();
        foreach ($result as $row) {
            $children[] = new Application_Model_DbTable_Pupils($row['pupilId'], $row['name'], $row['firstname'], $row['groupId']);
        }
        
        return $children;
    }
    
    
    /**
     * Function returns an array with information about all conferences available to one or more children of this parent.
     * @return array['conferenceId', 'name'] 
     */
    public function getConferences() {
        $select = $this->select();
        $select->from('parents', array())
                ->joinInner('childrelations', 'parents.parentId = childrelations.parentId', array())
                ->joinInner('pupils', 'childrelations.childId = pupils.pupilId', array())
                ->distinct()
                ->joinInner('participants', 'pupils.groupId = participants.groupId', array())
                ->joinInner('conferences', 'participants.conferenceId = conferences.conferenceId', array('conferences.conferenceId as conferenceId', 'conferences.name as name'))
                ->where('parents.parentId = ?')
                ->bind(array($this->parentId))
                ->setIntegrityCheck(false);
        $result = $this->fetchAll($select);
        
        return $result;
    }
    
    
    /**
     * Function removes mail address of parent
     * @throws Exception Er150 Null in non optional field
     */
    public function removeMail() {
        if ($this->parentId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        $this->update(array('mail' => null), 'parentId = ' . $this->_db->quote($this->parentId, 'INT'));
        $this->mail = null;
    }
    
    
    
    /**
     * Function used to authenticate Parents using parent login and password
     * @param string $login parent login
     * @param string $password parent password
     * @return Application_Model_DbTable_Parents
     * @throws Exception Er101 Unexisting account
     * @throws Exception Er125 Illegal password
     */
    public static function Authenticate($login, $password) {
        $login = (string) $login;
        $password = (string) md5($password);
        
        $parent = new Application_Model_DbTable_Parents();
        $select = $parent->select();
        $select->from('parents', array('parentId', 'password'))
                ->where('login = ?')
                ->bind(array($login));
        $result = $parent->_fetch($select);
        
        //check if user exists and password is correct
        if (sizeof($result) < 1) {
            throw new Exception('Er101 Unexisting account', 101);
        }
        else if ($password != $result[0]['password']) {
            throw new Exception('Er125 Illegal password', 125);
        }
        
        return self::getParentByParentId($result[0]['parentId']);
    }
    
    
    /**
     * Function counts the amount of records in this table
     * @return int amount of records
     */
    public static function countEntries() {
        $parent = new Application_Model_DbTable_Parents();
        $select = $parent->select();
        $select->from('parents', 'count(*) As amount')
                ->setIntegrityCheck(false);
        $result = $parent->fetchRow($select);
        
        return $result['amount'];
    }
    
    
    /**
     * Function returns parents object from database, based on it's id
     * @param int $parentId parentId to identify parent
     * @return \Application_Model_DbTable_Parents
     * @throws Exception Er101 Unexisting account
     */
    public static function getParentByParentId($parentId) {
        $parentId = (int) $parentId;
        
        $parent = new Application_Model_DbTable_Parents();
        $select = $parent->select();
        $select->from('parents', array('parentId', 'salutation', 'mail'))
                ->where('parentId = ?')
                ->bind(array($parentId));
        $result = $parent->_fetch($select);
        
        //check if user exists and password is correct
        if (sizeof($result) < 1) {
            throw new Exception('Er101 Unexisting account');
        }
        
        $parent->parentId = $result[0]['parentId'];
        $parent->salutation = $result[0]['salutation'];
        $parent->mail = $result[0]['mail'];
        return $parent;
    }
    
    
    
    /**
     * Function deletes all data from parents table
     */
    public static function deleteAll() {
        $parent = new Application_Model_DbTable_Parents();
        $parent->delete('');
    }
    
    
    /**
     * Function updates parents table based on a data array
     * @param array $data array of arrays with following structure array(('salutation', 'login', 'password'))
     * @throws Exception Er151 Update failed
     */
    public static function updateParents($data) {
        $parents = new Application_Model_DbTable_Parents();
        
        $succes = true;
        $information = '';
        $parents->_db->beginTransaction();
        try {
            foreach ($data as $parent) {
                $information = $parent['login'];
                
                $select1 = $parents->select();
                $select1->from('parents', 'count(*) AS amount')
                        ->where('login = ?')
                        ->bind(array($parent['login']))
                        ->setIntegrityCheck(false);
                $result1 = $parents->fetchRow($select1);
                if ($result1['amount'] == 0) {       //insert
                    $parents->insert(array('salutation' => $parent['salutation'], 'login' => $parent['login'], 'password' => md5($parent['password'])));
                }
                else {      //update
                    $parents->update(array('salutation' => $parent['salutation'], 'password' => md5($parent['password'])), 'login = ' . $parents->_db->quote($parent['login']));
                }
            }
        }
        catch (Exception $e) {
            $succes = false;
        }
        
        if ($succes) {
            $parents->_db->commit();
        }
        else {
            $parents->_db->rollBack();
            throw new Exception('update of ' . $information . ' failed', 151);
        }
    }
}