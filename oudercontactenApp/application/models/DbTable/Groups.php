<?php
/**
 * Db class for groups table 
 */
class Application_Model_DbTable_Groups extends Zend_Db_Table_Abstract
{
    protected $groupId, $yearId, $name, $titularId, $remark;
    protected $_name = 'groups';

    
    
    public function __construct($groupId = null, $yearId = null, $name = null, $titularId = null, $remark = null, $config = array()) {
        parent::__construct($config);
        $this->groupId = $groupId;
        $this->yearId = $yearId;
        $this->name = $name;
        $this->titularId = $titularId;
        $this->remark = $remark;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getGroupId() {
        return $this->groupId;
    }
    
    public function getName() {
        return $this->name;
    }
    
     public function getRemark() {
        return $this->remark;
    }
    
    public function getTitularId() {
        return $this->titularId;
    }
    
    public function getYear() {
        return Application_Model_DbTable_Years::getYearById($this->yearId);
    }
   
    
    
    /**
     * Function returns array of all courses for this group
     * @return array of \Application_Model_DbTable_Courses 
     */
    public function getCourses() {
        $select = $this->select();
        $select->from('groups_have_courses', array())
                ->joinInner('courses', 'groups_have_courses.courseId = courses.courseId', array('courseId', 'teacherId', 'course', 'remark'))
                ->where('groupId = ?')
                ->bind(array($this->groupId))
                ->order(array('courses.course'))
                ->setIntegrityCheck(false);
        $result = $this->_fetch($select);
        
        $courses = array();
        foreach ($result as $row) {
            $courses[] = new Application_Model_DbTable_Courses($row['courseId'], $row['teacherId'], $row['course'], $row['remark']);
        }
        
        return $courses;
    }
    
    
    /**
     * Function returns array of all responsibles for this classroup
     * @return array of \Application_Model_DbTable_Responsibles 
     */
    public function getResponsibles() {
        return Application_Model_DbTable_Responsibles::getResponsiblesByYear($this->yearId);
    }
    
    
    /**
     * Function returns full name of titular
     * @return string full name of titular 
     */
    public function getTitular() {
        return Application_Model_DbTable_Staff::getFullNameById($this->titularId);
    }
    
    
    /**
     * Function returns titular as staff object
     * @return \Application_Model_DbTable_Staff 
     */
    public function getTitularAsStaff() {
        return Application_Model_DbTable_Staff::getStaffById($this->titularId);
    }
    
    
    
    /**
     * Function counts the amount of records in this table
     * @return int amount of records
     */
    public static function countEntries() {
        $group = new Application_Model_DbTable_Groups();
        $select = $group->select();
        $select->from('groups', 'count(*) As amount')
                ->setIntegrityCheck(false);
        $result = $group->fetchRow($select);
        
        return $result['amount'];
    }
    
    
    /**
     * Function deletes all data from groups table
     */
    public static function deleteAll() {
        $group = new Application_Model_DbTable_Groups();
        $group->delete('');
    }
    
    
    /**
     * Functions returns all groups in an array, with their groupId as index and name as value
     * @return array 
     */
    public static function getAllGroups() {
        $group = new Application_Model_DbTable_Groups();
        $select = $group->select();
        $select->from('groups', array('groupId', 'name'))
                ->order('name');
        $results = $group->fetchAll($select);
        
        $groups = array();
        foreach ($results as $result) {
            $groups[$result['groupId']] = $result['name'];
        }
        
        return $groups;
    }
    
    
    /**
     * Returns group object from database, based on its id
     * @param int $groupId groupId to identify group
     * @return \Application_Model_DbTable_Groups
     * @throws Exception Er102 Unexisting group
     */
    public static function getGroupByGroupId($groupId) {
        $groupId = (int) $groupId;
        
        $group = new Application_Model_DbTable_Groups();
        $select = $group->select();
        $select->from('groups', '*')
                ->where('groupId = ?')
                ->bind(array($groupId));
        $result = $group->_fetch($select);
        
        //check if group exists
        if (sizeof($result) < 1) {
            throw new Exception('Er102 Unexisting group');
        }
        
        $group->groupId = $result[0]['groupId'];
        $group->yearId = $result[0]['yearId'];
        $group->name = $result[0]['name'];
        $group->titularId = $result[0]['titularId'];
        $group->remark = $result[0]['remark'];
        return $group;
    }
    
    
    /**
     * Function checks if a staffmemeber is a titular of a group for which a conference is organized
     * @param int $staffId staffId to identify staffmember
     * @param int $conferenceId conferenceId to identify conference
     * @return boolean whether or not a titular is participating on a conference
     */
    public static function isTitularOnConference($staffId, $conferenceId) {
        $staffId = (int) $staffId;
        $conferenceId = (int) $conferenceId;
        $group = new Application_Model_DbTable_Groups();
        
        $select = $group->select();
        $select->from('participants', 'count(*) AS amount')
                ->joinInner('groups', 'participants.groupId = groups.groupId', array())
                ->where('groups.titularId = ? AND participants.conferenceId = ?')
                ->bind(array($staffId, $conferenceId))
                ->setIntegrityCheck(false);
        $result = $group->fetchRow($select);
        
        if ($result['amount'] == 0) {
            return false;
        }
        
        return true;
    }
        
    
    /**
     * Function updates groups table based on a data array
     * @param array $data array of arrays with following structure array(('yearId', 'name', 'titular'))
     * @throws Exception Er151 Update failed
     */
    public static function updateGroups($data) {
        $groups = new Application_Model_DbTable_Groups();
        
        $succes = true;
        $information = '';
        $groups->_db->beginTransaction();
        try {
            foreach($data as $group) {
                $information = $group['name'];
                
                //determine staffId, based on google-account
                $select1 = $groups->select();
                $select1->from('staff', 'staffId')
                        ->where('openId = ?')
                        ->bind(array($group['titular']))
                        ->setIntegritycheck(false);
                $result1 = $groups->fetchRow($select1);
                $titularId = $result1['staffId'];
                
                //determine if update or insert has to be executed
                $select2 = $groups->select();
                $select2->from('groups', 'count(*) AS amount')
                        ->where('name = ?')
                        ->bind(array($group['name']))
                        ->setIntegrityCheck(false);
                $result2 = $groups->fetchRow($select2);
                if ($result2['amount'] == 0) {       //insert
                    $groups->insert(array('yearId' => $group['yearId'], 'name' => $group['name'], 'titularId' => $titularId, 'remark' => $group['remark']));
                }
                else {      //update
                    $groups->update(array('yearId' => $group['yearId'], 'titularId' => $titularId, 'remark' => $group['remark']), 'name = ' . $groups->_db->quote($group['name'], 'VARCHAR'));
                }
            }
            
        }
        catch (Exception $e) {
            $succes = false;
            print_r($e);
        }
        
        if ($succes) {
            $groups->_db->commit();
        }
        else {
            $groups->_db->rollBack();
            throw new Exception('update of ' . $information . ' failed', 151);
        }
    }
}

