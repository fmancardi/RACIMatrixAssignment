<?php
/*
   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.

*/
class RACIMatrixAssignmentPlugin extends MantisPlugin {
    
    var $whoami;
    var $fieldsKeys;
    var $inputs;
    var $inputPrefix;
    var $bugAPI;
    var $userAPI;

    function register() {
        $this->name = plugin_lang_get( 'plugin_title' );
        $this->description = plugin_lang_get( 'plugin_description' );
        $this->page = '';

        $this->version = '1.0.1';
        $this->requires = array(
            'MantisCore' => '2.0.0',
            'helperMethods' => '1.0.1'
        );

        $this->author = 'Francisco Mancardi ';
        $this->contact = '';
        $this->url = '';
    }

    /**
     *
     * Custom Events
     * 
     */
    function events(){
      return array(
        'EVENT_REPORT_BUG_FORM_BEFORE_CUSTOM_FIELDS' => EVENT_TYPE_EXECUTE,
        'EVENT_UPDATE_BUG_FORM_BEFORE_CUSTOM_FIELDS' => EVENT_TYPE_EXECUTE
      );
    }


    /**
     *
     *
     */ 
    function hooks() {
        return array(
            'EVENT_MANAGE_USER_CREATE_FORM' => 'manageUserCreateForm',
            'EVENT_MANAGE_USER_CREATE' => 'saveRACIRolesUserCanPlay',
            'EVENT_MANAGE_USER_UPDATE_FORM' => 'manageUserUpdateForm',
            'EVENT_MANAGE_USER_UPDATE' => 'saveRACIRolesUserCanPlay',
            'EVENT_MANAGE_USER_DELETE' => 'deleteRACIRolesUserCanPlay',
            
            'EVENT_REPORT_BUG_FORM_BEFORE_CUSTOM_FIELDS' => 'reportBugForm',
            'EVENT_UPDATE_BUG_FORM_BEFORE_CUSTOM_FIELDS' => 'updateBugForm',
            'EVENT_VIEW_BUG_DETAILS' => 'bugDetails',
            'EVENT_REPORT_BUG'  => 'reportBug',
            'EVENT_UPDATE_BUG'  => 'updateBug',
            'EVENT_BUG_DELETED'  => 'deleteBug',


        );
    }

    /**
     *
     */
    function config() {
        $cfg = self::getConfig();

        $this->whoami = str_replace('Plugin','',__CLASS__);
        $this->inputPrefix = $this->whoami . '_';

        $this->fieldsKeysFor = array('user' => array(), 'bug' => array());
        $prefix = array('user' => 'can_be_raci_', 'bug' => 'user_id_raci_');
        $raci = explode(',','r,a,c,i');
        foreach($raci as $role) {
            foreach ($prefix as $artifact => $pfx) {
                $pfx .= $role;
                $this->fieldsKeysFor[$artifact][$pfx] = $pfx;
            }
        }
        
        $this->inputsFor = $this->fieldsKeysFor;
        foreach($this->inputsFor as $artifact => $in) {
            foreach($in as $t_ty => $t_tv) {
                $this->inputsFor[$artifact][$t_ty] =  
                    $this->inputPrefix . $t_ty;
            }
        }

        return $cfg;
    }

    /**
     *
     * 
     */
    function init() {
        plugin_require_api('core/BugAPI.class.php');
        plugin_require_api('core/UserAPI.class.php');

        $obj = new stdClass();
        $obj->fieldsKeys = $this->fieldsKeysFor['bug'];
        $obj->inputs = $this->inputsFor['bug'];
        $obj->inputPrefix = $this->inputPrefix;
        $this->bugAPI = new RACIMatrixAssignment\BugAPI( $obj );
        
        $obj = new stdClass();
        $obj->fieldsKeys = $this->fieldsKeysFor['user'];
        $obj->inputs = $this->inputsFor['user'];
        $obj->inputPrefix = $this->inputPrefix;
        $this->userAPI = new RACIMatrixAssignment\UserAPI( $obj );
    }


    /**
     *
     */
    static function getConfig() {
        $cfg = array();
        return $cfg;
    }

    /**
     *
     *
     */ 
    function manageUserCreateForm( $p_event, $p_user_id = null ) {
        $this->userAPI->raciInputs();
    }

