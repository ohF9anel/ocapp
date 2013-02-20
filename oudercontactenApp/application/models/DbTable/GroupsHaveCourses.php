<?php
/**
 * Db class for groups_have_courses table
 */
class Application_Model_DbTable_GroupsHaveCourses extends Zend_Db_Table_Abstract
{
    protected $_name = 'groups_have_courses';

    
    /**
     * Function counts the amount of records in this table
     * @return int amount of records
     */
    public static function countEntries() {
        $groupHasCourse = new Application_Model_DbTable_GroupsHaveCourses();
        $select = $groupHasCourse->select();
        $select->from('groups_have_courses', 'count(*) As amount')
                ->setIntegrityCheck(false);
        $result = $groupHasCourse->fetchRow($select);
        
        return $result['amount'];
    }
    
    
    /**
     * Function deletes all data from groups_have_courses table
     */
    public static function deleteAll() {
        $groupHasCourse = new Application_Model_DbTable_GroupsHaveCourses();
        $groupHasCourse->delete('');
    }
    

    /**
     * Function updates groups_have_courses table based on a data array
     * @param array $data array of arrays with following structure array(('name', 'uuid'))
     * @throws Exception Er151 Update failed
     */
    public static function updateGroupsHaveCourses($data) {
        $groupsHaveCourses = new Application_Model_DbTable_GroupsHaveCourses();
        
        $succes = true;
        $information = '';
        $groupsHaveCourses->_db->beginTransaction();
        try {
            foreach ($data as $groupHasCourse) {
                $information = $groupHasCourse['name'] . '-' . $groupHasCourse['uuid'];
                
                $select1 = $groupsHaveCourses->select();
                $select1->from('groups', array('groupId'))
                        ->where('name = ?')
                        ->bind(array($groupHasCourse['name']))
                        ->setIntegrityCheck(false);
                $result1 = $groupsHaveCourses->fetchRow($select1);
                $groupId = $result1['groupId'];
                
                $select2 = $groupsHaveCourses->select();
                $select2->from('courses', array('courseId'))
                        ->where('uuid = ?')
                        ->bind(array($groupHasCourse['uuid']))
                        ->setIntegrityCheck(false);
                $result2 = $groupsHaveCourses->fetchRow($select2);
                $courseId = $result2['courseId'];
                
                $select3 = $groupsHaveCourses->select();
                $select3->from('groups_have_courses', 'count(*) As amount')
                        ->where('groupId = ? AND courseId = ?')
                        ->bind(array($groupId, $courseId))
                        ->setIntegrityCheck(false);
                $result3 = $groupsHaveCourses->fetchRow($select3);
                
                if ($result3['amount'] == 0) {
                    $groupsHaveCourses->insert(array('groupId' => $groupId, 'courseId' => $courseId));
                }
            }
        }
        catch (Exception $e) {
           $succes = false; 
        }
        
        if ($succes) {
            $groupsHaveCourses->_db->commit();
        }
        else {
            $groupsHaveCourses->_db->rollBack();
            throw new Exception('update of ' . $information . ' failed', 151);
        }
    }
}