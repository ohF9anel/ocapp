<?php
/**
 * Db class for childrelations table
 */
class Application_Model_DbTable_Childrelations extends Zend_Db_Table_Abstract
{
    protected $_name = 'childrelations';

    

    /**
     * Function returns all childIds of a parent based on a specified  parent
     * @param int $parentId parentId to identify parent
     * @return array of childIds
     */
    public function getChildrenBasedOnParentId($parentId) {
        $parentId = (int) $parentId;
        
        $children = array();
        $select = $this->select();
        $select->from('childrelations', array('childId'))
                ->where('parentId = ?')
                ->bind(array($parentId))
                ->setIntegrityCheck(false);
        $results = $this->fetchAll($select);
        
        foreach ($results as $result) {
            $children[] = $result['childId'];
        }
        
        return $children;
    }
    
    
    
    /**
     * Function counts the amount of records in this table
     * @return int amount of records
     */
    public static function countEntries() {
        $childrelation = new Application_Model_DbTable_Childrelations();
        $select = $childrelation->select();
        $select->from('childrelations', 'count(*) As amount')
                ->setIntegrityCheck(false);
        $result = $childrelation->fetchRow($select);
        
        return $result['amount'];
    }
    
    
    /**
     * Function deletes all data from childrelations table 
     */
    public static function deleteAll() {
        $childrelation = new Application_Model_DbTable_Childrelations();
        $childrelation->delete('');
    }
    
    
    /**
     * Function updates childrelations table based on a data array
     * @param array $data array of arrays with following structure array(('uuid', 'name', 'firstname', 'groupName))
     * @throws Exception Er151 Update failed
     */
    public static function updateChildrelations($data) {
        $childrelations = new Application_Model_DbTable_Childrelations();
        
        $succes = true;
        $information = '';
        $childrelations->_db->beginTransaction();
        try {
            foreach ($data as $relation) {
                $information = $relation['login'] . '-' . $relation['uuid'];
                
                $select1 = $childrelations->select();
                $select1->from('parents', array('parentId'))
                        ->where('login = ?')
                        ->bind(array($relation['login']))
                        ->setIntegrityCheck(false);
                $result1 = $childrelations->fetchRow($select1);
                $parentId = $result1['parentId'];
                
                $select2 = $childrelations->select();
                $select2->from('pupils', array('pupilId'))
                        ->where('uuid = ?')
                        ->bind(array($relation['uuid']))
                        ->setIntegrityCheck(false);
                $result2 = $childrelations->fetchRow($select2);
                $pupilId = $result2['pupilId'];
                
                $select3 = $childrelations->select();
                $select3->from('childrelations', 'count(*) As amount')
                        ->where('parentId = ? AND childId = ?')
                        ->bind(array($parentId, $pupilId))
                        ->setIntegrityCheck(false);
                $result3 = $childrelations->fetchRow($select3);
                
                if ($result3['amount'] == 0) {
                    $childrelations->insert(array('parentId' => $parentId, 'childId' => $pupilId));
                }
            }
        }
        catch (Exception $e) {
            $succes = false;
        }
        
        if ($succes) {
            $childrelations->_db->commit();
        }
        else {
            $childrelations->_db->rollBack();
            throw new Exception('update of ' . $information . ' failed', 151);
        }
    }
}