    /**
     *
     *
     */
    function manageUserUpdateForm( $p_event, $p_user_id = null ) {
        $this->userAPI->raciInputs($p_user_id,'edit');
    }



  
   /**
    *
    */
    function getTableComboI18N($p_table,$opt_blank=false) {
        $t_debug = '/* ' . __METHOD__ . ' */ ';

        $t_rs = array();
        $t_query = $t_debug . " SELECT id, label FROM $p_table ";
        $t_result = db_query( $t_query, array());
    
        if($opt_blank) {
          $t_rs[''] = ''; 
        }
        
        while ( $t_row = db_fetch_array( $t_result ) ) {
            $t_rs[$t_row['id']] = plugin_lang_get($t_row['label']); 
        }
        natsort($t_rs);

        return $t_rs;
    }

   /**
    *
    */
    function saveRACIRolesUserCanPlay( $p_event, $p_user_id ) {

        $table = plugin_table( 'users' );
        db_param_push();
        $t_query = " SELECT user_id 
                     FROM {$table} WHERE user_id=" . db_param();
        $t_result = db_query( $t_query, array( $p_user_id ) );

        if( db_affected_rows() == 0 ) {
          $this->userAPI->insertRACIRoles( $p_user_id );
        } else {
          $this->userAPI->updateRACIRoles( $p_user_id, $t_result );          
        }
        
    }

    /**
     *
     */ 
    function deleteRACIRolesUserCanPlay($p_event, $p_user_id) {

        $t_debug = '/* ' . __METHOD__ . ' */ ';

        $table = plugin_table( 'users' );
        $t_query = " $t_debug DELETE FROM {$table} 
                     WHERE user_id=" . db_param();

        $t_sql_param = array($p_user_id);        
        db_query($t_query,$t_sql_param);             
    }    

   /**
    *
    *
    */
    function reportBugForm( $p_event, $p_project_id ) {
        $m = __FUNCTION__;
        $this->bugAPI->$m( $p_event, $p_project_id );
    }
      
   /**
    *
    *
    */
    function reportBug( $p_event, $p_bug_data_obj, $p_bug_id ) {
        $m = __FUNCTION__;
        $this->bugAPI->$m( $p_event, $p_bug_data_obj, $p_bug_id );
    }

   /**
    *
    *
    */
    function updateBugForm( $p_event, $p_bug_id ) {
        $m = __FUNCTION__;
        $this->bugAPI->$m( $p_event, $p_bug_id );
    }
      
   /**
    *
    *
    */
    function updateBug( $p_event, $p_bug, $p_updated_bug ) {
        $m = __FUNCTION__;
        $this->bugAPI->$m( $p_event, $p_bug, $p_updated_bug );
    }

   /**
    *
    *
    */
    function deleteBug( $p_event, $p_bug_id ) {
        $m = __FUNCTION__;
        $this->bugAPI->$m( $p_event, $p_bug_id );
    }


   /** 
    *  Schema
    * 
    */
    function schema() {
      $t_schema = array();

      // 
      $t_table = plugin_table( 'users' );
      $t_ddl = " id  I   NOTNULL UNSIGNED PRIMARY AUTOINCREMENT,
                 user_id I   UNSIGNED NOTNULL DEFAULT '0',
                 can_be_raci_r I UNSIGNED NOTNULL DEFAULT '0' ,
                 can_be_raci_a I UNSIGNED NOTNULL DEFAULT '0' ,
                 can_be_raci_c I UNSIGNED NOTNULL DEFAULT '0' ,
                 can_be_raci_i I UNSIGNED NOTNULL DEFAULT '0' ";

      $t_schema[] = array( 'CreateTableSQL',
                           array($t_table , $t_ddl) );

      $t_schema[] = array( 'CreateIndexSQL', 
                           array( 'idx_user_raci', 
                                  $t_table, 
                                  'user_id', array( 'UNIQUE' ) ) );

      // 
      $t_table = plugin_table( 'bug' );
      $t_ddl = " id  I   NOTNULL UNSIGNED PRIMARY AUTOINCREMENT,
                 bug_id I   UNSIGNED NOTNULL DEFAULT '0',
                 user_id_raci_r I UNSIGNED NOTNULL DEFAULT '0' ,
                 user_id_raci_a I UNSIGNED NOTNULL DEFAULT '0' ,
                 user_id_raci_c I UNSIGNED NOTNULL DEFAULT '0' ,
                 user_id_raci_i I UNSIGNED NOTNULL DEFAULT '0' ";

      $t_schema[] = array( 'CreateTableSQL',
                           array($t_table , $t_ddl) );

      $t_schema[] = array( 'CreateIndexSQL', 
                           array( 'idx_bug_raci', 
                                  $t_table, 
                                  'bug_id', array( 'UNIQUE' ) ) );

      return $t_schema;
    } 
} 