<?php
/**
 * Db class for rights table 
 */
class Application_Model_DbTable_Rights extends Zend_Db_Table_Abstract
{
    protected $rightId, $staffId, $accessebilityLevel;
    protected $_name = 'rights';

    
    
    public function __construct($rightId = null, $staffId = null, $accessebilitLevel = null, $config = array()) {
        parent::__construct($config);
        $this->rightId = (int) $rightId;
        $this->staffId = (int) $staffId;
        $this->accessebilityLevel = (int) $accessebilitLevel;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getAccessebilityLevel() {
        return $this->accessebilityLevel;
    }
    
    public function getRightId() {
        return $this->rightId;
    }
    
    public function getStaffId() {
        return $this->staffId;
    }
    
    
    
    /**
     * Function saves/updates right object to database if all required fields have a value
     * @return int insert/update id
     * @throws Exception Er150 Null in non optional field
     */
    public function save() {
        if ($this->staffId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        if ($this->accessebilityLevel == 0) {
            $this->delete('rightId = ' . $this->_db->quote($this->rightId, 'INT'));
        } else {
            $select = $this->select();
            $select->from('rights', 'count(*) AS amount')
                    ->where('rightId = ?')
                    ->bind(array($this->rightId));
            $result = $this->fetchRow($select);
            
            if ($result['amount'] != 0) {
                $this->update(array('accessebilityLevel' => $this->accessebilityLevel), 'rightId = ' . $this->_db->quote($this->rightId, 'INT'));
                return $this->rightId;
            }

            return $this->insert(array('staffId' => $this->staffId, 'accessebilityLevel' => $this->accessebilityLevel));
        }
    }
    
    
    
    /**
     * Function looks rights of specified staffmember and returns its as an object
     * @param int $staffId staffId to identify staffmember
     * @return \Application_Model_DbTable_Rights
     * @throws Exception Er126 Not enough rights
     */
    public static function Authorize($staffId) {
        $staffId = (int) $staffId;
        
        $right = new Application_Model_DbTable_Rights();
        $select = $right->select();
        $select->from('rights', '*')
                ->where('staffId = ?')
                ->bind(array($staffId));
        $result = $right->fetchAll($select);
        
        if (sizeof($result) < 1) {
            throw new Exception('Er126 Not enough rights', 126);
        }
        
        return new Application_Model_DbTable_Rights($result[0]['rightId'], $result[0]['staffId'], $result[0]['accessebilityLevel']);
    }
    
    
    /**
     * Function deletes all data from rights table except for the given staffId
     * @param int $staffId staffmember you do not want to delete
     */
    public static function deleteAllExcept($staffId) {
        $right = new Application_Model_DbTable_Rights();
        $right->delete('staffId != ' . $right->_db->quote($staffId, 'INT'));
    }
    
    
    /**
     * Function looks up the rights of the specified staffmember
     * @param int $staffId staffId to identify staffmember
     * @return int right
     */
    public static function getAccessebilityLevelByStaffId($staffId) {
        $staffId = (int) $staffId;
        $right = new Application_Model_DbTable_Rights();
        
        $select = $right->select();
        $select->from('rights', array('accessebilityLevel'))
                ->where('staffId = ?')
                ->bind(array($staffId));
        $result = $right->fetchRow($select);
        
        return (sizeof($result) == 1 ? $result['accessebilityLevel'] : 0);
    }
    
    
    
    /**
     * Function returns staffRight object for all staffmembers
     * @return \Application_Model_StaffRights 
     */
    public static function getAllStaffRights() {
        $right = new Application_Model_DbTable_Rights();
        
        $select = $right->select();
        $select->from('staff', array('staffId', 'name', 'firstname'))
                ->joinLeft('rights', 'staff.staffId = rights.staffId', array('rightId', 'accessebilityLevel'))
                ->setIntegrityCheck(false);
        $results = $right->fetchAll($select);
        
        $staffRights = array();
        foreach($results as $result) {
            $staffRights[] = new Application_Model_StaffRights($result['rightId'], $result['staffId'], $result['name'], $result['firstname'], (int) $result['accessebilityLevel']);
        }
        return $staffRights;
    }
}

