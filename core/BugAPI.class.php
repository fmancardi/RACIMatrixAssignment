<?php
namespace RACIMatrixAssignment;

class BugAPI {

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
     */
    function reportBugForm( $p_event, $p_project_id ) {
      $idCard = array('project' => $p_project_id, 'bug_id' => null);
      $this->drawRACIResponsible($idCard);
      $this->drawRACIAccountable($idCard);
      $this->drawRACIConsulted($idCard);
      $this->drawRACIInformed($idCard);
    }

   /**
    *
    *
    */
    function reportBug( $p_event, $p_bug_data_obj, $p_bug_id ) {
        $this->saveRACIUsers( $p_event, $p_bug_id );
    }

    /**
     * 
     */
    function updateBugForm( $p_event, $p_bug_id ) {

      $t_project_id = bug_get_field( $p_bug_id, 'project_id' );
      $idCard = array('project' => $t_project_id, 'bug_id' => $p_bug_id);
      $this->drawRACIResponsible($idCard);
      $this->drawRACIAccountable($idCard);
      $this->drawRACIConsulted($idCard);
      $this->drawRACIInformed($idCard);
    }

    /**
     * 
     */
    function updateBug( $p_event, $p_bug, $p_updated_bug ) {
        $this->saveRACIUsers( $p_event, $p_bug->id );
    }

   /**
    *
    *
    */
    function deleteBug( $p_event, $p_bug_id ) {
        $table = plugin_table( 'bug' );
        $t_query = " DELETE FROM {$table} WHERE bug_id=" .db_param();
        db_query($t_query,array($p_bug_id));
    }


   /**
    *
    */
    function saveRACIUsers( $p_event, $p_bug_id ) {

        $table = plugin_table( 'bug' );
        db_param_push();
        $t_query = " SELECT * 
                     FROM {$table} WHERE bug_id=" . db_param();
        $t_result = db_query( $t_query, array( $p_bug_id ) );

        if( db_affected_rows() == 0 ) {
          $this->insertRACIUsers( $p_bug_id );
        } else {
          $this->updateRACIUsers( $p_bug_id, $t_result );          
        }      
    }


    /**
     *
     */
    function insertRACIUsers( $p_bug_id ) {
        $table = plugin_table( 'bug' );
        $userInput = $this->getUserInput();
        
        // Litte Magic Begin
        $t_raci_fields = implode(',',array_keys($this->fieldsKeys));

        $t_db_param = array(db_param()); 
        $t_sql_param = array($p_bug_id);
        foreach($this->fieldsKeys as $fieldName) {
            $t_db_param[] = db_param();
            $t_sql_param[] = intval($userInput[$fieldName]);
        }
        $t_db_param = implode(',',$t_db_param); 
        // Little Magic End
        
        $t_query = " INSERT INTO {$table}
                     (bug_id,{$t_raci_fields})
                     VALUES( {$t_db_param} )";
        db_query($t_query, $t_sql_param);             
    }

    /**
     *
     */
    function updateRACIUsers( $p_bug_id ) {
        $table = plugin_table( 'bug' );
        $userInput = $this->getUserInput();

        // Litte Magic Begin
        foreach($this->fieldsKeys as $fieldName) {
            $t_db_param[] = $fieldName . '=' . db_param();
            $t_sql_param[] = intval($userInput[$fieldName]);
        }
        $t_sql_param[] = $p_bug_id;
  
        $t_db_param = implode(',',$t_db_param); 
        // Little Magic End

        $t_query = " UPDATE {$table}
                     SET {$t_db_param} 
                     WHERE bug_id = " . db_param();
        
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


   /**
    * 
    */
    function drawRACIResponsible( $p_id_card ) {
        $this->drawRACIRole($p_id_card, 'raci_r');
    }

   /**
    * 
    */
    function drawRACIAccountable( $p_id_card ) {
        $this->drawRACIRole($p_id_card, 'raci_a');
    }

   /**
    * 
    */
    function drawRACIConsulted( $p_id_card ) {
        $this->drawRACIRole($p_id_card, 'raci_c');
    }

   /**
    * 
    */
    function drawRACIInformed( $p_id_card ) {
        $this->drawRACIRole($p_id_card, 'raci_i');
    }

   /**
    * 
    */
    function drawRACIRole( $p_id_card, $p_role ) {

        // To manage selected element in HTML select
        $t_raci_bug_row = array();
        if( ($t_bug_id = intval($p_id_card['bug_id'])) > 0 ) {
          $raci_bug = plugin_table('bug');
          $t_query = " SELECT * FROM {$raci_bug} 
                       WHERE bug_id=" . db_param();

          $t_result = db_query($t_query,array($t_bug_id));
          $t_raci_bug_row = db_fetch_array( $t_result );
        }

        // Get User Domain
        $canBeField = 'can_be_' . $p_role;
        $raci_users = plugin_table('users');
        $users = db_get_table('user');
        $t_query = " SELECT user_id,realname 
                     FROM {$raci_users} RACI
                     LEFT JOIN {$users} U ON U.id=RACI.user_id 
                     WHERE RACI.{$canBeField} = " . db_param();

        $t_result = db_query($t_query,array(1));
        if( db_affected_rows() == 0 ){
          return;
        }

        $t_users_with_role = array('' => '');
        while( $t_row = db_fetch_array( $t_result ) ) {
            $t_users_with_role[$t_row['user_id']] = $t_row['realname'];  
        }

        // drawComboRow($item_idcard,$item_set,$attr=null,$options=null)
        // $opt = array('lbl' => null, 'suffix' => '_code', 
        //              'input_prefix' => null, 'input_suffix' => null,
        //              'input_name' => null); 
        $itemID = 'user_id_' . $p_role;
        $dccrOpt = array('input_prefix' => $this->inputPrefix,
                         'access_key' => $itemID);

        $attr = $t_raci_bug_row;
        if( count($attr) != 0 ) {
          $dccrOpt['colspan'] = 5; // MAGIC after inspect MantisBT screen
          unset($attr['id']);
          unset($attr['bug_id']);
        }

        natcasesort($t_users_with_role);
        \helperMethodsPlugin::drawComboRow($itemID, $t_users_with_role,
          $attr,$dccrOpt);
    }




    /**
     * 
     */
    function bugDetails( $p_event, $p_bug_id ) {
    }

}
