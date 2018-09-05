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

    function register() {
        $this->name = plugin_lang_get( 'plugin_title' );
        $this->description = plugin_lang_get( 'plugin_description' );
        $this->page = '';

        $this->version = '1.0.0';
        $this->requires = array(
            'MantisCore' => '2.0.0',
            'helperMethods' => '1.0.1'
        );

        $this->author = 'Francisco Mancardi ';
        $this->contact = '';
        $this->url = '';
    }

    function hooks() {
        return array(
            'EVENT_MANAGE_USER_CREATE_FORM' => 'manageUserCreateForm',
            'EVENT_MANAGE_USER_CREATE' => 'saveRACIRolesUserCanPlay',
            'EVENT_MANAGE_USER_UPDATE_FORM' => 'manageUserUpdateForm',
            'EVENT_MANAGE_USER_UPDATE' => 'saveRACIRolesUserCanPlay',
            'EVENT_MANAGE_USER_DELETE' => 'deleteRACIRolesUserCanPlay'
        );
    }

    /**
     *
     */
    function config() {
        $cfg = self::getConfig();

        $this->whoami = str_replace('Plugin','',__CLASS__);
        $this->inputPrefix = $this->whoami . '_';

        $this->fieldsKeys = array('can_be_raci_r' => null,
                                  'can_be_raci_a' => null,
                                  'can_be_raci_c' => null,
                                  'can_be_raci_i' => null);
        foreach($this->fieldsKeys as $key => $val) {
            $this->fieldsKeys[$key] = $key;
        } 
        
        $this->inputs = $this->fieldsKeys; 
        foreach($this->inputs as $t_ty => $t_vv ) {
            $this->inputs[$t_ty] =  $this->inputPrefix . $t_ty;
        }

        return $cfg;
    }

    /**
     *
     */
    function init() {
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
        $this->raciInputs();
    }

    /**
     *
     *
     */
    function manageUserUpdateForm( $p_event, $p_user_id = null ) {
        $this->raciInputs($p_user_id,'edit');
    }


    /**
     *
     *
     */
    function raciInputs( $p_user_id = null, $p_operation = null ) {
        
        echo __FUNCTION__;


        $table = plugin_table( 'users' );
        $attr = array();
        switch( $p_operation ) {
            case 'edit':
                $str_open = $str_close = '';
                $t_query = " SELECT * FROM {$table} 
                             WHERE user_id=" . db_param();

                $t_sql_param = array( $p_user_id );
                $t_result = db_query( $t_query, $t_sql_param);
                
                if( db_affected_rows() == 0 ) {
                  // Transform operation in create
                    $attr = array();
                    foreach($this->fieldsKeys as $fieldName) {
                      $attr[$fieldName] = 0;
                    }
                } else {
                    $t_row = db_fetch_array( $t_result );
                    foreach($this->fieldsKeys as $fieldName) {
                      $attr[$fieldName] = $t_row[$fieldName];
                    }
                } 

            break;

            case 'create':
            default:
                $str_open = ' <p><table class="table table-bordered ' .
                            ' table-condensed table-striped">' . '<fieldset>';
                $str_close = '</fieldset></table>';
                $attr = array();
                foreach($this->fieldsKeys as $fieldName) {
                  $attr[$fieldName] = 0;
                }
            break;
        }
        echo $str_open;
        $dgOpt = array('input_prefix' => $this->inputPrefix);
        foreach($attr as $key => $val) {
            $xx = array($key => $val);
            helperMethodsPlugin::drawGenericYesNoComboRow( $xx, $dgOpt );
        }
        echo $str_close;
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
          $this->insertRACIRoles( $p_user_id );
        } else {
          $this->updateRACIRoles( $p_user_id, $t_result );          
        }
        
    }

    /**
     *
     */
    function getUserInput() {      
        $userInput = array();
        foreach($this->inputs as $t_ty => $t_vv ) {  
            $accessKey = $this->inputPrefix . $t_ty;

            $userInput[$t_ty] = 0;  
            if( isset($_REQUEST[$accessKey]) ) {
                $userInput[$t_ty] = intval($_REQUEST[$accessKey]);
            }         
        }
        return $userInput;
    }

    /**
     *
     */
    function insertRACIRoles($p_user_id) {
        $table = plugin_table( 'users' );
        $userInput = $this->getUserInput();
        
        // Litte Magic Begin
        $t_raci_fields = implode(',',array_keys($this->fieldsKeys));

        $t_db_param = array(db_param()); 
        $t_sql_param = array($p_user_id);
        foreach($this->fieldsKeys as $fieldName) {
            $t_db_param[] = db_param();
            $t_sql_param[] = intval($userInput[$fieldName]);
        }
        $t_db_param = implode(',',$t_db_param); 
        // Little Magic End
        
        $t_query = " INSERT INTO {$table}
                     (user_id,{$t_raci_fields})
                     VALUES( {$t_db_param} )";
        db_query($t_query, $t_sql_param);             
    }

    /**
     *
     */
    function updateRACIRoles($p_user_id) {
        $table = plugin_table( 'users' );
        $userInput = $this->getUserInput();

        // Litte Magic Begin
        foreach($this->fieldsKeys as $fieldName) {
            $t_db_param[] = $fieldName . '=' . db_param();
            $t_sql_param[] = intval($userInput[$fieldName]);
        }
        $t_sql_param[] = $p_user_id;
  
        $t_db_param = implode(',',$t_db_param); 
        // Little Magic End

        $t_query = " UPDATE {$table}
                     SET {$t_db_param} 
                     WHERE user_id = " . db_param();
        
        db_query($t_query, $t_sql_param);            
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

      return $t_schema;
    } 

} 