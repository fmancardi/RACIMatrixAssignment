<?php
namespace RACIMatrixAssignment;

class UserAPI {

    var $fieldsKeys;
    var $inputPrefix;
    var $inputs;

    /**
     *
     *
     */
    function __construct( $cfgObj ) {
        $this->fieldsKeys = $cfgObj->fieldsKeys;
        $this->inputPrefix = $cfgObj->inputPrefix;
        $this->inputs = $cfgObj->inputs;
    } 

    /**
     *
     *
     */
    function raciInputs( $p_user_id = null, $p_operation = null ) {
        
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
            \helperMethodsPlugin::drawGenericYesNoComboRow( $xx, $dgOpt );
        }
        echo $str_close;
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


}
